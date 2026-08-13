<?php

use Livewire\Component;

// Tutorial konsep ADMINISTRASI (kasir) RJ/UGD/RI sampai pasien pulang, plus konsep
// TRANSFER (UGD→RI) & MODEL BATAL (batal transaksi / batal transfer / batal inap).
// Gaya sama koding-satusehat: sidebar per-submenu, snippet = nowdoc (aman compiler Blade).
new class extends Component {
    public function snippets(): array
    {
        return [

'status' => <<<'TXT'
// STATUS TRANSAKSI per jalur — dipakai untuk gating tombol & filter list.

// RJ & UGD — kolom rj_status / txn_status (rstxn_rjhdrs / rstxn_ugdhdrs)
//   'A' = Aktif / Antri     (default saat pendaftaran)
//   'I' = Transfer Inap     (UGD/RJ dipindah ke RI — terkunci)
//   status pulang/closed dihitung dari task-id di JSON, bukan kolom tersendiri.

// RI — kolom ri_status (rstxn_rihdrs)
//   'I' = Dirawat   (default saat admisi)
//   'P' = Pulang    (sudah diproses pulang + bayar)
//   'F' = Batal     (admisi dibatalkan)

// Peta status RI (daftar-ri-bulanan):
//   ['I' => 'Dirawat', 'P' => 'Pulang', 'F' => 'Batal']
// PENTING: laporan SIRS/manajemen MENGECUALIKAN ri_status='F' (dianggap tak terjadi).
TXT,

'biaya' => <<<'TXT'
// TOTAL TAGIHAN = jumlah SEMUA pos biaya. Dihitung reusable (dipakai kasir & transfer).
protected function calculateRJCosts(int $rjNo): array
{
    return [
        'rsAdmin'   => header rs_admin,
        'rjAdmin'   => header rj_admin,
        'poliPrice' => header poli_price,
        'actePrice' => sum rstxn_rjactemps.acte_price,      // tindakan medis
        'actdPrice' => sum rstxn_rjaccdocs.accdoc_price,    // jasa dokter
        'actpPrice' => sum rstxn_rjactparams.pact_price,    // tindakan penunjang
        'obat'      => sum(qty * price) rstxn_rjobats,
        'lab'       => sum rstxn_rjlabs.lab_price,
        'rad'       => sum rstxn_rjrads.rad_price,
        'other'     => sum rstxn_rjothers.other_price,
        'kamarOperasi' => sum rstxn_rjoks.ok_price,          // hasil Trf Biaya modul OK
    ];
}
// UGD: pola sama di rstxn_ugd* (ugdobats/ugdrads/ugdoks/…).
// RI : calculateRICosts() — adminAge, adminStatus, visit, konsul, jasaMedis, jasaDokter,
//      lab, rad, ok, lainLain, room, commonService, perawatan, bonResep, + trfUgdRj.
//
// ⚠️ FUNGSI INI DIPAKAI DUA PIHAK: kasir DAN transfer antar-unit. Transfer memetakan
//    tiap komponen ke KOLOM TETAP rstxn_*tempadmins, jadi menambah komponen di sini
//    WAJIB dibarengi kolom baru di tabel itu + pemetaannya. Kalau tidak, angkanya
//    ikut di total kasir tapi HILANG saat pasien dipindah unit.
//    Jangan pula menambah pos langsung di hitungTotal() kasir — nanti dobel.
//    Selengkapnya: bab "Menambah Pos Biaya Baru".
TXT,

'kasir' => <<<'TXT'
// ALUR KASIR SAMPAI PULANG (kasir-ri, pola serupa kasir-rj/kasir-ugd):
//
// 1. Set Tanggal Pulang    → updateTglPulang()   (exit_date; tglPulangSudahDiproses=true)
// 2. Input Nominal Bayar   → wire:model bayar
// 3. Proses Pulang         → postTransaksi()     (satu transaksi + lock):
//      - hitung sisaTagihan = totalSetelahDiskon - angsuran
//      - status_pulang = 'L' (LUNAS) bila bayar >= sisa, else 'H' (BON/HUTANG)
//      - insert payment (rstxn_ripaymentpdtls / *paymentdtls) + kembalian
//      - ri_status → 'P' (Pulang); tutup end_date kamar; lockstatus pasien lepas
//
// GUARD SEBELUM POSTING (urutannya mengikat) — kunjungan tak boleh ditutup selagi
// masih ada order penunjang menggantung, karena transfer biayanya mensyaratkan
// kunjungan masih aktif:
//   1. checkLabPending{RJ,UGD,RI}  — "Hasil Laborat belum selesai"
//   2. checkOkPending{RJ,UGD,RI}   — sebut nomor OK-nya (daftarOkPending*)
// Guard yang sama dipasang di kedua komponen transfer antar-unit.
// Radiologi & Apotek TIDAK punya guard ini — biayanya langsung masuk, tanpa jeda.
//
// Form terkunci (isFormLocked) setelah pulang → hanya tombol Batal yang muncul.
// Guard role BEDA per aksi (sejak 2026-07):
//   • Batal Transfer & Batal Transaksi (RJ/UGD) = Admin|Tu|Perawat|Manager Umum|Supervisor Tu.
//   • Batal Inap / Batal Kunjungan (RI)         = Admin|Supervisor Tu.
// lockRIRow/lockUGDRow/lockRJRow sebelum tulis.
TXT,

'transfer' => <<<'TXT'
// TRANSFER UGD → RI (transfer-ugd-ke-ri-actions). SATU transaksi:
DB::transaction(function () {
    // 1. Buat header RI (rstxn_rihdrs, ri_status 'I') + kamar (rsmst_trfrooms)
    // 2. Biaya UGD sendiri → rstxn_ritempadmins
    //      tempadm_flag = 'UGD', tempadm_ref = rj_no (UGD), rihdr_no = RI baru
    //      Kolomnya TETAP: rj_admin, poli_price, acte_price, actp_price, actd_price,
    //      obat, lab, rad, other, rs_admin, ok   ← 'ok' ditambah 2026-08-01 supaya
    //      biaya Kamar Operasi ikut pindah, bukan hilang senyap.
    //          'ok' => $costs['kamarOperasi']
    // 3. Cascade biaya RJ: rstxn_ugdtempadmins → rstxn_ritempadmins (rihdr_no),
    //      salin kolom apa adanya ('ok' => $temp->ok), lalu HAPUS rstxn_ugdtempadmins
    // 4. Link tambahan: rstxn_ribiayaselamadugds (rj_no, ugd_no_rsri = rihdr_no, total_biayaugd)
    // 5. UGD status → 'I'; lockstatus pasien → RI
});

// LINK UTAMA UGD ↔ RI = baris rstxn_ritempadmins flag 'UGD'
//   (tempadm_ref = rj_no  →  rihdr_no).
// rstxn_ribiayaselamadugds = link tambahan — BISA KOSONG untuk transfer lama
//   (Oracle Dev 6i / dual-system) → jangan jadikan satu-satunya sumber.
TXT,

'transfer-rj-ugd' => <<<'TXT'
// TRANSFER RJ → UGD (transfer-rj-ke-ugd-actions). Pola sama, arah beda:
// 1. Buat header UGD (rstxn_ugdhdrs, rj_status 'A')
// 2. Biaya RJ → rstxn_ugdtempadmins (tempadm_flag='RJ', tempadm_ref=rj_no RJ)
// 3. Link: rstxn_ugdbiayaselamadirjs
// Beda dari UGD→RI: cara masuk dari rsmst_entryugds, TIDAK ada ruangan/bed.
//
// PENAMAAN — konvensi lama BERLAWANAN padahal bentuknya sama (transfer-X-Y):
//   transfer-ri-ugd = UGD→RI (tujuan-asal) | transfer-rj-ugd = RJ→UGD (asal-tujuan)
// Sejak 2026-07 dipakai kata "ke": transfer-ugd-ke-ri, transfer-rj-ke-ugd.
TXT,

'transfer-dokter-tarif' => <<<'TXT'
// DOKTER & TARIF SAAT TRANSFER — jangan salin mentah dari kunjungan asal.
//
// 1) DOKTER dipilih lewat <livewire:lov.dokter.lov-dokter target="...">.
//    resetTransferState() WAJIB mereset dokter: komponen di-mount sekali per
//    halaman & dipakai berulang → tanpa itu dokter pasien sebelumnya terbawa.
//    Default kini BEDA per arah (sejak 2026-07):
//    • RJ→UGD (transfer-rj-ke-ugd): default KOSONG + WAJIB pilih — guard di
//      transferKeUGD() menolak bila empty, TANPA fallback ke dokter RJ.
//      Disamakan dgn Daftar UGD yg dr_id-nya required.
//    • UGD→RI (transfer-ugd-ke-ri): default = dokter asal (UGD) + fallback saat
//      insert ($pilih ?: $hdr->dr_id). dr_id RI = dokter PENERIMA (lihat #2).
//
// 2) rstxn_rihdrs.dr_id = dokter PENERIMA, BUKAN DPJP.
//    ⚡daftar-ri menampilkannya sebagai "Penerima:"; "DPJP:" diambil dari
//    pengkajianAwalPasienRawatInap.levelingDokter (diisi di EMR, berlevel
//    Utama/RawatGabung, BISA >1 dokter). Satu kolom dr_id tak menampung itu.
//
// 3) TARIF ikut dokter & klaim TERPILIH, bukan disalin:
//    rstxn_ugdhdrs.poli_price NAMANYA menyesatkan → isinya TARIF UGD
//      (rsmst_doctors.ugd_price / ugd_price_bpjs). Daftar RJ mengisi kolom
//      senama dari poli_price. Aturan resmi: recomputeAdminPrices()
//      ⚡daftar-ugd-actions (Kronis → 0).
//    Tampilkan tarif di modal pakai method YANG SAMA dgn saat insert, supaya
//      angka di layar tak pernah beda dgn yang tersimpan.
//    rj_admin = 0 saat transfer: admin OB sudah dibayar di kunjungan asal.
//
// 4) rstxn_rihdrs.admin_status BUKAN FLAG — itu NOMINAL:
//      rsmst_parameters par_id=2 "ADMIN STATUS RI" (50.000) → SELALU dikenakan
//      rsmst_parameters par_id=3 "ADMIN USIA 14+" (25.000) → admin_age, via toggle
//    Keduanya dijumlahkan sebagai UANG di kasir-ri & PendapatanRsTrait
//      (NVL(admin_age,0) + NVL(admin_status,0) + ...).
//    Riwayat kolom = tarif: 20.000 → 30.000 → 50.000. Menulis '1'/'0' di situ
//      membuat RS menagih Rp 1 / kehilangan 50.000.
TXT,

'batal-transfer' => <<<'TXT'
// BATAL TRANSFER (kasir-ugd::batalTransferRI). Cari RI hasil transfer BERLAPIS:
$riHdrNo = DB::table('rstxn_ritempadmins')                 // 1) link UTAMA
    ->where('tempadm_flag', 'UGD')
    ->where('tempadm_ref', $this->rjNo)
    ->value('rihdr_no');

if (!$riHdrNo) {                                           // 2) fallback legacy
    $riHdrNo = DB::table('rstxn_ribiayaselamadugds')
        ->where('rj_no', $this->rjNo)->value('ugd_no_rsri');
}
if (!$riHdrNo) { toast('Tidak ada data transfer untuk UGD ini.'); return; }

// GUARD sebelum batal:
//   - RI masih status 'I' (belum diproses)
//   - RI belum ada transaksi: rivisits/rikonsuls/riactparams/riactdocs/rilabs/
//                             riradiologs/rioks/riobats/riothers/ripaymentdtls
//   - lab UGD tidak pending (checkLabPendingUGD)
// AKSI (satu transaksi + lockUGDRow):
//   - restore rstxn_ritempadmins (flag != 'UGD') → rstxn_ugdtempadmins
//   - hapus RI: ritempadmins / trfrooms / ribiayaselamadugds / rihdrs
//   - UGD → 'A'; lockstatus pasien → 'UGD'
TXT,

'batal-transaksi' => <<<'TXT'
// BATAL TRANSAKSI (batalTransaksi) — membatalkan PEMBAYARAN/PULANG, BUKAN admisi.
// Ada di kasir-rj / kasir-ugd / kasir-ri. Contoh RI:
DB::transaction(function () {
    $this->lockRIRow($riHdrNo);
    DB::table('rstxn_ripaymentpdtls')->where('rihdr_no', $riHdrNo)->delete();  // hapus payment
    DB::table('rstxn_rihdrs')->where('rihdr_no', $riHdrNo)->update([
        'ri_bayar' => 0, 'ri_diskon' => 0, 'status_pulang' => null,
        'payment_date' => null, 'exit_date' => null,
        'ri_status' => 'I',                                 // Pulang → kembali DIRAWAT
    ]);
    // buka end_date kamar terakhir (pasien 'kembali' menempati bed); lock pasien lagi
});
// Hasil: status balik ke Dirawat ('I'). Role: Admin | Supervisor Tu.
TXT,

'batal-inap' => <<<'TXT'
// BATAL INAP → status 'F' (kasir-ri::batalInap). SOFT-cancel admisi RI (record TETAP).
// Hanya boleh: status 'I' (Dirawat) + BUKAN dari transfer + BELUM ada transaksi apa pun.
DB::transaction(function () {
    $this->lockRIRow($riHdrNo);

    // guard 1: ri_status harus 'I' (bukan 'P'/'F')
    // guard 2: bukan RI hasil transfer — cek rstxn_ritempadmins flag 'UGD'/'RJ'
    //          (kalau ya → arahkan pakai "Batal Transfer" di kasir asal)
    // guard 3: belum ada rivisits/rikonsuls/riactparams/riactdocs/rilabs/
    //          riradiologs/rioks/riobats/riothers/ripaymentdtls

    DB::table('rstxn_rihdrs')->where('rihdr_no', $riHdrNo)->update(['ri_status' => 'F']);
    // bebaskan bed: trfroom end_date = SYSDATE
    // unlock pasien: lockstatus = '1'
    // appendAdminLogRI(...) — audit
});
// Beda dari: Batal Transaksi (Pulang→Dirawat 'I') & Batal Transfer (hapus RI, UGD→'A').
// Role: Admin | Supervisor Tu.
TXT,

'batal-sls' => <<<'TXT'
// BATAL TRANSAKSI APOTEK RI (administrasi-ri-resep / administrasi-kasir-ri::batalTransaksi).
// Membatalkan PEMBAYARAN RESEP. Header resep = imtxn_slshdrs (bukan rstxn_*hdrs).
// Status resep cuma 2: 'A' belum diproses kasir | 'L' sudah.

// GUARD (urut — yang murah dulu, baru sentuh DB):
if (!auth()->user()->hasAnyRole(['Apoteker','Admin','Tu'])) { toast(...); return; }   // role DI SERVER
if ($this->status !== 'L')            { toast('Transaksi belum diproses'); return; }
if (strtoupper($this->riStatus) === 'P') { toast('Pasien sudah pulang'); return; }

DB::transaction(function () {
    // anti-race: kunci baris, lalu BACA ULANG statusnya di dalam transaksi.
    // tanpa ini 2 kasir yang menekan Batal bersamaan sama-sama lolos guard di atas.
    DB::table('imtxn_slshdrs')->where('sls_no', $this->slsNo)->lockForUpdate()->first();
    $current = DB::table('imtxn_slshdrs')->where('sls_no', $this->slsNo)->first();
    if (strtoupper($current->status ?? 'A') !== 'L') {
        throw new \RuntimeException('Transaksi sudah dalam status belum diproses.');
    }

    DB::table('imtxn_slshdrs')->where('sls_no', $this->slsNo)->update([
        'status'    => 'A',        // L → A, resep aktif lagi & bisa dibayar ulang
        'sls_bayar' => null, 'sls_bon' => null,
        'bayar'     => null, 'sisa'    => null,
        'acc_id'    => null, 'waktu_selesai_pelayanan' => null,
        // emp_id sengaja TIDAK direset — jejak siapa terakhir mem-posting tetap dibutuhkan
    ]);

    // efek samping WAJIB ikut dicabut: sisa yang masuk Bon Inap
    DB::table('rstxn_ribonobats')->where('sls_no', $this->slsNo)->delete();
});

// sesudah sukses: samakan properti dgn DB (BUKAN null) + buang cache computed
$this->status = 'A';
$this->bayar  = null; $this->accId = null; $this->accName = null;
unset($this->isKasirPosted, $this->isObatLocked, $this->canEditJasa);
$this->recalcKasir();
$this->dispatch('ri-resep-refresh-after-antrian.saved');   // refresh list antrian

// BELUM ADA di Apotek RI: lapis kedua (setara Batal Inap → 'F') untuk membatalkan
// RESEPNYA sendiri. Resep salah yg belum dibayar → hapus obat satu per satu
// (removeObat), header imtxn_slshdrs tetap ada & tetap muncul di antrian.
TXT,

'edit-inline' => <<<'TXT'
// SEL TABEL YANG LANGSUNG TERSIMPAN (room-ri: Hari, tarif, tgl Mulai/Selesai).
// Nilai dikirim lewat ARGUMEN AKSI, bukan wire:model — baris tabel tak punya properti per-sel.
// Blade:
//   x-on:change="$wire.updateTanggalKamar({{ trfr_no }}, 'end_date', $event.target.value)"

public function updateTanggalKamar(int $trfrNo, string $kolom, ?string $nilai): void
{
    // 1. whitelist kolom — nilai tak terduga DITOLAK (jangan jatuh ke else)
    if (!in_array($kolom, ['start_date', 'end_date'], true)) return;

    // 2. guard lock; tiap jalur gagal WAJIB findData() lagi supaya layar balik ke isi DB
    if ($this->isFormLocked) { /* toast + findData */ return; }

    // 3. validasi pakai RULE repo, bukan cek manual Carbon
    Validator::make(
        ['tanggal' => $nilai === '' ? null : $nilai],
        ['tanggal' => 'bail|required|date_format:d/m/Y H:i:s'],   // nullable bila boleh kosong
    );
    // createFromFormat SAJA tidak cukup: 32/13/2026 diterima lalu digeser diam-diam.

    // 4. tak ada perubahan → diam (tanpa query, tanpa toast)
    if ($nilaiLama === $nilai) return;

    // 5. KOLOM TURUNAN: tiru rumus proses yang membuatnya (pindah-kamar-ri)
    //    ROUND(selesai - mulai) lalu max(1, ...) → pindah < 1 hari TETAP 1 hari.
    //    max(0, ...) akan MENGHAPUS tagihan sehari saat jam kamar transit dikoreksi.
    $hariBaru = max(1, (int) round(($selesai->getTimestamp() - $mulai->getTimestamp()) / 86400));
    //    Carbon 3: JANGAN diffInSeconds($other, false) — tandanya terbalik.

    DB::transaction(function () use (...) {
        $this->lockRIRow($this->riHdrNo);
        DB::table('rsmst_trfrooms')->where('trfr_no', $trfrNo)->update([
            $kolom => DB::raw("to_date('...', 'dd/mm/yyyy hh24:mi:ss')"),
            'day'  => $hariBaru,
        ]);
        // audit DI DALAM transaksi — rollback tak boleh meninggalkan log
        $this->appendAdminLogRI($this->riHdrNo, "Ubah tanggal Selesai kamar #{$trfrNo}: {lama} → {baru}, Hari → {$hariBaru}");
    });

    $this->findData($this->riHdrNo);
    $this->dispatch('administrasi-ri.updated');
}
TXT,

'peta-modul' => <<<'TXT'
// PETA MODUL — 3 JALUR KUNJUNGAN + 4 MODUL LAYANAN
//
// Jalur kunjungan (punya kasir & tagihan sendiri):
//   RJ  rstxn_rjhdrs   (rj_no)      rj_status  'A' aktif -> 'L' dibayar / 'I' ditransfer
//   UGD rstxn_ugdhdrs  (rj_no)      rj_status  idem
//   RI  rstxn_rihdrs   (rihdr_no)   ri_status  'I' dirawat -> 'P' pulang / 'F' batal
//
// Modul layanan menaruh biayanya ke tabel PER JALUR:
//                     RJ                 UGD                 RI
//   Laborat      rstxn_rjlabs       rstxn_ugdlabs       rstxn_rilabs
//   Radiologi    rstxn_rjrads       rstxn_ugdrads       rstxn_riradiologs
//   K. Operasi   rstxn_rjoks        rstxn_ugdoks        rstxn_rioks
//   Apotek       rstxn_rjobats      rstxn_ugdobats      rstxn_riobats
//
// TIGA POLA BERBEDA — jangan disamaratakan:
//
// (a) PUNYA HEADER ORDER + TRANSFER  — Laborat, Kamar Operasi
//     Header sendiri (lbtxn_checkuphdrs / rstxn_oks) ber-`status_rjri` + `ref_no`.
//     `ref_no` = rj_no untuk RJ/UGD, rihdr_no untuk RI.
//     Biaya BELUM masuk tagihan sampai petugas menekan transfer:
//       Lab : checkup_status 'P' -> 'C'/'H'
//       OK  : ok_status      'A' -> 'L'   (tombol Trf Biaya-RJ/UGD/INAP)
//     Karena ada jeda itu, WAJIB ada guard: kunjungan tak boleh ditutup/dipulangkan
//     selagi masih ada order menggantung (checkLabPending* / checkOkPending*).
//
// (b) LANGSUNG KE TABEL BIAYA — Radiologi
//     Tidak ada header order. Order dari EMR langsung insert rstxn_*rads, jadi
//     biayanya seketika masuk tagihan. Modul Radiologi hanya meng-upload hasil ke
//     baris yang sama. Tanpa jeda -> tidak butuh guard pending.
//
// (c) LEWAT RESEP — Apotek
//     E-resep -> rstxn_*obats. RI punya jalur tambahan (SLS/penjualan) dengan
//     model batal sendiri, lihat bab "Batal Transaksi Apotek RI (SLS)".
//
// ATURAN: satu modul layanan = satu tabel biaya per jalur. JANGAN menitipkan ke
// rstxn_*others hanya karena tak mau bikin tabel — di jurnal, pendapatannya akan
// menyamar jadi pendapatan lain-lain. (Keputusan user, 2026-07-31, saat OK.)
TXT,

'hilir-biaya' => <<<'TXT'
// ENAM LAPIS HILIR — WAJIB DISISIR TIAP MENAMBAH POS BIAYA BARU
// Melewatkan satu lapis = uang hilang tanpa jejak. Semuanya pernah terjadi.
//
// 1. calculate{RJ,UGD,RI}Costs()      <- dipakai KASIR *dan* TRANSFER antar-unit
// 2. rstxn_{ugd,ri}tempadmins         <- kolomnya TETAP; tambah kolom + petakan di
//                                         2 komponen transfer (insert $costs[...]
//                                         dan cascade $temp->...)
// 3. ~11 ekspresi penjumlah kolom tetap tempadmins  (kasir/administrasi RI & UGD,
//    transfer-ugd, trf-ugd-rj-ri, PendapatanRsTrait x2, PiutangPasienTrait)
// 4. Tab + $sum* di Administrasi, rjTotal di Kasir
// 5. KWITANSI — RJ/UGD TIDAK dihitung di PHP, dibaca dari view
//    RSVIEW_RJSTRS / RSVIEW_UGDSTRS (ORDER BY txn_no). RI lewat calculateRICosts().
// 6. JURNAL TKVIEW_ACCOUNTS — sepasang cabang per pos:
//       piutang unit (RJ1 4.1AA / UGD1 4.1BB / RI1 4.1CA)  <->  akun pendapatan pos
//    plus RSVIEW_NEWDOCSALARIES bila pos itu menggerakkan gaji dokter.
//
// CARA MENCARINYA: grep baris yang menyebut `rs_admin` BERSAMA `obat|lab|rad`.
// JANGAN grep satu pola nvl() — penulisannya tak seragam (ada berspasi, ada rapat);
// calculateRICosts() pernah lolos persis karena itu.
//
// SALDO KAS AMAN dari perubahan pos biaya: akun kas hanya muncul di cabang BAYAR
// (rumus halaman Cek Saldo Kas = txn_acc_k = akun, SUM(K - D)). Yang bergeser
// adalah sisi PENDAPATAN & PIUTANG.
//
// Cabang jurnal baru WAJIB memakai EXISTS ke tabel sumbernya — cabang lama
// menerbitkan 1 baris per kunjungan walau nol; menirunya membengkakkan
// TKVIEW_ACCOUNTS dari 26 juta jadi 44 juta baris.
//
// MENGUJI JURNAL: ukur SELISIH sebelum vs sesudah, jangan total. Label barisnya
// hanya 'LAB ('||rj_no||'/'||reg_no||')' dan nomor RJ lama BERIRISAN dengan nomor
// UGD (203858 ada di kedua tabel) -> filter LIKE '%(no/%' menjaring unit lain.
TXT,


        ];
    }
};

