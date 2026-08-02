<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;

/**
 * Mesin hitung slip gaji dokter — RSTXN_GAJIDOCTORHDRS / RSTXN_GAJIDOCTORDTLS.
 *
 * Rumus & alasan tiap parameter ada di database/sql/2026_08_04_install_gaji_dokter.sql.
 * Ringkasnya, dengan potongan disimpan NEGATIF sehingga semuanya dijumlah:
 *
 *   jasa_total    = SUM(detail 'J' berkode komponen jasa)
 *   tunjangan     = SUM(detail 'J' berkode 'TUNJ %')      <- kena pajak
 *   total_gaji    = jasa + tunjangan + gaji pokok             (skema 'A')
 *                 = GREATEST(jasa, gaji pokok) + tunjangan    (skema 'G')
 *                 = jasa + tunjangan                          (skema 'N')
 *   potongan_rs   = -(persen% x basis)
 *   pph21         = -FLOOR(pph% x (total_gaji + potongan_rs) x faktorNpwp)
 *                   faktorNpwp = 1,2 bila dokter TIDAK ber-NPWP (UU PPh Pasal 21
 *                   ayat 5a: tarif 20% lebih tinggi); selain itu 1.
 *   gaji_diterima = total_gaji + potongan_rs + pph21
 *                   + potongan_lain_total + tambahan_total
 *
 * Parameter dokter DI-SNAPSHOT ke header saat generate, tidak di-join saat cetak —
 * revisi gaji pokok tahun depan tidak boleh mengubah slip periode lama.
 */
class GajiDokter
{
    public const STATUS_DRAFT = 'D';
    public const STATUS_FINAL = 'F';

    /**
     * Grup komponen di RSVIEW_NEWDOCSALARIES yang digantikan bila dokter memakai
     * model per kapita. Dokter per-kepala TIDAK dibayar per komponen untuk jalur
     * tersebut, jadi barisnya dibuang dan diganti satu baris 'KAPITA %'.
     * Jalur lain (OK, radiologi, dst) tetap dihitung normal.
     *
     * Publik karena GajiDokterLampiran perlu tahu grup mana yang digantikan,
     * supaya barisnya bisa ditandai "dasar hitung" alih-alih dibaca sebagai
     * uang yang diterima. Satu-satunya sumber daftar ini tetap di sini.
     */
    public const GRUP_KAPITA = [
        'ri' => ['RI'],
        'rj' => ['RJ'],
    ];

    /**
     * Komponen jasa yang bisa dipakai pada aturan potongan berjenjang —
     * DESC_DOC dari RSVIEW_NEWDOCSALARIES, urut sesuai GROUP_SEQ-nya.
     *
     * Ditulis sebagai konstanta, bukan di-query tiap membuka form: daftarnya
     * praktis tidak berubah (mengikuti struktur tarif, bukan data transaksi),
     * sementara SELECT DISTINCT atas view itu menyapu seluruh riwayat sejak
     * 2010. Kalau kelak ada komponen baru, tambahkan di sini.
     */
    public const KOMPONEN_JASA = [
        'UP RJ', 'JD RJ',
        'UP RJTRF', 'JD RJTRF',
        'UP UGD', 'JD UGD',
        'UP UGDTRF', 'JD UGDTRF',
        'VISIT', 'KONSUL', 'JD RI',
        'OPERATOR', 'ANASTESI',
        'UP KLINIK', 'JD KLINIK',
        'RAD RJ', 'RAD UGD', 'RAD RI',
    ];

    /**
     * Kode yang lazim dipakai saat menambah baris manual, per jenis detail.
     * Dipakai form rincian slip sebagai pilihan — mengetik bebas membuat kode
     * kembar yang beda ejaan ('KASBON' vs 'Kas bon') dan tidak pernah cocok
     * saat direkap. 'LAIN' disediakan untuk yang di luar daftar; keterangannya
     * yang menjelaskan.
     */
    public const KODE_POTONGAN = [
        'KASBON', 'IDI', 'ARISAN', 'KOPERASI', 'ANGSURAN', 'BPJS', 'ZARIYAH', 'LAIN',
    ];

    public const KODE_TAMBAHAN = ['BONUS', 'RAPEL', 'KOREKSI', 'LAIN'];

    /** Potongan rutin di master -> kode detail jenis 'P'. */
    protected const POTONGAN_RUTIN = [
        'potongan_idi' => 'IDI',
        'potongan_arisan' => 'ARISAN',
        'potongan_koperasi' => 'KOPERASI',
        'potongan_angsuran' => 'ANGSURAN',
        'potongan_bpjs' => 'BPJS',
        'potongan_zariyah' => 'ZARIYAH',
    ];

    /** Tunjangan rutin di master -> kode detail jenis 'J' (KENA PAJAK). */
    protected const TUNJANGAN_RUTIN = [
        'tunjangan_struktural' => 'TUNJ STRUKTURAL',
        'tunjangan_fungsional' => 'TUNJ FUNGSIONAL',
        'tunjangan_hadir' => 'TUNJ HADIR',
    ];

