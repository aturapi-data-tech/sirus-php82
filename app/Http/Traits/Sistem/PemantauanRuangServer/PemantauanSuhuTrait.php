<?php

namespace App\Http\Traits\Sistem\PemantauanRuangServer;

use App\Support\OracleLob;
use App\Support\Options\SuhuRuangServerOptions;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Pemantauan Suhu Ruang Server (Akreditasi MRMIK 2.2).
 *
 * SATU BARIS = SATU PENGUKURAN. Tabelnya cuma dua kolom: PK + CLOB JSON berisi
 * satu pengukuran (waktu, suhu, status AC, kondisi, tindak lanjut, paraf).
 * Rancangan & alasannya: docs/ddl-pemantauan-suhu-ruang-server.sql.
 *
 * Tak ada lembar, tak ada array entri, tak ada lock — menambah baris tak pernah
 * menyentuh baris lain, jadi read-modify-write pun tak diperlukan. Dua modul
 * area Sistem lainnya (Akses Ruang Server & Pelaporan Down Time) berbentuk sama.
 *
 * Kop formulir (nama ruang, kapasitas AC, ambang suhu, dst.) tidak disimpan per
 * baris — nilainya tetap, jadi tinggal di SuhuRuangServerOptions dan dipasang
 * saat mencetak.
 */
trait PemantauanSuhuTrait
{
    /** Bendera encode SAMA dengan updateJsonRJ — termasuk UNESCAPED_SLASHES. */
    protected const JSON_FLAGS_SUHU = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR;

    /** Satu layar tak perlu memuat seluruh riwayat sekaligus. */
    protected const BATAS_RIWAYAT_SUHU = 500;

    /**
     * Satu pengukuran berdasarkan PK-nya.
     *
     * @return array{0: ?object, 1: array} baris + isi JSON-nya
     */
    protected function findSuhu(int $suhuNo): array
    {
        if ($suhuNo <= 0) {
            return [null, []];
        }

        $baris = DB::table('rstxn_suhuservers')->where('suhuserver_no', $suhuNo)->first();

        return $baris === null ? [null, []] : [$baris, $this->readJsonSuhu($baris)];
    }

