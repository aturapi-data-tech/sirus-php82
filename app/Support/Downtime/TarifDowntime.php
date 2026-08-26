<?php

namespace App\Support\Downtime;

use Illuminate\Support\Facades\DB;

/**
 * TarifDowntime — sumber tunggal daftar tarif (price list) untuk keperluan waktu henti (down time).
 *
 * Saat SIMRS tidak dapat diakses, petugas mengisi formulir manual (lihat
 * App\Support\Downtime\FormulirDowntime) dan tetap harus menuliskan nominal biaya.
 * Halaman /downtime/daftar-tarif mencetak tarif dari master yang SAMA dengan yang
 * dipakai LOV Administrasi RJ/UGD/RI, jadi angka manual = angka yang nanti muncul
 * saat data dientri ulang.
 *
 * Peta master → LOV / layar yang memakainya:
 * - rsmst_rooms + rsmst_class ............ LOV Kamar (Daftar RI, pindah kamar)
 * - rsmst_docvisits + rsmst_doctors ...... auto-harga Visit & Konsul RI
 * - rsmst_actparamedics + rsmst_actpclasses  LOV Jasa Medis (RJ/UGD & RI per kelas)
 * - rsmst_accdocs + rsmst_actdclasses .... LOV Jasa Dokter (RJ/UGD & RI per kelas)
 * - rsmst_actemps ........................ LOV Jasa Karyawan (RJ/UGD)
 * - lbmst_clabitems + lbmst_clabs ........ Tambah Pemeriksaan Laboratorium
 * - rsmst_radiologis ..................... LOV Radiologi
 * - immst_products ....................... LOV Obat (e-resep & administrasi)
 * - rsmst_others ......................... LOV Lain-lain
 *
 * Tiap kategori mengembalikan baris SERAGAM (array asosiatif sesuai kolom()) supaya
 * satu markup tabel dipakai bersama oleh halaman layar dan blade cetak PDF.
 */
class TarifDowntime
{
    /** Batas baris per kategori saat mencetak — di atas ini dipotong & diberi catatan di PDF. */
    public const MAKS_CETAK = 4000;

    /** Judul gabungan untuk kelompok kolom bertingkat (lihat headerKolom()). */
    public const GRUP_KOLOM = [
        'poli' => 'Tarif Poli — Rawat Jalan & UGD',
        'inap' => 'Tarif Rawat Inap per Kelas Kamar',
    ];

    /** Kategori tarif → label tab, unit pengguna & keterangan sumber. Urutan = urutan tab & cetak. */
    public const KATEGORI = [
        'dokter-rj-ugd' => [
            'label' => 'Karcis & Jasa Dokter RJ/UGD',
            'unit' => 'Pendaftaran & administrasi RJ / UGD',
            'desc' => 'Administrasi RS, tarif poli rawat jalan, dan tarif dokter UGD — melekat pada tiap dokter, terisi otomatis saat pendaftaran RJ/UGD. Dipakai mengisi RJ-ADM-02 & UGD-ADM-02. Hanya dokter berstatus aktif.',
            'sumber' => 'rsmst_doctors + rsmst_polis',
        ],
        'kamar' => [
            'label' => 'Kamar & Akomodasi',
            'unit' => 'Pendaftaran & administrasi rawat inap',
            'desc' => 'Tarif per hari tiap kamar: sewa kamar, jasa perawatan, dan pelayanan umum. Dipakai mengisi RI-ADM-01 (pendaftaran) & RI-ADM-02 (rincian biaya).',
            'sumber' => 'rsmst_rooms + rsmst_class',
        ],
        'visite' => [
            'label' => 'Visite & Konsul Dokter',
            'unit' => 'Perawat ruangan & administrasi rawat inap',
            'desc' => 'Tarif visite dan konsul per dokter per kelas kamar, umum maupun BPJS. Dipakai mengisi RI-ADM-02. Hanya dokter aktif dan baris yang tarifnya sudah diisi.',
            'sumber' => 'rsmst_docvisits + rsmst_doctors',
        ],
        'jasa-medis' => [
            'label' => 'Jasa Medis / Tindakan',
            'unit' => 'Administrasi RJ / UGD / RI',
            'desc' => 'Tarif tindakan & jasa paramedis. Kolom "Tarif Poli" dipakai pasien rawat jalan & UGD; kolom per kelas dipakai pasien rawat inap sesuai kelas kamarnya. Angka kelas yang tercetak abu-abu berarti kelas itu belum punya tarif sendiri sehingga mengikuti tarif poli. Hanya tindakan berstatus aktif.',
            'sumber' => 'rsmst_actparamedics + rsmst_actpclasses',
        ],
        'jasa-dokter' => [
            'label' => 'Jasa Dokter',
            'unit' => 'Administrasi RJ / UGD / RI',
            'desc' => 'Tarif jasa dokter di luar visite & konsul. Sama seperti jasa medis: kolom "Tarif Poli" untuk rawat jalan & UGD, kolom per kelas untuk rawat inap. Angka kelas abu-abu = mengikuti tarif poli karena kelas itu belum ditarifkan sendiri. Hanya jasa berstatus aktif.',
            'sumber' => 'rsmst_accdocs + rsmst_actdclasses',
        ],
        'jasa-karyawan' => [
            'label' => 'Jasa Karyawan',
            'unit' => 'Administrasi RJ / UGD',
            'desc' => 'Tarif jasa karyawan / non-medis yang ditagihkan ke pasien. Hanya yang berstatus aktif.',
            'sumber' => 'rsmst_actemps',
        ],
        'laborat' => [
            'label' => 'Laboratorium',
            'unit' => 'Laboratorium & administrasi',
            'desc' => 'Tarif per pemeriksaan laboratorium yang bisa diorder. Item turunan (parameter di dalam paket) tidak ditampilkan karena tidak ditarifkan sendiri. lbmst_clabitems tidak punya kolom active_status; yang disaring hanya hidden_status, sama seperti layar Tambah Pemeriksaan.',
            'sumber' => 'lbmst_clabitems + lbmst_clabs',
        ],
        'radiologi' => [
            'label' => 'Radiologi',
            'unit' => 'Radiologi & administrasi',
            'desc' => 'Tarif per pemeriksaan radiologi yang berstatus aktif.',
            'sumber' => 'rsmst_radiologis',
        ],
        'obat' => [
            'label' => 'Obat & Alkes',
            'unit' => 'Apotek, UGD & ruangan',
            'desc' => 'Harga jual obat & alat kesehatan yang berstatus aktif. Dipakai mengisi APT-01 (resep manual) & APT-03 (register pengeluaran obat).',
            'sumber' => 'immst_products',
        ],
        'lain-lain' => [
            'label' => 'Lain-lain',
            'unit' => 'Administrasi RJ / UGD / RI',
            'desc' => 'Tarif komponen biaya lain-lain (administrasi, bahan habis pakai paket tindakan, dsb.) yang berstatus aktif.',
            'sumber' => 'rsmst_others',
        ],
    ];

