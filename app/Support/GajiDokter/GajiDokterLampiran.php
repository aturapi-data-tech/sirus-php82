<?php

namespace App\Support\GajiDokter;

use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Rincian transaksi jasa dokter per pasien — baris yang dipasok SIRUS ke SIAP
 * (GET /api/siap/gaji-dokter/{tahun}/{bulan}/transaksi) dan yang dibuka di
 * modal Rincian layar Monitoring Jasa Dokter.
 *
 * Lahir sebagai lampiran slip gaji dokter (Agustus 2026). Slip gaji dokter kini
 * dihitung di SIAP; yang tersisa di sini adalah pembongkar agregat view menjadi
 * daftar transaksi: tanggal layanan, nomor rekam medis, nama pasien, klaim,
 * dan nominalnya masing-masing.
 *
 * ANGKANYA DARI MANA
 *   Nominal diambil dari RSVIEW_NEWDOCSALARIES — view yang sama dengan rekap
 *   per dokter (MonitoringJasaDokter) dan laporan Pendapatan Jasa Dokter. Itu
 *   disengaja: kalau rincian memakai sumber lain, dua angka yang dilihat
 *   bersamaan bisa berbeda dan tidak ada cara menentukan mana yang benar.
 *
 * TANGGALNYA DARI MANA — DAN KENAPA TIDAK DARI VIEW
 *   DOC_DATE di view BUKAN tanggal layanan untuk jalur rawat inap. Komponen
 *   VISIT, KONSUL, JD RI, OPERATOR, RAD RI, dan semua *TRF di-anchor ke
 *   EXIT_DATE (tanggal pasien pulang) supaya jasanya jatuh di bulan yang sudah
 *   final. Akibatnya seluruh visite satu episode tampil pada tanggal yang sama
 *   — tidak berguna sebagai lampiran.
 *
 *   Karena itu tanggal ditarik ulang dari tabel sumbernya (VISIT_DATE,
 *   KONSUL_DATE, ACTD_DATE, OK_DATE, RIRAD_DATE, RJ_DATE), sementara PERIODE
 *   tetap disaring memakai DOC_DATE. Dua hal yang berbeda dan keduanya perlu:
 *   periode menentukan baris mana yang masuk bulan jasa, tanggal layanan
 *   menentukan apa yang tampil di rincian. Konsekuensi yang harus dipahami
 *   pembaca: baris rawat inap bisa bertanggal BULAN LAIN dari bulan jasa, dan
 *   itu benar — pasien dirawat Mei, pulang Juni, jasanya masuk bulan jasa Juni.
 *
 * SATU KUERI PER KOMPONEN, BUKAN PER BARIS — DAN BUKAN PER DOKTER
 *   Identitas pasien di-lookup sekali per DESC_DOC dengan WHERE IN atas
 *   seluruh nomor transaksinya, jadi jumlah kueri tetap (paling banyak 22)
 *   berapa pun banyak barisnya. Semua dokter sekaligus memakai jalur yang SAMA
 *   lewat barisMassal(): satu tarikan 36 dokter tetap ~23 kueri, bukan 36 x 23.
 *   Itu sebabnya baris() cuma pembungkus tipis — kalau keduanya punya jalur
 *   sendiri, cepat atau lambat hasil satu dokter dan semua dokter akan berbeda.
 */
class GajiDokterLampiran
{
    /** Batas daftar IN Oracle (ORA-01795). */
    protected const BATAS_IN = 1000;

    /**
     * Keterangan untuk komponen yang satu nomornya memuat beberapa jasa
     * (klinik, transfer): jasa pertama + jumlah sisanya. Oracle 10g tidak
     * punya LISTAGG, dan menyambung semua nama pun tidak muat di kolom.
     */
    protected const GABUNG_JASA = "CASE WHEN COUNT(*) > 1 THEN MIN(m.accdoc_desc) || ' (+' || TO_CHAR(COUNT(*) - 1) || ' jasa lain)' ELSE MIN(m.accdoc_desc) END";