    /**
     * Nama panjang tiap kode komponen, untuk layar & cetak.
     *
     * Kodenya sendiri warisan RSVIEW_NEWDOCSALARIES (DESC_DOC) dan sengaja
     * TIDAK diubah — dipakai sebagai kunci di uq_gajidoctordtls, di aturan
     * potongan berjenjang, dan di sisi Oracle Dev 6i. Yang diganti hanya
     * teks yang dibaca orang: "UP RJ" tidak berarti apa-apa bagi dokter yang
     * menerima slipnya, "Uang Periksa Rawat Jalan" berarti.
     *
     * TRF = transfer, yaitu pasien yang berpindah unit; tarifnya terpisah dari
     * kunjungan biasa sehingga komponennya pun berdiri sendiri.
     */
    public const LABEL_KOMPONEN = [
        // ---- jasa: rawat jalan ----
        'UP RJ' => 'Uang Periksa Rawat Jalan',
        'JD RJ' => 'Jasa Dokter Rawat Jalan',
        'UP RJTRF' => 'Uang Periksa Rawat Jalan Transfer',
        'JD RJTRF' => 'Jasa Dokter Rawat Jalan Transfer',

        // ---- jasa: gawat darurat ----
        'UP UGD' => 'Uang Periksa UGD',
        'JD UGD' => 'Jasa Dokter UGD',
        'UP UGDTRF' => 'Uang Periksa UGD Transfer',
        'JD UGDTRF' => 'Jasa Dokter UGD Transfer',

        // ---- jasa: rawat inap ----
        'VISIT' => 'Visite Rawat Inap',
        'KONSUL' => 'Konsultasi Rawat Inap',
        'JD RI' => 'Jasa Dokter Rawat Inap',

        // ---- jasa: kamar operasi ----
        'OPERATOR' => 'Operator Bedah',
        'ANASTESI' => 'Anestesi',
        'OPERATOR RJ' => 'Operator Bedah Rawat Jalan',
        'ANASTESI RJ' => 'Anestesi Rawat Jalan',
        'OPERATOR UGD' => 'Operator Bedah UGD',
        'ANASTESI UGD' => 'Anestesi UGD',

        // ---- jasa: klinik & radiologi ----
        'UP KLINIK' => 'Uang Periksa Klinik',
        'JD KLINIK' => 'Jasa Dokter Klinik',
        'RAD RJ' => 'Bacaan Radiologi Rawat Jalan',
        'RAD UGD' => 'Bacaan Radiologi UGD',
        'RAD RI' => 'Bacaan Radiologi Rawat Inap',

        // ---- jasa: model per kapita ----
        // Baris ini keterangannya dibuat khusus saat generate (memuat tarifnya),
        // jadi label di sini hanya jaring pengaman.
        'KAPITA RI' => 'Per Pasien Rawat Inap',
        'KAPITA RJ' => 'Per Kunjungan Rawat Jalan',

        // ---- tunjangan rutin (tetap jenis 'J', kena pajak) ----
        'TUNJ STRUKTURAL' => 'Tunjangan Struktural',
        'TUNJ FUNGSIONAL' => 'Tunjangan Fungsional',
        'TUNJ HADIR' => 'Tunjangan Kehadiran',

        // ---- potongan ----
        'RS' => 'Potongan Rumah Sakit',
        'PPH21' => 'PPh 21',
        'KASBON' => 'Kasbon / Telah Diambil',
        'IDI' => 'Iuran IDI',
        'ARISAN' => 'Arisan',
        'KOPERASI' => 'Koperasi',
        'ANGSURAN' => 'Angsuran',
        'BPJS' => 'BPJS Kesehatan',
        'ZARIYAH' => 'Zariyah',

        // ---- tambahan ----
        'BONUS' => 'Bonus',
        'RAPEL' => 'Rapel',
        'KOREKSI' => 'Koreksi Bulan Lalu',

        'LAIN' => 'Lain-lain',
    ];

    /**
     * Teks yang ditampilkan untuk satu baris detail.
     *
     * Keterangan tersimpan yang BERBEDA dari kodenya berarti sudah ada teks
     * pilihan manusia di sana — entah label hasil generate, keterangan per
     * kapita yang memuat tarif, atau ketikan bagian keuangan pada baris manual.
     * Itu dipakai apa adanya; snapshot tidak boleh ditimpa.
     *
     * Pemetaan baru dipakai kalau keterangannya masih sama persis dengan kode.
     * Itu bukan pilihan kata siapa pun, melainkan slip lama yang dibuat sebelum
     * peta ini ada — jadi aman diperbarui tanpa melanggar maksud snapshot.
     */
    public static function labelKomponen(string $kode, ?string $keterangan = null): string
    {
        $tersimpan = trim((string) $keterangan);

        if ($tersimpan !== '' && $tersimpan !== trim($kode)) {
            return $tersimpan;
        }

        return self::LABEL_KOMPONEN[$kode] ?? $kode;
    }