    /** Label kategori (fallback ke kunci bila tak dikenal). */
    public static function labelKategori(string $kategori): string
    {
        return self::KATEGORI[$kategori]['label'] ?? $kategori;
    }

    public static function adaKategori(string $kategori): bool
    {
        return array_key_exists($kategori, self::KATEGORI);
    }

    /**
     * Definisi kolom tabel per kategori.
     *
     * key   : kunci pada baris hasil baris()
     * label : judul kolom
     * rata  : 'kiri' | 'kanan' | 'tengah'
     * lebar : lebar kolom untuk tabel cetak (dompdf butuh lebar eksplisit)
     * uang  : true → dirender sebagai rupiah, 0/null jadi tanda strip
     *
     * @return array<int, array<string, mixed>>
     */
    public static function kolom(string $kategori): array
    {
        return match ($kategori) {
            'dokter-rj-ugd' => [
                ['key' => 'nama', 'label' => 'Dokter', 'rata' => 'kiri', 'lebar' => '30%'],
                ['key' => 'kelompok', 'label' => 'Poli', 'rata' => 'kiri', 'lebar' => '18%'],
                ['key' => 'admin', 'label' => 'Administrasi RS', 'rata' => 'kanan', 'lebar' => '13%', 'uang' => true],
                ['key' => 'poli', 'label' => 'Poli Umum', 'rata' => 'kanan', 'lebar' => '13%', 'uang' => true],
                ['key' => 'poliBpjs', 'label' => 'Poli BPJS', 'rata' => 'kanan', 'lebar' => '13%', 'uang' => true],
                ['key' => 'ugd', 'label' => 'UGD Umum', 'rata' => 'kanan', 'lebar' => '13%', 'uang' => true],
                ['key' => 'ugdBpjs', 'label' => 'UGD BPJS', 'rata' => 'kanan', 'lebar' => '13%', 'uang' => true],
            ],
            'kamar' => [
                ['key' => 'nama', 'label' => 'Ruangan', 'rata' => 'kiri', 'lebar' => '30%'],
                ['key' => 'kelas', 'label' => 'Kelas', 'rata' => 'kiri', 'lebar' => '14%'],
                ['key' => 'kamar', 'label' => 'Kamar / hari', 'rata' => 'kanan', 'lebar' => '14%', 'uang' => true],
                ['key' => 'perawatan', 'label' => 'Perawatan / hari', 'rata' => 'kanan', 'lebar' => '14%', 'uang' => true],
                ['key' => 'umum', 'label' => 'Pelayanan Umum', 'rata' => 'kanan', 'lebar' => '14%', 'uang' => true],
                ['key' => 'total', 'label' => 'Total / hari', 'rata' => 'kanan', 'lebar' => '14%', 'uang' => true],
            ],
            'visite' => [
                ['key' => 'nama', 'label' => 'Dokter', 'rata' => 'kiri', 'lebar' => '34%'],
                ['key' => 'kelas', 'label' => 'Kelas', 'rata' => 'kiri', 'lebar' => '14%'],
                ['key' => 'visite', 'label' => 'Visite Umum', 'rata' => 'kanan', 'lebar' => '13%', 'uang' => true],
                ['key' => 'visiteBpjs', 'label' => 'Visite BPJS', 'rata' => 'kanan', 'lebar' => '13%', 'uang' => true],
                ['key' => 'konsul', 'label' => 'Konsul Umum', 'rata' => 'kanan', 'lebar' => '13%', 'uang' => true],
                ['key' => 'konsulBpjs', 'label' => 'Konsul BPJS', 'rata' => 'kanan', 'lebar' => '13%', 'uang' => true],
            ],
            'jasa-medis', 'jasa-dokter' => self::kolomJasa($kategori),
            'jasa-karyawan' => [
                ['key' => 'kode', 'label' => 'Kode', 'rata' => 'kiri', 'lebar' => '12%'],
                ['key' => 'nama', 'label' => 'Jasa Karyawan', 'rata' => 'kiri', 'lebar' => '58%'],
                ['key' => 'harga', 'label' => 'Tarif Umum', 'rata' => 'kanan', 'lebar' => '15%', 'uang' => true],
                ['key' => 'hargaBpjs', 'label' => 'Tarif BPJS', 'rata' => 'kanan', 'lebar' => '15%', 'uang' => true],
            ],
            'laborat' => [
                ['key' => 'kelompok', 'label' => 'Kelompok', 'rata' => 'kiri', 'lebar' => '26%'],
                ['key' => 'kode', 'label' => 'Kode', 'rata' => 'kiri', 'lebar' => '12%'],
                ['key' => 'nama', 'label' => 'Pemeriksaan', 'rata' => 'kiri', 'lebar' => '44%'],
                ['key' => 'harga', 'label' => 'Tarif', 'rata' => 'kanan', 'lebar' => '18%', 'uang' => true],
            ],
            'radiologi' => [
                ['key' => 'kode', 'label' => 'Kode', 'rata' => 'kiri', 'lebar' => '14%'],
                ['key' => 'nama', 'label' => 'Pemeriksaan', 'rata' => 'kiri', 'lebar' => '66%'],
                ['key' => 'harga', 'label' => 'Tarif', 'rata' => 'kanan', 'lebar' => '20%', 'uang' => true],
            ],
            'obat' => [
                ['key' => 'kode', 'label' => 'Kode', 'rata' => 'kiri', 'lebar' => '12%'],
                ['key' => 'nama', 'label' => 'Nama Obat / Alkes', 'rata' => 'kiri', 'lebar' => '44%'],
                ['key' => 'satuan', 'label' => 'Satuan', 'rata' => 'kiri', 'lebar' => '12%'],
                ['key' => 'kelompok', 'label' => 'Kategori', 'rata' => 'kiri', 'lebar' => '17%'],
                ['key' => 'harga', 'label' => 'Harga Jual', 'rata' => 'kanan', 'lebar' => '15%', 'uang' => true],
            ],
            'lain-lain' => [
                ['key' => 'kode', 'label' => 'Kode', 'rata' => 'kiri', 'lebar' => '14%'],
                ['key' => 'nama', 'label' => 'Item Biaya', 'rata' => 'kiri', 'lebar' => '66%'],
                ['key' => 'harga', 'label' => 'Tarif', 'rata' => 'kanan', 'lebar' => '20%', 'uang' => true],
            ],
            default => [],
        };
    }

