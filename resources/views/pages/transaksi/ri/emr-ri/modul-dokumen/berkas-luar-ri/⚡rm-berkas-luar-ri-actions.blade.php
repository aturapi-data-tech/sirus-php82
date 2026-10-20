<?php
// resources/views/pages/transaksi/ri/emr-ri/modul-dokumen/berkas-luar-ri/rm-berkas-luar-ri-actions.blade.php

use Livewire\Component;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Carbon\Carbon;
use App\Http\Traits\Txn\Ri\EmrRITrait;
use App\Http\Traits\Concerns\WithRenderVersioningTrait;
use App\Support\BerkasLuar;

/**
 * Berkas Scan Luar RI — upload berkas dari luar dokumen SIRUS (surat rujukan, hasil pemeriksaan
 * luar, resume RS lain, kartu identitas, …) ke kunjungan ini. Bukan dokumen bertanda tangan:
 * tidak ada draft/TTD/kunci; satu entri = satu berkas. Node JSON `berkasLuarRI`.
 */
new class extends Component {
    use EmrRITrait, WithRenderVersioningTrait, WithFileUploads;

    public bool $isFormLocked = false;
    public ?string $riHdrNo = null;
    public bool $disabled = false;

    public array $renderVersions = [];
    protected array $renderAreas = ['modal-berkas-luar-ri'];

    public array $berkasList = [];

    // ── Form upload ──
    public $fileBerkas = null;
    public array $formBerkas = [
        'jenis' => '',
        'tglBerkas' => '',
        'keterangan' => '',
    ];

    /** Layar aktif di modal: 'daftar' atau 'form' (upload berkas baru). */
    public string $layar = 'daftar';

    /** Alamat berkas yang sedang dilihat (route files.show); '' = tidak ada. */
    public string $urlLihat = '';
    public string $judulLihat = '';

    private function muatDariDokumen(array $data): void
    {
        $this->berkasList = is_array($data[BerkasLuar::NODE['RI']] ?? null) ? $data[BerkasLuar::NODE['RI']] : [];
    }

    public function mount(?string $riHdrNo = null, bool $disabled = false): void
    {
        $this->riHdrNo = $riHdrNo ?: null;
        $this->disabled = $disabled;
        $this->registerAreas(['modal-berkas-luar-ri']);

        if ($this->riHdrNo) {
            $data = $this->findDataRI($this->riHdrNo);
            if ($data) {
                $this->muatDariDokumen($data);
                $this->isFormLocked = $this->checkEmrRIStatus($this->riHdrNo) || $disabled;
            }
        }
    }

    public function openModal(): void
    {
        if (!$this->riHdrNo || $this->disabled) {
            return;
        }

        $data = $this->findDataRI($this->riHdrNo);
        if (!$data) {
            $this->dispatch('toast', type: 'error', message: 'Data RI tidak ditemukan.');
            return;
        }

        $this->muatDariDokumen($data);
        $this->isFormLocked = $this->checkEmrRIStatus($this->riHdrNo) || $this->disabled;
        $this->resetFormBerkas();
        $this->incrementVersion('modal-berkas-luar-ri');

        $this->dispatch('open-modal', name: "rm-berkas-luar-ri-{$this->riHdrNo}");
    }

    public function closeModal(): void
    {
        $this->resetFormBerkas();
        $this->dispatch('close-modal', name: "rm-berkas-luar-ri-{$this->riHdrNo}");
    }

    public function diForm(): bool
    {
        return $this->layar === 'form';
    }

    public function tambahEntri(): void
    {
        if ($this->isFormLocked) {
            return;
        }
        $this->resetFormBerkas();
        $this->layar = 'form';
    }

    public function kembaliKeDaftar(): void
    {
        $this->resetFormBerkas();
    }

    private function resetFormBerkas(): void
    {
        $this->reset(['fileBerkas', 'formBerkas']);
        $this->resetValidation();
        $this->layar = 'daftar';
    }

    /* ===============================
     | UPLOAD
     =============================== */
    protected function rules(): array
    {
        return [
            'fileBerkas' => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'formBerkas.jenis' => 'required|in:' . implode(',', BerkasLuar::JENIS),
            'formBerkas.tglBerkas' => 'nullable|date_format:d/m/Y',
            'formBerkas.keterangan' => 'required|string|max:255',
        ];
    }

    protected function messages(): array
    {
        return [
            'fileBerkas.required' => 'Berkas wajib dipilih.',
            'fileBerkas.mimes' => 'Format harus PDF, JPG, atau PNG.',
            'fileBerkas.max' => 'Ukuran berkas maksimal 10 MB.',
            'formBerkas.jenis.required' => 'Jenis berkas wajib dipilih.',
            'formBerkas.jenis.in' => 'Jenis berkas tidak dikenal.',
            'formBerkas.tglBerkas.date_format' => 'Format tanggal dd/mm/yyyy.',
            'formBerkas.keterangan.required' => 'Keterangan wajib diisi.',
            'formBerkas.keterangan.max' => 'Keterangan maksimal 255 karakter.',
        ];
    }

    public function uploadBerkas(): void
    {
        if ($this->isFormLocked || !$this->riHdrNo) {
            $this->dispatch('toast', type: 'error', message: 'EMR terkunci, berkas tidak dapat diupload.');
            return;
        }

        $this->validate();

        // Nama berkas: waktu + 4 huruf acak — dua upload di detik yang sama tidak saling menimpa.
        $ekstensi = strtolower($this->fileBerkas->getClientOriginalExtension() ?: $this->fileBerkas->extension());
        $namaBerkas = Carbon::now()->format('dmYHis') . '-' . Str::lower(Str::random(4)) . '.' . $ekstensi;
        $sekarang = now()->timezone(config('app.timezone'))->format('d/m/Y H:i:s');

        try {
            $this->fileBerkas->storeAs(BerkasLuar::FOLDER_UPLOAD, $namaBerkas, 'local');

            DB::transaction(function () use ($namaBerkas, $sekarang) {
                $this->lockRIRow($this->riHdrNo);

                $data = $this->findDataRI($this->riHdrNo) ?? [];
                if (empty($data)) {
                    throw new \RuntimeException('Data RI tidak ditemukan.');
                }

                $data[BerkasLuar::NODE['RI']][] = [
                    'file' => $namaBerkas,
                    'namaAsli' => $this->fileBerkas->getClientOriginalName(),
                    'jenis' => $this->formBerkas['jenis'],
                    'tglBerkas' => $this->formBerkas['tglBerkas'],
                    'keterangan' => $this->formBerkas['keterangan'],
                    'tglUpload' => $sekarang,
                    'penanggungJawab' => [
                        'userLog' => auth()->user()->myuser_name,
                        'userLogDate' => $sekarang,
                        'userLogCode' => auth()->user()->myuser_code,
                    ],
                ];

                $this->updateJsonRI($this->riHdrNo, $data);
                $this->muatDariDokumen($data);

                $this->appendAdminLogRI((int) $this->riHdrNo, 'Upload berkas scan luar RI — ' . $this->formBerkas['jenis'] . ': ' . $this->formBerkas['keterangan'] . ' (' . $namaBerkas . ')', 'MR');
            });
        } catch (\Throwable $e) {
            // JSON gagal tersimpan → berkas yang terlanjur ditulis dibuang lagi supaya tidak yatim.
            Storage::disk('local')->delete(BerkasLuar::FOLDER_UPLOAD . '/' . $namaBerkas);
            $this->dispatch('toast', type: 'error', message: 'Gagal upload: ' . $e->getMessage());
            return;
        }

        $this->resetFormBerkas();
        $this->dispatch('refresh-modul-dokumen-ri-data', riHdrNo: $this->riHdrNo);
        $this->dispatch('toast', type: 'success', message: 'Berkas berhasil diupload.');
    }

    /* ===============================
     | HAPUS
     =============================== */
    public function hapusBerkas(string $file): void
    {
        if (!auth()->user()?->can('dokumen.hapus')) {
            $this->dispatch('toast', type: 'error', message: 'Anda tidak berwenang menghapus berkas.');
            return;
        }
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'EMR terkunci, berkas tidak dapat dihapus.');
            return;
        }

        try {
            DB::transaction(function () use ($file) {
                $this->lockRIRow($this->riHdrNo);

                $data = $this->findDataRI($this->riHdrNo) ?? [];
                $daftar = collect($data[BerkasLuar::NODE['RI']] ?? []);
                $berkas = $daftar->firstWhere('file', $file);
                if (!$berkas) {
                    throw new \RuntimeException('Berkas tidak ditemukan di kunjungan ini.');
                }

                $data[BerkasLuar::NODE['RI']] = $daftar->reject(fn($entri) => ($entri['file'] ?? '') === $file)->values()->all();
                $this->updateJsonRI($this->riHdrNo, $data);
                $this->muatDariDokumen($data);

                $namaBerkas = basename($file);
                Storage::disk('local')->delete([BerkasLuar::FOLDER_UPLOAD . '/' . $namaBerkas, BerkasLuar::FOLDER_MOUNT . '/' . $namaBerkas]);

                $this->appendAdminLogRI((int) $this->riHdrNo, 'Hapus berkas scan luar RI — ' . ($berkas['jenis'] ?? '-') . ': ' . ($berkas['keterangan'] ?? '-') . ' (' . $namaBerkas . ')', 'MR');
            });
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menghapus: ' . $e->getMessage());
            return;
        }

        $this->dispatch('refresh-modul-dokumen-ri-data', riHdrNo: $this->riHdrNo);
        $this->dispatch('toast', type: 'success', message: 'Berkas dihapus.');
    }

    /* ===============================
     | LIHAT
     =============================== */
    public function lihatBerkas(string $file): void
    {
        $url = BerkasLuar::url($file);
        if (!$url) {
            $this->dispatch('toast', type: 'error', message: 'Berkas tidak ditemukan di server.');
            return;
        }

        $berkas = collect($this->berkasList)->firstWhere('file', $file) ?? [];
        $this->urlLihat = $url;
        $this->judulLihat = trim(($berkas['jenis'] ?? '') . ' — ' . ($berkas['keterangan'] ?? ''), ' —');
        $this->dispatch('open-modal', name: "lihat-berkas-luar-ri-{$this->riHdrNo}");
    }

    public function tutupLihatBerkas(): void
    {
        $this->reset(['urlLihat', 'judulLihat']);
        $this->dispatch('close-modal', name: "lihat-berkas-luar-ri-{$this->riHdrNo}");
    }

    /** Daftar urut tanggal upload terbaru di atas. */
    public function berkasUrut(): array
    {
        return collect($this->berkasList)
            ->sortByDesc(fn($entri) => strtotime(strtr($entri['tglUpload'] ?? '', '/', '-')))
            ->values()
            ->all();
    }
};
?>