    /**
     * Nama panjang tiap kode komponen jasa (DESC_DOC RSVIEW_NEWDOCSALARIES), untuk layar, CSV, dan JSON API.
     *
     * Kodenya warisan Oracle Dev 6i dan sengaja TIDAK diubah — dipakai apa adanya sebagai 'komponen' di JSON
     * SIAP. Yang diganti hanya teks yang dibaca orang: "UP RJ" tidak berarti apa-apa bagi dokter, "Uang Periksa
     * Rawat Jalan" berarti. TRF = transfer, pasien yang berpindah unit; tarifnya terpisah dari kunjungan biasa.
     * Dipindah dari kelas GajiDokter (mesin slip, dihapus 2026-11-07) — hanya bagian jasa yang masih relevan.
     */
    public const LABEL_KOMPONEN = [
        'UP RJ' => 'Uang Periksa Rawat Jalan',
        'JD RJ' => 'Jasa Dokter Rawat Jalan',
        'UP RJTRF' => 'Uang Periksa Rawat Jalan Transfer',
        'JD RJTRF' => 'Jasa Dokter Rawat Jalan Transfer',
        'UP UGD' => 'Uang Periksa UGD',
        'JD UGD' => 'Jasa Dokter UGD',
        'UP UGDTRF' => 'Uang Periksa UGD Transfer',
        'JD UGDTRF' => 'Jasa Dokter UGD Transfer',
        'VISIT' => 'Visite Rawat Inap',
        'KONSUL' => 'Konsultasi Rawat Inap',
        'JD RI' => 'Jasa Dokter Rawat Inap',
        'OPERATOR' => 'Operator Bedah',
        'ANASTESI' => 'Anestesi',
        'OPERATOR RJ' => 'Operator Bedah Rawat Jalan',
        'ANASTESI RJ' => 'Anestesi Rawat Jalan',
        'OPERATOR UGD' => 'Operator Bedah UGD',
        'ANASTESI UGD' => 'Anestesi UGD',
        'UP KLINIK' => 'Uang Periksa Klinik',
        'JD KLINIK' => 'Jasa Dokter Klinik',
        'RAD RJ' => 'Bacaan Radiologi Rawat Jalan',
        'RAD UGD' => 'Bacaan Radiologi UGD',
        'RAD RI' => 'Bacaan Radiologi Rawat Inap',
    ];

    /** Nama panjang satu kode komponen; kode yang belum terdaftar ditampilkan apa adanya. */
    public static function labelKomponen(string $kode): string
    {
        return self::LABEL_KOMPONEN[$kode] ?? $kode;
    }

    /**
     * Daftar transaksi satu dokter untuk satu periode jasa.
     *
     * @return Collection<int, array{desc_doc:string,group_doc:string,label:string,seq:int,txn_no:string,tgl:string,tgl_sort:string,reg_no:string,nama:string,nominal:float,pasien:int}>
     */
    public static function baris(string $drId, string $tahunJasa, string $bulanJasa): Collection
    {
        return self::barisMassal([$drId], $tahunJasa, $bulanJasa)->get($drId) ?? collect();
    }

