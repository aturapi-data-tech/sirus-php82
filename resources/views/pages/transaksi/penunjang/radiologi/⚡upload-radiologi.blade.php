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
     | Modul: Upload Hasil Radiologi
     | Khusus upload Foto Radiologi (RAD_UPLOAD_PDF_FOTO) & Hasil Bacaan
     | (RAD_UPLOAD_PDF) ke order radiologi yang sudah ada di EMR.
     |
     | Sumber per source:
     |   RJ  → rstxn_rjrads     (PK rad_dtl,  ref rj_no)
     |   UGD → rstxn_ugdrads    (PK rad_dtl,  ref rj_no)
     |   RI  → rstxn_riradiologs(PK rirad_no, ref rihdr_no)
     */

    public string $searchKeyword = '';
    public string $filterSource = 'RJ';
    public string $filterUpload = 'belum'; // '' | 'belum_foto' | 'belum_pdf' | 'belum' (any) | 'lengkap'
    public string $filterBulan = ''; // format mm/yyyy, default = bulan ini
    public int $itemsPerPage = 15;

    public ?int $selectedDtl = null;
    public ?int $selectedRefNo = null;
    public string $selectedSource = '';
    public $fotoFile = null;
    public $pdfFile = null;

    public function mount(): void
    {
        // Default bulan = bulan saat ini (mm/yyyy)
        $this->filterBulan = Carbon::now()->format('m/Y');
    }

    public function updatedSearchKeyword(): void { $this->resetPage(); }
    public function updatedFilterSource(): void { $this->resetPage(); }
    public function updatedFilterUpload(): void { $this->resetPage(); }
    public function updatedFilterBulan(): void { $this->resetPage(); }

    public function resetFilters(): void
    {
        $this->reset(['searchKeyword']);
        $this->filterSource = 'RJ';
        $this->filterUpload = 'belum';
        $this->filterBulan = Carbon::now()->format('m/Y');
        $this->resetPage();
    }

    /* ===============================
     | QUERY — single source per request (toggle filterSource)
     =============================== */
    #[Computed]
    public function rows()
    {
        $src = $this->filterSource;

        if ($src === 'RJ') {
            $q = DB::table('rstxn_rjrads as r')
                ->join('rsmst_radiologis as m', 'r.rad_id', '=', 'm.rad_id')
                ->join('rstxn_rjhdrs as h', 'r.rj_no', '=', 'h.rj_no')
                ->leftJoin('rsmst_pasiens as p', 'h.reg_no', '=', 'p.reg_no')
                ->select(
                    DB::raw("'RJ' as src"),
                    'r.rad_dtl as dtl_no', 'r.rj_no as ref_no',
                    'p.reg_no', 'p.reg_name',
                    'm.rad_desc', 'r.rad_price',
                    'r.dr_pengirim', 'r.dr_radiologi',
                    'r.rad_upload_pdf', 'r.rad_upload_pdf_foto',
                    'r.keterangan',
                    'r.waktu_entry',
                );
        } elseif ($src === 'UGD') {
            $q = DB::table('rstxn_ugdrads as r')
                ->join('rsmst_radiologis as m', 'r.rad_id', '=', 'm.rad_id')
                ->join('rstxn_ugdhdrs as h', 'r.rj_no', '=', 'h.rj_no')
                ->leftJoin('rsmst_pasiens as p', 'h.reg_no', '=', 'p.reg_no')
                ->select(
                    DB::raw("'UGD' as src"),
                    'r.rad_dtl as dtl_no', 'r.rj_no as ref_no',
                    'p.reg_no', 'p.reg_name',
                    'm.rad_desc', 'r.rad_price',
                    'r.dr_pengirim', 'r.dr_radiologi',
                    'r.rad_upload_pdf', 'r.rad_upload_pdf_foto',
                    'r.keterangan',
                    'r.waktu_entry',
                );
        } else { // RI
            $q = DB::table('rstxn_riradiologs as r')
                ->join('rsmst_radiologis as m', 'r.rad_id', '=', 'm.rad_id')
                ->join('rstxn_rihdrs as h', 'r.rihdr_no', '=', 'h.rihdr_no')
                ->leftJoin('rsmst_pasiens as p', 'h.reg_no', '=', 'p.reg_no')
                ->select(
                    DB::raw("'RI' as src"),
                    'r.rirad_no as dtl_no', 'r.rihdr_no as ref_no',
                    'p.reg_no', 'p.reg_name',
                    'm.rad_desc', 'r.rirad_price as rad_price',
                    'r.dr_pengirim', 'r.dr_radiologi',
                    'r.rad_upload_pdf', 'r.rad_upload_pdf_foto',
                    'r.keterangan',
                    'r.waktu_entry',
                );
        }

        // Filter status upload
        if ($this->filterUpload === 'belum_foto') {
            $q->whereNull('r.rad_upload_pdf_foto');
        } elseif ($this->filterUpload === 'belum_pdf') {
            $q->whereNull('r.rad_upload_pdf');
        } elseif ($this->filterUpload === 'belum') {
            $q->where(function ($w) {
                $w->whereNull('r.rad_upload_pdf_foto')->orWhereNull('r.rad_upload_pdf');
            });
        } elseif ($this->filterUpload === 'lengkap') {
            $q->whereNotNull('r.rad_upload_pdf_foto')->whereNotNull('r.rad_upload_pdf');
        }

        $kw = trim($this->searchKeyword);
        if ($kw !== '') {
            $up = '%' . mb_strtoupper($kw) . '%';
            $q->where(function ($w) use ($kw, $up) {
                $w->whereRaw('UPPER(p.reg_name) LIKE ?', [$up])
                    ->orWhereRaw('TO_CHAR(p.reg_no) LIKE ?', ['%' . $kw . '%'])
                    ->orWhereRaw('UPPER(m.rad_desc) LIKE ?', [$up]);
            });
        }

        // Filter bulan (format mm/yyyy) → EXTRACT month + year dari waktu_entry
        $bulan = trim($this->filterBulan);
        if (preg_match('/^(\d{1,2})\/(\d{4})$/', $bulan, $m)) {
            $bln = (int) $m[1];
            $thn = (int) $m[2];
            if ($bln >= 1 && $bln <= 12) {
                $q->whereRaw('EXTRACT(MONTH FROM r.waktu_entry) = ?', [$bln])
                  ->whereRaw('EXTRACT(YEAR FROM r.waktu_entry) = ?', [$thn]);
            }
        }

        return $q->orderByDesc('r.waktu_entry')->orderByDesc('r.' . ($src === 'RI' ? 'rirad_no' : 'rad_dtl'))
            ->paginate($this->itemsPerPage);
    }

    /* ===============================
     | OPEN UPLOAD FOTO MODAL
     =============================== */
    public function openUploadFotoModal(string $source, int $dtlNo, int $refNo): void
    {
        $this->selectedSource = $source;
        $this->selectedDtl = $dtlNo;
        $this->selectedRefNo = $refNo;
        $this->fotoFile = null;
        $this->resetValidation();
        $this->dispatch('open-modal', name: 'rad-upload-foto');
    }

    public function closeUploadFotoModal(): void
    {
        $this->dispatch('close-modal', name: 'rad-upload-foto');
        $this->reset(['selectedSource', 'selectedDtl', 'selectedRefNo', 'fotoFile']);
    }

    public function uploadFoto(): void
    {
        $this->validate(
            ['fotoFile' => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240'],
            [
                'fotoFile.required' => 'File foto harus dipilih.',
                'fotoFile.mimes' => 'Format harus PDF / JPG / PNG.',
                'fotoFile.max' => 'Ukuran maksimal 10 MB.',
            ],
        );

        $row = $this->getSelectedRow();
        if (!$row) {
            $this->dispatch('toast', type: 'error', message: 'Data tidak ditemukan.');
            return;
        }

        try {
            $existing = $row->rad_upload_pdf_foto ?? null;
            if (!empty($existing) && Storage::disk('public')->exists($existing)) {
                Storage::disk('public')->delete($existing);
            }

            $ext = $this->fotoFile->getClientOriginalExtension();
            $filename = 'foto_' . $this->selectedSource . '_' . $this->selectedRefNo . '_' . $this->selectedDtl . '_' . now()->format('YmdHis') . '.' . $ext;
            $path = $this->fotoFile->storeAs('Radiologi/Foto', $filename, 'public');

            $this->updateUploadColumn('rad_upload_pdf_foto', $path);

            $this->dispatch('toast', type: 'success', message: 'Foto radiologi berhasil di-upload.');
            $this->closeUploadFotoModal();
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal upload: ' . $e->getMessage());
        }
    }

    /* ===============================
     | OPEN UPLOAD PDF (HASIL BACAAN) MODAL
     =============================== */
    public function openUploadPdfModal(string $source, int $dtlNo, int $refNo): void
    {
        $this->selectedSource = $source;
        $this->selectedDtl = $dtlNo;
        $this->selectedRefNo = $refNo;
        $this->pdfFile = null;
        $this->resetValidation();
        $this->dispatch('open-modal', name: 'rad-upload-pdf');
    }

    public function closeUploadPdfModal(): void
    {
        $this->dispatch('close-modal', name: 'rad-upload-pdf');
        $this->reset(['selectedSource', 'selectedDtl', 'selectedRefNo', 'pdfFile']);
    }

    public function uploadPdf(): void
    {
        $this->validate(
            ['pdfFile' => 'required|file|mimes:pdf|max:5120'],
            [
                'pdfFile.required' => 'File PDF harus dipilih.',
                'pdfFile.mimes' => 'File harus PDF.',
                'pdfFile.max' => 'Ukuran maksimal 5 MB.',
            ],
        );

        $row = $this->getSelectedRow();
        if (!$row) {
            $this->dispatch('toast', type: 'error', message: 'Data tidak ditemukan.');
            return;
        }

        try {
            $existing = $row->rad_upload_pdf ?? null;
            if (!empty($existing) && Storage::disk('public')->exists($existing)) {
                Storage::disk('public')->delete($existing);
            }

            $filename = 'hasil_' . $this->selectedSource . '_' . $this->selectedRefNo . '_' . $this->selectedDtl . '_' . now()->format('YmdHis') . '.pdf';
            $path = $this->pdfFile->storeAs('Radiologi/Hasil', $filename, 'public');

            $this->updateUploadColumn('rad_upload_pdf', $path);

            $this->dispatch('toast', type: 'success', message: 'Hasil bacaan PDF berhasil di-upload.');
            $this->closeUploadPdfModal();
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal upload: ' . $e->getMessage());
        }
    }

    /* ===============================
     | UPDATE KETERANGAN — inline edit per row
     =============================== */
    public function updateKeterangan(string $source, int $dtlNo, int $refNo, string $value): void
    {
        $value = trim($value);
        $payload = $value === '' ? null : $value;

        try {
            if ($source === 'RJ') {
                DB::table('rstxn_rjrads')->where('rad_dtl', $dtlNo)->where('rj_no', $refNo)->update(['keterangan' => $payload]);
            } elseif ($source === 'UGD') {
                DB::table('rstxn_ugdrads')->where('rad_dtl', $dtlNo)->where('rj_no', $refNo)->update(['keterangan' => $payload]);
            } elseif ($source === 'RI') {
                DB::table('rstxn_riradiologs')->where('rirad_no', $dtlNo)->where('rihdr_no', $refNo)->update(['keterangan' => $payload]);
            }
            $this->dispatch('toast', type: 'success', message: 'Keterangan disimpan.');
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal simpan: ' . $e->getMessage());
        }
    }

    private function getSelectedRow(): ?object
    {
        if ($this->selectedSource === 'RJ') {
            return DB::table('rstxn_rjrads')
                ->where('rad_dtl', $this->selectedDtl)
                ->where('rj_no', $this->selectedRefNo)
                ->first(['rad_upload_pdf', 'rad_upload_pdf_foto']);
        }
        if ($this->selectedSource === 'UGD') {
            return DB::table('rstxn_ugdrads')
                ->where('rad_dtl', $this->selectedDtl)
                ->where('rj_no', $this->selectedRefNo)
                ->first(['rad_upload_pdf', 'rad_upload_pdf_foto']);
        }
        if ($this->selectedSource === 'RI') {
            return DB::table('rstxn_riradiologs')
                ->where('rirad_no', $this->selectedDtl)
                ->where('rihdr_no', $this->selectedRefNo)
                ->first(['rad_upload_pdf', 'rad_upload_pdf_foto']);
        }
        return null;
    }

    private function updateUploadColumn(string $column, string $path): void
    {
        if ($this->selectedSource === 'RJ') {
            DB::table('rstxn_rjrads')
                ->where('rad_dtl', $this->selectedDtl)
                ->where('rj_no', $this->selectedRefNo)
                ->update([$column => $path]);
        } elseif ($this->selectedSource === 'UGD') {
            DB::table('rstxn_ugdrads')
                ->where('rad_dtl', $this->selectedDtl)
                ->where('rj_no', $this->selectedRefNo)
                ->update([$column => $path]);
        } elseif ($this->selectedSource === 'RI') {
            DB::table('rstxn_riradiologs')
                ->where('rirad_no', $this->selectedDtl)
                ->where('rihdr_no', $this->selectedRefNo)
                ->update([$column => $path]);
        }
    }
};
?>

