<?php

namespace App\Support\Keuangan;

use Illuminate\Support\Facades\DB;

/**
 * Tutup buku tahunan SALDO AKUN (Kas & Neraca) ke TKTXN_SALDOAWALAKUNS — meniru tutup buku Forms 6i
 * (dibuktikan di data lokal 2026-11-18 untuk tutup 2023, 2024, 2025):
 *
 *   akun yang ikut  = akun yang sudah punya baris saldo awal tahun T  ∪  akun master bpl_flag 'BL'
 *                     (akun laba-rugi seperti 4.1J / 5.1L1 / 5.1L2 TIDAK dibuatkan baris)
 *   akun D          : SA_ACC_D(T+1) = SA_ACC_D(T) + Σ debit − Σ kredit tahun T
 *   akun K          : SA_ACC_K(T+1) = SA_ACC_K(T) + Σ kredit − Σ debit tahun T
 *   L01 LABA (RUGI) : PENYEIMBANG — Σ saldo awal akun D − Σ akun K tahun T+1 dibuat sama dengan tahun T
 *                     (dari data: selisih itu konstan 2023=2024 dan 2025=2026; pergeseran 2025 = isi
 *                     manual KAS 1.1A01 500.000.000 setelah tutup buku).
 *
 * Arus memakai Jurnal::arusPerAkunPerCabang (bukan UNION ALL raksasa — wrong result Oracle 10g).
 * Sisa beda terhadap baris Forms = koreksi manual / data yang berubah setelah tutup buku, sehingga
 * default proses TIDAK menimpa baris yang sudah ada (mode timpa harus dipilih sadar).
 *
 * Catatan: jurnal web punya 6 cabang sumber WEB (non-medis) yang tidak ada di view 6i — tutup buku
 * web dan Forms bisa berbeda sebesar transaksi non-medis yang dibayar lewat web.
 */
final class TutupBukuAkun
{
    public const AKUN_LABA = 'L01';

    /**
     * @return array<int, array{accId: string, nama: string, dk: string, awal: float, debit: float, kredit: float,
     *   baru: float, ada: bool, lama: ?float, laba: bool}>
     */
    public static function pratinjau(int $tahun): array
    {
        $thn = (string) $tahun;
        $berikut = (string) ($tahun + 1);

        $master = DB::table('acmst_accounts')->get(['acc_id', 'acc_name', 'dk_status', 'bpl_flag'])->keyBy(fn ($r) => (string) $r->acc_id);
        $awal = DB::table('tktxn_saldoawalakuns')->where('sa_year', $thn)->get()->keyBy(fn ($r) => (string) $r->acc_id);
        $tersimpan = DB::table('tktxn_saldoawalakuns')->where('sa_year', $berikut)->get()->keyBy(fn ($r) => (string) $r->acc_id);

        $ids = $awal->keys()
            ->merge($master->filter(fn ($m) => $m->bpl_flag === 'BL')->keys())
            ->map(fn ($id) => (string) $id)
            ->unique()->sort()->values()->all();

        $arus = Jurnal::arusPerAkunPerCabang($ids, "{$thn}-01-01", "{$thn}-12-31");

        $baris = [];
        $selisihDK = 0.0; // perubahan (Σ D − Σ K) dari akun selain L01 → diserap L01
        foreach ($ids as $id) {
            $dk = ($master[$id]->dk_status ?? 'D') === 'K' ? 'K' : 'D';
            $a = $awal[$id] ?? null;
            $nilaiAwal = (float) ($dk === 'D' ? ($a->sa_acc_d ?? 0) : ($a->sa_acc_k ?? 0));
            $d = $arus[$id]['debit'] ?? 0.0;
            $k = $arus[$id]['kredit'] ?? 0.0;
            $nilaiBaru = $dk === 'D' ? $nilaiAwal + $d - $k : $nilaiAwal + $k - $d;

            if ($id !== self::AKUN_LABA) {
                $selisihDK += $dk === 'D' ? ($nilaiBaru - $nilaiAwal) : -($nilaiBaru - $nilaiAwal);
            }

            $s = $tersimpan[$id] ?? null;
            // Akun neraca tanpa baris, tanpa arus, bernilai 0 → Forms tidak membuatkan baris; ikut.
            if ($a === null && $s === null && abs($d) < 0.01 && abs($k) < 0.01 && abs($nilaiAwal) < 0.01) {
                continue;
            }
            $baris[$id] = [
                'accId' => $id,
                'nama' => (string) ($master[$id]->acc_name ?? ''),
                'dk' => $dk,
                'awal' => round($nilaiAwal, 2),
                'debit' => round($d, 2),
                'kredit' => round($k, 2),
                'baru' => round($nilaiBaru, 2),
                'ada' => $s !== null,
                'lama' => $s !== null ? round((float) ($dk === 'D' ? $s->sa_acc_d : $s->sa_acc_k), 2) : null,
                'laba' => $id === self::AKUN_LABA,
            ];
        }

        // L01 = penyeimbang: menyerap seluruh perubahan akun lain (+ arus jurnal L01 sendiri, bila ada).
        if (isset($baris[self::AKUN_LABA])) {
            $l = &$baris[self::AKUN_LABA];
            $l['baru'] = round($l['baru'] + $selisihDK, 2);
            unset($l);
        }

        return array_values($baris);
    }

    public static function sama(?float $a, ?float $b): bool
    {
        return $a !== null && $b !== null && abs($a - $b) < 0.01;
    }

    /**
     * Tulis saldo awal T+1. $timpa = false → hanya akun yang belum punya baris; true → hitung ulang semua
     * (koreksi manual di baris tahun depan ikut tertimpa).
     *
     * @return array{tahunBaru: string, dibuat: int, diubah: int, sama: int, dilewati: int, sebelum: array<string, ?float>}
     */
    public static function proses(int $tahun, bool $timpa, ?array $pratinjau = null): array
    {
        $berikut = (string) ($tahun + 1);
        $baris = $pratinjau ?? self::pratinjau($tahun);

        return DB::transaction(function () use ($baris, $berikut, $timpa) {
            DB::table('tktxn_saldoawalakuns')->where('sa_year', $berikut)->lockForUpdate()->get(['acc_id']);

            $dibuat = $diubah = $sama = $dilewati = 0;
            $sebelum = [];
            foreach ($baris as $row) {
                $kolom = $row['dk'] === 'D'
                    ? ['sa_acc_d' => $row['baru'], 'sa_acc_k' => 0]
                    : ['sa_acc_d' => 0, 'sa_acc_k' => $row['baru']];

                if (!$row['ada']) {
                    DB::table('tktxn_saldoawalakuns')->insert(['sa_year' => $berikut, 'acc_id' => $row['accId']] + $kolom);
                    $dibuat++;
                    continue;
                }
                if (self::sama($row['lama'], $row['baru'])) {
                    $sama++;
                    continue;
                }
                if (!$timpa) {
                    $dilewati++;
                    continue;
                }
                $sebelum[$row['accId']] = $row['lama'];
                DB::table('tktxn_saldoawalakuns')->where('sa_year', $berikut)->where('acc_id', $row['accId'])->update($kolom);
                $diubah++;
            }

            return ['tahunBaru' => $berikut, 'dibuat' => $dibuat, 'diubah' => $diubah, 'sama' => $sama, 'dilewati' => $dilewati, 'sebelum' => $sebelum];
        });
    }
}
