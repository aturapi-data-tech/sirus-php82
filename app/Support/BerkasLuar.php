<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

/**
 * Berkas Scan Luar — berkas dari luar dokumen SIRUS (surat rujukan, hasil pemeriksaan luar,
 * resume RS lain, kartu identitas, …) yang di-scan lalu di-upload di Modul Dokumen.
 *
 * Satu sumber untuk komponen upload per jalur (RJ/UGD/RI) dan penampil lintas kunjungan di
 * Rekam Medis Open: daftar jenis, folder simpan, dan alamat lihat berkas.
 *
 * Entri tersimpan di JSON kunjungan, node `berkasLuarRJ` / `berkasLuarUGD` / `berkasLuarRI`:
 *   file (nama berkas di folder) · namaAsli · jenis · tglBerkas (dd/mm/yyyy, boleh kosong)
 *   · keterangan · tglUpload · penanggungJawab {userLog, userLogDate, userLogCode}
 *
 * Folder mengikuti pola upload penunjang: ditulis ke `upload/...` (lokal), lalu program sync
 * di luar aplikasi menyalinnya ke share SMB `mount/...`. Lihat berkas mencoba mount dulu,
 * lalu jatuh ke salinan lokal bila sync belum jalan.
 */
final class BerkasLuar
{
    // FOLDER_MOUNT juga tertulis literal di whitelist route files.show (routes/web.php) — ubah keduanya.
    public const FOLDER_UPLOAD = 'upload/dokumen/emr/uploadBerkasLuar';
    public const FOLDER_MOUNT = 'mount/dokumen/emr/uploadBerkasLuar';

    /** Jenis berkas — urutan = urutan pilihan di form. */
    public const JENIS = [
        'Surat Rujukan Luar',
        'Hasil Pemeriksaan Luar',
        'Resume Medis RS Lain',
        'Kartu / Identitas',
        'Surat Keterangan',
        'Lainnya',
    ];

    /** Node JSON per jalur. */
    public const NODE = [
        'RJ' => 'berkasLuarRJ',
        'UGD' => 'berkasLuarUGD',
        'RI' => 'berkasLuarRI',
    ];

    /** Alamat route files.show untuk berkas ini, atau null bila berkasnya tidak ada di server. */
    public static function url(?string $file): ?string
    {
        if (empty($file)) {
            return null;
        }

        $namaBerkas = basename($file);
        $disk = Storage::disk('local');

        if ($disk->exists(self::FOLDER_MOUNT . '/' . $namaBerkas) || $disk->exists(self::FOLDER_UPLOAD . '/' . $namaBerkas)) {
            // Route files.show menerima path mount/... lalu sendiri jatuh ke upload/... bila belum ter-sync.
            return route('files.show', ['path' => self::FOLDER_MOUNT . '/' . $namaBerkas]);
        }

        return null;
    }

    /** true bila berkas gambar (ditampilkan dengan pratinjau gambar, bukan PDF). */
    public static function gambar(?string $file): bool
    {
        return (bool) preg_match('/\.(jpe?g|png)$/i', (string) $file);
    }
}
