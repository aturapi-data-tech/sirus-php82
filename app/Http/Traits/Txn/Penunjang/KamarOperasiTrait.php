<?php

namespace App\Http\Traits\Txn\Penunjang;

use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Ri\EmrRITrait;

/**
 * Guard & helper bersama modul Kamar Operasi.
 *
 * Modul ini dipecah per bagian seperti Administrasi RJ (shell + komponen anak
 * per tab). Semua anak menulis ke tabel yang sama dan harus memakai guard yang
 * sama persis — kalau disalin per komponen, satu anak bisa ketinggalan saat
 * aturannya berubah dan diam-diam melewati penguncian atau audit log.
 *
 * Dipakai bersama EmrRITrait (lockRIRow + appendAdminLogRI).
 */
trait KamarOperasiTrait
{
    use EmrRITrait;

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

    /** Audit log ke kunjungan induk. Panggil DI DALAM transaksi. */
    protected function catatLogOk(int $riHdrNo, string $keterangan): void
    {
        if ($riHdrNo <= 0) {
            return;
        }

        $this->lockRIRow($riHdrNo);
        $this->appendAdminLogRI($riHdrNo, $keterangan, 'ADMIN');
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

    /** rihdr_no kunjungan induk transaksi OK ini. */
    protected function riHdrNoOk(string $okReg): int
    {
        return (int) DB::table('rstxn_oks')->where('ok_reg', $okReg)->value('rihdr_no');
    }
}