    /**
     * Kolom jasa medis / jasa dokter: tarif poli (RJ/UGD) + satu kolom per kelas kamar
     * (rawat inap). Kolom kelas dibangun dari master kelas yang bernama, jadi kalau
     * RS menambah kelas, tabel & cetakan ikut bertambah kolom tanpa ubah kode.
     *
     * @return array<int, array<string, mixed>>
     */
    private static function kolomJasa(string $kategori): array
    {
        $daftarKelas = self::daftarKelas();

        // Tiap kelas punya 2 kolom (umum & BPJS), ditambah 2 kolom tarif poli.
        $jumlahKolomTarif = 2 + 2 * count($daftarKelas);
        $lebarTarif = round(76 / max($jumlahKolomTarif, 1), 2) . '%';

        $kolom = [
            ['key' => 'kode', 'label' => 'Kode', 'rata' => 'kiri', 'lebar' => '5%'],
            ['key' => 'nama', 'label' => self::labelKategori($kategori), 'rata' => 'kiri', 'lebar' => '19%'],
            // 'batas' = kolom pembuka satu kelompok → digambar garis tebal di kirinya.
            ['key' => 'harga', 'label' => 'Umum', 'rata' => 'kanan', 'lebar' => $lebarTarif, 'uang' => true, 'grup' => 'poli', 'warna' => 'poli', 'batas' => true],
            ['key' => 'hargaBpjs', 'label' => 'BPJS', 'rata' => 'kanan', 'lebar' => $lebarTarif, 'uang' => true, 'grup' => 'poli', 'warna' => 'poli'],
        ];

        // Warna kelas diselang-seling supaya pasangan Umum/BPJS milik satu kelas
        // terbaca sebagai satu blok di tabel selebar 12 kolom nominal.
        $urutanKelas = 0;

        foreach ($daftarKelas as $classId => $classDesc) {
            $warna = $urutanKelas % 2 === 0 ? 'inap-a' : 'inap-b';
            $urutanKelas++;

            foreach ([['kelas' . $classId, 'Umum'], ['kelas' . $classId . 'Bpjs', 'BPJS']] as $urutan => [$kunciKolom, $labelKolom]) {
                $kolom[] = [
                    'key' => $kunciKolom,
                    'label' => $labelKolom,
                    'rata' => 'kanan',
                    'lebar' => $lebarTarif,
                    'tipe' => 'tarifKelas',
                    'grup' => 'inap',
                    'subgrup' => 'kelas' . $classId,
                    'subgrupLabel' => $classDesc,
                    'warna' => $warna,
                    'batas' => $urutan === 0,
                ];
            }
        }

        return $kolom;
    }

