                        {{-- ══ DAFTAR PENGKAJIAN TERSIMPAN (expandable) ══ --}}
                        @if (count($preOpList ?? []))
                            <div class="mt-6">
                                <h3
                                    class="text-base font-semibold text-body dark:text-gray-300 pb-2 border-b border-hairline-soft dark:border-gray-800 mb-3">
                                    Daftar Pengkajian Tersimpan
                                </h3>
                                <p class="mb-3 text-xs italic text-muted-soft">Klik baris untuk lihat detail lengkap</p>
                                <div class="overflow-x-auto">
                                    <table class="min-w-full text-base border border-hairline rounded-lg dark:border-gray-700">
                                        <thead class="bg-surface-soft dark:bg-gray-800">
                                            <tr class="text-left text-sm font-semibold tracking-wide uppercase text-muted dark:text-gray-300">
                                                <th class="w-8 px-2 py-3 border-b"></th>
                                                <th class="px-4 py-3 border-b">Tanggal</th>
                                                <th class="px-4 py-3 border-b">Rencana Operasi</th>
                                                <th class="px-4 py-3 border-b">TTD (3 Pihak)</th>
                                                <th class="px-4 py-3 text-center border-b">Status</th>
                                                <th class="px-4 py-3 text-center border-b">Aksi</th>
                                            </tr>
                                        </thead>
                                        @foreach (array_reverse($preOpList) as $entry)
                                            @php
                                                $isFinal = $this->entryIsFinal($entry);
                                                $rowKey = $entry['createdAt'] ?? '';
                                                $entryTtdCount = collect(['ttdPerawatRuangan', 'ttdPerawatKamarBedah', 'ttdDokterOperator'])->filter(fn($k) => !empty($entry[$k]))->count();
                                            @endphp
                                            <tbody x-data="{ open: false }" class="border-b border-hairline dark:border-gray-700">
                                                <tr @click="open = !open"
                                                    class="cursor-pointer hover:bg-surface-soft dark:hover:bg-gray-800 {{ $editingKey && $editingKey === $rowKey ? 'bg-brand-lime/10 dark:bg-brand-lime/5' : '' }}">
                                                    <td class="px-2 py-3 text-center align-middle">
                                                        <svg class="w-4 h-4 mx-auto transition-transform text-muted" :class="{ 'rotate-90': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                                        </svg>
                                                    </td>
                                                    <td class="px-4 py-3 font-semibold align-middle text-ink dark:text-gray-100">
                                                        {{ $entry['createdAt'] ?: '-' }}
                                                    </td>
                                                    <td class="px-4 py-3 align-middle text-muted dark:text-gray-300">
                                                        {{ $entry['rencanaOperasi'] ? Str::limit($entry['rencanaOperasi'], 45) : '-' }}
                                                    </td>
                                                    <td class="px-4 py-3 align-middle text-muted dark:text-gray-300">
                                                        <x-badge :variant="$entryTtdCount === 3 ? 'success' : ($entryTtdCount > 0 ? 'warning' : 'danger')">{{ $entryTtdCount }}/3 TTD</x-badge>
                                                    </td>
                                                    <td class="px-4 py-3 text-center align-middle">
                                                        @if ($isFinal)
                                                            <x-badge variant="info">Terkunci</x-badge>
                                                        @else
                                                            <x-badge variant="warning">Draft</x-badge>
                                                        @endif
                                                    </td>
                                                    <td class="px-4 py-3 text-center align-middle" @click.stop>
                                                        <div class="flex flex-col items-center gap-2">
                                                            <div class="flex items-center justify-center gap-2">
                                                            @if (!$isFinal && !$isFormLocked)
                                                                <x-primary-button type="button" wire:click="editEntry('{{ $rowKey }}')" wire:loading.attr="disabled" wire:target="editEntry('{{ $rowKey }}')" class="gap-1.5" title="Lanjutkan mengisi entri ini">
                                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                                    </svg>
                                                                    Lanjutkan Pengisian
                                                                </x-primary-button>
                                                            @endif
                                                            @if ($isFinal)
                                                                <x-secondary-button type="button" wire:click="viewEntry('{{ $rowKey }}')" wire:loading.attr="disabled" wire:target="viewEntry('{{ $rowKey }}')" class="gap-1.5" title="Lihat detail (read-only) di form atas">
                                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                                    </svg>
                                                                    Lihat
                                                                </x-secondary-button>
                                                            @endif
                                                            <x-secondary-button type="button" wire:click="cetak('{{ $rowKey }}')"
                                                                wire:loading.attr="disabled" wire:target="cetak('{{ $rowKey }}')" class="gap-1.5" title="Cetak">
                                                                <span wire:loading.remove wire:target="cetak('{{ $rowKey }}')" class="flex items-center gap-1.5">
                                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                                                                    </svg>
                                                                    Cetak
                                                                </span>
                                                                <span wire:loading wire:target="cetak('{{ $rowKey }}')" class="flex items-center gap-1.5"><x-loading class="w-4 h-4" /> Mencetak...</span>
                                                            </x-secondary-button>
                                                            </div>
                                                            @if (!$isFormLocked)
                                                                <div class="flex items-center justify-center gap-2">
                                                                @if ($isFinal)
                                                                    @can('dokumen.bukaKunci')
                                                                        <x-confirm-button action="bukaKunci('{{ $rowKey }}')"
                                                                            title="Buka Kunci Pengkajian Pre Operasi"
                                                                            message="KETIGA TTD (Perawat Ruangan, Perawat Kamar Bedah, Dokter Operator) akan dicabut & entri kembali menjadi Draft — proses TTD diulang dari awal. Lanjutkan?"
                                                                            confirmText="Ya, Buka Kunci" class="gap-1.5">
                                                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                                                    d="M8 11V7a4 4 0 118 0m-8 4h10a2 2 0 012 2v5a2 2 0 01-2 2H8a2 2 0 01-2-2v-5a2 2 0 012-2z" />
                                                                            </svg>
                                                                            Buka Kunci
                                                                        </x-confirm-button>
                                                                    @endcan
                                                                @endif
                                                                @can('dokumen.hapus')
                                                                <x-outline-button type="button" wire:click.prevent="hapus('{{ $rowKey }}')" wire:confirm="Yakin hapus pengkajian ini?"
                                                                    wire:loading.attr="disabled"
                                                                    class="!text-red-600 !bg-red-50 !border-red-200 hover:!bg-red-100 hover:!text-red-700 hover:!border-red-300 dark:!text-red-400 dark:!bg-red-900/20 dark:!border-red-800/30 dark:hover:!bg-red-900/30 dark:hover:!text-red-300"
                                                                    title="Hapus">
                                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                                    </svg>
                                                                </x-outline-button>
                                                                @endcan
                                                                </div>
                                                            @endif
                                                        </div>
                                                    </td>
                                                </tr>

                                                {{-- DETAIL (expand) --}}
                                                <tr x-show="open" x-cloak>
                                                    <td colspan="6" class="px-4 py-4 bg-surface-soft/60 dark:bg-gray-950/30">
                                                        <dl class="grid grid-cols-1 gap-x-8 gap-y-3 md:grid-cols-2">
                                                            <div class="md:col-span-2">
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Diagnosa Pre Operasi</dt>
                                                                <dd class="mt-0.5 whitespace-pre-line text-ink dark:text-gray-200">{{ $entry['diagnosaPreOp'] ?: '-' }}</dd>
                                                            </div>
                                                            <div class="md:col-span-2">
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Rencana Operasi</dt>
                                                                <dd class="mt-0.5 whitespace-pre-line text-ink dark:text-gray-200">{{ $entry['rencanaOperasi'] ?: '-' }}</dd>
                                                            </div>
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Dokter Operator</dt>
                                                                <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['dokterOperator'] ?: '-' }}</dd>
                                                            </div>
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Tanggal / Jam Operasi</dt>
                                                                <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['tanggalOperasi'] ?: '-' }}</dd>
                                                            </div>
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Perjanjian dgn Perawat OK</dt>
                                                                <dd class="mt-0.5 text-ink dark:text-gray-200">{{ ($entry['perjanjianPerawatOk'] ?? '') ?: '-' }}</dd>
                                                            </div>
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Urgensi</dt>
                                                                <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['urgensi'] ?: '-' }}</dd>
                                                            </div>
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Tensi (mmHg)</dt>
                                                                <dd class="mt-0.5 text-ink dark:text-gray-200">{{ ($entry['sistolik'] ?? '') ?: '-' }} / {{ ($entry['diastolik'] ?? '') ?: '-' }}</dd>
                                                            </div>
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Nadi (x/mnt)</dt>
                                                                <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['nadi'] ?: '-' }}</dd>
                                                            </div>
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Nafas (x/mnt)</dt>
                                                                <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['rr'] ?: '-' }}</dd>
                                                            </div>
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Suhu (°C)</dt>
                                                                <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['suhu'] ?: '-' }}</dd>
                                                            </div>
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">SPO2 (%)</dt>
                                                                <dd class="mt-0.5 text-ink dark:text-gray-200">{{ ($entry['spo2'] ?? '') ?: '-' }}</dd>
                                                            </div>
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">GDA (g/dl)</dt>
                                                                <dd class="mt-0.5 text-ink dark:text-gray-200">{{ ($entry['gda'] ?? '') ?: '-' }}</dd>
                                                            </div>
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">BB / TB / IMT</dt>
                                                                <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['bb'] ?: '-' }} kg / {{ $entry['tb'] ?: '-' }} cm / {{ ($entry['imt'] ?? '') ?: '-' }} kg/m²</dd>
                                                            </div>
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Hb</dt>
                                                                <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['hb'] ?: '-' }}</dd>
                                                            </div>
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Gol. Darah</dt>
                                                                <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['golDarah'] ?: '-' }}</dd>
                                                            </div>
                                                            <div class="md:col-span-2">
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Pre Medikasi / Cairan / Obat</dt>
                                                                <dd class="mt-0.5 text-ink dark:text-gray-200">
                                                                    @forelse ($entry['persiapanObatCairan'] ?? [] as $persiapanItem)
                                                                        <div>
                                                                            {{ $loop->iteration }}. <b>{{ $persiapanItem['jenis'] ?? '-' }}</b>:
                                                                            {{ $persiapanItem['nama'] ?? '-' }}{{ !empty($persiapanItem['tglJam']) ? ' · ' . $persiapanItem['tglJam'] : '' }}
                                                                        </div>
                                                                    @empty
                                                                        -
                                                                    @endforelse
                                                                </dd>
                                                            </div>
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Tgl/Jam Mulai Puasa</dt>
                                                                <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['puasaMulaiJam'] ?: '-' }}</dd>
                                                            </div>
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Sudah Dicukur</dt>
                                                                <dd class="mt-0.5 text-ink dark:text-gray-200">{{ !empty($entry['sudahDicukur']) ? 'Ya' : 'Tidak' }}</dd>
                                                            </div>
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Persiapan Darah</dt>
                                                                <dd class="mt-0.5 text-ink dark:text-gray-200">{{ !empty($entry['persiapanDarah']) ? 'Ya' : 'Tidak' }}</dd>
                                                            </div>
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Gigi Palsu / Perhiasan Dilepas</dt>
                                                                <dd class="mt-0.5 text-ink dark:text-gray-200">{{ !empty($entry['gigiPalsuDilepas']) ? 'Ya' : 'Tidak' }}</dd>
                                                            </div>
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Pengosongan Kandung Kemih</dt>
                                                                <dd class="mt-0.5 text-ink dark:text-gray-200">{{ !empty($entry['pengosonganKandungKemih']) ? 'Ya' : 'Tidak' }}</dd>
                                                            </div>
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Clysma / Glyserin</dt>
                                                                <dd class="mt-0.5 text-ink dark:text-gray-200">{{ !empty($entry['clysma']) ? 'Ya' : 'Tidak' }}</dd>
                                                            </div>
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Riwayat Penyakit</dt>
                                                                <dd class="mt-0.5 text-ink dark:text-gray-200">{{ !empty($entry['riwayatPenyakit']) ? ('Ya' . (!empty($entry['riwayatPenyakitKet']) ? ' — ' . $entry['riwayatPenyakitKet'] : '')) : 'Tidak' }}</dd>
                                                            </div>
                                                            <div class="md:col-span-2">
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Lain-lain</dt>
                                                                <dd class="mt-0.5 whitespace-pre-line text-ink dark:text-gray-200">{{ $entry['lainLain'] ?: '-' }}</dd>
                                                            </div>
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Rekam Medis</dt>
                                                                <dd class="mt-0.5 text-ink dark:text-gray-200">{{ !empty($entry['adaRekamMedis']) ? 'Ya' : 'Tidak' }}</dd>
                                                            </div>
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Surat Ijin Tindakan</dt>
                                                                <dd class="mt-0.5 text-ink dark:text-gray-200">{{ !empty($entry['adaSuratIjin']) ? 'Ya' : 'Tidak' }}</dd>
                                                            </div>
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Hasil Laboratorium</dt>
                                                                <dd class="mt-0.5 text-ink dark:text-gray-200">{{ !empty($entry['adaLab']) ? 'Ya' : 'Tidak' }}</dd>
                                                            </div>
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Hasil Radiologi</dt>
                                                                <dd class="mt-0.5 text-ink dark:text-gray-200">{{ !empty($entry['adaRadiologi']) ? ('Ya' . (!empty($entry['radiologiJenis']) ? ' — ' . $entry['radiologiJenis'] : '')) : 'Tidak' }}</dd>
                                                            </div>
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Hasil Diagnostik</dt>
                                                                <dd class="mt-0.5 text-ink dark:text-gray-200">{{ !empty($entry['adaDiagnostik']) ? ('Ya' . (!empty($entry['diagnostikJenis']) ? ' — ' . $entry['diagnostikJenis'] : '')) : 'Tidak' }}</dd>
                                                            </div>
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Penandaan Lokasi</dt>
                                                                <dd class="mt-0.5 text-ink dark:text-gray-200">
                                                                    @if (($entry['perluPenandaan'] ?? '') === 'Tidak diperlukan')
                                                                        Tidak diperlukan{{ !empty($entry['alasanTidakPerlu']) ? ' — ' . $entry['alasanTidakPerlu'] : '' }}
                                                                    @else
                                                                        {{ trim(($entry['regionAnatomi'] ?? '') . ' ' . ($entry['sisi'] ?? '')) ?: '-' }}{{ !empty($entry['detailLokasi']) ? ' — ' . $entry['detailLokasi'] : '' }}
                                                                        · {{ count($entry['marks'] ?? []) }} tanda diagram
                                                                    @endif
                                                                </dd>
                                                            </div>
                                                            @foreach ([['ttdPerawatRuangan', 'Perawat Ruangan'], ['ttdPerawatKamarBedah', 'Perawat Kamar Bedah'], ['ttdDokterOperator', 'Dokter Operator']] as [$ttdField, $ttdLabel])
                                                                <div>
                                                                    <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">TTD {{ $ttdLabel }}</dt>
                                                                    <dd class="mt-0.5">
                                                                        @if (!empty($entry[$ttdField]))
                                                                            <span class="text-ink dark:text-gray-200">{{ $entry[$ttdField] }}</span>
                                                                            <span class="text-sm text-muted-soft">— {{ $entry[$ttdField . 'Date'] ?? '-' }}</span>
                                                                        @else
                                                                            <x-badge variant="danger">Belum TTD</x-badge>
                                                                        @endif
                                                                    </dd>
                                                                </div>
                                                            @endforeach
                                                        </dl>
                                                    </td>
                                                </tr>
                                            </tbody>
                                        @endforeach
                                    </table>
                                </div>
                            </div>
                        @endif