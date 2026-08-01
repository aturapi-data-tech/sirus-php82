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

                    {{-- ====== 01 PENDAHULUAN ====== --}}
                    <section x-show="section === 'pendahuluan'" x-cloak>
                        <div class="ds-eyebrow mb-3">01 — Mulai</div>
                        <h1 class="ds-display-md mb-4">Konsep Administrasi &amp; Batal</h1>
                        <p class="ds-body-md mb-4" style="max-width:62ch">
                            <strong>Administrasi (kasir)</strong> adalah tahap akhir perjalanan pasien:
                            menghitung seluruh pos biaya, memproses pembayaran, dan memulangkan pasien.
                            Tiga jalur — <strong>RJ</strong>, <strong>UGD</strong>, <strong>RI</strong> — polanya mirip
                            tapi tak identik; RI paling kaya (billing per-item, transfer kamar, transfer masuk dari UGD/RJ).
                        </p>
                        <p class="ds-body-md mb-4" style="max-width:62ch">
                            Bab-bab di sini merangkum <strong>model status</strong>, <strong>struktur biaya</strong>,
                            <strong>alur kasir sampai pulang</strong>, serta <strong>tiga model pembatalan</strong>
                            yang sering tertukar: <em>Batal Transaksi</em>, <em>Batal Transfer</em>, dan <em>Batal Inap</em>.
                        </p>

                        <div class="grid grid-cols-1 gap-4 mt-8 sm:grid-cols-3">
                            <div class="ds-card" style="padding:20px">
                                <div class="ds-title-sm mb-1">Batal Transaksi</div>
                                <div class="ds-body-sm">Batalkan <strong>pembayaran/pulang</strong>. Status kembali ke sebelum-bayar (RI: Pulang→Dirawat).</div>
                            </div>
                            <div class="ds-card" style="padding:20px">
                                <div class="ds-title-sm mb-1">Batal Transfer</div>
                                <div class="ds-body-sm">Batalkan <strong>transfer UGD→RI</strong>. RI dihapus, UGD kembali Aktif ('A').</div>
                            </div>
                            <div class="ds-card" style="padding:20px">
                                <div class="ds-title-sm mb-1">Batal Inap</div>
                                <div class="ds-body-sm">Batalkan <strong>admisi RI</strong> → status <span class="ds-code">'F'</span> (soft, record tetap). Hanya bila belum ada transaksi.</div>
                            </div>
                        </div>

                        <div class="ds-card-outline mt-8" style="padding:16px 20px">
                            <span class="ds-spike" style="vertical-align:middle"></span>
                            <span class="ds-body-sm" style="color:var(--body-strong)">
                                <strong>Prinsip semua batal:</strong> jalankan dalam <span class="ds-code">DB::transaction</span> +
                                <span class="ds-code">lock*Row</span>, verifikasi status &amp; guard dulu, tulis audit
                                (<span class="ds-code">appendAdminLog*</span>), dan gate role sesuai aksi
                                (Batal Inap/Kunjungan RI: Admin / Supervisor Tu; Batal Transfer &amp; Transaksi RJ/UGD:
                                Admin, Tu, Perawat, Manager Umum, Supervisor Tu).
                            </span>
                        </div>
                    </section>

                    {{-- ====== 02 STATUS ====== --}}
                    <section x-show="section === 'status'" x-cloak>
                        <div class="ds-eyebrow mb-3">02 — Mulai</div>
                        <h1 class="ds-display-md mb-4">Model Status Transaksi</h1>
                        <p class="ds-body-md mb-4" style="max-width:62ch">
                            Batal = memindahkan status. Kenali dulu kode status tiap jalur.
                        </p>

                        <div class="ds-card-outline mb-6" style="padding:0; overflow-x:auto">
                            <table class="ds-table">
                                <thead><tr><th>Jalur</th><th>Kolom</th><th>Nilai</th><th>Arti</th></tr></thead>
                                <tbody>
                                    <tr><td class="ds-td-strong">RJ / UGD</td><td class="ds-td-class">rj_status / txn_status</td><td class="ds-td-class">A</td><td class="ds-body-sm">Aktif / Antri</td></tr>
                                    <tr><td class="ds-body-sm">RJ / UGD</td><td class="ds-td-class">rj_status / txn_status</td><td class="ds-td-class">I</td><td class="ds-body-sm">Transfer Inap (terkunci)</td></tr>
                                    <tr><td class="ds-td-strong">RI</td><td class="ds-td-class">ri_status</td><td class="ds-td-class" style="color:var(--primary)">I</td><td class="ds-body-sm"><strong>Dirawat</strong> (default admisi)</td></tr>
                                    <tr><td class="ds-body-sm">RI</td><td class="ds-td-class">ri_status</td><td class="ds-td-class">P</td><td class="ds-body-sm">Pulang (sudah bayar)</td></tr>
                                    <tr><td class="ds-body-sm">RI</td><td class="ds-td-class">ri_status</td><td class="ds-td-class" style="color:#dc2626">F</td><td class="ds-body-sm"><strong>Batal</strong> (dikecualikan laporan)</td></tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="ds-card-dark" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">Ringkasan kode status</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['status'] }}</pre>
                        </div>

                        <div class="ds-card-outline mt-6" style="padding:16px 20px">
                            <span class="ds-spike" style="vertical-align:middle"></span>
                            <span class="ds-body-sm" style="color:var(--body-strong)">
                                <strong>ri_status='F' hanya DIBACA</strong> oleh laporan (SIRS RL, manajemen) yang
                                mengecualikannya. Menandai batal = <em>menulis</em> 'F' (soft), bukan menghapus baris —
                                agar jejak audit &amp; statistik tetap konsisten.
                            </span>
                        </div>
                    </section>

                    {{-- ====== 03 BIAYA ====== --}}
                    <section x-show="section === 'biaya'" x-cloak>
                        <div class="ds-eyebrow mb-3">03 — Mulai</div>
                        <h1 class="ds-display-md mb-4">Struktur Biaya &amp; Total</h1>
                        <p class="ds-body-md mb-4" style="max-width:62ch">
                            Total tagihan = penjumlahan pos biaya dari tabel-tabel transaksi per jalur.
                            Perhitungan dibuat <strong>reusable</strong> supaya kasir &amp; transfer memakai angka yang sama.
                        </p>

                        <div class="ds-card-dark mt-2" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">Pola perhitungan biaya (calculateRJCosts)</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['biaya'] }}</pre>
                        </div>

                        <div class="ds-card-outline mt-6" style="padding:16px 20px">
                            <span class="ds-spike" style="vertical-align:middle"></span>
                            <span class="ds-body-sm" style="color:var(--body-strong)">
                                <strong>Tabel jembatan biaya transfer = <span class="ds-code">rstxn_ritempadmins</span></strong>
                                (kolom <span class="ds-code">tempadm_flag</span>). Saat UGD/RJ transfer ke RI, biaya asalnya
                                ikut disalin ke sini (flag 'UGD'/'RJ') supaya total RI mencakup biaya sebelum masuk inap.
                            </span>
                        </div>

                        <div class="ds-card-outline mt-4" style="padding:16px 20px; border-color:#d97706">
                            <span class="ds-spike" style="vertical-align:middle"></span>
                            <span class="ds-body-sm" style="color:var(--body-strong)">
                                <strong>Kolomnya TETAP, bukan bebas.</strong>
                                <span class="ds-code">rstxn_*tempadmins</span> menampung biaya per kolom
                                (<span class="ds-code">rj_admin, poli_price, acte_price, actp_price, actd_price,
                                obat, lab, rad, other, rs_admin, ok</span>) — bukan per baris pos. Karena itu
                                menambah komponen di <span class="ds-code">calculate*Costs()</span> tanpa menambah
                                kolomnya di sini membuat angka itu <em>ikut ditagih di kasir tetapi hilang saat
                                pasien dipindah unit</em>. Kolom <span class="ds-code">ok</span> ditambahkan
                                2026-08-01 untuk biaya Kamar Operasi. Lihat bab
                                <strong>Menambah Pos Biaya Baru</strong>.
                            </span>
                        </div>
                    </section>

                    {{-- ====== 04 ALUR VISUAL (FLOW) ====== --}}
                    <section x-show="section === 'flow'" x-cloak>
                        <div class="ds-eyebrow mb-3">04 — Alur Visual</div>
                        <h1 class="ds-display-md mb-4">Alur Visual (Flowchart)</h1>
                        <p class="ds-body-md mb-2" style="max-width:64ch">
                            Bab ini menceritakan <strong>perjalanan seorang pasien</strong> — dari mendaftar,
                            dilayani, sampai pulang &amp; membayar — dengan gambar sederhana. Semua di sini
                            pakai <strong>bahasa sehari-hari</strong>, tanpa istilah teknis.
                        </p>
                        <p class="ds-caption mb-8" style="color:var(--muted)">
                            Butuh detail kode / nama tabel / aturan teknis? Buka <strong>Sisi 2 — Coding</strong> (tombol di atas).
                        </p>

                        @php
                            $flowBox = function ($tone) {
                                return match ($tone) {
                                    'entry' => 'padding:10px 14px; border-color:var(--primary)',
                                    'opt'   => 'padding:10px 14px; border-style:dashed; border-color:#d97706',
                                    'cash'  => 'padding:10px 14px; border-color:#059669',
                                    'done'  => 'padding:10px 14px; border-color:#059669; background:rgba(5,150,105,0.06)',
                                    default => 'padding:10px 14px',
                                };
                            };
                            $arrow = '<span class="ds-code" style="color:var(--primary); font-size:16px">▶</span>';
                        @endphp

                        {{-- ===== 1. TIGA JALUR (RJ / UGD / RI) ===== --}}
                        <h2 class="ds-title-lg mb-2">1. Tiga jenis transaksi (tiga jalur pelayanan)</h2>
                        <p class="ds-body-md mb-4" style="max-width:64ch">
                            Ada <strong>tiga jenis pelayanan</strong>, masing-masing punya alur &amp; kasir sendiri:
                            <strong>Rawat Jalan (RJ)</strong>, <strong>UGD</strong>, dan <strong>Rawat Inap (RI)</strong>.
                        </p>

                        @php
                            $jalurFlows = [
                                ['RJ · Rawat Jalan', 'pasien poli — pulang hari itu juga', [
                                    ['Daftar', 'di loket', 'entry'],
                                    ['Diperiksa Dokter', 'di poli', 'main'],
                                    ['Lab / Rontgen', 'bila perlu', 'opt'],
                                    ['Ambil Obat', 'di apotek', 'main'],
                                    ['Kasir RJ', 'bayar', 'cash'],
                                    ['Pulang', '', 'done'],
                                ]],
                                ['UGD · Gawat Darurat', 'pasien darurat — bisa pulang atau dirawat', [
                                    ['Daftar UGD', 'di IGD', 'entry'],
                                    ['Triase &amp; Ditangani', 'sesuai kegawatan', 'main'],
                                    ['Lab / Rontgen', 'bila perlu', 'opt'],
                                    ['Ambil Obat', 'di apotek', 'main'],
                                    ['Kasir UGD', 'bayar', 'cash'],
                                    ['Pulang', 'atau transfer ke Rawat Inap', 'done'],
                                ]],
                                ['RI · Rawat Inap', 'pasien menginap — biaya dihitung per hari / per-item', [
                                    ['Masuk', 'daftar / transfer', 'entry'],
                                    ['Dirawat', 'visit · obat · lab · tindakan tiap hari', 'main'],
                                    ['Kasir RI', 'dijumlah saat mau pulang', 'cash'],
                                    ['Pulang', 'lunas / bon', 'done'],
                                ]],
                            ];
                        @endphp

                        @foreach ($jalurFlows as [$namaJalur, $ketJalur, $steps])
                            <div class="ds-card-outline mb-4" style="padding:14px 16px">
                                <div class="flex flex-wrap items-baseline gap-x-2 mb-2">
                                    <span class="ds-title-sm" style="color:var(--primary)">{{ $namaJalur }}</span>
                                    <span class="ds-caption" style="color:var(--muted)">— {{ $ketJalur }}</span>
                                </div>
                                <div class="flex flex-wrap items-center gap-2">
                                    @foreach ($steps as $i => [$judul, $ket, $tone])
                                        @if ($i > 0) {!! $arrow !!} @endif
                                        <span class="ds-card-outline" style="{{ $flowBox($tone) }}; background:var(--canvas)">
                                            <span class="block text-sm font-semibold" style="color:var(--ink)">{!! $judul !!}</span>
                                            @if ($ket !== '')<span class="block text-xs" style="color:var(--muted)">{!! $ket !!}</span>@endif
                                        </span>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach

                        <p class="ds-caption mb-8" style="color:var(--muted)">
                            <span style="color:#d97706">▦ kotak garis putus-putus</span> = langkah <strong>opsional</strong> (cuma bila perlu lab/rontgen).
                            <strong>RJ &amp; UGD mirip</strong> — selesai di hari yang sama. <strong>RI beda</strong> — pasien
                            menginap, jadi biaya dikumpulkan tiap hari &amp; baru ditotal saat pulang. Ketiganya bisa
                            <strong>lunas</strong> (dibayar penuh) atau <strong>bon</strong> (dibayar sebagian).
                        </p>

                        <div class="ds-card-outline mb-8" style="padding:16px 20px">
                            <span class="ds-spike" style="vertical-align:middle"></span>
                            <span class="ds-body-sm" style="color:var(--body-strong)">
                                <strong>Khusus rawat inap: resep apoteknya transaksi terpisah.</strong>
                                Obat pasien inap ditebus lewat <strong>Apotek RI</strong> yang punya nomor
                                (<span class="ds-code">No SLS</span>), antrean, dan <strong>kasir sendiri</strong> —
                                terpisah dari kasir RI yang menghitung biaya kamar &amp; tindakan saat pulang.
                                Satu pasien inap bisa punya banyak resep. Sisa yang belum dibayar di kasir apotek
                                masuk <strong>Bon Inap</strong>, lalu ditagih saat pasien pulang.
                            </span>
                        </div>

                        {{-- ===== 2. TRANSFER (PINDAH TINGKAT PELAYANAN) ===== --}}
                        <h2 class="ds-title-lg mb-2">2. Kalau pasien dipindah (transfer)</h2>
                        <div class="flex flex-wrap items-center gap-2 mb-3">
                            @foreach ([
                                ['Rawat Jalan', 'poli', 'entry'],
                                ['UGD', 'kondisi darurat', 'main'],
                                ['Rawat Inap', 'perlu dirawat', 'main'],
                                ['Kasir', 'biaya semua tahap dijumlah', 'cash'],
                                ['Pulang', '', 'done'],
                            ] as $i => [$judul, $ket, $tone])
                                @if ($i > 0) {!! $arrow !!} @endif
                                <span class="ds-card-outline" style="{{ $flowBox($tone) }}">
                                    <span class="block text-sm font-semibold" style="color:var(--ink)">{{ $judul }}</span>
                                    <span class="block text-xs" style="color:var(--muted)">{!! $ket !!}</span>
                                </span>
                            @endforeach
                        </div>
                        <p class="ds-body-md mb-2" style="max-width:64ch">
                            Kalau kondisi memburuk, pasien bisa <strong>dipindah</strong> ke pelayanan yang lebih tinggi —
                            dari <strong>poli ke UGD</strong>, lalu dari <strong>UGD ke rawat inap</strong>.
                        </p>
                        <div class="ds-card-outline mb-8" style="padding:14px 18px; border-color:#059669">
                            <span class="ds-body-sm" style="color:var(--body-strong)">
                                💡 <strong>Yang penting: tagihan ikut pindah.</strong> Anggap saja seperti membawa
                                <strong>keranjang belanja</strong> — isinya tidak berkurang saat pindah kasir. Jadi biaya
                                poli &amp; UGD <strong>otomatis sudah termasuk</strong> saat pasien membayar di rawat inap.
                                Tidak ada biaya yang tertinggal atau hilang.
                            </span>
                        </div>

                        {{-- ===== 3. PEMBATALAN ===== --}}
                        <h2 class="ds-title-lg mb-2">3. Kalau ada yang perlu dibatalkan</h2>
                        <p class="ds-body-md mb-4" style="max-width:64ch">
                            Kadang ada kekeliruan yang harus dikoreksi. Ada <strong>3 cara membatalkan</strong>,
                            dipilih sesuai keadaan pasien:
                        </p>
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3 mb-4">
                            @foreach ([
                                ['Batal Pembayaran', 'Batal Transaksi', 'Pasien sudah terlanjur <strong>dibayar/dipulangkan</strong>, tapi mau dikoreksi. Pembayaran dibatalkan, status kembali seperti <strong>belum bayar</strong> (bisa diproses ulang).'],
                                ['Batal Kunjungan', 'Batal Kunjungan / Inap', 'Pasien terlanjur <strong>didaftarkan</strong> tapi ternyata batal datang / salah input. Ditandai <strong>"Batal"</strong> — datanya <strong>tidak dihapus</strong>, cuma dicap batal (jejak tetap ada).'],
                                ['Batal Perpindahan', 'Batal Transfer', 'Pemindahan (mis. UGD → rawat inap) ternyata keliru. Perpindahan dibatalkan, <strong>tagihan dikembalikan</strong> ke tempat asal, tempat asal aktif lagi.'],
                            ] as [$tag, $judul, $ket])
                                <div class="ds-card-outline" style="padding:16px 18px; border-color:#dc2626">
                                    <div class="ds-caption-up mb-1" style="color:#dc2626">{{ $tag }}</div>
                                    <div class="ds-title-sm mb-2">{{ $judul }}</div>
                                    <div class="ds-body-sm">{!! $ket !!}</div>
                                </div>
                            @endforeach
                        </div>
                        <p class="ds-caption mb-8" style="color:var(--muted)">
                            Kalau pasien sudah pulang lalu ingin dibatalkan total: <strong>batalkan pembayaran dulu</strong>
                            (kembali aktif), <strong>baru batalkan kunjungannya</strong>. Untuk pasien hasil pindahan,
                            gunakan <strong>Batal Perpindahan</strong>, bukan Batal Kunjungan.
                        </p>

                        <div class="ds-card-outline mb-8" style="padding:16px 20px; border-color:#d97706">
                            <div class="ds-caption-up mb-1" style="color:#d97706">Resep Apotek RI</div>
                            <div class="ds-body-sm" style="color:var(--body-strong)">
                                Untuk resep rawat inap, yang bisa dibatalkan <strong>baru pembayarannya</strong> —
                                resep kembali berstatus belum diproses kasir dan bisa dibayar ulang.
                                <strong>Resepnya sendiri belum bisa dibatalkan.</strong> Kalau resep terlanjur salah
                                dan belum dibayar, obatnya harus dihapus satu per satu; nomor resepnya tetap ada dan
                                tetap muncul di antrean apotek. Ini beda dari kunjungan RJ/UGD/RI yang sudah punya
                                <strong>Batal Kunjungan</strong>.
                            </div>
                        </div>

                        {{-- ===== ATURAN MAIN (guards dalam bahasa awam) ===== --}}
                        <h2 class="ds-title-lg mb-3">Aturan main biar data tetap rapi</h2>
                        <div class="space-y-3 mb-4">
                            @foreach ([
                                ['🧪', 'Belum bisa dibayar/dipulangkan kalau hasil lab belum keluar.', 'Supaya tagihan pasti sudah lengkap sebelum pasien membayar. Berlaku di rawat jalan, UGD, maupun rawat inap.'],
                                ['🧹', 'Membatalkan hanya boleh kalau BELUM ada tindakan, obat, atau pembayaran.', 'Kalau pasien sudah dilayani (ada obat/lab/tindakan), tak bisa asal dibatalkan — biar tidak ada biaya yang hilang begitu saja.'],
                                ['📌', 'Membatalkan = memberi cap "Batal", bukan menghapus.', 'Datanya tetap tersimpan untuk audit. Laporan otomatis mengeluarkan data yang berstatus Batal, jadi tidak ikut dihitung.'],
                                ['🔒', 'Yang boleh membatalkan dibatasi sesuai jenisnya.', 'Batal inap rawat inap hanya Admin / Supervisor TU; batal transfer & transaksi di RJ/UGD juga boleh TU, Perawat, dan Manager Umum. Petugas lain tidak bisa — mencegah salah/sengaja hapus.'],
                                ['📱', 'Batal antrean BPJS (di Mobile JKN) itu urusan terpisah.', 'Itu hanya untuk melapor ke BPJS, dan TIDAK mengubah tagihan/status di sistem kita. Dua hal yang berbeda.'],
                            ] as [$emoji, $judul, $ket])
                                <div class="ds-card-outline" style="padding:14px 18px">
                                    <div class="flex items-start gap-3">
                                        <span style="font-size:20px; line-height:1.2">{{ $emoji }}</span>
                                        <div>
                                            <div class="ds-title-sm mb-1">{{ $judul }}</div>
                                            <div class="ds-body-sm">{{ $ket }}</div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <div class="ds-card-outline" style="padding:16px 20px; border-color:var(--primary)">
                            <span class="ds-spike" style="vertical-align:middle"></span>
                            <span class="ds-body-sm" style="color:var(--body-strong)">
                                Ingin tahu <strong>bagaimana ini dikerjakan di kode</strong> — nama tombol, tabel database,
                                &amp; kode status? Semua ada di <strong>Sisi 2 — Coding</strong> (tombol di bagian atas halaman).
                            </span>
                        </div>
                    </section>

                    {{-- ====== 05 KASIR ====== --}}
                    <section x-show="section === 'kasir'" x-cloak>
                        <div class="ds-eyebrow mb-3">05 — Administrasi</div>
                        <h1 class="ds-display-md mb-4">Alur Kasir sampai Pasien Pulang</h1>
                        <p class="ds-body-md mb-4" style="max-width:62ch">
                            Urutan baku administrasi: set tanggal pulang → input bayar → proses pulang.
                            Setelah pulang, form terkunci dan hanya menyisakan tombol batal.
                        </p>

                        <div class="ds-card-dark mt-2" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">Alur postTransaksi (proses pulang)</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['kasir'] }}</pre>
                        </div>

                        <div class="grid grid-cols-1 gap-4 mt-8 sm:grid-cols-2">
                            <div class="ds-card-outline" style="padding:20px">
                                <div class="ds-title-sm mb-2">LUNAS vs BON</div>
                                <div class="ds-body-sm"><span class="ds-code">status_pulang</span>: <strong>'L'</strong> (LUNAS) bila bayar ≥ sisa tagihan; <strong>'H'</strong> (BON/Hutang) bila kurang — sisa jadi piutang pasien.</div>
                            </div>
                            <div class="ds-card-outline" style="padding:20px">
                                <div class="ds-title-sm mb-2">Terkunci setelah pulang</div>
                                <div class="ds-body-sm"><span class="ds-code">isFormLocked</span> = true saat status Pulang → input disable, muncul banner + tombol <strong>Batal Transaksi</strong>.</div>
                            </div>
                        </div>
                    </section>

                    {{-- ====== 06 TRANSFER ====== --}}
                    <section x-show="section === 'transfer'" x-cloak>
                        <div class="ds-eyebrow mb-3">06 — Transfer &amp; Batal</div>
                        <h1 class="ds-display-md mb-4">Transfer antar-layanan</h1>
                        <p class="ds-body-md mb-4" style="max-width:62ch">
                            Dua arah: <strong>RJ → UGD</strong> dan <strong>UGD → RI</strong>. Polanya sama —
                            buat header tujuan, pindahkan biaya asal lewat tabel <span class="ds-code">tempadmins</span>,
                            kunci kunjungan asal.
                        </p>

                        <h2 class="ds-title-md mt-6 mb-3">Transfer UGD → RI</h2>
                        <p class="ds-body-md mb-4" style="max-width:62ch">
                            Pasien UGD yang perlu dirawat inap di-<em>transfer</em>: sistem membuat header RI baru,
                            memindahkan biaya UGD/RJ ke RI, dan mengunci UGD. Komponen:
                            <span class="ds-code">transfer-ugd-ke-ri-actions</span>.
                        </p>

                        <div class="ds-card-dark mt-2" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">Transfer — tabel & tautan yang ditulis</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['transfer'] }}</pre>
                        </div>

                        <h2 class="ds-title-md mt-8 mb-3">Transfer RJ → UGD</h2>
                        <p class="ds-body-md mb-4" style="max-width:62ch">
                            Arah sebaliknya, pola sama. Komponen:
                            <span class="ds-code">transfer-rj-ke-ugd-actions</span>.
                        </p>
                        <div class="ds-card-dark mt-2" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">RJ → UGD &amp; penamaan komponen</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['transfer-rj-ugd'] }}</pre>
                        </div>

                        <h2 class="ds-title-md mt-8 mb-3">Dokter &amp; tarif saat transfer</h2>
                        <p class="ds-body-md mb-4" style="max-width:62ch">
                            Bagian yang paling sering salah: dokter &amp; tarif <strong>tidak boleh disalin mentah</strong>
                            dari kunjungan asal. Tiga jebakan di bawah semuanya pernah terjadi di produksi.
                        </p>
                        <div class="ds-card-dark mt-2" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">Aturan dokter, tarif &amp; admin</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['transfer-dokter-tarif'] }}</pre>
                        </div>

                        <div class="grid grid-cols-1 gap-4 mt-8 sm:grid-cols-3">
                            <div class="ds-card-outline" style="padding:20px">
                                <div class="ds-title-sm mb-2">Penerima ≠ DPJP</div>
                                <div class="ds-body-sm">
                                    <span class="ds-code">rihdr.dr_id</span> = dokter <strong>Penerima</strong>.
                                    DPJP ada di <span class="ds-code">levelingDokter</span> (EMR, bisa lebih dari satu).
                                </div>
                            </div>
                            <div class="ds-card-outline" style="padding:20px">
                                <div class="ds-title-sm mb-2">poli_price = tarif UGD</div>
                                <div class="ds-body-sm">
                                    Di <span class="ds-code">rstxn_ugdhdrs</span> kolom itu diisi dari
                                    <span class="ds-code">ugd_price</span>, bukan tarif poli. Nama kolom menyesatkan.
                                </div>
                            </div>
                            <div class="ds-card-outline" style="padding:20px">
                                <div class="ds-title-sm mb-2">admin_status = nominal</div>
                                <div class="ds-body-sm">
                                    Bukan flag. <span class="ds-code">par_id=2</span> = 50.000, dijumlahkan sebagai uang.
                                    Menulis <span class="ds-code">'1'</span> = menagih Rp 1.
                                </div>
                            </div>
                        </div>

                        <div class="ds-card-outline mt-6" style="padding:16px 20px">
                            <span class="ds-spike" style="vertical-align:middle"></span>
                            <span class="ds-body-sm" style="color:var(--body-strong)">
                                <strong>Tautan UGD↔RI yang andal = baris <span class="ds-code">rstxn_ritempadmins</span> flag 'UGD'</strong>
                                (<span class="ds-code">tempadm_ref=rj_no → rihdr_no</span>), bukan <span class="ds-code">rstxn_ribiayaselamadugds</span>
                                yang bisa kosong untuk data lama Oracle Dev 6i. (Lihat bab Batal Transfer.)
                            </span>
                        </div>
                    </section>

                    {{-- ====== 07 BATAL TRANSFER ====== --}}
                    <section x-show="section === 'batal-transfer'" x-cloak>
                        <div class="ds-eyebrow mb-3">07 — Transfer &amp; Batal</div>
                        <h1 class="ds-display-md mb-4">Batal Transfer</h1>
                        <p class="ds-body-md mb-4" style="max-width:62ch">
                            Membatalkan transfer UGD→RI: menghapus RI yang baru dibuat &amp; mengembalikan UGD ke Aktif.
                            Hanya boleh bila RI <strong>belum diproses</strong> &amp; <strong>belum ada transaksi</strong>.
                        </p>

                        <div class="ds-card-dark mt-2" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">batalTransferRI — cari RI berlapis + guard + aksi</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['batal-transfer'] }}</pre>
                        </div>

                        <div class="ds-card-outline mt-6" style="padding:16px 20px">
                            <span class="ds-spike" style="vertical-align:middle"></span>
                            <span class="ds-body-sm" style="color:var(--body-strong)">
                                <strong>Bug yang diperbaiki:</strong> dulu pengecekan hanya melihat
                                <span class="ds-code">rstxn_ribiayaselamadugds</span> → transfer lama (tanpa baris itu)
                                salah dianggap "Tidak ada data transfer". Fix: cari <span class="ds-code">rihdr_no</span>
                                dari <span class="ds-code">rstxn_ritempadmins</span> (link utama) dulu, baru fallback.
                            </span>
                        </div>
                    </section>

                    {{-- ====== 08 BATAL TRANSAKSI ====== --}}
                    <section x-show="section === 'batal-transaksi'" x-cloak>
                        <div class="ds-eyebrow mb-3">08 — Transfer &amp; Batal</div>
                        <h1 class="ds-display-md mb-4">Batal Transaksi (Pembayaran / Pulang)</h1>
                        <p class="ds-body-md mb-4" style="max-width:62ch">
                            Membatalkan <strong>pembayaran</strong>, bukan admisi. Menghapus payment &amp; mengembalikan
                            status ke sebelum-bayar. Ada di ketiga jalur (kasir-rj / kasir-ugd / kasir-ri).
                        </p>

                        <div class="ds-card-dark mt-2" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">batalTransaksi (contoh RI)</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['batal-transaksi'] }}</pre>
                        </div>

                        <div class="ds-card-outline mt-6" style="padding:16px 20px">
                            <span class="ds-spike" style="vertical-align:middle"></span>
                            <span class="ds-body-sm" style="color:var(--body-strong)">
                                RI: Pulang ('P') → Dirawat ('I'). RJ/UGD: reset field pembayaran &amp; buka kembali status.
                                Ini <strong>bukan</strong> pembatalan admisi — untuk itu lihat bab <em>Batal Inap</em>.
                            </span>
                        </div>
                    </section>

                    {{-- ====== 09 BATAL INAP ====== --}}
                    <section x-show="section === 'batal-inap'" x-cloak>
                        <div class="ds-eyebrow mb-3">09 — Transfer &amp; Batal</div>
                        <h1 class="ds-display-md mb-4">Batal Inap → status F</h1>
                        <p class="ds-body-md mb-4" style="max-width:62ch">
                            Membatalkan <strong>pendaftaran inap</strong> yang salah/tak jadi. Bersifat
                            <strong>soft</strong> (set <span class="ds-code">ri_status='F'</span>, record tetap),
                            hanya boleh saat masih Dirawat, bukan dari transfer, dan belum ada transaksi apa pun.
                        </p>

                        <div class="ds-card-dark mt-2" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">batalInap — guard bertingkat + set 'F'</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['batal-inap'] }}</pre>
                        </div>

                        <div class="ds-card-outline mt-6" style="padding:16px 20px">
                            <span class="ds-spike" style="vertical-align:middle"></span>
                            <span class="ds-body-sm" style="color:var(--body-strong)">
                                Kenapa soft (set 'F'), bukan hapus? Karena laporan sudah mengecualikan 'F' &amp; jejak
                                audit harus terjaga. Bed dibebaskan (<span class="ds-code">trfroom end_date=SYSDATE</span>)
                                &amp; pasien di-unlock agar bisa didaftar ulang.
                            </span>
                        </div>
                    </section>

                    {{-- ====== 10 MATRIKS ====== --}}
                    {{-- ====== 10 BATAL TRANSAKSI APOTEK RI (SLS) ====== --}}
                    <section x-show="section === 'batal-sls'" x-cloak>
                        <div class="ds-eyebrow mb-3">10 — Transfer &amp; Batal</div>
                        <h1 class="ds-display-md mb-4">Batal Transaksi Apotek RI (SLS)</h1>
                        <p class="ds-body-md mb-4" style="max-width:64ch">
                            Membatalkan <strong>pembayaran resep rawat inap</strong>. Header resep ada di
                            <span class="ds-code">imtxn_slshdrs</span> — bukan <span class="ds-code">rstxn_*hdrs</span>
                            seperti RJ/UGD/RI — dan statusnya cuma dua:
                            <span class="ds-code">'A'</span> belum diproses kasir, <span class="ds-code">'L'</span> sudah.
                        </p>

                        <div class="ds-card-dark mt-2" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">batalTransaksi (Apotek RI) — guard, anti-race, efek samping</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['batal-sls'] }}</pre>
                        </div>

                        <div class="ds-card-outline mt-6" style="padding:16px 20px">
                            <span class="ds-spike" style="vertical-align:middle"></span>
                            <span class="ds-body-sm" style="color:var(--body-strong)">
                                <strong>Dua komponen kembar.</strong>
                                <span class="ds-code">administrasi-ri-resep</span> (dibuka dari Antrian RI-Resep di
                                halaman Apotek) dan <span class="ds-code">administrasi-kasir-ri</span> (dari Antrian
                                Kasir RI) punya judul modal yang sama persis tapi file &amp; nama event berbeda.
                                Rolenya pun beda: Apoteker|Admin|Tu vs Admin|Manager Umum|Supervisor Tu. Kalau
                                menyalin pola dari satu ke yang lain, cek ulang nama event LOV-nya.
                            </span>
                        </div>
                    </section>

                    <section x-show="section === 'matriks'" x-cloak>
                        <div class="ds-eyebrow mb-3">11 — Transfer &amp; Batal</div>
                        <h1 class="ds-display-md mb-4">Matriks Model Batal</h1>
                        <p class="ds-body-md mb-6" style="max-width:62ch">
                            Tiga model batal sering tertukar. Bedakan dari <strong>apa yang dibatalkan</strong> &amp;
                            <strong>status akhirnya</strong>.
                        </p>

                        <div class="ds-card-outline" style="padding:0; overflow-x:auto">
                            <table class="ds-table">
                                <thead><tr><th>Model</th><th>Membatalkan</th><th>Status: dari → ke</th><th>Guard utama</th><th>Role</th></tr></thead>
                                <tbody>
                                    <tr><td class="ds-td-strong">Batal Transaksi</td><td class="ds-body-sm">Pembayaran / pulang</td><td class="ds-td-class">P → I (RI) · reset (RJ/UGD)</td><td class="ds-body-sm">Sudah dibayar/pulang</td><td class="ds-body-sm">Admin / Supervisor Tu</td></tr>
                                    <tr><td class="ds-td-strong">Batal Transfer</td><td class="ds-body-sm">Transfer UGD→RI</td><td class="ds-td-class">UGD: I → A · RI dihapus</td><td class="ds-body-sm">RI belum ada transaksi; lab UGD tak pending</td><td class="ds-body-sm">Admin / Tu</td></tr>
                                    <tr><td class="ds-td-strong">Batal Inap</td><td class="ds-body-sm">Admisi RI</td><td class="ds-td-class">I → F (soft)</td><td class="ds-body-sm">Dirawat, bukan transfer, belum ada transaksi</td><td class="ds-body-sm">Admin / Supervisor Tu</td></tr>
                                    <tr><td class="ds-td-strong">Batal Transaksi (Apotek RI)</td><td class="ds-body-sm">Pembayaran resep RI</td><td class="ds-td-class">L → A (<span class="ds-code">imtxn_slshdrs.status</span>)</td><td class="ds-body-sm">Status 'L'; pasien belum pulang; <span class="ds-code">lockForUpdate</span> + baca ulang status</td><td class="ds-body-sm">Apoteker / Admin / Tu <span class="ds-body-sm" style="color:var(--muted)">(kasir-ri: Admin / Manager Umum / Supervisor Tu)</span></td></tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="ds-card-outline mt-6" style="padding:16px 20px">
                            <span class="ds-spike" style="vertical-align:middle"></span>
                            <span class="ds-body-sm" style="color:var(--body-strong)">
                                <strong>Apotek RI belum punya lapis kedua.</strong>
                                <span class="ds-code">administrasi-ri-resep</span> &amp;
                                <span class="ds-code">administrasi-kasir-ri</span> hanya punya Batal Transaksi
                                (pembayaran). Resep yang terlanjur salah dan belum dibayar harus dihapus obat per obat
                                lewat <span class="ds-code">removeObat()</span> — header
                                <span class="ds-code">imtxn_slshdrs</span> tetap ada dan tetap muncul di antrian.
                                Kalau lapis kedua ditambahkan, ikuti pola Batal Inap: soft-cancel ke status batal,
                                role lebih ketat, syarat belum ada pembayaran.
                            </span>
                        </div>

                        <div class="ds-card-outline mt-3" style="padding:16px 20px">
                            <span class="ds-spike" style="vertical-align:middle"></span>
                            <span class="ds-body-sm" style="color:var(--body-strong)">
                                <strong>Sesudah batal, samakan properti dengan yang ditulis ke DB — jangan di-null-kan.</strong>
                                <span class="ds-code">batalTransaksi()</span> RJ/UGD dulu men-set
                                <span class="ds-code">$txnStatus = null</span> padahal DB ditulis
                                <span class="ds-code">'A'</span>. Tombol Batal Transaksi (A → F) digerbangi
                                <span class="ds-code">$txnStatus === 'A'</span>, jadi tombolnya hilang sesudah
                                Post → Batal sampai modal ditutup &amp; dibuka ulang. Diperbaiki di
                                <span class="ds-code">91218d91</span>. Buang juga cache computed
                                (<span class="ds-code">unset($this-&gt;isKasirPosted, ...)</span>) dan
                                <span class="ds-code">emp_id</span> sengaja TIDAK direset demi jejak audit.
                            </span>
                        </div>

                        <div class="ds-card-outline mt-3" style="padding:16px 20px">
                            <span class="ds-spike" style="vertical-align:middle"></span>
                            <span class="ds-body-sm" style="color:var(--body-strong)">
                                <strong>Urutan bila kasus campur:</strong> pasien sudah pulang lalu ingin dibatalkan total →
                                (1) Batal Transaksi (P→I), lalu (2) Batal Inap (I→F). Pasien dari UGD → gunakan
                                Batal Transfer, bukan Batal Inap.
                            </span>
                        </div>
                    </section>

                    {{-- ====== 11 GUARD & KONSISTENSI TRANSFER ====== --}}
                    <section x-show="section === 'guard-transfer'" x-cloak>
                        <div class="ds-eyebrow mb-3">12 — Transfer &amp; Batal</div>
                        <h1 class="ds-display-md mb-4">Guard &amp; Konsistensi Transfer</h1>
                        <p class="ds-body-md mb-6" style="max-width:64ch">
                            Checklist semua <strong>guard</strong> di dua alur transfer
                            (<span class="ds-code">RJ→UGD</span> &amp; <span class="ds-code">UGD→RI</span>),
                            saat <strong>create (maju)</strong> maupun <strong>batal (mundur)</strong>,
                            plus status <strong>konsistensi</strong> antar-arah.
                        </p>

                        {{-- ===== GUARD CREATE ===== --}}
                        <h2 class="ds-title-lg mb-3">A. Guard saat CREATE (maju)</h2>
                        <div class="ds-card-outline mb-3" style="padding:0; overflow-x:auto">
                            <table class="ds-table">
                                <thead><tr><th>Guard</th><th>Pesan / arti</th><th>Berlaku</th></tr></thead>
                                <tbody>
                                    <tr><td class="ds-td-strong">rjNo ada</td><td class="ds-body-sm">"Data transaksi tidak ditemukan"</td><td class="ds-body-sm">keduanya</td></tr>
                                    <tr><td class="ds-td-strong">Sumber status 'A'</td><td class="ds-body-sm">"sudah diproses, tidak bisa ditransfer"</td><td class="ds-body-sm">keduanya</td></tr>
                                    <tr><td class="ds-td-strong">Lab tidak pending</td><td class="ds-body-sm">"Hasil Laborat belum selesai, transfer tidak bisa dilakukan"</td><td class="ds-body-sm">keduanya</td></tr>
                                    <tr><td class="ds-td-strong">Kamar Operasi tidak pending</td><td class="ds-body-sm"><span class="ds-code">checkOkPending{RJ,UGD}</span> — pesannya menyebut nomor OK-nya. Transfer mengubah <span class="ds-code">rj_status</span> jadi 'I', padahal Trf Biaya mensyaratkan 'A'</td><td class="ds-body-sm">keduanya</td></tr>
                                    <tr><td class="ds-td-strong">Belum pernah transfer</td><td class="ds-body-sm">idempoten (cek <span class="ds-code">*biayaselamadi*</span>) — "sudah pernah dilakukan"</td><td class="ds-body-sm">keduanya</td></tr>
                                    <tr><td class="ds-td-strong">Anti-race</td><td class="ds-body-sm">"Data sudah diproses oleh user lain" (dalam transaksi)</td><td class="ds-body-sm">keduanya</td></tr>
                                    <tr><td class="ds-td-strong">Data sumber ada</td><td class="ds-body-sm">"Data UGD/RJ tidak ditemukan" (dalam transaksi)</td><td class="ds-body-sm">keduanya</td></tr>
                                    <tr><td class="ds-td-strong">Pasien lockstatus</td><td class="ds-body-sm">"Pasien sedang dalam status X, tidak bisa transfer" (cegah dobel jalur)</td><td class="ds-body-sm">keduanya</td></tr>
                                    <tr><td class="ds-td-strong">Ruangan dipilih</td><td class="ds-body-sm">wajib pilih room</td><td class="ds-body-sm" style="color:var(--primary)">UGD→RI saja</td></tr>
                                    <tr><td class="ds-td-strong">Bed dipilih</td><td class="ds-body-sm">"Pilih ruangan dan bed terlebih dahulu"</td><td class="ds-body-sm" style="color:var(--primary)">UGD→RI saja</td></tr>
                                </tbody>
                            </table>
                        </div>
                        <p class="ds-caption mb-8" style="color:var(--muted)">
                            CREATE <strong>sudah konsisten</strong> di kedua arah — kecuali UGD→RI menambah pilih room/bed (memang butuh tempat tidur).
                        </p>

                        {{-- ===== GUARD BATAL ===== --}}
                        <h2 class="ds-title-lg mb-3">B. Guard saat BATAL (mundur)</h2>
                        <div class="ds-card-outline mb-3" style="padding:0; overflow-x:auto">
                            <table class="ds-table">
                                <thead><tr><th>Guard</th><th>Arti</th><th>Berlaku</th></tr></thead>
                                <tbody>
                                    <tr><td class="ds-td-strong">Role Admin | Tu</td><td class="ds-body-sm">hanya Admin/TU boleh batal transfer</td><td class="ds-body-sm">keduanya</td></tr>
                                    <tr><td class="ds-td-strong">rjNo ada</td><td class="ds-body-sm">data transaksi ditemukan</td><td class="ds-body-sm">keduanya</td></tr>
                                    <tr><td class="ds-td-strong">Lookup target</td><td class="ds-body-sm">cari header hasil transfer</td><td class="ds-body-sm">keduanya</td></tr>
                                    <tr><td class="ds-td-strong">Target bisa dibatalkan</td><td class="ds-body-sm">UGD→RI: RI harus 'I'; RJ→UGD: UGD harus 'A'</td><td class="ds-body-sm">keduanya</td></tr>
                                    <tr><td class="ds-td-strong">Target belum ada transaksi</td><td class="ds-body-sm">obat/lab/rad/tindakan/jasa/lain-lain + pembayaran</td><td class="ds-body-sm">keduanya</td></tr>
                                    <tr><td class="ds-td-strong">Sumber status 'I'</td><td class="ds-body-sm">memang tertransfer (dalam transaksi)</td><td class="ds-body-sm">keduanya</td></tr>
                                    <tr><td class="ds-td-strong">Lab-pending DILEPAS</td><td class="ds-body-sm" style="color:var(--primary)">batal (mundur) TIDAK diblok lab pending</td><td class="ds-body-sm">keduanya ✅</td></tr>
                                </tbody>
                            </table>
                        </div>

                        {{-- ===== KONSISTENSI ===== --}}
                        <h2 class="ds-title-lg mt-8 mb-3">C. Konsistensi antar-arah (batal)</h2>
                        <div class="ds-card-outline" style="padding:0; overflow-x:auto">
                            <table class="ds-table">
                                <thead><tr><th>Aspek</th><th>UGD→RI (kuat)</th><th>RJ→UGD (tertinggal)</th></tr></thead>
                                <tbody>
                                    <tr><td class="ds-td-strong">Lookup transfer</td><td class="ds-body-sm" style="color:var(--primary)">berlapis (ritempadmins + fallback)</td><td class="ds-body-sm" style="color:#d97706">1 sumber (ugdbiayaselamadirjs) ⚠️</td></tr>
                                    <tr><td class="ds-td-strong">Not-found → recovery</td><td class="ds-body-sm" style="color:var(--primary)">✅ UGD 'I'→'A'</td><td class="ds-body-sm" style="color:#dc2626">❌ tak ada — RJ bisa nyangkut 'I'</td></tr>
                                    <tr><td class="ds-td-strong">Header target saat batal</td><td class="ds-body-sm" style="color:var(--primary)">soft ri_status='F'</td><td class="ds-body-sm" style="color:#d97706">hard delete ugdhdrs (rawan ORA-02292) ⚠️</td></tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="ds-card-outline mt-6" style="padding:16px 20px">
                            <span class="ds-spike" style="vertical-align:middle"></span>
                            <span class="ds-body-sm" style="color:var(--body-strong)">
                                <strong>Verdict:</strong> guard CREATE &amp; guard inti BATAL sudah konsisten.
                                Yang <strong>belum</strong>: batal <span class="ds-code">RJ→UGD</span> perlu (1) lookup berlapis via
                                <span class="ds-code">ugdtempadmins</span> flag 'RJ', (2) recovery RJ 'I'→'A' saat data tak ketemu.
                                Poin (3) hard-delete <strong>tak bisa 100% sama</strong> karena UGD tak punya status 'F' seperti RI —
                                opsi: buat delete berpanduan child atau biarkan.
                            </span>
                        </div>
                    </section>

                    {{-- ====== EDIT INLINE TABEL BIAYA ====== --}}
                    <section x-show="section === 'edit-inline'" x-cloak>
                        <div class="ds-eyebrow mb-3">05b — Administrasi</div>
                        <h1 class="ds-display-md mb-4">Edit Inline Tabel Biaya</h1>
                        <p class="ds-body-md mb-4" style="max-width:62ch">
                            Sel tabel yang tersimpan begitu blur — dipakai di Riwayat Kamar
                            (<span class="ds-code">room-ri</span>: Hari, tarif kamar/perawatan/CS, tanggal
                            Mulai &amp; Selesai) serta tabel Visit/Konsul. Yang berbahaya di sini bukan UI-nya,
                            melainkan <strong>angka biaya yang ikut bergerak</strong>.
                        </p>

                        <div class="ds-card-dark mt-2" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">Kerangka aksi — urutannya mengikat</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['edit-inline'] }}</pre>
                        </div>

                        <div class="grid grid-cols-1 gap-4 mt-8 sm:grid-cols-2">
                            <div class="ds-card-outline" style="padding:20px">
                                <div class="ds-title-sm mb-2">Kolom turunan = tiru rumus pembuatnya</div>
                                <div class="ds-body-sm">
                                    <span class="ds-code">day</span> mengalikan biaya
                                    (<span class="ds-code">subtotal = (kamar+prwtn+cs) × day</span>). Pindah Kamar menulisnya
                                    <span class="ds-code">max(1, ROUND(trfrDate - start_date))</span>, maka aksi lain wajib sama.
                                    Beda rumus antar-jalur = hasil berbeda tergantung user masuk lewat pintu mana.
                                </div>
                            </div>
                            <div class="ds-card-outline" style="padding:20px">
                                <div class="ds-title-sm mb-2">Log tiap kolom pengali biaya</div>
                                <div class="ds-body-sm">
                                    Cek aksi lama di file yang sama — sempat timpang: hapus kamar &amp; ubah tarif ter-log,
                                    tapi <span class="ds-code">updateDay</span> tidak. Log tulis <strong>lama → baru</strong>;
                                    kolom NULL tulis maknanya (<span class="ds-code">(otomatis)</span>), bukan <span class="ds-code">0</span>.
                                </div>
                            </div>
                            <div class="ds-card-outline" style="padding:20px">
                                <div class="ds-title-sm mb-2">Guard state, bukan cuma format</div>
                                <div class="ds-body-sm">
                                    Selesai tak boleh lebih kecil dari Mulai (sama persis boleh — kamar transit);
                                    Selesai dikosongkan = kamar aktif lagi, tolak bila sudah ada baris aktif lain.
                                    Bandingkan pasangan nilai final, supaya edit Mulai kena aturan yang sama.
                                </div>
                            </div>
                            <div class="ds-card-outline" style="padding:20px">
                                <div class="ds-title-sm mb-2">UI</div>
                                <div class="ds-body-sm">
                                    Ring fokus brand (samakan dengan <span class="ds-code">x-text-input</span>), tombol hapus baris
                                    = outline merah-tint + ikon sampah <span class="ds-code">!px-2 !py-1</span>. Saat terkunci,
                                    sel kembali jadi teks biasa — bukan sekadar <span class="ds-code">disabled</span>.
                                </div>
                            </div>
                        </div>

                        <div class="ds-card-outline mt-6" style="padding:16px 20px">
                            <span class="ds-spike" style="vertical-align:middle"></span>
                            <span class="ds-body-sm" style="color:var(--body-strong)">
                                <strong>Sengaja belum dipasang</strong> di Riwayat Kamar (keputusan user — jangan ditambahkan diam-diam):
                                guard tumpang tindih antar baris, rentang di luar tanggal rawat inap, dan sinkronisasi rantai transfer
                                (Selesai baris bawah ↔ Mulai baris atas).
                            </span>
                        </div>
                    </section>


                    {{-- ====== PETA MODUL & ALIRAN BIAYA ====== --}}
                    <section x-show="section === 'peta-modul'" x-cloak>
                        <div class="ds-eyebrow mb-3">05 — Alur Visual</div>
                        <h1 class="ds-display-md mb-4">Peta Modul &amp; Aliran Biaya</h1>
                        <p class="ds-body-md mb-2" style="max-width:64ch">
                            Bab sebelumnya menceritakan perjalanan pasien. Bab ini menjawab pertanyaan
                            berikutnya: <strong>uangnya lewat mana</strong> — dari layanan yang dikerjakan,
                            sampai muncul di tagihan, kwitansi, dan pembukuan.
                        </p>
                        <p class="ds-caption mb-8" style="color:var(--muted)">
                            Nama tabel &amp; aturan teknisnya ada di <strong>Sisi 2 — Coding</strong>,
                            bab <em>Peta Modul &amp; Aliran Biaya</em> dan <em>Menambah Pos Biaya Baru</em>.
                        </p>

                        @php
                            // Didefinisikan ulang di sini — bab ini tidak boleh bergantung pada
                            // urutan render bab "Alur Visual" yang mendefinisikannya lebih dulu.
                            $flowBox = function ($tone) {
                                return match ($tone) {
                                    'entry' => 'padding:10px 14px; border-color:var(--primary)',
                                    'opt'   => 'padding:10px 14px; border-style:dashed; border-color:#d97706',
                                    'cash'  => 'padding:10px 14px; border-color:#059669',
                                    'done'  => 'padding:10px 14px; border-color:#059669; background:rgba(5,150,105,0.06)',
                                    default => 'padding:10px 14px',
                                };
                            };
                            $arrow = '<span class="ds-code" style="color:var(--primary); font-size:16px">▶</span>';
                        @endphp

                        {{-- ===== 1. GAMBAR BESAR ===== --}}
                        <h2 class="ds-title-lg mb-2">1. Tiga jalur, empat modul layanan</h2>
                        <p class="ds-body-md mb-4" style="max-width:64ch">
                            <strong>Jalur kunjungan</strong> (RJ / UGD / RI) adalah yang punya kasir dan tagihan.
                            <strong>Modul layanan</strong> (Laborat, Radiologi, Kamar Operasi, Apotek) tidak punya
                            kasir sendiri — hasil kerjanya <em>menempel</em> jadi baris biaya di jalur asal pasien.
                        </p>

                        <div class="ds-card-outline mb-6" style="padding:18px 20px; overflow-x:auto">
