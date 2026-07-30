<?php
// resources/views/pages/transaksi/ri/emr-ri/penilaian/nyeri-ri/rm-penilaian-nyeri-ri-actions.blade.php

use Livewire\Component;
use App\Http\Traits\Txn\Ri\EmrRITrait;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;
use App\Http\Traits\WithValidationToast\WithValidationToastTrait;
use App\Support\NyeriOptions;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;

new class extends Component {
    use EmrRITrait, WithRenderVersioningTrait, WithValidationToastTrait;

    public bool $isFormLocked = false;
    public ?string $riHdrNo = null;
    public array $dataDaftarRi = [];

    // Umur pasien (tahun) utk menyarankan skala yang sesuai — hanya saran, tidak memaksa.
    public ?int $umurPasienTahun = null;
    public array $skalaDisarankan = [];

    public array $renderVersions = [];
    protected array $renderAreas = ['modal-penilaian-nyeri-ri'];

    public array $formEntryNyeri = [
        'tglPenilaian' => '',
        'petugasPenilai' => '',
        'petugasPenilaiCode' => '',
        'nyeri' => [
            'nyeri' => 'Tidak',
            'nyeriMetode' => ['nyeriMetode' => '', 'nyeriMetodeScore' => 0, 'dataNyeri' => []],
            'nyeriKet' => '',
            'pencetus' => '',
            'durasi' => '',
            'lokasi' => '',
            'waktuNyeri' => '',
            'tingkatKesadaran' => '',
            'tingkatAktivitas' => '',
            'sistolik' => '',
            'distolik' => '',
            'frekuensiNafas' => '',
            'frekuensiNadi' => '',
            'suhu' => '',
            'ketIntervensiFarmakologi' => '',
            'ketIntervensiNonFarmakologi' => '',
            'catatanTambahan' => '',
        ],
    ];

    /* Definisi skala (sasaran populasi, rentang, item, interpretasi) — App\Support\NyeriOptions. */
    #[Computed]
    public function daftarSkala(): array
    {
        return NyeriOptions::SKALA;
    }

    /* Definisi skala yang sedang dipakai; null bila metode belum dipilih. */
    #[Computed]
    public function skalaTerpilih(): ?array
    {
        return NyeriOptions::skala($this->formEntryNyeri['nyeri']['nyeriMetode']['nyeriMetode'] ?? '');
    }

    /* Interpretasi skor berjalan: label, tingkat, warna badge, tata laksana. */
    #[Computed]
    public function interpretasiBerjalan(): array
    {
        return NyeriOptions::interpretasi($this->formEntryNyeri['nyeri']['nyeriMetode']['nyeriMetode'] ?? '', $this->formEntryNyeri['nyeri']['nyeriMetode']['nyeriMetodeScore'] ?? null);
    }

    public function mount(): void
    {
        $this->registerAreas(['modal-penilaian-nyeri-ri']);
    }

    #[On('open-rm-penilaian-nyeri-ri')]
    public function open(string $riHdrNo): void
    {
        if (empty($riHdrNo)) {
            return;
        }

        $this->riHdrNo = $riHdrNo;
        $this->resetForm();
        $this->resetValidation();

        $data = $this->findDataRI($riHdrNo);
        if (!$data) {
            $this->dispatch('toast', type: 'error', message: 'Data RI tidak ditemukan.');
            return;
        }

        $this->dataDaftarRi = $data;
        $this->dataDaftarRi['penilaian']['nyeri'] ??= [];

        $this->isFormLocked = $this->checkEmrRIStatus($riHdrNo);

        $this->umurPasienTahun = $this->hitungUmurPasien($data['regNo'] ?? null);
        $this->skalaDisarankan = NyeriOptions::saranUntukUmur($this->umurPasienTahun);

        $this->incrementVersion('modal-penilaian-nyeri-ri');
    }

    /*
     | Umur pasien dalam tahun, dihitung on-the-fly dari birth_date (kolom umur
     | di master hanya snapshot saat pendaftaran). Null bila tgl lahir kosong —
     | saran skala sekadar tidak muncul, form tetap jalan.
     */
    private function hitungUmurPasien(?string $regNo): ?int
    {
        if (empty($regNo)) {
            return null;
        }

        $birthDate = DB::table('rsmst_pasiens')->where('reg_no', $regNo)->value('birth_date');
        if (empty($birthDate)) {
            return null;
        }

        try {
            return (int) Carbon::parse($birthDate)->diffInYears(Carbon::now(config('app.timezone')));
        } catch (\Throwable) {
            return null;
        }
    }

    public function setTglPenilaianNyeri(): void
    {
        $this->formEntryNyeri['tglPenilaian'] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
    }

    /* Ganti metode → bangun ulang kerangka item/pilihan skala baru, skor & keterangan direset. */
    public function updatedFormEntryNyeriNyeriNyeriMetodeNyeriMetode(string $value): void
    {
        $this->formEntryNyeri['nyeri']['nyeriMetode']['dataNyeri'] = NyeriOptions::kerangkaData($value);
        $this->formEntryNyeri['nyeri']['nyeriMetode']['nyeriMetodeScore'] = 0;
        $this->formEntryNyeri['nyeri']['nyeriKet'] = '';
    }

    /* Skala tipe 'pilih' (VAS, Wong-Baker): satu nilai dipilih langsung. */
    public function updateSkorSkala(int $skor): void
    {
        foreach ($this->formEntryNyeri['nyeri']['nyeriMetode']['dataNyeri'] as &$opsi) {
            $opsi['active'] = (int) $opsi['score'] === $skor;
        }
        unset($opsi);

        $this->formEntryNyeri['nyeri']['nyeriMetode']['nyeriMetodeScore'] = $skor;
        $this->sinkronKetNyeri();
    }

    /* Skala tipe 'item' (FLACC, NIPS, BPS, CPOT, PAINAD): skor = jumlah item terpilih. */
    public function updateSkorItem(string $kategori, int $skor): void
    {
        if (!isset($this->formEntryNyeri['nyeri']['nyeriMetode']['dataNyeri'][$kategori]['opsi'])) {
            return;
        }

        foreach ($this->formEntryNyeri['nyeri']['nyeriMetode']['dataNyeri'][$kategori]['opsi'] as &$opsi) {
            $opsi['active'] = (int) $opsi['score'] === $skor;
        }
        unset($opsi);

        $this->formEntryNyeri['nyeri']['nyeriMetode']['nyeriMetodeScore'] = NyeriOptions::totalSkorItem($this->formEntryNyeri['nyeri']['nyeriMetode']['dataNyeri']);
        $this->sinkronKetNyeri();
    }

    /*
     | Skor diketik manual (NRS) — clamp ke rentang skala lalu hitung ulang keterangan.
     | Tanpa hook ini, keterangan tertinggal di nilai lama (mis. skor 8 berlabel
     | "Tidak Nyeri") dan nilai keliru itu ikut tersimpan ke JSON EMR.
     */
    public function updated(string $property): void
    {
        if ($property !== 'formEntryNyeri.nyeri.nyeriMetode.nyeriMetodeScore') {
            return;
        }

        $rentang = NyeriOptions::rentang($this->formEntryNyeri['nyeri']['nyeriMetode']['nyeriMetode'] ?? '');
        $skor = (int) ($this->formEntryNyeri['nyeri']['nyeriMetode']['nyeriMetodeScore'] ?? 0);
        $this->formEntryNyeri['nyeri']['nyeriMetode']['nyeriMetodeScore'] = max($rentang['min'], min($rentang['max'], $skor));
        $this->sinkronKetNyeri();
    }

    /*
     | Interpretasi satu entri riwayat — dihitung ulang dari metode + skor tersimpan,
     | bukan dari nyeriKet. Entri lama sempat menyimpan keterangan yang tidak ikut
     | skor (mis. NRS 8 tersimpan "Tidak Nyeri"); dengan dihitung ulang, riwayat
     | menampilkan interpretasi yang benar. Bila skor di luar rentang skala
     | (data lama BPS/NIPS), nilai tersimpan dipakai apa adanya.
     */
    public function interpretasiEntri(array $entri): array
    {
        $hasil = NyeriOptions::interpretasi(data_get($entri, 'nyeri.nyeriMetode.nyeriMetode'), data_get($entri, 'nyeri.nyeriMetode.nyeriMetodeScore'));

        if ($hasil['tingkat'] === '' && filled(data_get($entri, 'nyeri.nyeriKet'))) {
            $hasil['label'] = data_get($entri, 'nyeri.nyeriKet');
        }

        return $hasil;
    }

    /* Satu-satunya tempat keterangan nyeri dihitung — selalu turunan dari metode + skor. */
    private function sinkronKetNyeri(): void
    {
        $this->formEntryNyeri['nyeri']['nyeriKet'] = NyeriOptions::interpretasi($this->formEntryNyeri['nyeri']['nyeriMetode']['nyeriMetode'] ?? '', $this->formEntryNyeri['nyeri']['nyeriMetode']['nyeriMetodeScore'] ?? null)['label'];
    }

    #[On('save-rm-penilaian-nyeri-ri')]
    public function addAssessmentNyeri(): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Pasien sudah pulang.');
            return;
        }

        $this->formEntryNyeri['petugasPenilai'] = auth()->user()->myuser_name;
        $this->formEntryNyeri['petugasPenilaiCode'] = auth()->user()->myuser_code;

        // Auto-fill tanggal kalau Tidak nyeri & tgl kosong (UI tgl hanya tampil saat Ya).
        if (($this->formEntryNyeri['nyeri']['nyeri'] ?? '') !== 'Ya' && empty($this->formEntryNyeri['tglPenilaian'])) {
            $this->setTglPenilaianNyeri();
        }

        // Skor divalidasi terhadap rentang skala yang dipilih — BPS 3–12, NIPS 0–7,
        // CPOT 0–8, sisanya 0–10. Tanpa ini skor di luar akal (mis. 50) ikut tersimpan.
        $metode = $this->formEntryNyeri['nyeri']['nyeriMetode']['nyeriMetode'] ?? '';
        $rentang = NyeriOptions::rentang($metode);

        $this->validateWithToast(
            [
                'formEntryNyeri.nyeri.nyeri' => 'required|in:Ya,Tidak',
                'formEntryNyeri.tglPenilaian' => 'required|date_format:d/m/Y H:i:s',
                'formEntryNyeri.nyeri.nyeriMetode.nyeriMetode' => 'required_if:formEntryNyeri.nyeri.nyeri,Ya|nullable|in:' . implode(',', array_keys(NyeriOptions::SKALA)),
                'formEntryNyeri.nyeri.nyeriMetode.nyeriMetodeScore' => 'required_if:formEntryNyeri.nyeri.nyeri,Ya|nullable|numeric|min:' . $rentang['min'] . '|max:' . $rentang['max'],
                'formEntryNyeri.nyeri.sistolik' => 'required_if:formEntryNyeri.nyeri.nyeri,Ya|nullable|numeric|min:0|max:300',
                'formEntryNyeri.nyeri.distolik' => 'required_if:formEntryNyeri.nyeri.nyeri,Ya|nullable|numeric|min:0|max:200',
                'formEntryNyeri.nyeri.frekuensiNafas' => 'required_if:formEntryNyeri.nyeri.nyeri,Ya|nullable|numeric|min:0|max:100',
                'formEntryNyeri.nyeri.frekuensiNadi' => 'required_if:formEntryNyeri.nyeri.nyeri,Ya|nullable|numeric|min:0|max:200',
                'formEntryNyeri.nyeri.suhu' => 'required_if:formEntryNyeri.nyeri.nyeri,Ya|nullable|numeric|min:30|max:45',
            ],
            [
                'required' => ':attribute wajib diisi.',
                'required_if' => ':attribute wajib diisi saat Status Nyeri = Ya.',
                'in' => ':attribute harus salah satu dari: :values.',
                'numeric' => ':attribute harus berupa angka.',
                'min' => ':attribute minimal :min.',
                'max' => ':attribute maksimal :max.',
                'date_format' => ':attribute harus format dd/mm/yyyy hh:mm:ss.',
            ],
            [
                'formEntryNyeri.nyeri.nyeri' => 'Status Nyeri',
                'formEntryNyeri.tglPenilaian' => 'Tanggal Penilaian',
                'formEntryNyeri.nyeri.nyeriMetode.nyeriMetode' => 'Metode Penilaian',
                'formEntryNyeri.nyeri.nyeriMetode.nyeriMetodeScore' => 'Skor ' . ($metode ?: 'Nyeri'),
                'formEntryNyeri.nyeri.sistolik' => 'Sistolik',
                'formEntryNyeri.nyeri.distolik' => 'Diastolik',
                'formEntryNyeri.nyeri.frekuensiNafas' => 'Frekuensi Nafas',
                'formEntryNyeri.nyeri.frekuensiNadi' => 'Frekuensi Nadi',
                'formEntryNyeri.nyeri.suhu' => 'Suhu',
            ],
        );

        // Skala tipe 'item' (FLACC/NIPS/BPS/CPOT/PAINAD) wajib dinilai lengkap —
        // total dari sebagian aspek bukan skor yang sah.
        if (($this->formEntryNyeri['nyeri']['nyeri'] ?? '') === 'Ya' && (NyeriOptions::skala($metode)['tipe'] ?? '') === 'item') {
            $belum = NyeriOptions::itemBelumDinilai($this->formEntryNyeri['nyeri']['nyeriMetode']['dataNyeri'] ?? []);
            if (!empty($belum)) {
                $this->dispatch('toast', type: 'warning', message: 'Aspek ' . $metode . ' belum dinilai: ' . implode(', ', $belum) . '.');
                return;
            }
        }

        // Keterangan nyeri selalu diturunkan ulang sesaat sebelum simpan.
        $this->sinkronKetNyeri();

        try {
            DB::transaction(function () {
                $this->lockRIRow($this->riHdrNo);

                $fresh = $this->findDataRI($this->riHdrNo) ?? [];
                $fresh['penilaian']['nyeri'][] = $this->formEntryNyeri;
                $this->updateJsonRI((int) $this->riHdrNo, $fresh);
                $this->appendAdminLogRI((int) $this->riHdrNo, 'Tambah Penilaian Nyeri — ' . ($this->formEntryNyeri['tglPenilaian'] ?? '-'), 'MR');
                $this->dataDaftarRi = $fresh;
            });
            $this->reset(['formEntryNyeri']);
            $this->afterSave('Penilaian Nyeri berhasil disimpan.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal: ' . $e->getMessage());
        }
    }

    public function removeAssessmentNyeri(int $index): void
    {
        if ($this->isFormLocked) {
            return;
        }

        try {
            DB::transaction(function () use ($index) {
                $this->lockRIRow($this->riHdrNo);

                $fresh = $this->findDataRI($this->riHdrNo) ?? [];
                $tglHapus = $fresh['penilaian']['nyeri'][$index]['tglPenilaian'] ?? '-';
                array_splice($fresh['penilaian']['nyeri'], $index, 1);
                $fresh['penilaian']['nyeri'] = array_values($fresh['penilaian']['nyeri']);
                $this->updateJsonRI((int) $this->riHdrNo, $fresh);
                $this->appendAdminLogRI((int) $this->riHdrNo, 'Hapus Penilaian Nyeri — entri ' . $tglHapus, 'MR');
                $this->dataDaftarRi = $fresh;
            });
            $this->afterSave('Penilaian Nyeri dihapus.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal: ' . $e->getMessage());
        }
    }

    private function afterSave(string $msg): void
    {
        $this->incrementVersion('modal-penilaian-nyeri-ri');
        $this->dispatch('penilaian-ri-saved', riHdrNo: $this->riHdrNo);
        $this->dispatch('refresh-after-ri.saved', tab: 'penilaian', subTab: 'nyeri');
        $this->dispatch('toast', type: 'success', message: $msg);
    }

    protected function resetForm(): void
    {
        $this->resetVersion();
        $this->isFormLocked = false;
        $this->reset(['formEntryNyeri']);
    }
};
?>

