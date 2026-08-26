<?php

namespace App\Http\Traits\Sistem\PelaporanDowntime;

use App\Support\Options\PelaporanDowntimeOptions;
use App\Support\Options\SuhuRuangServerOptions;
use App\Support\OracleLob;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Pelaporan Down Time SIMRS — formulir DT-01 (Akreditasi MRMIK 13.1).
 *
 * SATU BARIS = SATU KEJADIAN waktu henti. Tabelnya cuma dua kolom: PK + CLOB
 * JSON berisi satu laporan utuh (bagian A-E + paraf). Rancangan & alasannya:
 * docs/ddl-pelaporan-downtime.sql.
 *
 * Bentuknya sama dengan dua modul pemantauan ruang server — tak ada PERIODE,
 * tak ada lock, tak ada read-modify-write, tak ada TTD tersimpan. Yang khas di
 * sini: Bagian D (dampak per unit) panjangnya TETAP sembilan unit dari daftar
 * baku, selalu lengkap termasuk yang tak terdampak.
 */
trait PelaporanDowntimeTrait
{
    /** Bendera encode SAMA dengan updateJsonRJ — termasuk UNESCAPED_SLASHES. */
    protected const JSON_FLAGS_DOWNTIME = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR;

    /** Satu layar tak perlu memuat seluruh riwayat sekaligus. */
    protected const BATAS_RIWAYAT_DOWNTIME = 500;

    /**
     * Satu laporan berdasarkan PK-nya.
     *
     * @return array{0: ?object, 1: array} baris + isi JSON-nya
     */
    protected function findDowntime(int $downtimeNo): array
    {
        if ($downtimeNo <= 0) {
            return [null, []];
        }

        $baris = DB::table('rstxn_downtimes')->where('downtime_no', $downtimeNo)->first();

        return $baris === null ? [null, []] : [$baris, $this->readJsonDowntime($baris)];
    }

