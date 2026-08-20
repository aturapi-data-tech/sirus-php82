<?php

namespace App\Support;

/**
 * Penyusun teks log aktivitas untuk stempel waktu Antrean BPJS (taskId 1-7 & 99).
 *
 * Dibuat helper statis, bukan method trait: titik penyetel task tersebar di RJ,
 * UGD, dan RI-Resep yang memakai trait EMR berbeda-beda, dan sebagian komponen
 * memakai lebih dari satu trait sekaligus. Pola yang sama dipakai [[LogText]]
 * untuk menghindari tabrakan method trait.
 *
 * Arti task 1-7 dikonfirmasi user 2026-08-03; task 99 disimpulkan dari nama
 * berkas & alurnya (pembatalan) dan BELUM dikonfirmasi -- jangan menambah arti
 * lain dari hafalan. Lihat skill `bpjs-antrean-task-id`.
 */
class TaskIdAntrean
{
    private const LABEL = [
        '1'  => 'Admisi Daftar Pasien',
        '2'  => 'Dipanggil Admisi',
        '3'  => 'Daftar Poli',
        '4'  => 'Masuk Poli',
        '5'  => 'Keluar Poli',
        '6'  => 'Masuk Apotek',
        '7'  => 'Obat Diserahkan',
        '99' => 'Pembatalan',
    ];

    public static function label(int|string $task): string
    {
        return self::LABEL[(string) $task] ?? 'Task ' . $task;
    }

    /**
     * Satu baris log aktivitas untuk satu stempel task.
     *
     * @param  int|string   $task       nomor task (1-7, 99)
     * @param  string       $waktu      stempel yang disimpan, format d/m/Y H:i:s
     * @param  int|string|null $kodeBpjs kode balasan BPJS bila pada klik ini memang
     *                                   ada pengiriman; null = tidak mengirim
     * @param  string       $tambahan   keterangan konteks, mis. nomor lembar resep
     */
    public static function keterangan(int|string $task, string $waktu, int|string|null $kodeBpjs = null, string $tambahan = ''): string
    {
        $teks = 'Task ' . $task . ' (' . self::label($task) . ') ' . $waktu;

        if ($tambahan !== '') {
            $teks .= ' - ' . $tambahan;
        }

        // Sengaja hanya ditulis bila benar-benar mengirim. Baris tanpa embel-embel
        // BPJS berarti stempelnya dicatat lokal saja (mis. poli non-spesialis).
        if ($kodeBpjs !== null && $kodeBpjs !== '') {
            $teks .= ' - kirim BPJS ' . $kodeBpjs;
        }

        return $teks;
    }
}
