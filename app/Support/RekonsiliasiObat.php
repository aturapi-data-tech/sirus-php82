<?php

namespace App\Support;

use Carbon\Carbon;

/**
 * SUMBER TUNGGAL bentuk satu baris Rekonsiliasi Obat.
 *
 * Baris yang sama dipakai di EMPAT pintu masuk yang bentuk komponennya beda:
 *   - EMR UGD  → tab Anamnesa → Rekonsiliasi Obat   (anamnesa.rekonsiliasiObat)
 *   - EMR RI   → Pengkajian Dokter                   (pengkajianDokter.anamnesa.rekonsiliasiObat)
 *   - Pelayanan UGD → titik-3 → Rekonsiliasi Obat    (modal Apoteker)
 *   - Daftar RI     → titik-3 → Rekonsiliasi Obat    (modal Apoteker)
 *
 * Karena itu bentuk baris & stempel pencatat TIDAK boleh ditulis ulang di tiap
 * pintu: begitu satu pintu lupa menstempel petugas, barisnya jadi anonim dan
 * tidak ada yang tahu sampai dilihat di cetakan berbulan-bulan kemudian.
 */
final class RekonsiliasiObat
{
    /** Pilihan Rute — dipakai LOV di semua pintu masuk. */
    public const RUTE = ['Oral', 'Sublingual', 'IV', 'IM', 'SC', 'Inhalasi', 'Topikal', 'Rektal', 'Tetes Mata', 'Tetes Telinga', 'Lainnya'];

    /**
     * Baris BARU + stempel pencatat (user yang sedang login, jam sekarang).
     * Dipakai saat petugas menekan Tambah, di pintu mana pun.
     */
    public static function barisBaru(string $namaObat, string $dosis, string $rute, ?string $dibawaRanap = 'Tidak', ?string $digunakanRanap = 'Tidak', ?string $lanjutPulang = 'Tidak'): array
    {
        return [
            'namaObat' => trim($namaObat),
            'dosis' => trim($dosis),
            'rute' => $rute,
            'dibawaRanap' => self::yaAtauTidak($dibawaRanap),
            'digunakanRanap' => self::yaAtauTidak($digunakanRanap),
            'lanjutPulang' => self::yaAtauTidak($lanjutPulang),
            // Jejak pencatat menempel di entri (bukan cuma di audit log), supaya
            // ikut terbawa saat prefill ke RI dan bisa ditampilkan di cetakan.
            'tglRekonsiliasi' => Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s'),
            'petugasRekonsiliasi' => auth()->user()?->myuser_name ?? '',
            'petugasRekonsiliasiCode' => auth()->user()?->myuser_code ?? '',
        ];
    }

    /**
     * Baris dari SUMBER LAIN (mis. disalin UGD → RI) — bentuknya diseragamkan
     * tapi jejak pencatatnya dibawa apa adanya, TIDAK distempel ulang: barisnya
     * memang dicatat petugas UGD, bukan petugas RI yang membuka formnya.
     * Baris lama yang belum punya field jejak jadi '' (tampil '-').
     */
    public static function normalkanBaris(array $obat): array
    {
        return [
            'namaObat' => (string) ($obat['namaObat'] ?? ''),
            'dosis' => (string) ($obat['dosis'] ?? ''),
            'rute' => (string) ($obat['rute'] ?? ''),
            'dibawaRanap' => self::yaAtauTidak($obat['dibawaRanap'] ?? null),
            'digunakanRanap' => self::yaAtauTidak($obat['digunakanRanap'] ?? null),
            'lanjutPulang' => self::yaAtauTidak($obat['lanjutPulang'] ?? null),
            'tglRekonsiliasi' => (string) ($obat['tglRekonsiliasi'] ?? ''),
            'petugasRekonsiliasi' => (string) ($obat['petugasRekonsiliasi'] ?? ''),
            'petugasRekonsiliasiCode' => (string) ($obat['petugasRekonsiliasiCode'] ?? ''),
        ];
    }

    /** Daftar dari sumber lain: buang baris tanpa nama obat, seragamkan bentuknya. */
    public static function normalkanDaftar(?array $daftarObat): array
    {
        return collect($daftarObat ?? [])
            ->filter(fn($obat) => filled($obat['namaObat'] ?? null))
            ->map(fn($obat) => self::normalkanBaris((array) $obat))
            ->values()
            ->all();
    }