    /**
     * Baca SUHUSERVER_JSON lewat OracleLob (anti ORA-01555/terpotong), decode
     * dengan JSON_THROW_ON_ERROR.
     *
     * JSON rusak DILEMPAR, bukan diam-diam jadi array kosong — baris yang tak
     * terbaca harus kelihatan, bukan tampil sebagai pengukuran kosong.
     *
     * @throws \JsonException
     */
    protected function readJsonSuhu(object $baris): array
    {
        $json = OracleLob::read(
            $baris->suhuserver_json ?? null,
            'rstxn_suhuservers',
            'suhuserver_no',
            $baris->suhuserver_no,
            'suhuserver_json'
        );

        return $json === '' ? [] : json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * Simpan satu pengukuran — buat bila $suhuNo null, perbarui bila terisi.
     *
     * Tak ada lock dan tak ada read-modify-write: satu baris = satu pengukuran,
     * jadi tak ada isi bersama yang bisa saling tertimpa.
     *
     * @return int PK pengukuran
     *
     * @throws \JsonException
     */
    protected function simpanSuhu(?int $suhuNo, array $payload): int
    {
        $json = json_encode($payload, self::JSON_FLAGS_SUHU);

        if ($suhuNo !== null && $suhuNo > 0) {
            DB::table('rstxn_suhuservers')
                ->where('suhuserver_no', $suhuNo)
                ->update(['suhuserver_json' => $json]);

            return $suhuNo;
        }

        // NEXTVAL diambil lebih dulu supaya PK-nya bisa dikembalikan ke pemanggil.
        // Oracle tak punya "returning id" yang seragam lewat Query Builder.
        $nomorBaru = (int) DB::selectOne('SELECT SEQ_SUHUSERVERS.NEXTVAL AS val FROM DUAL')->val;

        DB::table('rstxn_suhuservers')->insert([
            'suhuserver_no' => $nomorBaru,
            'suhuserver_json' => $json,
        ]);

        return $nomorBaru;
    }

    /**
     * Daftar pengukuran, terbaru dulu.
     *
     * Penyaringan bulan & kata kunci dikerjakan di PHP karena tabelnya tak punya
     * kolom datar — lihat DDL soal konsekuensi keputusan itu.
     *
     * @param  ?string  $periode  'MM/YYYY'; null/kosong = semua
     * @return array{0: array<int, array>, 1: int} daftar (sudah dipotong) + total sebenarnya
     */
    protected function findRiwayatSuhu(?string $periode = null, string $kataKunci = ''): array
    {
        $kunci = mb_strtolower(trim($kataKunci));
        $daftar = [];

        foreach (DB::table('rstxn_suhuservers')->get() as $baris) {
            $isi = $this->readJsonSuhu($baris);

            if ($isi === []) {
                continue;
            }

            $catatan = $this->ringkasSuhu($baris, $isi);

            if (filled($periode) && $catatan['periode'] !== $periode) {
                continue;
            }

            if ($kunci !== '' && ! str_contains(mb_strtolower($catatan['teksCari']), $kunci)) {
                continue;
            }

            $daftar[] = $catatan;
        }

        // Terbaru dulu: yang dicari petugas hampir selalu pengukuran terakhir.
        // Waktu ada di CLOB dan berformat d/m/Y H:i:s — tak bisa diurut sebagai
        // string, jadi diubah ke timestamp; yang tak terbaca jatuh ke 0 dan PK
        // jadi pemutus supaya urutannya tetap pasti.
        usort($daftar, fn (array $kiri, array $kanan): int => $kanan['urut'] <=> $kiri['urut']
            ?: $kanan['suhuNo'] <=> $kiri['suhuNo']);

        $total = count($daftar);

        return [array_slice($daftar, 0, self::BATAS_RIWAYAT_SUHU), $total];
    }

    /** Satu baris tabel di layar & cetakan. */
    protected function ringkasSuhu(object $baris, array $isi): array
    {
        $waktu = (string) ($isi['waktu'] ?? '');
        $saat = SuhuRuangServerOptions::pecahWaktu($waktu);
        $statusAc = (string) ($isi['statusAc'] ?? '');
        $tindakLanjut = (string) ($isi['tindakLanjut'] ?? '');
        $paraf = (string) ($isi['paraf']['nama'] ?? '');
        $stempel = $this->stempelWaktuSuhu($waktu);

        return [
            'suhuNo' => (int) $baris->suhuserver_no,
            'waktu' => $waktu,
            'tanggal' => $saat['tanggal'],
            'jam' => $saat['jam'],
            'urut' => $stempel,
            // 'MM/YYYY' bulan pengukuran — bahan filter periode di layar list.
            'periode' => $stempel === 0 ? '' : Carbon::createFromTimestamp($stempel)->format('m/Y'),
            'suhu' => (string) ($isi['suhu'] ?? ''),
            'statusAc' => $statusAc,
            'statusAcLabel' => SuhuRuangServerOptions::labelStatusAc($statusAc),
            'kondisi' => (string) ($isi['kondisi'] ?? ''),
            'tindakLanjut' => $tindakLanjut,
            'paraf' => $paraf,
            'parafTanggal' => (string) ($isi['paraf']['tanggal'] ?? ''),
            'teksCari' => $waktu . ' ' . SuhuRuangServerOptions::labelStatusAc($statusAc)
                . ' ' . $tindakLanjut . ' ' . $paraf,
        ];
    }

    /** Rekap sebulan untuk kepala cetakan & layar list. */
    protected function rekapSuhu(array $daftar): array
    {
        $suhuList = array_values(array_filter(
            array_map(fn (array $catatan) => SuhuRuangServerOptions::angka($catatan['suhu']), $daftar),
            fn (?float $suhu) => $suhu !== null
        ));

        return [
            'jumlah' => count($daftar),
            'tidakNormal' => count(array_filter($daftar, fn (array $catatan) => $catatan['kondisi'] === 'TN')),
            'suhuMin' => $suhuList === [] ? null : min($suhuList),
            'suhuMax' => $suhuList === [] ? null : max($suhuList),
            // Rata-rata dibulatkan satu desimal — angka pengukurannya pun satu desimal.
            'suhuRata' => $suhuList === [] ? null : round(array_sum($suhuList) / count($suhuList), 1),
        ];
    }

    /**
     * Tabel sudah dipasang? Dipakai komponen untuk menampilkan pesan yang jelas
     * ketimbang ORA-00942 di layar. Di-cache supaya tak query tiap render.
     */
    protected function checkTabelSuhu(): bool
    {
        return Cache::remember('suhuserver.tabel.ada', 300, function () {
            try {
                DB::table('rstxn_suhuservers')->limit(1)->exists();

                return true;
            } catch (\Throwable) {
                return false;
            }
        });
    }

    /** 'd/m/Y H:i:s' -> timestamp; 0 bila tak terbaca. */
    private function stempelWaktuSuhu(string $waktu): int
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
