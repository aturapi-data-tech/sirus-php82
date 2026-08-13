<?php
// resources/views/pages/transaksi/ri/emr-ri/observasi-ri/alat-invasif-ri/rm-alat-invasif-ri-actions.blade.php
//
// Catatan pemasangan alat invasif per pasien — sumber PENYEBUT (hari pemakaian alat)
// pada Laporan Surveilans HAIs. Wajib diisi untuk SETIAP pasien terpasang alat,
// bukan hanya yang dicurigai infeksi; formulir Surveilans HAIs yang memasok kasusnya.
// Pola meniru Observasi RI → Pemakaian Oksigen (mulai/selesai + set waktu lepas).

use Livewire\Component;
use App\Http\Traits\Txn\Ri\EmrRITrait;
use App\Http\Traits\Concerns\WithRenderVersioningTrait;
use App\Http\Traits\Concerns\WithValidationToastTrait;
use App\Support\Options\SurveilansHaisOptions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use Livewire\Attributes\On;

new class extends Component {
    use EmrRITrait, WithRenderVersioningTrait, WithValidationToastTrait;

    public bool $isFormLocked = false;
    public ?int $riHdrNo = null;
    public array $dataDaftarRi = [];

    public array $formEntryAlat = [
        'jenisAlat' => 'ivPerifer',
        'lokasi' => '',
        'tanggalWaktuMulai' => '',
        'tanggalWaktuSelesai' => '',
        'keterangan' => '',
    ];

    public array $renderVersions = [];
    protected array $renderAreas = ['modal-alat-invasif-ri'];

    public function mount(): void
    {
        $this->registerAreas(['modal-alat-invasif-ri']);
    }

    #[On('open-alat-invasif-ri')]
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
        $this->dataDaftarRi['observasi']['alatInvasif'] ??= [
            'alatInvasifTab' => 'Alat Invasif',
            'alatInvasifData' => [],
        ];

        $this->isFormLocked = $this->checkEmrRIStatus($riHdrNo);
        $this->setWaktuMulaiAlat();
        $this->incrementVersion('modal-alat-invasif-ri');
    }

    public function setWaktuMulaiAlat(): void
    {
        $this->formEntryAlat['tanggalWaktuMulai'] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
        $this->incrementVersion('modal-alat-invasif-ri');
    }

    public function setWaktuSelesaiAlat(): void
    {
        $this->formEntryAlat['tanggalWaktuSelesai'] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
        $this->incrementVersion('modal-alat-invasif-ri');
    }

    #[On('save-rm-alat-invasif-ri')]
    public function addAlatInvasif(): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Pasien sudah pulang.');
            return;
        }

        $this->validateWithToast(
            [
                'formEntryAlat.jenisAlat' => 'required|in:' . implode(',', array_keys(SurveilansHaisOptions::PENYEBUT_HAIS)),
                'formEntryAlat.lokasi' => 'nullable|string|max:100',
                'formEntryAlat.tanggalWaktuMulai' => 'required|date_format:d/m/Y H:i:s',
                'formEntryAlat.tanggalWaktuSelesai' => 'nullable|date_format:d/m/Y H:i:s|after:formEntryAlat.tanggalWaktuMulai',
                'formEntryAlat.keterangan' => 'nullable|string|max:255',
            ],
            [
                'in' => ':attribute tidak valid.',
                'date_format' => 'Format :attribute harus dd/mm/yyyy HH:ii:ss.',
                'after' => 'Waktu lepas harus setelah waktu pasang.',
            ],
            [
                'formEntryAlat.jenisAlat' => 'Jenis',
                'formEntryAlat.lokasi' => 'Lokasi pemasangan',
                'formEntryAlat.tanggalWaktuMulai' => 'Waktu pasang',
                'formEntryAlat.tanggalWaktuSelesai' => 'Waktu lepas',
                'formEntryAlat.keterangan' => 'Keterangan',
            ],
        );

        try {
            DB::transaction(function () {
                $this->lockRIRow($this->riHdrNo);
                $data = $this->findDataRI($this->riHdrNo);
                if (empty($data)) {
                    throw new \RuntimeException('Data RI tidak ditemukan.');
                }

                $data['observasi']['alatInvasif']['alatInvasifData'] ??= [];

                // Satu pasien boleh punya beberapa alat berbeda pada waktu pasang yang sama,
                // jadi kunci duplikat = kombinasi jenis alat + waktu pasang.
                $duplikat = collect($data['observasi']['alatInvasif']['alatInvasifData'])
                    ->contains(fn($baris) => ($baris['jenisAlat'] ?? '') === $this->formEntryAlat['jenisAlat']
                        && trim((string) ($baris['tanggalWaktuMulai'] ?? '')) === trim($this->formEntryAlat['tanggalWaktuMulai']));
                if ($duplikat) {
                    throw new \RuntimeException('Jenis dengan waktu mulai tersebut sudah ada.');
                }

                $data['observasi']['alatInvasif']['alatInvasifData'][] = array_merge($this->formEntryAlat, [
                    'pemeriksa' => auth()->user()->myuser_name,
                    'tanggalWaktuSelesai' => $this->formEntryAlat['tanggalWaktuSelesai'] ?: null,
                ]);

                $this->updateJsonRI($this->riHdrNo, $data);
                $this->dataDaftarRi = $data;

                $this->appendAdminLogRI(
                    (int) $this->riHdrNo,
                    'Tambah Alat Invasif — ' . SurveilansHaisOptions::label(SurveilansHaisOptions::PENYEBUT_HAIS, $this->formEntryAlat['jenisAlat'])
                        . ', pasang ' . ($this->formEntryAlat['tanggalWaktuMulai'] ?? '-'),
                    'MR',
                );
            });

            $this->reset(['formEntryAlat']);
            $this->setWaktuMulaiAlat();
            $this->incrementVersion('modal-alat-invasif-ri');
            $this->dispatch('refresh-after-ri.saved', tab: 'observasi', subTab: 'alat-invasif');
            $this->dispatch('toast', type: 'success', message: 'Data berhasil ditambahkan.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menyimpan: ' . $e->getMessage());
        }
    }

    /**
     * Isi/ubah waktu lepas satu baris. Kuncinya jenis alat + waktu pasang karena
     * daftar di-sort saat render, jadi index visual beda dari index penyimpanan.
     */
    public function updateWaktuLepas(string $jenisAlat, string $waktuPasang, string $waktuLepas): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Pasien sudah pulang.');
            return;
        }

        $validator = Validator::make(
            ['waktuPasang' => $waktuPasang, 'waktuLepas' => $waktuLepas],
            [
                'waktuPasang' => 'required|date_format:d/m/Y H:i:s',
                'waktuLepas' => 'required|date_format:d/m/Y H:i:s|after:waktuPasang',
            ],
            [
                'waktuPasang.required' => 'Waktu pasang tidak ditemukan.',
                'waktuPasang.date_format' => 'Format waktu pasang tidak valid.',
                'waktuLepas.required' => 'Waktu lepas wajib diisi.',
                'waktuLepas.date_format' => 'Format waktu lepas harus dd/mm/yyyy HH:ii:ss.',
                'waktuLepas.after' => 'Waktu lepas harus setelah waktu pasang.',
            ],
        );

        if ($validator->fails()) {
            $this->dispatch('toast', type: 'error', message: $validator->errors()->first());
            return;
        }

        try {
            DB::transaction(function () use ($jenisAlat, $waktuPasang, $waktuLepas) {
                $this->lockRIRow($this->riHdrNo);
                $data = $this->findDataRI($this->riHdrNo);
                if (empty($data)) {
                    throw new \RuntimeException('Data RI tidak ditemukan.');
                }

                $daftar = $data['observasi']['alatInvasif']['alatInvasifData'] ?? [];
                $ketemu = false;

                foreach ($daftar as &$baris) {
                    if (($baris['jenisAlat'] ?? '') === $jenisAlat
                        && trim((string) ($baris['tanggalWaktuMulai'] ?? '')) === trim($waktuPasang)) {
                        $baris['tanggalWaktuSelesai'] = $waktuLepas;
                        $ketemu = true;
                        break;
                    }
                }
                unset($baris);

                if (!$ketemu) {
                    throw new \RuntimeException('Baris alat tersebut tidak ditemukan.');
                }

                $data['observasi']['alatInvasif']['alatInvasifData'] = array_values($daftar);
                $this->updateJsonRI($this->riHdrNo, $data);
                $this->dataDaftarRi = $data;

                $this->appendAdminLogRI((int) $this->riHdrNo, 'Set waktu lepas Alat Invasif — ' . $waktuLepas, 'MR');
            });

            $this->incrementVersion('modal-alat-invasif-ri');
            $this->dispatch('refresh-after-ri.saved', tab: 'observasi', subTab: 'alat-invasif');
            $this->dispatch('toast', type: 'success', message: 'Waktu lepas diperbarui.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal memperbarui: ' . $e->getMessage());
        }
    }

    public function removeAlatInvasif(string $jenisAlat, string $waktuPasang): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Pasien sudah pulang.');
            return;
        }

        try {
            DB::transaction(function () use ($jenisAlat, $waktuPasang) {
                $this->lockRIRow($this->riHdrNo);
                $data = $this->findDataRI($this->riHdrNo);
                if (empty($data)) {
                    throw new \RuntimeException('Data RI tidak ditemukan.');
                }

                $data['observasi']['alatInvasif']['alatInvasifData'] = collect($data['observasi']['alatInvasif']['alatInvasifData'] ?? [])
                    ->reject(fn($baris) => ($baris['jenisAlat'] ?? '') === $jenisAlat
                        && trim((string) ($baris['tanggalWaktuMulai'] ?? '')) === trim($waktuPasang))
                    ->values()
                    ->all();

                $this->updateJsonRI($this->riHdrNo, $data);
                $this->dataDaftarRi = $data;

                $this->appendAdminLogRI((int) $this->riHdrNo, 'Hapus Alat Invasif — pasang ' . $waktuPasang, 'MR');
            });

            $this->incrementVersion('modal-alat-invasif-ri');
            $this->dispatch('refresh-after-ri.saved', tab: 'observasi', subTab: 'alat-invasif');
            $this->dispatch('toast', type: 'success', message: 'Data berhasil dihapus.');
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
        $this->reset(['formEntryAlat']);
    }
};
?>