    /**
     * Daftar transaksi BANYAK dokter sekaligus, dikelompokkan per DR_ID.
     *
     * @param array<int, string> $drIds
     * @return Collection<string, Collection>
     */
    public static function barisMassal(array $drIds, string $tahunJasa, string $bulanJasa): Collection
    {
        $drIds = array_values(array_unique(array_filter($drIds, fn ($drIdItem) => trim((string) $drIdItem) !== '')));

        if ($drIds === []) {
            return collect();
        }

        $periode = $bulanJasa . '/' . $tahunJasa;

        // View sudah ter-GROUP BY per TXN_NO; SUM di sini hanya jaring pengaman
        // supaya satu nomor transaksi tetap menghasilkan satu baris lampiran.
        $transaksi = collect();

        foreach (collect($drIds)->chunk(self::BATAS_IN) as $bagianDokter) {
            $transaksi = $transaksi->concat(
                DB::table('rsview_newdocsalaries as v')
                    ->select([
                        'v.dr_id',
                        'v.group_doc',
                        'v.desc_doc',
                        'v.txn_no',
                        'v.doc_date',
                        DB::raw('SUM(v.doc_nominal) AS nominal'),
                        DB::raw('SUM(v.jml_pasien) AS pasien'),
                        DB::raw('MIN(v.group_seq) AS seq'),
                        // MIN, bukan ikut GROUP BY: klaim_id berasal dari header
                        // transaksinya, jadi satu TXN_NO pasti satu klaim. Dipakai
                        // MIN supaya pengelompokan barisnya tidak ikut berubah.
                        DB::raw('MIN(v.klaim_id) AS klaim_id'),
                    ])
                    ->whereRaw("to_char(to_date(v.doc_date, 'dd/mm/yyyy'), 'mm/yyyy') = ?", [$periode])
                    ->whereIn('v.dr_id', $bagianDokter->values()->all())
                    ->groupBy('v.dr_id', 'v.group_doc', 'v.desc_doc', 'v.txn_no', 'v.doc_date')
                    ->get()
            );
        }

        if ($transaksi->isEmpty()) {
            return collect();
        }

        // Sekali untuk seluruh berkas — isinya belasan baris master, jadi lebih
        // murah dipetakan di PHP daripada di-join ke view yang menyapu seluruh
        // riwayat.
        $petaKlaim = DB::table('rsmst_klaimtypes')->get()
            ->keyBy(fn ($jenis) => trim((string) $jenis->klaim_id));

        // Lookup identitas dikumpulkan LINTAS DOKTER: satu komponen = satu
        // kueri untuk seluruh berkas. Nomor transaksi unik per tabel sumber,
        // jadi tidak ada risiko baris dokter A terbaca sebagai milik dokter B.
        $identitas = [];
        foreach ($transaksi->groupBy('desc_doc') as $descDoc => $barisKomponen) {
            $identitas[$descDoc] = self::identitasPasien(
                (string) $descDoc,
                $barisKomponen->pluck('txn_no')->map(fn ($nomor) => self::kunci($nomor))->unique()->values()->all(),
            );
        }

        return $transaksi
            ->map(function ($baris) use ($identitas, $petaKlaim) {
                $sumber = $identitas[$baris->desc_doc][self::kunci($baris->txn_no)] ?? null;

                // Tanpa sumber (nomor transaksi tak ketemu, atau tabelnya
                // menolak dibaca) tanggal jatuh balik ke DOC_DATE. Tanggal
                // pulang lebih baik daripada baris tanpa tanggal sama sekali,
                // asalkan barisnya tetap tampil dan nominalnya tetap benar.
                $tanggal = $sumber->tgl ?? $baris->doc_date;

                $klaim = $petaKlaim[trim((string) $baris->klaim_id)] ?? null;

                return [
                    'dr_id' => (string) $baris->dr_id,
                    'group_doc' => (string) $baris->group_doc,
                    'desc_doc' => (string) $baris->desc_doc,
                    'label' => self::labelKomponen((string) $baris->desc_doc),
                    'seq' => (int) $baris->seq,
                    'txn_no' => self::kunci($baris->txn_no),
                    'tgl' => (string) $tanggal,
                    'tgl_sort' => (string) ($sumber->tgl_sort ?? self::urutTanggal((string) $baris->doc_date)),
                    'reg_no' => trim((string) ($sumber->reg_no ?? '')),
                    'nama' => trim((string) ($sumber->nama ?? '')),
                    'nominal' => (float) $baris->nominal,
                    'pasien' => (int) $baris->pasien,
                    'klaim_id' => trim((string) $baris->klaim_id),
                    // Kode mentah dipakai apa adanya bila tidak ada di master —
                    // lebih berguna daripada sel kosong saat menelusuri.
                    'klaim' => trim((string) ($klaim->klaim_desc ?? $baris->klaim_id)),
                    'klaim_status' => trim((string) ($klaim->klaim_status ?? '')),
                    'no_sep' => trim((string) ($sumber->no_sep ?? '')),
                    // Nama jasa/tindakan/pemeriksaan/kasus operasi; kosong untuk
                    // uang periksa, visite, konsul.
                    'keterangan' => trim((string) ($sumber->keterangan ?? '')),
                ];
            })
            ->sortBy([['seq', 'asc'], ['tgl_sort', 'asc'], ['nama', 'asc']])
            ->groupBy('dr_id');
    }

    /* ===============================
     | INTERNAL
     =============================== */

