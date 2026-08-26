<?php

namespace App\Http\Traits\Txn\RujukanMasuk;

use App\Support\OracleLob;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Janji rujukan masuk yang sudah kita SETUJUI — sisi faskes tujuan (SRBK).
 *
 * Menyetujui permintaan rujukan sebelumnya hanya menghasilkan satu PATCH Task ke
 * SATUSEHAT, tanpa jejak apa pun di basis data kita. Trait ini yang menyimpannya,
 * supaya petugas punya daftar "siapa yang ditunggu kedatangannya" dan supaya saat
 * pasiennya tiba ada bahan untuk mengisi pendaftaran + `Encounter.basedOn`.
 *
 * SATU BARIS = SATU PERMINTAAN DISETUJUI, BUKAN KUNJUNGAN. Menyetujui tidak
 * berarti pasien datang; pendaftaran RJ/UGD/RI dibuat terpisah saat pasiennya
 * benar-benar tiba, lalu nomornya dicatat balik ke node 'pendaftaran'.
 *
 * Rancangan tabel & alasan kolomnya: docs/ddl-rujukan-masuk-disetujui.sql.
 */
trait RujukanMasukTrait
{
    /** Bendera encode SAMA dengan updateJsonRJ — termasuk UNESCAPED_SLASHES. */
    protected const JSON_FLAGS_RUJUKAN_MASUK = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR;

    /**
     * Catat satu permintaan rujukan yang baru saja disetujui.
     *
     * IDEMPOTEN LEWAT BASIS DATA, bukan lewat pemeriksaan di PHP: TASK_ID unik,
     * dan pelanggarannya (ORA-00001) ditangkap sebagai "sudah ada" — bukan error.
     * Pemeriksaan "sudah ada belum?" di PHP bisa kalah balapan kalau dua petugas
     * menyetujui bersamaan; batasan basis data tidak.
     *
     * @return array{tersimpan: bool, sudahAda: bool, pesan: string}
     */
    protected function catatRujukanMasukDisetujui(array $permintaan): array
    {
        $taskId = trim((string) ($permintaan['taskId'] ?? ''));

        if ($taskId === '') {
            return ['tersimpan' => false, 'sudahAda' => false, 'pesan' => 'ID tugas rujukan kosong.'];
        }

        $pengguna = auth()->user();

        $isi = [
            // Salinan permintaan dari SATUSEHAT. Disalin, bukan dibaca ulang saat
            // dipakai: yang dilihat petugas saat menyetujui harus sama dengan yang
            // dipakai mendaftarkan pasien nanti, walau isinya di sana berubah.
            'permintaan' => [
                'taskId' => $taskId,
                'noPermintaan' => (string) ($permintaan['noPermintaan'] ?? ''),
                'pasienIhs' => (string) ($permintaan['pasienId'] ?? ''),
                'pasienNama' => (string) ($permintaan['pasienNama'] ?? ''),
                'perujukOrgId' => (string) ($permintaan['perujukOrgId'] ?? ''),
                'dokterPerujuk' => (string) ($permintaan['dokterPerujuk'] ?? ''),
                'encounterPerujukId' => (string) ($permintaan['encounterId'] ?? ''),
                'diagnosaId' => (string) ($permintaan['diagnosaId'] ?? ''),
                'rencanaId' => (string) ($permintaan['rencanaId'] ?? ''),
                'jalur' => (string) ($permintaan['jalur'] ?? ''),
                'layananKode' => (string) ($permintaan['layananKode'] ?? ''),
                'layananNama' => (string) ($permintaan['layananNama'] ?? ''),
                'deskripsi' => (string) ($permintaan['deskripsi'] ?? ''),
            ],
            'disetujui' => [
                'oleh' => $pengguna->myuser_name ?? $pengguna->name ?? '',
                'kode' => $pengguna->myuser_code ?? '',
                'waktu' => Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s'),
            ],
            // Kosong = pasien masih ditunggu.
            'pendaftaran' => ['regNo' => '', 'jenis' => '', 'noKunjungan' => null, 'waktu' => '', 'oleh' => ''],
        ];

        try {
            $nomorBaru = (int) DB::selectOne('SELECT SEQ_RUJUKANMASUKS.NEXTVAL AS val FROM DUAL')->val;

            DB::table('rstxn_rujukanmasuks')->insert([
                'rujukanmasuk_no' => $nomorBaru,
                'task_id' => $taskId,
                'rujukanmasuk_json' => json_encode($isi, self::JSON_FLAGS_RUJUKAN_MASUK),
            ]);
        } catch (QueryException $exception) {
            if (str_contains($exception->getMessage(), 'ORA-00001')) {
                return ['tersimpan' => false, 'sudahAda' => true, 'pesan' => 'Permintaan ini sudah pernah dicatat.'];
            }

            return ['tersimpan' => false, 'sudahAda' => false, 'pesan' => $exception->getMessage()];
        } catch (\Throwable $exception) {
            return ['tersimpan' => false, 'sudahAda' => false, 'pesan' => $exception->getMessage()];
        }

        return ['tersimpan' => true, 'sudahAda' => false, 'pesan' => ''];
    }

