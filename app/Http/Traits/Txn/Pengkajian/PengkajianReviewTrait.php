<?php

namespace App\Http\Traits\Txn\Pengkajian;

use App\Support\OracleLob;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Helper bersama modul Pengkajian Medis — pakai-ulang & review pengkajian
 * (Akreditasi PP 1.2 poin e).
 *
 * ATURANNYA: pengkajian medis yang dibuat <= 30 hari sebelum pasien masuk rawat
 * inap atau sebelum menjalani prosedur di rawat jalan BOLEH dipakai lagi, asal
 * ditinjau/diverifikasi dan diperbarui sesuai kondisi terkini. Lebih dari 30
 * hari, WAJIB pengkajian ulang.
 *
 * DITARUH LINTAS-JALUR (bukan di Txn/Rj) karena aturan itu menyebut rawat jalan
 * DAN rawat inap. Semua method menerima jenis+nomor kunjungan, jadi RI tinggal
 * memakai trait yang sama tanpa menyalin logikanya — pola yang sama dengan
 * Txn/Penunjang/KamarOperasiTrait.
 *
 * BENTUK PENYIMPANAN. Tabel RSTXN_PENGKAJIAN_REVIEWS cuma punya tiga kolom:
 * REVIEW_NO, REG_NO, dan REVIEW_JSON (CLOB). Semua sisanya — kunjungan pemakai,
 * sumber, tanggal, keputusan, formulir, tanda tangan — ada di dalam JSON.
 * REG_NO satu-satunya penyaring lewat SQL karena Oracle di sini TIDAK mendukung
 * JSON_VALUE (ORA-00904); pencocokan kunjungan diselesaikan di PHP, aman karena
 * satu pasien hanya punya segelintir review.
 *
 * Cara baca/tulis CLOB-nya mencontoh App\Http\Traits\Txn\Rj\EmrRJTrait:
 * OracleLob untuk baca, JSON_THROW_ON_ERROR untuk decode, lockForUpdate sebelum
 * read-modify-write, dan bendera encode yang sama.
 *
 * Rancangan kolom & bentuk JSON: docs/ddl-pengkajian-medis-pp12.sql
 */
trait PengkajianReviewTrait
{
    /** Ambang PP 1.2 poin e — pengkajian <= 30 hari boleh dipakai ulang. */
    protected const AMBANG_HARI_PENGKAJIAN = 30;

    /** Pasien lama bisa punya ratusan kunjungan; panel tak perlu memuat semuanya. */
    protected const BATAS_RIWAYAT_PENGKAJIAN = 20;

    /** Bendera encode SAMA dengan updateJsonRJ — termasuk UNESCAPED_SLASHES. */
    protected const JSON_FLAGS_PENGKAJIAN = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR;

    /**
     * Review milik SATU kunjungan. Disaring lewat REG_NO (terindeks), lalu
     * dicocokkan di PHP karena kunjungan pemakainya ada di dalam JSON.
     *
     * @return array{0: ?object, 1: array} baris + isi JSON-nya
     */
    protected function findDataPengkajianReview(string $regNo, string $jenisKunjungan, int $nomorKunjungan): array
    {
        if (blank($regNo)) {
            return [null, []];
        }

        foreach (DB::table('rstxn_pengkajian_reviews')->where('reg_no', $regNo)->get() as $row) {
            $payload = $this->readJsonPengkajianReview($row);

            if (($payload['pemakai']['jenis'] ?? '') === $jenisKunjungan && (int) ($payload['pemakai']['no'] ?? 0) === $nomorKunjungan) {
                return [$row, $payload];
            }
        }

        return [null, []];
    }

