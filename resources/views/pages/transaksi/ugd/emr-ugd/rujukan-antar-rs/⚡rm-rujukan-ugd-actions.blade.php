<?php

use Livewire\Component;
use Livewire\Attributes\On;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Ugd\EmrUGDTrait;
use App\Http\Traits\Concerns\WithRenderVersioningTrait;
use App\Http\Traits\BPJS\VclaimTrait;

new class extends Component {
    use EmrUGDTrait, WithRenderVersioningTrait;

    public bool $isFormLocked = false;
    public ?int $rjNo = null;

    // dataDaftarUGD — key 'rujukanAntarRS' di-bind ke form
    public array $dataDaftarUGD = [];

    // renderVersions
    public array $renderVersions = [];
    protected array $renderAreas = ['modal-rujukan-rs'];

    // List spesialistik & faskes dari BPJS
    public array $listSpesialistik = [];
    public array $listFaskes = [];
    public string $searchFaskes = '';
    public bool $showFaskesLov = false;

    /* ===============================
     | OPEN
     =============================== */
    #[On('open-rm-rujukan-ugd')]
    public function openRujukan($rjNo): void
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
        $this->dataDaftarUGD['rujukanAntarRS'] ??= $this->getDefaultRujukanAntarRS();

        $this->incrementVersion('modal-rujukan-rs');

        if ($this->checkEmrUGDStatus($rjNo)) {
            $this->isFormLocked = true;
        }
    }

    /* ===============================
     | CLOSE MODAL
     =============================== */
    /**
     * Panel ini dulu tampil inline memenuhi tab; kini kartu ringkas + modal,
     * menyamai panel Rujukan Berbasis Kompetensi di tab yang sama.
     */
    public function openModal(): void
    {
        if (empty($this->rjNo)) {
            return;
        }

        // Baca ulang saat dibuka: SEP bisa terbit setelah panel pertama dirender.
        $this->openRujukan($this->rjNo);

        $this->dispatch('open-modal', name: 'rujukan-antar-rs-ugd-' . $this->rjNo);
    }

    public function closeModal(): void
    {
        // resetForm() sengaja TIDAK dipanggil: menutup modal bukan membatalkan
        // isian — petugas harus bisa menutup lalu melanjutkan nanti.
        $this->resetValidation();
        $this->dispatch('close-modal', name: 'rujukan-antar-rs-ugd-' . $this->rjNo);
    }

    /* ===============================
     | DEFAULT RUJUKAN ANTAR RS STRUCTURE
     =============================== */
    private function getDefaultRujukanAntarRS(): array
    {
        $noSep = $this->dataDaftarUGD['sep']['noSep'] ?? (DB::table('rsview_rjkasir')->where('rj_no', $this->rjNo)->value('vno_sep') ?? '');

        return [
            'noSep' => $noSep,
            'tglRujukan' => Carbon::now()->format('d/m/Y'),
            'tglRencanaKunjungan' => Carbon::now()->addDays(1)->format('d/m/Y'),
            'ppkDirujuk' => '',
            'ppkDirujukNama' => '',
            'jnsPelayanan' => '2',
            'catatan' => '',
            'diagRujukan' => $this->dataDaftarUGD['sep']['reqSep']['request']['t_sep']['diagAwal'] ?? '',
            'diagRujukanNama' => '',
            'tipeRujukan' => '0', // 0=Penuh, 1=Partial, 2=Balik PRB
            'poliRujukan' => '',
            'poliRujukanNama' => '',
            'noRujukan' => '', // hasil dari BPJS setelah insert
        ];
    }

    /* ===============================
     | CARI FASKES dari BPJS
     =============================== */
    public function cariFaskes(): void
    {
        if (strlen($this->searchFaskes) < 3) {
            $this->dispatch('toast', type: 'warning', message: 'Keyword minimal 3 karakter.');
            return;
        }

        try {
            $response = VclaimTrait::ref_faskes($this->searchFaskes, '2')->getOriginalContent();
            $code = $response['metadata']['code'] ?? 500;

            if ($code == 200) {
                $this->listFaskes = $response['response']['faskes'] ?? [];
                $this->showFaskesLov = true;

                if (empty($this->listFaskes)) {
                    $this->dispatch('toast', type: 'warning', message: 'Faskes tidak ditemukan.');
                }
            } else {
                $this->listFaskes = [];
                $this->dispatch('toast', type: 'warning', message: 'Cari faskes: ' . ($response['metadata']['message'] ?? '-'));
            }
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Error cari faskes: ' . $e->getMessage());
        }

        $this->incrementVersion('modal-rujukan-rs');
    }

    public function pilihFaskes(int $index): void
    {
        $faskes = $this->listFaskes[$index] ?? null;
        if (!$faskes) {
            return;
        }

        $this->dataDaftarUGD['rujukanAntarRS']['ppkDirujuk'] = $faskes['kode'] ?? '';
        $this->dataDaftarUGD['rujukanAntarRS']['ppkDirujukNama'] = $faskes['nama'] ?? '';
        $this->showFaskesLov = false;
        $this->listFaskes = [];

        // Auto-load list spesialistik setelah pilih faskes
        $this->fetchListSpesialistik();

        $this->incrementVersion('modal-rujukan-rs');
        $this->dispatch('toast', type: 'success', message: 'Faskes dipilih: ' . ($faskes['nama'] ?? ''));
    }

    /* ===============================
     | FETCH LIST SPESIALISTIK
     =============================== */
    public function fetchListSpesialistik(): void
    {
        $rujukan = $this->dataDaftarUGD['rujukanAntarRS'] ?? [];
        $ppk = $rujukan['ppkDirujuk'] ?? '';
        if (empty($ppk)) {
            $this->dispatch('toast', type: 'warning', message: 'Pilih PPK tujuan terlebih dahulu.');
            return;
        }

        $tglRaw = $rujukan['tglRencanaKunjungan'] ?? '';
        if (empty($tglRaw)) {
            $this->dispatch('toast', type: 'warning', message: 'Tanggal rencana kunjungan harus diisi.');
            return;
        }

        $tgl = Carbon::createFromFormat('d/m/Y', $tglRaw)->format('Y-m-d');

        try {
            $response = VclaimTrait::rujukan_list_spesialistik($ppk, $tgl)->getOriginalContent();
            $code = $response['metadata']['code'] ?? 500;

            if ($code == 200) {
                $this->listSpesialistik = $response['response']['list'] ?? [];
                $this->dispatch('toast', type: 'success', message: 'List spesialistik berhasil dimuat.');
            } else {
                $this->listSpesialistik = [];
                $this->dispatch('toast', type: 'warning', message: 'List spesialistik: ' . ($response['metadata']['message'] ?? '-'));
            }
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Error list spesialistik: ' . $e->getMessage());
        }

        $this->incrementVersion('modal-rujukan-rs');
    }

    /* ===============================
     | VALIDATION
     =============================== */
    protected function rules(): array
    {
        $rules = [
            'dataDaftarUGD.rujukanAntarRS.tglRujukan' => 'required|date_format:d/m/Y',
            'dataDaftarUGD.rujukanAntarRS.tglRencanaKunjungan' => 'required|date_format:d/m/Y',
            'dataDaftarUGD.rujukanAntarRS.ppkDirujuk' => 'required',
            'dataDaftarUGD.rujukanAntarRS.jnsPelayanan' => 'required|in:1,2',
            'dataDaftarUGD.rujukanAntarRS.diagRujukan' => 'required',
            'dataDaftarUGD.rujukanAntarRS.tipeRujukan' => 'required|in:0,1,2',
        ];

        $tipe = $this->dataDaftarUGD['rujukanAntarRS']['tipeRujukan'] ?? '0';
        if (in_array($tipe, ['0', '1'])) {
            $rules['dataDaftarUGD.rujukanAntarRS.poliRujukan'] = 'required';
        }

        return $rules;
    }

    protected function messages(): array
    {
        return [
            'dataDaftarUGD.rujukanAntarRS.tglRujukan.required' => 'Tanggal rujukan harus diisi.',
            'dataDaftarUGD.rujukanAntarRS.tglRujukan.date_format' => 'Format tanggal rujukan harus dd/mm/yyyy.',
            'dataDaftarUGD.rujukanAntarRS.tglRencanaKunjungan.required' => 'Tanggal rencana kunjungan harus diisi.',
            'dataDaftarUGD.rujukanAntarRS.tglRencanaKunjungan.date_format' => 'Format tanggal rencana kunjungan harus dd/mm/yyyy.',
            'dataDaftarUGD.rujukanAntarRS.ppkDirujuk.required' => 'PPK tujuan rujukan harus diisi.',
            'dataDaftarUGD.rujukanAntarRS.jnsPelayanan.required' => 'Jenis pelayanan harus dipilih.',
            'dataDaftarUGD.rujukanAntarRS.diagRujukan.required' => 'Diagnosis rujukan harus diisi.',
            'dataDaftarUGD.rujukanAntarRS.tipeRujukan.required' => 'Tipe rujukan harus dipilih.',
            'dataDaftarUGD.rujukanAntarRS.poliRujukan.required' => 'Poli rujukan wajib diisi untuk tipe Penuh/Partial.',
        ];
    }

    /* ===============================
     | SAVE — dipanggil dari event parent (save EMR)
     |
     | Alur:
     | 1. Validasi form
     | 2. Simpan rujukanAntarRS ke DB
     | 3. TIDAK push ke BPJS — user klik tombol terpisah
     =============================== */
    public function save(): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form dalam mode read-only.');
            return;
        }

        $this->validate();

        try {
            DB::transaction(function () {
                // 1. Lock row dulu
                $this->lockUGDRow($this->rjNo);

                // 2. Baca data terkini setelah lock
                $data = $this->findDataUGD($this->rjNo) ?? [];

                if (empty($data)) {
                    throw new \RuntimeException('Data UGD tidak ditemukan, simpan dibatalkan.');
                }

                // Tangkap status sebelum overwrite (untuk verb log Buat/Update)
                $isBaru = empty($data['rujukanAntarRS']);

                // 3. Patch hanya key rujukanAntarRS
                $data['rujukanAntarRS'] = $this->dataDaftarUGD['rujukanAntarRS'] ?? [];

                $this->updateJsonUGD($this->rjNo, $data);
                $this->dataDaftarUGD = $data;

                // 4. Audit log (rekam medis)
                $this->appendAdminLogUGD((int) $this->rjNo, ($isBaru ? 'Buat' : 'Update') . ' Rujukan Antar RS UGD: tujuan ' . ($data['rujukanAntarRS']['ppkDirujukNama'] ?: ($data['rujukanAntarRS']['ppkDirujuk'] ?: '-')) . ' (tgl rujukan ' . ($data['rujukanAntarRS']['tglRujukan'] ?: '-') . ')', 'MR');
            });

            // 4. Notify
            $this->afterSave('Data Rujukan Antar RS berhasil disimpan.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menyimpan: ' . $e->getMessage());
        }
    }

    /* ===============================
     | KIRIM KE BPJS — tombol terpisah
     =============================== */
    public function kirimBPJS(): void
    {
        $rujukan = $this->dataDaftarUGD['rujukanAntarRS'] ?? [];

        if (empty($rujukan['noSep'])) {
            $this->dispatch('toast', type: 'error', message: 'No. SEP belum terisi. Buat SEP terlebih dahulu.');
            return;
        }

        $this->validate();

        $isUpdate = !empty($rujukan['noRujukan']);

        $payload = [
            'noSep' => $rujukan['noSep'],
            'tglRujukan' => Carbon::createFromFormat('d/m/Y', $rujukan['tglRujukan'])->format('Y-m-d'),
            'tglRencanaKunjungan' => Carbon::createFromFormat('d/m/Y', $rujukan['tglRencanaKunjungan'])->format('Y-m-d'),
            'ppkDirujuk' => $rujukan['ppkDirujuk'],
            'jnsPelayanan' => $rujukan['jnsPelayanan'],
            'catatan' => $rujukan['catatan'] ?: '-',
            'diagRujukan' => $rujukan['diagRujukan'],
            'tipeRujukan' => $rujukan['tipeRujukan'],
            'poliRujukan' => $rujukan['poliRujukan'] ?? '',
            'user' => 'Sirus',
        ];

        if ($isUpdate) {
            $payload['noRujukan'] = $rujukan['noRujukan'];
        }

        try {
            $response = $isUpdate ? VclaimTrait::rujukan_update($payload)->getOriginalContent() : VclaimTrait::rujukan_insert($payload)->getOriginalContent();

            $code = $response['metadata']['code'] ?? 500;
            $msg = $response['metadata']['message'] ?? '-';
            $label = $isUpdate ? 'Update' : 'Insert';

            if ($code == 200) {
                if (!$isUpdate) {
                    $this->dataDaftarUGD['rujukanAntarRS']['noRujukan'] = $response['response']['rujukan']['noRujukan'] ?? '';
                }

                // Persist noRujukan ke JSON DB
                DB::transaction(function () use ($label) {
                    $this->lockUGDRow($this->rjNo);
                    $data = $this->findDataUGD($this->rjNo) ?? [];
                    $data['rujukanAntarRS'] = $this->dataDaftarUGD['rujukanAntarRS'];
                    $this->updateJsonUGD($this->rjNo, $data);
                    $this->dataDaftarUGD = $data;

                    $this->appendAdminLogUGD((int) $this->rjNo, $label . ' Rujukan Antar RS UGD ke BPJS: noRujukan ' . ($data['rujukanAntarRS']['noRujukan'] ?: '-') . ' (tujuan ' . ($data['rujukanAntarRS']['ppkDirujukNama'] ?: ($data['rujukanAntarRS']['ppkDirujuk'] ?: '-')) . ')', 'MR');
                });

                $this->afterSave("{$label} Rujukan berhasil ({$code}): {$msg}");
            } else {
                $this->dispatch('toast', type: 'error', message: "{$label} Rujukan gagal ({$code}): {$msg}");
            }
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Error rujukan: ' . $e->getMessage());
        }
    }

    /* ===============================
     | HAPUS RUJUKAN dari BPJS
     =============================== */
    public function hapusRujukan(): void
    {
        $noRujukan = $this->dataDaftarUGD['rujukanAntarRS']['noRujukan'] ?? '';
        if (empty($noRujukan)) {
            $this->dispatch('toast', type: 'error', message: 'Tidak ada rujukan untuk dihapus.');
            return;
        }

        try {
            $response = VclaimTrait::rujukan_delete([
                'noRujukan' => $noRujukan,
                'user' => 'Sirus',
            ])->getOriginalContent();

            $code = $response['metadata']['code'] ?? 500;
            $msg = $response['metadata']['message'] ?? '-';

            if ($code == 200) {
                $this->dataDaftarUGD['rujukanAntarRS']['noRujukan'] = '';

                DB::transaction(function () use ($noRujukan) {
                    $this->lockUGDRow($this->rjNo);
                    $data = $this->findDataUGD($this->rjNo) ?? [];
                    $data['rujukanAntarRS'] = $this->dataDaftarUGD['rujukanAntarRS'];
                    $this->updateJsonUGD($this->rjNo, $data);
                    $this->dataDaftarUGD = $data;

                    $this->appendAdminLogUGD((int) $this->rjNo, 'Hapus Rujukan Antar RS UGD dari BPJS: noRujukan ' . $noRujukan, 'MR');
                });

                $this->afterSave("Rujukan berhasil dihapus ({$code}): {$msg}");
            } else {
                $this->dispatch('toast', type: 'error', message: "Hapus rujukan gagal ({$code}): {$msg}");
            }
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Error hapus rujukan: ' . $e->getMessage());
        }
    }

    private function afterSave(string $message): void
    {
        $this->incrementVersion('modal-rujukan-rs');
        $this->dispatch('toast', type: 'success', message: $message);
    }

    protected function resetForm(): void
    {
        $this->reset(['listSpesialistik', 'listFaskes', 'searchFaskes', 'showFaskesLov']);
        $this->resetVersion();
        $this->isFormLocked = false;
    }

    /* ===============================
     | LIFECYCLE
     =============================== */
    public function mount(): void
    {
        $this->registerAreas(['modal-rujukan-rs']);
        $this->openRujukan($this->rjNo);
    }

    public function rendering(): void
    {
        $default = $this->getDefaultRujukanAntarRS();
        $current = $this->dataDaftarUGD['rujukanAntarRS'] ?? [];
        $this->dataDaftarUGD['rujukanAntarRS'] = array_replace_recursive($default, $current);
    }
};
?>

