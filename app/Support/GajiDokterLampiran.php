<?php

namespace App\Support;

use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Lampiran rincian pasien untuk slip gaji dokter.
 *
 * Slip sendiri hanya menyimpan AGREGAT per komponen (RSTXN_GAJIDOCTORDTLS:
 * satu baris 'UP RJ' berisi total + jumlah pasien). Kelas ini membongkar
 * agregat itu kembali menjadi daftar transaksi: tanggal layanan, nomor rekam
 * medis, nama pasien, dan nominalnya masing-masing.
 *
 * ANGKANYA DARI MANA
 *   Nominal diambil dari RSVIEW_NEWDOCSALARIES — sumber yang sama persis
 *   dengan yang dipakai GajiDokter::generate(). Itu disengaja: kalau lampiran
 *   memakai sumber lain, dua kertas yang dicetak bersamaan bisa berbeda dan
 *   tidak ada cara menentukan mana yang benar.
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
 *   periode menentukan baris mana yang masuk slip, tanggal layanan menentukan
 *   apa yang tercetak di lampiran. Konsekuensi yang harus dipahami pembaca
 *   lampiran: baris rawat inap bisa bertanggal BULAN LAIN dari periode slip,
 *   dan itu benar — pasien dirawat Mei, pulang Juni, jasanya masuk gaji Juni.
 *
 * SATU KUERI PER KOMPONEN, BUKAN PER BARIS — DAN BUKAN PER DOKTER
 *   Identitas pasien di-lookup sekali per DESC_DOC dengan WHERE IN atas
 *   seluruh nomor transaksinya, jadi jumlah kueri tetap (paling banyak 22)
 *   berapa pun banyak barisnya. Cetak massal memakai jalur yang SAMA lewat
 *   barisMassal(): satu berkas berisi 36 dokter tetap ~23 kueri, bukan 36 x 23.
 *   Itu sebabnya baris() cuma pembungkus tipis — kalau keduanya punya jalur
 *   sendiri, cepat atau lambat hasil cetak satuan dan massal akan berbeda.
 */