    /**
     * SEMUA review pengkajian milik satu pasien, terbaru dulu — isi panel
     * "Riwayat Pengkajian".
     *
     * Beda dari findRiwayatPengkajian(): yang itu mendaftar KUNJUNGAN (calon
     * pengkajian yang bisa dipakai ulang), yang ini mendaftar REVIEW yang sudah
     * pernah dibuat — siapa meninjau pengkajian mana, kapan, dan hasilnya apa.
     *
     * Penyaringnya cuma REG_NO (kolom datar, terindeks). Pengurutan &
     * pemetaannya di PHP karena sisanya ada di CLOB dan Oracle di sini tak
     * mendukung JSON_VALUE — aman, satu pasien hanya punya segelintir review.
     *
     * @return array{0: array<int, array>, 1: int} daftar (sudah dipotong) + total sebenarnya
     */
    protected function findRiwayatReviewPengkajian(
        string $regNo,
        ?string $jenisKunjunganSekarang = null,
        ?int $nomorKunjunganSekarang = null
    ): array {
        if (blank($regNo)) {
            return [[], 0];
        }

        $daftar = [];

        foreach (DB::table('rstxn_pengkajian_reviews')->where('reg_no', $regNo)->get() as $row) {
            $payload = $this->readJsonPengkajianReview($row);

            if ($payload === []) {
                continue;
            }

            $form = $payload['form'] ?? [];
            $pemakaiJenis = (string) ($payload['pemakai']['jenis'] ?? '');
            $pemakaiNo = (int) ($payload['pemakai']['no'] ?? 0);

            $daftar[] = [
                'reviewNo' => (int) $row->review_no,
                'tglReview' => (string) ($payload['reviewDate'] ?? ''),
                'keputusan' => (string) ($payload['keputusan'] ?? ''),
                'usiaHari' => $payload['usiaHariSaatReview'] ?? null,
                'tglPengkajian' => (string) ($payload['tglPengkajian'] ?? ''),
                'sumberJenis' => (string) ($payload['sumber']['jenis'] ?? ''),
                'sumberNo' => $payload['sumber']['no'] ?? null,
                'sumberDeskripsi' => (string) ($payload['sumber']['deskripsi'] ?? ''),
                'pemakaiJenis' => $pemakaiJenis,
                'pemakaiNo' => $pemakaiNo,
                // Tindakan disajikan sebagai daftar teks, bukan tiga boolean mentah —
                // kartu riwayat butuh yang terbaca, bukan yang perlu ditafsirkan.
                'tindakan' => array_values(array_filter([
                    ! empty($form['tindakanTinjau']) ? 'Meninjau hasil pengkajian sebelumnya' : null,
                    ! empty($form['tindakanVerifikasi']) ? 'Verifikasi & update sesuai kondisi terkini' : null,
                    ! empty($form['tindakanUlang']) ? 'Pengkajian medis ulang' : null,
                ])),
                'adaPerubahan' => (string) ($form['adaPerubahan'] ?? ''),
                'perubahanDesc' => (string) ($form['perubahanDesc'] ?? ''),
                'catatan' => (string) ($form['reviewCatatan'] ?? ''),
                'ttdNama' => (string) ($payload['review']['drDesc'] ?? $form['ttdPengkajianReview'] ?? ''),
                'terkunci' => (bool) ($payload['review']['terkunci'] ?? false),
                // Penanda "ini review kunjungan yang sedang dibuka" — supaya petugas
                // tahu baris mana yang sedang ia kerjakan.
                'iniKunjunganIni' => $jenisKunjunganSekarang !== null
                    && $nomorKunjunganSekarang !== null
                    && $pemakaiJenis === $jenisKunjunganSekarang
                    && $pemakaiNo === $nomorKunjunganSekarang,
            ];
        }

        // Terbaru dulu. reviewDate itu teks d/m/Y H:i:s — tak bisa diurutkan
        // sebagai string, jadi diubah ke timestamp; yang tak terbaca jatuh ke 0
        // dan REVIEW_NO jadi pemutus supaya urutannya tetap pasti.
        usort($daftar, function (array $kiri, array $kanan): int {
            $waktuKiri = $this->parseWaktuReviewPengkajian($kiri['tglReview']);
            $waktuKanan = $this->parseWaktuReviewPengkajian($kanan['tglReview']);

            return $waktuKanan <=> $waktuKiri ?: $kanan['reviewNo'] <=> $kiri['reviewNo'];
        });

        $total = count($daftar);

        return [array_slice($daftar, 0, self::BATAS_RIWAYAT_PENGKAJIAN), $total];
    }

