<?php

namespace App\Http\Traits\Sistem\PemantauanRuangServer;

use App\Support\Options\AksesRuangServerOptions;
use App\Support\Options\SuhuRuangServerOptions;
use App\Support\OracleLob;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Pemantauan Akses Ruang Server (Akreditasi MRMIK 2.2).
 *
 * SATU BARIS = SATU KUNJUNGAN. Tabelnya cuma dua kolom: PK + CLOB JSON berisi
 * satu kunjungan (waktu masuk & keluar, siapa, dari mana, untuk apa, pendamping,
 * perangkat yang dibawa, paraf). Rancangan & alasannya:
 * docs/ddl-pemantauan-akses-ruang-server.sql.
 *
 * Bentuknya kembar dengan PemantauanSuhuTrait — keduanya catatan lepas, bukan
 * dokumen berlembar, jadi tak ada PERIODE, tak ada lock, tak ada
 * read-modify-write. Formulir bulanannya dirakit saat cetak: kop dari
 * RuangServerOptions + baris bulan itu + garis tanda tangan kosong.
 */
trait PemantauanAksesTrait
{
    /** Bendera encode SAMA dengan updateJsonRJ — termasuk UNESCAPED_SLASHES. */
    protected const JSON_FLAGS_AKSES = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR;

    /** Satu layar tak perlu memuat seluruh riwayat sekaligus. */
    protected const BATAS_RIWAYAT_AKSES = 500;

    /**
     * Satu kunjungan berdasarkan PK-nya.
     *
     * @return array{0: ?object, 1: array} baris + isi JSON-nya
     */
    protected function findAkses(int $aksesNo): array
    {
        if ($aksesNo <= 0) {
            return [null, []];
        }

        $baris = DB::table('rstxn_aksesservers')->where('aksesserver_no', $aksesNo)->first();

        return $baris === null ? [null, []] : [$baris, $this->readJsonAkses($baris)];
    }

