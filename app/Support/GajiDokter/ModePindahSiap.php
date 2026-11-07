<?php

namespace App\Support\GajiDokter;

use Illuminate\Support\Facades\DB;

/**
 * Mode pemindahan penggajian dokter ke SIAP — dibaca langsung dari tabel pengaturan SIAP (SIAP.SIAPMST_PENGATURAN_GDOKS,
 * satu Oracle beda skema), jadi satu-satunya tempat menyetel adalah SIAP → Gaji Dokter → Struktur Gaji → Mode. SIRUS tinggal
 * mengikuti: panel Struktur Gaji Master Dokter jadi baca-saja saat struktur disetel di SIAP, Slip Gaji Dokter jadi arsip
 * baca saat slip dihitung SIAP. Bila tabel SIAP tak terbaca (belum ada / belum di-GRANT) dianggap tahap 0: SIRUS sumber semuanya.
 * Butuh hak baca sekali: GRANT SELECT ON siap.siapmst_pengaturan_gdoks TO sirus.
 */
class ModePindahSiap
{
    private static ?array $cache = null;

    /** @return array{struktur_gaji_dari_siap: bool, gaji_dokter_nonaktif: bool} */
    public static function ambil(): array
    {
        if (self::$cache === null) {
            $sumberStruktur = 'SIRUS';
            $mesinSlip = 'SIRUS';
            try {
                $skema = strtolower((string) config('services.siap.schema', 'SIAP'));
                $row = DB::table($skema . '.siapmst_pengaturan_gdoks')->where('id', 1)->first(['sumber_struktur', 'mesin_slip']);
                $sumberStruktur = strtoupper(trim((string) ($row->sumber_struktur ?? 'SIRUS')));
                $mesinSlip = strtoupper(trim((string) ($row->mesin_slip ?? 'SIRUS')));
            } catch (\Throwable) {
                // tabel SIAP belum ada / belum di-GRANT → tahap 0
            }
            self::$cache = ['struktur_gaji_dari_siap' => $sumberStruktur === 'SIAP', 'gaji_dokter_nonaktif' => $mesinSlip === 'SIAP'];
        }

        return self::$cache;
    }

    /** Struktur gaji dokter disetel di SIAP → panel Struktur Gaji SIRUS baca-saja, PUT struktur-gaji diterima. */
    public static function strukturDiSiap(): bool
    {
        return self::ambil()['struktur_gaji_dari_siap'];
    }

    /** Slip gaji dokter dihitung SIAP → Slip Gaji Dokter SIRUS hanya arsip baca. */
    public static function slipDiSiap(): bool
    {
        return self::ambil()['gaji_dokter_nonaktif'];
    }
}
