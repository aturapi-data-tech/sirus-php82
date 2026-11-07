<?php

namespace App\Support\GajiDokter;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Rekap pergerakan transaksi jasa dokter per bulan jasa — mesin data layar Monitoring Jasa Dokter.
 *
 * Sejak slip gaji dokter dihitung di SIAP (struktur gaji disetel di SIAP; SIRUS memasok transaksi
 * mentah lewat GET /api/siap/gaji-dokter/{tahun}/{bulan}/transaksi), tugas SIRUS di sini tinggal
 * memperlihatkan APA yang dipasok: baris RSVIEW_NEWDOCSALARIES per dokter, dikelompokkan per jalur
 * layanan, dibandingkan dengan bulan jasa sebelumnya. Tidak ada perhitungan gaji di kelas ini —
 * gaji pokok, potongan RS, PPh 21 seluruhnya urusan SIAP.
 *
 * Sumber barisnya sama persis dengan yang dikirim ke SIAP (GajiDokterLampiran::barisMassal), jadi
 * angka di layar ini = angka yang ditarik SIAP. Kalau SIAP mempertanyakan nominal seorang dokter,
 * layar ini yang dibuka — bukan slipnya.
 */
class MonitoringJasaDokter
{
    /**
     * GROUP_DOC view -> jalur layanan; urutan larik = urutan kolom di layar.
     * Kode grup diambil dari data nyata view (RJTRF/UGDTRF = pasien transfer antar unit,
     * RJK = klinik, RADxx = radiologi per jalur). Grup di luar daftar jatuh ke 'lain' supaya
     * nominalnya tetap terhitung di Total, bukan hilang diam-diam.
     */
    public const JALUR = [
        'rj' => ['label' => 'Rawat Jalan', 'grup' => ['RJ', 'RJTRF']],
        'ugd' => ['label' => 'UGD', 'grup' => ['UGD', 'UGDTRF']],
        'ri' => ['label' => 'Rawat Inap', 'grup' => ['RI']],
        'ok' => ['label' => 'Kamar Operasi', 'grup' => ['OK']],
        'klinik' => ['label' => 'Klinik', 'grup' => ['RJK']],
        'rad' => ['label' => 'Radiologi', 'grup' => ['RADRJ', 'RADUGD', 'RADRI']],
    ];

    /** Rekap bulan pembanding (bulan jasa sebelumnya) dicache selama ini — lihat pergerakan(). */
    public const CACHE_BULAN_LALU_MENIT = 360;

    public const KOLOM_CSV_REKAP = [
        'Dokter', 'Periode Jasa', 'Transaksi',
        'Rawat Jalan', 'UGD', 'Rawat Inap', 'Kamar Operasi', 'Klinik', 'Radiologi', 'Lain',
        'Total', 'Total Bulan Lalu', 'Selisih',
    ];

    public static function jalurDariGrup(string $groupDoc): string
    {
        foreach (self::JALUR as $kunci => $jalur) {
            if (in_array($groupDoc, $jalur['grup'], true)) {
                return $kunci;
            }
        }

        return 'lain';
    }

    /** "Juli 2026" — nama bulan Indonesia dari tabel GajiDokterLampiran, bukan translatedFormat (APP_LOCALE aplikasi 'en'). */
    public static function labelPeriode(string $tahunJasa, string $bulanJasa): string
    {
        return (GajiDokterLampiran::NAMA_BULAN[$bulanJasa] ?? $bulanJasa) . ' ' . $tahunJasa;
    }

    /** @return array{0: string, 1: string} [tahun, bulan] bulan jasa sebelum periode yang diberikan. */
    public static function bulanSebelumnya(string $tahunJasa, string $bulanJasa): array
    {
        $sebelumnya = Carbon::createFromDate((int) $tahunJasa, (int) $bulanJasa, 1)->subMonth();

        return [$sebelumnya->format('Y'), $sebelumnya->format('m')];
    }

    /**
     * Satu entri per dokter untuk SATU bulan jasa, kunci DR_ID.
     *
     * Satu kueri agregat per (dokter, grup) — bukan menarik baris per pasien lalu menjumlah di PHP:
     * sebulan ±16.000 baris, dan layar ini dibuka berulang. Baris per pasien baru ditarik saat
     * Rincian diminta (GajiDokterLampiran::susunPerDokter).
     *
     * @return Collection<string, array{dr_id: string, dr_name: string, transaksi: int, pasien: int, nominal: float, jalur: array<string, float>}>
     */
    public static function rekapPerDokter(string $tahunJasa, string $bulanJasa): Collection
    {
        $rows = DB::table('rsview_newdocsalaries as rv')
            ->select([
                'rv.dr_id',
                DB::raw('MAX(rv.dr_name) AS dr_name'),
                'rv.group_doc',
                DB::raw('SUM(rv.doc_nominal) AS nominal'),
                DB::raw('SUM(rv.jml_pasien) AS pasien'),
                DB::raw('COUNT(*) AS transaksi'),
            ])
            ->whereRaw("to_char(to_date(rv.doc_date, 'dd/mm/yyyy'), 'mm/yyyy') = ?", [$bulanJasa . '/' . $tahunJasa])
            ->groupBy('rv.dr_id', 'rv.group_doc')
            ->get();

        return $rows->groupBy(fn ($row) => (string) $row->dr_id)->map(function (Collection $perGrup, string $drId) {
            $jalur = array_fill_keys([...array_keys(self::JALUR), 'lain'], 0.0);
            foreach ($perGrup as $row) {
                $jalur[self::jalurDariGrup((string) $row->group_doc)] += (float) $row->nominal;
            }

            return [
                'dr_id' => $drId,
                'dr_name' => trim((string) $perGrup->first()->dr_name),
                'transaksi' => (int) $perGrup->sum('transaksi'),
                'pasien' => (int) $perGrup->sum('pasien'),
                'nominal' => (float) $perGrup->sum('nominal'),
                'jalur' => $jalur,
            ];
        });
    }