    /**
     * Baca AKSESSERVER_JSON lewat OracleLob (anti ORA-01555/terpotong), decode
     * dengan JSON_THROW_ON_ERROR.
     *
     * JSON rusak DILEMPAR, bukan diam-diam jadi array kosong — baris yang tak
     * terbaca harus kelihatan, bukan tampil sebagai kunjungan kosong.
     *
     * @throws \JsonException
     */
    protected function readJsonAkses(object $baris): array
    {
        $json = OracleLob::read(
            $baris->aksesserver_json ?? null,
            'rstxn_aksesservers',
            'aksesserver_no',
            $baris->aksesserver_no,
            'aksesserver_json'
        );

        return $json === '' ? [] : json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * Simpan satu kunjungan — buat bila $aksesNo null, perbarui bila terisi.
     *
     * Tak ada lock dan tak ada read-modify-write: satu baris = satu kunjungan.
     *
     * @return int PK kunjungan
     *
     * @throws \JsonException
     */
    protected function simpanAkses(?int $aksesNo, array $payload): int
    {
        $json = json_encode($payload, self::JSON_FLAGS_AKSES);

        if ($aksesNo !== null && $aksesNo > 0) {
            DB::table('rstxn_aksesservers')
                ->where('aksesserver_no', $aksesNo)
                ->update(['aksesserver_json' => $json]);

            return $aksesNo;
        }

        // NEXTVAL diambil lebih dulu supaya PK-nya bisa dikembalikan ke pemanggil.
        $nomorBaru = (int) DB::selectOne('SELECT SEQ_AKSESSERVERS.NEXTVAL AS val FROM DUAL')->val;

        DB::table('rstxn_aksesservers')->insert([
            'aksesserver_no' => $nomorBaru,
            'aksesserver_json' => $json,
        ]);

        return $nomorBaru;
    }

    /**
     * Daftar kunjungan, terbaru dulu.
     *
     * Penyaringan bulan & kata kunci dikerjakan di PHP karena tabelnya tak punya
     * kolom datar — lihat DDL soal konsekuensi keputusan itu.
     *
     * @param  ?string  $periode  'MM/YYYY'; null/kosong = semua
     * @return array{0: array<int, array>, 1: int} daftar (sudah dipotong) + total sebenarnya
     */
    protected function findRiwayatAkses(?string $periode = null, string $kataKunci = ''): array
    {
        $kunci = mb_strtolower(trim($kataKunci));
        $daftar = [];

        foreach (DB::table('rstxn_aksesservers')->get() as $baris) {
            $isi = $this->readJsonAkses($baris);

            if ($isi === []) {
                continue;
            }

            $catatan = $this->ringkasAkses($baris, $isi);

            if (filled($periode) && $catatan['periode'] !== $periode) {
                continue;
            }

            if ($kunci !== '' && ! str_contains(mb_strtolower($catatan['teksCari']), $kunci)) {
                continue;
            }

            $daftar[] = $catatan;
        }

        // Terbaru dulu: yang dicari petugas hampir selalu kunjungan terakhir.
        usort($daftar, fn (array $kiri, array $kanan): int => $kanan['urut'] <=> $kiri['urut']
            ?: $kanan['aksesNo'] <=> $kiri['aksesNo']);

        $total = count($daftar);

        return [array_slice($daftar, 0, self::BATAS_RIWAYAT_AKSES), $total];
    }

    /** Satu baris tabel di layar & cetakan. */
    protected function ringkasAkses(object $baris, array $isi): array
    {
        $waktu = (string) ($isi['waktu'] ?? '');
        $masuk = SuhuRuangServerOptions::pecahWaktu($waktu);
        $keluar = SuhuRuangServerOptions::pecahWaktu((string) ($isi['waktuKeluar'] ?? ''));
        $stempel = $this->stempelWaktuAkses($waktu);

        $nama = (string) ($isi['nama'] ?? '');
        $unitInstansi = (string) ($isi['unitInstansi'] ?? '');
        $jenisPengunjung = (string) ($isi['jenisPengunjung'] ?? '');
        $didampingi = (string) ($isi['didampingi'] ?? '');
        $keperluanTeks = AksesRuangServerOptions::keperluanTerbaca($isi);

        return [
            'aksesNo' => (int) $baris->aksesserver_no,
            'waktu' => $waktu,
            'waktuKeluar' => (string) ($isi['waktuKeluar'] ?? ''),
            'tanggal' => $masuk['tanggal'],
            'jamMasuk' => $masuk['jam'],
            'tanggalKeluar' => $keluar['tanggal'],
            'jamKeluar' => $keluar['jam'],
            // Kunjungan yang melewati tengah malam: layar menampilkan tanggal
            // keluarnya juga, kalau tidak "09:00 → 01:30" terbaca seolah mundur.
            'lintasHari' => filled($keluar['tanggal']) && $keluar['tanggal'] !== $masuk['tanggal'],
            'urut' => $stempel,
            'periode' => $stempel === 0 ? '' : Carbon::createFromTimestamp($stempel)->format('m/Y'),
            'nama' => $nama,
            'unitInstansi' => $unitInstansi,
            'jenisPengunjung' => $jenisPengunjung,
            'jenisLabel' => AksesRuangServerOptions::labelJenisPengunjung($jenisPengunjung),
            'keperluan' => $keperluanTeks,
            'membawaPerangkat' => (string) ($isi['membawaPerangkat'] ?? ''),
            'didampingi' => $didampingi,
            'catatan' => (string) ($isi['catatan'] ?? ''),
            'lama' => AksesRuangServerOptions::lamaKunjungan($isi),
            'masihDiDalam' => AksesRuangServerOptions::masihDiDalam($isi),
            'dariLuar' => AksesRuangServerOptions::wajibDidampingi($jenisPengunjung),
            'paraf' => (string) ($isi['paraf']['nama'] ?? ''),
            'parafTanggal' => (string) ($isi['paraf']['tanggal'] ?? ''),
            'teksCari' => $waktu . ' ' . $nama . ' ' . $unitInstansi . ' '
                . AksesRuangServerOptions::labelJenisPengunjung($jenisPengunjung) . ' '
                . $keperluanTeks . ' ' . $didampingi,
        ];
    }

    /** Rekap sebulan untuk kepala cetakan & layar list. */
    protected function rekapAkses(array $daftar): array
    {
        return [
            'jumlah' => count($daftar),
            'dariLuar' => count(array_filter($daftar, fn (array $catatan) => $catatan['dariLuar'])),
            'belumKeluar' => count(array_filter($daftar, fn (array $catatan) => $catatan['masihDiDalam'])),
            // Pengunjung dari luar yang tak tercatat pendampingnya — inilah temuan
            // yang dicari auditor, jadi dihitung terpisah bukan disembunyikan.
            'tanpaPendamping' => count(array_filter(
                $daftar,
                fn (array $catatan) => $catatan['dariLuar'] && blank($catatan['didampingi'])
            )),
        ];
    }

    /**
     * Tabel sudah dipasang? Dipakai komponen untuk menampilkan pesan yang jelas
     * ketimbang ORA-00942 di layar. Di-cache supaya tak query tiap render.
     */
    protected function checkTabelAkses(): bool
    {
        return Cache::remember('aksesserver.tabel.ada', 300, function () {
            try {
                DB::table('rstxn_aksesservers')->limit(1)->exists();

                return true;
            } catch (\Throwable) {
                return false;
            }
        });
    }

    /** 'd/m/Y H:i:s' -> timestamp; 0 bila tak terbaca. */
    private function stempelWaktuAkses(string $waktu): int
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
