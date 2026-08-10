<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Registry POS TARIF Kamar Operasi — SUMBER TUNGGAL.
 *
 * Port dari Oracle Forms `rit006x.fmb` (blok TRANSAKSI_OK). Di form legacy, daftar
 * pos tarif dan nilai bakunya tersebar di dua trigger PL/SQL yang berbeda (tombol
 * "Hitung Tarif OK" dan "Trf Biaya-INAP"), sehingga urutan/keterangan pos gampang
 * melenceng antar keduanya. Di sini keduanya membaca satu konstanta yang sama.
 *
 * PENTING: urutan POS = urutan INSERT ke rstxn_rioks di form legacy, dan
 * keterangannya HARUS persis sama — laporan lama (kwitansi, pendapatan, piutang)
 * mengelompokkan biaya berdasarkan string `ok_desc` ini, bukan berdasarkan kode.
 */
final class KamarOperasiTarif
{
    /**
     * Kolom fee di `rstxn_oks` => keterangan baris biaya (`rstxn_rioks.ok_desc`).
     *
     * @var array<string,string>
     */
    public const POS = [
        'oprdoc_fee'        => 'JASA DOKTER OPERATOR',
        'anesdoc_fee'       => 'JASA DOKTER ANESTESI',
        'changeanesdoc_fee' => 'JASA PENGGANTI ANESTESI',
        'instrument_fee'    => 'BIAYA INSTRUMENT',
        'asistopr_fee'      => 'BIAYA ASISTEN OPERATOR',
        'asistanes_fee'     => 'BIAYA ASISTEN ANESTESI',
        'omlop_fee'         => 'BIAYA OM LOP',
        'ok_fee'            => 'SEWA OK',
        'rr_fee'            => 'JASA PERAWAT',
        'equipment_fee'     => 'BAHAN DAN ALAT PASIEN',
        'rentequipment_fee' => 'SEWA ALAT',
    ];

    /**
     * Label ringkas untuk tampilan form & teks audit log — BUKAN untuk disimpan
     * ke `rstxn_*oks.ok_desc` (itu tugas POS, dan POS tidak boleh diubah).
     * Karena itu label di sini bebas dirapikan tanpa memengaruhi data.
     *
     * Ditulis LENGKAP tanpa singkatan — sebelumnya ada yang menempel
     * ("JAsisten Operator", "JPerawat / RR") sehingga terbaca seperti salah
     * ketik, dan "JD" tidak semua orang tahu artinya Jasa Dokter.
     */
    public const LABEL = [
        'oprdoc_fee'        => 'Jasa Dokter Operator',
        'anesdoc_fee'       => 'Jasa Dokter Anestesi',
        'changeanesdoc_fee' => 'Jasa Pengganti Anestesi',
        'instrument_fee'    => 'Biaya Instrument',
        'asistopr_fee'      => 'Jasa Asisten Operator',
        'asistanes_fee'     => 'Jasa Asisten Anestesi',
        'omlop_fee'         => 'Biaya OM LOP',
        'ok_fee'            => 'Sewa OK',
        'rr_fee'            => 'Jasa Perawat / RR',
        'equipment_fee'     => 'Bahan & Alat',
        'rentequipment_fee' => 'Sewa Alat',
    ];

    /**
     * Pos yang nilainya DIHITUNG dari tabel detail, bukan diketik petugas.
     * `oprdoc_fee` = SUM(rstxn_okacts), `equipment_fee` = SUM(qty x harga rstxn_okobats).
     */
    public const POS_TURUNAN_DETAIL = ['oprdoc_fee', 'equipment_fee'];

    /**
     * Pos yang JUGA masuk pendapatan jasa dokter.
     *
     * Diverifikasi dari view `RSVIEW_NEWDOCSALARIES`: baris DESC_DOC 'OPERATOR'
     * memakai SUM(OPRDOC_FEE) atas `dr_id`, dan 'ANASTESI' memakai SUM(ANESDOC_FEE)
     * atas `dr_id_ok` (keduanya hanya untuk kunjungan `ri_status='P'`).
     *
     * Konsekuensi: mengubah dua kolom ini menggeser tagihan pasien DAN penghasilan
     * dokter sekaligus. Semua pos lain hanya masuk tagihan pasien.
     *
     * @var array<string,string> kolom fee => kolom dokter penerimanya
     */
    public const POS_GAJI_DOKTER = [
        'oprdoc_fee'  => 'dr_id',
        'anesdoc_fee' => 'dr_id_ok',
    ];