?>

<div>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=source-sans-3:400,500,600,700|jetbrains-mono:400,500&display=swap" rel="stylesheet" />
    <style>[x-cloak] { display: none !important; }</style>

    @php
        $snip = $this->snippets();

        // Model 2 sisi: Sisi 1 = Konsep & Alur Visual, Sisi 2 = Coding.
        $sides = [
            'konsep' => [
                'label' => 'Konsep & Alur',
                'desc'  => 'Untuk siapa saja — konsep administrasi, status, biaya & alur visual',
                'groups' => [
                    'Pengantar' => [
                        'pendahuluan' => 'Pendahuluan',
                        'status'      => 'Model Status Transaksi',
                        'biaya'       => 'Struktur Biaya & Total',
                    ],
                    'Alur Visual' => [
                        'flow'       => 'Alur Visual (Flow)',
                        'peta-modul' => 'Peta Modul & Aliran Biaya',
                    ],
                ],
            ],
            'coding' => [
                'label' => 'Coding',
                'desc'  => 'Untuk programmer — kasir, transfer, model batal & guard (kode nyata)',
                'groups' => [
                    'Administrasi' => [
                        'kasir' => 'Alur Kasir sampai Pulang',
                        'edit-inline' => 'Edit Inline Tabel Biaya',
                    ],
                    'Transfer & Batal' => [
                        'transfer'        => 'Transfer UGD → RI',
                        'batal-transfer'  => 'Batal Transfer',
                        'batal-transaksi' => 'Batal Transaksi (Pulang)',
                        'batal-inap'      => 'Batal Inap → F',
                        'batal-sls'       => 'Batal Transaksi Apotek RI (SLS)',
                        'matriks'         => 'Matriks Batal',
                        'guard-transfer'  => 'Guard & Konsistensi Transfer',
                    ],
                    'Referensi' => [
                        'hilir-biaya' => 'Menambah Pos Biaya Baru',
                        'ranjau'    => 'Ranjau Umum',
                        'glosarium' => 'Glosarium',
                    ],
                ],
            ],
        ];

        // Turunan untuk Alpine.
        $labels = [];
        $sideKeys = [];       // { konsep: [key,...], coding: [key,...] } — urutan prev/next per sisi
        $sectionSide = [];    // { key: side }
        foreach ($sides as $sideKey => $side) {
            $sideKeys[$sideKey] = [];
            foreach ($side['groups'] as $items) {
                foreach ($items as $k => $lbl) {
                    $labels[$k] = $lbl;
                    $sideKeys[$sideKey][] = $k;
                    $sectionSide[$k] = $sideKey;
                }
            }
        }
    @endphp

    <div class="ds" style="min-height:100vh"
        x-data='{
            side: "konsep",
            sides: @json($sideKeys),
            labels: @json($labels),
            sectionSide: @json($sectionSide),
            section: "pendahuluan",
            curOrder() { return this.sides[this.side] || [] },
            idx() { return this.curOrder().indexOf(this.section) },
            go(s) {
                this.section = s;
                this.side = this.sectionSide[s] || this.side;
                history.replaceState(null, "", "#" + s);
                window.scrollTo({ top: 0, behavior: "smooth" });
            },
            switchSide(sd) {
                if (this.side === sd) return;
                this.side = sd;
                this.section = this.sides[sd][0];
                history.replaceState(null, "", "#" + this.section);
                window.scrollTo({ top: 0, behavior: "smooth" });
            },
            init() {
                const h = window.location.hash.slice(1);
                if (this.labels[h]) { this.section = h; this.side = this.sectionSide[h] || "konsep"; }
            }
        }'>
        <div class="ds-section" style="padding-top:32px; padding-bottom:96px">

            {{-- ============ HEADER ============ --}}
            <div class="flex items-center justify-between gap-2">
                <div class="flex items-center gap-2">
                    <span class="ds-spike"></span>
                    <span class="ds-title-sm" style="color:var(--ink)">RSI&nbsp;Madinah</span>
                    <a href="{{ route('panduan-dev') }}" wire:navigate
                        class="ds-body-sm hover:underline" style="color:var(--muted-soft)">/ Standarisasi UI</a>
                    <span class="ds-body-sm" style="color:var(--muted-soft)">/ Koding Administrasi</span>
                </div>
                <div class="flex items-center gap-2">
                    <a href="{{ route('panduan-dev.koding-transaksi') }}" wire:navigate
                        class="ds-btn ds-btn-secondary" style="height:34px; padding:6px 12px; font-size:13px">← Tutorial Transaksi</a>
                    <x-theme-toggle />
                </div>
            </div>

            {{-- ============ TOGGLE 2 SISI ============ --}}
            <div class="mt-8">
                <div class="inline-flex p-1 rounded-2xl" style="background:var(--surface-card); border:1px solid var(--hairline)">
                    @foreach ($sides as $sideKey => $side)
                        <button type="button" x-on:click="switchSide('{{ $sideKey }}')"
                            class="flex items-center gap-2 px-5 py-2.5 rounded-xl transition-colors"
                            :class="side === '{{ $sideKey }}' ? 'font-semibold' : 'font-medium'"
                            :style="side === '{{ $sideKey }}' ? 'background:var(--primary); color:#fff' : 'color:var(--body)'">
                            <span class="text-xs font-bold" :style="side === '{{ $sideKey }}' ? 'opacity:.85' : 'opacity:.5'">Sisi {{ $loop->iteration }}</span>
                            <span class="text-sm">{{ $side['label'] }}</span>
                        </button>
                    @endforeach
                </div>
                <div class="mt-2">
                    @foreach ($sides as $sideKey => $side)
                        <p class="ds-caption" style="color:var(--muted)" x-show="side === '{{ $sideKey }}'" x-cloak>{{ $side['desc'] }}</p>
                    @endforeach
                </div>
            </div>

            <div class="mt-6 grid grid-cols-1 gap-10 lg:grid-cols-[240px_1fr]">

                {{-- ============ SIDEBAR ============ --}}
                <aside class="self-start lg:sticky lg:top-24">
                    @foreach ($sides as $sideKey => $side)
                        <div x-show="side === '{{ $sideKey }}'" x-cloak>
                            @foreach ($side['groups'] as $group => $items)
                                <div class="mb-6">
                                    <div class="ds-caption-up mb-2 px-3">{{ $group }}</div>
                                    <div class="space-y-0.5">
                                        @foreach ($items as $key => $label)
                                            <button type="button" x-on:click="go('{{ $key }}')"
                                                class="block w-full px-3 py-1.5 text-sm text-left rounded-lg transition-colors"
                                                :class="section === '{{ $key }}' ? 'font-semibold' : 'font-normal'"
                                                :style="section === '{{ $key }}'
                                                    ? 'background:var(--surface-card); color:var(--ink)'
                                                    : 'color:var(--body)'">
                                                {{ $label }}
                                            </button>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endforeach

                    <div class="px-3 pt-4" style="border-top:1px solid var(--hairline)">
                        <div class="ds-caption" style="color:var(--muted-soft)">
                            Acuan kode: <span class="ds-code">transaksi/{rj,ugd,ri}/administrasi-*</span><br>
                            Prasyarat: <a href="{{ route('panduan-dev.koding-transaksi') }}" wire:navigate
                                class="hover:underline" style="color:var(--primary)">Tutorial Koding Transaksi</a>
                        </div>
                    </div>
                </aside>

                {{-- ============ KONTEN ============ --}}
                <main style="min-width:0">

                    @include('pages.panduan-dev.koding-administrasi.koding-administrasi-dasar')

                    @include('pages.panduan-dev.koding-administrasi.koding-administrasi-kasir-transfer')

                    @include('pages.panduan-dev.koding-administrasi.koding-administrasi-batal')

                    @include('pages.panduan-dev.koding-administrasi.koding-administrasi-matriks-guard')


                    @include('pages.panduan-dev.koding-administrasi.koding-administrasi-peta-hilir')

                    @include('pages.panduan-dev.koding-administrasi.koding-administrasi-penutup')

                    {{-- ============ PREV / NEXT ============ --}}
                    <div class="flex items-center justify-between gap-3 mt-12 pt-6" style="border-top:1px solid var(--hairline)">
                        <button type="button" class="ds-btn ds-btn-secondary"
                            x-show="idx() > 0" x-cloak
                            x-on:click="go(curOrder()[idx() - 1])">
                            ← <span x-text="labels[curOrder()[idx() - 1]]"></span>
                        </button>
                        <span x-show="idx() === 0"></span>
                        {{-- di akhir sisi Konsep: ajak lanjut ke sisi Coding --}}
                        <button type="button" class="ds-btn ds-btn-primary"
                            x-show="idx() < curOrder().length - 1" x-cloak
                            x-on:click="go(curOrder()[idx() + 1])">
                            <span x-text="labels[curOrder()[idx() + 1]]"></span> →
                        </button>
                        <button type="button" class="ds-btn ds-btn-primary"
                            x-show="side === 'konsep' && idx() === curOrder().length - 1" x-cloak
                            x-on:click="switchSide('coding')">
                            Lanjut ke Sisi 2 — Coding →
                        </button>
                    </div>

                </main>
            </div>
        </div>
    </div>
</div>