@php
    $opsiPenyebutHais = \App\Support\Options\SurveilansHaisOptions::PENYEBUT_HAIS;
@endphp

<div>
    <div class="flex flex-col w-full" wire:key="{{ $this->renderKey('modal-alat-invasif-ri', [$riHdrNo ?? 'new']) }}">
        <div class="w-full p-4 space-y-6 bg-canvas border border-hairline shadow-sm rounded-2xl dark:bg-gray-900 dark:border-gray-700">

            @if ($isFormLocked)
                <div class="flex items-center gap-2 px-4 py-2.5 text-sm font-medium text-amber-700 bg-amber-50 border border-amber-200 rounded-xl dark:bg-amber-900/20 dark:border-amber-600 dark:text-amber-300">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                    </svg>
                    EMR terkunci — data tidak dapat diubah.
                </div>
            @endif

            {{-- PANEL PANDUAN --}}
            <div class="border rounded-xl bg-blue-50 border-blue-200 dark:bg-blue-900/20 dark:border-blue-700"
                x-data="{ showPanduan: false }">
                <button type="button" x-on:click="showPanduan = !showPanduan"
                    class="flex items-center justify-between w-full px-4 py-3 text-left">
                    <span class="flex items-center gap-2 text-sm font-semibold text-ink dark:text-gray-100">
                        <svg class="w-4 h-4 text-blue-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        Kenapa data ini penting — dasar penyebut Laporan Surveilans HAIs
                    </span>
                    <svg class="w-4 h-4 text-blue-600 transition-transform" :class="showPanduan && 'rotate-180'"
                        fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>
                <div x-show="showPanduan" x-collapse style="display:none" class="px-4 pb-4 space-y-3">
                    <ul class="pl-5 space-y-1 text-sm list-disc text-body dark:text-gray-300">
                        <li>Catat <b>setiap</b> pasien yang terpasang alat — bukan hanya yang dicurigai infeksi. Kalau hanya pasien bermasalah yang tercatat, penyebutnya timpang dan insiden rate jadi melonjak jauh di atas kenyataan.</li>
                        <li><b>Waktu lepas wajib diisi</b> begitu alat dilepas. Selama kosong, alat dianggap masih terpasang dan hari pemakaiannya terus bertambah.</li>
                        <li>Lama pemakaian di sini menjadi <b>penyebut</b>: IV line perifer &rarr; plebitis, CVC/umbilikal &rarr; IAD, kateter urine &rarr; ISK, ventilator &rarr; VAP (per 1000 hari alat).</li>
                        <li><b>Pembilangnya</b> (kasus infeksi) tetap dari <b>Modul Dokumen &rarr; Surveilans HAIs</b>, bukan dari tab ini.</li>
                    </ul>
                </div>
            </div>

            {{-- FORM INPUT --}}
            @if (!$isFormLocked)
                <div class="p-4 border border-hairline rounded-2xl dark:border-gray-700 bg-surface-soft dark:bg-gray-800/40">
                    {{-- Rantai Enter: waktu pasang → jenis alat → lokasi → waktu lepas → simpan --}}
                    <div class="grid grid-cols-12 gap-3">

                        <div class="col-span-3">
                            <x-input-label value="Waktu Pasang *" class="mb-1" />
                            <div class="flex items-center gap-1">
                                <x-text-input wire:model="formEntryAlat.tanggalWaktuMulai"
                                    placeholder="dd/mm/yyyy HH:ii:ss" class="flex-1" x-ref="alatWaktuPasang"
                                    x-init="$nextTick(() => $el.focus())"
                                    x-on:keydown.enter.prevent="$refs.alatJenis.focus()" />
                                <x-now-button wire:click.prevent="setWaktuMulaiAlat" />
                            </div>
                            <x-input-error :messages="$errors->get('formEntryAlat.tanggalWaktuMulai')" class="mt-1" />
                        </div>

                        <div class="col-span-3">
                            <x-input-label value="Jenis *" class="mb-1" />
                            <x-select-input wire:model="formEntryAlat.jenisAlat" class="w-full"
                                x-ref="alatJenis" x-on:keydown.enter.prevent="$refs.alatLokasi.focus()">
                                @foreach ($opsiPenyebutHais as $kunciAlat => $labelAlat)
                                    <option value="{{ $kunciAlat }}">{{ $labelAlat }}</option>
                                @endforeach
                            </x-select-input>
                            <x-input-error :messages="$errors->get('formEntryAlat.jenisAlat')" class="mt-1" />
                        </div>

                        <div class="col-span-3">
                            <x-input-label value="Lokasi" class="mb-1" />
                            <x-text-input wire:model="formEntryAlat.lokasi" class="w-full" x-ref="alatLokasi"
                                placeholder="mis. vena metacarpal dextra"
                                x-on:keydown.enter.prevent="$refs.alatWaktuLepas.focus()" />
                            <x-input-error :messages="$errors->get('formEntryAlat.lokasi')" class="mt-1" />
                        </div>

                        <div class="col-span-3">
                            <x-input-label value="Waktu Lepas" class="mb-1" />
                            <div class="flex items-center gap-1">
                                <x-text-input wire:model="formEntryAlat.tanggalWaktuSelesai"
                                    placeholder="dd/mm/yyyy HH:ii:ss" class="flex-1" x-ref="alatWaktuLepas"
                                    x-on:keydown.enter.prevent="$el.blur(); $wire.addAlatInvasif()" />
                                <x-now-button wire:click.prevent="setWaktuSelesaiAlat" />
                            </div>
                            <x-input-error :messages="$errors->get('formEntryAlat.tanggalWaktuSelesai')" class="mt-1" />
                        </div>

                        <div class="col-span-12">
                            <x-input-label value="Keterangan" class="mb-1" />
                            <x-text-input wire:model="formEntryAlat.keterangan" class="w-full"
                                placeholder="mis. dipasang ulang karena tersumbat" />
                            <x-input-error :messages="$errors->get('formEntryAlat.keterangan')" class="mt-1" />
                        </div>

                    </div>
                </div>
            @endif

            {{-- TABEL DATA --}}
            @php
                $daftarAlat = $dataDaftarRi['observasi']['alatInvasif']['alatInvasifData'] ?? [];
                $alatTersusun = collect($daftarAlat)
                    ->sortByDesc(fn($baris) => Carbon::createFromFormat('d/m/Y H:i:s', $baris['tanggalWaktuMulai'] ?? '01/01/2000 00:00:00')->timestamp)
                    ->values();

                // Lama pemakaian dalam hari kalender inklusif — sama seperti penyebut di laporan
                // (hari pasang dihitung hari ke-1); tanggal lepas kosong = masih terpasang.
                $lamaHari = function (array $baris): string {
                    $teksPasang = $baris['tanggalWaktuMulai'] ?? '';
                    if (!$teksPasang) {
                        return '-';
                    }
                    try {
                        $zona = config('app.timezone');
                        $pasang = Carbon::createFromFormat('d/m/Y H:i:s', $teksPasang, $zona)->startOfDay();
                        $teksLepas = $baris['tanggalWaktuSelesai'] ?? '';
                        $lepas = $teksLepas
                            ? Carbon::createFromFormat('d/m/Y H:i:s', $teksLepas, $zona)->startOfDay()
                            : Carbon::now($zona)->startOfDay();
                        if ($lepas->lessThan($pasang)) {
                            return '-';
                        }
                        $hari = (int) round($pasang->diffInDays($lepas)) + 1;
                        return $hari . ' hari' . ($teksLepas ? '' : ' (berjalan)');
                    } catch (\Throwable $e) {
                        return '-';
                    }
                };
            @endphp

            <div class="overflow-hidden bg-canvas border border-hairline rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                <div class="flex items-center justify-between px-4 py-3 border-b border-hairline dark:border-gray-700">
                    <h3 class="text-sm font-semibold text-body dark:text-gray-300">Riwayat Alat Invasif &amp; Tirah Baring</h3>
                    <x-badge variant="gray">{{ count($daftarAlat) }} item</x-badge>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left">
                        <thead class="text-xs font-semibold text-muted uppercase bg-surface-soft dark:bg-gray-800/50 dark:text-gray-400">
                            <tr>
                                <th class="px-4 py-3">No</th>
                                <th class="px-4 py-3">Jenis</th>
                                <th class="px-4 py-3">Lokasi</th>
                                <th class="px-4 py-3">Waktu Pasang</th>
                                <th class="px-4 py-3">Waktu Lepas</th>
                                <th class="px-4 py-3">Lama</th>
                                <th class="px-4 py-3">Keterangan</th>
                                <th class="px-4 py-3">Pemeriksa</th>
                                @if (!$isFormLocked)
                                    <th class="px-4 py-3 text-center w-20">Hapus</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-hairline-soft dark:divide-gray-800">
                            @forelse ($alatTersusun as $baris)
                                @php
                                    $jenisBaris = $baris['jenisAlat'] ?? '';
                                    $waktuPasang = $baris['tanggalWaktuMulai'] ?? '';
                                    $waktuLepas = $baris['tanggalWaktuSelesai'] ?? '';
                                @endphp
                                <tr wire:key="alat-{{ $jenisBaris }}-{{ $waktuPasang }}"
                                    class="transition hover:bg-surface-soft dark:hover:bg-gray-800/40"
                                    x-data="{ editing: false, val: '{{ $waktuLepas }}' }">
                                    <td class="px-4 py-3 text-muted dark:text-gray-400">{{ $loop->iteration }}</td>
                                    <td class="px-4 py-3 font-medium text-ink dark:text-gray-100">
                                        {{ $opsiPenyebutHais[$jenisBaris] ?? $jenisBaris ?: '-' }}
                                    </td>
                                    <td class="px-4 py-3">{{ $baris['lokasi'] ?: '-' }}</td>
                                    <td class="px-4 py-3 font-mono whitespace-nowrap">{{ $waktuPasang ?: '-' }}</td>
                                    <td class="px-4 py-3 font-mono whitespace-nowrap">
                                        @if (!$isFormLocked && empty($waktuLepas))
                                            <div class="flex items-center gap-1">
                                                <input type="text" x-model="val" placeholder="dd/mm/yyyy HH:ii:ss"
                                                    class="w-44 px-2 py-1 font-mono text-xs border rounded border-hairline dark:border-gray-700 dark:bg-gray-800" />
                                                <button type="button"
                                                    x-on:click="
                                                        const d = new Date();
                                                        const pad = n => String(n).padStart(2, '0');
                                                        val = `${pad(d.getDate())}/${pad(d.getMonth()+1)}/${d.getFullYear()} ${pad(d.getHours())}:${pad(d.getMinutes())}:${pad(d.getSeconds())}`;
                                                    "
                                                    class="px-2 py-1 text-[10px] rounded bg-gray-200 hover:bg-gray-300 dark:bg-gray-700 dark:hover:bg-gray-600"
                                                    title="Isi waktu sekarang">Now</button>
                                                <button type="button"
                                                    x-on:click="$wire.updateWaktuLepas('{{ $jenisBaris }}', '{{ $waktuPasang }}', val)"
                                                    class="px-2 py-1 text-[10px] rounded bg-emerald-600 hover:bg-emerald-700 text-white"
                                                    title="Simpan waktu lepas">Set</button>
                                            </div>
                                        @elseif (!$isFormLocked && !empty($waktuLepas))
                                            <div class="flex items-center gap-1">
                                                <span x-show="!editing">{{ $waktuLepas }}</span>
                                                <input x-show="editing" type="text" x-model="val"
                                                    placeholder="dd/mm/yyyy HH:ii:ss"
                                                    class="w-44 px-2 py-1 font-mono text-xs border rounded border-hairline dark:border-gray-700 dark:bg-gray-800" />
                                                <button type="button" x-show="!editing" x-on:click="editing = true"
                                                    class="px-2 py-1 text-[10px] rounded bg-gray-200 hover:bg-gray-300 dark:bg-gray-700 dark:hover:bg-gray-600"
                                                    title="Ubah waktu lepas">Edit</button>
                                                <button type="button" x-show="editing"
                                                    x-on:click="$wire.updateWaktuLepas('{{ $jenisBaris }}', '{{ $waktuPasang }}', val); editing = false"
                                                    class="px-2 py-1 text-[10px] rounded bg-emerald-600 hover:bg-emerald-700 text-white">Set</button>
                                                <button type="button" x-show="editing"
                                                    x-on:click="editing = false; val = '{{ $waktuLepas }}'"
                                                    class="px-2 py-1 text-[10px] rounded bg-gray-200 hover:bg-gray-300 dark:bg-gray-700 dark:hover:bg-gray-600">Batal</button>
                                            </div>
                                        @else
                                            {{ $waktuLepas ?: '-' }}
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap">{{ $lamaHari($baris) }}</td>
                                    <td class="px-4 py-3">{{ $baris['keterangan'] ?: '-' }}</td>
                                    <td class="px-4 py-3">{{ $baris['pemeriksa'] ?: '-' }}</td>
                                    @if (!$isFormLocked)
                                        <td class="px-4 py-3 text-center">
                                            <x-outline-button type="button"
                                                wire:click.prevent="removeAlatInvasif('{{ $jenisBaris }}', '{{ $waktuPasang }}')"
                                                wire:confirm="Hapus data ini?" wire:loading.attr="disabled"
                                                class="!text-red-600 !bg-red-50 !border-red-200 hover:!bg-red-100 hover:!text-red-700 hover:!border-red-300 dark:!text-red-400 dark:!bg-red-900/20 dark:!border-red-800/30 dark:hover:!bg-red-900/30 dark:hover:!text-red-300"
                                                title="Hapus">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                </svg>
                                            </x-outline-button>
                                        </td>
                                    @endif
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ $isFormLocked ? 8 : 9 }}"
                                        class="px-4 py-10 text-sm text-center text-muted-soft dark:text-gray-600">
                                        <svg class="w-8 h-8 mx-auto mb-2 opacity-40" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                                d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                        </svg>
                                        Belum ada data alat invasif / tirah baring
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
</div>
