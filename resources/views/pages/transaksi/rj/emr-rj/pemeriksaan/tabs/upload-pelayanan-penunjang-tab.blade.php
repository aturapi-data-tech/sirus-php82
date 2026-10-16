<div class="space-y-4">

    @role(['Perawat', 'Admin'])
        <div class="p-4 bg-canvas border border-hairline rounded-xl shadow-sm dark:bg-gray-900 dark:border-gray-700">
            <h3 class="mb-3 text-base font-semibold text-body dark:text-gray-300">
                Upload Hasil Penunjang
            </h3>
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-1">

                {{-- File PDF --}}
                <x-file-upload
                    name="filePDF"
                    label="File (PDF/JPG)"
                    accept="application/pdf,image/jpeg"
                    :disabled="$isFormLocked"
                    loading-style="bar"
                    :show-error="false"
                />

                {{-- Keterangan --}}
                <div>
                    <x-input-label for="descPDF" :value="__('Keterangan')" />
                    <x-text-input id="descPDF" type="text" wire:model.live.debounce.400ms="descPDF"
                        placeholder="Keterangan hasil penunjang..." maxlength="255" :disabled="$isFormLocked" :error="__($errors->has('descPDF'))"
                        class="mt-1 block w-full" />
                </div>

                {{-- Tombol Upload --}}
                <div class="flex items-end">
                    <div wire:loading wire:target="uploadHasilPenunjang,filePDF" class="w-full">
                        <x-primary-button disabled class="w-full justify-center">
                            <svg class="w-4 h-4 animate-spin mr-2" xmlns="http://www.w3.org/2000/svg" fill="none"
                                viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                    stroke-width="4" />
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z" />
                            </svg>
                            Mengupload...
                        </x-primary-button>
                    </div>
                    <div wire:loading.remove wire:target="uploadHasilPenunjang,filePDF" class="w-full">
                        <x-primary-button type="button" wire:click="uploadHasilPenunjang" :disabled="$isFormLocked"
                            class="w-full justify-center">
                            Upload Penunjang
                        </x-primary-button>
                    </div>
                </div>

            </div>

            {{-- Row error — grid sama persis agar posisi sejajar dengan inputnya --}}
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                <div>
                    <x-input-error :messages="$errors->get('filePDF')" class="mt-1" />
                </div>
                <div>
                    <x-input-error :messages="$errors->get('descPDF')" class="mt-1" />
                </div>
                <div>{{-- kolom ketiga kosong, placeholder agar grid tetap 3 kolom --}}</div>
            </div>
        </div>
    @endrole

    {{-- ── TABEL DAFTAR FILE ───────────────────────────────────── --}}
    <div
        class="overflow-x-auto rounded-2xl border border-hairline bg-canvas dark:border-gray-700 dark:bg-gray-900">
        <table class="min-w-full text-sm">
            <thead class="bg-surface-card dark:bg-gray-800">
                <tr class="text-xs font-semibold tracking-wide text-left uppercase text-muted dark:text-gray-300">
                    <th class="px-4 py-3 whitespace-nowrap border-b border-hairline dark:border-gray-700 w-44">Tgl Upload</th>
                    <th class="px-4 py-3 whitespace-nowrap border-b border-hairline dark:border-gray-700">Keterangan</th>
                    <th class="px-4 py-3 whitespace-nowrap border-b border-hairline dark:border-gray-700 w-28 text-center">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($pemeriksaan['uploadHasilPenunjang'] ?? [] as $item)
                    <tr class="border-b border-hairline dark:border-gray-700 hover:bg-surface-soft dark:hover:bg-gray-800/60">

                        <td class="px-4 py-3 whitespace-nowrap text-body dark:text-gray-300">
                            {{ $item['tglUpload'] ?? '-' }}
                        </td>

                        <td class="px-4 py-3 text-body dark:text-gray-300">
                            {{ $item['desc'] ?? '-' }}
                        </td>

                        <td class="px-4 py-3">
                            <div class="flex items-center justify-center gap-2">

                                {{-- Tombol Lihat PDF
                                     x-icon-button: icon only, aksi di row tabel --}}
                                @role(['Perawat', 'Admin', 'Dokter'])
                                    <x-icon-button wire:click="openModalViewPenunjang('{{ $item['file'] ?? '' }}')"
                                        title="Lihat PDF">
                                        <svg class="w-4 h-4" aria-hidden="true" xmlns="http://www.w3.org/2000/svg"
                                            width="24" height="24" fill="currentColor" viewBox="0 0 24 24">
                                            <path fill-rule="evenodd" d="M5 6a1 1 0 0 1 1-1h12a1 1 0 1 1 0 2H6a1 1 0 0 1-1-1Zm0
                                                                                     12a1 1 0 0 1 1-1h12a1 1 0 1 1 0 2H6a1 1 0 0 1-1-1Zm1.65-9.76A1
                                                                                     1 0 0 0 5 9v6a1 1 0 0 0 1.65.76l3.5-3a1 1 0 0 0
                                                                                     0-1.52l-3.5-3ZM12 10a1 1 0 0 1 1-1h5a1 1 0 1 1 0
                                                                                     2h-5a1 1 0 0 1-1-1Zm0 4a1 1 0 0 1 1-1h5a1 1 0 1 1
                                                                                     0 2h-5a1 1 0 0 1-1-1Z"
                                                clip-rule="evenodd" />
                                        </svg>
                                    </x-icon-button>
                                @endrole

                                {{-- Tombol Hapus
                                     x-confirm-button: row tabel, trigger confirm, aksi irreversible --}}
                                @role(['Perawat', 'Admin'])
                                    <x-confirm-button variant="danger" :action="'deleteHasilPenunjang(\'' . ($item['file'] ?? '') . '\')'" title="Hapus File Penunjang"
                                        message="Yakin ingin menghapus file {{ $item['desc'] ?? '' }}?"
                                        confirmText="Ya, hapus" cancelText="Batal" :disabled="$isFormLocked">
                                        <svg class="w-4 h-4" aria-hidden="true" xmlns="http://www.w3.org/2000/svg"
                                            fill="currentColor" viewBox="0 0 18 20">
                                            <path d="M17 4h-4V2a2 2 0 0 0-2-2H7a2 2 0 0 0-2 2v2H1a1 1
                                             0 0 0 0 2h1v12a2 2 0 0 0 2 2h10a2 2 0 0 0
                                             2-2V6h1a1 1 0 1 0 0-2ZM7 2h4v2H7V2Zm1 14a1 1 0 1
                                             1-2 0V8a1 1 0 0 1 2 0v8Zm4 0a1 1 0 0 1-2 0V8a1
                                             1 0 0 1 2 0v8Z" />
                                        </svg>
                                    </x-confirm-button>
                                @endrole

                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="px-4 py-8 text-center text-muted-soft">
                            Belum ada file penunjang yang diupload.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- ── MODAL LIHAT PDF ─────────────────────────────────────── --}}
    <x-modal name="view-penunjang-pdf" size="full" height="full" focusable>

        <div class="flex flex-col h-[calc(100vh-4rem)]" wire:key="view-penunjang-pdf-{{ $viewFilePDF }}">

            {{-- HEADER --}}
            <x-modul-dokumen.header judul="Hasil Penunjang"
                ikon="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
                tutup="closeModalViewPenunjang">
                Preview file hasil penunjang pasien (PDF/JPG)
            </x-modul-dokumen.header>

            {{-- BODY — iframe PDF --}}
            <div class="flex-1 min-h-0 bg-surface-soft dark:bg-gray-950">
                @if ($viewFilePDF && (str_starts_with($viewFilePDF, 'data:image/') || preg_match('/\.(jpe?g|png)$/i', $viewFilePDF)))
                    {{-- JPG: zoom + rotasi; iframe menaruh gambar di pojok kiri atas ukuran asli --}}
                    <x-pratinjau-gambar :src="$viewFilePDF" />
                @elseif ($viewFilePDF)
                    <x-pratinjau-pdf :src="$viewFilePDF" />
                @endif
            </div>

            {{-- FOOTER --}}
            <div
                class="shrink-0 px-6 py-4 bg-canvas border-t border-hairline
                    dark:bg-gray-900 dark:border-gray-700">
                <div class="flex items-center justify-between gap-3">
                    <p class="text-base text-muted dark:text-gray-400">
                        File dibuka dalam mode preview — tidak dapat diedit.
                    </p>
                    <x-secondary-button type="button" wire:click="closeModalViewPenunjang">
                        Tutup
                    </x-secondary-button>
                </div>
            </div>

        </div>

    </x-modal>

</div>