    /** reviewDate ('d/m/Y H:i:s') → timestamp; 0 bila tak terbaca. */
    private function parseWaktuReviewPengkajian(string $teks): int
    {
        if (blank($teks)) {
            return 0;
        }

        try {
            return Carbon::createFromFormat('d/m/Y H:i:s', $teks)->getTimestamp();
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * Lock baris review (SELECT FOR UPDATE) — mencontoh lockRJRow.
     * Wajib DI DALAM DB::transaction, sebelum read-modify-write REVIEW_JSON.
     *
     * @throws \RuntimeException bila barisnya tak ada
     */
    protected function lockPengkajianReviewRow(int $reviewNo): void
    {
        $exists = DB::table('rstxn_pengkajian_reviews')
            ->where('review_no', $reviewNo)
            ->lockForUpdate()
            ->exists();

        if (! $exists) {
            throw new \RuntimeException("Baris review #{$reviewNo} tidak ditemukan untuk di-lock.");
        }
    }

    /**
     * Simpan (buat atau perbarui) review satu kunjungan.
     *
     * ⚠️ Tidak membungkus DB::transaction sendiri agar tidak membuat nested
     *    transaction di caller. Selalu panggil DI DALAM DB::transaction.
     *
     * Node 'dibuat' dipertahankan dari baris lama supaya pembuat pertama tak
     * tertimpa tiap kali draft disimpan ulang.
     *
     * @throws \JsonException|\RuntimeException
     */
    protected function updateJsonPengkajianReview(string $regNo, string $jenisKunjungan, int $nomorKunjungan, array $payload): void
    {
        // REG_NO wajib. Tanpa penjaga ini, regNo kosong lolos sampai INSERT lalu
        // meledak ORA-01400 (kolomnya NOT NULL, dan di Oracle '' = NULL) —
        // pesan mentah yang tak menjelaskan apa pun ke petugas.
        //
        // Lebih halus lagi: findDataPengkajianReview() memulangkan [null, []] saat regNo
        // kosong, jadi kode akan mengira "belum ada review" dan mencoba INSERT baru
        // padahal barisnya mungkin sudah ada. Dihentikan di sini, bukan di sana.
        if (blank($regNo)) {
            throw new \RuntimeException('Nomor RM pasien kosong — review pengkajian tidak disimpan.');
        }

        // Whitelist jalur (skill naming-conventions §4): nilai di luar dugaan tak
        // boleh diam-diam tersimpan. Review ber-jenis salah tak akan pernah ketemu
        // lagi oleh findDataPengkajianReview() — jadi baris yatim yang tak terlihat.
        if (! in_array($jenisKunjungan, ['RJ', 'RI'], true)) {
            throw new \RuntimeException("Jenis kunjungan '{$jenisKunjungan}' tidak dikenal — hanya RJ atau RI.");
        }

        if ($nomorKunjungan <= 0) {
            throw new \RuntimeException('Nomor kunjungan kosong — review pengkajian tidak disimpan.');
        }

        [$rowLama, $payloadLama] = $this->findDataPengkajianReview($regNo, $jenisKunjungan, $nomorKunjungan);

        if ($rowLama) {
            $this->lockPengkajianReviewRow((int) $rowLama->review_no);
        }

        $payload['pemakai'] = ['jenis' => $jenisKunjungan, 'no' => $nomorKunjungan];
        $payload['dibuat'] = $payloadLama['dibuat'] ?? ($payload['dibuat'] ?? null);

        $kolom = [
            'reg_no' => $regNo,
            'review_json' => json_encode($payload, self::JSON_FLAGS_PENGKAJIAN),
        ];

        if ($rowLama) {
            DB::table('rstxn_pengkajian_reviews')->where('review_no', $rowLama->review_no)->update($kolom);

            return;
        }

        $kolom['review_no'] = DB::selectOne('SELECT seq_pengkajian_reviews.NEXTVAL n FROM DUAL')->n;
        DB::table('rstxn_pengkajian_reviews')->insert($kolom);
    }

    /**
     * Baca REVIEW_JSON satu baris. Mencontoh findDataRJ: CLOB lewat OracleLob
     * (anti ORA-01555/terpotong), decode dengan JSON_THROW_ON_ERROR.
     *
     * JSON rusak DILEMPAR, bukan diam-diam jadi array kosong — pada alur
     * read-modify-write, array kosong berarti menimpa isi lama dengan isian kosong.
     *
     * @throws \JsonException
     */
    protected function readJsonPengkajianReview(object $row): array
    {
        $json = OracleLob::read(
            $row->review_json ?? null,
            'rstxn_pengkajian_reviews',
            'review_no',
            $row->review_no,
            'review_json'
        );

        return $json === '' ? [] : json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * Riwayat pengkajian medis pasien, terbaru dulu.
     *
     * Penanda "kunjungan ini menghasilkan pengkajian medis" = ERM_STATUS 'L',
     * yaitu saat dokter menekan TTD-E Dokter Pemeriksa di EMR (yang sekaligus
     * mengunci EMR). Kolom itu SUDAH ADA dan sudah terisi puluhan ribu baris —
     * tak perlu kolom stempel baru, dan tak perlu isi mundur apa pun.
     *
     * Tanggalnya memakai RJ_DATE: untuk RJ satu kunjungan itu satu hari, jadi
     * tepat sampai ketelitian HARI — dan ambang 30 hari tak memerlukan jamnya.
     *
     * CATATAN RI: cabang RI sudah disiapkan tapi HARI INI belum menghasilkan apa
     * pun — RSTXN_RIHDRS.ERM_STATUS tak pernah bernilai 'L' karena EMR RI tak
     * punya TTD-E setara. Itu kekurangan yang masih terbuka, bukan bug query ini.
     *
     * @return array{0: array<int, array>, 1: int} daftar (sudah dipotong) + total sebenarnya
     */
    protected function findRiwayatPengkajian(
        string $regNo,
        string $jenisKunjunganSekarang,
        int $nomorKunjunganSekarang,
        ?string $teksTanggalAcuan = null
    ): array {
        if (blank($regNo)) {
            return [[], 0];
        }

        $rowList = DB::select(
            "SELECT * FROM (
                SELECT 'RJ' AS sumber_jenis,
                       h.rj_no     AS sumber_no,
                       h.rj_date   AS tgl,
                       d.dr_name   AS dokter,
                       p.poli_desc AS unit
                  FROM rstxn_rjhdrs h
                  LEFT JOIN rsmst_polis p   ON p.poli_id = h.poli_id
                  LEFT JOIN rsmst_doctors d ON d.dr_id = h.dr_id
                 WHERE h.reg_no = :reg1
                --    AND h.erm_status = 'L'
                   AND NOT (:jenisKunjungan1 = 'RJ' AND h.rj_no = :nomor1)
                UNION ALL
                SELECT 'RI' AS sumber_jenis,
                       r.rihdr_no   AS sumber_no,
                       r.entry_date AS tgl,
                       d2.dr_name   AS dokter,
                       'Rawat Inap' AS unit
                  FROM rstxn_rihdrs r
                  LEFT JOIN rsmst_doctors d2 ON d2.dr_id = r.dr_id
                 WHERE r.reg_no = :reg2
                --    AND r.erm_status = 'L'
                   AND NOT (:jenisKunjungan2 = 'RI' AND r.rihdr_no = :nomor2)
             ) ORDER BY tgl DESC",
            [
                'reg1' => $regNo,
                'jenisKunjungan1' => $jenisKunjunganSekarang,
                'nomor1' => $nomorKunjunganSekarang,
                'reg2' => $regNo,
                'jenisKunjungan2' => $jenisKunjunganSekarang,
                'nomor2' => $nomorKunjunganSekarang,
            ]
        );

        $acuan = $this->tanggalAcuanPengkajian($teksTanggalAcuan);

        // Pengkajian yang tanggalnya SESUDAH acuan belum terjadi pada saat itu —
        // dibuang, bukan ditampilkan dengan usia negatif.
        $rowList = array_values(array_filter(
            $rowList,
            fn($row) => $row->tgl === null || Carbon::parse($row->tgl)->startOfDay()->lte($acuan)
        ));
        $total = count($rowList);

        $daftar = collect(array_slice($rowList, 0, self::BATAS_RIWAYAT_PENGKAJIAN))
            ->map(function ($row) use ($acuan) {
                $tanggal = $row->tgl ? Carbon::parse($row->tgl) : null;
                // Usia DIHITUNG, tidak disimpan: turunan pasti dari dua tanggal.
                $usia = $tanggal ? (int) $tanggal->startOfDay()->diffInDays($acuan) : null;

                return [
                    'sumberJenis' => (string) $row->sumber_jenis,
                    'sumberNo' => (string) $row->sumber_no,
                    'tgl' => $tanggal?->format('d/m/Y'),
                    'dokter' => trim((string) ($row->dokter ?? '')) ?: '-',
                    'unit' => trim((string) ($row->unit ?? '')) ?: '-',
                    'usiaHari' => $usia,
                    'masihBerlaku' => $usia !== null && $usia <= self::AMBANG_HARI_PENGKAJIAN,
                ];
            })
            ->values()
            ->all();

        return [$daftar, $total];
    }

    /**
     * Tabel review sudah ada di environment ini?
     *
     * Membaca riwayat tak butuh DDL apa pun (memakai ERM_STATUS & RJ_DATE yang
     * sudah ada), tapi MENYIMPAN review butuh tabelnya. Diperiksa supaya layar
     * bisa menjelaskan keadaan, bukan meledak dengan ORA-00942.
     */
    protected function checkPengkajianReviewTable(): bool
    {
        return Cache::remember('ddl.pengkajian-medis.siap', now()->addMinutes(10), fn() => count(DB::select(
            "SELECT table_name FROM user_tables WHERE table_name = 'RSTXN_PENGKAJIAN_REVIEWS'"
        )) > 0);
    }

    /** Usia pengkajian dalam hari dari teks dd/mm/yyyy; null bila tak terbaca. */
    protected function calculateUsiaPengkajian(?string $teksTanggal, ?string $teksTanggalAcuan = null): ?int
    {
        $tanggal = $this->parseTanggalPengkajian($teksTanggal)?->startOfDay();
        if ($tanggal === null) {
            return null;
        }

        $usia = (int) $tanggal->diffInDays($this->tanggalAcuanPengkajian($teksTanggalAcuan));

        // Acuan lebih AWAL dari tanggal pengkajian = kombinasi mustahil (pengkajiannya
        // belum terjadi pada tanggal acuan). Carbon memulangkan angka NEGATIF, dan
        // -2063 <= 30 membuat keputusan berbalik jadi REVIEW — jalan pintas menembus
        // ambang 30 hari. Dipulangkan null supaya jatuh ke ULANG, bukan diloloskan.
        return $usia < 0 ? null : $usia;
    }

    /**
     * Tanggal pembanding usia pengkajian. Boleh digeser petugas (mis. merekam
     * review yang dilakukan kemarin); bila kosong/tak terbaca, jatuh ke hari ini.
     */
    protected function tanggalAcuanPengkajian(?string $teksTanggalAcuan = null): Carbon
    {
        return $this->parseTanggalPengkajian($teksTanggalAcuan)?->startOfDay() ?? now()->startOfDay();
    }

    /** REVIEW bila <= ambang, ULANG bila lebih (atau tanggalnya tak terbaca). */
    protected function calculateKeputusanPengkajian(?string $teksTanggal, ?string $teksTanggalAcuan = null): string
    {
        $usia = $this->calculateUsiaPengkajian($teksTanggal, $teksTanggalAcuan);

        return $usia !== null && $usia <= self::AMBANG_HARI_PENGKAJIAN ? 'REVIEW' : 'ULANG';
    }

    protected function parseTanggalPengkajian(?string $teksTanggal): ?Carbon
    {
        $teksTanggal = trim((string) $teksTanggal);
        if ($teksTanggal === '') {
            return null;
        }

        try {
            return Carbon::createFromFormat('d/m/Y', $teksTanggal);
        } catch (\Throwable) {
            return null;
        }
    }
}
