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

    /**
     * IRISAN dokumen: cabang `rujukanAntarRS` (model form) + tiga nilai dari cabang lain
     * yang dipakai sebagai isian awal / penentu tombol BPJS.
     */
    public array $rujukanAntarRS = [];
    public string $noSepKunjungan = '';
    public string $diagAwalSep = '';
    public string $klaimStatus = '';
    public string $klaimId = '';

    /** Penanda kunjungan sudah dimuat lewat open(). */
    public bool $dokumenTermuat = false;

    /** Dokumen dibaca sebagai variabel LOKAL; hanya irisan + skalar yang disimpan. */
    private function serapDokumen(array $data): void
    {
        $this->rujukanAntarRS = $data['rujukanAntarRS'] ?? [];
        $this->noSepKunjungan = (string) ($data['sep']['noSep'] ?? '');
        $this->diagAwalSep = (string) ($data['sep']['reqSep']['request']['t_sep']['diagAwal'] ?? '');
        $this->klaimStatus = (string) ($data['klaimStatus'] ?? '');
        $this->klaimId = (string) ($data['klaimId'] ?? '');
        $this->dokumenTermuat = true;
    }

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

        $this->serapDokumen($data);
        $this->rujukanAntarRS = $this->rujukanAntarRS ?: $this->getDefaultRujukanAntarRS();

        $this->incrementVersion('modal-rujukan-rs');

        if ($this->checkEmrUGDStatus($rjNo)) {
            $this->isFormLocked = true;
        }
    }

    /* ===============================
     | CLOSE MODAL
     =============================== */
    public function closeModal(): void
    {
        $this->resetValidation();
        $this->resetForm();
    }

    /* ===============================
     | DEFAULT RUJUKAN ANTAR RS STRUCTURE
     =============================== */
    private function getDefaultRujukanAntarRS(): array
    {
        $noSep = $this->noSepKunjungan ?: (DB::table('rsview_rjkasir')->where('rj_no', $this->rjNo)->value('vno_sep') ?? '');

        return [
            'noSep' => $noSep,
            'tglRujukan' => Carbon::now()->format('d/m/Y'),
            'tglRencanaKunjungan' => Carbon::now()->addDays(1)->format('d/m/Y'),
            'ppkDirujuk' => '',
            'ppkDirujukNama' => '',
            'jnsPelayanan' => '2',
            'catatan' => '',
            'diagRujukan' => $this->diagAwalSep,
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

        $this->rujukanAntarRS['ppkDirujuk'] = $faskes['kode'] ?? '';
        $this->rujukanAntarRS['ppkDirujukNama'] = $faskes['nama'] ?? '';
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
        $rujukan = $this->rujukanAntarRS ?? [];
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
            'rujukanAntarRS.tglRujukan' => 'required|date_format:d/m/Y',
            'rujukanAntarRS.tglRencanaKunjungan' => 'required|date_format:d/m/Y',
            'rujukanAntarRS.ppkDirujuk' => 'required',
            'rujukanAntarRS.jnsPelayanan' => 'required|in:1,2',
            'rujukanAntarRS.diagRujukan' => 'required',
            'rujukanAntarRS.tipeRujukan' => 'required|in:0,1,2',
        ];

        $tipe = $this->rujukanAntarRS['tipeRujukan'] ?? '0';
        if (in_array($tipe, ['0', '1'])) {
            $rules['rujukanAntarRS.poliRujukan'] = 'required';
        }

        return $rules;
    }

    protected function messages(): array
    {
        return [
            'rujukanAntarRS.tglRujukan.required' => 'Tanggal rujukan harus diisi.',
            'rujukanAntarRS.tglRujukan.date_format' => 'Format tanggal rujukan harus dd/mm/yyyy.',
            'rujukanAntarRS.tglRencanaKunjungan.required' => 'Tanggal rencana kunjungan harus diisi.',
            'rujukanAntarRS.tglRencanaKunjungan.date_format' => 'Format tanggal rencana kunjungan harus dd/mm/yyyy.',
            'rujukanAntarRS.ppkDirujuk.required' => 'PPK tujuan rujukan harus diisi.',
            'rujukanAntarRS.jnsPelayanan.required' => 'Jenis pelayanan harus dipilih.',
            'rujukanAntarRS.diagRujukan.required' => 'Diagnosis rujukan harus diisi.',
            'rujukanAntarRS.tipeRujukan.required' => 'Tipe rujukan harus dipilih.',
            'rujukanAntarRS.poliRujukan.required' => 'Poli rujukan wajib diisi untuk tipe Penuh/Partial.',
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
                $data['rujukanAntarRS'] = $this->rujukanAntarRS ?? [];

                $this->updateJsonUGD($this->rjNo, $data);
                $this->serapDokumen($data);

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
        $rujukan = $this->rujukanAntarRS ?? [];

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
                    $this->rujukanAntarRS['noRujukan'] = $response['response']['rujukan']['noRujukan'] ?? '';
                }

                // Persist noRujukan ke JSON DB
                DB::transaction(function () use ($label) {
                    $this->lockUGDRow($this->rjNo);
                    $data = $this->findDataUGD($this->rjNo) ?? [];
                    $data['rujukanAntarRS'] = $this->rujukanAntarRS;
                    $this->updateJsonUGD($this->rjNo, $data);
                    $this->serapDokumen($data);

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
        $noRujukan = $this->rujukanAntarRS['noRujukan'] ?? '';
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
                $this->rujukanAntarRS['noRujukan'] = '';

                DB::transaction(function () use ($noRujukan) {
                    $this->lockUGDRow($this->rjNo);
                    $data = $this->findDataUGD($this->rjNo) ?? [];
                    $data['rujukanAntarRS'] = $this->rujukanAntarRS;
                    $this->updateJsonUGD($this->rjNo, $data);
                    $this->serapDokumen($data);

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
        $current = $this->rujukanAntarRS ?? [];
        $this->rujukanAntarRS = array_replace_recursive($default, $current);
    }
};
?>

<div>
    {{-- CONTAINER — inline, mirip form perencanaan/kontrol --}}
    <div class="flex flex-col w-full" wire:key="{{ $this->renderKey('modal-rujukan-rs', [$rjNo ?? 'new']) }}">
        <div class="w-full mx-auto">
            <div
                class="w-full p-4 space-y-6 bg-canvas border border-hairline shadow-sm rounded-2xl dark:bg-gray-900 dark:border-gray-700">

                <div class="w-full">

                    {{-- Header --}}
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-base font-semibold text-body dark:text-gray-300">Rujukan Antar RS</h3>
                        @if (!empty($rujukanAntarRS['noRujukan']))
                            <x-badge variant="success">BPJS:
                                {{ $rujukanAntarRS['noRujukan'] }}</x-badge>
                        @else
                            <x-badge variant="warning">Belum dikirim ke BPJS</x-badge>
                        @endif
                    </div>

                    <div class="grid grid-cols-1 gap-2">

                        {{-- KOLOM KIRI --}}
                        <div class="space-y-4">

                            {{-- No SEP (readonly) --}}
                            <div>
                                <x-input-label value="No. SEP" class="mb-1" />
                                <x-text-input wire:model="rujukanAntarRS.noSep" :disabled="true"
                                    class="w-full" />
                                @if (empty($rujukanAntarRS['noSep']))
                                    <p class="mt-1 text-sm text-amber-500">SEP belum terbit.</p>
                                @endif
                            </div>

                            {{-- No Rujukan BPJS (readonly) --}}
                            <div>
                                <x-input-label value="No. Rujukan BPJS" class="mb-1" />
                                <x-text-input wire:model="rujukanAntarRS.noRujukan"
                                    placeholder="Terisi setelah kirim ke BPJS" :disabled="true" class="w-full" />
                            </div>

                            {{-- Tanggal Rujukan --}}
                            <div>
                                <x-input-label value="Tanggal Rujukan *" class="mb-1" />
                                <x-text-input wire:model.live="rujukanAntarRS.tglRujukan"
                                    placeholder="dd/mm/yyyy" :disabled="$isFormLocked" :error="$errors->has('rujukanAntarRS.tglRujukan')" class="w-full" />
                                <x-input-error :messages="$errors->get('rujukanAntarRS.tglRujukan')" class="mt-1" />
                            </div>

                            {{-- Tanggal Rencana Kunjungan --}}
                            <div>
                                <x-input-label value="Tanggal Rencana Kunjungan *" class="mb-1" />
                                <x-text-input wire:model.live="rujukanAntarRS.tglRencanaKunjungan"
                                    placeholder="dd/mm/yyyy" :disabled="$isFormLocked" :error="$errors->has('rujukanAntarRS.tglRencanaKunjungan')" class="w-full" />
                                <x-input-error :messages="$errors->get('rujukanAntarRS.tglRencanaKunjungan')" class="mt-1" />
                            </div>

                        </div>

                        {{-- KOLOM KANAN --}}
                        <div class="space-y-4">

                            {{-- PPK Tujuan --}}
                            <div>
                                <x-input-label value="PPK Tujuan Rujukan *" class="mb-1" />
                                <div class="flex gap-2">
                                    <x-text-input wire:model.live="rujukanAntarRS.ppkDirujuk"
                                        class="w-40" :disabled="true" placeholder="Kode PPK"
                                        :error="$errors->has('rujukanAntarRS.ppkDirujuk')" />
                                    <x-text-input wire:model="rujukanAntarRS.ppkDirujukNama"
                                        class="flex-1" :disabled="true" placeholder="Pilih faskes via tombol Cari" />
                                </div>
                                <x-input-error :messages="$errors->get('rujukanAntarRS.ppkDirujuk')" class="mt-1" />

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
                                <x-select-input wire:model="rujukanAntarRS.jnsPelayanan" class="w-full"
                                    :disabled="$isFormLocked">
                                    <option value="1">1 - Rawat Inap</option>
                                    <option value="2">2 - Rawat Jalan</option>
                                </x-select-input>
                            </div>

                            {{-- Tipe Rujukan --}}
                            <div>
                                <x-input-label value="Tipe Rujukan *" class="mb-1" />
                                <x-select-input wire:model.live="rujukanAntarRS.tipeRujukan"
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
                                    <x-text-input wire:model.live="rujukanAntarRS.diagRujukan"
                                        class="w-32" :disabled="$isFormLocked" placeholder="Kode ICD" :error="$errors->has('rujukanAntarRS.diagRujukan')" />
                                    <x-text-input wire:model="rujukanAntarRS.diagRujukanNama"
                                        class="flex-1" :disabled="true" placeholder="Nama diagnosa" />
                                </div>
                                <x-input-error :messages="$errors->get('rujukanAntarRS.diagRujukan')" class="mt-1" />
                            </div>

                            {{-- Poli Rujukan (wajib tipe 0/1, kosong tipe 2) --}}
                            @if (in_array($rujukanAntarRS['tipeRujukan'] ?? '0', ['0', '1']))
                                <div>
                                    <x-input-label value="Poli Rujukan *" class="mb-1" />
                                    @if (!empty($listSpesialistik))
                                        <x-select-input wire:model="rujukanAntarRS.poliRujukan"
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
                                            <x-text-input wire:model.live="rujukanAntarRS.poliRujukan"
                                                class="w-32" :disabled="$isFormLocked" placeholder="Kode poli"
                                                :error="$errors->has('rujukanAntarRS.poliRujukan')" />
                                            <x-text-input wire:model="rujukanAntarRS.poliRujukanNama"
                                                class="flex-1" :disabled="true" placeholder="Nama poli" />
                                        </div>
                                    @endif
                                    @if (!$isFormLocked && !empty($rujukanAntarRS['ppkDirujuk']))
                                        <x-secondary-button type="button" wire:click="fetchListSpesialistik"
                                            wire:loading.attr="disabled" class="mt-1 text-sm">
                                            <span wire:loading.remove wire:target="fetchListSpesialistik">Muat Poli
                                                dari BPJS</span>
                                            <span wire:loading wire:target="fetchListSpesialistik"><x-loading /></span>
                                        </x-secondary-button>
                                    @endif
                                    <x-input-error :messages="$errors->get('rujukanAntarRS.poliRujukan')" class="mt-1" />
                                </div>
                            @endif

                            {{-- Catatan --}}
                            <div>
                                <x-input-label value="Catatan" class="mb-1" />
                                <x-text-input wire:model.live="rujukanAntarRS.catatan" class="w-full"
                                    :disabled="$isFormLocked" placeholder="Catatan rujukan" />
                            </div>

                        </div>
                    </div>
                </div>

                {{-- Tombol Kirim ke BPJS / Hapus --}}
                @if (!$isFormLocked)
                    @php $isBPJS = $klaimStatus === 'BPJS' || $klaimId === 'JM'; @endphp

                    @if ($isBPJS)
                        <div class="flex items-center justify-end gap-2 pt-2">
                            @if (!empty($rujukanAntarRS['noRujukan']))
                                <x-hapus-button wire:click="hapusRujukan" confirm="Yakin hapus rujukan {{ $rujukanAntarRS['noRujukan'] }} dari BPJS?" label="Hapus Rujukan BPJS" />
                            @endif

                            <x-success-button type="button" wire:click="kirimBPJS" wire:loading.attr="disabled">
                                <span wire:loading.remove wire:target="kirimBPJS"
                                    class="inline-flex items-center gap-2">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                        stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.27 20.876L5.999 12zm0 0h7.5" />
                                    </svg>
                                    {{ !empty($rujukanAntarRS['noRujukan']) ? 'Update Rujukan BPJS' : 'Kirim Rujukan ke BPJS' }}
                                </span>
                                <span wire:loading wire:target="kirimBPJS" class="inline-flex items-center gap-2">
                                    <x-loading />
                                    {{ !empty($rujukanAntarRS['noRujukan']) ? 'Mengupdate...' : 'Mengirim...' }}
                                </span>
                            </x-success-button>
                        </div>
                    @endif
                @endif

            </div>
        </div>
    </div>
</div>
