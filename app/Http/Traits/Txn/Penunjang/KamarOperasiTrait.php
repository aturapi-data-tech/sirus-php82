<?php

namespace App\Http\Traits\Txn\Penunjang;

use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Rj\EmrRJTrait;
use App\Http\Traits\Txn\Ugd\EmrUGDTrait;
use App\Http\Traits\Txn\Ri\EmrRITrait;

/**
 * Guard & helper bersama modul Kamar Operasi.
 *
 * Modul ini dipecah per bagian seperti Administrasi RJ (shell + komponen anak
 * per tab). Semua anak menulis ke tabel yang sama dan harus memakai guard yang
 * sama persis — kalau disalin per komponen, satu anak bisa ketinggalan saat
 * aturannya berubah dan diam-diam melewati penguncian atau audit log.
 *
 * SUMBER LAYANAN (pola lab `lbtxn_checkuphdrs`): `rstxn_oks.status_rjri` +
 * `ref_no` adalah sumber kebenaran kunjungan induk — RJ/UGD merujuk `rj_no`,
 * RI merujuk `rihdr_no`. Kolom `rihdr_no` hanya diisi untuk RI, demi laporan
 * lama yang masih membacanya. Semua guard di bawah bercabang lewat SATU tempat
 * supaya aturan "boleh transfer / boleh batal" tidak berbeda antar komponen.
 *
 * Dipakai bersama EmrRJTrait/EmrUGDTrait/EmrRITrait (lock*Row + appendAdminLog*).
 */
trait KamarOperasiTrait
{
    use EmrRJTrait, EmrUGDTrait, EmrRITrait;

    /** Sumber layanan yang diakui — dipakai sebagai whitelist, bukan if/else. */
    public const SUMBER_OK = ['RJ', 'UGD', 'RI'];

    /** Tabel biaya tujuan transfer per sumber. */
    private const TABEL_BIAYA_OK = ['RJ' => 'rstxn_rjoks', 'UGD' => 'rstxn_ugdoks', 'RI' => 'rstxn_rioks'];

    /** Kolom FK kunjungan di tabel biaya masing-masing. */
    private const KOLOM_INDUK_BIAYA_OK = ['RJ' => 'rj_no', 'UGD' => 'rj_no', 'RI' => 'rihdr_no'];

    /** Label sumber untuk tampilan & kalimat toast/audit. */
    private const LABEL_SUMBER_OK = ['RJ' => 'Rawat Jalan', 'UGD' => 'Gawat Darurat', 'RI' => 'Rawat Inap'];

    /** Petugas yang boleh membuka & mengubah transaksi OK. */
    protected function isAllowedRoleOk(): bool
    {
        $user = auth()->user();

        return $user ? $user->hasAnyRole(['Admin', 'Manager Umum', 'Supervisor Penunjang', 'Perawat']) : false;
    }

    /** Batal transaksi = eskalasi ke atasan, sejalan dengan modul Laboratorium. */
    protected function isAllowedBatalOk(): bool
    {
        $user = auth()->user();

        return $user ? $user->hasAnyRole(['Admin', 'Supervisor Penunjang']) : false;
    }

    /**
     * Ambil baris rstxn_oks terkunci + pastikan masih boleh diubah.
     *
     * @throws \RuntimeException
     */
    protected function kunciBarisOk(string $okReg): object
    {
        $row = DB::table('rstxn_oks')->where('ok_reg', $okReg)->lockForUpdate()->first();

        if (!$row) {
            throw new \RuntimeException('Transaksi tidak ditemukan.');
        }

        if (($row->ok_status ?? 'A') !== 'A') {
            throw new \RuntimeException('Transaksi sudah selesai/dibatalkan — tidak bisa diubah.');
        }

        return $row;
    }

    /**
     * Audit log ke kunjungan induk, diarahkan sesuai sumber layanan.
     * Panggil DI DALAM transaksi — barisnya dikunci lebih dulu.
     */
    protected function catatLogOk(string $sumber, int $refNo, string $keterangan): void
    {
        if ($refNo <= 0 || !in_array($sumber, self::SUMBER_OK, true)) {
            return;
        }

        if ($sumber === 'RJ') {
            $this->lockRJRow($refNo);
            $this->appendAdminLogRJ($refNo, $keterangan, 'ADMIN');
        }

        if ($sumber === 'UGD') {
            $this->lockUGDRow($refNo);
            $this->appendAdminLogUGD($refNo, $keterangan, 'ADMIN');
        }

        if ($sumber === 'RI') {
            $this->lockRIRow($refNo);
            $this->appendAdminLogRI($refNo, $keterangan, 'ADMIN');
        }
    }

