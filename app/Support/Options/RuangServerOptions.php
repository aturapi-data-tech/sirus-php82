<?php

namespace App\Support\Options;

/**
 * Identitas FISIK ruang server RS — dipakai bersama dua modul pemantauan
 * (Akreditasi MRMIK 2.2 - Perlindungan Data):
 *
 *   SuhuRuangServerOptions   pemantauan suhu & status AC
 *   AksesRuangServerOptions  catatan keluar-masuk ruang server
 *
 * Kenapa berdiri sendiri: keterangan ini tidak disimpan per baris — nilainya
 * tetap sepanjang umur ruangan — dan MUNCUL DI DUA CETAKAN. Kalau tiap modul
 * menyimpan salinannya sendiri, mengganti nama ruang berarti mengubah dua berkas
 * dan satu di antaranya pasti terlewat.
 *
 * Prasetel, bukan nilai mati: kalau kelak RS punya ruang server kedua, yang
 * berubah cukup di sini.
 */
class RuangServerOptions
{
    /** Nama / lokasi ruang server. */
    public const NAMA_RUANG = 'Ruang server';

    /** Gedung & lantai tempat ruang server berada. */
    public const GEDUNG_LANTAI = 'Lantai 2';

    /** Isi ruang server hari ini - rack, server, UPS. */
    public const JUMLAH_PERANGKAT = '2 rack 5 server 1 ups';

    /** Kapasitas AC yang terpasang. */
    public const KAPASITAS_PENDINGIN = '1.5 PK';

    /** Pemegang tanggung jawab ruang, tercetak di kop formulir akses. */
    public const PENANGGUNG_JAWAB = 'Kepala Unit IT / SIMRS';
}