    /**
     * Peta nomor transaksi -> identitas pasien & tanggal layanan, untuk satu
     * komponen. Dipecah per BATAS_IN supaya tidak kena ORA-01795.
     *
     * @param array<int, string> $nomorTransaksiList
     * @return array<string, object>
     */
    protected static function identitasPasien(string $descDoc, array $nomorTransaksiList): array
    {
        if ($nomorTransaksiList === []) {
            return [];
        }

        $petaIdentitas = [];

        foreach (collect($nomorTransaksiList)->chunk(self::BATAS_IN) as $bagianNomor) {
            $kueri = self::kueriSumber($descDoc, $bagianNomor->values()->all());

            if ($kueri === null) {
                return [];
            }

            try {
                foreach ($kueri->get() as $baris) {
                    $petaIdentitas[self::kunci($baris->txn_key)] = $baris;
                }
            } catch (QueryException $galat) {
                // Nama kolom pada tabel warisan tidak bisa diverifikasi dari
                // repo ini. Kalau salah satu meleset, yang gagal HANYA identitas
                // komponen tersebut — barisnya tetap tercetak dengan tanggal
                // DOC_DATE dan nama kosong, sementara nominal & totalnya utuh.
                // Cetakan yang kehilangan satu kolom jauh lebih baik daripada
                // cetakan yang gagal seluruhnya.
                report($galat);

                return [];
            }
        }

        return $petaIdentitas;
    }

