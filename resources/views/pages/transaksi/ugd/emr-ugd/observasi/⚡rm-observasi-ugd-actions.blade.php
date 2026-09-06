<?php
// resources/views/pages/transaksi/ugd/emr-ugd/observasi/rm-observasi-ugd-actions.blade.php

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Http\Traits\Txn\Ugd\EmrUGDTrait;
use App\Http\Traits\Concerns\WithRenderVersioningTrait;
use App\Support\Ews\EwsDefault;
use App\Support\Ews\EwsMaster;
use App\Support\Ews\EwsSkor;

new class extends Component {
    use EmrUGDTrait, WithRenderVersioningTrait;

    public bool $isFormLocked = false;
    public ?int $rjNo = null;
    public array $dataDaftarUGD = [];

    public array $renderVersions = [];
    protected array $renderAreas = ['modal-observasi-ugd'];

    /*
     | ── Form entry observasi lanjutan ──
     | Satu entri = TTV + parameter EWS (sama persis dengan Observasi Lanjutan RI).
     | Kunci EWS disimpan datar di entri; hasil skor di sub-array `ews`
     | (keluaran EwsSkor::hitung). Entri lama tanpa `ews` tetap sah — tampil "-".
     */
    public array $observasiLanjutan = [
        'cairan' => '',
        'tetesan' => '',
        'sistolik' => '',
        'distolik' => '',
        'frekuensiNafas' => '',
        'frekuensiNadi' => '',
        'suhu' => '',
        'spo2' => '',
        'gda' => '',
        'gcs' => '',
        'waktuPemeriksaan' => '',
        'pemeriksa' => '',
        // ── EWS ──
        'kesadaran' => '',       // A/C/V/P/U (DEWASA, MEOWS)
        'oksigen' => '',         // ROOM_AIR / O2
        'alatOksigen' => '',     // teks bebas: jenis alat + lpm
        'spo2Skala2' => '',      // SpO₂ skala 2 (gagal nafas tipe 2) — menggantikan spo2 saat diisi
        'keadaanUmum' => '',     // ANAK / NEONATUS
        'kardiovaskular' => '',
        'respirasi' => '',
        'nyeri' => '',           // MEOWS
        'perdarahan' => '',
        'lochea' => '',
        'produksiUrine' => '',
        'proteinUrine' => '',
        'djj' => '',
    ];

    /** Kunci entri yang BUKAN parameter EWS (dirender di baris TTV, bukan baris EWS). */
    private const KUNCI_TTV = ['cairan', 'tetesan', 'sistolik', 'distolik', 'frekuensiNafas', 'frekuensiNadi', 'suhu', 'spo2', 'gda', 'gcs', 'waktuPemeriksaan', 'pemeriksa', 'alatOksigen'];

    public string $ewsVarian = 'DEWASA';
    public ?int $umurHari = null;
    public ?int $umurBulan = null;
    public ?int $umurTahun = null;

    /*
     | ── EWS ──
     | Master dibaca lewat EwsMaster (cache 10 mnt). Bila DDL belum dijalankan,
     | master kosong → baris EWS tidak muncul, TTV tetap bisa disimpan.
     */
    public function ewsMaster(): array
    {
        return EwsMaster::muat();
    }

    public function ewsTersedia(): bool
    {
        return EwsSkor::paramsVarian($this->ewsMaster(), $this->ewsVarian) !== [];
    }

    /** Parameter EWS varian aktif yang perlu field sendiri (belum ada di baris TTV). */
    public function ewsParamTambahan(): array
    {
        $tambahan = [];
        foreach (EwsMaster::paramsDiskor($this->ewsMaster(), $this->ewsVarian) as $kode => $param) {
            if (in_array($kode, self::KUNCI_TTV, true)) {
                continue;
            }
            $tambahan[$kode] = $param + ['pilihan' => EwsMaster::pilihan($this->ewsMaster(), $this->ewsVarian, $kode)];
        }

        return $tambahan;
    }

    public function ewsResponList(): array
    {
        return EwsSkor::responsVarian($this->ewsMaster(), $this->ewsVarian);
    }

    public function ewsAcuanUsia(string $kode): ?array
    {
        return EwsSkor::acuanUsia($this->ewsMaster(), $this->ewsVarian, $kode, $this->umurBulan);
    }

    public function ewsVarianList(): array
    {
        return EwsMaster::varianTersedia($this->ewsMaster()) ?: EwsDefault::VARIAN;
    }

    private function tentukanUmurDanVarian(?string $regNo): void
    {
        $birthDate = empty($regNo) ? null : DB::table('rsmst_pasiens')->where('reg_no', $regNo)->value('birth_date');
        $umur = EwsSkor::umurDari($birthDate);
        $this->umurHari  = $umur['hari'];
        $this->umurBulan = $umur['bulan'];
        $this->umurTahun = $umur['tahun'];

        // Varian mengikuti umur; entri terakhir yang sudah memilih varian (mis. MEOWS) lebih diutamakan.
        $terakhir = collect($this->dataDaftarUGD['observasi']['observasiLanjutan']['tandaVital'] ?? [])
            ->sortByDesc(fn($item) => strtotime(str_replace('/', '-', $item['waktuPemeriksaan'] ?? '')) ?: 0)
            ->first();
        $varianTerakhir = $terakhir['ewsVarian'] ?? null;

        $this->ewsVarian = array_key_exists((string) $varianTerakhir, EwsDefault::VARIAN)
            ? $varianTerakhir
            : (EwsSkor::varianUntukUmur($this->umurHari, $this->umurTahun) ?? 'DEWASA');
    }

    /** Ganti varian → kosongkan isian EWS varian sebelumnya supaya tidak ikut tersimpan. */
    public function updatedEwsVarian(string $varian): void
    {
        if (!array_key_exists($varian, EwsDefault::VARIAN)) {
            $this->ewsVarian = 'DEWASA';
        }
        foreach (array_keys($this->observasiLanjutan) as $kode) {
            if (!in_array($kode, self::KUNCI_TTV, true)) {
                $this->observasiLanjutan[$kode] = '';
            }
        }
        $this->resetValidation();
    }

    /** Aturan validasi parameter EWS wajib untuk varian aktif — dari master, bukan hard-code. */
    private function aturanEws(): array
    {
        $rules = [];
        $attributes = [];
        foreach ($this->ewsParamTambahan() as $kode => $param) {
            if (($param['wajib'] ?? '1') !== '1') {
                continue;
            }
            $rules["observasiLanjutan.{$kode}"] = $param['tipe'] === 'PILIHAN' ? 'required' : 'required|numeric';
            $attributes["observasiLanjutan.{$kode}"] = $param['param_desc'];
        }

        return [$rules, $attributes];
    }

    /* ===============================
     | MOUNT
     =============================== */
    public function mount(): void
    {
        $this->registerAreas(['modal-observasi-ugd']);
    }

    /* ===============================
     | OPEN
     =============================== */
    #[On('open-rm-observasi-ugd')]
    public function openObservasi(int $rjNo): void
    {
        if (empty($rjNo)) {
            return;
        }

        $this->rjNo = $rjNo;
        $this->resetForm();
        $this->resetValidation();

        $data = $this->findDataUGD($rjNo);
        if (!$data) {
            $this->dispatch('toast', type: 'error', message: 'Data UGD tidak ditemukan.');
            return;
        }

        $this->dataDaftarUGD = $data;

        // Inisialisasi struktur jika belum ada
        $this->dataDaftarUGD['observasi']['observasiLanjutan'] ??= [
            'tandaVitalTab' => 'Observasi Lanjutan',
            'tandaVital' => [],
        ];
        $this->dataDaftarUGD['observasi']['observasiLanjutan']['tandaVital'] ??= [];

        // Generate ID untuk data lama yang belum ada ID
        $this->generateIds('tandaVital', 'observasi_');

        $this->isFormLocked = $this->checkEmrUGDStatus($rjNo);
        $this->tentukanUmurDanVarian($data['regNo'] ?? null);

        // Set waktu default
        $this->setWaktuPemeriksaan();

        $this->incrementVersion('modal-observasi-ugd');
    }

    /* ===============================
     | RELOAD SETELAH SIMPAN EMR GLOBAL
     | Simpan SOAP mem-morph parent EMR → komponen pasif (tak dapat event save) ikut
     | ter-wipe ("Data UGD belum dimuat"). Muat ulang HANYA bila memang ter-wipe
     | (regNo hilang), supaya input berjalan tak ke-reset.
     =============================== */
    #[On('refresh-after-ugd.saved')]
    public function reloadAfterUgdSaved(): void
    {
        if (empty($this->rjNo) || !empty($this->dataDaftarUGD['regNo'])) {
            return;
        }

        $this->openObservasi((int) $this->rjNo);
    }

    /* ===============================
     | VALIDATION
     =============================== */
    protected function rules(): array
    {
        [$aturanEws] = $this->aturanEws();

        return [
            'observasiLanjutan.sistolik' => 'required|numeric',
            'observasiLanjutan.distolik' => 'required|numeric',
            'observasiLanjutan.frekuensiNafas' => 'required|numeric',
            'observasiLanjutan.frekuensiNadi' => 'required|numeric',
            'observasiLanjutan.suhu' => 'required|numeric',
            'observasiLanjutan.spo2' => 'required|numeric',
            'observasiLanjutan.spo2Skala2' => 'nullable|numeric',
            'observasiLanjutan.waktuPemeriksaan' => 'required|date_format:d/m/Y H:i:s',
            'observasiLanjutan.pemeriksa' => 'required',
            ...$aturanEws,
        ];
    }

    protected function messages(): array
    {
        return [
            'required' => ':attribute wajib diisi.',
            'numeric' => ':attribute harus berupa angka.',
            'date_format' => ':attribute harus format dd/mm/yyyy HH:ii:ss.',
        ];
    }

    protected function validationAttributes(): array
    {
        [, $atributEws] = $this->aturanEws();

        return [
            'observasiLanjutan.sistolik' => 'TD Sistolik',
            'observasiLanjutan.distolik' => 'TD Diastolik',
            'observasiLanjutan.frekuensiNafas' => 'Frekuensi Nafas',
            'observasiLanjutan.frekuensiNadi' => 'Frekuensi Nadi',
            'observasiLanjutan.suhu' => 'Suhu',
            'observasiLanjutan.spo2' => 'SpO₂',
            'observasiLanjutan.spo2Skala2' => 'SpO₂ skala 2',
            'observasiLanjutan.waktuPemeriksaan' => 'Waktu Pemeriksaan',
            'observasiLanjutan.pemeriksa' => 'Pemeriksa',
            ...$atributEws,
        ];
    }

    /* ===============================
     | ADD OBSERVASI LANJUTAN
     =============================== */
    #[On('save-rm-observasi-ugd')]
    public function addObservasiLanjutan(): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form read-only, tidak dapat menyimpan.');
            return;
        }

        $this->observasiLanjutan['pemeriksa'] = auth()->user()->myuser_name ?? '';
        $this->validate();

        // Skor EWS dihitung SEKALI di sini dan disimpan bersama entri.
        $hasilEws = $this->ewsTersedia()
            ? EwsSkor::hitung($this->ewsVarian, $this->observasiLanjutan, $this->ewsMaster(), $this->umurBulan)
            : null;
        if ($hasilEws !== null && $hasilEws['frekuensiMenit'] !== null) {
            $hasilEws['pantauUlang'] = Carbon::createFromFormat('d/m/Y H:i:s', $this->observasiLanjutan['waktuPemeriksaan'])
                ->addMinutes($hasilEws['frekuensiMenit'])->format('d/m/Y H:i');
        }

        try {
            DB::transaction(function () use ($hasilEws) {
                // 1. Lock row dulu
                $this->lockUGDRow($this->rjNo);

                // 2. Baca data terkini setelah lock
                $data = $this->findDataUGD($this->rjNo);
                if (empty($data)) {
                    throw new \RuntimeException('Data UGD tidak ditemukan, simpan dibatalkan.');
                }

                // 3. Inisialisasi struktur jika belum ada
                $data['observasi']['observasiLanjutan'] ??= [
                    'tandaVitalTab' => 'Observasi Lanjutan',
                    'tandaVital' => [],
                ];
                $data['observasi']['observasiLanjutan']['tandaVital'] ??= [];

                // 4. Cek duplikasi waktu
                $exists = collect($data['observasi']['observasiLanjutan']['tandaVital'])->firstWhere('waktuPemeriksaan', $this->observasiLanjutan['waktuPemeriksaan']);

                if ($exists) {
                    throw new \RuntimeException('Data pada waktu tersebut sudah ada.');
                }

                // 5. Tambah entry baru
                $data['observasi']['observasiLanjutan']['tandaVital'][] = array_merge(
                    ['id' => uniqid('observasi_')],
                    $this->observasiLanjutan,
                    ['ewsVarian' => $this->ewsVarian, 'ews' => $hasilEws],
                );
                $data['observasi']['observasiLanjutan']['tandaVital'] = array_values($data['observasi']['observasiLanjutan']['tandaVital']);

                // 6. Log
                $ringkasEws = $hasilEws === null ? '' : ' - EWS ' . $hasilEws['total'] . ' (' . ($hasilEws['kategori'] ?? '-') . ')';
                $data['observasi']['observasiLanjutan']['tandaVitalLog'] = [
                    'userLogDesc' => 'Tambah Observasi Lanjutan UGD (' . $this->observasiLanjutan['waktuPemeriksaan'] . ')' . $ringkasEws,
                    'userLog' => auth()->user()->myuser_name ?? '',
                    'userLogDate' => Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s'),
                    'userLogCat' => 'MR',
                ];

                // 7. Simpan JSON
                $this->updateJsonUGD($this->rjNo, $data);
                $this->dataDaftarUGD = $data;
            });

            // 8. Reset form + notify — di luar transaksi
            $this->resetObservasiForm();
            $this->setWaktuPemeriksaan();
            $this->incrementVersion('modal-observasi-ugd');
            $pesan = 'Observasi Lanjutan berhasil ditambahkan.';
            if ($hasilEws !== null) {
                $pesan .= ' Skor EWS ' . $hasilEws['total'] . ' — ' . ($hasilEws['kategori'] ?? '-') . ', ' . strtolower((string) ($hasilEws['frekuensi'] ?? 'pantau sesuai kebijakan')) . '.';
            }
            $this->dispatch('toast', type: ($hasilEws['adaMerah'] ?? false) || (($hasilEws['total'] ?? 0) >= 5) ? 'warning' : 'success', message: $pesan);
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menyimpan: ' . $e->getMessage());
        }
    }

    /* ===============================
     | REMOVE OBSERVASI LANJUTAN
     =============================== */
    public function removeObservasiLanjutan(string $waktuPemeriksaan): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form read-only, tidak dapat menghapus.');
            return;
        }

        try {
            DB::transaction(function () use ($waktuPemeriksaan) {
                // 1. Lock row dulu
                $this->lockUGDRow($this->rjNo);

                // 2. Baca data terkini setelah lock
                $data = $this->findDataUGD($this->rjNo);
                if (empty($data)) {
                    throw new \RuntimeException('Data UGD tidak ditemukan.');
                }

                if (!isset($data['observasi']['observasiLanjutan']['tandaVital'])) {
                    throw new \RuntimeException('Data observasi tidak ditemukan.');
                }

                // 3. Hapus berdasarkan waktu
                $data['observasi']['observasiLanjutan']['tandaVital'] = collect($data['observasi']['observasiLanjutan']['tandaVital'])
                    ->reject(fn($row) => (string) ($row['waktuPemeriksaan'] ?? '') === (string) $waktuPemeriksaan)
                    ->values()
                    ->all();

                // 4. Update log
                $data['observasi']['observasiLanjutan']['tandaVitalLog'] = [
                    'userLogDesc' => 'Hapus Observasi Lanjutan UGD (' . $waktuPemeriksaan . ')',
                    'userLog' => auth()->user()->myuser_name ?? '',
                    'userLogDate' => Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s'),
                    'userLogCat' => 'MR',
                ];

                // 5. Simpan JSON
                $this->updateJsonUGD($this->rjNo, $data);
                $this->dataDaftarUGD = $data;
            });

            // 6. Notify — di luar transaksi
            $this->incrementVersion('modal-observasi-ugd');
            $this->dispatch('toast', type: 'success', message: 'Observasi Lanjutan berhasil dihapus.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menghapus: ' . $e->getMessage());
        }
    }

    /* ===============================
     | SET WAKTU
     =============================== */
    public function setWaktuPemeriksaan(): void
    {
        $this->observasiLanjutan['waktuPemeriksaan'] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
    }

    /* ===============================
     | HELPERS
     =============================== */
    private function resetObservasiForm(): void
    {
        $this->reset(['observasiLanjutan']);
        $this->resetValidation();
    }

    private function generateIds(string $key, string $prefix): void
    {
        if (isset($this->dataDaftarUGD['observasi']['observasiLanjutan'][$key])) {
            foreach ($this->dataDaftarUGD['observasi']['observasiLanjutan'][$key] as &$item) {
                $item['id'] ??= uniqid($prefix);
            }
        }
    }

    protected function resetForm(): void
    {
        $this->resetVersion();
        $this->isFormLocked = false;
        $this->dataDaftarUGD = [];
        $this->reset(['observasiLanjutan']);
    }
};
?>