    /**
     * Bungkus transaksi + retry saat nomor PK direbut petugas lain.
     *
     * PK di modul ini (ok_reg, ok_no, okact_id, okobat_id, omlop_dtl) global dan
     * tanpa sequence, sementara Oracle menolak FOR UPDATE pada query agregat
     * (ORA-01786). Jadi tabrakan ditangani dengan mengulang seluruh transaksi
     * yang sudah rollback penuh.
     *
     * @param  callable  $aksi  dijalankan di dalam DB::transaction
     * @return bool             true bila berhasil; toast error sudah dikirim bila gagal
     */
    protected function jalankanDenganRetryOk(callable $aksi, string $pesanGagal): bool
    {
        for ($percobaan = 1; ; $percobaan++) {
            try {
                DB::transaction($aksi);

                return true;
            } catch (\RuntimeException $e) {
                $this->dispatch('toast', type: 'error', message: $e->getMessage());

                return false;
            } catch (\Illuminate\Database\QueryException $e) {
                if ($percobaan < 3 && str_contains($e->getMessage(), 'ORA-00001')) {
                    continue;
                }

                $this->dispatch('toast', type: 'error', message: $pesanGagal . ': ' . $e->getMessage());

                return false;
            } catch (\Exception $e) {
                $this->dispatch('toast', type: 'error', message: $pesanGagal . ': ' . $e->getMessage());

                return false;
            }
        }
    }

    /**
     * Status transaksi OK saat ini, langsung dari DB.
     * Anak komponen memakainya untuk menentukan mode baca/entry tanpa
     * bergantung pada prop yang bisa basi.
     */
    protected function statusOk(string $okReg): string
    {
        $status = DB::table('rstxn_oks')->where('ok_reg', $okReg)->value('ok_status');

        return $status === null || $status === '' ? 'A' : (string) $status;
    }

    /**
     * Sumber layanan + nomor kunjungan induk transaksi OK ini.
     *
     * Baris lama (sebelum kolom status_rjri ada) sudah di-backfill 'RI', tapi
     * NVL tetap dipasang: CHECK constraint tidak menangkap NULL, jadi baris
     * cacat tidak boleh diam-diam jatuh ke tabel biaya yang salah.
     *
     * @return array{sumber: string, refNo: int}
     */
    protected function sumberRefOk(string $okReg): array
    {
        $row = DB::table('rstxn_oks')->select('status_rjri', 'ref_no', 'rihdr_no')->where('ok_reg', $okReg)->first();

        if (!$row) {
            return ['sumber' => 'RI', 'refNo' => 0];
        }

        $sumber = strtoupper((string) ($row->status_rjri ?? ''));
        if (!in_array($sumber, self::SUMBER_OK, true)) {
            $sumber = 'RI';
        }

        $refNo = (int) ($row->ref_no ?? 0);
        if ($refNo <= 0 && $sumber === 'RI') {
            $refNo = (int) ($row->rihdr_no ?? 0);
        }

        return ['sumber' => $sumber, 'refNo' => $refNo];
    }

    /** Tabel biaya tujuan transfer untuk sumber ini. */
    protected function tabelBiayaOk(string $sumber): string
    {
        return self::TABEL_BIAYA_OK[$sumber] ?? self::TABEL_BIAYA_OK['RI'];
    }

    /** Nama kolom FK kunjungan di tabel biaya sumber ini. */
    protected function kolomIndukBiayaOk(string $sumber): string
    {
        return self::KOLOM_INDUK_BIAYA_OK[$sumber] ?? self::KOLOM_INDUK_BIAYA_OK['RI'];
    }

    /** Label sumber untuk tampilan & kalimat audit. */
    protected function labelSumberOk(string $sumber): string
    {
        return self::LABEL_SUMBER_OK[$sumber] ?? $sumber;
    }

    /**
     * Kunci baris kunjungan induk lalu kembalikan statusnya.
     * Panggil DI DALAM transaksi.
     */
    protected function kunciIndukOk(string $sumber, int $refNo): string
    {
        if ($sumber === 'RJ') {
            $this->lockRJRow($refNo);

            return strtoupper((string) DB::table('rstxn_rjhdrs')->where('rj_no', $refNo)->value('rj_status'));
        }

        if ($sumber === 'UGD') {
            $this->lockUGDRow($refNo);

            return strtoupper((string) DB::table('rstxn_ugdhdrs')->where('rj_no', $refNo)->value('rj_status'));
        }

        $this->lockRIRow($refNo);

        return strtoupper((string) DB::table('rstxn_rihdrs')->where('rihdr_no', $refNo)->value('ri_status'));
    }

    /**
     * Kunjungan induk masih boleh menerima / melepas biaya?
     *
     * RJ & UGD memakai `rj_status = 'A'` (belum dibayar di kasir) — sejalan
     * dengan modul Laboratorium yang juga hanya mengizinkan tulis selama 'A'.
     * RI memakai `ri_status = 'I'` (masih dirawat). Status kosong dianggap
     * aktif: baris lama tanpa status jangan ikut terkunci.
     */
    protected function indukAktifOk(string $sumber, string $status): bool
    {
        if ($status === '') {
            return true;
        }

        return $sumber === 'RI' ? $status === 'I' : $status === 'A';
    }

    /** Sebab terkuncinya kunjungan induk — kalimat lengkapnya disusun pemanggil. */
    protected function sebabIndukTerkunciOk(string $sumber, string $status): string
    {
        if ($sumber === 'RI') {
            return match ($status) {
                'P', 'L' => 'Pasien sudah pulang',
                'F' => 'Kunjungan rawat inap dibatalkan',
                default => 'Kunjungan rawat inap tidak aktif',
            };
        }

        return match ($status) {
            'L' => 'Kunjungan sudah dibayar di kasir',
            'I' => 'Pasien sudah dirawat inap',
            'F' => 'Kunjungan dibatalkan',
            default => 'Kunjungan ' . $this->labelSumberOk($sumber) . ' tidak aktif',
        };
    }
}