<pre class="ds-code" style="margin:0; font-size:12.5px; line-height:1.65; color:var(--body-strong)">        MODUL LAYANAN                         JALUR KUNJUNGAN            HILIR
        (tak punya kasir)                     (punya kasir)

   ┌──────────────┐                          ┌──────────────┐
   │  Laborat     │─┐                     ┌─▶│  RJ  (poli)  │─┐
   ├──────────────┤ │                     │  ├──────────────┤ │      ┌─────────────┐
   │  Radiologi   │─┤   biaya menempel    │  │  UGD (IGD)   │─┼─────▶│  Kasir      │
   ├──────────────┤ ├────────────────────▶┤  ├──────────────┤ │      │  Kwitansi   │
   │ Kamar Operasi│─┤   ke jalur asal     │  │  RI  (inap)  │─┘      │  Jurnal     │
   ├──────────────┤ │                     │  └──────┬───────┘        │  Laporan    │
   │  Apotek      │─┘                     └─────────┘                └─────────────┘
   └──────────────┘                                 │
                                    transfer antar unit (RJ▶UGD▶RI)
                                    biaya ikut pindah, tidak ditinggal</pre>
                        </div>

                        {{-- ===== 2. TIGA POLA ===== --}}
                        <h2 class="ds-title-lg mb-2">2. Tiga pola penempelan biaya — beda, jangan disamakan</h2>
                        <p class="ds-body-md mb-4" style="max-width:64ch">
                            Keempat modul layanan itu <strong>tidak bekerja dengan cara yang sama</strong>.
                            Perbedaannya menentukan apakah dibutuhkan pengaman agar pasien tak dipulangkan
                            sebelum biayanya masuk.
                        </p>

                        @php
                            $polaLayanan = [
                                [
                                    'Punya antrean sendiri + tombol transfer',
                                    'Laborat · Kamar Operasi',
                                    'entry',
                                    [
                                        ['Diorder dari EMR', 'dokter/ruangan mengirim'],
                                        ['Masuk antrean modul', 'petugas mengerjakan'],
                                        ['Ditekan tombol transfer', 'Lab: Selesai · OK: Trf Biaya'],
                                        ['Baru jadi baris tagihan', ''],
                                    ],
                                    'Ada JEDA antara diorder dan masuk tagihan. Karena itu wajib ada pengaman: kunjungan tidak boleh ditutup selagi masih ada order menggantung.',
                                ],
                                [
                                    'Langsung jadi biaya',
                                    'Radiologi',
                                    'main',
                                    [
                                        ['Diorder dari EMR', 'dokter mengirim'],
                                        ['Seketika jadi baris tagihan', 'tanpa jeda'],
                                        ['Petugas meng-upload hasil', 'ke baris yang sama'],
                                    ],
                                    'Tidak ada jeda, jadi tidak butuh pengaman. Petugas radiologi hanya melengkapi hasil, bukan membuat biayanya.',
                                ],
                                [
                                    'Lewat resep',
                                    'Apotek',
                                    'opt',
                                    [
                                        ['Dokter menulis e-resep', ''],
                                        ['Obat diserahkan', 'di apotek'],
                                        ['Jadi baris tagihan obat', ''],
                                    ],
                                    'Rawat inap punya jalur tambahan (penjualan/SLS) dengan aturan pembatalan sendiri.',
                                ],
                            ];
                        @endphp

                        @foreach ($polaLayanan as [$judulPola, $modulnya, $tone, $langkah, $catatan])
                            <div class="ds-card-outline mb-4" style="padding:14px 16px">
                                <div class="flex flex-wrap items-baseline gap-x-2 mb-3">
                                    <span class="ds-title-sm" style="color:var(--primary)">{{ $judulPola }}</span>
                                    <span class="ds-caption" style="color:var(--muted)">— {{ $modulnya }}</span>
                                </div>
                                <div class="flex flex-wrap items-center gap-2 mb-3">
                                    @foreach ($langkah as $i => [$judul, $ket])
                                        @if ($i > 0) {!! $arrow !!} @endif
                                        <span class="ds-card-outline" style="{{ $flowBox($i === count($langkah) - 1 ? 'cash' : $tone) }}; background:var(--canvas)">
                                            <span class="block text-sm font-semibold" style="color:var(--ink)">{{ $judul }}</span>
                                            @if ($ket !== '')<span class="block text-xs" style="color:var(--muted)">{{ $ket }}</span>@endif
                                        </span>
                                    @endforeach
                                </div>
                                <p class="ds-body-sm" style="color:var(--body)">{{ $catatan }}</p>
                            </div>
                        @endforeach

                        {{-- ===== 3. SESUDAH JADI BIAYA ===== --}}
                        <h2 class="ds-title-lg mb-2">3. Sesudah jadi baris tagihan, ke mana lagi?</h2>
                        <p class="ds-body-md mb-4" style="max-width:64ch">
                            Satu baris biaya tidak berhenti di tagihan. Ia muncul di <strong>enam tempat</strong>,
                            dan semuanya harus ikut disesuaikan tiap kali ada pos biaya baru — kalau satu
                            terlewat, uangnya hilang tanpa jejak.
                        </p>

                        @php
                            $hilir = [
                                ['Tab Administrasi', 'rincian per jenis biaya, bisa dilihat petugas'],
                                ['Kasir', 'ikut Total Tagihan yang dibayar pasien'],
                                ['Transfer antar unit', 'ikut pindah kalau pasien dipindah RJ▶UGD▶RI'],
                                ['Kwitansi', 'tercetak sebagai baris rincian untuk pasien'],
                                ['Jurnal / pembukuan', 'diakui sebagai pendapatan, lawan piutang'],
                                ['Laporan manajemen', 'Pendapatan RS, Piutang Pasien, gaji dokter'],
                            ];
                        @endphp

                        <div class="grid gap-3 mb-6" style="grid-template-columns:repeat(auto-fit,minmax(230px,1fr))">
                            @foreach ($hilir as $i => [$nama, $ket])
                                <div class="ds-card-outline" style="padding:12px 14px">
                                    <div class="flex items-baseline gap-2">
                                        <span class="ds-code" style="color:var(--primary); font-size:12px">{{ $i + 1 }}</span>
                                        <span class="text-sm font-semibold" style="color:var(--ink)">{{ $nama }}</span>
                                    </div>
                                    <p class="mt-1 text-xs" style="color:var(--muted)">{{ $ket }}</p>
                                </div>
                            @endforeach
                        </div>

                        <div class="ds-card-outline mb-6" style="padding:16px 20px; border-color:#d97706">
                            <span class="ds-spike" style="vertical-align:middle"></span>
                            <span class="ds-body-sm" style="color:var(--body-strong)">
                                <strong>Saldo kas tidak ikut bergeser.</strong> Halaman Cek Saldo Kas menghitung
                                dari <em>uang yang benar-benar diterima</em> di cabang pembayaran — bukan dari
                                susunan pos biayanya. Yang bergerak saat pos biaya berubah adalah
                                <strong>pendapatan</strong> dan <strong>piutang</strong>.
                            </span>
                        </div>

                        {{-- ===== 4. STATUS PER MODUL ===== --}}
                        <h2 class="ds-title-lg mb-2">4. Ringkasan status</h2>
                        <div class="ds-card-outline" style="padding:0; overflow-x:auto">
                            <table class="w-full" style="font-size:13px; border-collapse:collapse">
                                <thead>
                                    <tr style="background:var(--surface-card)">
                                        <th class="px-4 py-2 text-left" style="color:var(--muted)">Modul</th>
                                        <th class="px-4 py-2 text-left" style="color:var(--muted)">Antrean sendiri?</th>
                                        <th class="px-4 py-2 text-left" style="color:var(--muted)">Perlu ditransfer?</th>
                                        <th class="px-4 py-2 text-left" style="color:var(--muted)">Pengaman pulang?</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ([
                                        ['Laborat', 'Ya', 'Ya — tombol Selesai', 'Ya'],
                                        ['Kamar Operasi', 'Ya', 'Ya — tombol Trf Biaya', 'Ya'],
                                        ['Radiologi', 'Ya (upload hasil)', 'Tidak — langsung', 'Tidak perlu'],
                                        ['Apotek', 'Ya (antrian resep)', 'Tidak — lewat resep', 'Tidak perlu'],
                                    ] as $baris)
                                        <tr style="border-top:1px solid var(--hairline)">
                                            @foreach ($baris as $kolomKe => $isi)
                                                <td class="px-4 py-2" style="color:{{ $kolomKe === 0 ? 'var(--ink)' : 'var(--body)' }}; {{ $kolomKe === 0 ? 'font-weight:600' : '' }}">{{ $isi }}</td>
                                            @endforeach
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="ds-card-dark mt-6" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">Detail teknis — nama tabel &amp; kunci relasi</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['peta-modul'] }}</pre>
                        </div>
                    </section>


                    {{-- ====== MENAMBAH POS BIAYA BARU ====== --}}
                    <section x-show="section === 'hilir-biaya'" x-cloak>
                        <div class="ds-eyebrow mb-3">13 — Referensi</div>
                        <h1 class="ds-display-md mb-4">Menambah Pos Biaya Baru</h1>
                        <p class="ds-body-md mb-6" style="max-width:64ch">
                            Menambah satu jenis biaya <strong>bukan pekerjaan satu-dua berkas</strong>. Satu baris
                            biaya muncul di enam lapis, dan melewatkan satu lapis berarti uangnya hilang tanpa
                            jejak — bukan error yang kelihatan. Daftar di bawah disusun dari kejadian nyata saat
                            pos Kamar Operasi dibuka untuk RJ/UGD (2026-08-01).
                        </p>

                        <div class="ds-card-dark" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">Enam lapis hilir + cara menyisirnya</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['hilir-biaya'] }}</pre>
                        </div>

                        <h2 class="ds-title-lg mt-8 mb-2">Yang benar-benar terjadi</h2>
                        <div class="space-y-3">
                            @foreach ([
                                ['calculateRICosts() terlewat saat sapuan', 'Ekspresi nvl()-nya ditulis rapat tanpa spasi, sedangkan tempat lain berspasi — grep satu pola meleset. Akibatnya biaya operasi yang dibawa dari RJ/UGD tak masuk kwitansi RI.'],
                                ['Kwitansi RJ/UGD tidak dihitung di PHP', 'Rinciannya dibaca dari view RSVIEW_RJSTRS / RSVIEW_UGDSTRS. Menambah pos di PHP saja membuat kasir menagih lebih besar daripada yang tercetak di kwitansi.'],
                                ['Jurnal bolong Rp 936.000 per operasi', 'Cabang OK di TKVIEW_ACCOUNTS membaca rihdr_no, yang untuk RJ/UGD memang NULL. Kasir menagih penuh & pembayaran mengkredit piutang penuh, tapi pendapatannya tak pernah diakui.'],
                                ['Kolom tetap di tabel transfer', 'rstxn_*tempadmins memetakan biaya ke kolom TETAP. Tanpa kolom baru, biaya yang sudah masuk tagihan hilang begitu pasien dipindah unit.'],
                                ['View jurnal membengkak 26jt → 44jt baris', 'Cabang lama menerbitkan 1 baris per kunjungan walau nol. Cabang baru wajib memakai EXISTS ke tabel sumbernya.'],
                                ['Nomor RJ beririsan dengan nomor UGD', 'Label jurnal hanya memuat nomor kunjungan, dan mis. 203858 ada di kedua tabel. Menguji jurnal harus berbasis SELISIH sebelum-sesudah, bukan total.'],
                            ] as $i => [$judul, $isi])
                                <div class="ds-card-outline" style="padding:16px 20px">
                                    <div class="flex items-start gap-3">
                                        <span style="display:inline-flex;align-items:center;justify-content:center;min-width:24px;height:24px;border-radius:9999px;background:var(--primary);color:#fff;font-size:12px;font-weight:700;flex:none">{{ $i + 1 }}</span>
                                        <div>
                                            <div class="ds-title-sm mb-1">{{ $judul }}</div>
                                            <div class="ds-body-sm">{{ $isi }}</div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <div class="ds-card-outline mt-6" style="padding:16px 20px" >
                            <span class="ds-spike" style="vertical-align:middle"></span>
                            <span class="ds-body-sm" style="color:var(--body-strong)">
                                <strong>Cara menguji yang terbukti:</strong> jalankan alur penuh di dalam
                                <code class="ds-code">DB::beginTransaction()</code> … <code class="ds-code">DB::rollBack()</code>,
                                lalu bandingkan tiga angka yang harus sama — nilai transfer, subtotal kwitansi,
                                dan kenaikan pendapatan di jurnal. Untuk view besar, bangkitkan definisi barunya
                                dari <code class="ds-code">user_views.text</code> dan validasi lewat view bayangan
                                <code class="ds-code">ZZ_UJI_*</code> sebelum menyentuh yang asli.
                            </span>
                        </div>
                    </section>

                    {{-- ====== 12 RANJAU ====== --}}
                    <section x-show="section === 'ranjau'" x-cloak>
                        <div class="ds-eyebrow mb-3">13 — Referensi</div>
                        <h1 class="ds-display-md mb-4">Ranjau Umum</h1>
                        <div class="space-y-3">
                            @foreach ([
                                ['Sumber tautan transfer', 'Jangan andalkan hanya rstxn_ribiayaselamadugds — bisa kosong (data Oracle Dev 6i). Link utama = rstxn_ritempadmins flag UGD.'],
                                ['Selalu lock sebelum tulis', 'lockRJRow/lockUGDRow/lockRIRow di dalam DB::transaction; tanpa lock, dua kasir bisa bentrok (last write wins).'],
                                ['Batal ≠ hapus', 'Batal Inap = SET ri_status F (soft), bukan DELETE. Laporan sudah mengecualikan F; hapus akan merusak audit & nomor.'],
                                ['Guard transaksi sebelum batal', 'Selalu cek RI/UGD belum punya transaksi (visit/obat/lab/dll.) sebelum batal transfer/inap, demi integritas billing.'],
                                ['Bebaskan bed & unlock pasien', 'Batal inap/transfer wajib menutup end_date kamar & mengembalikan lockstatus pasien, agar bed & pasien bisa dipakai lagi.'],
                                ['Audit setiap batal', 'appendAdminLog{RI,RJ,UGD} untuk tiap pembatalan — jejak siapa & kapan.'],
                            ] as $i => [$judul, $isi])
                                <div class="ds-card-outline" style="padding:16px 20px">
                                    <div class="flex items-start gap-3">
                                        <span style="display:inline-flex;align-items:center;justify-content:center;min-width:24px;height:24px;border-radius:9999px;background:var(--primary);color:#fff;font-size:12px;font-weight:700;flex:none">{{ $i + 1 }}</span>
                                        <div>
                                            <div class="ds-title-sm mb-1">{{ $judul }}</div>
                                            <div class="ds-body-sm">{{ $isi }}</div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </section>

                    {{-- ====== 13 GLOSARIUM ====== --}}
                    <section x-show="section === 'glosarium'" x-cloak>
                        <div class="ds-eyebrow mb-3">14 — Referensi</div>
                        <h1 class="ds-display-md mb-4">Glosarium</h1>
                        <div class="ds-card-outline" style="padding:0; overflow-x:auto">
                            <table class="ds-table">
                                <thead><tr><th>Istilah</th><th>Arti</th></tr></thead>
                                <tbody>
                                    @foreach ([
                                        ['ri_status', 'Status RI: I=Dirawat, P=Pulang, F=Batal'],
                                        ['rj_status / txn_status', 'Status RJ/UGD: A=Aktif, I=Transfer Inap'],
                                        ['status_pulang', "Cara pulang: 'L'=Lunas, 'H'=Bon/Hutang"],
                                        ['rstxn_ritempadmins', 'Jembatan biaya RI — carry-over biaya UGD/RJ (kolom tempadm_flag). Link utama transfer UGD↔RI'],
                                        ['rstxn_ugdtempadmins', 'Jembatan biaya sementara UGD sebelum transfer'],
                                        ['rstxn_ribiayaselamadugds', 'Tabel link tambahan UGD→RI (rj_no ↔ ugd_no_rsri) — bisa kosong utk data lama'],
                                        ['rsmst_trfrooms', 'Riwayat kamar RI (start_date/end_date) — end_date kosong = bed sedang ditempati'],
                                        ['tempadm_flag', "Penanda asal biaya di ritempadmins: 'UGD' / 'RJ'"],
                                        ['lockstatus', 'Penanda pasien sedang dikunci di satu jalur (UGD/RI) agar tak dobel'],
                                        ['Batal Transaksi', 'Batalkan pembayaran/pulang → status kembali sebelum-bayar'],
                                        ['Batal Transfer', 'Batalkan transfer UGD→RI → RI dihapus, UGD kembali Aktif'],
                                        ['Batal Inap', 'Batalkan admisi RI → ri_status F (soft)'],
                                        ['Bon', 'Pembayaran kurang dari tagihan — sisa jadi piutang pasien'],
                                    ] as [$istilah, $arti])
                                        <tr>
                                            <td class="ds-td-strong" style="white-space:nowrap">{{ $istilah }}</td>
                                            <td class="ds-body-sm">{{ $arti }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </section>

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
