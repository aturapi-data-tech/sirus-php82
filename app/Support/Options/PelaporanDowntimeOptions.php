<?php

namespace App\Support\Options;

use Carbon\Carbon;

// Format waktu catatan dipinjam dari modul pemantauan suhu — satu bentuk yang
// sama dipakai seluruh modul area Sistem, jadi tak dideklarasikan ulang di sini.
// §6.2 standar-struktur-folder: tulis use eksplisit walau satu namespace.
use App\Support\Options\SuhuRuangServerOptions;

/**
 * Sumber tunggal daftar pilihan formulir DT-01 "Log Kejadian & Penanganan Down
 * Time SIMRS" (Akreditasi MRMIK 13.1 - Penanganan Down Time).
 *
 * Isinya dikutip dari cetakan kosong yang sudah dipakai unit IT
 * (resources/views/pages/downtime/cetak/form/dt-01-log-kejadian.blade.php).
 * Jangan diringkas atau diterjemahkan ulang: yang direkam di sistem harus
 * terbaca sama dengan yang ditulis tangan di formulirnya.
 *
 * Dipakai bersama oleh formulir entry, layar list, dan blade cetak.
 */
class PelaporanDowntimeOptions
{
    /** Bagian A — jenis waktu henti. */
    public const JENIS = [
        'terencana' => 'Terencana',
        'tidakTerencana' => 'Tidak terencana',
    ];

    /** Bagian A — lingkup gangguan. */
    public const LINGKUP = [
        'seluruhSistem' => 'Seluruh sistem',
        'sebagianModul' => 'Sebagian modul',
    ];

    /** Bagian B — lewat apa laporan pertama masuk. */
    public const MEDIA_LAPORAN = [
        'telepon' => 'Telepon',
        'grup' => 'Grup / pesan',
        'langsung' => 'Langsung / datang',
        'lainnya' => 'Lainnya',
    ];

    /**
     * Bagian D — unit pelayanan yang dinilai dampaknya.
     *
     * Urutan & redaksinya mengikuti formulir cetak DT-01 supaya orang yang biasa
     * mengisi versi kertasnya menemukan barisnya di tempat yang sama.
     */
    public const UNIT_DAMPAK = [
        'pendaftaran' => 'Pendaftaran / TU',
        'rawatJalan' => 'Poli Rawat Jalan',
        'ugd' => 'Unit Gawat Darurat',
        'rawatInap' => 'Rawat Inap',
        'laboratorium' => 'Laboratorium',
        'radiologi' => 'Radiologi',
        'apotek' => 'Apotek',
        'kasir' => 'Kasir',
        'rekamMedis' => 'Rekam Medis',
    ];

    public static function labelJenis(?string $kunci): string
    {
        return self::JENIS[$kunci] ?? '-';
    }

    public static function labelLingkup(?string $kunci): string
    {
        return self::LINGKUP[$kunci] ?? '-';
    }

    public static function labelMediaLaporan(?string $kunci): string
    {
        return self::MEDIA_LAPORAN[$kunci] ?? '-';
    }

    public static function labelUnitDampak(?string $kunci): string
    {
        return self::UNIT_DAMPAK[$kunci] ?? '-';
    }

    /**
     * Kerangka Bagian D: SEMUA unit, termasuk yang tak terdampak.
     *
     * "Tidak terdampak" adalah keterangan yang bernilai untuk auditor - baris yang
     * dihilangkan tak bisa dibedakan dari baris yang lupa diisi.
     */
    public static function dampakKosong(): array
    {
        return array_values(array_map(
            fn (string $kunci) => [
                'unit' => $kunci,
                'manual' => false,
                'jumlah' => '',
                'catatan' => '',
            ],
            array_keys(self::UNIT_DAMPAK)
        ));
    }

    /**
     * Gabungkan dampak tersimpan ke kerangka baku.
     *
     * Dipanggil saat memuat: unit yang belum ada di record lama (mis. unit baru
     * ditambahkan ke daftar) tetap muncul kosong, dan unit yang sudah tak dipakai
     * dibuang - bukan ditampilkan sebagai kunci mentah.
     */
    public static function gabungDampak(array $tersimpan): array
    {
        $peta = [];

        foreach ($tersimpan as $baris) {
            if (is_array($baris) && filled($baris['unit'] ?? null)) {
                $peta[(string) $baris['unit']] = $baris;
            }
        }

        return array_map(function (array $kerangka) use ($peta): array {
            $lama = $peta[$kerangka['unit']] ?? [];

            return [
                'unit' => $kerangka['unit'],
                'manual' => (bool) ($lama['manual'] ?? false),
                'jumlah' => (string) ($lama['jumlah'] ?? ''),
                'catatan' => (string) ($lama['catatan'] ?? ''),
            ];
        }, self::dampakKosong());
    }