    /**
     * Kueri identitas untuk satu komponen, atau null bila komponennya tidak
     * berasal dari transaksi pasien (mis. tunjangan & baris manual).
     *
     * Pemetaan TXN_NO -> tabel sumber mengikuti definisi RSVIEW_NEWDOCSALARIES
     * di database/sql/2026_07_31_view_docsalaries_ok_rj_ugd.sql. Ke-22 cabang
     * view punya arti TXN_NO yang berbeda-beda: ada yang nomor header (RJ_NO,
     * RIHDR_NO), ada yang nomor baris detail (RJHN_DTL, ACTD_NO, RAD_DTL).
     */
    protected static function kueriSumber(string $descDoc, array $nomorTransaksiList)
    {
        return match ($descDoc) {
            'UP RJ' => self::bentuk(
                DB::table('rstxn_rjhdrs as h'),
                'h.rj_no', 'h.rj_date', $nomorTransaksiList,
            ),

            // Jasa dokter: satu baris = satu jasa (RSMST_ACCDOCS.accdoc_desc),
            // jadi nama jasanya ikut sebagai keterangan — tanpa ini pembaca
            // tidak tahu baris "Jasa Dokter" itu konsultasi, tindakan, atau
            // apa (permintaan user 2026-09-08).
            'JD RJ' => self::bentuk(
                DB::table('rstxn_rjaccdocs as x')
                    ->join('rstxn_rjhdrs as h', 'h.rj_no', '=', 'x.rj_no')
                    ->leftJoin('rsmst_accdocs as m', 'm.accdoc_id', '=', 'x.accdoc_id'),
                'x.rjhn_dtl', 'h.rj_date', $nomorTransaksiList,
                ekspresiKeterangan: 'MIN(m.accdoc_desc)',
            ),

            'UP UGD' => self::bentuk(
                DB::table('rstxn_ugdhdrs as h'),
                'h.rj_no', 'h.rj_date', $nomorTransaksiList,
            ),

            'JD UGD' => self::bentuk(
                DB::table('rstxn_ugdaccdocs as x')
                    ->join('rstxn_ugdhdrs as h', 'h.rj_no', '=', 'x.rj_no')
                    ->leftJoin('rsmst_accdocs as m', 'm.accdoc_id', '=', 'x.accdoc_id'),
                'x.rjhn_dtl', 'h.rj_date', $nomorTransaksiList,
                ekspresiKeterangan: 'MIN(m.accdoc_desc)',
            ),

            // Pasien transfer: TXN_NO-nya RIHDR_NO, tapi jasanya lahir dari
            // kunjungan RJ/UGD SEBELUM transfer — tanggal layanannya di sana,
            // dijangkau lewat RSTXN_RITEMPADMINS. Nilai penanda ditulis literal,
            // bukan binding, supaya urutan binding tidak bergeser oleh JOIN.
            'UP RJTRF' => self::bentuk(
                DB::table('rstxn_ritempadmins as t')
                    ->join('rstxn_rihdrs as h', 'h.rihdr_no', '=', 't.rihdr_no')
                    ->join('rstxn_rjhdrs as a', 'a.rj_no', '=', 't.tempadm_ref')
                    ->whereRaw("t.tempadm_flag = 'RJ'"),
                't.rihdr_no', 'a.rj_date', $nomorTransaksiList,
            ),
            // JD transfer ber-TXN_NO RIHDR_NO yang menampung beberapa jasa
            // sekaligus → jasa pertama + jumlah sisanya (lihat GABUNG_JASA).
            'JD RJTRF' => self::bentuk(
                DB::table('rstxn_ritempadmins as t')
                    ->join('rstxn_rihdrs as h', 'h.rihdr_no', '=', 't.rihdr_no')
                    ->join('rstxn_rjhdrs as a', 'a.rj_no', '=', 't.tempadm_ref')
                    ->join('rstxn_rjaccdocs as e', 'e.rj_no', '=', 'a.rj_no')
                    ->leftJoin('rsmst_accdocs as m', 'm.accdoc_id', '=', 'e.accdoc_id')
                    ->whereRaw("t.tempadm_flag = 'RJ'"),
                't.rihdr_no', 'a.rj_date', $nomorTransaksiList,
                ekspresiKeterangan: self::GABUNG_JASA,
            ),

            'UP UGDTRF' => self::bentuk(
                DB::table('rstxn_ritempadmins as t')
                    ->join('rstxn_rihdrs as h', 'h.rihdr_no', '=', 't.rihdr_no')
                    ->join('rstxn_ugdhdrs as a', 'a.rj_no', '=', 't.tempadm_ref')
                    ->whereRaw("t.tempadm_flag = 'UGD'"),
                't.rihdr_no', 'a.rj_date', $nomorTransaksiList,
            ),
            'JD UGDTRF' => self::bentuk(
                DB::table('rstxn_ritempadmins as t')
                    ->join('rstxn_rihdrs as h', 'h.rihdr_no', '=', 't.rihdr_no')
                    ->join('rstxn_ugdhdrs as a', 'a.rj_no', '=', 't.tempadm_ref')
                    ->join('rstxn_ugdaccdocs as e', 'e.rj_no', '=', 'a.rj_no')
                    ->leftJoin('rsmst_accdocs as m', 'm.accdoc_id', '=', 'e.accdoc_id')
                    ->whereRaw("t.tempadm_flag = 'UGD'"),
                't.rihdr_no', 'a.rj_date', $nomorTransaksiList,
                ekspresiKeterangan: self::GABUNG_JASA,
            ),

            'VISIT' => self::bentuk(
                DB::table('rstxn_rivisits as x')
                    ->join('rstxn_rihdrs as h', 'h.rihdr_no', '=', 'x.rihdr_no'),
                'x.visit_no', 'x.visit_date', $nomorTransaksiList,
            ),

            'KONSUL' => self::bentuk(
                DB::table('rstxn_rikonsuls as x')
                    ->join('rstxn_rihdrs as h', 'h.rihdr_no', '=', 'x.rihdr_no'),
                'x.konsul_no', 'x.konsul_date', $nomorTransaksiList,
            ),

            // Tindakan RI: nama jasa + ACTD_KET (catatan bebas petugas) bila ada.
            'JD RI' => self::bentuk(
                DB::table('rstxn_riactdocs as x')
                    ->join('rstxn_rihdrs as h', 'h.rihdr_no', '=', 'x.rihdr_no')
                    ->leftJoin('rsmst_accdocs as m', 'm.accdoc_id', '=', 'x.accdoc_id'),
                'x.actd_no', 'x.actd_date', $nomorTransaksiList,
                ekspresiKeterangan: "MIN(CASE WHEN x.actd_ket IS NOT NULL THEN m.accdoc_desc || ' - ' || x.actd_ket ELSE m.accdoc_desc END)",
            ),

            // Kamar operasi: nama tindakan dari RSTXN_OKACTS → RSMST_ACCDOCS (satu
            // operasi bisa beberapa tindakan → jasa pertama + sisanya). CASE_ID/
            // RSMST_OKCASES tidak dipakai: isinya kosong & masternya cuma sewa alat.
            'OPERATOR', 'ANASTESI' => self::bentuk(
                DB::table('rstxn_oks as x')
                    ->join('rstxn_rihdrs as h', 'h.rihdr_no', '=', 'x.rihdr_no')
                    ->leftJoin('rstxn_okacts as e', 'e.ok_reg', '=', 'x.ok_reg')
                    ->leftJoin('rsmst_accdocs as m', 'm.accdoc_id', '=', 'e.accdoc_id'),
                'x.ok_reg', 'x.ok_date', $nomorTransaksiList,
                ekspresiKeterangan: self::GABUNG_JASA,
            ),

            'OPERATOR RJ', 'ANASTESI RJ' => self::bentuk(
                DB::table('rstxn_oks as x')
                    ->join('rstxn_rjhdrs as h', 'h.rj_no', '=', 'x.ref_no')
                    ->leftJoin('rstxn_okacts as e', 'e.ok_reg', '=', 'x.ok_reg')
                    ->leftJoin('rsmst_accdocs as m', 'm.accdoc_id', '=', 'e.accdoc_id')
                    ->whereRaw("x.status_rjri = 'RJ'"),
                'x.ok_reg', 'x.ok_date', $nomorTransaksiList,
                ekspresiKeterangan: self::GABUNG_JASA,
            ),

            'OPERATOR UGD', 'ANASTESI UGD' => self::bentuk(
                DB::table('rstxn_oks as x')
                    ->join('rstxn_ugdhdrs as h', 'h.rj_no', '=', 'x.ref_no')
                    ->leftJoin('rstxn_okacts as e', 'e.ok_reg', '=', 'x.ok_reg')
                    ->leftJoin('rsmst_accdocs as m', 'm.accdoc_id', '=', 'e.accdoc_id')
                    ->whereRaw("x.status_rjri = 'UGD'"),
                'x.ok_reg', 'x.ok_date', $nomorTransaksiList,
                ekspresiKeterangan: self::GABUNG_JASA,
            ),

            // Klinik: kedua komponennya ber-TXN_NO nomor header yang sama.
            'UP KLINIK' => self::bentuk(
                DB::table('rstxn_rjhdrks as h'),
                'h.rj_no', 'h.rj_date', $nomorTransaksiList,
                adaSep: false,
            ),
            // JD klinik: satu RJ_NO menampung beberapa jasa → jasa pertama + sisanya.
            'JD KLINIK' => self::bentuk(
                DB::table('rstxn_rjhdrks as h')
                    ->join('rstxn_rjaccdocks as e', 'e.rj_no', '=', 'h.rj_no')
                    ->leftJoin('rsmst_accdocs as m', 'm.accdoc_id', '=', 'e.accdoc_id'),
                'h.rj_no', 'h.rj_date', $nomorTransaksiList,
                adaSep: false,
                ekspresiKeterangan: self::GABUNG_JASA,
            ),

            // Radiologi: satu baris = satu pemeriksaan (RSMST_RADIOLOGIS.rad_desc).
            'RAD RJ' => self::bentuk(
                DB::table('rstxn_rjrads as x')
                    ->join('rstxn_rjhdrs as h', 'h.rj_no', '=', 'x.rj_no')
                    ->leftJoin('rsmst_radiologis as m', 'm.rad_id', '=', 'x.rad_id'),
                'x.rad_dtl', 'h.rj_date', $nomorTransaksiList,
                ekspresiKeterangan: 'MIN(m.rad_desc)',
            ),

            'RAD UGD' => self::bentuk(
                DB::table('rstxn_ugdrads as x')
                    ->join('rstxn_ugdhdrs as h', 'h.rj_no', '=', 'x.rj_no')
                    ->leftJoin('rsmst_radiologis as m', 'm.rad_id', '=', 'x.rad_id'),
                'x.rad_dtl', 'h.rj_date', $nomorTransaksiList,
                ekspresiKeterangan: 'MIN(m.rad_desc)',
            ),

            'RAD RI' => self::bentuk(
                DB::table('rstxn_riradiologs as x')
                    ->join('rstxn_rihdrs as h', 'h.rihdr_no', '=', 'x.rihdr_no')
                    ->leftJoin('rsmst_radiologis as m', 'm.rad_id', '=', 'x.rad_id'),
                'x.rirad_no', 'x.rirad_date', $nomorTransaksiList,
                ekspresiKeterangan: 'MIN(m.rad_desc)',
            ),

            default => null,
        };
    }