    /**
     * Susunan header tabel, sampai tiga tingkat:
     *
     *   tingkat 1 : kategori biasa — satu baris judul kolom
     *   tingkat 2 : ada kolom bergrup ('poli'/'inap') — baris judul grup + baris kolom
     *   tingkat 3 : grup masih dipecah per kelas kamar — baris grup, baris kelas,
     *               lalu baris Umum/BPJS
     *
     * Contoh tingkat 3 (jasa medis / jasa dokter):
     *   ┌──────┬──────┬───────────────┬───────────────────────────────────┐
     *   │ Kode │ Nama │  TARIF POLI   │   TARIF RAWAT INAP PER KELAS      │
     *   │      │      │               ├─────────────────┬─────────────────┤
     *   │      │      │               │   KELAS SATU    │   KELAS DUA     │
     *   │      │      ├───────┬───────┼────────┬────────┼────────┬────────┤
     *   │      │      │ Umum  │ BPJS  │  Umum  │  BPJS  │  Umum  │  BPJS  │
     *
     * @return array{tingkat: int, berlapis: bool, atas: array<int, array<string, mixed>>, tengah: array<int, array<string, mixed>>, bawah: array<int, array<string, mixed>>}
     */
    public static function headerKolom(string $kategori): array
    {
        $kolom = self::kolom($kategori);
        $adaGrup = collect($kolom)->contains(fn($satuKolom) => filled($satuKolom['grup'] ?? null));
        $adaSubgrup = collect($kolom)->contains(fn($satuKolom) => filled($satuKolom['subgrup'] ?? null));

        if (!$adaGrup) {
            return ['tingkat' => 1, 'berlapis' => false, 'atas' => $kolom, 'tengah' => [], 'bawah' => []];
        }

        $tingkat = $adaSubgrup ? 3 : 2;

        // Grup yang punya pecahan kelas hanya memakai 1 baris; grup polos memanjang
        // ke bawah sampai tepat di atas baris judul kolomnya.
        $grupBersubgrup = collect($kolom)
            ->filter(fn($satuKolom) => filled($satuKolom['subgrup'] ?? null))
            ->pluck('grup')
            ->unique()
            ->all();

        $atas = [];
        $tengah = [];
        $bawah = [];
        $grupBerjalan = null;
        $subgrupBerjalan = null;

        foreach ($kolom as $satuKolom) {
            $grup = $satuKolom['grup'] ?? null;

            if ($grup === null) {
                $atas[] = ['label' => $satuKolom['label'], 'colspan' => 1, 'rowspan' => $tingkat, 'lebar' => $satuKolom['lebar'] ?? null];
                $grupBerjalan = null;
                $subgrupBerjalan = null;
                continue;
            }

            if ($grup !== $grupBerjalan) {
                $atas[] = [
                    'label' => self::GRUP_KOLOM[$grup] ?? $grup,
                    'colspan' => 1,
                    'rowspan' => in_array($grup, $grupBersubgrup, true) ? 1 : $tingkat - 1,
                    'lebar' => null,
                    // Judul grup memakai warna netral grup, bukan warna selang-seling kelas.
                    'warna' => $grup,
                    'batas' => true,
                ];
                $grupBerjalan = $grup;
            } else {
                $atas[count($atas) - 1]['colspan']++;
            }

            $subgrup = $satuKolom['subgrup'] ?? null;

            if ($subgrup !== null) {
                if ($subgrup !== $subgrupBerjalan) {
                    $tengah[] = ['label' => $satuKolom['subgrupLabel'] ?? $subgrup, 'colspan' => 1, 'warna' => $satuKolom['warna'] ?? null, 'batas' => true];
                    $subgrupBerjalan = $subgrup;
                } else {
                    $tengah[count($tengah) - 1]['colspan']++;
                }
            }

            $bawah[] = ['label' => $satuKolom['label'], 'lebar' => $satuKolom['lebar'] ?? null, 'warna' => $satuKolom['warna'] ?? null, 'batas' => $satuKolom['batas'] ?? false];
        }

        return ['tingkat' => $tingkat, 'berlapis' => true, 'atas' => $atas, 'tengah' => $tengah, 'bawah' => $bawah];
    }

