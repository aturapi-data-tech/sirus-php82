<?php

/*
 * Hal BERSAMA beberapa jalur yang TIDAK punya centang sendiri — kelompok ini tak tampil sebagai
 * hak di Role Control (keputusan user 2026-11-25: hak dipetakan per kelompok RJ/UGD/RI/...).
 *
 *  - Halaman bertab gabungan (Antrian Apotek, Antrian Kasir): menu + tabnya mengikuti hak
 *    halaman asli tiap tab, yang didefinisikan di kelompok jalurnya masing-masing.
 *  - Pintu modal yang dibuka dari lebih dari satu jalur: daftar pembukanya = hak per jalur.
 *
 * Lihat App\Support\ProgramAkses & docs/standar-hak-akses.md.
 */

return [
    'judul' => 'Bersama',
    'program' => [
        'Antrian Apotek Gabungan' => [
            'menu' => [
                ['group' => 'Apotek', 'groupOrder' => 9, 'order' => 1, 'route' => 'apotek', 'gabungan' => ['rj.antrian-apotek', 'ugd.antrian-apotek', 'ri-resep.antrian'], 'title' => 'Antrian Apotek', 'desc' => 'Telaah resep & pelayanan kefarmasian — tab RJ, UGD, RI', 'roles' => ['admin', 'apoteker', 'manager medis'], 'badge' => 'APT'],
            ],
        ],

        'Antrian Kasir Gabungan' => [
            'menu' => [
                ['group' => 'Kasir', 'groupOrder' => 9, 'order' => 1, 'route' => 'kasir', 'gabungan' => ['rj.antrian-kasir', 'ugd.antrian-kasir', 'kasir.antrian-ri', 'kasir.daftar-ri', 'keuangan.penerimaan-kas-tu', 'keuangan.pengeluaran-kas-tu', 'keuangan.pembayaran-piutang-pasien', 'manajemen.rs.tu.piutang-pasien', 'keuangan.pembayaran-hutang-pbf', 'keuangan.pembayaran-hutang-non-medis', 'keuangan.topup-supplier-pbf', 'keuangan.topup-supplier-non-medis'], 'title' => 'Antrian Kasir', 'desc' => 'Kasir — tab RJ, UGD, RI', 'roles' => ['admin', 'tu', 'manager umum', 'supervisor tu'], 'badge' => 'KSR'],
            ],
        ],

        'Pintu Modal Lintas Jalur' => [
            'pintu' => [
                'rekam-medis-nasional.open' => ['rj.emr.bukaRekamMedisNasional', 'ugd.emr.bukaRekamMedisNasional', 'ri.emr.bukaRekamMedisNasional'],
                'daftar-surat-kontrol-bpjs.open' => ['rj.bpjs.hapusSuratKontrol', 'ri.bpjs.hapusSuratKontrol'],
            ],
        ],
    ],
];
