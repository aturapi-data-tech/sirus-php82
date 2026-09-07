<?php

namespace App\Support\AutomaticStopOrder;

/**
 * Isi AWAL master Automatic Stop Order — satu sumber kebenaran untuk
 * `php artisan automatic-stop-order:seed`, unit test, dan dokumentasi.
 *
 * Acuan: tabel "Batasan waktu stop order" Pedoman Pelayanan Kefarmasian RS
 * (PKPO). Dua angka (Narkotik, Kortikosteroid) diambil dari salinan yang rusak
 * dan WAJIB dicocokkan apoteker lewat /master/automatic-stop-order sebelum dipakai.
 *
 * Bentuk datanya meniru tabel Oracle (docs/ddl-automatic-stop-order.sql) supaya seed tinggal
 * memindahkan apa adanya: golongan[] -> RSMST_STOP_ORDER_GOLONGANS.
 * Pemetaan obat (RSMST_STOP_ORDER_PRODUCTS) TIDAK di-seed — itu keputusan apoteker.
 *
 * Teks di sini masuk Oracle WE8ISO8859P1: ASCII saja, tanpa simbol matematika
 * atau tanda pisah panjang (lihat memory feedback_oracle_charset_latin1).
 */
class AutomaticStopOrderDefault
{
    /**
     * batas_minimal_hari sengaja null di bawaan: tabel acuan RS hanya menyebut batas stop.
     * Isi lewat /master/automatic-stop-order bila kebijakan RS menetapkan lama pemberian minimal.
     *
     * @return array<int, array{golongan_kode:string, golongan_nama:string, batas_hari:int, batas_minimal_hari:?int, keterangan:string, urutan:int, active_status:string}>
     */
    public static function golongan(): array
    {
        $daftarGolongan = [
            ['VASODILATOR_TOPIKAL', 'Vasodilator (ophthalmic, nasal)', 3, 'Assessment ulang berdasarkan respon klinik pasien.'],
            ['PETHIDIN', 'Pethidin', 2, 'Untuk mencegah akumulasi hasil metabolisme yang toksik (norpetidin).'],
            ['KETOROLAK', 'Ketorolak (oral dan parenteral)', 5, 'IV maksimal 120 mg/hari. Untuk mencegah adverse effect pada ginjal dan saluran gastrointestinal.'],
            ['ANTIKOAGULAN', 'Antikoagulan (LMWH, heparin, fondaparinux)', 7, 'Assessment ulang berdasarkan respon klinik pasien.'],
            ['WARFARIN', 'Warfarin', 14, 'Assessment ulang berdasarkan respon klinik pasien.'],
            ['ANTIINFEKSI_SISTEMIK', 'Antiinfeksi oral dan parenteral, kecuali anti-TB', 7, 'Pemberian lanjutan diberikan bila tersedia hasil kultur, respon klinik yang baik, atau ada persetujuan dari KPRA dan KFT. Bila respon klinik membaik, lakukan assessment untuk switch dari parenteral ke oral.'],
            ['ANTIVIRAL', 'Antiviral, kecuali amantadin dan oseltamivir (sesuai protokol)', 7, 'Assessment ulang berdasarkan respon klinik pasien.'],
            ['ANTIINFEKSI_TOPIKAL', 'Antiinfeksi topikal, mata, telinga', 10, 'Assessment ulang berdasarkan respon klinik pasien.'],
            ['ANTIFUNGI', 'Antifungi oral dan topikal', 10, 'Assessment ulang berdasarkan respon klinik pasien.'],
            ['NARKOTIK', 'Narkotik', 7, 'Assessment ulang berdasarkan respon klinik pasien. Angka bawaan perlu dicocokkan dengan pedoman RS.'],
            ['KORTIKOSTEROID', 'Kortikosteroid (topikal, ophthalmic, oral)', 7, 'Assessment ulang berdasarkan respon klinik pasien. Angka bawaan perlu dicocokkan dengan pedoman RS.'],
            ['PENYAKIT_KRONIK', 'Obat penyakit kronik (DM, HT, jantung, psikiatri, dll)', 30, 'Assessment ulang berdasarkan respon klinik pasien.'],
        ];

        return array_map(fn(array $golongan, int $indeks) => [
            'golongan_kode'       => $golongan[0],
            'golongan_nama'       => $golongan[1],
            'batas_hari'     => $golongan[2],
            'batas_minimal_hari' => null,
            'keterangan'     => $golongan[3],
            'urutan'         => $indeks + 1,
            'active_status'  => '1',
        ], $daftarGolongan, array_keys($daftarGolongan));
    }
}