    /**
     * Query builder per kategori — dipakai halaman (paginate) maupun cetak (get).
     * Pencarian selalu UPPER + LIKE karena master Oracle campur huruf besar/kecil.
     */
    public static function query(string $kategori, string $kataKunci = '')
    {
        $kataKunci = trim($kataKunci);
        $polaPencarian = '%' . mb_strtoupper($kataKunci) . '%';

        return match ($kategori) {
            'dokter-rj-ugd' => DB::table('rsmst_doctors as d')
                ->leftJoin('rsmst_polis as p', 'd.poli_id', '=', 'p.poli_id')
                ->select('d.dr_id', 'd.dr_name', 'p.poli_desc', 'd.rs_admin', 'd.poli_price', 'd.poli_price_bpjs', 'd.ugd_price', 'd.ugd_price_bpjs')
                ->where('d.active_status', '1')
                ->when($kataKunci !== '', fn($subQuery) => $subQuery->whereRaw("UPPER(d.dr_name || ' ' || NVL(p.poli_desc,' ')) LIKE ?", [$polaPencarian]))
                ->orderBy('d.dr_name'),

            'kamar' => DB::table('rsmst_rooms as r')
                ->leftJoin('rsmst_class as c', 'r.class_id', '=', 'c.class_id')
                ->select('r.room_id', 'r.room_name', 'r.class_id', 'c.class_desc', 'r.room_price', 'r.perawatan_price', 'r.common_service')
                ->where('r.active_status', '1')
                ->when($kataKunci !== '', fn($subQuery) => $subQuery->whereRaw("UPPER(r.room_name || ' ' || r.room_id || ' ' || NVL(c.class_desc,' ')) LIKE ?", [$polaPencarian]))
                ->orderBy('c.class_id')
                ->orderBy('r.room_name'),

            'visite' => DB::table('rsmst_docvisits as v')
                ->join('rsmst_doctors as d', 'v.dr_id', '=', 'd.dr_id')
                ->leftJoin('rsmst_class as c', 'v.class_id', '=', 'c.class_id')
                ->select('v.dr_id', 'd.dr_name', 'v.class_id', 'c.class_desc', 'v.visit_price', 'v.visit_price_bpjs', 'v.konsul_price', 'v.konsul_price_bpjs')
                ->where('d.active_status', '1')
                // Baris tarif nol tidak berguna sebagai acuan tulis tangan — master
                // menyimpan satu baris per dokter x kelas walau belum ditarifkan.
                ->whereRaw('NVL(v.visit_price,0) + NVL(v.visit_price_bpjs,0) + NVL(v.konsul_price,0) + NVL(v.konsul_price_bpjs,0) > 0')
                ->when($kataKunci !== '', fn($subQuery) => $subQuery->whereRaw("UPPER(d.dr_name || ' ' || NVL(c.class_desc,' ')) LIKE ?", [$polaPencarian]))
                ->orderBy('d.dr_name')
                ->orderBy('v.class_id'),

            'jasa-medis' => DB::table('rsmst_actparamedics')
                ->select('pact_id', 'pact_desc', 'pact_price', 'pact_price_bpjs')
                // NVL: 3 baris ber-active_status NULL (mis. PASANG VENTILATOR,
                // AMBIL SAMPEL BGA) masih dipakai — kosong dianggap aktif, kalau
                // dibandingkan langsung dengan '1' item itu hilang dari daftar.
                ->whereRaw("NVL(active_status,'1') = '1'")
                ->when($kataKunci !== '', fn($subQuery) => $subQuery->whereRaw("UPPER(pact_desc || ' ' || pact_id) LIKE ?", [$polaPencarian]))
                ->orderBy('pact_desc')
                ->orderBy('pact_id'),

            'jasa-dokter' => DB::table('rsmst_accdocs')
                ->select('accdoc_id', 'accdoc_desc', 'accdoc_price', 'accdoc_price_bpjs')
                ->whereRaw("NVL(active_status,'1') = '1'")
                ->when($kataKunci !== '', fn($subQuery) => $subQuery->whereRaw("UPPER(accdoc_desc || ' ' || accdoc_id) LIKE ?", [$polaPencarian]))
                ->orderBy('accdoc_desc')
                ->orderBy('accdoc_id'),

            'jasa-karyawan' => DB::table('rsmst_actemps')
                ->select('acte_id', 'acte_desc', 'acte_price', 'acte_price_bpjs')
                ->whereRaw("NVL(active_status,'1') = '1'")
                ->when($kataKunci !== '', fn($subQuery) => $subQuery->whereRaw("UPPER(acte_desc || ' ' || acte_id) LIKE ?", [$polaPencarian]))
                ->orderBy('acte_desc')
                ->orderBy('acte_id'),

            // Hanya item yang bisa diorder: parameter turunan (clabitem_group terisi)
            // ikut paket induknya, tidak punya tarif sendiri.
            'laborat' => DB::table('lbmst_clabitems as i')
                ->leftJoin('lbmst_clabs as g', 'i.clab_id', '=', 'g.clab_id')
                ->select('i.clabitem_id', 'i.clabitem_desc', 'i.price', 'g.clab_desc', 'i.item_seq')
                ->whereNull('i.clabitem_group')
                ->whereNotNull('i.clabitem_desc')
                ->whereRaw("NVL(i.hidden_status,'N') <> 'Y'")
                ->when($kataKunci !== '', fn($subQuery) => $subQuery->whereRaw("UPPER(i.clabitem_desc || ' ' || i.clabitem_id || ' ' || NVL(g.clab_desc,' ')) LIKE ?", [$polaPencarian]))
                ->orderBy('g.clab_desc')
                ->orderBy('i.item_seq')
                ->orderBy('i.clabitem_desc'),

            'radiologi' => DB::table('rsmst_radiologis')
                ->select('rad_id', 'rad_desc', 'rad_price')
                ->whereRaw("NVL(active_status,'1') = '1'")
                ->when($kataKunci !== '', fn($subQuery) => $subQuery->whereRaw("UPPER(rad_desc || ' ' || rad_id) LIKE ?", [$polaPencarian]))
                ->orderBy('rad_desc')
                ->orderBy('rad_id'),

            'obat' => DB::table('immst_products as p')
                ->leftJoin('immst_uoms as u', 'p.uom_id', '=', 'u.uom_id')
                ->leftJoin('immst_catproducts as c', 'p.cat_id', '=', 'c.cat_id')
                ->select('p.product_id', 'p.product_name', 'p.kode', 'p.sales_price', 'u.uom_desc', 'c.cat_desc')
                ->where('p.active_status', '1')
                ->when($kataKunci !== '', fn($subQuery) => $subQuery->whereRaw("UPPER(p.product_name || ' ' || p.product_id || ' ' || NVL(p.kode,' ') || ' ' || NVL(c.cat_desc,' ')) LIKE ?", [$polaPencarian]))
                ->orderBy('p.product_name'),

            'lain-lain' => DB::table('rsmst_others')
                ->select('other_id', 'other_desc', 'other_price')
                ->where('active_status', '1')
                ->when($kataKunci !== '', fn($subQuery) => $subQuery->whereRaw("UPPER(other_desc || ' ' || other_id) LIKE ?", [$polaPencarian]))
                ->orderBy('other_desc')
                ->orderBy('other_id'),

            default => DB::table('rsmst_others')->whereRaw('1 = 0'),
        };
    }