    /**
     * Pemetaan CREW => pos jasa miliknya.
     *
     * Tiap baris tarif operasi sebenarnya jasa untuk orang tertentu, jadi nama dan
     * angkanya ditampilkan berpasangan (Operator ↔ Jasa Dokter Operator, dst.) — bukan
     * dua daftar terpisah yang harus dicocokkan sendiri oleh petugas.
     *
     * `oncall` null berarti posisi itu memang tidak punya kolom jasa on call di
     * `rstxn_oks` (hanya asisten operator, asisten anestesi, dan instrument yang punya).
     *
     * @var array<string,array{label:string,fee:string,oncall:?string,jenis:string}>
     */
    public const CREW = [
        'dr_id' => ['label' => 'Operator', 'fee' => 'oprdoc_fee', 'oncall' => null, 'jenis' => 'dokter'],
        'dr_id_ok' => ['label' => 'Anestesi', 'fee' => 'anesdoc_fee', 'oncall' => null, 'jenis' => 'dokter'],
        // 'emp_id_changeanesdoc' => ['label' => 'Pengganti Anestesi', 'fee' => 'changeanesdoc_fee', 'oncall' => null, 'jenis' => 'karyawan'],
        'emp_id_asistopr' => ['label' => 'Asisten Operator', 'fee' => 'asistopr_fee', 'oncall' => 'oncallasistopr_fee', 'jenis' => 'karyawan'],
        'emp_id_asistanes' => ['label' => 'Asisten Anestesi', 'fee' => 'asistanes_fee', 'oncall' => 'oncallasistanes_fee', 'jenis' => 'karyawan'],
        'emp_id_instrument' => ['label' => 'Instrument', 'fee' => 'instrument_fee', 'oncall' => 'oncallinstrument_fee', 'jenis' => 'karyawan'],
    ];

    /**
     * Pos tarif yang TIDAK melekat pada satu orang — fasilitas, bahan, dan jasa
     * kelompok. Sisa dari POS setelah pos milik CREW dikeluarkan.
     *
     * @return list<string>
     */
    public static function posTanpaCrew(): array
    {
        $milikCrew = array_column(self::CREW, 'fee');

        return array_values(array_diff(array_keys(self::POS), $milikCrew));
    }

    /**
     * Jasa ON CALL — TIDAK ikut ditransfer ke tagihan pasien.
     *
     * Kolom ini ada di `rstxn_oks` tapi sengaja tidak masuk POS: form legacy tidak
     * pernah menuliskannya ke `rstxn_rioks`. Jadi ini komponen jasa petugas saja,
     * bukan biaya yang ditanggung pasien.
     *
     * @var array<string,string> kolom => label
     */
    public const POS_ONCALL = [
        'oncallasistopr_fee'   => 'On Call Asisten Operator',
        'oncallasistanes_fee'  => 'On Call Asisten Anestesi',
        'oncallinstrument_fee' => 'On Call Instrument',
    ];

    /** Persentase terhadap jasa dokter operator (form legacy: 50/10/10/10). */
    public const PERSEN_DARI_OPERATOR = [
        'anesdoc_fee'    => 50,
        'asistopr_fee'   => 10,
        'asistanes_fee'  => 10,
        'instrument_fee' => 10,
    ];

    /** Tarif baku flat — dipakai HANYA saat kolomnya masih NULL (belum pernah diisi). */
    public const TARIF_BAKU = [
        'omlop_fee'         => 50_000,
        'ok_fee'            => 400_000,
        'rr_fee'            => 100_000,
        'rentequipment_fee' => 350_000,
        'changeanesdoc_fee' => 0,
    ];