    /**
     * Baca RUJUKANMASUK_JSON. CLOB dibaca lewat OracleLob (anti ORA-01555),
     * decode dengan JSON_THROW_ON_ERROR — JSON rusak dilempar, bukan diam-diam
     * jadi array kosong.
     *
     * @throws \JsonException
     */
    protected function readJsonRujukanMasuk(object $baris): array
    {
        $json = OracleLob::read(
            $baris->rujukanmasuk_json ?? null,
            'rstxn_rujukanmasuks',
            'rujukanmasuk_no',
            $baris->rujukanmasuk_no,
            'rujukanmasuk_json'
        );

        return $json === '' ? [] : json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * Daftar janji rujukan masuk.
     *
     * @param  ?bool  $belumDidaftarkan  true = hanya yang pasiennya masih ditunggu,
     *                                   false = hanya yang sudah didaftarkan,
     *                                   null = semuanya
     * @return array<int, array>
     */
    protected function findRujukanMasukDisetujui(?bool $belumDidaftarkan = true): array
    {
        $daftar = [];

        foreach (DB::table('rstxn_rujukanmasuks')->get() as $baris) {
            $isi = $this->readJsonRujukanMasuk($baris);

            if ($isi === []) {
                continue;
            }

            $sudah = filled($isi['pendaftaran']['regNo'] ?? '');

            if ($belumDidaftarkan !== null && $sudah === $belumDidaftarkan) {
                continue;
            }

            $daftar[] = [
                'rujukanMasukNo' => (int) $baris->rujukanmasuk_no,
                'taskId' => (string) $baris->task_id,
                'permintaan' => $isi['permintaan'] ?? [],
                'disetujui' => $isi['disetujui'] ?? [],
                'pendaftaran' => $isi['pendaftaran'] ?? [],
                'sudahDidaftarkan' => $sudah,
                'urut' => $this->stempelWaktuRujukanMasuk((string) ($isi['disetujui']['waktu'] ?? '')),
            ];
        }

        // Terbaru dulu: yang ditunggu paling akhir biasanya yang sedang ditanyakan.
        usort($daftar, fn (array $kiri, array $kanan): int => $kanan['urut'] <=> $kiri['urut']
            ?: $kanan['rujukanMasukNo'] <=> $kiri['rujukanMasukNo']);

        return $daftar;
    }

    /**
     * Pasien lokal yang cocok dengan IHS permintaan.
     *
     * Pencocokan HANYA lewat PATIENT_UUID — nama tak bisa dipakai karena
     * Patient/<ihs> dari SATUSEHAT itu cangkang (name null, NIK di-mask). Baru
     * 4,7% pasien punya kolom itu terisi, jadi null adalah hasil yang WAJAR dan
     * pemanggil harus menyediakan pencarian manual, bukan menganggapnya error.
     */
    protected function findPasienDariIhs(string $ihs): ?object
    {
        if (trim($ihs) === '') {
            return null;
        }

        return DB::table('rsmst_pasiens')
            ->where('patient_uuid', trim($ihs))
            ->select('reg_no', 'reg_name', 'patient_uuid')
            ->first();
    }

    /**
     * Tabel sudah dipasang? Dipakai komponen untuk memilih diam-diam melewatkan
     * pencatatan ketimbang menggagalkan persetujuan yang SUDAH terkirim ke
     * SATUSEHAT. Di-cache supaya tak query tiap render.
     */
    protected function checkTabelRujukanMasuk(): bool
    {
        return Cache::remember('rujukanmasuk.tabel.ada', 300, function () {
            try {
                DB::table('rstxn_rujukanmasuks')->limit(1)->exists();

                return true;
            } catch (\Throwable) {
                return false;
            }
        });
    }

    /** 'd/m/Y H:i:s' -> timestamp; 0 bila tak terbaca. */
    private function stempelWaktuRujukanMasuk(string $waktu): int
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