    /**
     * Rangka kueri identitas yang sama untuk semua komponen.
     *
     * Selalu ber-GROUP BY nomor transaksi walau kebanyakan cabang sudah unik.
     * Yang tidak unik ada dua: transfer (satu RIHDR_NO bisa punya lebih dari
     * satu baris RSTXN_RITEMPADMINS) dan klinik (satu RJ_NO menampung beberapa
     * baris jasa). Tanpa agregat keduanya menghasilkan kunci kembar yang
     * diam-diam saling menimpa di peta.
     */
    protected static function bentuk(
        $kueri,
        string $kolomKunci,
        string $kolomTanggal,
        array $nomorTransaksiList,
        bool $adaSep = true,
        ?string $ekspresiKeterangan = null,
    ) {
        // RSTXN_RJHDRKS (klinik) TIDAK punya kolom VNO_SEP — diperiksa langsung
        // ke USER_TAB_COLUMNS 2026-08-02, sementara RJHDRS/UGDHDRS/RIHDRS
        // punya. Menyeragamkannya berarti ORA-00904 pada cabang klinik saja,
        // dan itu tipe kegagalan yang baru ketahuan di produksi karena komponen
        // klinik tidak muncul pada tiap dokter. NULL di-cast supaya Oracle tahu
        // tipe kolomnya saat cabang ini digabungkan di sisi PHP.
        $ekspresiSep = $adaSep ? 'MIN(h.vno_sep)' : "CAST(NULL AS VARCHAR2(30))";

        // Keterangan layanan = nama jasa/tindakan/pemeriksaan/kasus operasi.
        // Uang periksa, visite, dan konsul tidak punya padanannya (komponennya
        // sendiri sudah menjelaskan). NULL di-cast supaya Oracle tahu tipenya
        // saat semua cabang digabung di PHP.
        $ekspresiKeterangan ??= "CAST(NULL AS VARCHAR2(200))";

        return $kueri
            ->leftJoin('rsmst_pasiens as p', 'p.reg_no', '=', 'h.reg_no')
            ->whereIn($kolomKunci, $nomorTransaksiList)
            ->select([
                DB::raw("{$kolomKunci} AS txn_key"),
                DB::raw('MIN(h.reg_no) AS reg_no'),
                DB::raw('MIN(p.reg_name) AS nama'),
                DB::raw("to_char(MIN({$kolomTanggal}), 'dd/mm/yyyy') AS tgl"),
                DB::raw("to_char(MIN({$kolomTanggal}), 'yyyymmdd') AS tgl_sort"),
                DB::raw("{$ekspresiSep} AS no_sep"),
                DB::raw("{$ekspresiKeterangan} AS keterangan"),
            ])
            ->groupBy(DB::raw($kolomKunci));
    }

