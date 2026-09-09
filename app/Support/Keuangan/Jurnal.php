<?php

namespace App\Support\Keuangan;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Jurnal umum dibaca LANGSUNG dari tabel transaksi — pengganti view TKVIEW_ACCOUNTS
 * (dan turunannya _LABARUGI / _NERACA1) untuk laporan keuangan.
 *
 * Katalog cabangnya (JurnalCabang) dibangkitkan dari DDL view, jadi bentuk barisnya
 * SAMA dengan view: TXN_NAME, TXN_ACC, TXN_ACC_K, SHIFT, TXN_DATE, TXN_D, TXN_K, termasuk
 * baris cermin tiap transaksi. Bedanya, query dibangun PER AKUN:
 *   - cabang yang akunnya akun konfigurasi (tkacc_confacctxns) dan bukan akun yang diminta
 *     dibuang di PHP, sama sekali tidak dikirim ke Oracle;
 *   - cabang yang akunnya kolom tabel sumber diberi predikat `kolom = ?`;
 *   - predikat tanggal sargable ditanam di TIAP cabang (bukan di luar).
 * Hasilnya untuk akun kas hanya 12 cabang (24 baris) yang benar-benar dibaca.
 *
 * Konvensi sisi: baris `txn_acc = akun` adalah baris MILIK akun itu (txn_d/txn_k = debit/kredit
 * akun itu sendiri); baris `txn_acc_k = akun` adalah baris cermin milik akun lawan.
 */
final class Jurnal
{
    public const SISI_ACC  = 'txn_acc';
    public const SISI_ACCK = 'txn_acc_k';

    private static ?array $konfigurasi = null;

    /** conf_id => acc_id dari tkacc_confacctxns (di-cache satu request). */
    public static function konfigurasiAkun(): array
    {
        return self::$konfigurasi ??= DB::table('tkacc_confacctxns')
            ->pluck('acc_id', 'conf_id')
            ->map(fn ($v) => (string) $v)
            ->all();
    }

    private static function isKonfigurasi(string $expr): bool
    {
        return str_starts_with($expr, 'conf:');
    }

    private static function akunKonfigurasi(string $expr, array $konf): ?string
    {
        return $konf[substr($expr, 5)] ?? null;
    }

    /**
     * Baris jurnal satu akun pada rentang tanggal (inklusif).
     * $sisi: kolom yang harus sama dengan $accId — SISI_ACC (baris milik akun) atau SISI_ACCK (baris lawan).
     * Kolom hasil: txn_name, txn_acc, txn_acc_k, shift, txn_date, txn_d, txn_k.
     */
    public static function query(string $accId, string $sisi, string $dari, string $sampai): Builder
    {
        $konf  = self::konfigurasiAkun();
        $parts = [];
        $bind  = [];

        foreach (JurnalCabang::semua() as $c) {
            $utama = $sisi === self::SISI_ACCK ? $c['accK'] : $c['acc'];

            if (self::isKonfigurasi($utama) && self::akunKonfigurasi($utama, $konf) !== $accId) {
                continue; // akun konfigurasi cabang ini bukan akun yang diminta
            }

            // Ekspresi akun di select-list: konfigurasi → binding literal, kolom → apa adanya.
            $selBind = [];
            $accSql  = $c['acc'];
            if (self::isKonfigurasi($accSql)) { $selBind[] = self::akunKonfigurasi($accSql, $konf); $accSql = '?'; }
            $accKSql = $c['accK'];
            if (self::isKonfigurasi($accKSql)) { $selBind[] = self::akunKonfigurasi($accKSql, $konf); $accKSql = '?'; }

            $where     = [];
            $whereBind = [];
            if (!self::isKonfigurasi($utama)) {
                $where[]     = "{$utama} = ?";
                $whereBind[] = $accId;
            }
            $where[]     = "{$c['date']} >= TO_DATE(?,'YYYY-MM-DD') and {$c['date']} < TO_DATE(?,'YYYY-MM-DD') + 1";
            $whereBind[] = $dari;
            $whereBind[] = $sampai;
            if ($c['where'] !== '') {
                $where[] = "({$c['where']})";
            }

            $parts[] = "select {$c['name']} txn_name, {$accSql} txn_acc, {$accKSql} txn_acc_k, {$c['shift']} shift, "
                . "{$c['date']} txn_date, {$c['d']} txn_d, {$c['k']} txn_k from {$c['from']} where " . implode(' and ', $where);

            array_push($bind, ...$selBind, ...$whereBind);
        }

        if ($parts === []) {
            // Akun tidak pernah muncul di jurnal → query kosong yang tetap sah.
            $parts[] = "select null txn_name, null txn_acc, null txn_acc_k, null shift, "
                . "cast(null as date) txn_date, 0 txn_d, 0 txn_k from dual where 1 = 0";
        }

        return DB::table(DB::raw('(' . implode("\nunion all\n", $parts) . ') j'))
            ->addBinding($bind, 'from');
    }

    /** Nama akun untuk kumpulan acc_id (query kecil terpisah; jangan join di atas jurnal). */
    public static function namaAkun(iterable $accIds): array
    {
        $ids = collect($accIds)->filter()->unique()->values()->all();

        return $ids === [] ? [] : DB::table('acmst_accounts')
            ->whereIn('acc_id', $ids)
            ->pluck('acc_name', 'acc_id')
            ->all();
    }
}
