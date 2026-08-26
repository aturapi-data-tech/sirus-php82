                    {{-- ====== 08 ADMINISTRASI & KASIR ====== --}}
                    <section x-show="section === 'administrasi'" x-cloak>
                        <div class="ds-eyebrow mb-3">08 — Tahapan</div>
                        <h1 class="ds-display-md mb-4">Administrasi &amp; Kasir</h1>
                        <p class="ds-body-md mb-4" style="max-width:62ch">
                            Administrasi = modal rekap biaya kunjungan; tiap <strong>pos</strong> biaya
                            adalah file partial sendiri di folder <span class="ds-code">administrasi-&lt;jalur&gt;</span>.
                            Setelah petugas menandai selesai, pasien masuk antrian kasir untuk posting bayar.
                        </p>

                        @php
                            $admBadge = 'display:inline-flex;align-items:center;justify-content:center;min-width:20px;height:20px;border-radius:9999px;background:var(--primary);color:#fff;font-size:11px;font-weight:700;line-height:1;flex:none';
                        @endphp

                        {{-- visual anatomi modal administrasi --}}
                        <div class="ds-frame mt-2 mb-2">
                            <div class="ds-frame-label">Tata letak modal Administrasi (administrasi-rj)</div>
                            <div class="mt-3" style="border:1px solid var(--hairline); border-radius:14px; overflow:hidden; background:var(--canvas)">

                                {{-- row 1: identitas + total + close --}}
                                <div class="flex flex-wrap items-center justify-between gap-3 px-4 py-3" style="position:relative; background:var(--surface-soft); border-bottom:1px solid var(--hairline)">
                                    <span class="ds-body-sm" style="color:var(--muted)">Identitas pasien (display-pasien-rj)</span>
                                    <span class="px-3 py-1.5 rounded-xl" style="border:1px solid var(--hairline); background:var(--canvas)">
                                        <span class="block ds-caption" style="color:var(--muted)">Total Tagihan</span>
                                        <span class="block text-sm font-bold" style="color:var(--ink)">Rp 385.000 ▾</span>
                                    </span>
                                    <span class="flex items-center gap-2">
                                        <span style="color:var(--muted)">✕</span>
                                        <span style="{{ $admBadge }}">1</span>
                                    </span>
                                </div>

                                {{-- row 2: breakdown pos --}}
                                <div class="flex flex-wrap items-center gap-1.5 px-4 py-2" style="position:relative; border-bottom:1px solid var(--hairline); background:var(--surface-soft)">
                                    @foreach (['Adm RS', 'Adm RJ', 'Poli', 'Js Karyawan', 'Js Dokter', 'Js Medis', 'Obat', 'Lab', 'Rad', 'Lain-lain'] as $pos)
                                        <span class="px-2 py-0.5 text-xs rounded-full" style="border:1px solid var(--hairline); background:var(--canvas); color:var(--body)">{{ $pos }}</span>
                                    @endforeach
                                    <span style="{{ $admBadge }};background:var(--info)">2</span>
                                </div>

                                {{-- row 3: tab pos --}}
                                <div class="flex flex-wrap items-center gap-3 px-4 py-2" style="position:relative; border-bottom:1px solid var(--hairline)">
                                    @foreach (['Jasa Dokter', 'Obat', 'Laboratorium', 'Lain-lain', 'Kasir'] as $i => $tabPos)
                                        <span class="text-sm {{ $i === 3 ? 'font-bold' : '' }}"
                                            style="color:{{ $i === 3 ? 'var(--primary)' : 'var(--muted)' }}; {{ $i === 3 ? 'border-bottom:2px solid var(--primary); padding-bottom:2px' : '' }}">{{ $tabPos }}</span>
                                    @endforeach
                                    <span class="ds-caption" style="color:var(--muted)">…</span>
                                    <span style="{{ $admBadge }}">3</span>
                                </div>

                                {{-- panel pos aktif --}}
                                <div class="px-4 py-3" style="position:relative">
                                    <p class="ds-body-sm" style="color:var(--muted)">
                                        Tabel baris pos aktif (child Livewire ber-<span class="ds-code">:rjNo</span>) —
                                        tambah item via LOV, edit inline per baris, hapus dgn konfirmasi.
                                    </p>
                                    <span style="{{ $admBadge }};position:absolute;top:10px;right:12px">4</span>
                                </div>

                                {{-- footer --}}
                                <div class="flex items-center justify-end gap-2 px-4 py-3" style="position:relative; border-top:1px solid var(--hairline); background:var(--surface-soft)">
                                    <span class="ds-btn ds-btn-primary" style="height:32px; padding:6px 12px; font-size:12px">Selesai Administrasi</span>
                                    <span style="{{ $admBadge }}">5</span>
                                </div>
                            </div>
                        </div>

                        {{-- legenda anatomi --}}
                        <div class="grid grid-cols-1 gap-2 mb-6 sm:grid-cols-2">
                            @foreach ([
                                ['1', 'Header — identitas pasien + kartu Total Tagihan (klik = buka/tutup rincian) + tutup modal', ''],
                                ['2', 'Rincian breakdown 10 pos hasil sumAll() — 3 dari kolom header (adm RS/RJ/poli), 7 dari SUM tabel billing per pos', 'background:var(--info)'],
                                ['3', 'Tab per pos + tab Kasir — tiap tab adalah child Livewire sendiri ber-:rjNo (file per pos)', ''],
                                ['4', 'Panel pos aktif — mutasi apa pun dispatch administrasi-rj.updated → host re-sumAll()', ''],
                                ['5', 'Selesai Administrasi — stempel userLog ke JSON + set status → pasien masuk antrian kasir (poll 30s)', ''],
                            ] as [$num, $ket, $extra])
                                <div class="flex items-start gap-2.5">
                                    <span style="{{ $admBadge }}; margin-top:2px; {{ $extra }}">{{ $num }}</span>
                                    <span class="ds-body-sm">{{ $ket }}</span>
                                </div>
                            @endforeach
                        </div>

                        <div class="ds-card-dark mt-2" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">Pos biaya &amp; total — administrasi-rj</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['administrasi'] }}</pre>
                        </div>

                        <div class="ds-card-dark mt-4" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">Membuat pos — kerangka utuh satu pos + wiring host (lain-lain-rj)</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['administrasi-pos'] }}</pre>
                        </div>

                        <div class="ds-card-dark mt-4" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">Posting bayar — administrasi-kasir-ri</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['kasir-post'] }}</pre>
                        </div>

                        <div class="ds-card-dark mt-4" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">Guard role + audit log</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['role-audit'] }}
{{ $snip['audit-log'] }}</pre>
                        </div>

                        <div class="ds-card-dark mt-4" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">Model pengunci — rj_status · lab pending · userLog · row-lock · TTD</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['lock-model'] }}</pre>
                        </div>

                        <div class="ds-card-outline mt-6" style="padding:16px 20px">
                            <span class="ds-spike" style="vertical-align:middle"></span>
                            <span class="ds-body-sm" style="color:var(--body-strong)">
                                <strong>Uang &amp; kunci:</strong> semua mutasi finansial dalam
                                <span class="ds-code">DB::transaction</span> + <span class="ds-code">lockForUpdate</span>;
                                posting bayar hanya role kasir (<span class="ds-code">Admin|Tu|Manager Umum|Supervisor Tu</span>);
                                bayar kurang dari total = otomatis <strong>bon</strong>, bukan error.
                                Semua nominal di UI pakai <span class="ds-code">x-text-input-number</span>.
                            </span>
                        </div>
                    </section>