<div>
    <div class="flex flex-col w-full" wire:key="{{ $this->renderKey('modal-observasi-ugd', [$rjNo ?? 'new']) }}">
        <div
            class="w-full p-4 space-y-6 bg-canvas border border-hairline shadow-sm rounded-2xl dark:bg-gray-900 dark:border-gray-700">

            @if ($isFormLocked)
                <div
                    class="flex items-center gap-2 px-4 py-2.5 text-base font-medium text-amber-700 bg-amber-50 border border-amber-200 rounded-xl dark:bg-amber-900/20 dark:border-amber-600 dark:text-amber-300">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                    </svg>
                    EMR terkunci — data tidak dapat diubah.
                </div>
            @endif

            @if (isset($dataDaftarUGD['observasi']['observasiLanjutan']))

                {{-- FORM INPUT --}}
                @if (!$isFormLocked)
                    <div
                        class="grid grid-cols-12 gap-2 p-4 border border-hairline rounded-2xl dark:border-gray-700 bg-surface-soft dark:bg-gray-800/40">

                        {{-- BARIS EWS: parameter tambahan varian aktif (dari master, bukan hard-code) — skor dihitung
                             otomatis saat Tambah. Enter di dalam baris ini pindah ke field berikutnya; di field terakhir turun ke baris TTV.
                             Tidak tampil bila master EWS belum ada di environment (DDL belum jalan) — TTV tetap bisa disimpan. --}}
                        @if ($this->ewsTersedia())
                            @php
                                $ewsParamTambahan = $this->ewsParamTambahan();
                                $acuanNadi = $this->ewsAcuanUsia('nadiNormal');
                                $acuanNafas = $this->ewsAcuanUsia('nafasNormal');
                            @endphp
                            <div class="col-span-12 pb-3 mb-1 border-b border-hairline dark:border-gray-700"
                                x-on:keydown.enter.prevent="
                                    const sel = 'input:not([type=hidden]), select';
                                    if (!$event.target.matches(sel)) return;
                                    const els = [...$el.querySelectorAll(sel)].filter(x => !x.disabled && x.offsetParent !== null);
                                    const i = els.indexOf($event.target);
                                    if (i > -1 && i < els.length - 1) { els[i + 1].focus() } else { document.getElementById('ol-ugd-cairan')?.focus() }">
                                <div class="flex flex-wrap items-center gap-2 mb-2">
                                    <span class="text-sm font-semibold text-body dark:text-gray-300">Skor EWS</span>
                                    <x-select-input wire:model.live="ewsVarian" class="!w-auto py-1 text-sm">
                                        @foreach ($this->ewsVarianList() as $kode => $label)
                                            <option value="{{ $kode }}">{{ $label }}</option>
                                        @endforeach
                                    </x-select-input>
                                    @if ($umurTahun !== null)
                                        <x-badge variant="gray">Umur {{ $umurTahun >= 1 ? $umurTahun . ' th' : ($umurBulan >= 1 ? $umurBulan . ' bln' : $umurHari . ' hr') }}</x-badge>
                                    @endif
                                    @if ($acuanNadi)
                                        <x-badge variant="info">Nadi normal {{ EwsDefault::labelRentang($acuanNadi['batas_bawah'], $acuanNadi['batas_atas']) }} x/mnt</x-badge>
                                    @endif
                                    @if ($acuanNafas)
                                        <x-badge variant="info">Nafas normal {{ EwsDefault::labelRentang($acuanNafas['batas_bawah'], $acuanNafas['batas_atas']) }} x/mnt</x-badge>
                                    @endif
                                    <span class="text-xs text-muted-soft">Skor, frekuensi pantau & respon dihitung otomatis saat Tambah.</span>
                                </div>
                                <div class="grid grid-cols-2 gap-2 md:grid-cols-4 xl:grid-cols-6 items-stretch">
                                    @foreach ($ewsParamTambahan as $kode => $param)
                                        <div class="flex flex-col {{ $param['tipe'] === 'PILIHAN' && count($param['pilihan']) > 2 ? 'col-span-2' : '' }}"
                                            wire:key="ews-field-{{ $ewsVarian }}-{{ $kode }}">
                                            <x-input-label class="mb-1">{{ $param['param_desc'] }}{{ ($param['wajib'] ?? '1') === '1' ? ' *' : '' }}</x-input-label>
                                            @if ($param['tipe'] === 'PILIHAN')
                                                <x-select-input wire:model="observasiLanjutan.{{ $kode }}" class="w-full mt-auto"
                                                    :error="$errors->has('observasiLanjutan.' . $kode)" :id="$loop->first ? 'ews-ugd-first' : null">
                                                    <option value="">— pilih —</option>
                                                    @foreach ($param['pilihan'] as $pilihanKode => $pilihanLabel)
                                                        <option value="{{ $pilihanKode }}">{{ $pilihanLabel }}</option>
                                                    @endforeach
                                                </x-select-input>
                                            @else
                                                <x-text-input wire:model="observasiLanjutan.{{ $kode }}" type="number" step="0.1"
                                                    placeholder="{{ $param['satuan'] ?? '' }}" class="w-full mt-auto"
                                                    :error="$errors->has('observasiLanjutan.' . $kode)" :id="$loop->first ? 'ews-ugd-first' : null" />
                                            @endif
                                            <x-input-error :messages="$errors->get('observasiLanjutan.' . $kode)" class="mt-1" />
                                        </div>
                                        @if ($kode === 'oksigen')
                                            <div class="flex flex-col" wire:key="ews-field-{{ $ewsVarian }}-alatOksigen">
                                                <x-input-label class="mb-1">Alat O₂ / lpm</x-input-label>
                                                <x-text-input wire:model="observasiLanjutan.alatOksigen" placeholder="NRBM 10 lpm" class="w-full mt-auto" />
                                            </div>
                                        @endif
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        {{-- Cairan + Tetesan --}}
                        <div class="col-span-12 md:col-span-6">
                            <x-input-label value="Cairan" class="mb-1" />
                            <div class="relative">
                                <x-text-input wire:model="observasiLanjutan.cairan" placeholder="Cairan"
                                    id="ol-ugd-cairan" class="w-full pr-8" x-ref="olCairan"
                                    x-on:keydown.enter.prevent="$refs.olTetesan?.focus()" />
                                <span
                                    class="absolute inset-y-0 right-1.5 flex items-center text-xs text-muted-soft pointer-events-none">ml</span>
                            </div>
                            <x-input-error :messages="$errors->get('observasiLanjutan.cairan')" class="mt-1" />
                        </div>

                        <div class="col-span-12 md:col-span-6">
                            <x-input-label value="Tetesan" class="mb-1" />
                            <div class="relative">
                                <x-text-input wire:model="observasiLanjutan.tetesan" placeholder="Tetesan/menit"
                                    class="w-full pr-16" x-ref="olTetesan"
                                    x-on:keydown.enter.prevent="$refs.olSistolik?.focus()" />
                                <span
                                    class="absolute inset-y-0 right-1.5 flex items-center text-xs text-muted-soft pointer-events-none">gtt/menit</span>
                            </div>
                        </div>

                        {{-- Tanda Vital --}}
                        <div class="col-span-4 md:col-span-1">
                            <x-input-label value="Sistolik *" class="mb-1 truncate whitespace-nowrap" />
                            <div class="relative">
                                <x-text-input wire:model="observasiLanjutan.sistolik" placeholder="Sist"
                                    class="w-full px-2 pr-10" x-ref="olSistolik"
                                    x-on:keydown.enter.prevent="$refs.olDistolik?.focus()" />
                                <span
                                    class="absolute inset-y-0 right-1.5 flex items-center text-xs text-muted-soft pointer-events-none">mmHg</span>
                            </div>
                            <x-input-error :messages="$errors->get('observasiLanjutan.sistolik')" class="mt-1" />
                        </div>

                        <div class="col-span-4 md:col-span-1">
                            <x-input-label value="Diastolik *" class="mb-1 truncate whitespace-nowrap" />
                            <div class="relative">
                                <x-text-input wire:model="observasiLanjutan.distolik" placeholder="Dias"
                                    class="w-full px-2 pr-10" x-ref="olDistolik"
                                    x-on:keydown.enter.prevent="$refs.olNafas?.focus()" />
                                <span
                                    class="absolute inset-y-0 right-1.5 flex items-center text-xs text-muted-soft pointer-events-none">mmHg</span>
                            </div>
                            <x-input-error :messages="$errors->get('observasiLanjutan.distolik')" class="mt-1" />
                        </div>

                        <div class="col-span-4 md:col-span-1">
                            <x-input-label value="RR (Nafas) *" class="mb-1 truncate whitespace-nowrap" />
                            <div class="relative">
                                <x-text-input wire:model="observasiLanjutan.frekuensiNafas" placeholder="Nafas"
                                    class="w-full px-2 pr-10" x-ref="olNafas"
                                    x-on:keydown.enter.prevent="$refs.olNadi?.focus()" />
                                <span
                                    class="absolute inset-y-0 right-1.5 flex items-center text-xs text-muted-soft pointer-events-none">x/mnt</span>
                            </div>
                            <x-input-error :messages="$errors->get('observasiLanjutan.frekuensiNafas')" class="mt-1" />
                        </div>

                        <div class="col-span-4 md:col-span-1">
                            <x-input-label value="Nadi *" class="mb-1 truncate whitespace-nowrap" />
                            <div class="relative">
                                <x-text-input wire:model="observasiLanjutan.frekuensiNadi" placeholder="Nadi"
                                    class="w-full px-2 pr-10" x-ref="olNadi"
                                    x-on:keydown.enter.prevent="$refs.olSuhu?.focus()" />
                                <span
                                    class="absolute inset-y-0 right-1.5 flex items-center text-xs text-muted-soft pointer-events-none">x/mnt</span>
                            </div>
                            <x-input-error :messages="$errors->get('observasiLanjutan.frekuensiNadi')" class="mt-1" />
                        </div>

                        <div class="col-span-4 md:col-span-1">
                            <x-input-label value="Suhu *" class="mb-1 truncate whitespace-nowrap" />
                            <div class="relative">
                                <x-text-input wire:model="observasiLanjutan.suhu" placeholder="Suhu"
                                    class="w-full px-2 pr-6" x-ref="olSuhu"
                                    x-on:keydown.enter.prevent="$refs.olSpo2?.focus()" />
                                <span
                                    class="absolute inset-y-0 right-1.5 flex items-center text-xs text-muted-soft pointer-events-none">°C</span>
                            </div>
                            <x-input-error :messages="$errors->get('observasiLanjutan.suhu')" class="mt-1" />
                        </div>

                        <div class="col-span-4 md:col-span-1">
                            <x-input-label value="SpO₂ *" class="mb-1 truncate whitespace-nowrap" />
                            <div class="relative">
                                <x-text-input wire:model="observasiLanjutan.spo2" placeholder="SpO₂"
                                    class="w-full px-2 pr-5" x-ref="olSpo2"
                                    x-on:keydown.enter.prevent="$refs.olGda?.focus()" />
                                <span
                                    class="absolute inset-y-0 right-1.5 flex items-center text-xs text-muted-soft pointer-events-none">%</span>
                            </div>
                            <x-input-error :messages="$errors->get('observasiLanjutan.spo2')" class="mt-1" />
                        </div>

                        <div class="col-span-4 md:col-span-1">
                            <x-input-label value="GDA" class="mb-1 truncate whitespace-nowrap" />
                            <div class="relative">
                                <x-text-input wire:model="observasiLanjutan.gda" placeholder="GDA"
                                    class="w-full px-2 pr-10" x-ref="olGda"
                                    x-on:keydown.enter.prevent="$refs.olGcs?.focus()" />
                                <span
                                    class="absolute inset-y-0 right-1.5 flex items-center text-xs text-muted-soft pointer-events-none">mg/dL</span>
                            </div>
                        </div>

                        <div class="col-span-4 md:col-span-1">
                            <x-input-label value="GCS" class="mb-1 truncate whitespace-nowrap" />
                            <x-text-input wire:model="observasiLanjutan.gcs" placeholder="E V M" class="w-full px-2"
                                x-ref="olGcs" x-on:keydown.enter.prevent="$refs.olWaktu?.focus()" />
                        </div>

                        {{-- Waktu + Aksi --}}
                        <div class="col-span-12 md:col-span-3">
                            <x-input-label value="Waktu Pemeriksaan *" class="mb-1 truncate whitespace-nowrap" />
                            <div class="flex items-center gap-2">
                                <x-text-input wire:model="observasiLanjutan.waktuPemeriksaan"
                                    placeholder="dd/mm/yyyy hh:mm:ss" class="grow px-2" x-ref="olWaktu"
                                    x-on:keydown.enter.prevent="$el.blur(); $wire.addObservasiLanjutan()" />
                                <x-now-button wire:click.prevent="setWaktuPemeriksaan" />
                            </div>
                            <x-input-error :messages="$errors->get('observasiLanjutan.waktuPemeriksaan')" class="mt-1" />
                        </div>

                        <div class="col-span-12 md:col-span-1 flex items-end">
                            <x-primary-button wire:click.prevent="addObservasiLanjutan" wire:loading.attr="disabled"
                                wire:target="addObservasiLanjutan" class="gap-1.5 w-full justify-center px-2">
                                <span wire:loading.remove wire:target="addObservasiLanjutan">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M12 4v16m8-8H4" />
                                    </svg>
                                </span>
                                <span wire:loading wire:target="addObservasiLanjutan"><x-loading
                                        class="w-4 h-4" /></span>
                                Tambah
                            </x-primary-button>
                        </div>


                    </div>
                @endif

                {{-- TABEL DATA --}}
                @php
                    $tandaVitalData = $dataDaftarUGD['observasi']['observasiLanjutan']['tandaVital'] ?? [];
                    $sortedTtv = collect($tandaVitalData)
                        ->sortByDesc(
                            fn($item) => \Carbon\Carbon::createFromFormat(
                                'd/m/Y H:i:s',
                                $item['waktuPemeriksaan'] ?? '01/01/2000 00:00:00',
                            )->timestamp,
                        )
                        ->values();
                @endphp

                <div
                    class="overflow-hidden bg-canvas border border-hairline rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                    <div
                        class="flex items-center justify-between px-4 py-3 border-b border-hairline dark:border-gray-700">
                        <h3 class="text-base font-semibold text-body dark:text-gray-300">Daftar Observasi Lanjutan
                        </h3>
                        <x-badge variant="gray">{{ count($tandaVitalData) }} item</x-badge>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-base text-left">
                            <thead
                                class="text-sm font-semibold text-muted uppercase bg-surface-soft dark:bg-gray-800/50 dark:text-gray-400">
                                <tr class="text-center">
                                    <th class="px-4 py-3">No</th>
                                    <th class="px-4 py-3">Waktu / Pemeriksa</th>
                                    <th class="px-4 py-3">TD</th>
                                    <th class="px-4 py-3">Nadi</th>
                                    <th class="px-4 py-3">Nafas</th>
                                    <th class="px-4 py-3">Suhu</th>
                                    <th class="px-4 py-3">SpO₂</th>
                                    <th class="px-4 py-3">GDA</th>
                                    <th class="px-4 py-3">GCS</th>
                                    <th class="px-4 py-3">Kesadaran</th>
                                    <th class="px-4 py-3">O₂</th>
                                    <th class="px-4 py-3">EWS</th>
                                    <th class="px-4 py-3">Pantau Ulang</th>
                                    <th class="px-4 py-3">Cairan / Tetesan</th>
                                    @if (!$isFormLocked)
                                        <th class="px-4 py-3 text-center">Hapus</th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-hairline-soft dark:divide-gray-800">
                                @forelse ($sortedTtv as $obs)
                                    @php
                                        $ewsItem = is_array($obs['ews'] ?? null) && !empty($obs['ews']['tersedia']) ? $obs['ews'] : null;
                                        $ewsRinci = $ewsItem
                                            ? collect($ewsItem['per'] ?? [])->map(fn($p) => $p['desc'] . ': ' . ($p['skor'] ?? '-'))->implode(' · ')
                                            : '';
                                    @endphp
                                    <tr wire:key="ttv-{{ $obs['id'] ?? $obs['waktuPemeriksaan'] }}"
                                        class="text-center hover:bg-surface-soft dark:hover:bg-gray-800/40 transition">
                                        <td class="px-4 py-3 text-muted dark:text-gray-400">{{ $loop->iteration }}
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap">
                                            <div class="font-medium text-ink dark:text-gray-100 text-sm">
                                                {{ $obs['waktuPemeriksaan'] ?? '-' }}</div>
                                            <div class="text-sm text-muted-soft">{{ $obs['pemeriksa'] ?? '-' }}</div>
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-body dark:text-gray-300">
                                            {{ $obs['sistolik'] ?? '-' }}/{{ $obs['distolik'] ?? '-' }} <span
                                                class="text-sm">mmHg</span>
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-body dark:text-gray-300">
                                            {{ $obs['frekuensiNadi'] ?? '-' }} <span class="text-sm">x/mnt</span></td>
                                        <td class="px-4 py-3 whitespace-nowrap text-body dark:text-gray-300">
                                            {{ $obs['frekuensiNafas'] ?? '-' }} <span class="text-sm">x/mnt</span></td>
                                        <td class="px-4 py-3 whitespace-nowrap text-body dark:text-gray-300">
                                            {{ $obs['suhu'] ?? '-' }} <span class="text-sm">°C</span></td>
                                        <td class="px-4 py-3 whitespace-nowrap text-body dark:text-gray-300">
                                            {{ $obs['spo2'] ?? '-' }} <span class="text-sm">%</span></td>
                                        <td class="px-4 py-3 whitespace-nowrap text-body dark:text-gray-300">
                                            {{ $obs['gda'] ?? '-' }} <span class="text-sm">mg/dL</span></td>
                                        <td class="px-4 py-3 whitespace-nowrap text-body dark:text-gray-300">
                                            {{ $obs['gcs'] ?? '-' }}</td>
                                        <td class="px-4 py-3 whitespace-nowrap text-body dark:text-gray-300">
                                            {{ filled($obs['kesadaran'] ?? null) ? $obs['kesadaran'] : '-' }}</td>
                                        <td class="px-4 py-3 whitespace-nowrap text-body dark:text-gray-300">
                                            @if (($obs['oksigen'] ?? '') === 'O2')
                                                O₂ <span class="text-sm text-muted-soft">{{ $obs['alatOksigen'] ?? '' }}</span>
                                            @elseif (($obs['oksigen'] ?? '') === 'ROOM_AIR')
                                                Room air
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap">
                                            @if ($ewsItem)
                                                <span class="inline-flex items-center justify-center min-w-8 h-7 px-2 rounded font-bold {{ EwsSkor::warnaKelas($ewsItem['warna'] ?? null) }}"
                                                    title="{{ $ewsRinci }}">{{ $ewsItem['total'] }}</span>
                                                <div class="text-sm text-muted-soft">{{ $ewsItem['kategori'] ?? '-' }}{{ ($ewsItem['varian'] ?? '') !== 'DEWASA' ? ' · ' . $ewsItem['varian'] : '' }}</div>
                                                @if (empty($ewsItem['lengkap']))
                                                    <div class="text-sm text-warning-deep" title="{{ implode(', ', $ewsItem['kurang'] ?? []) }}">belum lengkap</div>
                                                @endif
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-body dark:text-gray-300">
                                            @if ($ewsItem && !empty($ewsItem['pantauUlang']))
                                                <div class="font-mono text-sm">{{ $ewsItem['pantauUlang'] }}</div>
                                                <div class="text-sm text-muted-soft">{{ $ewsItem['frekuensi'] ?? '' }}</div>
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-body dark:text-gray-300">
                                            <div>{{ $obs['cairan'] ?? '-' }} <span class="text-sm">ml</span></div>
                                            <div class="text-sm text-muted-soft">{{ $obs['tetesan'] ?? '-' }} gtt/mnt
                                            </div>
                                        </td>
                                        @if (!$isFormLocked)
                                            <td class="px-4 py-3">
                                                <x-outline-button type="button"
                                                    wire:click.prevent="removeObservasiLanjutan('{{ $obs['waktuPemeriksaan'] }}')"
                                                    wire:confirm="Hapus data observasi ini?"
                                                    wire:loading.attr="disabled"
                                                    class="!text-red-600 !bg-red-50 !border-red-200 hover:!bg-red-100 hover:!text-red-700 hover:!border-red-300 dark:!text-red-400 dark:!bg-red-900/20 dark:!border-red-800/30 dark:hover:!bg-red-900/30 dark:hover:!text-red-300"
                                                    title="Hapus">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor"
                                                        viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="2"
                                                            d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                    </svg>
                                                </x-outline-button>
                                            </td>
                                        @endif
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="{{ $isFormLocked ? 14 : 15 }}"
                                            class="px-4 py-10 text-base text-center text-muted-soft dark:text-gray-600">
                                            <svg class="w-8 h-8 mx-auto mb-2 opacity-40" fill="none"
                                                stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                    stroke-width="1.5"
                                                    d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                            </svg>
                                            Belum ada data observasi
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- KETERANGAN SKOR EWS — tabel respon varian aktif (dari master), tertutup secara bawaan --}}
                @if ($this->ewsTersedia())
                    <div x-data="{ terbuka: false }"
                        class="text-xs border rounded-xl border-blue-200 bg-blue-50 text-blue-900 dark:border-blue-900 dark:bg-blue-950/40 dark:text-blue-200">
                        <button type="button" x-on:click="terbuka = !terbuka" class="flex items-center justify-between w-full px-3 py-2 font-semibold text-left">
                            <span>Keterangan skor {{ $this->ewsVarianList()[$ewsVarian] ?? $ewsVarian }}: risiko, frekuensi pantau & respon klinis</span>
                            <span x-text="terbuka ? 'Tutup' : 'Selengkapnya'" class="font-normal underline"></span>
                        </button>
                        <div x-show="terbuka" x-cloak class="px-3 pb-3">
                            <table class="w-full">
                                <thead>
                                    <tr class="text-left">
                                        <th class="py-1 pr-2">Total skor</th>
                                        <th class="py-1 pr-2">Risiko</th>
                                        <th class="py-1 pr-2">Pantau ulang</th>
                                        <th class="py-1">Respon klinis</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($this->ewsResponList() as $respon)
                                        <tr wire:key="ews-ket-{{ $ewsVarian }}-{{ $respon['urutan'] }}" class="align-top border-t border-blue-200/60 dark:border-blue-900/60">
                                            <td class="py-1 pr-2 whitespace-nowrap">
                                                <span class="inline-block px-1.5 rounded font-semibold {{ EwsSkor::warnaKelas($respon['warna']) }}">{{ EwsSkor::labelRespon($respon) }}</span>
                                            </td>
                                            <td class="py-1 pr-2 whitespace-nowrap">{{ $respon['kategori'] }}</td>
                                            <td class="py-1 pr-2 whitespace-nowrap">{{ $respon['frekuensi'] }}</td>
                                            <td class="py-1">{{ $respon['respon'] }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif

                {{-- GRAFIK --}}
                @if (!empty($tandaVitalData))
                    @php
                        $sortedForChart = collect($tandaVitalData)
                            ->sortBy(
                                fn($item) => Carbon::createFromFormat(
                                    'd/m/Y H:i:s',
                                    $item['waktuPemeriksaan'] ?? '01/01/2000 00:00:00',
                                )->timestamp,
                            )
                            ->values();
                        $chartLabels = $sortedForChart->pluck('waktuPemeriksaan')->toArray();
                        $chartSuhu = $sortedForChart
                            ->map(fn($i) => is_numeric($i['suhu'] ?? null) ? (float) $i['suhu'] : null)
                            ->toArray();
                        $chartNadi = $sortedForChart
                            ->map(fn($i) => is_numeric($i['frekuensiNadi'] ?? null) ? (int) $i['frekuensiNadi'] : null)
                            ->toArray();
                    @endphp
                    <div class="p-4 bg-canvas border border-hairline rounded-2xl dark:border-gray-700 dark:bg-gray-900"
                        x-data="{
                            chart: null,
                            initChart() {
                                const ctx = document.getElementById('observasiChart-{{ $rjNo }}');
                                if (!ctx || typeof Chart === 'undefined') return;
                                if (this.chart) {
                                    this.chart.destroy();
                                    this.chart = null;
                                }
                                this.chart = new Chart(ctx, {
                                    type: 'line',
                                    data: {
                                        labels: {{ Js::from($chartLabels) }},
                                        datasets: [{
                                                label: 'Suhu (°C)',
                                                data: {{ Js::from($chartSuhu) }},
                                                borderColor: 'rgba(54,162,235,1)',
                                                borderWidth: 2,
                                                fill: false
                                            },
                                            {
                                                label: 'Nadi (x/mnt)',
                                                data: {{ Js::from($chartNadi) }},
                                                borderColor: 'rgba(255,99,132,1)',
                                                borderWidth: 2,
                                                fill: false
                                            }
                                        ]
                                    },
                                    options: { scales: { y: { beginAtZero: false } } }
                                });
                            }
                        }" x-init="$nextTick(() => initChart())"
                        x-on:livewire:navigated.window="$nextTick(() => initChart())">
                        <p class="mb-2 text-base font-semibold text-body dark:text-gray-300">Grafik Suhu &amp; Nadi
                        </p>
                        <div wire:ignore>
                            <canvas id="observasiChart-{{ $rjNo }}"></canvas>
                        </div>
                    </div>
                @endif
            @else
                <div class="flex flex-col items-center justify-center py-16 text-gray-300 dark:text-gray-600">
                    <svg class="w-10 h-10 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                            d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    <p class="text-base font-medium">Data UGD belum dimuat</p>
                </div>
            @endif

        </div>
    </div>
</div>