    /**
     * Rekap bulan ini DISANDINGKAN dengan bulan jasa sebelumnya — inilah "pergerakan"-nya.
     *
     * Dokter yang bertransaksi bulan lalu tetapi TIDAK bulan ini tetap muncul (nominal 0, bulan lalu
     * terisi): hilangnya seorang dokter dari pasokan SIAP justru pergerakan yang paling perlu dilihat,
     * dan tidak akan terlihat kalau daftarnya hanya diisi dari bulan ini.
     *
     * Satu kueri agregat atas view menyapu seluruh riwayat (±2,5 detik, filter to_char tidak terindeks).
     * Bulan yang dipilih selalu ditarik LIVE — itulah yang sedang dikoreksi kasir dan ditarik SIAP.
     * Bulan pembandingnya sudah lewat dan jarang berubah, jadi dicache di store BERKAS (bukan store
     * bawaan) selama CACHE_BULAN_LALU_MENIT; tanpa itu tiap muat halaman membayar dua kali sapuan.
     *
     * @return Collection<int, array{dr_id: string, dr_name: string, transaksi: int, pasien: int, nominal: float, jalur: array<string, float>, nominal_lalu: float, selisih: float, status: string}>
     */
    public static function pergerakan(string $tahunJasa, string $bulanJasa, string $cariDokter = ''): Collection
    {
        [$tahunLalu, $bulanLalu] = self::bulanSebelumnya($tahunJasa, $bulanJasa);
        $kini = self::rekapPerDokter($tahunJasa, $bulanJasa);
        $lalu = Cache::store('file')->remember(
            'monitoring-jasa-dokter:rekap:' . $tahunLalu . $bulanLalu,
            now()->addMinutes(self::CACHE_BULAN_LALU_MENIT),
            fn () => self::rekapPerDokter($tahunLalu, $bulanLalu),
        );
        $kata = strtoupper(trim($cariDokter));

        return $kini->keys()->merge($lalu->keys())->unique()->values()
            ->map(function (string $drId) use ($kini, $lalu) {
                $row = $kini[$drId] ?? [
                    'dr_id' => $drId, 'dr_name' => $lalu[$drId]['dr_name'], 'transaksi' => 0, 'pasien' => 0, 'nominal' => 0.0,
                    'jalur' => array_fill_keys([...array_keys(self::JALUR), 'lain'], 0.0),
                ];
                $row['nominal_lalu'] = (float) ($lalu[$drId]['nominal'] ?? 0.0);
                $row['selisih'] = $row['nominal'] - $row['nominal_lalu'];
                // baru = ada bulan ini, tidak ada bulan lalu; hilang = kebalikannya; tetap = dua-duanya ada.
                $row['status'] = !isset($lalu[$drId]) ? 'baru' : (!isset($kini[$drId]) ? 'hilang' : 'tetap');

                return $row;
            })
            ->when($kata !== '', fn (Collection $rows) => $rows->filter(fn ($row) => str_contains(strtoupper($row['dr_name']), $kata)))
            ->sortByDesc('nominal')
            ->values();
    }

    /**
     * Angka kepala layar dari hasil pergerakan() — dihitung di sini supaya CSV & layar membaca satu rumus.
     *
     * @param  Collection<int, array>  $rows
     * @return array{dokter: int, transaksi: int, pasien: int, nominal: float, nominalLalu: float, selisih: float, persen: ?float, baru: int, hilang: int}
     */
    public static function ringkasan(Collection $rows): array
    {
        $nominal = (float) $rows->sum('nominal');
        $nominalLalu = (float) $rows->sum('nominal_lalu');

        return [
            'dokter' => $rows->where('transaksi', '>', 0)->count(),
            'transaksi' => (int) $rows->sum('transaksi'),
            'pasien' => (int) $rows->sum('pasien'),
            'nominal' => $nominal,
            'nominalLalu' => $nominalLalu,
            'selisih' => $nominal - $nominalLalu,
            // null bila bulan lalu nol — persen dari nol tidak bermakna, layar menampilkan "—".
            'persen' => $nominalLalu != 0.0 ? ($nominal - $nominalLalu) / $nominalLalu * 100 : null,
            'baru' => $rows->where('status', 'baru')->count(),
            'hilang' => $rows->where('status', 'hilang')->count(),
        ];
    }

    /**
     * Rekap per dokter sebagai CSV siap Excel (pipih, satu baris per dokter, angka mentah).
     * Pola sama dengan GajiDokterLampiran::tulisCsv: BOM UTF-8, pemisah ';', tanpa baris "sep=;".
     *
     * @param  resource  $keluaran
     * @param  Collection<int, array>  $rows  hasil pergerakan()
     */
    public static function tulisCsvRekap($keluaran, Collection $rows, string $tahunJasa, string $bulanJasa): void
    {
        $periodeJasa = self::labelPeriode($tahunJasa, $bulanJasa);

        fwrite($keluaran, "\xEF\xBB\xBF");
        fputcsv($keluaran, self::KOLOM_CSV_REKAP, ';', '"', '\\');

        foreach ($rows as $row) {
            fputcsv($keluaran, [
                $row['dr_name'],
                $periodeJasa,
                $row['transaksi'],
                ...array_map(fn ($nilai) => (int) round($nilai), array_values($row['jalur'])),
                (int) round($row['nominal']),
                (int) round($row['nominal_lalu']),
                (int) round($row['selisih']),
            ], ';', '"', '\\');
        }
    }
}