    /**
     * Modul / layanan terdampak — DITURUNKAN dari Bagian D, tidak diketik ulang.
     *
     * Formulir kertas punya baris "Modul / Layanan Terdampak" di Bagian A, tapi
     * isinya persis daftar unit yang dicentang "beralih ke manual" di Bagian D.
     * Mengetiknya dua kali cuma membuka peluang keduanya berbeda - dan yang
     * berbeda itu justru yang ditanya auditor. Cetakan mengisinya dari sini.
     */
    public static function modulTerdampakDari(array $dampak): string
    {
        $unit = array_map(
            fn (array $baris) => self::labelUnitDampak($baris['unit'] ?? null),
            array_filter(
                array_filter($dampak, 'is_array'),
                fn (array $baris) => ! empty($baris['manual'])
            )
        );

        return $unit === [] ? '' : implode(', ', $unit);
    }

    /**
     * Hasil penanganan — DITURUNKAN dari waktu pulih.
     *
     * Baris "Hasil Penanganan" di formulir kertas pada praktiknya selalu berbunyi
     * "layanan pulih" plus waktunya, yang sudah tercatat di Bagian A. Yang benar
     * benar bervariasi (apa yang dikerjakan) ada di Tindakan Penanganan.
     */
    public static function hasilPenangananDari(array $kejadian): string
    {
        if (self::belumPulih($kejadian)) {
            return 'Layanan belum dinyatakan pulih.';
        }

        $durasi = self::hitungDurasi($kejadian);

        return 'Seluruh layanan pulih ' . ($kejadian['waktuPulih'] ?? '')
            . ($durasi === '' ? '' : ' (waktu henti ' . $durasi . ').');
    }

    /**
     * Lingkup gangguan yang DISARANKAN dari Bagian D — dipakai sebagai pratinjau,
     * bukan pengganti pilihan petugas.
     *
     * Sengaja tidak dipaksakan: down time terencana tengah malam bisa saja tak
     * membuat satu unit pun beralih manual, padahal lingkupnya seluruh sistem.
     */
    public static function lingkupSaranDari(array $dampak): ?string
    {
        $manual = count(array_filter(
            array_filter($dampak, 'is_array'),
            fn (array $baris) => ! empty($baris['manual'])
        ));

        if ($manual === 0) {
            return null;
        }

        return $manual >= count(self::UNIT_DAMPAK) ? 'seluruhSistem' : 'sebagianModul';
    }

    /**
     * Durasi waktu henti sebagai teks siap simpan & cetak.
     *
     * Selisih dihitung dari TIMESTAMP, bukan diffInMinutes: Carbon 3 membalik
     * tanda pada beberapa pemakaian dan pernah menghasilkan durasi negatif di
     * repo ini (lihat memori feedback_carbon3_diff_signed).
     *
     * Mengembalikan '' bila salah satu ujungnya belum terisi atau tak terbaca -
     * bukan '0 menit', yang akan terbaca sebagai "tidak ada gangguan".
     */
    public static function hitungDurasi(array $kejadian): string
    {
        $mulai = self::waktu($kejadian['waktuMulai'] ?? null);
        $pulih = self::waktu($kejadian['waktuPulih'] ?? null);

        if ($mulai === null || $pulih === null) {
            return '';
        }

        $detik = $pulih->getTimestamp() - $mulai->getTimestamp();

        if ($detik < 0) {
            return '';
        }

        $menitTotal = intdiv($detik, 60);
        $hari = intdiv($menitTotal, 1440);
        $jam = intdiv($menitTotal % 1440, 60);
        $menit = $menitTotal % 60;

        $bagian = [];

        if ($hari > 0) {
            $bagian[] = $hari . ' hari';
        }

        if ($jam > 0) {
            $bagian[] = $jam . ' jam';
        }

        // Menit tetap ditulis walau 0, supaya "2 jam" tak rancu dengan "2 jam lebih".
        $bagian[] = $menit . ' menit';

        return implode(' ', $bagian);
    }

    /** Total menit waktu henti; null bila belum bisa dihitung. Dipakai rekap. */
    public static function menitDurasi(array $kejadian): ?int
    {
        $mulai = self::waktu($kejadian['waktuMulai'] ?? null);
        $pulih = self::waktu($kejadian['waktuPulih'] ?? null);

        if ($mulai === null || $pulih === null) {
            return null;
        }

        $detik = $pulih->getTimestamp() - $mulai->getTimestamp();

        return $detik < 0 ? null : intdiv($detik, 60);
    }

    /** Layanan belum dinyatakan pulih? Laporan tak boleh dikunci selama ini benar. */
    public static function belumPulih(array $kejadian): bool
    {
        return blank($kejadian['waktuPulih'] ?? null);
    }

    /** Waktu pulih mendahului waktu mulai? Dipakai guard saat menyimpan. */
    public static function pulihSebelumMulai(array $kejadian): bool
    {
        $mulai = self::waktu($kejadian['waktuMulai'] ?? null);
        $pulih = self::waktu($kejadian['waktuPulih'] ?? null);

        return $mulai !== null && $pulih !== null && $pulih->lt($mulai);
    }

    /** 'd/m/Y H:i:s' -> Carbon, atau null bila tak terbaca. */
    private static function waktu(?string $teks): ?Carbon
    {
        if (blank($teks)) {
            return null;
        }

        try {
            return Carbon::createFromFormat(SuhuRuangServerOptions::FORMAT_WAKTU, trim($teks));
        } catch (\Throwable) {
            return null;
        }
    }
}