    /** Dedupe nama obat — case-insensitive, karena "Amlodipin" & "amlodipin" obat yang sama. */
    public static function sudahAda(?array $daftarObat, string $namaObat): bool
    {
        $namaObatDicari = strtolower(trim($namaObat));

        return collect($daftarObat ?? [])->contains(fn($obat) => strtolower(trim((string) ($obat['namaObat'] ?? ''))) === $namaObatDicari);
    }

    /** Gabung daftar sumber ke daftar tujuan — hanya nama obat yang belum ada. */
    public static function gabung(array $daftarTujuan, array $daftarSumber): array
    {
        foreach ($daftarSumber as $obat) {
            if (self::sudahAda($daftarTujuan, (string) ($obat['namaObat'] ?? ''))) {
                continue;
            }
            $daftarTujuan[] = $obat;
        }

        return array_values($daftarTujuan);
    }

    /**
     * Merge TIGA ARAH untuk daftar rekonsiliasi obat.
     *
     * Node ini punya lebih dari satu pintu tulis (EMR + modal Farmasi), dan form
     * EMR bisa terbuka belasan menit. Tanpa ini, siapa pun yang menekan Simpan
     * BELAKANGAN akan menimpa daftar dengan salinan yang sudah basi — baris yang
     * ditambahkan pintu lain hilang tanpa peringatan apa pun.
     *
     * @param array $basis      daftar saat form DIBUKA (titik cabang)
     * @param array $versiKita  daftar di layar sekarang (hasil tambah/hapus user ini)
     * @param array $versiDb    daftar TERBARU di database (mungkin sudah diubah pintu lain)
     *
     * Niat user ini dihitung sebagai SELISIH terhadap basis, lalu diterapkan ke
     * versiDb — bukan versiKita yang dipakai apa adanya. Jadi:
     *   - baris yang user ini hapus  -> tetap hilang
     *   - baris yang user ini tambah -> tetap masuk
     *   - baris yang pintu lain tambah selagi form terbuka -> IKUT SELAMAT
     *
     * Dicocokkan lewat namaObat (case-insensitive) — sama dengan aturan dedupe
     * saat menambah, jadi tidak ada baris yang dianggap beda hanya karena kapital.
     */
    public static function gabungTigaArah(array $basis, array $versiKita, array $versiDb): array
    {
        $kunci = fn(array $obat) => strtolower(trim((string) ($obat['namaObat'] ?? '')));

        $namaDiKita = array_map($kunci, $versiKita);
        $dihapusUserIni = collect($basis)
            ->reject(fn($obat) => in_array($kunci($obat), $namaDiKita, true))
            ->map($kunci)
            ->all();

        // Mulai dari versiDb (paling baru), buang yang memang dihapus user ini.
        $hasil = collect($versiDb)
            ->reject(fn($obat) => in_array($kunci($obat), $dihapusUserIni, true))
            ->values()
            ->all();

        // Lalu masukkan baris yang BENAR-BENAR ditambahkan user ini — yaitu yang ada
        // di versiKita tapi TIDAK ada di basis. Tanpa syarat "tidak ada di basis",
        // baris yang dihapus pintu lain akan hidup lagi hanya karena masih nongkrong
        // di layar user ini.
        foreach ($versiKita as $obat) {
            $namaObat = (string) ($obat['namaObat'] ?? '');
            if (self::sudahAda($basis, $namaObat) || self::sudahAda($hasil, $namaObat)) {
                continue;
            }
            $hasil[] = $obat;
        }

        return array_values($hasil);
    }

    /*
    |--------------------------------------------------------------------------
    | Ceklis "sudah direkonsiliasi Apoteker" — node SEBELAH daftar
    |--------------------------------------------------------------------------
    | Disimpan di key STATUS_KEY, bersebelahan dgn `rekonsiliasiObat`, bukan di
    | dalam daftar: daftar tetap array polos sehingga semua pembaca lama (cetak,
    | merge tiga arah, prefill UGD->RI) tidak perlu tahu.
    |
    | Daftar kosong itu AMBIGU — pasien memang tak memakai obat apa pun, atau
    | apoteker belum mengkaji. Ceklis inilah yang membedakannya:
    |   - belum diceklis, daftar kosong -> "Belum ada riwayat pemakaian obat."
    |   - sudah diceklis, daftar kosong -> "Tidak ada riwayat pemakaian obat."
    |
    | Hanya pintu Farmasi (modal titik-3 RI/UGD) yang boleh mengubahnya; form EMR
    | menampilkannya saja dan WAJIB mempertahankan nilai DB saat menimpa node
    | induk (lihat pertahankanStatus()).
    */