    /**
     * Samakan bentuk nomor transaksi dari dua sisi.
     *
     * Oracle mengembalikan NUMBER kadang sebagai '1234', kadang '1234.0'
     * tergantung driver — kalau dipakai sebagai kunci larik apa adanya, sisi
     * view dan sisi tabel sumber tidak pernah bertemu dan seluruh nama pasien
     * tampil kosong. Nomor non-numerik (OK_REG bisa beralfabet) dibiarkan.
     */
    protected static function kunci($nomor): string
    {
        $teks = trim((string) $nomor);

        return is_numeric($teks) ? (string) (0 + $teks) : $teks;
    }

    /** 'dd/mm/yyyy' -> 'yyyymmdd' untuk pengurutan. */
    protected static function urutTanggal(string $tanggal): string
    {
        $bagianTanggal = explode('/', $tanggal);

        return count($bagianTanggal) === 3
            ? $bagianTanggal[2] . $bagianTanggal[1] . $bagianTanggal[0]
            : $tanggal;
    }

    /* ===============================
     | RINCIAN SIAP SAJI (modal layar, CSV)
     * =============================== */

    /** Judul kolom CSV — urutannya bersebelahan dengan nilai di tulisCsv(). */
    public const KOLOM_CSV = [
        'Dokter', 'Periode Jasa', 'Komponen', 'Tanggal',
        'No. RM', 'Nama Pasien', 'Keterangan Jasa', 'Klaim', 'Jenis Klaim', 'No. SEP',
        'No. Transaksi', 'Nominal',
    ];

    public const NAMA_BULAN = [
        '01' => 'Januari', '02' => 'Februari', '03' => 'Maret', '04' => 'April',
        '05' => 'Mei', '06' => 'Juni', '07' => 'Juli', '08' => 'Agustus',
        '09' => 'September', '10' => 'Oktober', '11' => 'November', '12' => 'Desember',
    ];