    /**
     * Tempelkan properti `label` ke tiap baris detail.
     *
     * Dipanggil dari blok kelas komponen, bukan dari template. Alasannya
     * teknis: `use` di blok <?php Volt TIDAK menjangkau ekspresi {{ }} —
     * keduanya dikompilasi terpisah — sehingga memanggil labelKomponen()
     * langsung dari template memaksa FQCN, yang dilarang skill
     * naming-conventions. Menyiapkan datanya di sini membuat template cukup
     * menulis {{ $baris->label }}.
     */
    public static function denganLabel($detail)
    {
        return $detail->map(function ($baris) {
            $baris->label = self::labelKomponen($baris->kode, $baris->keterangan ?? null);

            return $baris;
        });
    }

    /** Pilihan kode untuk form tambah baris: [kode => label]. */
    public static function pilihanKode(string $jenis): array
    {
        $kodeList = self::kodeUntukJenis($jenis);

        return array_combine($kodeList, array_map(
            fn (string $kode) => self::labelKomponen($kode),
            $kodeList
        ));
    }

    /** Pilihan komponen jasa untuk aturan berjenjang: [kode => label]. */
    public static function pilihanKomponenJasa(): array
    {
        return array_combine(self::KOMPONEN_JASA, array_map(
            fn (string $kode) => self::labelKomponen($kode),
            self::KOMPONEN_JASA
        ));
    }

    /**
     * 'Y' bila dokter ber-NPWP, 'N' bila tidak.
     *
     * Yang menentukan adalah kolom npwp_status, BUKAN terisi atau tidaknya
     * nomor npwp. Versi pertama membaca kekosongan nomor sebagai "tidak
     * ber-NPWP" dan itu keliru: kolom nomor baru dipasang 2026-08-02 dalam
     * keadaan kosong untuk 122 dari 122 dokter, sehingga semuanya akan kena
     * PPh 21 +20% hanya karena pendataan nomor belum jalan.
     *
     * Nomornya tetap disimpan, tapi murni sebagai dokumentasi dan boleh
     * menyusul. Status 'Y' dengan nomor kosong itu sah.
     *
     * NULL diperlakukan 'Y' — sama dengan DEFAULT kolomnya, supaya baris yang
     * entah bagaimana lolos dari default tidak diam-diam kena tambahan 20%.
     */
    public static function statusNpwp(object $dokter): string
    {
        return ($dokter->npwp_status ?? 'Y') === 'N' ? 'N' : 'Y';
    }

    /** Pilihan kode untuk form tambah baris, sesuai jenis detailnya. */
    public static function kodeUntukJenis(string $jenis): array
    {
        return match ($jenis) {
            'P' => self::KODE_POTONGAN,
            'T' => self::KODE_TAMBAHAN,
            default => array_merge(
                self::KOMPONEN_JASA,
                ['KAPITA RI', 'KAPITA RJ'],
                array_values(self::TUNJANGAN_RUTIN),
            ),
        };
    }

    /**
     * Periode gaji = periode jasa + 1 bulan. Gaji yang dibayarkan bulan ini
     * berasal dari jasa bulan lalu; CHECK ck_gajidoctorhdrs_periode di database
     * menolak baris yang melanggar aturan ini.
     */
    public static function periodeGaji(string $tahunJasa, string $bulanJasa): array
    {
        $bulan = (int) $bulanJasa;
        $tahun = (int) $tahunJasa;

        return $bulan === 12
            ? [(string) ($tahun + 1), '01']
            : [(string) $tahun, str_pad((string) ($bulan + 1), 2, '0', STR_PAD_LEFT)];
    }