    /**
     * Pos persentase — SELALU dihitung ulang dari jasa dokter operator terkini.
     *
     * Persentase di sini fungsinya membantu petugas supaya tidak menghitung manual,
     * bukan mengunci nilai. Karena itu tombol "Hitung Tarif OK" menyegarkannya setiap
     * kali ditekan: begitu tindakan bertambah/berkurang, `oprdoc_fee` berubah dan
     * turunannya harus ikut, bukan tertinggal di angka lama.
     *
     * Petugas tetap bebas menimpa hasilnya lewat edit manual sesudahnya — total
     * transaksi selalu dijumlah dari nilai yang benar-benar tersimpan (lihat total()),
     * bukan dari persentase.
     *
     * @return array<string,int> pos => nilai
     */
    public static function hitungPersentase(int $oprdocFee): array
    {
        $hasil = [];
        foreach (self::PERSEN_DARI_OPERATOR as $kolom => $persen) {
            $hasil[$kolom] = (int) round($oprdocFee * $persen / 100);
        }

        return $hasil;
    }

    /**
     * Tarif baku flat untuk pos yang BELUM PERNAH diisi.
     *
     * Beda perlakuan dengan pos persentase: nilai ini tidak turunan dari apa pun,
     * jadi menekan tombol hitung ulang tidak boleh mengembalikannya ke baku —
     * itu akan menghapus penyesuaian petugas (mis. OM LOP 50rb diubah jadi 75rb)
     * tanpa ada yang perlu dihitung ulang.
     *
     * @param  array<string,mixed>  $existing  nilai kolom fee saat ini (null = belum diisi)
     * @return array<string,int>
     */
    public static function bakuPosKosong(array $existing): array
    {
        $hasil = [];
        foreach (self::TARIF_BAKU as $kolom => $nilai) {
            if (($existing[$kolom] ?? null) === null) {
                $hasil[$kolom] = $nilai;
            }
        }

        return $hasil;
    }

    /** Total 11 pos dari satu baris rstxn_oks. */
    public static function total(array $row): int
    {
        $total = 0;
        foreach (array_keys(self::POS) as $kolom) {
            $total += (int) ($row[$kolom] ?? 0);
        }

        return $total;
    }

    /**
     * Hitung ulang seluruh pos turunan lalu simpan ke rstxn_oks.
     *
     * SATU-satunya tempat rumus ini ditulis. Ada dua pintu masuk yang memicunya
     * (modul Kamar Operasi dan order dari EMR Rawat Inap) plus tiga pemicu tak
     * langsung (tambah/hapus tindakan, tambah/hapus bahan-alat); kalau rumusnya
     * disalin ke masing-masing, angka pasien akan berbeda tergantung lewat pintu
     * mana petugas masuk.
     *
     * WAJIB dipanggil DI DALAM DB::transaction dengan baris `rstxn_oks` sudah
     * dikunci (`lockForUpdate`) — $row adalah hasil kunci itu.
     *
     * @return array{0:array<string,int>,1:int,2:list<string>} [update, totalBaru, ringkasanPerubahan]
     */
    public static function hitungUlang(string $okReg, object $row): array
    {
        $oprdocFee = (int) DB::table('rstxn_okacts')->where('ok_reg', $okReg)->sum('okact_price');

        $equipmentFee = (int) DB::table('rstxn_okobats')->where('ok_reg', $okReg)->selectRaw('NVL(SUM(NVL(okobat_qty,0) * NVL(okobat_price,0)),0) as total')->value('total');

        $existing = [];
        foreach (array_keys(self::POS) as $kolom) {
            $existing[$kolom] = $row->{$kolom} ?? null;
        }

        // Turunan detail + pos persentase disegarkan; tarif baku flat hanya
        // mengisi yang masih kosong supaya penyesuaian petugas tidak terhapus.
        $update = ['oprdoc_fee' => $oprdocFee, 'equipment_fee' => $equipmentFee] + self::hitungPersentase($oprdocFee) + self::bakuPosKosong($existing);

        DB::table('rstxn_oks')->where('ok_reg', $okReg)->update($update);

        // Total = dijumlah dari nilai yang BENAR-BENAR disimpan, termasuk pos yang
        // sebelumnya diedit manual di luar persentase.
        $totalBaru = self::total(array_merge($existing, $update));

        $berubah = [];
        foreach ($update as $kolom => $nilaiBaru) {
            $nilaiLama = $existing[$kolom] === null ? null : (int) $existing[$kolom];
            if ($nilaiLama !== (int) $nilaiBaru) {
                $berubah[] = (self::LABEL[$kolom] ?? $kolom) . ' ' . ($nilaiLama === null ? '(belum diisi)' : number_format($nilaiLama)) . '→' . number_format($nilaiBaru);
            }
        }

        return [$update, $totalBaru, $berubah];
    }
}