    /**
     * Ubah baris mentah DB jadi baris tampil seragam sesuai kolom().
     * Untuk jasa medis & jasa dokter, tarif per kelas diambil sekali untuk seluruh
     * halaman (satu query whereIn) lalu ditempel sebagai baris 'kelas'.
     *
     * @param iterable<int, object> $barisMaster
     * @return array<int, array<string, mixed>>
     */
    public static function baris(string $kategori, iterable $barisMaster): array
    {
        $daftar = collect($barisMaster);

        if ($daftar->isEmpty()) {
            return [];
        }

        return match ($kategori) {
            'dokter-rj-ugd' => $daftar
                ->map(
                    fn($dokter) => [
                        'nama' => trim((string) ($dokter->dr_name ?? '')) ?: '-',
                        'kelompok' => trim((string) ($dokter->poli_desc ?? '')) ?: '-',
                        'admin' => (int) ($dokter->rs_admin ?? 0),
                        'poli' => (int) ($dokter->poli_price ?? 0),
                        'poliBpjs' => (int) ($dokter->poli_price_bpjs ?? 0),
                        'ugd' => (int) ($dokter->ugd_price ?? 0),
                        'ugdBpjs' => (int) ($dokter->ugd_price_bpjs ?? 0),
                    ],
                )
                ->all(),

            'kamar' => $daftar
                ->map(
                    fn($kamar) => [
                        'nama' => trim((string) ($kamar->room_name ?? '')) . ' (' . trim((string) ($kamar->room_id ?? '')) . ')',
                        'kelas' => trim((string) ($kamar->class_desc ?? '')) ?: '-',
                        'kamar' => (int) ($kamar->room_price ?? 0),
                        'perawatan' => (int) ($kamar->perawatan_price ?? 0),
                        'umum' => (int) ($kamar->common_service ?? 0),
                        'total' => (int) ($kamar->room_price ?? 0) + (int) ($kamar->perawatan_price ?? 0) + (int) ($kamar->common_service ?? 0),
                    ],
                )
                ->all(),

            'visite' => $daftar
                ->map(
                    fn($tarifDokter) => [
                        'nama' => trim((string) ($tarifDokter->dr_name ?? '')) ?: '-',
                        'kelas' => trim((string) ($tarifDokter->class_desc ?? '')) ?: 'Kelas ' . ($tarifDokter->class_id ?? '-'),
                        'visite' => (int) ($tarifDokter->visit_price ?? 0),
                        'visiteBpjs' => (int) ($tarifDokter->visit_price_bpjs ?? 0),
                        'konsul' => (int) ($tarifDokter->konsul_price ?? 0),
                        'konsulBpjs' => (int) ($tarifDokter->konsul_price_bpjs ?? 0),
                    ],
                )
                ->all(),

            'jasa-medis' => self::barisTarifKelas(
                $daftar,
                kolomId: 'pact_id',
                kolomNama: 'pact_desc',
                kolomHarga: 'pact_price',
                kolomHargaBpjs: 'pact_price_bpjs',
                tabelKelas: 'rsmst_actpclasses',
                kolomKelasHarga: 'actp_price',
                kolomKelasHargaBpjs: 'actp_price_bpjs',
            ),

            'jasa-dokter' => self::barisTarifKelas(
                $daftar,
                kolomId: 'accdoc_id',
                kolomNama: 'accdoc_desc',
                kolomHarga: 'accdoc_price',
                kolomHargaBpjs: 'accdoc_price_bpjs',
                tabelKelas: 'rsmst_actdclasses',
                kolomKelasHarga: 'actd_price',
                kolomKelasHargaBpjs: 'actd_price_bpjs',
            ),

            'jasa-karyawan' => $daftar
                ->map(
                    fn($jasa) => [
                        'kode' => trim((string) ($jasa->acte_id ?? '')),
                        'nama' => trim((string) ($jasa->acte_desc ?? '')) ?: '-',
                        'harga' => (int) ($jasa->acte_price ?? 0),
                        'hargaBpjs' => (int) ($jasa->acte_price_bpjs ?? 0),
                    ],
                )
                ->all(),

            'laborat' => $daftar
                ->map(
                    fn($pemeriksaan) => [
                        'kelompok' => trim((string) ($pemeriksaan->clab_desc ?? '')) ?: '-',
                        'kode' => trim((string) ($pemeriksaan->clabitem_id ?? '')),
                        'nama' => trim((string) ($pemeriksaan->clabitem_desc ?? '')) ?: '-',
                        'harga' => (int) ($pemeriksaan->price ?? 0),
                    ],
                )
                ->all(),

            'radiologi' => $daftar
                ->map(
                    fn($pemeriksaan) => [
                        'kode' => trim((string) ($pemeriksaan->rad_id ?? '')),
                        'nama' => trim((string) ($pemeriksaan->rad_desc ?? '')) ?: '-',
                        'harga' => (int) ($pemeriksaan->rad_price ?? 0),
                    ],
                )
                ->all(),

            'obat' => $daftar
                ->map(
                    fn($obat) => [
                        'kode' => trim((string) ($obat->kode ?? '')) ?: trim((string) ($obat->product_id ?? '')),
                        'nama' => trim((string) ($obat->product_name ?? '')) ?: '-',
                        'satuan' => trim((string) ($obat->uom_desc ?? '')) ?: '-',
                        'kelompok' => trim((string) ($obat->cat_desc ?? '')) ?: '-',
                        'harga' => (int) ($obat->sales_price ?? 0),
                    ],
                )
                ->all(),

            'lain-lain' => $daftar
                ->map(
                    fn($biaya) => [
                        'kode' => trim((string) ($biaya->other_id ?? '')),
                        'nama' => trim((string) ($biaya->other_desc ?? '')) ?: '-',
                        'harga' => (int) ($biaya->other_price ?? 0),
                    ],
                )
                ->all(),

            default => [],
        };
    }