    /**
     * Bangun/segarkan slip untuk satu periode.
     *
     * Slip berstatus FINAL dilewati — itu maksud statusnya. Slip DRAFT ditulis
     * ulang seluruhnya (detail dihapus lalu dibuat lagi) supaya hasilnya sama
     * dengan generate pertama, tidak menumpuk.
     *
     * @return array{dibuat:int,diperbarui:int,dilewati:array,tanpaSumberKapita:array,tanpaNpwp:array}
     */
    public static function generate(string $tahunJasa, string $bulanJasa, ?string $drId, string $user): array
    {
        $periode = $bulanJasa . '/' . $tahunJasa;
        [$tahunGaji, $bulanGaji] = self::periodeGaji($tahunJasa, $bulanJasa);

        $komponen = self::komponenJasa($periode, $drId);
        $dokterIds = $komponen->pluck('dr_id')->unique();

        // Dokter per kapita bisa TIDAK punya baris jasa sama sekali (mis. Patologi
        // Klinik) — tetap harus dapat slip, jadi ikut ditarik terpisah.
        $dokterIds = $dokterIds->merge(self::dokterPerKapita($drId))->unique()->values();

        $hasil = ['dibuat' => 0, 'diperbarui' => 0, 'dilewati' => [], 'tanpaSumberKapita' => [], 'tanpaNpwp' => []];

        foreach ($dokterIds as $drIdDokter) {
            $dokter = DB::table('rsmst_doctors')->where('dr_id', $drIdDokter)->first();
            if (!$dokter) {
                continue;
            }

            $existing = DB::table('rstxn_gajidoctorhdrs')
                ->where('dr_id', $drIdDokter)
                ->where('tahun_jasa', $tahunJasa)
                ->where('bulan_jasa', $bulanJasa)
                ->first();

            if ($existing && $existing->gaji_status === self::STATUS_FINAL) {
                $hasil['dilewati'][] = $dokter->dr_name;
                continue;
            }

            // Status 'N' menaikkan PPh 21 sebesar 20%, dan itu satu-satunya
            // parameter gaji yang MEMPERBESAR potongan tanpa ada angka yang
            // diketik di mana pun — tidak akan ketahuan dari membaca slip.
            // Karena itu namanya selalu dikumpulkan dan dilaporkan, supaya
            // tambahan itu terbaca sebagai keputusan, bukan kecelakaan setelan.
            // Slipnya tetap dibuat; memblokir generate akan menyandera seluruh
            // periode hanya karena satu dokter.
            if (self::statusNpwp($dokter) === 'N') {
                $hasil['tanpaNpwp'][] = $dokter->dr_name;
            }

            $detail = self::susunDetail($komponen->where('dr_id', $drIdDokter), $dokter, $hasil);

            DB::transaction(function () use ($existing, $dokter, $tahunJasa, $bulanJasa, $tahunGaji, $bulanGaji, $detail, $user, &$hasil) {
                $gajidoctorNo = $existing->gajidoctor_no ?? null;

                if ($gajidoctorNo === null) {
                    $gajidoctorNo = self::simpanHeaderBaru($dokter, $tahunJasa, $bulanJasa, $tahunGaji, $bulanGaji, $user);
                    $hasil['dibuat']++;
                } else {
                    DB::table('rstxn_gajidoctordtls')->where('gajidoctor_no', $gajidoctorNo)->delete();
                    DB::table('rstxn_gajidoctorhdrs')->where('gajidoctor_no', $gajidoctorNo)->update([
                        'skema_gaji_pokok' => $dokter->skema_gaji_pokok ?? 'A',
                        'nilai_gaji_pokok' => (float) ($dokter->basic_salary ?? 0),
                        'potongan_rs_basis' => $dokter->potongan_rs_basis ?? 'T',
                        'potongan_rs_persen' => (float) ($dokter->potongan_rs_persen ?? 0),
                        'potongan_rs_aturan' => $dokter->potongan_rs_aturan,
                        'pph21_persen' => (float) ($dokter->pph21_persen ?? 0),
                        'npwp_status' => self::statusNpwp($dokter),
                        'update_user' => $user,
                        'update_date' => now(),
                    ]);
                    $hasil['diperbarui']++;
                }

                foreach ($detail as $urutan => $baris) {
                    self::simpanDetail($gajidoctorNo, $baris, $baris['urutan'] ?? $urutan, $user);
                }

                self::hitungUlang($gajidoctorNo, $user);
            });
        }

        return $hasil;
    }