<div>
    {{-- ══ KARTU DI TAB ══ --}}
    <x-modul-dokumen.kartu judul="Berkas Scan Luar" :jumlah="count($berkasList)" satuan="berkas"
        tombol="Kelola Berkas" :nonaktif="$disabled || !$riHdrNo">
        <x-slot:deskripsi>Berkas dari luar dokumen SIRUS yang di-scan lalu di-upload — surat rujukan, hasil pemeriksaan luar, resume RS lain, kartu identitas, surat keterangan.</x-slot:deskripsi>
        <div class="overflow-x-auto border rounded-2xl border-hairline dark:border-gray-700">
            <table class="min-w-full text-sm">
                <thead class="bg-surface-card dark:bg-gray-800">
                    <tr class="text-xs font-semibold tracking-wide text-left uppercase text-muted dark:text-gray-300">
                        <th class="px-3 py-2 border-b">Jenis</th>
                        <th class="px-3 py-2 border-b">Keterangan</th>
                        <th class="px-3 py-2 border-b">Diupload</th>
                        <th class="px-3 py-2 border-b">Petugas</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse (array_slice($this->berkasUrut(), 0, 3) as $berkas)
                        <tr class="border-b border-hairline dark:border-gray-700">
                            <td class="px-3 py-2 font-medium text-ink dark:text-gray-200">{{ $berkas['jenis'] ?? '-' }}</td>
                            <td class="px-3 py-2 text-muted dark:text-gray-400">{{ Str::limit($berkas['keterangan'] ?? '-', 60) }}</td>
                            <td class="px-3 py-2 tabular-nums text-muted dark:text-gray-400">{{ $berkas['tglUpload'] ?? '-' }}</td>
                            <td class="px-3 py-2 text-muted dark:text-gray-400">{{ $berkas['penanggungJawab']['userLog'] ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-3 py-6 text-center text-muted-soft">Belum ada berkas diupload</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-modul-dokumen.kartu>

    {{-- ══ MODAL KELOLA BERKAS ══ --}}
    <x-modal name="rm-berkas-luar-ri-{{ $riHdrNo ?? 'init' }}" size="full" height="full" focusable>
        <div class="flex flex-col min-h-full"
            wire:key="{{ $this->renderKey('modal-berkas-luar-ri', [$riHdrNo ?? 'new']) }}">
            <x-modul-dokumen.header judul="Berkas Scan Luar"
                ikon="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"
                jalur="RI" :jumlah="count($berkasList)" :readOnly="$isFormLocked">
                Upload berkas dari luar (hasil scan) ke kunjungan ini — tampil juga di Rekam Medis Open.
            </x-modul-dokumen.header>

            <div class="px-4 pt-2">
                <livewire:pages::transaksi.ri.display-pasien-ri.display-pasien-ri :riHdrNo="$riHdrNo"
                    wire:key="berkas-luar-ri-display-pasien-{{ $riHdrNo ?? 'init' }}" />
            </div>

            <div class="flex-1 px-4 py-4 bg-surface-soft/70 dark:bg-gray-950/20">
                <div class="max-w-full mx-auto space-y-4">
                    <div
                        class="{{ $this->diForm() ? 'p-6 sm:p-8 bg-canvas border border-hairline shadow-sm rounded-2xl dark:bg-gray-900 dark:border-gray-700' : '' }} space-y-6">

                        @if ($isFormLocked)
                            <x-modul-dokumen.banner jenis="terkunci" />
                        @endif

                        @if ($this->diForm())
                            {{-- ══ FORM UPLOAD ══ --}}
                            <fieldset @disabled($isFormLocked) class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div>
                                    <x-input-label for="berkas-luar-jenis" value="Jenis Berkas" :required="true" />
                                    <x-select-input id="berkas-luar-jenis" wire:model="formBerkas.jenis" class="mt-1"
                                        :error="$errors->has('formBerkas.jenis')">
                                        <option value="">— Pilih jenis berkas —</option>
                                        @foreach (\App\Support\BerkasLuar::JENIS as $jenis)
                                            <option value="{{ $jenis }}">{{ $jenis }}</option>
                                        @endforeach
                                    </x-select-input>
                                    <x-input-error :messages="$errors->get('formBerkas.jenis')" class="mt-1" />
                                </div>

                                <div>
                                    <x-input-label for="berkas-luar-tgl" value="Tanggal Berkas (dd/mm/yyyy)" />
                                    <x-text-input id="berkas-luar-tgl" wire:model="formBerkas.tglBerkas"
                                        placeholder="Tanggal yang tertera di berkas, boleh kosong" maxlength="10"
                                        class="block w-full mt-1" :error="$errors->has('formBerkas.tglBerkas')" />
                                    <x-input-error :messages="$errors->get('formBerkas.tglBerkas')" class="mt-1" />
                                </div>

                                <div class="md:col-span-2">
                                    <x-input-label for="berkas-luar-keterangan" value="Keterangan" :required="true" />
                                    <x-text-input id="berkas-luar-keterangan" wire:model="formBerkas.keterangan"
                                        placeholder="Mis. Surat rujukan Puskesmas Ngunut, hasil CT Scan RS X" maxlength="255"
                                        class="block w-full mt-1" :error="$errors->has('formBerkas.keterangan')" />
                                    <x-input-error :messages="$errors->get('formBerkas.keterangan')" class="mt-1" />
                                </div>

                                <div class="md:col-span-2">
                                    <x-file-upload name="fileBerkas" label="Berkas (PDF / JPG / PNG, maks. 10 MB)"
                                        accept="application/pdf,image/jpeg,image/png" :required="true"
                                        :disabled="$isFormLocked" loading-style="bar" />
                                </div>
                            </fieldset>
                        @else
                            {{-- ══ DAFTAR BERKAS ══ --}}
                            <x-modul-dokumen.tabel-daftar :kolom="['', 'Jenis', 'Keterangan', 'Tanggal Berkas', 'Diupload', 'Aksi' => 'text-center']">
                                @forelse ($this->berkasUrut() as $berkas)
                                    @php $tersedia = \App\Support\BerkasLuar::url($berkas['file'] ?? '') !== null; @endphp
                                    <tbody x-data="{ open: false }" class="border-b border-hairline dark:border-gray-700"
                                        wire:key="berkas-luar-ri-{{ $berkas['file'] ?? $loop->index }}">
                                        <tr @click="open = !open" class="cursor-pointer hover:bg-surface-soft dark:hover:bg-gray-800">
                                            <td class="px-2 py-3 text-center align-middle">
                                                <svg class="w-4 h-4 mx-auto transition-transform text-muted" :class="{ 'rotate-90': open }"
                                                    fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                                </svg>
                                            </td>
                                            <td class="px-4 py-3 font-semibold align-middle text-ink dark:text-gray-100">
                                                {{ $berkas['jenis'] ?? '-' }}
                                                @unless ($tersedia)
                                                    <x-badge variant="danger" class="ml-1">Berkas tidak ditemukan</x-badge>
                                                @endunless
                                            </td>
                                            <td class="px-4 py-3 align-middle text-body dark:text-gray-300">{{ Str::limit($berkas['keterangan'] ?? '-', 70) }}</td>
                                            <td class="px-4 py-3 text-sm align-middle tabular-nums text-muted dark:text-gray-400">{{ ($berkas['tglBerkas'] ?? '') ?: '-' }}</td>
                                            <td class="px-4 py-3 text-sm align-middle text-muted dark:text-gray-400">
                                                <div class="tabular-nums">{{ $berkas['tglUpload'] ?? '-' }}</div>
                                                <div>{{ $berkas['penanggungJawab']['userLog'] ?? '-' }}</div>
                                            </td>
                                            <td class="px-4 py-3 text-center align-middle whitespace-nowrap" @click.stop>
                                                <x-modul-dokumen.aksi-entri kunci="{{ $berkas['file'] ?? '' }}" :final="true"
                                                    :terkunci="$isFormLocked" :bisaDiubah="!empty($berkas['file'])"
                                                    lanjut="" cetak="" bukaKunci="" lihat="{{ $tersedia ? 'lihatBerkas' : '' }}"
                                                    hapus="hapusBerkas" judulLihat="Lihat berkas"
                                                    konfirmasiHapus="Yakin hapus berkas ini? Berkasnya ikut terhapus dari server." />
                                            </td>
                                        </tr>
                                        <tr x-show="open" x-cloak>
                                            <td colspan="6" class="px-4 py-4 bg-surface-soft/60 dark:bg-gray-950/30">
                                                <dl class="grid grid-cols-1 gap-x-8 gap-y-3 md:grid-cols-2">
                                                    <div class="md:col-span-2">
                                                        <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Keterangan</dt>
                                                        <dd class="mt-0.5 whitespace-pre-line text-ink dark:text-gray-200">{{ $berkas['keterangan'] ?? '-' }}</dd>
                                                    </div>
                                                    <div>
                                                        <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Nama Berkas Asli</dt>
                                                        <dd class="mt-0.5 break-all text-ink dark:text-gray-200">{{ ($berkas['namaAsli'] ?? '') ?: '-' }}</dd>
                                                    </div>
                                                    <div>
                                                        <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Diupload Oleh</dt>
                                                        <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $berkas['penanggungJawab']['userLog'] ?? '-' }} — <span class="tabular-nums">{{ $berkas['tglUpload'] ?? '-' }}</span></dd>
                                                    </div>
                                                </dl>
                                            </td>
                                        </tr>
                                    </tbody>
                                @empty
                                    <x-modul-dokumen.baris-kosong :kolom="6" />
                                @endforelse
                            </x-modul-dokumen.tabel-daftar>
                        @endif
                    </div>
                </div>
            </div>

            {{-- FOOTER — bukan x-modul-dokumen.footer: modul ini tanpa draft/kunci, kalimat & tombolnya beda. --}}
            <div class="sticky bottom-0 z-10 px-6 py-4 border-t bg-canvas border-hairline dark:bg-gray-900 dark:border-gray-700">
                @if ($this->diForm())
                    <div class="flex flex-wrap items-center justify-end gap-3">
                        <p class="flex items-center gap-1.5 mr-auto text-sm text-muted dark:text-gray-400">
                            Pilih jenis, isi keterangan, pilih berkas, lalu <strong>Upload Berkas</strong>.
                        </p>
                        <x-secondary-button type="button" wire:click="kembaliKeDaftar">Kembali ke Daftar</x-secondary-button>
                        @unless ($isFormLocked)
                            <x-primary-button type="button" wire:click="uploadBerkas" wire:loading.attr="disabled"
                                wire:target="uploadBerkas,fileBerkas" class="gap-2 min-w-[160px] justify-center">
                                <span wire:loading.remove wire:target="uploadBerkas,fileBerkas">Upload Berkas</span>
                                <span wire:loading wire:target="uploadBerkas,fileBerkas"><x-loading class="w-4 h-4" /> Mengupload...</span>
                            </x-primary-button>
                        @endunless
                    </div>
                @else
                    <div class="flex flex-wrap items-center justify-end gap-2">
                        <p class="flex items-center gap-1.5 mr-auto text-sm text-muted dark:text-gray-400">
                            Satu baris = satu berkas. <strong>Upload Berkas Baru</strong> untuk menambah.
                        </p>
                        <x-secondary-button type="button" wire:click="closeModal">Tutup</x-secondary-button>
                        @unless ($isFormLocked)
                            <x-primary-button type="button" wire:click="tambahEntri" wire:target="tambahEntri"
                                wire:loading.attr="disabled" class="gap-1.5 min-w-[150px] justify-center">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                </svg>
                                Upload Berkas Baru
                            </x-primary-button>
                        @endunless
                    </div>
                @endif
            </div>
        </div>
    </x-modal>

    {{-- ══ MODAL LIHAT BERKAS ══ --}}
    <x-modal name="lihat-berkas-luar-ri-{{ $riHdrNo ?? 'init' }}" size="full" height="full" focusable>
        <div class="flex flex-col h-[calc(100vh-4rem)]" wire:key="lihat-berkas-luar-ri-{{ $urlLihat }}">
            <x-modul-dokumen.header judul="Berkas Scan Luar" tutup="tutupLihatBerkas"
                ikon="M15 12a3 3 0 11-6 0 3 3 0 016 0z M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z">
                {{ $judulLihat ?: 'Pratinjau berkas' }}
            </x-modul-dokumen.header>
            <div class="flex-1 min-h-0 bg-surface-soft dark:bg-gray-950">
                @if ($urlLihat && \App\Support\BerkasLuar::gambar($urlLihat))
                    <x-pratinjau-gambar :src="$urlLihat" />
                @elseif ($urlLihat)
                    <x-pratinjau-pdf :src="$urlLihat" />
                @endif
            </div>
            <div class="px-6 py-4 border-t shrink-0 bg-canvas border-hairline dark:bg-gray-900 dark:border-gray-700">
                <div class="flex justify-end">
                    <x-secondary-button type="button" wire:click="tutupLihatBerkas">Tutup</x-secondary-button>
                </div>
            </div>
        </div>
    </x-modal>
</div>