<div>
    <header class="bg-white shadow dark:bg-gray-800">
        <div class="w-full px-4 py-2 sm:px-6 lg:px-8">
            <h2 class="text-2xl font-bold leading-tight text-gray-900 dark:text-gray-100">
                Upload Hasil Radiologi
            </h2>
            <p class="text-base text-gray-700 dark:text-gray-700">
                Upload foto radiologi & hasil bacaan PDF untuk order pemeriksaan
            </p>
        </div>
    </header>

    <div class="px-6 pt-4 pb-6 bg-white dark:bg-gray-800 min-h-[calc(100vh-5rem-72px)]">

        <div class="p-4 mb-4 bg-white border border-gray-200 rounded-2xl dark:border-gray-700 dark:bg-gray-900">
            <div class="grid grid-cols-1 gap-3 md:grid-cols-5">
                <div>
                    <x-input-label value="Cari" />
                    <x-text-input wire:model.live.debounce.300ms="searchKeyword" class="block w-full mt-1"
                        placeholder="reg_no / nama / pemeriksaan" />
                </div>
                <div>
                    <x-input-label value="Bulan (mm/yyyy)" />
                    <x-text-input wire:model.live.debounce.500ms="filterBulan" class="block w-full mt-1"
                        placeholder="contoh: 05/2026" maxlength="7" />
                </div>
                <div>
                    <x-input-label value="Sumber" />
                    <select wire:model.live="filterSource"
                        class="block w-full mt-1 text-sm border-gray-300 rounded-md shadow-sm focus:border-brand-green focus:ring-brand-green dark:bg-gray-800 dark:border-gray-600">
                        <option value="RJ">RJ</option>
                        <option value="UGD">UGD</option>
                        <option value="RI">RI</option>
                    </select>
                </div>
                <div>
                    <x-input-label value="Status Upload" />
                    <select wire:model.live="filterUpload"
                        class="block w-full mt-1 text-sm border-gray-300 rounded-md shadow-sm focus:border-brand-green focus:ring-brand-green dark:bg-gray-800 dark:border-gray-600">
                        <option value="">Semua</option>
                        <option value="belum">Belum lengkap (foto/hasil ada yang kosong)</option>
                        <option value="belum_foto">Foto belum di-upload</option>
                        <option value="belum_pdf">Hasil bacaan belum di-upload</option>
                        <option value="lengkap">Sudah lengkap</option>
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
                            <th class="px-4 py-3">Dr. Pengirim</th>
                            <th class="px-4 py-3">Keterangan</th>
                            <th class="px-4 py-3 text-center">Foto</th>
                            <th class="px-4 py-3 text-center">Hasil Bacaan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse ($this->rows as $r)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/40">
                                <td class="px-4 py-3 font-mono text-xs text-gray-500 whitespace-nowrap">
                                    {{ $r->waktu_entry ? \Carbon\Carbon::parse($r->waktu_entry)->format('d/m/Y H:i') : '-' }}
                                </td>
                                <td class="px-4 py-3">
                                    <x-badge variant="alternative">{{ $r->src }}</x-badge>
                                    <span class="ml-1 font-mono text-xs text-gray-500">{{ $r->ref_no }}</span>
                                </td>
                                <td class="px-4 py-3">
                                    <p class="font-semibold text-gray-800 dark:text-gray-200">{{ $r->reg_name ?? '-' }}</p>
                                    <p class="text-xs text-gray-500 font-mono">{{ $r->reg_no }}</p>
                                </td>
                                <td class="px-4 py-3 text-gray-700 dark:text-gray-300">
                                    {{ $r->rad_desc }}
                                </td>
                                <td class="px-4 py-3 text-gray-700 dark:text-gray-300">
                                    {{ $r->dr_pengirim ?? '-' }}
                                </td>
                                <td class="px-4 py-3">
                                    <input type="text"
                                        value="{{ $r->keterangan }}"
                                        wire:change="updateKeterangan('{{ $r->src }}', {{ $r->dtl_no }}, {{ $r->ref_no }}, $event.target.value)"
                                        placeholder="contoh: AP/lateral, sebelum kontras"
                                        class="block w-56 text-xs border-gray-300 rounded-md shadow-sm focus:border-brand-green focus:ring-brand-green dark:bg-gray-800 dark:border-gray-600 dark:text-gray-100" />
                                </td>
                                <td class="px-4 py-3 text-center whitespace-nowrap">
                                    @if ($r->rad_upload_pdf_foto)
                                        <a href="{{ asset('storage/' . $r->rad_upload_pdf_foto) }}" target="_blank"
                                            class="inline-flex items-center gap-1 px-2 py-1 text-xs font-medium rounded-md bg-brand-green/10 text-brand-green border border-brand-green/20 hover:bg-brand-green/20">
                                            Lihat
                                        </a>
                                        <x-secondary-button type="button"
                                            wire:click="openUploadFotoModal('{{ $r->src }}', {{ $r->dtl_no }}, {{ $r->ref_no }})"
                                            class="text-xs">Replace</x-secondary-button>
                                    @else
                                        <x-primary-button type="button"
                                            wire:click="openUploadFotoModal('{{ $r->src }}', {{ $r->dtl_no }}, {{ $r->ref_no }})"
                                            class="text-xs">Upload Foto</x-primary-button>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-center whitespace-nowrap">
                                    @if ($r->rad_upload_pdf)
                                        <a href="{{ asset('storage/' . $r->rad_upload_pdf) }}" target="_blank"
                                            class="inline-flex items-center gap-1 px-2 py-1 text-xs font-medium rounded-md bg-brand-green/10 text-brand-green border border-brand-green/20 hover:bg-brand-green/20">
                                            Lihat
                                        </a>
                                        <x-secondary-button type="button"
                                            wire:click="openUploadPdfModal('{{ $r->src }}', {{ $r->dtl_no }}, {{ $r->ref_no }})"
                                            class="text-xs">Replace</x-secondary-button>
                                    @else
                                        <x-primary-button type="button"
                                            wire:click="openUploadPdfModal('{{ $r->src }}', {{ $r->dtl_no }}, {{ $r->ref_no }})"
                                            class="text-xs">Upload Hasil</x-primary-button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-4 py-10 text-sm text-center text-gray-400 dark:text-gray-600">
                                    Tidak ada order radiologi.
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
        {{-- MODAL: UPLOAD FOTO RADIOLOGI                 --}}
        {{-- ============================================ --}}
        <x-modal name="rad-upload-foto" size="lg" focusable>
            <div>
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                    <h2 class="text-lg font-semibold">Upload Foto Radiologi</h2>
                    <p class="text-xs text-gray-500">Format PDF / JPG / PNG, maks 10 MB.</p>
                </div>
                <div class="px-6 py-5 space-y-4">
                    <div>
                        <x-input-label value="File Foto" required />
                        <input type="file" wire:model="fotoFile" accept="application/pdf,image/jpeg,image/png"
                            class="block w-full mt-1 text-sm text-gray-600 file:mr-3 file:py-2 file:px-4 file:rounded file:border-0 file:text-sm file:font-semibold file:bg-brand-green/10 file:text-brand-green hover:file:bg-brand-green/20" />
                        @error('fotoFile')
                            <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                        @enderror
                        <div wire:loading wire:target="fotoFile" class="mt-2 text-xs text-gray-500">Memuat file...</div>
                    </div>
                </div>
                <div class="flex items-center justify-end gap-2 px-6 py-3 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/40">
                    <x-secondary-button type="button" wire:click="closeUploadFotoModal">Batal</x-secondary-button>
                    <x-primary-button type="button" wire:click="uploadFoto" wire:loading.attr="disabled" wire:target="uploadFoto,fotoFile">
                        <span wire:loading.remove wire:target="uploadFoto">Upload</span>
                        <span wire:loading wire:target="uploadFoto"><x-loading /> Uploading...</span>
                    </x-primary-button>
                </div>
            </div>
        </x-modal>

        {{-- ============================================ --}}
        {{-- MODAL: UPLOAD PDF HASIL BACAAN               --}}
        {{-- ============================================ --}}
        <x-modal name="rad-upload-pdf" size="lg" focusable>
            <div>
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                    <h2 class="text-lg font-semibold">Upload Hasil Bacaan Radiologi</h2>
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
                    <x-secondary-button type="button" wire:click="closeUploadPdfModal">Batal</x-secondary-button>
                    <x-primary-button type="button" wire:click="uploadPdf" wire:loading.attr="disabled" wire:target="uploadPdf,pdfFile">
                        <span wire:loading.remove wire:target="uploadPdf">Upload</span>
                        <span wire:loading wire:target="uploadPdf"><x-loading /> Uploading...</span>
                    </x-primary-button>
                </div>
            </div>
        </x-modal>

    </div>
</div>
