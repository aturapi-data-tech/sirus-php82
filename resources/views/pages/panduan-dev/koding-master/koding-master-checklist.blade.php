                    {{-- ====== 14 CHECKLIST ====== --}}
                    <section x-show="section === 'checklist'" x-cloak>
                        <div class="ds-eyebrow mb-3">14 — Lanjutan</div>
                        <h1 class="ds-display-md mb-4">Checklist &amp; Referensi</h1>
                        <p class="ds-body-md mb-6" style="max-width:62ch">
                            Sebelum modul master baru di-merge, semua butir ini harus terpenuhi:
                        </p>

                        <div class="ds-card-outline" style="padding:24px">
                            <ul class="ds-body-sm space-y-2.5">
                                @foreach ([
                                    'Folder + 2 file ⚡ (list & actions), route Route::livewire + ->name(\'master.*\')',
                                    'Kontrak penamaan: searchKeyword, itemsPerPage, rows(), event master.<folder>.* verb standar',
                                    'LIST: page-title → frame flex-fill → toolbar sticky → ds-table → x-action-edit/delete → empty state → pagination sticky',
                                    'LIST tanpa validasi/DB-write — semua mutasi di file -actions',
                                    'FORM: WithRenderVersioningTrait + x-modal + x-dirty-modal-content + header/body/footer standar',
                                    'validate() sebelum logika lain; pesan Indonesia + attributes',
                                    'Delete: x-action-delete + catch ORA-02292',
                                    'x-enter-chain + Enter di field terakhir = simpan; fokus otomatis saat modal buka',
                                    'Toast sukses/gagal via dispatch toast; refresh list via event saved',
                                    'LIST ≤ ±300 baris, FORM ≤ ±400 baris; pecah partial bila lebih',
                                ] as $item)
                                    <li class="flex items-start gap-2.5">
                                        <svg class="w-4 h-4 mt-0.5 shrink-0" style="color:var(--primary)" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                        </svg>
                                        <span>{{ $item }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>

                        <h2 class="ds-title-lg mt-10 mb-4">Referensi</h2>
                        <div class="ds-card-outline" style="padding:0; overflow:hidden">
                            <table class="ds-table">
                                <thead>
                                    <tr><th>Apa</th><th>Di mana</th></tr>
                                </thead>
                                <tbody>
                                    <tr><td class="ds-td-strong">Template kanonik</td><td class="ds-td-class">resources/views/pages/master/master-agama/</td></tr>
                                    <tr><td class="ds-td-strong">Dokumen sumber</td><td class="ds-td-class">docs/standar-master-module.md</td></tr>
                                    <tr><td class="ds-td-strong">Token &amp; kelas ds-*</td><td class="ds-td-class">resources/css/app.css (warna: tailwind.config.cjs)</td></tr>
                                    <tr><td class="ds-td-strong">Komponen aksi tabel</td><td class="ds-td-class">resources/views/components/action-{edit,delete}.blade.php</td></tr>
                                    <tr><td class="ds-td-strong">Toolbar refresh/reset</td><td class="ds-td-class">resources/views/components/toolbar-refresh-reset.blade.php</td></tr>
                                    <tr><td class="ds-td-strong">Render versioning</td><td class="ds-td-class">app/Http/Traits/Concerns/WithRenderVersioningTrait.php</td></tr>
                                    <tr><td class="ds-td-strong">Frame halaman &amp; empty state</td><td class="ds-td-class">docs/page-frame-pattern.md</td></tr>
                                    <tr><td class="ds-td-strong">Modal dirty-guard</td><td class="ds-td-class">docs/dirty-modal-pattern.md</td></tr>
                                    <tr><td class="ds-td-strong">Tombol &amp; UI umum</td><td class="ds-td-class">docs/standar-komponen-tombol.md · docs/standar-ui-komponen.md</td></tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="ds-card-outline mt-8" style="padding:16px 20px">
                            <span class="ds-spike" style="vertical-align:middle"></span>
                            <span class="ds-body-sm" style="color:var(--body-strong)">
                                <strong>Lanjutan:</strong> sudah khatam modul master? Lanjut ke
                                <a href="{{ route('panduan-dev.koding-transaksi') }}" wire:navigate
                                    class="hover:underline font-semibold" style="color:var(--primary)">Tutorial Koding Transaksi</a>
                                — pendaftaran → pelayanan → kasir (RJ/UGD/RI) + EMR, modul dokumen, administrasi.
                            </span>
                        </div>
                    </section>