<?php

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\WithFileUploads;
use Livewire\Attributes\Computed;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

new class extends Component {
    use WithPagination, WithFileUploads;

    /*
     | Modul: Administrasi Lab Luar
     | Sumber data: lbtxn_checkuphdrs (1 hdr = 1 order lab luar) +
     |              lbtxn_checkupoutdtls (item per hdr).
     | Indikator lab luar: hdr punya row di lbtxn_checkupoutdtls (bukan lbtxn_checkupdtls).
     | Status: PENDING = LABOUT_PRICE IS NULL, POSTED = IS NOT NULL.
     | PDF hasil disimpan di lbtxn_checkupoutdtls.pdf_path (1 dtl = 1 PDF).
     */

    public string $searchKeyword = '';
    public string $filterStatus = 'PENDING';
    public string $filterSource = '';
    public int $itemsPerPage = 15;

    public ?int $selectedCheckupNo = null;
    public ?int $selectedDtl = null;
    public array $form = [
        'tarif' => '',
        'keterangan_lab' => '',
    ];
    public $pdfFile = null;

    public function updatedSearchKeyword(): void
    {
        $this->resetPage();
    }
    public function updatedFilterStatus(): void
    {
        $this->resetPage();
    }
    public function updatedFilterSource(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset(['searchKeyword', 'filterSource']);
        $this->filterStatus = 'PENDING';
        $this->resetPage();
    }

    #[Computed]
    public function rows()
    {
        $q = DB::table('lbtxn_checkupoutdtls as o')
            ->join('lbtxn_checkuphdrs as h', 'o.checkup_no', '=', 'h.checkup_no')
            ->leftJoin('rsmst_pasiens as p', 'h.reg_no', '=', 'p.reg_no')
            ->leftJoin('rsmst_doctors as d', 'h.dr_id', '=', 'd.dr_id')
            ->select(
                'o.labout_dtl', 'o.checkup_no', 'o.labout_desc', 'o.labout_price', 'o.labout_result',
                'o.pdf_path',
                'h.reg_no', 'p.reg_name',
                'h.status_rjri', 'h.ref_no',
                'd.dr_name',
                'h.checkup_date',
            )
            ->orderByDesc('h.checkup_date')
            ->orderByDesc('o.labout_dtl');

        if ($this->filterStatus === 'PENDING') {
            $q->whereNull('o.labout_price');
        } elseif ($this->filterStatus === 'POSTED') {
            $q->whereNotNull('o.labout_price');
        }
        if ($this->filterSource !== '') {
            $q->where('h.status_rjri', $this->filterSource);
        }
        $kw = trim($this->searchKeyword);
        if ($kw !== '') {
            $q->where(function ($w) use ($kw) {
                $up = '%' . mb_strtoupper($kw) . '%';
                $w->whereRaw('UPPER(p.reg_name) LIKE ?', [$up])
                    ->orWhereRaw('TO_CHAR(h.reg_no) LIKE ?', ['%' . $kw . '%'])
                    ->orWhereRaw('UPPER(o.labout_desc) LIKE ?', [$up]);
            });
        }

        return $q->paginate($this->itemsPerPage);
    }

    /* ===============================
     | OPEN POST MODAL
     =============================== */
    public function openPostModal(int $checkupNo, int $dtl): void
    {
        $row = DB::table('lbtxn_checkupoutdtls')
            ->where('checkup_no', $checkupNo)
            ->where('labout_dtl', $dtl)
            ->first();
        if (!$row) {
            $this->dispatch('toast', type: 'error', message: 'Data tidak ditemukan.');
            return;
        }
        $this->selectedCheckupNo = $checkupNo;
        $this->selectedDtl = $dtl;
        $this->form = [
            'tarif' => $row->labout_price !== null ? (string) $row->labout_price : '',
            'keterangan_lab' => $row->labout_normal ?? '',
        ];
        $this->resetValidation();
        $this->dispatch('open-modal', name: 'lab-luar-post');
    }

    public function closePostModal(): void
    {
        $this->dispatch('close-modal', name: 'lab-luar-post');
        $this->selectedCheckupNo = null;
        $this->selectedDtl = null;
        $this->reset(['form']);
    }

    /* ===============================
     | POST TARIF — insert ke billing kunjungan asal
     =============================== */
    public function postTarif(): void
    {
        $this->validate(
            [
                'form.tarif' => 'bail|required|numeric|min:0',
                'form.keterangan_lab' => 'nullable|string|max:1000',
            ],
            [
                'form.tarif.required' => 'Tarif harus diisi.',
                'form.tarif.numeric' => 'Tarif harus berupa angka.',
            ],
        );

        $row = DB::table('lbtxn_checkupoutdtls as o')
            ->join('lbtxn_checkuphdrs as h', 'o.checkup_no', '=', 'h.checkup_no')
            ->select('o.labout_dtl', 'o.checkup_no', 'o.labout_desc', 'o.labout_price', 'h.status_rjri', 'h.ref_no')
            ->where('o.checkup_no', $this->selectedCheckupNo)
            ->where('o.labout_dtl', $this->selectedDtl)
            ->first();
        if (!$row) {
            $this->dispatch('toast', type: 'error', message: 'Data tidak ditemukan.');
            return;
        }
        if ($row->labout_price !== null) {
            $this->dispatch('toast', type: 'error', message: 'Order sudah di-post sebelumnya.');
            return;
        }

        $tarif = (float) $this->form['tarif'];
        $labDesc = '[LAB LUAR] ' . $row->labout_desc;

        try {
            DB::transaction(function () use ($row, $tarif, $labDesc) {
                $this->insertBillingDetail($row, $tarif, $labDesc);

                DB::table('lbtxn_checkupoutdtls')
                    ->where('checkup_no', $row->checkup_no)
                    ->where('labout_dtl', $row->labout_dtl)
                    ->update([
                        'labout_price' => $tarif,
                        'labout_normal' => $this->form['keterangan_lab'] ?: null,
                    ]);
            });

            $this->dispatch('toast', type: 'success', message: 'Tarif lab luar berhasil di-post ke billing kunjungan asal.');
            $this->closePostModal();
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal post: ' . $e->getMessage());
        }
    }

    private function insertBillingDetail(object $row, float $tarif, string $labDesc): void
    {
        if ($row->status_rjri === 'RJ') {
            $dtl = DB::scalar('SELECT NVL(MAX(lab_dtl)+1,1) FROM rstxn_rjlabs');
            DB::table('rstxn_rjlabs')->insert([
                'lab_dtl' => $dtl,
                'rj_no' => $row->ref_no,
                'lab_desc' => $labDesc,
                'lab_price' => $tarif,
            ]);
            return;
        }

        if ($row->status_rjri === 'UGD') {
            $dtl = DB::scalar('SELECT NVL(MAX(lab_dtl)+1,1) FROM rstxn_ugdlabs');
            DB::table('rstxn_ugdlabs')->insert([
                'lab_dtl' => $dtl,
                'rj_no' => $row->ref_no,
                'lab_desc' => $labDesc,
                'lab_price' => $tarif,
            ]);
            return;
        }

        if ($row->status_rjri === 'RI') {
            $dtl = DB::scalar('SELECT NVL(MAX(lab_dtl)+1,1) FROM rstxn_rilabs');
            $now = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
            DB::table('rstxn_rilabs')->insert([
                'lab_dtl' => $dtl,
                'rihdr_no' => $row->ref_no,
                'lab_desc' => $labDesc,
                'lab_price' => $tarif,
                'lab_date' => DB::raw("TO_DATE('{$now}','dd/mm/yyyy hh24:mi:ss')"),
            ]);
            return;
        }

        throw new \RuntimeException('Sumber kunjungan tidak dikenal: ' . $row->status_rjri);
    }

    /* ===============================
     | OPEN UPLOAD MODAL — PDF per dtl (1 dtl = 1 PDF)
     =============================== */
    public function openUploadModal(int $checkupNo, int $dtl): void
    {
        $row = DB::table('lbtxn_checkupoutdtls')
            ->where('checkup_no', $checkupNo)
            ->where('labout_dtl', $dtl)
            ->first();
        if (!$row) {
            $this->dispatch('toast', type: 'error', message: 'Detail tidak ditemukan.');
            return;
        }
        $this->selectedCheckupNo = $checkupNo;
        $this->selectedDtl = $dtl;
        $this->pdfFile = null;
        $this->resetValidation();
        $this->dispatch('open-modal', name: 'lab-luar-upload');
    }

    public function closeUploadModal(): void
    {
        $this->dispatch('close-modal', name: 'lab-luar-upload');
        $this->selectedCheckupNo = null;
        $this->selectedDtl = null;
        $this->pdfFile = null;
    }

    public function uploadHasil(): void
    {
        $this->validate(
            [
                'pdfFile' => 'required|file|mimes:pdf|max:5120',
            ],
            [
                'pdfFile.required' => 'File PDF harus dipilih.',
                'pdfFile.mimes' => 'File harus PDF.',
                'pdfFile.max' => 'Ukuran PDF maksimal 5 MB.',
            ],
        );

        $row = DB::table('lbtxn_checkupoutdtls')
            ->where('checkup_no', $this->selectedCheckupNo)
            ->where('labout_dtl', $this->selectedDtl)
            ->first();
        if (!$row) {
            $this->dispatch('toast', type: 'error', message: 'Detail tidak ditemukan.');
            return;
        }

        try {
            if (!empty($row->pdf_path) && Storage::disk('public')->exists($row->pdf_path)) {
                Storage::disk('public')->delete($row->pdf_path);
            }

            $filename = $row->checkup_no . '_' . $row->labout_dtl . '_' . now()->format('YmdHis') . '.pdf';
            $path = $this->pdfFile->storeAs('LabLuar', $filename, 'public');

            DB::table('lbtxn_checkupoutdtls')
                ->where('checkup_no', $row->checkup_no)
                ->where('labout_dtl', $row->labout_dtl)
                ->update([
                    'pdf_path' => $path,
                ]);

            $this->dispatch('toast', type: 'success', message: 'Hasil PDF berhasil di-upload.');
            $this->closeUploadModal();
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal upload: ' . $e->getMessage());
        }
    }

    /* ===============================
     | BATAL ORDER — hanya saat PENDING (LABOUT_PRICE NULL)
     | Hard delete: row dtl + hdr (kalau hdr tidak punya dtl lain)
     =============================== */
    public function batalOrder(int $checkupNo, int $dtl): void
    {
        $row = DB::table('lbtxn_checkupoutdtls as o')
            ->join('lbtxn_checkuphdrs as h', 'o.checkup_no', '=', 'h.checkup_no')
            ->select('o.checkup_no', 'o.labout_dtl', 'o.labout_price', 'h.status_rjri', 'h.ref_no')
            ->where('o.checkup_no', $checkupNo)
            ->where('o.labout_dtl', $dtl)
            ->first();
        if (!$row || $row->labout_price !== null) {
            $this->dispatch('toast', type: 'error', message: 'Order tidak bisa dibatalkan (sudah di-post).');
            return;
        }

        try {
            DB::transaction(function () use ($row) {
                DB::table('lbtxn_checkupoutdtls')
                    ->where('checkup_no', $row->checkup_no)
                    ->where('labout_dtl', $row->labout_dtl)
                    ->delete();

                $sisa = DB::table('lbtxn_checkupoutdtls')->where('checkup_no', $row->checkup_no)->count();
                $sisaInt = DB::table('lbtxn_checkupdtls')->where('checkup_no', $row->checkup_no)->count();
                if ($sisa === 0 && $sisaInt === 0) {
                    DB::table('lbtxn_checkuphdrs')->where('checkup_no', $row->checkup_no)->delete();
                }
            });

            $this->dispatch('toast', type: 'success', message: 'Order dibatalkan.');
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal: ' . $e->getMessage());
        }
    }
};
?>

