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
                // Nama RS perujuk TIDAK ada di Task — kotak masuk mengambilnya lewat
                // GET Organization. Disalin ke sini supaya daftar tunggu tak perlu
                // memanggil SATUSEHAT lagi cuma untuk menampilkan nama; kalau nanti
                // kosong, layar jatuh ke Org ID.
                'perujukNama' => (string) ($permintaan['perujukNama'] ?? ''),
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

        $this->lupakanJumlahRujukanMasukDitunggu();

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
     * Tandai satu janji rujukan sudah dipakai mendaftarkan pasien.
     *
     * Baris tidak dihapus dan tidak dipindah — yang berubah cuma node
     * 'pendaftaran'-nya, karena janji yang sudah terpakai tetap perlu terbaca
     * (dari nomor kunjungan inilah nanti Encounter.basedOn menunjuk balik ke
     * rujukan yang kita terima).
     *
     * READ-MODIFY-WRITE DI DALAM LOCK. Dua petugas bisa membuka daftar tunggu
     * yang sama lalu sama-sama menekan Daftarkan; yang kedua harus melihat
     * kunjungan yang sudah dibuat rekannya, bukan menimpanya. Karena itu
     * 'sudahAda' membawa nomor kunjungan yang menang supaya bisa disebut di
     * layar.
     *
     * @param  array{regNo: string, jenis: string, noKunjungan: int|string|null}  $pendaftaran
     * @return array{tersimpan: bool, sudahAda: bool, noKunjungan: int|string|null, pesan: string}
     */
    protected function tandaiRujukanMasukDidaftarkan(int $rujukanMasukNo, array $pendaftaran): array
    {
        $pengguna = auth()->user();

        try {
            $hasil = DB::transaction(function () use ($rujukanMasukNo, $pendaftaran, $pengguna): array {
                $baris = DB::table('rstxn_rujukanmasuks')
                    ->where('rujukanmasuk_no', $rujukanMasukNo)
                    ->lockForUpdate()
                    ->first();

                if (! $baris) {
                    return ['tersimpan' => false, 'sudahAda' => false, 'noKunjungan' => null, 'pesan' => 'Janji rujukan tidak ditemukan.'];
                }

                $isi = $this->readJsonRujukanMasuk($baris);

                if ($isi === []) {
                    return ['tersimpan' => false, 'sudahAda' => false, 'noKunjungan' => null, 'pesan' => 'Isi janji rujukan tidak terbaca.'];
                }

                if (filled($isi['pendaftaran']['regNo'] ?? '')) {
                    return [
                        'tersimpan' => false,
                        'sudahAda' => true,
                        'noKunjungan' => $isi['pendaftaran']['noKunjungan'] ?? null,
                        'pesan' => 'Janji rujukan ini sudah dipakai mendaftarkan pasien.',
                    ];
                }

                $isi['pendaftaran'] = [
                    'regNo' => (string) ($pendaftaran['regNo'] ?? ''),
                    'jenis' => (string) ($pendaftaran['jenis'] ?? ''),
                    'noKunjungan' => $pendaftaran['noKunjungan'] ?? null,
                    'waktu' => Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s'),
                    'oleh' => $pengguna->myuser_name ?? $pengguna->name ?? '',
                ];

                DB::table('rstxn_rujukanmasuks')
                    ->where('rujukanmasuk_no', $rujukanMasukNo)
                    ->update(['rujukanmasuk_json' => json_encode($isi, self::JSON_FLAGS_RUJUKAN_MASUK)]);

                return ['tersimpan' => true, 'sudahAda' => false, 'noKunjungan' => $pendaftaran['noKunjungan'] ?? null, 'pesan' => ''];
            });
        } catch (\Throwable $exception) {
            return ['tersimpan' => false, 'sudahAda' => false, 'noKunjungan' => null, 'pesan' => $exception->getMessage()];
        }

        $this->lupakanJumlahRujukanMasukDitunggu();

        return $hasil;
    }

    /**
     * Simpan rujukan resmi (ServiceRequest) yang baru dipungut ke janji.
     *
     * Ditulis sebagai node SENDIRI, bukan disusupkan ke `permintaan`: isi
     * `permintaan` adalah salinan apa adanya dari SATUSEHAT SAAT DISETUJUI, dan
     * mencampur data yang datang belakangan ke sana membuat salinan itu tak lagi
     * bisa dipercaya sebagai potret keputusan.
     *
     * @return array{tersimpan: bool, pesan: string}
     */
    protected function simpanRujukanResmiJanji(int $rujukanMasukNo, string $serviceRequestId, string $noRujukan): array
    {
        if (trim($serviceRequestId) === '') {
            return ['tersimpan' => false, 'pesan' => 'Id ServiceRequest kosong.'];
        }

        try {
            DB::transaction(function () use ($rujukanMasukNo, $serviceRequestId, $noRujukan): void {
                $baris = DB::table('rstxn_rujukanmasuks')
                    ->where('rujukanmasuk_no', $rujukanMasukNo)
                    ->lockForUpdate()
                    ->first();

                if (! $baris) {
                    throw new \RuntimeException('Janji rujukan tidak ditemukan.');
                }

                $isi = $this->readJsonRujukanMasuk($baris);

                if ($isi === []) {
                    throw new \RuntimeException('Isi janji rujukan tidak terbaca.');
                }

                $isi['rujukanResmi'] = [
                    'serviceRequestId' => trim($serviceRequestId),
                    'noRujukan' => trim($noRujukan),
                    'waktu' => Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s'),
                ];

                DB::table('rstxn_rujukanmasuks')
                    ->where('rujukanmasuk_no', $rujukanMasukNo)
                    ->update(['rujukanmasuk_json' => json_encode($isi, self::JSON_FLAGS_RUJUKAN_MASUK)]);
            });
        } catch (\Throwable $exception) {
            return ['tersimpan' => false, 'pesan' => $exception->getMessage()];
        }

        return ['tersimpan' => true, 'pesan' => ''];
    }

    /**
     * Tulis balik IHS ke pasien lokal yang baru saja dicocokkan manual.
     *
     * Inilah yang membuat cakupan PATIENT_UUID menambal sendiri: tiap rujukan
     * masuk yang pasiennya dicari manual menambah satu pemetaan, sehingga
     * rujukan berikutnya untuk pasien itu langsung ketemu.
     *
     * TIDAK PERNAH MENIMPA. Kolom yang sudah terisi nilai lain, atau IHS yang
     * sudah dipegang nomor RM lain, adalah tanda salah satu dari dua data itu
     * keliru — dan menimpanya diam-diam akan menyatukan dua orang berbeda.
     * Keduanya dikembalikan sebagai 'bentrok' supaya pemanggil bisa
     * memperingatkan petugas, bukan diam.
     *
     * @return array{tersimpan: bool, bentrok: bool, pesan: string}
     */
    protected function simpanPatientUuidPasien(string $regNo, string $ihs): array
    {
        $regNo = trim($regNo);
        $ihs = trim($ihs);

        if ($regNo === '' || $ihs === '') {
            return ['tersimpan' => false, 'bentrok' => false, 'pesan' => ''];
        }

        try {
            $pasien = DB::table('rsmst_pasiens')
                ->where('reg_no', $regNo)
                ->select('reg_no', 'reg_name', 'patient_uuid')
                ->first();

            if (! $pasien) {
                return ['tersimpan' => false, 'bentrok' => false, 'pesan' => 'Pasien tidak ditemukan.'];
            }

            $terpasang = trim((string) ($pasien->patient_uuid ?? ''));

            if ($terpasang === $ihs) {
                return ['tersimpan' => false, 'bentrok' => false, 'pesan' => ''];
            }

            if ($terpasang !== '') {
                return [
                    'tersimpan' => false,
                    'bentrok' => true,
                    'pesan' => "No. RM {$regNo} sudah terpetakan ke IHS lain ({$terpasang}).",
                ];
            }

            $pemilikLain = DB::table('rsmst_pasiens')
                ->where('patient_uuid', $ihs)
                ->where('reg_no', '!=', $regNo)
                ->value('reg_no');

            if ($pemilikLain) {
                return [
                    'tersimpan' => false,
                    'bentrok' => true,
                    'pesan' => "IHS {$ihs} sudah dipakai No. RM {$pemilikLain}.",
                ];
            }

            DB::table('rsmst_pasiens')->where('reg_no', $regNo)->update(['patient_uuid' => $ihs]);
        } catch (\Throwable $exception) {
            return ['tersimpan' => false, 'bentrok' => false, 'pesan' => $exception->getMessage()];
        }

        return ['tersimpan' => true, 'bentrok' => false, 'pesan' => ''];
    }

    /**
     * Berapa pasien yang sudah disetujui tapi belum tiba.
     *
     * Dipakai lencana tombol di layar pendaftaran, yang ikut ter-render tiap
     * kali daftar disaring/diganti halaman. Menghitungnya berarti menyapu CLOB
     * seluruh tabel, jadi angkanya di-cache; pencatatan persetujuan baru dan
     * pendaftaran yang selesai membuang cache-nya sendiri, sehingga jeda satu
     * menit hanya terjadi bila janji dicatat dari sesi lain.
     */
    protected function jumlahRujukanMasukDitunggu(): int
    {
        if (! $this->checkTabelRujukanMasuk()) {
            return 0;
        }

        return Cache::remember(
            'rujukanmasuk.ditunggu.jumlah',
            60,
            fn (): int => count($this->findRujukanMasukDisetujui(true)),
        );
    }

    protected function lupakanJumlahRujukanMasukDitunggu(): void
    {
        Cache::forget('rujukanmasuk.ditunggu.jumlah');
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