    /**
     * Baris jasa medis / jasa dokter + tarif per kelas kamar.
     *
     * Tarif kelas hanya ditampilkan bila nilainya > 0 DAN berbeda dari tarif dasar,
     * sesuai urutan harga efektif di LOV rawat inap: tarif kelas dulu, baru tarif dasar.
     *
     * @param \Illuminate\Support\Collection<int, object> $daftarJasa
     * @return array<int, array<string, mixed>>
     */
    private static function barisTarifKelas(
        $daftarJasa,
        string $kolomId,
        string $kolomNama,
        string $kolomHarga,
        string $kolomHargaBpjs,
        string $tabelKelas,
        string $kolomKelasHarga,
        string $kolomKelasHargaBpjs,
    ): array {
        $daftarId = $daftarJasa
            ->pluck($kolomId)
            ->filter(fn($nilai) => $nilai !== null && $nilai !== '')
            ->map(fn($nilai) => (string) $nilai)
            ->unique()
            ->values();

        $kelasPerItem = [];

        if ($daftarId->isNotEmpty()) {
            // Oracle membatasi IN list 1.000 nilai — pecah per 1.000.
            foreach ($daftarId->chunk(1000) as $potongan) {
                $barisKelas = DB::table($tabelKelas)
                    ->select($kolomId, 'class_id', $kolomKelasHarga, $kolomKelasHargaBpjs)
                    ->whereIn($kolomId, $potongan->all())
                    ->orderBy('class_id')
                    ->get();

                // Dikunci per id jasa DAN per class_id supaya nilai tiap kolom kelas
                // bisa diambil langsung tanpa mencari ulang di dalam loop baris.
                foreach ($barisKelas as $barisTarif) {
                    $idJasa = (string) ($barisTarif->{$kolomId} ?? '');
                    $idKelas = (string) ($barisTarif->class_id ?? '');

                    $kelasPerItem[$idJasa][$idKelas] = [
                        'harga' => (int) ($barisTarif->{$kolomKelasHarga} ?? 0),
                        'hargaBpjs' => (int) ($barisTarif->{$kolomKelasHargaBpjs} ?? 0),
                    ];
                }
            }
        }

        $daftarKelas = self::daftarKelas();

        return $daftarJasa
            ->map(function ($jasa) use ($kolomId, $kolomNama, $kolomHarga, $kolomHargaBpjs, $kelasPerItem, $daftarKelas) {
                $idJasa = (string) ($jasa->{$kolomId} ?? '');
                $hargaPoli = (int) ($jasa->{$kolomHarga} ?? 0);
                $hargaPoliBpjs = (int) ($jasa->{$kolomHargaBpjs} ?? 0);

                $baris = [
                    'kode' => $idJasa,
                    'nama' => trim((string) ($jasa->{$kolomNama} ?? '')) ?: '-',
                    'harga' => $hargaPoli,
                    'hargaBpjs' => $hargaPoliBpjs,
                ];

                // Dua kunci per kelas kamar: 'kelas1' (umum) & 'kelas1Bpjs'. Nilainya
                // array supaya sel bisa menandai asal tarif. Urutan harga efektif
                // mengikuti LOV rawat inap: tarif kelas dulu (kalau > 0), baru jatuh
                // ke tarif poli; khusus BPJS turun ke poli BPJS lalu poli umum.
                foreach ($daftarKelas as $classId => $classDesc) {
                    $tarifKelas = $kelasPerItem[$idJasa][(string) $classId] ?? null;
                    $hargaKelas = (int) ($tarifKelas['harga'] ?? 0);
                    $hargaKelasBpjs = (int) ($tarifKelas['hargaBpjs'] ?? 0);

                    $baris['kelas' . $classId] = [
                        'harga' => $hargaKelas > 0 ? $hargaKelas : $hargaPoli,
                        'asal' => $hargaKelas > 0 ? 'kelas' : 'poli',
                    ];

                    $baris['kelas' . $classId . 'Bpjs'] = [
                        'harga' => $hargaKelasBpjs > 0 ? $hargaKelasBpjs : ($hargaPoliBpjs > 0 ? $hargaPoliBpjs : $hargaPoli),
                        'asal' => $hargaKelasBpjs > 0 ? 'kelas' : 'poli',
                    ];
                }

                return $baris;
            })
            ->all();
    }