    /**
     * Hitung ulang ringkasan header dari detailnya. Dipanggil setelah generate dan
     * setelah bagian keuangan menyunting detail slip draft.
     */
    public static function hitungUlang(int $gajidoctorNo, ?string $user = null): void
    {
        $header = DB::table('rstxn_gajidoctorhdrs')->where('gajidoctor_no', $gajidoctorNo)->first();
        if (!$header) {
            return;
        }

        $detail = DB::table('rstxn_gajidoctordtls')->where('gajidoctor_no', $gajidoctorNo)->get();

        $jasa = 0.0;
        $tunjangan = 0.0;
        foreach ($detail->where('jenis', 'J') as $baris) {
            if (str_starts_with($baris->kode, 'TUNJ ')) {
                $tunjangan += (float) $baris->nilai;
            } else {
                $jasa += (float) $baris->nilai;
            }
        }

        $pokok = (float) $header->nilai_gaji_pokok;
        $total = match ($header->skema_gaji_pokok) {
            'G' => max($jasa, $pokok) + $tunjangan,
            'N' => $jasa + $tunjangan,
            default => $jasa + $tunjangan + $pokok,
        };

        // NILAI MANUAL — potongan RS & PPh 21 boleh ditimpa bagian keuangan.
        // Rumusnya tetap dipakai sebagai nilai awal, tapi begitu suatu baris
        // ditandai nilai_manual = 'Y', angkanya dipertahankan apa adanya dan
        // TIDAK dihitung ulang. Itu sebabnya penanda ini ada di tabel detail,
        // bukan tabel baru: yang ditimpa memang baris detailnya sendiri.
        $manual = $detail->where('jenis', 'P')
            ->whereIn('kode', ['RS', 'PPH21'])
            ->where('nilai_manual', 'Y')
            ->pluck('nilai', 'kode');

        $potonganRs = isset($manual['RS'])
            ? -abs((float) $manual['RS'])
            : self::potonganRs($header, $detail, $total, $pokok);

        // PPh menyusul potongan RS — kalau RS ditimpa manual, basis pajaknya
        // ikut memakai angka yang baru itu.
        // Dokter tanpa NPWP dikenai tarif 20% lebih tinggi (UU PPh Pasal 21 ayat 5a).
        $faktorNpwp = ($header->npwp_status ?? 'Y') === 'N' ? 1.2 : 1.0;

        $pph21 = isset($manual['PPH21'])
            ? -abs((float) $manual['PPH21'])
            : -floor((float) $header->pph21_persen / 100 * ($total + $potonganRs) * $faktorNpwp);

        // 'RS' & 'PPH21' juga ditulis sebagai baris detail supaya bisa dicetak apa
        // adanya, tapi TIDAK ikut potongan_lain_total — kalau ikut, keduanya
        // terhitung dua kali pada gaji_diterima.
        $potonganLain = (float) $detail->where('jenis', 'P')
            ->whereNotIn('kode', ['RS', 'PPH21'])
            ->sum('nilai');
        $tambahan = (float) $detail->where('jenis', 'T')->sum('nilai');

        DB::table('rstxn_gajidoctordtls')
            ->where('gajidoctor_no', $gajidoctorNo)
            ->whereIn('kode', ['RS', 'PPH21'])
            ->where('jenis', 'P')
            ->delete();

        $pelaku = $user ?? $header->entry_user ?? 'system';

        // Kedua baris SELALU ditulis, termasuk saat nilainya nol. Sebelumnya baris
        // bernilai nol dilewati, sehingga potongan RS lenyap dari rincian begitu
        // persennya 0 / aturan berjenjangnya kosong — terbaca seolah komponennya
        // hilang, padahal cuma sedang nol, dan petugas kehilangan tempat untuk
        // mengoreksinya. Nol di sini informasi, bukan ketiadaan.
        self::simpanDetail($gajidoctorNo, [
            'jenis' => 'P', 'kode' => 'RS',
            'keterangan' => self::keteranganPotonganRs($header, isset($manual['RS'])),
            'nilai' => $potonganRs, 'jumlah_pasien' => 0,
            'nilai_manual' => isset($manual['RS']) ? 'Y' : 'N',
        ], 10, $pelaku);

        self::simpanDetail($gajidoctorNo, [
            'jenis' => 'P', 'kode' => 'PPH21',
            'keterangan' => 'PPh 21'
                . ($faktorNpwp > 1 ? ' (+20% tanpa NPWP)' : '')
                . (isset($manual['PPH21']) ? ' (disesuaikan)' : ''),
            'nilai' => $pph21, 'jumlah_pasien' => 0,
            'nilai_manual' => isset($manual['PPH21']) ? 'Y' : 'N',
        ], 11, $pelaku);

        DB::table('rstxn_gajidoctorhdrs')->where('gajidoctor_no', $gajidoctorNo)->update([
            'jasa_total' => round($jasa, 2),
            'total_gaji' => round($total, 2),
            'potongan_rs' => round($potonganRs, 2),
            'pph21' => round($pph21, 2),
            'potongan_lain_total' => round($potonganLain, 2),
            'tambahan_total' => round($tambahan, 2),
            'gaji_diterima' => round($total + $potonganRs + $pph21 + $potonganLain + $tambahan, 2),
            'update_user' => $user ?? $header->update_user,
            'update_date' => now(),
        ]);
    }

    /* ===============================
     | INTERNAL
     =============================== */

    /** Komponen jasa per dokter untuk satu periode, sudah teragregasi. */
    protected static function komponenJasa(string $periode, ?string $drId)
    {
        // doc_date di view bertipe VARCHAR2 'dd/mm/yyyy' — wajib lewat TO_DATE,
        // bukan dibandingkan sebagai teks.
        return DB::table('rsview_newdocsalaries as v')
            ->select([
                'v.dr_id',
                'v.group_doc',
                'v.desc_doc',
                DB::raw('SUM(v.doc_nominal) AS nominal'),
                DB::raw('SUM(v.jml_pasien) AS pasien'),
                DB::raw('MIN(v.group_seq) AS seq'),
            ])
            ->whereRaw("to_char(to_date(v.doc_date, 'dd/mm/yyyy'), 'mm/yyyy') = ?", [$periode])
            ->when($drId, fn ($query) => $query->where('v.dr_id', $drId))
            ->groupBy('v.dr_id', 'v.group_doc', 'v.desc_doc')
            ->get();
    }

    /** Dokter bertarif per kepala — perlu slip walau tidak punya baris jasa. */
    protected static function dokterPerKapita(?string $drId)
    {
        return DB::table('rsmst_doctors')
            ->where(fn ($query) => $query->where('tarif_per_kapita_ri', '>', 0)->orWhere('tarif_per_kapita_rj', '>', 0))
            ->when($drId, fn ($query) => $query->where('dr_id', $drId))
            ->pluck('dr_id');
    }

