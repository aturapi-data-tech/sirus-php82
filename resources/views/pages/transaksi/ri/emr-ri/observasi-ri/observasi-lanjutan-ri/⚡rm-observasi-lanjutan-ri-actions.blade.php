<?php
// resources/views/pages/transaksi/ri/emr-ri/rm-observasi-lanjutan-ri-actions.blade.php

use Livewire\Component;
use App\Http\Traits\Txn\Ri\EmrRITrait;
use App\Http\Traits\Concerns\WithRenderVersioningTrait;
use App\Http\Traits\Concerns\WithValidationToastTrait;
use App\Support\Ews\EwsDefault;
use App\Support\Ews\EwsMaster;
use App\Support\Ews\EwsSkor;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Livewire\Attributes\On;

new class extends Component {
    use EmrRITrait, WithRenderVersioningTrait, WithValidationToastTrait;

    public bool $isFormLocked = false;
    public ?int $riHdrNo = null; // konsisten dengan komponen obat & cairan
    public array $dataDaftarRi = [];

    /*
     | Satu entri = TTV + parameter EWS. Kunci EWS (kesadaran, oksigen, dst.) ikut
     | disimpan datar di entri yang sama; hasil skornya di sub-array `ews`
     | (keluaran EwsSkor::hitung) supaya cetak/viewer tidak menghitung ulang.
     | Entri lama tanpa `ews` tetap sah — tampil "-".
     */
    public array $formEntryObservasi = [
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

    public array $renderVersions = [];
    protected array $renderAreas = ['modal-observasi-lanjutan-ri'];

    public function mount(): void
    {
        $this->registerAreas(['modal-observasi-lanjutan-ri']);
    }

    /*
     | ── EWS ──
     | Master dibaca lewat EwsMaster (cache 10 mnt). Bila DDL belum dijalankan,
     | master kosong → form EWS tidak muncul, TTV tetap bisa disimpan.
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
        $terakhir = collect($this->dataDaftarRi['observasi']['observasiLanjutan']['tandaVital'] ?? [])
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
        foreach (array_keys($this->formEntryObservasi) as $kode) {
            if (!in_array($kode, self::KUNCI_TTV, true)) {
                $this->formEntryObservasi[$kode] = '';
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
            $rules["formEntryObservasi.{$kode}"] = $param['tipe'] === 'PILIHAN' ? 'required' : 'required|numeric';
            $attributes["formEntryObservasi.{$kode}"] = $param['param_desc'];
        }

        return [$rules, $attributes];
    }

    #[On('open-observasi-lanjutan-ri')]
    public function open(int $riHdrNo): void
    {
        if (empty($riHdrNo)) {
            return;
        }
        $this->riHdrNo = $riHdrNo;
        $this->resetForm();

        $data = $this->findDataRI($riHdrNo);
        if (!$data) {
            $this->dispatch('toast', type: 'error', message: 'Data RI tidak ditemukan.');
            return;
        }

        $this->dataDaftarRi = $data;
        $this->dataDaftarRi['observasi'] ??= [];
        $this->dataDaftarRi['observasi']['observasiLanjutan'] ??= [
            'tandaVitalTab' => 'Observasi Lanjutan',
            'tandaVital' => [],
        ];

        $this->isFormLocked = $this->checkEmrRIStatus($riHdrNo);
        $this->tentukanUmurDanVarian($data['regNo'] ?? null);
        $this->setWaktuPemeriksaan();
        $this->incrementVersion('modal-observasi-lanjutan-ri');
    }

    public function setWaktuPemeriksaan(): void
    {
        $this->formEntryObservasi['waktuPemeriksaan'] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
        $this->incrementVersion('modal-observasi-lanjutan-ri');
    }

    #[On('save-rm-observasi-lanjutan-ri')]
    public function addObservasiLanjutan(): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Pasien sudah pulang.');
            return;
        }

        $this->formEntryObservasi['pemeriksa'] = auth()->user()->myuser_name ?? '';
        [$aturanEws, $atributEws] = $this->aturanEws();
        $this->validateWithToast(
            [
                'formEntryObservasi.waktuPemeriksaan' => 'required|date_format:d/m/Y H:i:s',
                'formEntryObservasi.sistolik' => 'required|numeric',
                'formEntryObservasi.distolik' => 'required|numeric',
                'formEntryObservasi.frekuensiNafas' => 'required|numeric',
                'formEntryObservasi.frekuensiNadi' => 'required|numeric',
                'formEntryObservasi.suhu' => 'required|numeric',
                'formEntryObservasi.spo2' => 'required|numeric',
                'formEntryObservasi.spo2Skala2' => 'nullable|numeric',
                ...$aturanEws,
            ],
            [
                'required' => ':attribute wajib diisi.',
                'numeric' => ':attribute harus berupa angka.',
                'date_format' => ':attribute harus format dd/mm/yyyy hh:mm:ss.',
            ],
            [
                'formEntryObservasi.waktuPemeriksaan' => 'Waktu Pemeriksaan',
                'formEntryObservasi.sistolik' => 'Sistolik',
                'formEntryObservasi.distolik' => 'Diastolik',
                'formEntryObservasi.frekuensiNafas' => 'Frekuensi Nafas',
                'formEntryObservasi.frekuensiNadi' => 'Frekuensi Nadi',
                'formEntryObservasi.suhu' => 'Suhu',
                'formEntryObservasi.spo2' => 'SpO₂',
                'formEntryObservasi.spo2Skala2' => 'SpO₂ skala 2',
                ...$atributEws,
            ],
        );

        // Skor EWS dihitung SEKALI di sini dan disimpan bersama entri.
        $hasilEws = $this->ewsTersedia()
            ? EwsSkor::hitung($this->ewsVarian, $this->formEntryObservasi, $this->ewsMaster(), $this->umurBulan)
            : null;
        if ($hasilEws !== null && $hasilEws['frekuensiMenit'] !== null) {
            $hasilEws['pantauUlang'] = Carbon::createFromFormat('d/m/Y H:i:s', $this->formEntryObservasi['waktuPemeriksaan'])
                ->addMinutes($hasilEws['frekuensiMenit'])->format('d/m/Y H:i');
        }

        try {
            DB::transaction(function () use ($hasilEws) {
                // 1. Lock row
                $this->lockRIRow($this->riHdrNo);

                // 2. Baca data terkini setelah lock
                $data = $this->findDataRI($this->riHdrNo);
                if (empty($data)) {
                    throw new \RuntimeException('Data RI tidak ditemukan.');
                }

                // 3. Inisialisasi struktur jika perlu
                $data['observasi']['observasiLanjutan']['tandaVital'] ??= [];

                // 4. Cek duplikasi waktu
                $exists = collect($data['observasi']['observasiLanjutan']['tandaVital'])->contains('waktuPemeriksaan', $this->formEntryObservasi['waktuPemeriksaan']);
                if ($exists) {
                    throw new \RuntimeException('Waktu pemeriksaan sudah ada.');
                }

                // 5. Tambah data
                $data['observasi']['observasiLanjutan']['tandaVital'][] = array_merge($this->formEntryObservasi, [
                    'sistolik' => (int) $this->formEntryObservasi['sistolik'],
                    'distolik' => (int) $this->formEntryObservasi['distolik'],
                    'frekuensiNafas' => (int) $this->formEntryObservasi['frekuensiNafas'],
                    'frekuensiNadi' => (int) $this->formEntryObservasi['frekuensiNadi'],
                    'suhu' => (float) $this->formEntryObservasi['suhu'],
                    'spo2' => (int) $this->formEntryObservasi['spo2'],
                    'gda' => $this->formEntryObservasi['gda'] === '' ? null : (float) $this->formEntryObservasi['gda'],
                    'gcs' => $this->formEntryObservasi['gcs'] === '' ? null : (int) $this->formEntryObservasi['gcs'],
                    'spo2Skala2' => $this->formEntryObservasi['spo2Skala2'] === '' ? null : (int) $this->formEntryObservasi['spo2Skala2'],
                    'ewsVarian' => $this->ewsVarian,
                    'ews' => $hasilEws,
                ]);

                // 6. Simpan JSON
                $this->updateJsonRI($this->riHdrNo, $data);
                $this->dataDaftarRi = $data;

                // 7. Audit log
                $ringkasEws = $hasilEws === null ? '' : ' - EWS ' . $hasilEws['total'] . ' (' . ($hasilEws['kategori'] ?? '-') . ')';
                $this->appendAdminLogRI((int) $this->riHdrNo, 'Tambah Observasi Lanjutan — entri ' . ($this->formEntryObservasi['waktuPemeriksaan'] ?? '-') . $ringkasEws, 'MR');
            });

            $this->reset(['formEntryObservasi']);
            $this->setWaktuPemeriksaan();
            $this->incrementVersion('modal-observasi-lanjutan-ri');
            $this->dispatch('refresh-after-ri.saved', tab: 'observasi', subTab: 'ttv');
            $pesan = 'Observasi berhasil disimpan.';
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

    public function removeObservasiLanjutan(string $waktuPemeriksaan): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Pasien sudah pulang.');
            return;
        }

        try {
            DB::transaction(function () use ($waktuPemeriksaan) {
                $this->lockRIRow($this->riHdrNo);
                $data = $this->findDataRI($this->riHdrNo);
                if (empty($data)) {
                    throw new \RuntimeException('Data RI tidak ditemukan.');
                }

                $data['observasi']['observasiLanjutan']['tandaVital'] = collect($data['observasi']['observasiLanjutan']['tandaVital'] ?? [])
                    ->reject(fn($r) => trim($r['waktuPemeriksaan'] ?? '') === trim($waktuPemeriksaan))
                    ->values()
                    ->all();

                $this->updateJsonRI($this->riHdrNo, $data);
                $this->dataDaftarRi = $data;

                // Audit log
                $this->appendAdminLogRI((int) $this->riHdrNo, 'Hapus Observasi Lanjutan — entri ' . $waktuPemeriksaan, 'MR');
            });

            $this->incrementVersion('modal-observasi-lanjutan-ri');
            $this->dispatch('refresh-after-ri.saved', tab: 'observasi', subTab: 'ttv');
            $this->dispatch('toast', type: 'success', message: 'Observasi berhasil dihapus.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menghapus: ' . $e->getMessage());
        }
    }

    protected function resetForm(): void
    {
        $this->resetVersion();
        $this->isFormLocked = false;
        $this->dataDaftarRi = [];
        $this->reset(['formEntryObservasi']);
    }
};
?>

<div>
    <div class="flex flex-col w-full"
        wire:key="{{ $this->renderKey('modal-observasi-lanjutan-ri', [$riHdrNo ?? 'new']) }}">
        <div
            class="w-full p-4 space-y-6 bg-canvas border border-hairline shadow-sm rounded-2xl dark:bg-gray-900 dark:border-gray-700">

            @if ($isFormLocked)
                <div
                    class="flex items-center gap-2 px-4 py-2.5 text-sm font-medium text-amber-700 bg-amber-50 border border-amber-200 rounded-xl dark:bg-amber-900/20 dark:border-amber-600 dark:text-amber-300">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                    </svg>
                    EMR terkunci — data tidak dapat diubah.
                </div>
            @endif

            {{-- FORM INPUT --}}
            @if (!$isFormLocked)
                <div class="p-4 border border-hairline rounded-2xl dark:border-gray-700 bg-surface-soft dark:bg-gray-800/40">
                    {{-- BARIS EWS: parameter tambahan varian aktif (dari master, bukan hard-code) — skor dihitung
                         otomatis saat Simpan. Enter di dalam baris ini pindah ke field berikutnya; di field terakhir turun ke baris TTV.
                         Tidak tampil bila master EWS belum ada di environment (DDL belum jalan) — TTV tetap bisa disimpan. --}}
                    @if ($this->ewsTersedia())
                        @php
                            $ewsParamTambahan = $this->ewsParamTambahan();
                            $acuanNadi = $this->ewsAcuanUsia('nadiNormal');
                            $acuanNafas = $this->ewsAcuanUsia('nafasNormal');
                        @endphp
                        <div class="pb-3 mb-3 border-b border-hairline dark:border-gray-700"
                            x-on:keydown.enter.prevent="
                                const sel = 'input:not([type=hidden]), select';
                                if (!$event.target.matches(sel)) return;
                                const els = [...$el.querySelectorAll(sel)].filter(x => !x.disabled && x.offsetParent !== null);
                                const i = els.indexOf($event.target);
                                if (i > -1 && i < els.length - 1) { els[i + 1].focus() } else { $refs.olWaktu?.focus() }">
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
                                <span class="text-xs text-muted-soft">Skor, frekuensi pantau & respon dihitung otomatis saat Simpan.</span>
                            </div>
                            <div class="grid grid-cols-2 gap-2 md:grid-cols-4 xl:grid-cols-6 items-stretch">
                                @foreach ($ewsParamTambahan as $kode => $param)
                                    <div class="flex flex-col {{ $param['tipe'] === 'PILIHAN' && count($param['pilihan']) > 2 ? 'col-span-2' : '' }}"
                                        wire:key="ews-field-{{ $ewsVarian }}-{{ $kode }}">
                                        <x-input-label class="mb-1">{{ $param['param_desc'] }}{{ ($param['wajib'] ?? '1') === '1' ? ' *' : '' }}</x-input-label>
                                        @if ($param['tipe'] === 'PILIHAN')
                                            <x-select-input wire:model="formEntryObservasi.{{ $kode }}" class="w-full mt-auto"
                                                :error="$errors->has('formEntryObservasi.' . $kode)" :id="$loop->first ? 'ews-ri-first' : null">
                                                <option value="">— pilih —</option>
                                                @foreach ($param['pilihan'] as $pilihanKode => $pilihanLabel)
                                                    <option value="{{ $pilihanKode }}">{{ $pilihanLabel }}</option>
                                                @endforeach
                                            </x-select-input>
                                        @else
                                            <x-text-input wire:model="formEntryObservasi.{{ $kode }}" type="number" step="0.1"
                                                placeholder="{{ $param['satuan'] ?? '' }}" class="w-full mt-auto"
                                                :error="$errors->has('formEntryObservasi.' . $kode)" :id="$loop->first ? 'ews-ri-first' : null" />
                                        @endif
                                        <x-input-error :messages="$errors->get('formEntryObservasi.' . $kode)" class="mt-1" />
                                    </div>
                                    @if ($kode === 'oksigen')
                                        <div class="flex flex-col" wire:key="ews-field-{{ $ewsVarian }}-alatOksigen">
                                            <x-input-label class="mb-1">Alat O₂ / lpm</x-input-label>
                                            <x-text-input wire:model="formEntryObservasi.alatOksigen" placeholder="NRBM 10 lpm" class="w-full mt-auto" />
                                        </div>
                                    @endif
                                @endforeach
                            </div>
                        </div>
                    @endif

                    {{-- SATU BARIS: Waktu Pemeriksaan, Cairan, Tetesan, lalu semua nilai numerik.
                         15 kolom di layar lebar (waktu 3 + cairan 2 + tetesan 2 + 8 field @1) — di layar sempit membungkus.
                         Tiap sel flex-col + input mt-auto: label boleh 1 atau 2 baris, kotak input tetap rata bawah.
                         Enter-chain (pola e-resep): waktu → cairan → tetesan → sistolik → ... → gcs → simpan. --}}
                    <div class="grid grid-cols-2 md:grid-cols-4 xl:grid-cols-[repeat(15,minmax(0,1fr))] gap-2 items-stretch">
                        <div class="flex flex-col col-span-2 xl:col-span-3">
                            <x-input-label value="Waktu Pemeriksaan *" class="mb-1" />
                            <div class="flex items-center gap-1 mt-auto">
                                <x-text-input wire:model="formEntryObservasi.waktuPemeriksaan"
                                    placeholder="dd/mm/yyyy HH:ii:ss" class="flex-1" x-ref="olWaktu"
                                    x-init="$nextTick(() => (document.getElementById('ews-ri-first') ?? $el).focus())"
                                    x-on:keydown.enter.prevent="$refs.olCairan.focus()" />
                                <x-now-button wire:click.prevent="setWaktuPemeriksaan" />
                            </div>
                            <x-input-error :messages="$errors->get('formEntryObservasi.waktuPemeriksaan')" class="mt-1" />
                        </div>
                        <div class="flex flex-col col-span-2 xl:col-span-2">
                            <x-input-label value="Cairan" class="mb-1" />
                            <x-text-input wire:model="formEntryObservasi.cairan" placeholder="Jenis cairan"
                                class="w-full mt-auto" x-ref="olCairan"
                                x-on:keydown.enter.prevent="$refs.olTetesan.focus()" />
                            <x-input-error :messages="$errors->get('formEntryObservasi.cairan')" class="mt-1" />
                        </div>
                        <div class="flex flex-col xl:col-span-2">
                            <x-input-label class="mb-1">Tetesan<br>(tetes/menit)</x-input-label>
                            <x-text-input wire:model="formEntryObservasi.tetesan" placeholder="Tetesan/menit"
                                class="w-full mt-auto" x-ref="olTetesan"
                                x-on:keydown.enter.prevent="$refs.olSistolik.focus()" />
                            <x-input-error :messages="$errors->get('formEntryObservasi.tetesan')" class="mt-1" />
                        </div>
                        <div class="flex flex-col">
                            <x-input-label class="mb-1">Sistolik<br>(mmHg)</x-input-label>
                            <x-text-input wire:model="formEntryObservasi.sistolik" type="number"
                                class="w-full mt-auto" x-ref="olSistolik"
                                x-on:keydown.enter.prevent="$refs.olDistolik.focus()" />
                            <x-input-error :messages="$errors->get('formEntryObservasi.sistolik')" class="mt-1" />
                        </div>
                        <div class="flex flex-col">
                            <x-input-label class="mb-1">Diastolik<br>(mmHg)</x-input-label>
                            <x-text-input wire:model="formEntryObservasi.distolik" type="number"
                                class="w-full mt-auto" x-ref="olDistolik"
                                x-on:keydown.enter.prevent="$refs.olNadi.focus()" />
                            <x-input-error :messages="$errors->get('formEntryObservasi.distolik')" class="mt-1" />
                        </div>
                        <div class="flex flex-col">
                            <x-input-label class="mb-1">Nadi<br>(x/mnt)</x-input-label>
                            <x-text-input wire:model="formEntryObservasi.frekuensiNadi" type="number"
                                class="w-full mt-auto" x-ref="olNadi"
                                x-on:keydown.enter.prevent="$refs.olNafas.focus()" />
                            <x-input-error :messages="$errors->get('formEntryObservasi.frekuensiNadi')" class="mt-1" />
                        </div>
                        <div class="flex flex-col">
                            <x-input-label class="mb-1">Nafas<br>(x/mnt)</x-input-label>
                            <x-text-input wire:model="formEntryObservasi.frekuensiNafas" type="number"
                                class="w-full mt-auto" x-ref="olNafas"
                                x-on:keydown.enter.prevent="$refs.olSuhu.focus()" />
                            <x-input-error :messages="$errors->get('formEntryObservasi.frekuensiNafas')" class="mt-1" />
                        </div>
                        <div class="flex flex-col">
                            <x-input-label class="mb-1">Suhu<br>(°C)</x-input-label>
                            <x-text-input wire:model="formEntryObservasi.suhu" type="number"
                                step="0.1" class="w-full mt-auto" x-ref="olSuhu"
                                x-on:keydown.enter.prevent="$refs.olSpo2.focus()" />
                            <x-input-error :messages="$errors->get('formEntryObservasi.suhu')" class="mt-1" />
                        </div>
                        <div class="flex flex-col">
                            <x-input-label class="mb-1">SpO₂<br>(%)</x-input-label>
                            <x-text-input wire:model="formEntryObservasi.spo2" type="number"
                                class="w-full mt-auto" x-ref="olSpo2"
                                x-on:keydown.enter.prevent="$refs.olGda.focus()" />
                            <x-input-error :messages="$errors->get('formEntryObservasi.spo2')" class="mt-1" />
                        </div>
                        <div class="flex flex-col">
                            <x-input-label class="mb-1">GDA<br>(g/dL)</x-input-label>
                            <x-text-input wire:model="formEntryObservasi.gda" type="number"
                                step="0.1" class="w-full mt-auto" x-ref="olGda"
                                x-on:keydown.enter.prevent="$refs.olGcs.focus()" />
                            <x-input-error :messages="$errors->get('formEntryObservasi.gda')" class="mt-1" />
                        </div>
                        <div class="flex flex-col">
                            <x-input-label class="mb-1">GCS<br>&nbsp;</x-input-label>
                            <x-text-input wire:model="formEntryObservasi.gcs" type="number"
                                class="w-full mt-auto" x-ref="olGcs"
                                x-on:keydown.enter.prevent="$el.blur(); $wire.addObservasiLanjutan()" />
                            <x-input-error :messages="$errors->get('formEntryObservasi.gcs')" class="mt-1" />
                        </div>
                    </div>

                </div>
            @endif

            {{-- TABEL DATA --}}
            @php
                $daftarObs = $dataDaftarRi['observasi']['observasiLanjutan']['tandaVital'] ?? [];
                $sortedObs = collect($daftarObs)
                    ->sortByDesc(
                        fn($item) => Carbon::createFromFormat(
                            'd/m/Y H:i:s',
                            $item['waktuPemeriksaan'] ?? '01/01/2000 00:00:00',
                        )->timestamp,
                    )
                    ->values();
            @endphp

            <div
                class="overflow-hidden bg-canvas border border-hairline rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                <div class="flex items-center justify-between px-4 py-3 border-b border-hairline dark:border-gray-700">
                    <h3 class="text-sm font-semibold text-body dark:text-gray-300">Riwayat Observasi Lanjutan
                    </h3>
                    <x-badge variant="gray">{{ count($daftarObs) }} item</x-badge>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left">
                        <thead
                            class="text-xs font-semibold text-muted uppercase bg-surface-soft dark:bg-gray-800/50 dark:text-gray-400">
                            <tr>
                                <th class="px-4 py-3">No</th>
                                <th class="px-4 py-3">Waktu</th>
                                <th class="px-4 py-3">Cairan</th>
                                <th class="px-4 py-3">Tetesan</th>
                                <th class="px-4 py-3">TD</th>
                                <th class="px-4 py-3">Nadi</th>
                                <th class="px-4 py-3">Nafas</th>
                                <th class="px-4 py-3">Suhu</th>
                                <th class="px-4 py-3">SpO₂</th>
                                <th class="px-4 py-3">GDA</th>
                                <th class="px-4 py-3">GCS</th>
                                <th class="px-4 py-3">Kesadaran</th>
                                <th class="px-4 py-3">O₂</th>
                                <th class="px-4 py-3 text-center">EWS</th>
                                <th class="px-4 py-3">Pantau Ulang</th>
                                <th class="px-4 py-3">Pemeriksa</th>
                                @if (!$isFormLocked)
                                    <th class="px-4 py-3 text-center w-20">Hapus</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-hairline-soft dark:divide-gray-800">
                            @forelse ($sortedObs as $item)
                                @php
                                    $ewsItem = is_array($item['ews'] ?? null) && !empty($item['ews']['tersedia']) ? $item['ews'] : null;
                                    $ewsRinci = $ewsItem
                                        ? collect($ewsItem['per'] ?? [])->map(fn($p) => $p['desc'] . ': ' . ($p['skor'] ?? '-'))->implode(' · ')
                                        : '';
                                @endphp
                                <tr wire:key="obs-{{ $item['waktuPemeriksaan'] ?? '' }}"
                                    class="hover:bg-surface-soft dark:hover:bg-gray-800/40 transition">
                                    <td class="px-4 py-3 text-muted dark:text-gray-400">{{ $loop->iteration }}
                                    </td>
                                    <td class="px-4 py-3 font-mono whitespace-nowrap">
                                        {{ $item['waktuPemeriksaan'] ?? '-' }}</td>
                                    <td class="px-4 py-3">{{ $item['cairan'] ?? '-' }}</td>
                                    <td class="px-4 py-3">{{ $item['tetesan'] ?? '-' }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        {{ ($item['sistolik'] ?? '-') . '/' . ($item['distolik'] ?? '-') }}</td>
                                    <td class="px-4 py-3">{{ $item['frekuensiNadi'] ?? '-' }}</td>
                                    <td class="px-4 py-3">{{ $item['frekuensiNafas'] ?? '-' }}</td>
                                    <td class="px-4 py-3">{{ $item['suhu'] ?? '-' }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap">{{ $item['spo2'] ?? '-' }}@if (filled($item['spo2Skala2'] ?? null)) <span class="text-xs text-muted-soft" title="SpO₂ skala 2">(S2 {{ $item['spo2Skala2'] }})</span>@endif</td>
                                    <td class="px-4 py-3">{{ $item['gda'] ?? '-' }}</td>
                                    <td class="px-4 py-3">{{ $item['gcs'] ?? '-' }}</td>
                                    <td class="px-4 py-3">{{ filled($item['kesadaran'] ?? null) ? $item['kesadaran'] : '-' }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        @if (($item['oksigen'] ?? '') === 'O2')
                                            O₂ <span class="text-xs text-muted-soft">{{ $item['alatOksigen'] ?? '' }}</span>
                                        @elseif (($item['oksigen'] ?? '') === 'ROOM_AIR')
                                            Room air
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-center whitespace-nowrap">
                                        @if ($ewsItem)
                                            <span class="inline-flex items-center justify-center min-w-8 h-7 px-2 rounded font-bold {{ EwsSkor::warnaKelas($ewsItem['warna'] ?? null) }}"
                                                title="{{ $ewsRinci }}">{{ $ewsItem['total'] }}</span>
                                            <div class="text-xs text-muted-soft">{{ $ewsItem['kategori'] ?? '-' }}{{ ($ewsItem['varian'] ?? '') !== 'DEWASA' ? ' · ' . $ewsItem['varian'] : '' }}</div>
                                            @if (empty($ewsItem['lengkap']))
                                                <div class="text-xs text-warning-deep" title="{{ implode(', ', $ewsItem['kurang'] ?? []) }}">belum lengkap</div>
                                            @endif
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        @if ($ewsItem && !empty($ewsItem['pantauUlang']))
                                            <div class="font-mono">{{ $ewsItem['pantauUlang'] }}</div>
                                            <div class="text-xs text-muted-soft">{{ $ewsItem['frekuensi'] ?? '' }}</div>
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td class="px-4 py-3">{{ $item['pemeriksa'] ?? '-' }}</td>
                                    @if (!$isFormLocked)
                                        <td class="px-4 py-3 text-center">
                                            <x-outline-button type="button"
                                                wire:click.prevent="removeObservasiLanjutan('{{ $item['waktuPemeriksaan'] }}')"
                                                wire:confirm="Hapus data observasi ini?" wire:loading.attr="disabled"
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
                                    <td colspan="{{ $isFormLocked ? 16 : 17 }}"
                                        class="px-4 py-10 text-sm text-center text-muted-soft dark:text-gray-600">
                                        <svg class="w-8 h-8 mx-auto mb-2 opacity-40" fill="none"
                                            stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                                d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                        </svg>
                                        Belum ada data observasi lanjutan
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

        </div>
    </div>
</div>