    /** class_id → class_desc, dipakai melabeli tarif per kelas. */
    private static function daftarKelas(): array
    {
        static $daftarKelasCache = null;

        if ($daftarKelasCache !== null) {
            return $daftarKelasCache;
        }

        $daftarKelasCache = DB::table('rsmst_class')
            ->select('class_id', 'class_desc')
            ->whereNotNull('class_desc')
            ->orderBy('class_id')
            ->get()
            ->mapWithKeys(fn($kelas) => [(string) $kelas->class_id => trim((string) $kelas->class_desc)])
            ->all();

        return $daftarKelasCache;
    }

    /**
     * Paket cetak satu kategori: baris siap render + info pemotongan.
     *
     * @return array{kategori: string, label: string, kolom: array, baris: array, jumlah: int, terpotong: bool, kataKunci: string}
     */
    public static function paketCetak(string $kategori, string $kataKunci = ''): array
    {
        $jumlah = (int) self::query($kategori, $kataKunci)->count();

        $barisMaster = self::query($kategori, $kataKunci)->limit(self::MAKS_CETAK)->get();

        return [
            'kategori' => $kategori,
            'label' => self::labelKategori($kategori),
            'kolom' => self::kolom($kategori),
            'baris' => self::baris($kategori, $barisMaster),
            'jumlah' => $jumlah,
            'terpotong' => $jumlah > self::MAKS_CETAK,
            'kataKunci' => trim($kataKunci),
        ];
    }

    /** Format rupiah untuk layar. Tarif 0 / kosong = belum diisi di master. */
    public static function rupiah(mixed $nilai): string
    {
        $angka = (int) ($nilai ?? 0);

        return $angka > 0 ? 'Rp ' . number_format($angka, 0, ',', '.') : '—';
    }

    /**
     * Format nominal tanpa awalan "Rp" — dipakai cetak PDF. Jasa medis & jasa dokter
     * memuat 12 kolom nominal dalam satu baris A4 potrait, awalan "Rp" di tiap sel
     * membuat angkanya terpotong. Satuan rupiah ditulis sekali di keterangan tabel.
     */
    public static function angka(mixed $nilai): string
    {
        $angka = (int) ($nilai ?? 0);

        return $angka > 0 ? number_format($angka, 0, ',', '.') : '—';
    }
}
