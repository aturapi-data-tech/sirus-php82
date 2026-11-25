<?php

/*
 * Hak akses kelompok LINTAS JALUR — SATU BLOK PER PROGRAM (lihat App\Support\ProgramAkses).
 *
 *  'menu'       : entri menu sidebar + Lihat/Tambah/Ubah/Hapus + tombol khusus halaman ('khusus').
 *  'aksiKhusus' : aksi di luar halaman (tombol di modal/EMR, posting/batal) — nama Gate => label, roles awal.
 *  'pintu'      : event pembuka modal => hak siapa saja yang sah membukanya.
 *
 * roles = CENTANG AWAL saja; sesudah permission dibuat (otomatis saat deploy) yang berlaku
 * centang Role Control. Pedoman: docs/standar-hak-akses.md.
 */

use App\Support\AksiRole;

return [
    'judul' => 'Lintas Jalur',
    'program' => [
        'Antrian Apotek Semua Jalur' => [
            'menu' => [
                ['group' => 'Apotek', 'groupOrder' => 9, 'order' => 1, 'route' => 'apotek', 'aksi' => ['lihat'], 'title' => 'Antrian Apotek', 'desc' => 'Telaah resep & pelayanan kefarmasian — tab RJ, UGD, RI', 'roles' => ['admin', 'apoteker', 'manager medis'], 'badge' => 'APT'],
            ],
        ],

        'Antrian Kasir Semua Jalur' => [
            'menu' => [
                ['group' => 'Kasir', 'groupOrder' => 9, 'order' => 1, 'route' => 'kasir', 'aksi' => ['lihat'], 'title' => 'Antrian Kasir', 'desc' => 'Kasir — tab RJ, UGD, RI', 'roles' => ['admin', 'tu', 'manager umum', 'supervisor tu'], 'badge' => 'KSR'],
            ],
        ],

        'EMR Semua Jalur' => [
            'aksiKhusus' => [
                'emr.logAktivitas' => ['label' => 'Lihat log aktivitas EMR', 'roles' => AksiRole::EMR_LOG_AKTIVITAS],
                'emr.cetakEresep' => ['label' => 'Cetak e-resep dari EMR', 'roles' => AksiRole::EMR_CETAK_ERESEP],
                'emr.codingFhir' => ['label' => 'Coding SATUSEHAT', 'roles' => AksiRole::EMR_CODING_FHIR],
                'rekonsiliasi.obat' => ['label' => 'Rekonsiliasi obat (titik-3)', 'roles' => AksiRole::REKONSILIASI_OBAT],
                'penunjang.lihatHasil' => ['label' => 'Lihat & cetak hasil penunjang', 'roles' => AksiRole::PENUNJANG_LIHAT_HASIL],
            ],
            'pintu' => [
                'rekam-medis-nasional.open' => ['rj.emr.bukaRekamMedisNasional', 'ugd.emr.bukaRekamMedisNasional', 'ri.emr.bukaRekamMedisNasional'],
            ],
        ],

        'Modul Dokumen' => [
            'aksiKhusus' => [
                'dokumen.hapus' => ['label' => 'Hapus entri dokumen', 'roles' => AksiRole::DOKUMEN_HAPUS],
                'dokumen.bukaKunci' => ['label' => 'Buka kunci (cabut TTD) dokumen', 'roles' => AksiRole::DOKUMEN_BUKA_KUNCI],
                'dokumen.ttdPerwakilan' => ['label' => 'TTD atas nama pejabat (perwakilan)', 'roles' => AksiRole::DOKUMEN_TTD_PERWAKILAN],
            ],
        ],

        'Bridging' => [
            'aksiKhusus' => [
                'satusehat.kirim' => ['label' => 'Kirim SATUSEHAT', 'roles' => AksiRole::SATUSEHAT_KIRIM],
                'bpjs.hapusSuratKontrol' => ['label' => 'Hapus surat kontrol BPJS', 'roles' => AksiRole::BPJS_HAPUS_SURAT_KONTROL],
            ],
            'pintu' => [
                'daftar-ugd.satu-sehat.open' => ['satusehat.kirim'],
                'daftar-surat-kontrol-bpjs.open' => ['bpjs.hapusSuratKontrol'],
            ],
        ],
    ],
];