    /**
     * Baca DOWNTIME_JSON lewat OracleLob (anti ORA-01555/terpotong), decode
     * dengan JSON_THROW_ON_ERROR.
     *
     * JSON rusak DILEMPAR, bukan diam-diam jadi array kosong — laporan yang tak
     * terbaca harus kelihatan, bukan tampil sebagai laporan kosong.
     *
     * @throws \JsonException
     */
    protected function readJsonDowntime(object $baris): array
    {
        $json = OracleLob::read(
            $baris->downtime_json ?? null,
            'rstxn_downtimes',
            'downtime_no',
            $baris->downtime_no,
            'downtime_json'
        );

        return $json === '' ? [] : json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * Simpan satu laporan — buat bila $downtimeNo null, perbarui bila terisi.
     *
     * @return int PK laporan
     *
     * @throws \JsonException
     */
    protected function simpanDowntime(?int $downtimeNo, array $payload): int
    {
        $json = json_encode($payload, self::JSON_FLAGS_DOWNTIME);

        if ($downtimeNo !== null && $downtimeNo > 0) {
            DB::table('rstxn_downtimes')
                ->where('downtime_no', $downtimeNo)
                ->update(['downtime_json' => $json]);

            return $downtimeNo;
        }

        // NEXTVAL diambil lebih dulu supaya PK-nya bisa dikembalikan ke pemanggil.
        $nomorBaru = (int) DB::selectOne('SELECT SEQ_DOWNTIMES.NEXTVAL AS val FROM DUAL')->val;

        DB::table('rstxn_downtimes')->insert([
            'downtime_no' => $nomorBaru,
            'downtime_json' => $json,
        ]);

        return $nomorBaru;
    }

    /**
     * Daftar laporan, terbaru dulu.
     *
     * Penyaringan bulan & kata kunci dikerjakan di PHP karena tabelnya tak punya
     * kolom datar — lihat DDL soal konsekuensi keputusan itu.
     *
     * @param  ?string  $periode  'MM/YYYY'; null/kosong = semua
     * @return array{0: array<int, array>, 1: int} daftar (sudah dipotong) + total sebenarnya
     */
    protected function findRiwayatDowntime(?string $periode = null, string $kataKunci = ''): array
    {
        $kunci = mb_strtolower(trim($kataKunci));
        $daftar = [];

        foreach (DB::table('rstxn_downtimes')->get() as $baris) {
            $isi = $this->readJsonDowntime($baris);

            if ($isi === []) {
                continue;
            }

            $laporan = $this->ringkasDowntime($baris, $isi);

            if (filled($periode) && $laporan['periode'] !== $periode) {
                continue;
            }

            if ($kunci !== '' && ! str_contains(mb_strtolower($laporan['teksCari']), $kunci)) {
                continue;
            }

            $daftar[] = $laporan;
        }

        // Terbaru dulu: yang dicari petugas hampir selalu kejadian terakhir.
        usort($daftar, fn (array $kiri, array $kanan): int => $kanan['urut'] <=> $kiri['urut']
            ?: $kanan['downtimeNo'] <=> $kiri['downtimeNo']);

        $total = count($daftar);

        return [array_slice($daftar, 0, self::BATAS_RIWAYAT_DOWNTIME), $total];
    }

    /** Satu baris tabel di layar. */
    protected function ringkasDowntime(object $baris, array $isi): array
    {
        $kejadian = $isi['kejadian'] ?? [];
        $penanganan = $isi['penanganan'] ?? [];

        $waktuMulai = (string) ($kejadian['waktuMulai'] ?? '');
        $waktuPulih = (string) ($kejadian['waktuPulih'] ?? '');
        $mulai = SuhuRuangServerOptions::pecahWaktu($waktuMulai);
        $pulih = SuhuRuangServerOptions::pecahWaktu($waktuPulih);
        $stempel = $this->stempelWaktuDowntime($waktuMulai);

        $unitTerdampak = array_values(array_map(
            fn (array $dampak) => PelaporanDowntimeOptions::labelUnitDampak($dampak['unit'] ?? null),
            array_filter(
                array_filter($isi['dampak'] ?? [], 'is_array'),
                fn (array $dampak) => ! empty($dampak['manual'])
            )
        ));

        $noLog = (string) ($kejadian['noLog'] ?? '');
        // Diturunkan dari Bagian D, tak lagi diketik terpisah di Bagian A.
        $modulTerdampak = PelaporanDowntimeOptions::modulTerdampakDari($isi['dampak'] ?? []);
        $penyebab = (string) ($penanganan['penyebab'] ?? '');
        $jenis = (string) ($kejadian['jenis'] ?? '');
        $lingkup = (string) ($kejadian['lingkup'] ?? '');

        return [
            'downtimeNo' => (int) $baris->downtime_no,
            // Kalau No. Log tak diisi petugas, PK dipakai supaya laporannya tetap
            // punya sebutan yang bisa diucapkan saat rapat evaluasi.
            'noLog' => filled($noLog) ? $noLog : '#' . $baris->downtime_no,
            'jenis' => $jenis,
            'jenisLabel' => PelaporanDowntimeOptions::labelJenis($jenis),
            'lingkup' => $lingkup,
            'lingkupLabel' => PelaporanDowntimeOptions::labelLingkup($lingkup),
            'waktuMulai' => $waktuMulai,
            'waktuPulih' => $waktuPulih,
            'jamPulih' => $pulih['jam'],
            // Gangguan yang melewati tengah malam: layar menampilkan tanggal
            // pulihnya juga, kalau tidak "08:15 → 01:30" terbaca seolah mundur.
            'lintasHari' => filled($pulih['tanggal']) && $pulih['tanggal'] !== $mulai['tanggal'],
            'urut' => $stempel,
            'periode' => $stempel === 0 ? '' : Carbon::createFromTimestamp($stempel)->format('m/Y'),
            'durasi' => (string) ($kejadian['durasi'] ?? ''),
            'menitDurasi' => PelaporanDowntimeOptions::menitDurasi($kejadian),
            'belumPulih' => PelaporanDowntimeOptions::belumPulih($kejadian),
            'modulTerdampak' => $modulTerdampak,
            'penyebab' => $penyebab,
            'jumlahUnitManual' => count($unitTerdampak),
            'unitTerdampak' => $unitTerdampak,
            'paraf' => (string) ($isi['paraf']['nama'] ?? ''),
            'parafTanggal' => (string) ($isi['paraf']['tanggal'] ?? ''),
            'teksCari' => $noLog . ' ' . $modulTerdampak . ' ' . $penyebab . ' '
                . PelaporanDowntimeOptions::labelJenis($jenis) . ' ' . implode(' ', $unitTerdampak),
        ];
    }

    /**
     * Rekap satu periode: berapa kejadian, total menit henti, berapa yang belum
     * dinyatakan pulih.
     *
     * @param  array<int, array>  $daftar  hasil findRiwayatDowntime()
     */
    protected function rekapDowntime(array $daftar): array
    {
        $menitList = array_values(array_filter(
            array_map(fn (array $laporan) => $laporan['menitDurasi'], $daftar),
            fn (?int $menit) => $menit !== null
        ));

        return [
            'jumlah' => count($daftar),
            'totalMenit' => array_sum($menitList),
            // Yang durasinya belum bisa dihitung tidak ikut menambah total, jadi
            // jumlahnya dilaporkan terpisah supaya total tak terbaca sebagai lengkap.
            'tanpaDurasi' => count($daftar) - count($menitList),
            'belumPulih' => count(array_filter($daftar, fn (array $laporan) => $laporan['belumPulih'])),
            'tidakTerencana' => count(array_filter($daftar, fn (array $laporan) => $laporan['jenis'] === 'tidakTerencana')),
        ];
    }

    /**
     * Tabel sudah dipasang? Dipakai komponen untuk menampilkan pesan yang jelas
     * ketimbang ORA-00942 di layar. Di-cache supaya tak query tiap render.
     */
    protected function checkTabelDowntime(): bool
    {
        return Cache::remember('downtime.tabel.ada', 300, function () {
            try {
                DB::table('rstxn_downtimes')->limit(1)->exists();

                return true;
            } catch (\Throwable) {
                return false;
            }
        });
    }

    /** 'd/m/Y H:i:s' -> timestamp; 0 bila tak terbaca. */
    private function stempelWaktuDowntime(string $waktu): int
    {
        if (blank($waktu)) {
            return 0;
        }

        foreach (['d/m/Y H:i:s', 'd/m/Y H:i', 'd/m/Y'] as $format) {
            try {
                return Carbon::createFromFormat($format, trim($waktu))->getTimestamp();
            } catch (\Throwable) {
                continue;
            }
        }

        return 0;
    }
}
