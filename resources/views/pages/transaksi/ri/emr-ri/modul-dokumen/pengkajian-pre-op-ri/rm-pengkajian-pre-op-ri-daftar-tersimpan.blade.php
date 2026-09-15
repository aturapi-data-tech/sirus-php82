                        {{-- ══ DAFTAR PENGKAJIAN TERSIMPAN (expandable) ══ --}}
                        <x-modul-dokumen.tabel-daftar :kolom="['', 'Tanggal', 'Rencana Operasi', 'TTD (3 Pihak)', 'Status' => 'text-center', 'Aksi' => 'text-center w-64']">
                                        @forelse (collect($preOpList)->sortByDesc(fn($entri) => strtotime(strtr(($entri['tanggal'] ?? '') ?: ($entri['createdAt'] ?? ''), '/', '-')))->values()->all() as $entry)
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
                                                        <x-modul-dokumen.status-entri :final="$isFinal" />
                                                    </td>
                                                    <td class="px-4 py-3 text-center align-middle whitespace-nowrap" @click.stop>
                                                        <x-modul-dokumen.aksi-entri kunci="{{ $rowKey }}" :final="$isFinal" :terkunci="$isFormLocked"
                                                            judulLihat="Lihat detail (read-only) di form atas"
                                                            judulBukaKunci="Buka Kunci Pengkajian Pre Operasi"
                                                            pesanBukaKunci="KETIGA TTD (Perawat Ruangan, Perawat Kamar Bedah, Dokter Operator) akan dicabut & entri kembali menjadi Draft — proses TTD diulang dari awal. Lanjutkan?"
                                                            konfirmasiHapus="Yakin hapus pengkajian ini?" />
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
                                                                        <x-modul-dokumen.status-ttd :nama="$entry[$ttdField] ?? ''" :waktu="$entry[$ttdField . 'Date'] ?? '-'" gaya="biasa" />
                                                                    </dd>
                                                                </div>
                                                            @endforeach
                                                        </dl>
                                                    </td>
                                                </tr>
                                            </tbody>
                                        @empty
                                            <x-modul-dokumen.baris-kosong :kolom="6" pesan="Belum ada pengkajian pre operasi tersimpan." />
                                        @endforelse
                        </x-modul-dokumen.tabel-daftar>