<div>
    <x-page-title title="Administrasi Lab Luar" subtitle="Post tarif & upload hasil PDF dari laboratorium luar" />

    <div class="p-4 mb-4 bg-white border border-gray-200 rounded-2xl dark:border-gray-700 dark:bg-gray-900">
        <div class="grid grid-cols-1 gap-3 md:grid-cols-4">
            <div>
                <x-input-label value="Cari" />
                <x-text-input wire:model.live.debounce.300ms="searchKeyword" class="block w-full mt-1"
                    placeholder="reg_no / nama / pemeriksaan" />
            </div>
            <div>
                <x-input-label value="Status" />
                <select wire:model.live="filterStatus"
                    class="block w-full mt-1 text-sm border-gray-300 rounded-md shadow-sm focus:border-brand-green focus:ring-brand-green dark:bg-gray-800 dark:border-gray-600">
                    <option value="">Semua</option>
                    <option value="PENDING">PENDING (belum di-post)</option>
                    <option value="POSTED">POSTED (sudah di-post)</option>
                </select>
            </div>
            <div>
                <x-input-label value="Sumber" />
                <select wire:model.live="filterSource"
                    class="block w-full mt-1 text-sm border-gray-300 rounded-md shadow-sm focus:border-brand-green focus:ring-brand-green dark:bg-gray-800 dark:border-gray-600">
                    <option value="">Semua</option>
                    <option value="RJ">RJ</option>
                    <option value="UGD">UGD</option>
                    <option value="RI">RI</option>
                </select>
            </div>
            <div class="flex items-end">
                <x-secondary-button type="button" wire:click="resetFilters">Reset</x-secondary-button>
            </div>
        </div>
    </div>

    <div class="overflow-hidden bg-white border border-gray-200 rounded-2xl dark:border-gray-700 dark:bg-gray-900">
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead class="text-xs font-semibold text-gray-500 uppercase bg-gray-50 dark:bg-gray-800/50 dark:text-gray-400">
                    <tr>
                        <th class="px-4 py-3">Tgl Order</th>
                        <th class="px-4 py-3">Sumber</th>
                        <th class="px-4 py-3">Pasien</th>
                        <th class="px-4 py-3">Pemeriksaan</th>
                        <th class="px-4 py-3 text-right">Tarif</th>
                        <th class="px-4 py-3 text-center">Status</th>
                        <th class="px-4 py-3 text-center">Hasil</th>
                        <th class="px-4 py-3 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse ($this->rows as $r)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/40">
                            <td class="px-4 py-3 font-mono text-xs text-gray-500 whitespace-nowrap">
                                {{ $r->checkup_date ? \Carbon\Carbon::parse($r->checkup_date)->format('d/m/Y H:i') : '-' }}
                            </td>
                            <td class="px-4 py-3">
                                <x-badge variant="alternative">{{ $r->status_rjri }}</x-badge>
                                <span class="ml-1 font-mono text-xs text-gray-500">{{ $r->ref_no }}</span>
                            </td>
                            <td class="px-4 py-3">
                                <p class="font-semibold text-gray-800 dark:text-gray-200">{{ $r->reg_name ?? '-' }}</p>
                                <p class="text-xs text-gray-500 font-mono">{{ $r->reg_no }}</p>
                            </td>
                            <td class="px-4 py-3 text-gray-700 dark:text-gray-300">
                                {{ $r->labout_desc }}
                                @if ($r->labout_result)
                                    <p class="text-xs italic text-gray-500">Catatan klinis: {{ $r->labout_result }}</p>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                @if ($r->labout_price !== null)
                                    Rp {{ number_format($r->labout_price) }}
                                @else
                                    <span class="text-gray-400">-</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-center">
                                @if ($r->labout_price !== null)
                                    <x-badge variant="success">POSTED</x-badge>
                                @else
                                    <x-badge variant="warning">PENDING</x-badge>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-center">
                                @if ($r->pdf_path)
                                    <a href="{{ asset('storage/' . $r->pdf_path) }}" target="_blank"
                                        class="inline-flex items-center gap-1 text-xs font-medium text-brand-green hover:underline">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M15 12a3 3 0 11-6 0 3 3 0 016 0z M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                        </svg>
                                        Lihat PDF
                                    </a>
                                @else
                                    <span class="text-xs text-gray-400">Belum ada</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-center whitespace-nowrap">
                                @if ($r->labout_price === null)
                                    <x-primary-button type="button"
                                        wire:click="openPostModal({{ $r->checkup_no }}, {{ $r->labout_dtl }})"
                                        class="text-xs">Post Tarif</x-primary-button>
                                    <x-secondary-button type="button"
                                        wire:click="batalOrder({{ $r->checkup_no }}, {{ $r->labout_dtl }})"
                                        wire:confirm="Yakin batalkan order ini?" class="text-xs">Batal</x-secondary-button>
                                @else
                                    <x-primary-button type="button"
                                        wire:click="openUploadModal({{ $r->checkup_no }}, {{ $r->labout_dtl }})"
                                        class="text-xs">
                                        {{ $r->pdf_path ? 'Replace PDF' : 'Upload Hasil' }}
                                    </x-primary-button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-10 text-sm text-center text-gray-400 dark:text-gray-600">
                                Tidak ada data lab luar.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-4 py-3 border-t border-gray-100 dark:border-gray-700">
            {{ $this->rows->links() }}
        </div>
    </div>

    {{-- ============================================ --}}
    {{-- MODAL: POST TARIF                            --}}
    {{-- ============================================ --}}
    <x-modal name="lab-luar-post" size="lg" focusable>
        <div>
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                <h2 class="text-lg font-semibold">Post Tarif Lab Luar</h2>
                <p class="text-xs text-gray-500">Tarif akan otomatis masuk ke billing kunjungan asal pasien.</p>
            </div>
            <div class="px-6 py-5 space-y-4">
                <div>
                    <x-input-label value="Tarif (Rp)" required />
                    <x-text-input-number wire:model="form.tarif" class="mt-1"
                        :error="$errors->has('form.tarif')" />
                    @error('form.tarif')
                        <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <x-input-label value="Keterangan Lab (opsional)" />
                    <textarea wire:model.defer="form.keterangan_lab" rows="3"
                        class="block w-full mt-1 text-sm border-gray-300 rounded-md shadow-sm focus:border-brand-green focus:ring-brand-green dark:bg-gray-800 dark:border-gray-600"
                        placeholder="catatan tambahan dari pihak lab"></textarea>
                </div>
            </div>
            <div class="flex items-center justify-end gap-2 px-6 py-3 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/40">
                <x-secondary-button type="button" wire:click="closePostModal">Batal</x-secondary-button>
                <x-primary-button type="button" wire:click="postTarif" wire:loading.attr="disabled" wire:target="postTarif">
                    <span wire:loading.remove wire:target="postTarif">Post Tarif</span>
                    <span wire:loading wire:target="postTarif"><x-loading /> Memproses...</span>
                </x-primary-button>
            </div>
        </div>
    </x-modal>

    {{-- ============================================ --}}
    {{-- MODAL: UPLOAD HASIL                          --}}
    {{-- ============================================ --}}
    <x-modal name="lab-luar-upload" size="lg" focusable>
        <div>
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                <h2 class="text-lg font-semibold">Upload Hasil Lab Luar</h2>
                <p class="text-xs text-gray-500">File PDF maks 5 MB.</p>
            </div>
            <div class="px-6 py-5 space-y-4">
                <div>
                    <x-input-label value="File PDF" required />
                    <input type="file" wire:model="pdfFile" accept="application/pdf"
                        class="block w-full mt-1 text-sm text-gray-600 file:mr-3 file:py-2 file:px-4 file:rounded file:border-0 file:text-sm file:font-semibold file:bg-brand-green/10 file:text-brand-green hover:file:bg-brand-green/20" />
                    @error('pdfFile')
                        <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                    @enderror
                    <div wire:loading wire:target="pdfFile" class="mt-2 text-xs text-gray-500">Memuat file...</div>
                </div>
            </div>
            <div class="flex items-center justify-end gap-2 px-6 py-3 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/40">
                <x-secondary-button type="button" wire:click="closeUploadModal">Batal</x-secondary-button>
                <x-primary-button type="button" wire:click="uploadHasil" wire:loading.attr="disabled" wire:target="uploadHasil,pdfFile">
                    <span wire:loading.remove wire:target="uploadHasil">Upload</span>
                    <span wire:loading wire:target="uploadHasil"><x-loading /> Uploading...</span>
                </x-primary-button>
            </div>
        </div>
    </x-modal>
</div>