    public const STATUS_KEY = 'rekonsiliasiObatStatus';

    /** Ceklis DICENTANG oleh user yang sedang login, jam sekarang. */
    public static function statusDicentang(): array
    {
        return [
            'sudahDirekonsiliasi' => 'Ya',
            'tglRekonsiliasi' => Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s'),
            'petugasRekonsiliasi' => auth()->user()?->myuser_name ?? '',
            'petugasRekonsiliasiCode' => auth()->user()?->myuser_code ?? '',
        ];
    }

    /** Ceklis dibatalkan — key tetap ada supaya pembaca tak perlu cek isset. */
    public static function statusKosong(): array
    {
        return ['sudahDirekonsiliasi' => '', 'tglRekonsiliasi' => '', 'petugasRekonsiliasi' => '', 'petugasRekonsiliasiCode' => ''];
    }

    /** Seragamkan node status dari sumber mana pun (record lama = kosong). */
    public static function normalkanStatus(mixed $status): array
    {
        $status = is_array($status) ? $status : [];

        return [
            'sudahDirekonsiliasi' => self::yaAtauTidak($status['sudahDirekonsiliasi'] ?? null) === 'Ya' ? 'Ya' : '',
            'tglRekonsiliasi' => (string) ($status['tglRekonsiliasi'] ?? ''),
            'petugasRekonsiliasi' => (string) ($status['petugasRekonsiliasi'] ?? ''),
            'petugasRekonsiliasiCode' => (string) ($status['petugasRekonsiliasiCode'] ?? ''),
        ];
    }

    public static function sudahDirekonsiliasi(mixed $status): bool
    {
        return self::normalkanStatus($status)['sudahDirekonsiliasi'] === 'Ya';
    }

    /** Teks baris kosong tabel — dibedakan menurut ceklis apoteker. */
    public static function teksDaftarKosong(mixed $status): string
    {
        return self::sudahDirekonsiliasi($status) ? 'Tidak ada riwayat pemakaian obat.' : 'Belum ada riwayat pemakaian obat.';
    }

    /**
     * Teks status untuk tampilan/cetak: "Sudah — nama, tgl" atau "Belum".
     * Petugas ikut karena ceklis adalah pernyataan klinis yang harus ada penanggungnya.
     */
    public static function teksStatus(mixed $status): string
    {
        $status = self::normalkanStatus($status);
        if ($status['sudahDirekonsiliasi'] !== 'Ya') {
            return 'Belum direkonsiliasi Apoteker';
        }

        $jejak = collect([$status['petugasRekonsiliasi'], $status['tglRekonsiliasi']])->filter(fn($isi) => $isi !== '')->implode(', ');

        return 'Sudah direkonsiliasi Apoteker' . ($jejak !== '' ? ' (' . $jejak . ')' : '');
    }

    /**
     * Form EMR menimpa SELURUH node induk (anamnesa / pengkajianDokter) dengan
     * salinan layar yang bisa berumur belasan menit. Ceklis apoteker yang dibuat
     * lewat modal Farmasi selagi form terbuka akan ikut tertimpa — jadi nilai DB
     * yang dipakai, bukan salinan layar. Form EMR memang tidak pernah mengubahnya.
     *
     * @param array $nodeInduk  node yang sudah ditimpa salinan layar (by-ref)
     * @param mixed $statusDb   nilai STATUS_KEY dari DB, DIAMBIL SEBELUM ditimpa
     */
    public static function pertahankanStatus(array &$nodeInduk, mixed $statusDb): void
    {
        if (is_array($statusDb)) {
            $nodeInduk[self::STATUS_KEY] = $statusDb;
        } else {
            unset($nodeInduk[self::STATUS_KEY]);
        }
    }

    private static function yaAtauTidak(?string $nilai): string
    {
        return $nilai === 'Ya' ? 'Ya' : 'Tidak';
    }
}