class GajiDokterLampiran
{
    /** Batas daftar IN Oracle (ORA-01795). */
    protected const BATAS_IN = 1000;

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
                    'label' => GajiDokter::labelKomponen((string) $baris->desc_doc),
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
                    // Terisi hanya untuk komponen radiologi (nama pemeriksaan).
                    'keterangan' => trim((string) ($sumber->keterangan ?? '')),
                ];
            })
            ->sortBy([['seq', 'asc'], ['tgl_sort', 'asc'], ['nama', 'asc']])
            ->groupBy('dr_id');
    }

    /**
     * Grup komponen yang DIGANTIKAN baris per kapita pada slip dokter ini.
     *
     * Untuk dokter per kepala, komponen RI/RJ tidak dibayar per baris — yang
     * dipakai hanya jumlah pasiennya. Nominal komponennya tetap ditampilkan di
     * lampiran (itu dasar hitungnya), tapi harus ditandai supaya tidak dibaca
     * sebagai uang yang diterima.
     *
     * Dibaca dari MASTER, bukan snapshot header: tarif kapita memang tidak
     * ikut di-snapshot ke RSTXN_GAJIDOCTORHDRS. Sejalan dengan sifat lampiran
     * tahap ini yang seluruhnya live.
     *
     * @return array<int, string> daftar GROUP_DOC, mis. ['RI']
     */
    public static function grupKapita(string $drId): array
    {
        return self::grupKapitaMassal([$drId])[$drId] ?? [];
    }

    /**
     * Versi banyak dokter: DR_ID -> daftar GROUP_DOC yang digantikan kapita.
     *
     * @param array<int, string> $drIds
     * @return array<string, array<int, string>>
     */
    public static function grupKapitaMassal(array $drIds): array
    {
        $drIds = array_values(array_unique(array_filter($drIds, fn ($drIdItem) => trim((string) $drIdItem) !== '')));

        if ($drIds === []) {
            return [];
        }

        $petaKapita = [];

        foreach (collect($drIds)->chunk(self::BATAS_IN) as $bagianDokter) {
            $dokterList = DB::table('rsmst_doctors')
                ->select('dr_id', 'tarif_per_kapita_ri', 'tarif_per_kapita_rj')
                ->whereIn('dr_id', $bagianDokter->values()->all())
                ->get();

            foreach ($dokterList as $dokter) {
                $grup = [];
                foreach (GajiDokter::GRUP_KAPITA as $jalur => $grupJalur) {
                    if ((float) ($dokter->{"tarif_per_kapita_$jalur"} ?? 0) > 0) {
                        $grup = array_merge($grup, $grupJalur);
                    }
                }

                $petaKapita[(string) $dokter->dr_id] = $grup;
            }
        }

        return $petaKapita;
    }

    /**
     * Angka kaki lampiran: perbandingan antara daftar transaksi dan slip.
     *
     * Dikerjakan di sini, bukan di blok @php cetakannya, karena ini agregasi —
     * bukan pemetaan tampilan. Alasan yang sama membuat GajiDokter::denganLabel()
     * ada: template cukup membaca hasilnya, dan cetak satuan & massal memakai
     * perhitungan yang sama persis tanpa saling menyalin.
     *
     * Baris pada grup per kapita TIDAK ikut dijumlahkan. Dokter per kepala
     * tidak dibayar sebesar nominal komponennya — yang dipakai slip hanya
     * jumlah pasiennya dikali tarif kepala — sehingga menjumlahkannya bersama
     * komponen lain menghasilkan total yang tidak pernah cocok dengan slip.
     *
     * @param  Collection|array $lampiran   keluaran baris()
     * @param  Collection|array $detail     baris RSTXN_GAJIDOCTORDTLS milik slip
     * @param  array<int, string> $grupKapita
     * @return array{totalTransaksi:float,totalDasarKapita:float,jasaSlip:float,kapitaSlip:float,selisih:float}
     */
    public static function rekonsiliasi($lampiran, $detail, array $grupKapita): array
    {
        $barisLampiran = collect($lampiran);
        $barisSlip = collect($detail);

        $totalTransaksi = (float) $barisLampiran
            ->reject(fn ($baris) => in_array($baris['group_doc'], $grupKapita, true))
            ->sum('nominal');

        $totalDasarKapita = (float) $barisLampiran
            ->filter(fn ($baris) => in_array($baris['group_doc'], $grupKapita, true))
            ->sum('nominal');

        $jasaSlip = (float) $barisSlip
            ->where('jenis', 'J')
            ->reject(fn ($baris) => str_starts_with($baris->kode, 'TUNJ '))
            ->sum('nilai');

        $kapitaSlip = (float) $barisSlip
            ->where('jenis', 'J')
            ->filter(fn ($baris) => str_starts_with($baris->kode, 'KAPITA '))
            ->sum('nilai');

        return [
            'totalTransaksi' => $totalTransaksi,
            'totalDasarKapita' => $totalDasarKapita,
            'jasaSlip' => $jasaSlip,
            'kapitaSlip' => $kapitaSlip,
            'selisih' => round($jasaSlip - $totalTransaksi - $kapitaSlip, 2),
        ];
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

            'JD RJ' => self::bentuk(
                DB::table('rstxn_rjaccdocs as x')
                    ->join('rstxn_rjhdrs as h', 'h.rj_no', '=', 'x.rj_no'),
                'x.rjhn_dtl', 'h.rj_date', $nomorTransaksiList,
            ),

            'UP UGD' => self::bentuk(
                DB::table('rstxn_ugdhdrs as h'),
                'h.rj_no', 'h.rj_date', $nomorTransaksiList,
            ),

            'JD UGD' => self::bentuk(
                DB::table('rstxn_ugdaccdocs as x')
                    ->join('rstxn_ugdhdrs as h', 'h.rj_no', '=', 'x.rj_no'),
                'x.rjhn_dtl', 'h.rj_date', $nomorTransaksiList,
            ),

            // Pasien transfer: TXN_NO-nya RIHDR_NO, tapi jasanya lahir dari
            // kunjungan RJ/UGD SEBELUM transfer — tanggal layanannya di sana,
            // dijangkau lewat RSTXN_RITEMPADMINS. Nilai penanda ditulis literal,
            // bukan binding, supaya urutan binding tidak bergeser oleh JOIN.
            'UP RJTRF', 'JD RJTRF' => self::bentuk(
                DB::table('rstxn_ritempadmins as t')
                    ->join('rstxn_rihdrs as h', 'h.rihdr_no', '=', 't.rihdr_no')
                    ->join('rstxn_rjhdrs as a', 'a.rj_no', '=', 't.tempadm_ref')
                    ->whereRaw("t.tempadm_flag = 'RJ'"),
                't.rihdr_no', 'a.rj_date', $nomorTransaksiList,
            ),

            'UP UGDTRF', 'JD UGDTRF' => self::bentuk(
                DB::table('rstxn_ritempadmins as t')
                    ->join('rstxn_rihdrs as h', 'h.rihdr_no', '=', 't.rihdr_no')
                    ->join('rstxn_ugdhdrs as a', 'a.rj_no', '=', 't.tempadm_ref')
                    ->whereRaw("t.tempadm_flag = 'UGD'"),
                't.rihdr_no', 'a.rj_date', $nomorTransaksiList,
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

            'JD RI' => self::bentuk(
                DB::table('rstxn_riactdocs as x')
                    ->join('rstxn_rihdrs as h', 'h.rihdr_no', '=', 'x.rihdr_no'),
                'x.actd_no', 'x.actd_date', $nomorTransaksiList,
            ),

            'OPERATOR', 'ANASTESI' => self::bentuk(
                DB::table('rstxn_oks as x')
                    ->join('rstxn_rihdrs as h', 'h.rihdr_no', '=', 'x.rihdr_no'),
                'x.ok_reg', 'x.ok_date', $nomorTransaksiList,
            ),

            'OPERATOR RJ', 'ANASTESI RJ' => self::bentuk(
                DB::table('rstxn_oks as x')
                    ->join('rstxn_rjhdrs as h', 'h.rj_no', '=', 'x.ref_no')
                    ->whereRaw("x.status_rjri = 'RJ'"),
                'x.ok_reg', 'x.ok_date', $nomorTransaksiList,
            ),

            'OPERATOR UGD', 'ANASTESI UGD' => self::bentuk(
                DB::table('rstxn_oks as x')
                    ->join('rstxn_ugdhdrs as h', 'h.rj_no', '=', 'x.ref_no')
                    ->whereRaw("x.status_rjri = 'UGD'"),
                'x.ok_reg', 'x.ok_date', $nomorTransaksiList,
            ),

            // Klinik: kedua komponennya ber-TXN_NO nomor header yang sama.
            'UP KLINIK', 'JD KLINIK' => self::bentuk(
                DB::table('rstxn_rjhdrks as h'),
                'h.rj_no', 'h.rj_date', $nomorTransaksiList,
                adaSep: false,
            ),

            // Radiologi: satu baris = satu pemeriksaan, jadi nama pemeriksaannya
            // (RSMST_RADIOLOGIS.rad_desc) bisa ikut ditarik dan ditampilkan di
            // bawah nama pasien. Komponen lain tidak punya padanan sedetail ini.
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

        // Keterangan layanan hanya ada pada komponen tertentu (radiologi punya
        // nama pemeriksaan; visite/uang periksa tidak punya padanannya). NULL
        // di-cast supaya Oracle tahu tipenya saat semua cabang digabung di PHP.
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
}