    /**
     * Susun baris detail untuk satu dokter: jasa (dengan penggantian per kapita),
     * tunjangan rutin, lalu potongan rutin.
     */
    protected static function susunDetail($komponen, object $dokter, array &$hasil): array
    {
        $detail = [];
        $grupDibuang = [];

        foreach (self::GRUP_KAPITA as $jalur => $grup) {
            $tarif = (float) ($dokter->{"tarif_per_kapita_$jalur"} ?? 0);
            if ($tarif <= 0) {
                continue;
            }

            $grupDibuang = array_merge($grupDibuang, $grup);
            $pasien = (int) $komponen->whereIn('group_doc', $grup)->sum('pasien');

            if ($pasien === 0) {
                // Tidak ada sumber hitung di view — slip tetap dibuat supaya gaji
                // pokoknya jalan, tapi dilaporkan agar bisa diisi manual.
                $hasil['tanpaSumberKapita'][] = $dokter->dr_name;
            }

            $detail[] = [
                'urutan' => 100 + count($detail),
                'jenis' => 'J',
                'kode' => 'KAPITA ' . strtoupper($jalur),
                'keterangan' => 'Per pasien ' . strtoupper($jalur) . ' @ ' . number_format($tarif, 0, ',', '.'),
                'nilai' => $pasien * $tarif,
                'jumlah_pasien' => $pasien,
            ];
        }

        foreach ($komponen->sortBy('seq') as $baris) {
            if (in_array($baris->group_doc, $grupDibuang, true)) {
                continue;
            }
            if ((float) $baris->nominal == 0.0) {
                continue;
            }

            $detail[] = [
                'urutan' => 100 + count($detail),
                'jenis' => 'J',
                'kode' => $baris->desc_doc,
                'keterangan' => self::labelKomponen($baris->desc_doc),
                'nilai' => (float) $baris->nominal,
                'jumlah_pasien' => (int) $baris->pasien,
            ];
        }

        foreach (self::TUNJANGAN_RUTIN as $kolom => $kode) {
            $nilai = (float) ($dokter->{$kolom} ?? 0);
            if ($nilai > 0) {
                $detail[] = ['urutan' => 200 + count($detail), 'jenis' => 'J', 'kode' => $kode, 'keterangan' => self::labelKomponen($kode), 'nilai' => $nilai, 'jumlah_pasien' => 0];
            }
        }

        foreach (self::POTONGAN_RUTIN as $kolom => $kode) {
            $nilai = (float) ($dokter->{$kolom} ?? 0);
            if ($nilai > 0) {
                // disimpan NEGATIF — lihat aturan tanda di kepala kelas.
                // urutan 400+: potongan rutin ditaruh DI BAWAH baris RS & PPh 21
                // (urutan 10 & 11) karena keduanya tidak ikut membentuk dasar
                // pajak — memisahkannya secara visual mencegah salah baca.
                $detail[] = ['urutan' => 400 + count($detail), 'jenis' => 'P', 'kode' => $kode, 'keterangan' => self::labelKomponen($kode), 'nilai' => -$nilai, 'jumlah_pasien' => 0];
            }
        }

        return $detail;
    }

    /**
     * Baca aturan potongan berjenjang.
     *
     * FORMAT — satu field JSON, nilainya boleh PERSEN atau NOMINAL:
     *
     *   {"UP RJ": 10}          -> 10% dari komponen UP RJ
     *   {"UP RJ": "10%"}       -> sama, bentuk eksplisit
     *   {"UP RJ": "7.5%"}      -> pecahan persen boleh
     *   {"JD RJ": "Rp500000"}  -> potong Rp500.000 dari komponen JD RJ
     *
     * Angka telanjang SELALU dibaca sebagai persen — itu bentuk yang sudah
     * terlanjur dipakai data lama. Untuk nominal WAJIB memakai awalan "Rp",
     * supaya {"JD RJ": 500000} tidak diam-diam berarti 500.000 persen.
     * validasiAturan() di bawah yang menolak salah tulis semacam itu.
     *
     * @return array<string, array{tipe:'P'|'N', nilai:float}>
     */
    public static function parseAturan(?string $json): array
    {
        $data = json_decode(trim((string) $json) ?: '{}', true);
        if (!is_array($data)) {
            return [];
        }

        $hasil = [];
        foreach ($data as $kode => $nilai) {
            if (is_numeric($nilai)) {
                $hasil[$kode] = ['tipe' => 'P', 'nilai' => (float) $nilai];
                continue;
            }

            // Pemisah dibersihkan BERBEDA per tipe. Pada persen, titik adalah
            // pemisah DESIMAL ("7.5%") — kalau ikut dibuang, 7,5% jadi 75%.
            // Pada nominal, titik/koma justru pemisah ribuan ("Rp500.000").
            $teks = strtoupper(str_replace(' ', '', (string) $nilai));

            if (str_ends_with($teks, '%')) {
                $angka = str_replace(',', '.', rtrim($teks, '%'));
                $hasil[$kode] = ['tipe' => 'P', 'nilai' => (float) $angka];
            } elseif (str_starts_with($teks, 'RP')) {
                $angka = str_replace(['.', ','], '', substr($teks, 2));
                $hasil[$kode] = ['tipe' => 'N', 'nilai' => (float) $angka];
            }
        }

        return $hasil;
    }

