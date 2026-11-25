<?php

/*
 * Hak akses kelompok MASTER DATA — SATU BLOK PER PROGRAM (lihat App\Support\ProgramAkses).
 *
 *  'menu'       : entri menu sidebar + Lihat/Tambah/Ubah/Hapus + tombol khusus halaman ('khusus').
 *  'aksiKhusus' : aksi di luar halaman (tombol di modal/EMR, posting/batal) — nama Gate => label, roles awal.
 *  'pintu'      : event pembuka modal => hak siapa saja yang sah membukanya.
 *
 * roles = CENTANG AWAL saja; sesudah permission dibuat (otomatis saat deploy) yang berlaku
 * centang Role Control. Pedoman: docs/standar-hak-akses.md.
 */

use App\Services\AppMenu;

return [
    'judul' => 'Master Data',
    'program' => [
        'Master Pelayanan' => [
            'menu' => [
                ['group' => 'Master Pelayanan', 'groupOrder' => 1, 'order' => 1, 'route' => 'master.poli', 'title' => 'Master Poli', 'desc' => 'Kelola data poli & ruangan', 'roles' => AppMenu::MASTER_ROLES, 'badge' => 'Pelayanan'],
                ['group' => 'Master Pelayanan', 'groupOrder' => 1, 'order' => 2, 'route' => 'master.dokter', 'title' => 'Master Dokter', 'desc' => 'Kelola data dokter, spesialis, tarif poli/UGD/visit/konsul per kelas', 'roles' => AppMenu::MASTER_ROLES, 'badge' => 'Pelayanan'],
                ['group' => 'Master Pelayanan', 'groupOrder' => 1, 'order' => 3, 'route' => 'master.pasien', 'khusus' => ['ambilIhs' => ['Ambil IHS SATUSEHAT', ['admin', 'mr']]], 'title' => 'Master Pasien', 'desc' => 'Kelola data pasien & rekam medis', 'roles' => [...AppMenu::MASTER_ROLES, 'mr'], 'badge' => 'Pelayanan'],
                ['group' => 'Master Pelayanan', 'groupOrder' => 1, 'order' => 4, 'route' => 'master.diagnosa', 'title' => 'Master Diagnosa', 'desc' => 'Kelola data diagnosa ICD-10', 'roles' => AppMenu::MASTER_ROLES, 'badge' => 'Pelayanan'],
                ['group' => 'Master Pelayanan', 'groupOrder' => 1, 'order' => 5, 'route' => 'master.radiologis', 'title' => 'Master Radiologi', 'desc' => 'Kelola data radiologi', 'roles' => AppMenu::MASTER_ROLES, 'badge' => 'Pelayanan'],
                ['group' => 'Master Pelayanan', 'groupOrder' => 1, 'order' => 6, 'route' => 'master.others', 'title' => 'Master Lain-lain', 'desc' => 'Kelola data lain-lain', 'roles' => AppMenu::MASTER_ROLES, 'badge' => 'Pelayanan'],
                ['group' => 'Master Pelayanan', 'groupOrder' => 1, 'order' => 7, 'route' => 'master.agama', 'title' => 'Master Agama', 'desc' => 'Kelola data agama pasien', 'roles' => AppMenu::MASTER_ROLES, 'badge' => 'Pelayanan'],
                ['group' => 'Master Pelayanan', 'groupOrder' => 1, 'order' => 8, 'route' => 'master.diag-keperawatan', 'title' => 'Master Diagnosis Keperawatan', 'desc' => 'Kelola data SDKI, SLKI, SIKI asuhan keperawatan', 'roles' => AppMenu::MASTER_ROLES, 'badge' => 'Pelayanan'],
                ['group' => 'Master Pelayanan', 'groupOrder' => 1, 'order' => 9, 'route' => 'master.kamar', 'title' => 'Master Kamar', 'desc' => 'Kelola bangsal, kamar & bed rawat inap', 'roles' => AppMenu::MASTER_ROLES, 'badge' => 'Pelayanan'],
                ['group' => 'Master Pelayanan', 'groupOrder' => 1, 'order' => 10, 'route' => 'master.kelas', 'title' => 'Master Kelas Rawat', 'desc' => 'Kelola kelas kamar & mapping Aplicares / SIRS', 'roles' => AppMenu::MASTER_ROLES, 'badge' => 'Pelayanan'],
                ['group' => 'Master Pelayanan', 'groupOrder' => 1, 'order' => 11, 'route' => 'master.karyawan', 'title' => 'Master Karyawan', 'desc' => 'Kelola NIK karyawan untuk login user & coder iDRG (E-Klaim)', 'roles' => AppMenu::MASTER_ROLES, 'badge' => 'Pelayanan'],
                ['group' => 'Master Pelayanan', 'groupOrder' => 1, 'order' => 11, 'route' => 'master.karyawan-hr', 'title' => 'Master Karyawan HR', 'desc' => 'Kelola karyawan hrmst_employees: unit kerja, jabatan, status aktif & penanda petugas Kamar Operasi (crew OK)', 'roles' => AppMenu::MASTER_ROLES, 'badge' => 'Pelayanan'],
                ['group' => 'Master Pelayanan', 'groupOrder' => 1, 'order' => 12, 'route' => 'master.setup-jadwal-bpjs', 'title' => 'Pemetaan Jadwal Dokter', 'desc' => 'Ambil & terapkan jadwal praktek dokter dari BPJS ke data RS', 'roles' => [...AppMenu::MASTER_ROLES, 'mr'], 'badge' => 'BPJS'],
                ['group' => 'Master Pelayanan', 'groupOrder' => 1, 'order' => 13, 'route' => 'master.stocklocations', 'title' => 'Master Lokasi Stok', 'desc' => 'Kelola kode lokasi stok (gudang, apotek, ruangan, klinik) untuk transfer obat & barang', 'roles' => AppMenu::MASTER_ROLES, 'badge' => 'Pelayanan'],
                ['group' => 'Master Pelayanan', 'groupOrder' => 1, 'order' => 17, 'route' => 'master.ews', 'title' => 'Master EWS', 'desc' => 'Parameter, rentang skor & respon Early Warning System (Dewasa / Anak / Neonatus / MEOWS) untuk Observasi Lanjutan', 'roles' => AppMenu::MASTER_ROLES, 'badge' => 'EMR'],
                ['group' => 'Master Pelayanan', 'groupOrder' => 1, 'order' => 18, 'route' => 'master.automatic-stop-order', 'title' => 'Master Automatic Stop Order', 'desc' => 'Automatic Stop Order: golongan obat, batas hari order berhenti otomatis, dan pemetaan obat ke golongan', 'roles' => AppMenu::MASTER_ROLES, 'badge' => 'Apotek'],
                ['group' => 'Master Pelayanan', 'groupOrder' => 1, 'order' => 16, 'route' => 'master.identitas', 'title' => 'Master Identitas RS', 'desc' => 'Kop cetakan (nama, alamat, telepon) + setelan PPN & harga pokok penerimaan', 'roles' => AppMenu::MASTER_ROLES, 'badge' => 'Pelayanan'],
                ['group' => 'Master Pelayanan', 'groupOrder' => 1, 'order' => 14, 'route' => 'master.jasa-medis', 'title' => 'Master Jasa Medis', 'desc' => 'Kelola tarif jasa medis (Umum & BPJS) + paket bundling obat / lain-lain', 'roles' => AppMenu::MASTER_ROLES, 'badge' => 'Pelayanan'],
                ['group' => 'Master Pelayanan', 'groupOrder' => 1, 'order' => 15, 'route' => 'master.jasa-dokter', 'title' => 'Master Jasa Dokter', 'desc' => 'Kelola tarif jasa dokter (Umum & BPJS) + paket bundling obat / lain-lain', 'roles' => AppMenu::MASTER_ROLES, 'badge' => 'Pelayanan'],
                ['group' => 'Master Pelayanan', 'groupOrder' => 1, 'order' => 19, 'route' => 'master.mapping-satusehat', 'aksi' => ['lihat'], 'khusus' => ['lihatObat' => ['Baris Obat (KFA)', ['admin', 'apoteker']], 'lihatLaborat' => ['Baris Item Laboratorium (LOINC)', ['admin', 'laboratorium']], 'lihatRadiologi' => ['Baris Tindakan Radiologi (LOINC)', ['admin', 'radiologi']], 'lihatDokter' => ['Baris Dokter (IHS)', ['admin']], 'lihatPoli' => ['Baris Poli (Location)', ['admin']], 'lihatKamar' => ['Baris Kamar RI (Location)', ['admin']], 'lihatPasien' => ['Baris Pasien (IHS)', ['admin', 'mr']]], 'title' => 'Mapping SATUSEHAT', 'desc' => 'Cakupan KFA, LOINC, IHS dan Location pada master, dengan tautan ke yang belum terisi', 'roles' => ['admin', 'apoteker', 'laboratorium', 'radiologi', 'mr'], 'badge' => 'SATUSEHAT'],
            ],
        ],

        'Master Laboratorium' => [
            'menu' => [
                ['group' => 'Master Laboratorium', 'groupOrder' => 2, 'order' => 1, 'route' => 'master.laborat', 'title' => 'Master Laboratorium', 'desc' => 'Kelola kategori lab & item pemeriksaan', 'roles' => AppMenu::MASTER_ROLES, 'badge' => 'Lab'],
                ['group' => 'Master Laboratorium', 'groupOrder' => 2, 'order' => 2, 'route' => 'master.loinc', 'title' => 'Master LOINC', 'desc' => 'Kode LOINC pemeriksaan lab & observasi — LOV + pengiriman SATUSEHAT', 'roles' => AppMenu::MASTER_ROLES, 'badge' => 'SATUSEHAT'],
                ['group' => 'Master Laboratorium', 'groupOrder' => 2, 'order' => 3, 'route' => 'master.snomed', 'title' => 'Master SNOMED CT', 'desc' => 'Kode SNOMED CT keluhan / prosedur / alergi — LOV + pengiriman SATUSEHAT', 'roles' => AppMenu::MASTER_ROLES, 'badge' => 'SATUSEHAT'],
            ],
        ],

        'Master Apotek' => [
            'menu' => [
                ['group' => 'Master Apotek', 'groupOrder' => 3, 'order' => 1, 'route' => 'master.obat', 'title' => 'Master Obat', 'desc' => 'Kelola data obat & farmasi', 'roles' => AppMenu::MASTER_ROLES, 'badge' => 'Apotek'],
                ['group' => 'Master Apotek', 'groupOrder' => 3, 'order' => 2, 'route' => 'master.obat-kronis', 'title' => 'Master Obat Kronis', 'desc' => 'Daftar obat kronis BPJS — max qty per resep & tarif klaim', 'roles' => AppMenu::MASTER_ROLES, 'badge' => 'Kronis'],
                ['group' => 'Master Apotek', 'groupOrder' => 3, 'order' => 3, 'route' => 'master.signa-catatan', 'title' => 'Master Catatan Khusus Signa', 'desc' => 'LOV catatan khusus signa untuk e-resep (RJ/UGD/RI)', 'roles' => AppMenu::MASTER_ROLES, 'badge' => 'Apotek'],
                ['group' => 'Master Apotek', 'groupOrder' => 3, 'order' => 4, 'route' => 'master.interaksi-obat', 'title' => 'Master Interaksi Obat', 'desc' => 'Kelola kelompok interaksi antar obat & produk anggotanya', 'roles' => [...AppMenu::MASTER_ROLES, 'apoteker'], 'badge' => 'Apotek'],
            ],
        ],

        'Master Akuntansi' => [
            'menu' => [
                ['group' => 'Master Akuntansi', 'groupOrder' => 4, 'order' => 1, 'route' => 'master.group-akun', 'title' => 'Master Group Akun', 'desc' => 'Kelola group akun (Aktiva, Kewajiban, Modal, Pendapatan, Beban)', 'roles' => AppMenu::MASTER_ROLES, 'badge' => 'Akuntansi'],
                ['group' => 'Master Akuntansi', 'groupOrder' => 4, 'order' => 2, 'route' => 'master.akun', 'title' => 'Master Akun', 'desc' => 'Kelola COA (Chart of Account) — daftar akun & saldo awal', 'roles' => AppMenu::MASTER_ROLES, 'badge' => 'Akuntansi'],
                ['group' => 'Master Akuntansi', 'groupOrder' => 4, 'order' => 3, 'route' => 'master.konf-akun-trans', 'title' => 'Master Konfigurasi Akun Transaksi', 'desc' => 'Mapping akun debit/kredit untuk tiap jenis transaksi (RJ, UGD, RI, Resep, dll.)', 'roles' => AppMenu::MASTER_ROLES, 'badge' => 'Akuntansi'],
            ],
        ],
    ],
];
