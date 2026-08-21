                    {{-- ====== 07 CHECKLIST & LANGKAH KIRIM ====== --}}
                    <section x-show="section === 'checklist'" x-cloak>
                        <div class="ds-eyebrow mb-3">07 — Pengiriman</div>
                        <h1 class="ds-display-md mb-4">Checklist Kolom Wajib &amp; Langkah Kirim</h1>
                        <p class="ds-body-md mb-6" style="max-width:64ch">
                            Panduan <strong>petugas</strong>: kolom apa saja yang <strong>wajib terisi</strong>
                            agar tiap tombol "Kirim" di modal <em>Satu Sehat</em> (Daftar RJ) berhasil,
                            dan urutan langkahnya. Prinsip: <strong>tanpa kode standar (IHS / SNOMED / LOINC /
                            KFA / ICD), item di-skip diam-diam</strong> — isi master dulu supaya tidak "berhasil (0 item)".
                        </p>

                        @php
                            $stepBadge = 'display:inline-flex;align-items:center;justify-content:center;min-width:28px;height:28px;border-radius:9999px;background:var(--primary);color:#fff;font-size:13px;font-weight:700;line-height:1;flex:none';
                            $numBadge  = 'display:inline-flex;align-items:center;justify-content:center;min-width:22px;height:22px;border-radius:9999px;background:var(--surface-dark-soft,#1f2937);color:#fff;font-size:11px;font-weight:700;flex:none';
                        @endphp

                        {{-- ===== BAGIAN 1: DUA LAPIS PRASYARAT ===== --}}
                        <h2 class="ds-title-lg mb-3">1 · Dua lapis prasyarat</h2>
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 mb-4">
                            <div class="ds-card-outline" style="padding:20px; border-color:var(--primary)">
                                <div class="ds-title-sm mb-1">A · Setup Master <span class="ds-caption" style="color:var(--muted)">(sekali per entitas)</span></div>
                                <div class="ds-body-sm mb-3" style="color:var(--muted)">Tanpa ini, kirim langsung gagal.</div>
                                <ul class="ds-body-sm space-y-2" style="list-style:none; padding:0">
                                    <li>🆔 <strong>IHS Pasien</strong> — <span class="ds-code">rsmst_pasiens.patient_uuid</span><br><span class="ds-caption" style="color:var(--muted)">Master Pasien (otomatis via NIK 16 digit) · WAJIB semua resource</span></li>
                                    <li>🩺 <strong>IHS Dokter</strong> — <span class="ds-code">rsmst_doctors.dr_uuid</span><br><span class="ds-caption" style="color:var(--muted)">Master Dokter · WAJIB Encounter, Alergi, Lab, Radiologi, Dispense, Impresi</span></li>
                                    <li>🏥 <strong>IHS Poli</strong> — <span class="ds-code">rsmst_polis.poli_uuid</span><br><span class="ds-caption" style="color:var(--muted)">Master Poli · WAJIB Encounter</span></li>
                                    <li>💊 <strong>KFA Obat</strong> — <span class="ds-code">product_id_satusehat</span><br><span class="ds-caption" style="color:var(--muted)">Master Obat · WAJIB Resep &amp; Obat Pulang</span></li>
                                    <li>🧪 <strong>LOINC Lab</strong> — <span class="ds-code">lbmst_clabitems.loinc_code</span><br><span class="ds-caption" style="color:var(--muted)">Master Lab (per item) · WAJIB Observasi Lab</span></li>
                                    <li>🏢 <strong>Organization Id</strong> — <span class="ds-code">env SATUSEHAT_ORGANIZATION_ID</span> <span class="ds-caption" style="color:var(--muted)">(tetap)</span></li>
                                </ul>
                            </div>
                            <div class="ds-card-outline" style="padding:20px">
                                <div class="ds-title-sm mb-1">B · Isi per Kunjungan <span class="ds-caption" style="color:var(--muted)">(EMR RJ)</span></div>
                                <div class="ds-body-sm mb-3" style="color:var(--muted)">Diisi petugas saat pelayanan; yang kosong → resource-nya tak terkirim.</div>
                                <ul class="ds-body-sm space-y-2" style="list-style:none; padding:0">
                                    <li>📝 <strong>Keluhan Utama</strong> + <strong>Kode SNOMED</strong> <span class="ds-caption" style="color:var(--muted)">(Anamnesa)</span></li>
                                    <li>⚠️ <strong>Alergi</strong> + <strong>Kode SNOMED</strong> <span class="ds-caption" style="color:var(--muted)">(Anamnesa)</span></li>
                                    <li>❤️ <strong>Tanda Vital</strong> (TD/nadi/suhu/RR) <span class="ds-caption" style="color:var(--muted)">(Pemeriksaan)</span></li>
                                    <li>🩹 <strong>Diagnosa ICD-10</strong> <span class="ds-caption" style="color:var(--muted)">(Diagnosa)</span></li>
                                    <li>✂️ <strong>Tindakan ICD-9</strong> <span class="ds-caption" style="color:var(--muted)">(Perencanaan)</span></li>
                                    <li>💊 <strong>E-Resep</strong> (obat ber-KFA)</li>
                                    <li>🧪 <strong>Hasil Lab selesai</strong> (status ≠ Pending)</li>
                                    <li>🩻 <strong>Order Radiologi</strong></li>
                                </ul>
                            </div>
                        </div>

                        {{-- ===== BAGIAN 2: TABEL KOLOM WAJIB PER KARTU ===== --}}
                        <h2 class="ds-title-lg mt-8 mb-3">2 · Kolom wajib per tombol Kirim</h2>
                        <div class="ds-card-outline" style="padding:0; overflow-x:auto">
                            <table class="ds-table">
                                <thead><tr><th>#</th><th>Kartu (Resource)</th><th>Kolom / field WAJIB</th><th>Di mana diisi</th><th>Kalau kosong</th></tr></thead>
                                <tbody>
                                    <tr><td class="ds-td-strong">1</td><td class="ds-body-sm">Encounter</td><td class="ds-body-sm">IHS pasien + <span class="ds-code">dr_uuid</span> + <span class="ds-code">poli_uuid</span></td><td class="ds-body-sm">Master Pasien/Dokter/Poli</td><td class="ds-body-sm" style="color:var(--primary)"><strong>Gagal → semua terkunci</strong></td></tr>
                                    <tr><td class="ds-td-strong">2</td><td class="ds-body-sm">Condition (diagnosa)</td><td class="ds-body-sm">Diagnosa ICD-10 (<span class="ds-code">kodeIcdx</span>)</td><td class="ds-body-sm">EMR › Diagnosa</td><td class="ds-body-sm">Item tanpa kode di-skip</td></tr>
                                    <tr><td class="ds-td-strong">3</td><td class="ds-body-sm">Observation</td><td class="ds-body-sm">Tanda vital (TD/nadi/suhu/RR)</td><td class="ds-body-sm">EMR › Pemeriksaan</td><td class="ds-body-sm">Tak ada vital → gagal</td></tr>
                                    <tr><td class="ds-td-strong">4</td><td class="ds-body-sm">Procedure</td><td class="ds-body-sm">Tindakan ICD-9 (<span class="ds-code">kodeIcd9</span>)</td><td class="ds-body-sm">EMR › Perencanaan</td><td class="ds-body-sm">Item tanpa kode di-skip</td></tr>
                                    <tr><td class="ds-td-strong">5</td><td class="ds-body-sm">MedicationRequest</td><td class="ds-body-sm">E-Resep + KFA (<span class="ds-code">product_id_satusehat</span>)</td><td class="ds-body-sm">E-Resep + Master Obat</td><td class="ds-body-sm">Obat tanpa KFA di-skip</td></tr>
                                    <tr><td class="ds-td-strong">6</td><td class="ds-body-sm">Chief Complaint</td><td class="ds-body-sm">Keluhan utama + <strong>Kode SNOMED</strong></td><td class="ds-body-sm">EMR › Anamnesa (LOV SNOMED)</td><td class="ds-body-sm">Tanpa SNOMED → ditolak (toast)</td></tr>
                                    <tr><td class="ds-td-strong">7</td><td class="ds-body-sm">Allergy Intolerance</td><td class="ds-body-sm">Alergi + <strong>Kode SNOMED</strong> + <span class="ds-code">dr_uuid</span></td><td class="ds-body-sm">EMR › Anamnesa + Master Dokter</td><td class="ds-body-sm">Tanpa SNOMED/IHS dokter → ditolak</td></tr>
                                    <tr><td class="ds-td-strong">8</td><td class="ds-body-sm">Medication Dispense</td><td class="ds-body-sm"><strong>Resep (kartu 5) harus dikirim dulu</strong> + KFA</td><td class="ds-body-sm">idem Resep</td><td class="ds-body-sm">Resep belum dikirim → tombol nonaktif</td></tr>
                                    <tr><td class="ds-td-strong">9</td><td class="ds-body-sm">Penunjang Lab</td><td class="ds-body-sm">Hasil lab selesai + <strong>LOINC per item</strong></td><td class="ds-body-sm">Master Lab (LOINC) + input hasil</td><td class="ds-body-sm">Item tanpa LOINC di-skip; tak ada hasil → gagal</td></tr>
                                    <tr><td class="ds-td-strong">10</td><td class="ds-body-sm">Penunjang Radiologi</td><td class="ds-body-sm">Order radiologi + <span class="ds-code">dr_uuid</span></td><td class="ds-body-sm">EMR › order radiologi</td><td class="ds-body-sm">ImagingStudy dilewati (no DICOM)</td></tr>
                                    <tr><td class="ds-td-strong">11</td><td class="ds-body-sm">Clinical Impression</td><td class="ds-body-sm">Diagnosa (jadi ringkasan) + <span class="ds-code">dr_uuid</span></td><td class="ds-body-sm">EMR › Diagnosa</td><td class="ds-body-sm">Tak ada diagnosa → ditolak</td></tr>
                                </tbody>
                            </table>
                        </div>
                        <p class="ds-caption mt-3" style="color:var(--muted)">
                            IHS pasien (<span class="ds-code">patient_uuid</span>) &amp; Encounter terkirim = prasyarat mutlak SEMUA kartu di atas.
                        </p>

                        {{-- ===== BAGIAN 3: STEP BY STEP ===== --}}
                        <h2 class="ds-title-lg mt-8 mb-4">3 · Langkah demi langkah</h2>
                        <div class="space-y-3">
                            @foreach ([
                                ['Lengkapi IHS di Master (sekali)', 'Master Pasien → <span class="ds-code">patient_uuid</span> (otomatis dari NIK 16 digit), Master Dokter → <span class="ds-code">dr_uuid</span>, Master Poli → <span class="ds-code">poli_uuid</span>. Tanpa ini Encounter gagal & semua terkunci.'],
                                ['Isi kode standar di Master (sekali)', 'KFA obat di Master Obat (<span class="ds-code">product_id_satusehat</span>), LOINC lab per item di Master Lab (<span class="ds-code">loinc_code</span>). Item tanpa kode akan dilewati saat kirim.'],
                                ['Petugas isi EMR lengkap saat pelayanan', 'Keluhan+SNOMED, Alergi+SNOMED, tanda vital, diagnosa ICD-10, tindakan ICD-9, e-resep; selesaikan hasil lab & order radiologi.'],
                                ['Buka modal Satu Sehat', 'Daftar RJ → klik ikon <em>Satu Sehat</em> pada baris pasien → muncul modal berisi <strong>15 kartu</strong>.'],
                                ['Kirim kartu 1 Encounter DULU', 'Encounter = akar (wajib). Semua tombol kartu lain <strong>nonaktif</strong> sampai Encounter sukses.'],
                                ['Kirim kartu 2–14 sesuai data terisi', 'Urutan bebas, KECUALI yang saling menunjuk: kartu 8 Obat Pulang butuh kartu 5 Resep lebih dulu, dan kartu 14 Telaah Resep menunjuk MedicationRequest dari kartu 5. Item tanpa kode standar dilewati dengan notifikasi jumlah.'],
                                ['Cek badge & verifikasi', 'Kartu yang sukses jadi hijau "Terkirim". Verifikasi payload &amp; respons server di tabel <span class="ds-code">web_log_status</span>, lalu cek angka di dashboard SATUSEHAT.'],
                                ['Saat pasien pulang', 'Ketika kunjungan CLOSED, Encounter otomatis di-<span class="ds-code">PUT</span> ke status <span class="ds-code">finished</span>.'],
                            ] as $i => [$judul, $isi])
                                <div class="ds-card-outline" style="padding:16px 20px">
                                    <div class="flex items-start gap-3">
                                        <span style="{{ $stepBadge }}">{{ $i + 1 }}</span>
                                        <div>
                                            <div class="ds-title-sm mb-1">{{ $judul }}</div>
                                            <div class="ds-body-sm">{!! $isi !!}</div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        {{-- ===== BAGIAN 4: GATE / KUNCI ===== --}}
                        <div class="ds-card-outline mt-8" style="padding:16px 20px">
                            <span class="ds-spike" style="vertical-align:middle"></span>
                            <span class="ds-body-sm" style="color:var(--body-strong)">
                                <strong>Aturan gate yang mengunci kirim:</strong>
                                (1) <strong>Encounter akar</strong> — semua kartu lain nonaktif sampai Encounter terkirim;
                                (2) <strong>Obat Pulang</strong> butuh Resep dikirim dulu;
                                (3) <strong>item tanpa kode standar</strong> (SNOMED/LOINC/KFA/ICD) di-skip diam-diam —
                                kalau hasil "0 item", cek pengisian master; ulangi kirim setelah master dilengkapi.
                            </span>
                        </div>
                    </section>