    /**
     * Kebalikan parseAturan(): rangkai kembali jadi JSON baku untuk disimpan.
     *
     * Bentuk bakunya — persen jadi ANGKA, nominal jadi teks berawalan "Rp" —
     * supaya hasil simpan form selalu lolos parseAturan() sendiri, dan data
     * lama yang ditulis tangan tetap kebaca.
     *
     * @param array<string, array{tipe:string, nilai:float}> $items
     */
    public static function susunAturan(array $items): string
    {
        $aturanTersusun = [];
        foreach ($items as $kode => $aturanItem) {
            $aturanTersusun[$kode] = ($aturanItem['tipe'] ?? 'P') === 'N'
                ? 'Rp' . (int) $aturanItem['nilai']
                : round((float) $aturanItem['nilai'], 2) + 0;   // + 0 membuang ".0" pada bilangan bulat
        }

        // FORCE_OBJECT supaya aturan kosong tersimpan sebagai {} — tanpa itu PHP
        // menulisnya [] dan parseAturan berikutnya membaca larik, bukan objek.
        return json_encode($aturanTersusun, JSON_UNESCAPED_UNICODE | JSON_FORCE_OBJECT) ?: '{}';
    }

    /**
     * Periksa aturan berjenjang. Mengembalikan pesan kesalahan, atau null bila
     * sudah benar. Dipakai bersama oleh form master dan form rincian slip
     * supaya aturannya tidak berbeda di dua tempat.
     */
    public static function validasiAturan(?string $json): ?string
    {
        $teks = trim((string) $json);
        if ($teks === '') {
            return 'Aturan berjenjang wajib diisi, mis. {"UP RJ":10} atau {"JD RJ":"Rp500000"}.';
        }

        $data = json_decode($teks, true);
        if (!is_array($data) || $data === []) {
            return 'Format harus JSON objek, mis. {"UP RJ":10,"JD RJ":"Rp500000"}.';
        }

        foreach ($data as $kode => $nilai) {
            if (is_numeric($nilai)) {
                if ($nilai < 0 || $nilai > 100) {
                    return "Nilai \"{$kode}\" = {$nilai} dibaca sebagai persen dan di luar 0–100. "
                        . 'Untuk potongan bernominal tetap, tulis "Rp' . (int) $nilai . '".';
                }
                continue;
            }

            $teksNilai = strtoupper(str_replace(' ', '', (string) $nilai));

            if (str_ends_with($teksNilai, '%')) {
                $angka = str_replace(',', '.', rtrim($teksNilai, '%'));
                if (!is_numeric($angka) || $angka < 0 || $angka > 100) {
                    return "Persen \"{$kode}\" harus angka 0–100.";
                }
            } elseif (str_starts_with($teksNilai, 'RP')) {
                $angka = str_replace(['.', ','], '', substr($teksNilai, 2));
                if (!is_numeric($angka) || $angka < 0) {
                    return "Nominal \"{$kode}\" harus angka, mis. \"Rp500000\".";
                }
            } else {
                return "Nilai \"{$kode}\" tidak dikenali. Pakai angka/\"10%\" untuk persen, "
                    . 'atau "Rp500000" untuk nominal tetap.';
            }
        }

        return null;
    }

    /**
     * Label baris potongan RS. Basis 'B' sengaja TIDAK menyebut persen tunggal —
     * di basis berjenjang kolom persen memang 0 dan yang berlaku adalah aturan
     * JSON, sehingga tulisan "Potongan RS 0%" cuma menyesatkan.
     */
    protected static function keteranganPotonganRs(object $header, bool $manual): string
    {
        $label = match ($header->potongan_rs_basis) {
            'B' => 'Potongan RS (berjenjang)',
            'N' => 'Potongan RS',
            default => 'Potongan RS ' . rtrim(rtrim(number_format((float) $header->potongan_rs_persen, 2, ',', '.'), '0'), ',') . '%',
        };

        return $manual ? $label . ' (disesuaikan)' : $label;
    }