<div wire:key="{{ $this->renderKey('modal-penilaian-nyeri-ri', [$riHdrNo ?? 'new']) }}" class="space-y-4">

    @if (!$isFormLocked)
        <div class="space-y-4">

                <div @class([
                    'grid gap-4',
                    'grid-cols-3' => ($formEntryNyeri['nyeri']['nyeri'] ?? 'Tidak') === 'Ya',
                    'grid-cols-1' => ($formEntryNyeri['nyeri']['nyeri'] ?? 'Tidak') !== 'Ya',
                ])>
                    <div>
                        <x-input-label value="Status Nyeri *" />
                        <x-select-input wire:model.live="formEntryNyeri.nyeri.nyeri" class="w-full mt-1">
                            <option value="Tidak">Tidak</option>
                            <option value="Ya">Ya</option>
                        </x-select-input>
                    </div>

                    @if ($formEntryNyeri['nyeri']['nyeri'] === 'Ya')
                        <div>
                            <x-input-label value="Tanggal Penilaian *" />
                            <div class="flex gap-2 mt-1">
                                <x-text-input wire:model="formEntryNyeri.tglPenilaian" placeholder="dd/mm/yyyy hh:ii:ss"
                                    :error="$errors->has('formEntryNyeri.tglPenilaian')" class="w-full" />
                                <x-now-button wire:click="setTglPenilaianNyeri" />
                            </div>
                            <x-input-error :messages="$errors->get('formEntryNyeri.tglPenilaian')" class="mt-1" />
                        </div>

                        <div>
                            <x-input-label value="Metode Penilaian *" />
                            <x-select-input wire:model.live="formEntryNyeri.nyeri.nyeriMetode.nyeriMetode"
                                class="w-full mt-1">
                                <option value="">-- Pilih Metode --</option>
                                @foreach ($this->daftarSkala as $kode => $skala)
                                    <option value="{{ $kode }}">{{ $kode }} — {{ $skala['sasaran'] }}</option>
                                @endforeach
                            </x-select-input>
                            <x-input-error :messages="$errors->get('formEntryNyeri.nyeri.nyeriMetode.nyeriMetode')" class="mt-1" />
                            @if (!empty($skalaDisarankan))
                                <p class="mt-1 text-xs text-muted">
                                    Umur pasien {{ $umurPasienTahun }} th — disarankan:
                                    <span class="font-semibold text-brand dark:text-brand-lime">{{ implode(' / ', $skalaDisarankan) }}</span>
                                </p>
                            @endif
                        </div>
                    @endif
                </div>

                {{-- Panduan pemilihan skala — siapa memakai skala apa --}}
                <x-nyeri.panduan-skala :daftarSkala="$this->daftarSkala" />

                @if ($formEntryNyeri['nyeri']['nyeri'] === 'Ya')
                    @if ($this->skalaTerpilih)
                        <x-nyeri.identitas-skala :skala="$this->skalaTerpilih" :kode="$formEntryNyeri['nyeri']['nyeriMetode']['nyeriMetode']"
                            :skor="$formEntryNyeri['nyeri']['nyeriMetode']['nyeriMetodeScore']" :tafsir="$this->interpretasiBerjalan" />
                    @endif

                    {{-- Instrumen skala — bentuknya mengikuti tipe skala --}}
                    @if ($this->skalaTerpilih)
                        <x-nyeri.instrumen :skala="$this->skalaTerpilih" :kode="$formEntryNyeri['nyeri']['nyeriMetode']['nyeriMetode']"
                            :dataNyeri="$formEntryNyeri['nyeri']['nyeriMetode']['dataNyeri']" />
                    @endif

                    <div class="grid grid-cols-2 gap-4">
                    {{-- Detail Nyeri (1/2) --}}
                    <div>
                    <x-border-form title="Detail Nyeri" align="start" bgcolor="bg-canvas">
                        <div class="mt-3 grid grid-cols-3 gap-3">
                            <div>
                                <x-input-label value="Pencetus" />
                                <x-text-input wire:model="formEntryNyeri.nyeri.pencetus" class="w-full mt-1" />
                            </div>
                            <div>
                                <x-input-label value="Durasi" />
                                <x-text-input wire:model="formEntryNyeri.nyeri.durasi" placeholder="30 menit"
                                    class="w-full mt-1" />
                            </div>
                            <div>
                                <x-input-label value="Lokasi" />
                                <x-text-input wire:model="formEntryNyeri.nyeri.lokasi" class="w-full mt-1" />
                            </div>
                            <div>
                                <x-input-label value="Waktu Nyeri" />
                                <x-text-input wire:model="formEntryNyeri.nyeri.waktuNyeri" placeholder="Malam hari"
                                    class="w-full mt-1" />
                            </div>
                            <div>
                                <x-input-label value="Tingkat Kesadaran" />
                                <x-select-input wire:model="formEntryNyeri.nyeri.tingkatKesadaran" class="w-full mt-1">
                                    <option value="">-- Pilih --</option>
                                    @foreach (['Composmentis', 'Apatis', 'Somnolen', 'Stupor', 'Koma'] as $opt)
                                        <option value="{{ $opt }}">{{ $opt }}</option>
                                    @endforeach
                                </x-select-input>
                            </div>
                            <div>
                                <x-input-label value="Tingkat Aktivitas" />
                                <x-select-input wire:model="formEntryNyeri.nyeri.tingkatAktivitas" class="w-full mt-1">
                                    <option value="">-- Pilih --</option>
                                    @foreach (['Mandiri', 'Dibantu Sebagian', 'Dibantu Penuh'] as $opt)
                                        <option value="{{ $opt }}">{{ $opt }}</option>
                                    @endforeach
                                </x-select-input>
                            </div>
                        </div>
                    </x-border-form>
                    </div>

                    {{-- TTV (1/2) --}}
                    <div>
                    <x-border-form title="Tanda-Tanda Vital" align="start" bgcolor="bg-canvas">
                        <div class="mt-3 grid grid-cols-6 gap-2">
                            {{-- Row 1: 2 field (each col-span-3 = 1/2 width) --}}
                            <div class="col-span-3">
                                <x-input-label value="Sistolik (mmHg) *" />
                                <x-text-input-number wire:model="formEntryNyeri.nyeri.sistolik"
                                    :error="$errors->has('formEntryNyeri.nyeri.sistolik')" class="mt-1" />
                                <x-input-error :messages="$errors->get('formEntryNyeri.nyeri.sistolik')" class="mt-1" />
                            </div>
                            <div class="col-span-3">
                                <x-input-label value="Diastolik (mmHg) *" />
                                <x-text-input-number wire:model="formEntryNyeri.nyeri.distolik"
                                    :error="$errors->has('formEntryNyeri.nyeri.distolik')" class="mt-1" />
                                <x-input-error :messages="$errors->get('formEntryNyeri.nyeri.distolik')" class="mt-1" />
                            </div>
                            {{-- Row 2: 3 field (each col-span-2 = 1/3 width) --}}
                            <div class="col-span-2">
                                <x-input-label value="Frekuensi Nafas (x/mnt) *" />
                                <x-text-input-number wire:model="formEntryNyeri.nyeri.frekuensiNafas"
                                    :error="$errors->has('formEntryNyeri.nyeri.frekuensiNafas')" class="mt-1" />
                                <x-input-error :messages="$errors->get('formEntryNyeri.nyeri.frekuensiNafas')" class="mt-1" />
                            </div>
                            <div class="col-span-2">
                                <x-input-label value="Frekuensi Nadi (x/mnt) *" />
                                <x-text-input-number wire:model="formEntryNyeri.nyeri.frekuensiNadi"
                                    :error="$errors->has('formEntryNyeri.nyeri.frekuensiNadi')" class="mt-1" />
                                <x-input-error :messages="$errors->get('formEntryNyeri.nyeri.frekuensiNadi')" class="mt-1" />
                            </div>
                            <div class="col-span-2">
                                <x-input-label value="Suhu (°C) *" />
                                <x-text-input-number wire:model="formEntryNyeri.nyeri.suhu"
                                    :error="$errors->has('formEntryNyeri.nyeri.suhu')" class="mt-1" />
                                <x-input-error :messages="$errors->get('formEntryNyeri.nyeri.suhu')" class="mt-1" />
                            </div>
                        </div>
                    </x-border-form>
                    </div>
                    </div>

                    {{-- Intervensi --}}
                    <x-border-form title="Intervensi & Catatan" align="start" bgcolor="bg-canvas">
                        <div class="mt-3 grid grid-cols-3 gap-3">
                            <div>
                                <x-input-label value="Intervensi Farmakologi" />
                                <x-textarea wire:model="formEntryNyeri.nyeri.ketIntervensiFarmakologi"
                                    class="w-full mt-1" rows="2" placeholder="Nama obat, dosis, rute..." />
                            </div>
                            <div>
                                <x-input-label value="Intervensi Non-Farmakologi" />
                                <x-textarea wire:model="formEntryNyeri.nyeri.ketIntervensiNonFarmakologi"
                                    class="w-full mt-1" rows="2"
                                    placeholder="Kompres, relaksasi, distraksi..." />
                            </div>
                            <div>
                                <x-input-label value="Catatan Tambahan" />
                                <x-textarea wire:model="formEntryNyeri.nyeri.catatanTambahan" class="w-full mt-1"
                                    rows="2" />
                            </div>
                        </div>
                    </x-border-form>
                @endif

        </div>
    @endif

    @if (collect($dataDaftarRi['penilaian']['nyeri'] ?? [])->filter(fn($r) => filled(data_get($r, 'tglPenilaian')))->isNotEmpty())
        <x-border-form title="Riwayat Penilaian Nyeri" align="start" bgcolor="bg-canvas">
            <div class="mt-3 overflow-x-auto rounded-lg border border-hairline dark:border-gray-700">
                <table class="w-full text-xs text-left text-muted dark:text-gray-300">
                    <thead class="bg-surface-soft dark:bg-gray-700 text-muted dark:text-gray-400">
                        <tr>
                            <th class="px-3 py-2">Tgl / Petugas</th>
                            <th class="px-3 py-2">Nyeri</th>
                            <th class="px-3 py-2">Metode / Skor</th>
                            <th class="px-3 py-2">Tanda Vital</th>
                            <th class="px-3 py-2">Detail Nyeri</th>
                            <th class="px-3 py-2">Kondisi</th>
                            <th class="px-3 py-2">Intervensi & Catatan</th>
                            @if (!$isFormLocked)
                                <th class="px-3 py-2"></th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-hairline-soft dark:divide-gray-700">
                        @foreach (array_reverse(array_filter($dataDaftarRi['penilaian']['nyeri'] ?? [], fn($r) => filled(data_get($r, 'tglPenilaian'))), true) as $i => $row)
                            @php
                                $tafsir = $this->interpretasiEntri($row);
                                $ket = $tafsir['label'];
                                $skala = $this->daftarSkala[$row['nyeri']['nyeriMetode']['nyeriMetode'] ?? ''] ?? null;
                                $rowBg = match ($tafsir['tingkat']) {
                                    'sangatBerat', 'berat' => 'bg-red-50 hover:bg-red-100 dark:bg-red-900/10 dark:hover:bg-red-900/20',
                                    'sedang' => 'bg-orange-50 hover:bg-orange-100 dark:bg-orange-900/10 dark:hover:bg-orange-900/20',
                                    'ringan' => 'bg-yellow-50 hover:bg-yellow-100 dark:bg-yellow-900/10 dark:hover:bg-yellow-900/20',
                                    'tidak' => 'bg-green-50 hover:bg-green-100 dark:bg-green-900/10 dark:hover:bg-green-900/20',
                                    default => 'hover:bg-surface-soft dark:hover:bg-gray-800',
                                };
                            @endphp
                            <tr class="{{ $rowBg }}">

                                {{-- Tgl / Petugas --}}
                                <td class="px-3 py-2 whitespace-nowrap">
                                    <div class="font-medium text-ink dark:text-gray-200">
                                        {{ $row['tglPenilaian'] ?? '-' }}</div>
                                    <div class="text-muted-soft">{{ $row['petugasPenilai'] ?? '-' }}</div>
                                </td>

                                {{-- Status Nyeri --}}
                                <td class="px-3 py-2">
                                    <span
                                        class="px-2 py-0.5 rounded-full text-xs font-medium
                        {{ ($row['nyeri']['nyeri'] ?? '') == 'Ya' ? 'bg-orange-100 text-orange-700' : 'bg-green-100 text-green-700' }}">
                                        {{ $row['nyeri']['nyeri'] ?? '-' }}
                                    </span>
                                </td>

                                {{-- Metode / Skor / Keterangan --}}
                                <td class="px-3 py-2">
                                    <div class="font-medium">{{ $row['nyeri']['nyeriMetode']['nyeriMetode'] ?? '-' }}
                                    </div>
                                    @if ($skala)
                                        <div class="text-[10px] text-muted-soft">{{ $skala['sasaran'] }}</div>
                                    @endif
                                    <div class="flex flex-wrap items-center gap-1 mt-0.5">
                                        <span class="font-bold">
                                            {{ $row['nyeri']['nyeriMetode']['nyeriMetodeScore'] ?? '-' }}@if ($skala)<span class="font-normal text-muted-soft">/{{ $skala['max'] }}</span>@endif
                                        </span>
                                        @if ($ket !== '-')
                                            <span class="px-1.5 py-0.5 rounded-full text-[10px] font-bold {{ $tafsir['badge'] }}">
                                                {{ $ket }}
                                            </span>
                                        @endif
                                    </div>
                                    @if ($tafsir['tataLaksana'])
                                        <div class="text-[10px] text-muted-soft mt-0.5">{{ $tafsir['tataLaksana'] }}</div>
                                    @endif
                                </td>

                                {{-- Tanda Vital --}}
                                <td class="px-3 py-2 whitespace-nowrap">
                                    <div>TD: <span
                                            class="font-medium">{{ ($row['nyeri']['sistolik'] ?? '-') . '/' . ($row['nyeri']['distolik'] ?? '-') }}</span>
                                    </div>
                                    <div>Nadi: <span
                                            class="font-medium">{{ $row['nyeri']['frekuensiNadi'] ?? '-' }}</span>
                                    </div>
                                    <div>Nafas: <span
                                            class="font-medium">{{ $row['nyeri']['frekuensiNafas'] ?? '-' }}</span>
                                    </div>
                                    <div>Suhu: <span class="font-medium">{{ $row['nyeri']['suhu'] ?? '-' }}</span>
                                    </div>
                                </td>

                                {{-- Detail Nyeri --}}
                                <td class="px-3 py-2">
                                    @if ($row['nyeri']['pencetus'] ?? '')
                                        <div>Pencetus: <span
                                                class="font-medium">{{ $row['nyeri']['pencetus'] }}</span></div>
                                    @endif
                                    @if ($row['nyeri']['lokasi'] ?? '')
                                        <div>Lokasi: <span class="font-medium">{{ $row['nyeri']['lokasi'] }}</span>
                                        </div>
                                    @endif
                                    @if ($row['nyeri']['durasi'] ?? '')
                                        <div>Durasi: <span class="font-medium">{{ $row['nyeri']['durasi'] }}</span>
                                        </div>
                                    @endif
                                    @if ($row['nyeri']['waktuNyeri'] ?? '')
                                        <div>Waktu: <span class="font-medium">{{ $row['nyeri']['waktuNyeri'] }}</span>
                                        </div>
                                    @endif
                                    @if (
                                        !($row['nyeri']['pencetus'] ?? '') &&
                                            !($row['nyeri']['lokasi'] ?? '') &&
                                            !($row['nyeri']['durasi'] ?? '') &&
                                            !($row['nyeri']['waktuNyeri'] ?? ''))
                                        <span class="text-muted-soft">-</span>
                                    @endif
                                </td>

                                {{-- Kondisi --}}
                                <td class="px-3 py-2 whitespace-nowrap">
                                    @if ($row['nyeri']['tingkatKesadaran'] ?? '')
                                        <div>Kesadaran: <span
                                                class="font-medium">{{ $row['nyeri']['tingkatKesadaran'] }}</span>
                                        </div>
                                    @endif
                                    @if ($row['nyeri']['tingkatAktivitas'] ?? '')
                                        <div>Aktivitas: <span
                                                class="font-medium">{{ $row['nyeri']['tingkatAktivitas'] }}</span>
                                        </div>
                                    @endif
                                    @if (!($row['nyeri']['tingkatKesadaran'] ?? '') && !($row['nyeri']['tingkatAktivitas'] ?? ''))
                                        <span class="text-muted-soft">-</span>
                                    @endif
                                </td>

                                {{-- Intervensi & Catatan --}}
                                <td class="px-3 py-2 max-w-[200px]">
                                    @if ($row['nyeri']['ketIntervensiFarmakologi'] ?? '')
                                        <div class="truncate">Farmako: <span
                                                class="font-medium">{{ $row['nyeri']['ketIntervensiFarmakologi'] }}</span>
                                        </div>
                                    @endif
                                    @if ($row['nyeri']['ketIntervensiNonFarmakologi'] ?? '')
                                        <div class="truncate">Non-Farmako: <span
                                                class="font-medium">{{ $row['nyeri']['ketIntervensiNonFarmakologi'] }}</span>
                                        </div>
                                    @endif
                                    @if ($row['nyeri']['catatanTambahan'] ?? '')
                                        <div class="truncate text-muted-soft">{{ $row['nyeri']['catatanTambahan'] }}
                                        </div>
                                    @endif
                                    @if (
                                        !($row['nyeri']['ketIntervensiFarmakologi'] ?? '') &&
                                            !($row['nyeri']['ketIntervensiNonFarmakologi'] ?? '') &&
                                            !($row['nyeri']['catatanTambahan'] ?? ''))
                                        <span class="text-muted-soft">-</span>
                                    @endif
                                </td>

                                @if (!$isFormLocked)
                                    <td class="px-3 py-2">
                                        <x-outline-button type="button"
                                            wire:click="removeAssessmentNyeri({{ $i }})"
                                            wire:confirm="Hapus data nyeri ini?" wire:loading.attr="disabled"
                                            class="!text-red-600 !bg-red-50 !border-red-200 hover:!bg-red-100 hover:!text-red-700 hover:!border-red-300 dark:!text-red-400 dark:!bg-red-900/20 dark:!border-red-800/30 dark:hover:!bg-red-900/30 dark:hover:!text-red-300"
                                            title="Hapus">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        </x-outline-button>
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-border-form>
    @else
        <p class="text-xs text-center text-muted-soft py-6">Belum ada data penilaian nyeri.</p>
    @endif
</div>
