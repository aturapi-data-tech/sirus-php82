                    {{-- ====== 05 LIST ====== --}}
                    <section x-show="section === 'list'" x-cloak>
                        <div class="ds-eyebrow mb-3">05 — Komponen</div>
                        <h1 class="ds-display-md mb-4">Halaman List</h1>
                        <p class="ds-body-md mb-4" style="max-width:62ch">
                            Kelas PHP komponen LIST hanya berisi: state filter, dispatch event, dan satu
                            computed <span class="ds-code">rows()</span>. Query memakai
                            <span class="ds-code">DB::table()</span> (bukan Eloquent) dan pencarian
                            case-insensitive Oracle: <span class="ds-code">UPPER(kolom) LIKE</span> +
                            <span class="ds-code">mb_strtoupper</span>.
                        </p>

                        <div class="ds-card-dark mt-2" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">Kelas PHP — ⚡master-agama.blade.php</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['list-class'] }}</pre>
                        </div>

                        <p class="ds-body-md mt-8 mb-2" style="max-width:62ch">
                            Markup mengikuti urutan wajib: <span class="ds-code">x-page-title</span> →
                            frame flex-fill <span class="ds-code">h-[calc(100vh-5rem)]</span> →
                            toolbar sticky → card tabel → pagination sticky. Toolbar standar:
                        </p>

                        <div class="ds-card-dark mt-2" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">Toolbar</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['toolbar'] }}</pre>
                        </div>

                        <p class="ds-body-md mt-8 mb-2" style="max-width:62ch">
                            Tabel memakai kelas <span class="ds-code">ds-table</span> — jangan menulis ulang
                            kelas header/padding manual. Sel ID pakai <span class="ds-code">ds-td-token</span>
                            (mono), sel nama utama <span class="ds-code">ds-td-strong</span>, kolom tengah
                            <span class="ds-code">ds-c</span>. Aksi baris selalu
                            <span class="ds-code">x-action-edit</span> + <span class="ds-code">x-action-delete</span>:
                        </p>

                        <div class="ds-card-dark mt-2" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">Tabel + aksi baris + empty state</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['table'] }}</pre>
                        </div>

                        <div class="ds-card-outline mt-8" style="padding:16px 20px">
                            <span class="ds-spike" style="vertical-align:middle"></span>
                            <span class="ds-body-sm" style="color:var(--body-strong)">
                                Query kompleks (banyak join / dipakai ulang untuk export)? Pisahkan
                                <span class="ds-code">baseQuery()</span> privat, lalu <span class="ds-code">rows()</span>
                                tinggal <span class="ds-code">-&gt;paginate()</span> — contoh: <span class="ds-code">master-obat</span>.
                                Detail frame &amp; empty state: <span class="ds-code">docs/page-frame-pattern.md</span>.
                            </span>
                        </div>
                    </section>

                    {{-- ====== 06 FORM ====== --}}
                    <section x-show="section === 'form'" x-cloak>
                        <div class="ds-eyebrow mb-3">06 — Komponen</div>
                        <h1 class="ds-display-md mb-4">Form Modal (Actions)</h1>
                        <p class="ds-body-md mb-4" style="max-width:62ch">
                            File <span class="ds-code">-actions</span> memegang <strong>seluruh</strong> logika tulis:
                            buka modal, validasi, simpan, hapus. Ia memakai
                            <span class="ds-code">WithRenderVersioningTrait</span> supaya modal
                            di-remount bersih setiap kali dibuka (tidak ada sisa state/validasi lama).
                        </p>

                        <div class="ds-card-dark mt-2" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">Kelas PHP — ⚡master-agama-actions.blade.php (inti)</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['form-class'] }}</pre>
                        </div>

                        <p class="ds-body-md mt-8 mb-2" style="max-width:62ch">
                            Markup modal = 3 bagian tetap (header / body / footer) dibungkus
                            <span class="ds-code">x-dirty-modal-content</span> — user yang menutup modal
                            dengan perubahan belum tersimpan otomatis dikonfirmasi:
                        </p>

                        <div class="ds-card-dark mt-2" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">Markup modal (kerangka)</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['modal'] }}</pre>
                        </div>

                        <div class="grid grid-cols-1 gap-4 mt-8 sm:grid-cols-2">
                            <div class="ds-card-outline" style="padding:20px">
                                <div class="ds-title-sm mb-2">Wajib di body</div>
                                <ul class="ds-body-sm space-y-1.5" style="list-style:disc; padding-left:18px">
                                    <li><span class="ds-code">x-enter-chain</span> + Enter di field terakhir = simpan</li>
                                    <li>Fokus otomatis field pertama saat modal terbuka (event window + x-ref)</li>
                                    <li>Tiap field: <span class="ds-code">:error</span> + <span class="ds-code">x-input-error</span> di bawahnya</li>
                                    <li>Field nominal uang → <span class="ds-code">x-text-input-number</span></li>
                                    <li>Section field dibungkus <span class="ds-code">x-border-form</span></li>
                                </ul>
                            </div>
                            <div class="ds-card-outline" style="padding:20px">
                                <div class="ds-title-sm mb-2">Alur wajib method</div>
                                <ul class="ds-body-sm space-y-1.5" style="list-style:disc; padding-left:18px">
                                    <li><strong>open*</strong>: resetForm → set mode → incrementVersion → open-modal → fokus</li>
                                    <li><strong>save</strong>: validate → tulis DB → toast → closeModal → event saved</li>
                                    <li><strong>closeModal</strong>: resetForm → close-modal → resetVersion</li>
                                    <li>Tutup via tombol selalu <span class="ds-code">tryClose()</span> (dirty-guard), bukan closeModal langsung</li>
                                </ul>
                            </div>
                        </div>
                    </section>