<div>
    {{-- ══ KARTU RINGKAS (inline di tab Tindak Lanjut) ══ --}}
    @php
        $rujukanAntarRS = $dataDaftarUGD['rujukanAntarRS'] ?? [];
        $sudahKirimBpjs = !empty($rujukanAntarRS['noRujukan']);
    @endphp

    <div class="p-5 bg-canvas border border-hairline shadow-sm rounded-2xl dark:bg-gray-900 dark:border-gray-700">
        <div class="flex flex-col gap-3">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="flex flex-wrap items-center gap-2 min-w-0">
                    <svg class="w-5 h-5 text-indigo-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 15L12 18.75 15.75 15m-7.5-6L12 5.25 15.75 9" />
                    </svg>
                    <h3 class="text-base font-semibold text-ink dark:text-gray-200">
                        Rujukan Antar RS (BPJS VClaim)
                    </h3>
                    @if ($sudahKirimBpjs)
                        <x-badge variant="success">Terkirim</x-badge>
                    @else
                        <x-badge variant="warning">Belum dikirim ke BPJS</x-badge>
                    @endif
                </div>

                <div class="flex shrink-0">
                    <x-primary-button type="button" wire:click="openModal" wire:loading.attr="disabled"
                        wire:target="openModal" :disabled="!$rjNo" class="gap-2">
                        <span wire:loading.remove wire:target="openModal" class="flex items-center gap-1.5">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                            </svg>
                            {{ $sudahKirimBpjs ? 'Lihat Rujukan' : 'Buat Rujukan' }}
                        </span>
                        <span wire:loading wire:target="openModal" class="flex items-center gap-1.5">
                            <x-loading class="w-4 h-4" /> Memuat...
                        </span>
                    </x-primary-button>
                </div>
            </div>

            <p class="text-base text-muted dark:text-gray-400">
                Rujukan biasa ke RS lain lewat BPJS (VClaim), memakai SEP kunjungan ini.
                Berbeda dari Rujukan Berbasis Kompetensi &mdash; di sini tujuannya ditentukan sendiri,
                bukan dari rekomendasi SATUSEHAT.
            </p>

            @if ($sudahKirimBpjs)
                <div class="flex flex-wrap gap-x-6 gap-y-1 text-sm text-muted dark:text-gray-400">
                    <span>No. Rujukan BPJS: <strong class="text-ink dark:text-gray-200">{{ $rujukanAntarRS['noRujukan'] }}</strong></span>
                    <span>Tujuan: <strong class="text-ink dark:text-gray-200">{{ ($rujukanAntarRS['ppkDirujukNama'] ?? '') ?: (($rujukanAntarRS['ppkDirujuk'] ?? '') ?: '-') }}</strong></span>
                    <span>Tgl. Rujukan: <strong class="text-ink dark:text-gray-200">{{ ($rujukanAntarRS['tglRujukan'] ?? '') ?: '-' }}</strong></span>
                </div>
            @endif
        </div>
    </div>

    {{-- ══ MODAL FORMULIR ══ --}}
    <x-modal name="rujukan-antar-rs-ugd-{{ $rjNo }}" size="full" height="full" focusable>
        <div class="flex flex-col min-h-[calc(100vh-8rem)]" wire:key="{{ $this->renderKey('modal-rujukan-rs', [$rjNo ?? 'new']) }}">

            {{-- HEADER --}}
            <div class="px-6 py-5 border-b border-hairline dark:border-gray-700">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <div class="flex items-center justify-center w-10 h-10 rounded-xl bg-indigo-500/10">
                            <svg class="w-6 h-6 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 15L12 18.75 15.75 15m-7.5-6L12 5.25 15.75 9" />
                            </svg>
                        </div>
                        <div>
                            <h2 class="text-2xl font-semibold text-ink dark:text-gray-100">Rujukan Antar RS</h2>
                            <p class="mt-0.5 text-base text-muted dark:text-gray-400">
                                UGD &rarr; RS lain &middot; lewat BPJS (VClaim), memakai SEP kunjungan ini
                            </p>
                        </div>
                    </div>
                    @if ($sudahKirimBpjs)
                        <x-badge variant="success">BPJS: {{ $rujukanAntarRS['noRujukan'] }}</x-badge>
                    @else
                        <x-badge variant="warning">Belum dikirim ke BPJS</x-badge>
                    @endif
                </div>
            </div>

            {{-- BODY --}}
            <div class="flex-1 px-4 py-4 overflow-y-auto bg-surface-soft dark:bg-gray-950/20">
                <div class="max-w-full mx-auto space-y-4">
                    <div class="w-full">
                    <div class="grid grid-cols-1 gap-2">

                        {{-- KOLOM KIRI --}}
                        <div class="space-y-4">

                            {{-- No SEP (readonly) --}}
                            <div>
                                <x-input-label value="No. SEP" class="mb-1" />
                                <x-text-input wire:model="dataDaftarUGD.rujukanAntarRS.noSep" :disabled="true"
                                    class="w-full" />
                                @if (empty($dataDaftarUGD['rujukanAntarRS']['noSep']))
                                    <p class="mt-1 text-sm text-amber-500">SEP belum terbit.</p>
                                @endif
                            </div>

                            {{-- No Rujukan BPJS (readonly) --}}
                            <div>
                                <x-input-label value="No. Rujukan BPJS" class="mb-1" />
                                <x-text-input wire:model="dataDaftarUGD.rujukanAntarRS.noRujukan"
                                    placeholder="Terisi setelah kirim ke BPJS" :disabled="true" class="w-full" />
                            </div>

                            {{-- Tanggal Rujukan --}}
                            <div>
                                <x-input-label value="Tanggal Rujukan *" class="mb-1" />
                                <x-text-input wire:model.live="dataDaftarUGD.rujukanAntarRS.tglRujukan"
                                    placeholder="dd/mm/yyyy" :disabled="$isFormLocked" :error="$errors->has('dataDaftarUGD.rujukanAntarRS.tglRujukan')" class="w-full" />
                                <x-input-error :messages="$errors->get('dataDaftarUGD.rujukanAntarRS.tglRujukan')" class="mt-1" />
                            </div>

                            {{-- Tanggal Rencana Kunjungan --}}
                            <div>
                                <x-input-label value="Tanggal Rencana Kunjungan *" class="mb-1" />
                                <x-text-input wire:model.live="dataDaftarUGD.rujukanAntarRS.tglRencanaKunjungan"
                                    placeholder="dd/mm/yyyy" :disabled="$isFormLocked" :error="$errors->has('dataDaftarUGD.rujukanAntarRS.tglRencanaKunjungan')" class="w-full" />
                                <x-input-error :messages="$errors->get('dataDaftarUGD.rujukanAntarRS.tglRencanaKunjungan')" class="mt-1" />
                            </div>

                        </div>

                        {{-- KOLOM KANAN --}}
                        <div class="space-y-4">

                            {{-- PPK Tujuan --}}
                            <div>
                                <x-input-label value="PPK Tujuan Rujukan *" class="mb-1" />
                                <div class="flex gap-2">
                                    <x-text-input wire:model.live="dataDaftarUGD.rujukanAntarRS.ppkDirujuk"
                                        class="w-40" :disabled="true" placeholder="Kode PPK"
                                        :error="$errors->has('dataDaftarUGD.rujukanAntarRS.ppkDirujuk')" />
                                    <x-text-input wire:model="dataDaftarUGD.rujukanAntarRS.ppkDirujukNama"
                                        class="flex-1" :disabled="true" placeholder="Pilih faskes via tombol Cari" />
                                </div>
                                <x-input-error :messages="$errors->get('dataDaftarUGD.rujukanAntarRS.ppkDirujuk')" class="mt-1" />

                                {{-- Cari Faskes BPJS --}}
                                @if (!$isFormLocked)
                                    <div class="flex gap-2 mt-2">
                                        <x-text-input wire:model="searchFaskes" class="flex-1"
                                            placeholder="Ketik nama RS tujuan (min 3 huruf)..."
                                            x-on:keyup.enter="$wire.cariFaskes()" />
                                        <x-secondary-button type="button" wire:click="cariFaskes"
                                            wire:loading.attr="disabled" class="shrink-0">
                                            <span wire:loading.remove wire:target="cariFaskes">Cari Faskes</span>
                                            <span wire:loading wire:target="cariFaskes"><x-loading /></span>
                                        </x-secondary-button>
                                    </div>

                                    {{-- List Faskes --}}
                                    @if ($showFaskesLov && !empty($listFaskes))
                                        <div class="mt-2 overflow-y-auto border border-hairline rounded-lg max-h-48 dark:border-gray-700">
                                            <table class="w-full text-sm">
                                                <thead class="sticky top-0 bg-surface-soft dark:bg-gray-800">
                                                    <tr>
                                                        <th class="px-2 py-1 text-left">Kode</th>
                                                        <th class="px-2 py-1 text-left">Nama Faskes</th>
                                                        <th class="px-2 py-1"></th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach ($listFaskes as $idx => $faskes)
                                                        <tr class="border-t border-hairline-soft cursor-pointer hover:bg-blue-50 dark:hover:bg-blue-900/20 dark:border-gray-700"
                                                            wire:click="pilihFaskes({{ $idx }})">
                                                            <td class="px-2 py-1 font-mono">{{ $faskes['kode'] ?? '' }}</td>
                                                            <td class="px-2 py-1">{{ $faskes['nama'] ?? '' }}</td>
                                                            <td class="px-2 py-1 text-blue-500">Pilih</td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    @endif
                                @endif
                            </div>

                            {{-- Jenis Pelayanan --}}
                            <div>
                                <x-input-label value="Jenis Pelayanan *" class="mb-1" />
                                <x-select-input wire:model="dataDaftarUGD.rujukanAntarRS.jnsPelayanan" class="w-full"
                                    :disabled="$isFormLocked">
                                    <option value="1">1 - Rawat Inap</option>
                                    <option value="2">2 - Rawat Jalan</option>
                                </x-select-input>
                            </div>

                            {{-- Tipe Rujukan --}}
                            <div>
                                <x-input-label value="Tipe Rujukan *" class="mb-1" />
                                <x-select-input wire:model.live="dataDaftarUGD.rujukanAntarRS.tipeRujukan"
                                    class="w-full" :disabled="$isFormLocked">
                                    <option value="0">0 - Penuh</option>
                                    <option value="1">1 - Partial</option>
                                    <option value="2">2 - Balik PRB</option>
                                </x-select-input>
                            </div>

                            {{-- Diagnosis Rujukan --}}
                            <div>
                                <x-input-label value="Diagnosis Rujukan *" class="mb-1" />
                                <div class="flex gap-2">
                                    <x-text-input wire:model.live="dataDaftarUGD.rujukanAntarRS.diagRujukan"
                                        class="w-32" :disabled="$isFormLocked" placeholder="Kode ICD" :error="$errors->has('dataDaftarUGD.rujukanAntarRS.diagRujukan')" />
                                    <x-text-input wire:model="dataDaftarUGD.rujukanAntarRS.diagRujukanNama"
                                        class="flex-1" :disabled="true" placeholder="Nama diagnosa" />
                                </div>
                                <x-input-error :messages="$errors->get('dataDaftarUGD.rujukanAntarRS.diagRujukan')" class="mt-1" />
                            </div>

                            {{-- Poli Rujukan (wajib tipe 0/1, kosong tipe 2) --}}
                            @if (in_array($dataDaftarUGD['rujukanAntarRS']['tipeRujukan'] ?? '0', ['0', '1']))
                                <div>
                                    <x-input-label value="Poli Rujukan *" class="mb-1" />
                                    @if (!empty($listSpesialistik))
                                        <x-select-input wire:model="dataDaftarUGD.rujukanAntarRS.poliRujukan"
                                            class="w-full" :disabled="$isFormLocked">
                                            <option value="">-- Pilih Poli --</option>
                                            @foreach ($listSpesialistik as $poli)
                                                <option value="{{ $poli['kode'] ?? '' }}">
                                                    {{ ($poli['kode'] ?? '') . ' - ' . ($poli['nama'] ?? '') }}
                                                </option>
                                            @endforeach
                                        </x-select-input>
                                    @else
                                        <div class="flex gap-2">
                                            <x-text-input wire:model.live="dataDaftarUGD.rujukanAntarRS.poliRujukan"
                                                class="w-32" :disabled="$isFormLocked" placeholder="Kode poli"
                                                :error="$errors->has('dataDaftarUGD.rujukanAntarRS.poliRujukan')" />
                                            <x-text-input wire:model="dataDaftarUGD.rujukanAntarRS.poliRujukanNama"
                                                class="flex-1" :disabled="true" placeholder="Nama poli" />
                                        </div>
                                    @endif
                                    @if (!$isFormLocked && !empty($dataDaftarUGD['rujukanAntarRS']['ppkDirujuk']))
                                        <x-secondary-button type="button" wire:click="fetchListSpesialistik"
                                            wire:loading.attr="disabled" class="mt-1 text-sm">
                                            <span wire:loading.remove wire:target="fetchListSpesialistik">Muat Poli
                                                dari BPJS</span>
                                            <span wire:loading wire:target="fetchListSpesialistik"><x-loading /></span>
                                        </x-secondary-button>
                                    @endif
                                    <x-input-error :messages="$errors->get('dataDaftarUGD.rujukanAntarRS.poliRujukan')" class="mt-1" />
                                </div>
                            @endif

                            {{-- Catatan --}}
                            <div>
                                <x-input-label value="Catatan" class="mb-1" />
                                <x-text-input wire:model.live="dataDaftarUGD.rujukanAntarRS.catatan" class="w-full"
                                    :disabled="$isFormLocked" placeholder="Catatan rujukan" />
                            </div>

                        </div>
                    </div>
                </div>
                </div>
            </div>

            {{-- FOOTER --}}
            <div class="sticky bottom-0 z-10 px-6 py-4 bg-canvas border-t border-hairline dark:bg-gray-900 dark:border-gray-700">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <p class="text-sm text-muted dark:text-gray-400">
                        Simpan menyimpan isian ke kunjungan ini; Kirim ke BPJS menerbitkan nomor rujukannya.
                    </p>

                    <div class="flex flex-wrap items-center gap-2">
                {{-- Aksi: dipindah dari badan ke footer yang selalu menempel. --}}
                        @if (!$isFormLocked)
                            @php
                                $klaimStatus = $dataDaftarUGD['klaimStatus'] ?? '';
                                $klaimId = $dataDaftarUGD['klaimId'] ?? '';
                                $isBPJS = $klaimStatus === 'BPJS' || $klaimId === 'JM';
                            @endphp

                            @if ($isBPJS)
                                <div class="flex items-center justify-end gap-2 pt-2">
                                    @if (!empty($dataDaftarUGD['rujukanAntarRS']['noRujukan']))
                                        <x-danger-button type="button" wire:click="hapusRujukan" wire:loading.attr="disabled"
                                            wire:confirm="Yakin hapus rujukan {{ $dataDaftarUGD['rujukanAntarRS']['noRujukan'] }} dari BPJS?">
                                            <span wire:loading.remove wire:target="hapusRujukan">Hapus Rujukan BPJS</span>
                                            <span wire:loading wire:target="hapusRujukan"><x-loading /> Menghapus...</span>
                                        </x-danger-button>
                                    @endif

                                    <x-success-button type="button" wire:click="kirimBPJS" wire:loading.attr="disabled">
                                        <span wire:loading.remove wire:target="kirimBPJS"
                                            class="inline-flex items-center gap-2">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                                stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                    d="M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.27 20.876L5.999 12zm0 0h7.5" />
                                            </svg>
                                            {{ !empty($dataDaftarUGD['rujukanAntarRS']['noRujukan']) ? 'Update Rujukan BPJS' : 'Kirim Rujukan ke BPJS' }}
                                        </span>
                                        <span wire:loading wire:target="kirimBPJS" class="inline-flex items-center gap-2">
                                            <x-loading />
                                            {{ !empty($dataDaftarUGD['rujukanAntarRS']['noRujukan']) ? 'Mengupdate...' : 'Mengirim...' }}
                                        </span>
                                    </x-success-button>
                                </div>
                            @endif
                        @endif

                        {{-- save() sudah lengkap (lock + patch node + audit log) tapi selama
                             panel ini inline tak pernah punya tombol. Setelah jadi modal,
                             menyimpan tanpa mengirim ke BPJS jadi kebutuhan nyata. --}}
                        @if (!$isFormLocked)
                            <x-outline-button type="button" wire:click="save" wire:loading.attr="disabled" wire:target="save">
                                <span wire:loading.remove wire:target="save">Simpan</span>
                                <span wire:loading wire:target="save" class="inline-flex items-center gap-1"><x-loading /> Menyimpan...</span>
                            </x-outline-button>
                        @endif

                        <x-secondary-button type="button" wire:click="closeModal">Tutup</x-secondary-button>
                    </div>
                </div>
            </div>

        </div>
    </x-modal>
</div>