    /**
     * Satu entri per dokter dari DR_ID + bulan jasa — untuk modal Rincian Monitoring Jasa Dokter dan
     * tulisCsv(). Header disusun sendiri (identitas + periode); tidak ada rekonsiliasi dengan slip dan
     * tidak ada penandaan grup kapita — keduanya urusan SIAP sejak penggajian pindah ke sana (2026-11).
     *
     * @param  array<int, string>  $drIdList
     * @return Collection<int, array{header: object, lampiran: Collection, perKomponen: Collection, angka: array{totalTransaksi: float}}>
     */
    public static function susunPerDokter(array $drIdList, string $tahunJasa, string $bulanJasa): Collection
    {
        $drIdList = array_values(array_unique(array_filter(array_map(fn ($drId) => trim((string) $drId), $drIdList), fn ($drId) => $drId !== '')));

        if ($drIdList === []) {
            return collect();
        }

        $lampiranPerDokter = self::barisMassal($drIdList, $tahunJasa, $bulanJasa);

        if ($lampiranPerDokter->isEmpty()) {
            return collect();
        }

        // Nama dari master, bukan dari view: view menyalin nama saat transaksi dibuat.
        $namaDokter = DB::table('rsmst_doctors')
            ->whereIn('dr_id', $lampiranPerDokter->keys()->all())
            ->pluck('dr_name', 'dr_id')
            ->mapWithKeys(fn ($nama, $drId) => [(string) $drId => trim((string) $nama)]);

        return $lampiranPerDokter
            ->map(function (Collection $lampiran, $drId) use ($namaDokter, $tahunJasa, $bulanJasa) {
                return [
                    'header' => (object) [
                        'dr_id' => (string) $drId,
                        'dr_name' => $namaDokter[(string) $drId] ?? (string) $drId,
                        'tahun_jasa' => $tahunJasa,
                        'bulan_jasa' => $bulanJasa,
                    ],
                    'lampiran' => $lampiran,
                    'perKomponen' => $lampiran->groupBy('desc_doc'),
                    'angka' => ['totalTransaksi' => (float) $lampiran->sum('nominal')],
                ];
            })
            ->sortBy(fn ($entri) => $entri['header']->dr_name)
            ->values();
    }

    /**
     * Tulis rincian (satu atau banyak dokter, hasil susunPerDokter()) sebagai CSV siap Excel ke $keluaran.
     *
     * BENTUKNYA SENGAJA PIPIH, TIDAK MENIRU PENGELOMPOKAN DI LAYAR: baris judul & subtotal
     * akan merusak pivot. Kolom Dokter & Periode ikut di SETIAP baris supaya ekspor banyak
     * dokter langsung bisa dipivot.
     *
     * JANGAN menambahkan baris "sep=;". Excel tidak mengenalinya bila didahului BOM (dan BOM
     * wajib demi nama pasien beraksen), sehingga barisnya tercetak sebagai data. Excel berlokal
     * Indonesia sudah memakai ';' sebagai pemisah daftar.
     *
     * @param  resource  $keluaran
     */
    public static function tulisCsv($keluaran, iterable $dataLampiran): void
    {
        // BOM UTF-8 — tanpa ini Excel membaca berkas sebagai ANSI dan nama pasien beraksen rusak.
        fwrite($keluaran, "\xEF\xBB\xBF");
        fputcsv($keluaran, self::KOLOM_CSV, ';', '"', '\\');

        foreach ($dataLampiran as $satuDokter) {
            $header = $satuDokter['header'];
            $periodeJasa = (self::NAMA_BULAN[$header->bulan_jasa] ?? $header->bulan_jasa) . ' ' . $header->tahun_jasa;

            foreach ($satuDokter['lampiran'] as $baris) {
                fputcsv($keluaran, [
                    $header->dr_name,
                    $periodeJasa,
                    $baris['label'],
                    $baris['tgl'],
                    $baris['reg_no'],
                    $baris['nama'],
                    // Nama jasa/tindakan/pemeriksaan/kasus operasi; kosong untuk uang periksa, visite, konsul.
                    $baris['keterangan'],
                    $baris['klaim'],
                    // Jenis klaim (BPJS/UMUM/KRONIS/DOKEL) dipisah dari namanya supaya bisa jadi kolom pivot.
                    $baris['klaim_status'],
                    // Kosong itu WAJAR: pasien umum tidak punya SEP, RSTXN_RJHDRKS tidak menyimpan kolomnya.
                    $baris['no_sep'],
                    $baris['txn_no'],
                    // Angka MENTAH, bukan 450.000 — nominal bertitik dibaca Excel sebagai teks, SUM-nya nol.
                    (int) round((float) $baris['nominal']),
                ], ';', '"', '\\'); // escape = bawaan PHP, ditulis eksplisit (PHP 8.4 deprecation)
            }
        }
    }
}
