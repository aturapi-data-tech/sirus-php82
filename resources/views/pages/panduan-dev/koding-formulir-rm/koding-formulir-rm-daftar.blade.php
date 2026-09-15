                    {{-- ====== 03 DAFTAR FORMULIR RM (nama dari halaman ini, view & revisi dari blade) ====== --}}
                    <section x-show="section === 'daftar'" x-cloak>
                        <div class="ds-eyebrow mb-3">03 — Daftar Induk</div>
                        <h1 class="ds-display-md mb-4">Daftar Formulir RM</h1>
                        <p class="ds-body-md mb-6" style="max-width:62ch">
                            {{ count($formulir) }} formulir dalam {{ count($kelompok) }} kelompok. Kolom
                            <strong>Rev</strong> dan <strong>View cetak</strong> dibaca langsung dari atribut
                            <span class="ds-code">kode="…"</span> di blade cetak — yang tampil di sini itulah yang
                            tercetak. Kode yang sudah ada <strong>jangan diubah</strong>; formulir baru mengambil
                            nomor berikutnya di kelompoknya.
                        </p>

                        @php
                            $kodeTakTerdaftar = array_diff_key($cetakan['berkode'], $formulir);
                        @endphp
                        @if ($kodeTakTerdaftar !== [] || $cetakan['takBaku'] !== [])
                            <div class="ds-card-outline mb-8" style="padding:20px; border-color:var(--primary)">
                                <div class="ds-caption-up mb-2" style="color:var(--primary)">Perlu dibenahi</div>
                                <ul class="ds-body-sm space-y-1.5">
                                    @foreach ($kodeTakTerdaftar as $kode => $pemakaiList)
                                        @foreach ($pemakaiList as $pemakai)
                                            <li>• <span class="ds-code">{{ $kode }}</span> belum ada di daftar —
                                                <span class="ds-code" style="font-size:11px">{{ $pemakai['view'] }}</span></li>
                                        @endforeach
                                    @endforeach
                                    @foreach ($cetakan['takBaku'] as $pemakai)
                                        <li>• Format kode tak baku <span class="ds-code">kode="{{ $pemakai['kode'] }}"</span> —
                                            <span class="ds-code" style="font-size:11px">{{ $pemakai['view'] }}</span></li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        @foreach ($kelompok as $kodeKelompok => $namaKelompok)
                            @php
                                $formulirKelompok = array_filter($formulir, fn($kode) => substr($kode, 3, 2) === (string) $kodeKelompok, ARRAY_FILTER_USE_KEY);
                            @endphp
                            <h2 class="ds-title-lg mt-8 mb-3">
                                <span class="ds-code" style="color:var(--primary)">{{ $kodeKelompok }}</span> {{ $namaKelompok }}
                            </h2>
                            <div class="ds-card-outline mb-2" style="padding:0; overflow-x:auto">
                                <table class="ds-table">
                                    <thead>
                                        <tr><th>Kode</th><th>Nama Formulir</th><th>Rev</th><th>View cetak</th></tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($formulirKelompok as $kode => $namaFormulir)
                                            @php
                                                $pemakaiList = $cetakan['berkode'][$kode] ?? [];
                                                $revisiList = array_values(array_unique(array_column($pemakaiList, 'revisi')));
                                            @endphp
                                            <tr>
                                                <td class="ds-td-strong" style="white-space:nowrap">{{ $kode }}</td>
                                                <td>{{ $namaFormulir }}</td>
                                                <td style="white-space:nowrap">
                                                    {{ $revisiList === [] ? '-' : implode(' / ', $revisiList) }}
                                                    @if (count($revisiList) > 1)
                                                        <div class="ds-caption" style="color:var(--primary)">revisi antar jalur berbeda</div>
                                                    @endif
                                                </td>
                                                <td>
                                                    @forelse ($pemakaiList as $pemakai)
                                                        <div class="ds-code" style="font-size:11px; color:var(--body)">
                                                            {{ \Illuminate\Support\Str::after($pemakai['view'], 'pages.components.') }}
                                                        </div>
                                                    @empty
                                                        <span class="ds-caption" style="color:var(--muted-soft)">Belum ada blade cetak yang memasang kode ini</span>
                                                    @endforelse
                                                </td>
                                            </tr>
                                        @empty
                                            <tr><td colspan="4" style="color:var(--muted-soft)">Belum ada formulir di kelompok ini.</td></tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        @endforeach
                    </section>

                    {{-- ====== 04 CETAKAN TANPA KODE (disisir dari blade) ====== --}}
                    <section x-show="section === 'pengecualian'" x-cloak>
                        <div class="ds-eyebrow mb-3">04 — Daftar Induk</div>
                        <h1 class="ds-display-md mb-4">Cetakan Tanpa Kode</h1>
                        <p class="ds-body-md mb-6" style="max-width:62ch">
                            Semua <span class="ds-code">*-print.blade.php</span> yang tidak memasang
                            <span class="ds-code">kode="…"</span> ({{ count($cetakan['tanpaKode']) }} berkas). Wajarnya
                            hanya cetakan yang bukan formulir rekam medis: kuitansi, etiket, dokumen BPJS
                            (SEP/PRB/SKDP), slip gaji, dokumen unit IT. Formulir rekam medis yang muncul di sini
                            berarti <strong>lupa dikode</strong>.
                        </p>

                        <div class="ds-card-outline" style="padding:0; overflow-x:auto">
                            <table class="ds-table">
                                <thead>
                                    <tr><th>View cetak</th></tr>
                                </thead>
                                <tbody>
                                    @forelse ($cetakan['tanpaKode'] as $view)
                                        <tr>
                                            <td><span class="ds-code" style="font-size:11px; color:var(--body)">{{ $view }}</span></td>
                                        </tr>
                                    @empty
                                        <tr><td style="color:var(--muted-soft)">Semua cetakan sudah berkode.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </section>