    /** Potongan RS sesuai basis T / J / N / B. Selalu dikembalikan NEGATIF. */
    protected static function potonganRs(object $header, $detail, float $total, float $pokok): float
    {
        $persen = (float) $header->potongan_rs_persen;

        switch ($header->potongan_rs_basis) {
            case 'N':
                return 0.0;

            case 'J':
                return -round($persen / 100 * ($total - $pokok), 2);

            case 'B':
                // Aturan berjenjang dibaca dari SNAPSHOT di header, bukan dari
                // master — mengubah aturan tahun depan tidak boleh menggeser slip
                // periode lama. Slip lama yang belum punya snapshot (dibuat sebelum
                // kolomnya ada) jatuh balik ke master supaya tidak mendadak nol.
                $json = $header->potongan_rs_aturan
                    ?? DB::table('rsmst_doctors')->where('dr_id', $header->dr_id)->value('potongan_rs_aturan');

                $jumlah = 0.0;
                foreach (self::parseAturan($json) as $kode => $aturanItem) {
                    $nilai = (float) $detail->where('jenis', 'J')->where('kode', $kode)->sum('nilai');

                    // Komponen yang tidak ada di slip ini dilewati — termasuk untuk
                    // aturan bernominal tetap. Memotong Rp500.000 atas jasa yang
                    // memang tidak dikerjakan bulan itu jelas keliru.
                    if ($nilai == 0.0) {
                        continue;
                    }

                    $jumlah += $aturanItem['tipe'] === 'N'
                        ? min($aturanItem['nilai'], abs($nilai))   // nominal tidak boleh melebihi komponennya
                        : $nilai * $aturanItem['nilai'] / 100;
                }

                return -round($jumlah, 2);

            default: // 'T'
                return -round($persen / 100 * $total, 2);
        }
    }

    protected static function simpanHeaderBaru(object $dokter, string $tahunJasa, string $bulanJasa, string $tahunGaji, string $bulanGaji, string $user): int
    {
        return self::insertDenganNomor('rstxn_gajidoctorhdrs', 'gajidoctor_no', fn ($nomorBaru) => [
            'gajidoctor_no' => $nomorBaru,
            'dr_id' => $dokter->dr_id,
            'tahun_jasa' => $tahunJasa,
            'bulan_jasa' => $bulanJasa,
            'tahun_gaji' => $tahunGaji,
            'bulan_gaji' => $bulanGaji,
            'skema_gaji_pokok' => $dokter->skema_gaji_pokok ?? 'A',
            'nilai_gaji_pokok' => (float) ($dokter->basic_salary ?? 0),
            'potongan_rs_basis' => $dokter->potongan_rs_basis ?? 'T',
            'potongan_rs_persen' => (float) ($dokter->potongan_rs_persen ?? 0),
            // Snapshot aturan berjenjang — sama alasannya dengan parameter lain:
            // revisi aturan di master tidak boleh menggeser slip periode lama.
            'potongan_rs_aturan' => $dokter->potongan_rs_aturan,
            'pph21_persen' => (float) ($dokter->pph21_persen ?? 0),
            // Status NPWP ikut di-snapshot: dokter yang baru mendaftarkan NPWP
            // bulan depan tidak boleh mengubah slip bulan-bulan sebelumnya.
            'npwp_status' => self::statusNpwp($dokter),
            'gaji_status' => self::STATUS_DRAFT,
            'entry_user' => $user,
            'entry_date' => now(),
        ]);
    }

    public static function simpanDetail(int $gajidoctorNo, array $baris, int $urutan, string $user): int
    {
        return self::insertDenganNomor('rstxn_gajidoctordtls', 'gajidoctor_dtl', fn ($nomorBaru) => [
            'gajidoctor_dtl' => $nomorBaru,
            'gajidoctor_no' => $gajidoctorNo,
            'jenis' => $baris['jenis'],
            'kode' => $baris['kode'],
            'keterangan' => $baris['keterangan'] ?? $baris['kode'],
            'nilai' => round((float) $baris['nilai'], 2),
            'nilai_manual' => $baris['nilai_manual'] ?? 'N',
            'jumlah_pasien' => (int) ($baris['jumlah_pasien'] ?? 0),
            'urutan' => $urutan,
            'entry_user' => $user,
            'entry_date' => now(),
        ]);
    }

    /**
     * MAX+1 dengan retry ORA-00001.
     *
     * Oracle menolak FOR UPDATE pada agregat (ORA-01786), jadi nomor tidak bisa
     * dikunci di muka. Yang dilakukan: ambil MAX+1, coba insert, dan kalau ada
     * proses lain yang keburu memakai nomor itu (unique violation) ulangi dari
     * MAX yang baru.
     */
    protected static function insertDenganNomor(string $tabel, string $kolom, callable $payload): int
    {
        for ($percobaan = 0; $percobaan < 10; $percobaan++) {
            $nomorBaru = ((int) DB::table($tabel)->max($kolom)) + 1;

            try {
                DB::table($tabel)->insert($payload($nomorBaru));

                return $nomorBaru;
            } catch (QueryException $galat) {
                if (!str_contains($galat->getMessage(), 'ORA-00001')) {
                    throw $galat;
                }
            }
        }

        throw new \RuntimeException("Gagal mendapatkan nomor baru untuk {$tabel} setelah 10 percobaan.");
    }
}
