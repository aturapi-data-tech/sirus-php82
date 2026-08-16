<?php

namespace App\Http\Traits\BPJS;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;

use Exception;

/**
 * APOTEK ONLINE BPJS (apotek-rest) — klaim obat PRB, kronis belum stabil, kemoterapi.
 *
 * Layanan ini BERDIRI SENDIRI dari VClaim meski auth-nya sama persis: cons-id
 * didaftarkan terpisah untuk service Apotek (CID 6323, faskes 0184A012 IFRS MADINAH
 * TULUNGAGUNG), jadi kredensial VClaim yang jalan di modul lain akan ditolak di sini
 * dengan "Unauthorized! You are not registered for this service!". Karena itu env-nya
 * juga blok sendiri, APOTEK_*.
 *
 * NAMA HELPER SENGAJA BERAWALAN apotek: satu komponen bisa memakai ApotekTrait dan
 * VclaimTrait bersamaan, dan keduanya butuh signature()/stringDecrypt()/sendResponse()
 * dengan isi berbeda. Kalau namanya sama, PHP menolak trait-nya bentrok — dan kalaupun
 * lolos, yang terpakai belum tentu yang dimaksud (lihat feedback trait method collision
 * di repo ini). Pola method publiknya tetap meniru VclaimTrait: apotek_* standalone.
 *
 * Katalog resmi: Trust Mark → Katalog WS → Apotek.
 */
trait ApotekTrait
{
    /* ═══════════════════════════════════════════════════════════════════
     | Infrastruktur: tanda tangan, dekripsi, pencatatan
     ═══════════════════════════════════════════════════════════════════ */

    /**
     * Header BPJS + kunci dekripsi. Sama rumusnya dengan VClaim (HMAC-SHA256 atas
     * "consid&timestamp"), tapi memakai kredensial APOTEK_*.
     *
     * timestamp WAJIB UTC — BPJS menolak signature yang timestamp-nya bergeser jauh
     * dari jam server mereka, dan APP_TIMEZONE repo ini bukan UTC.
     */
    public static function apotekSignature(): array
    {
        $consId = env('APOTEK_CONS_ID');
        $secretKey = env('APOTEK_SECRET_KEY');
        $userKey = env('APOTEK_USER_KEY');

        date_default_timezone_set('UTC');
        $tStamp = strval(time() - strtotime('1970-01-01 00:00:00'));
        $signature = hash_hmac('sha256', $consId . '&' . $tStamp, $secretKey, true);

        return [
            'user_key' => $userKey,
            'x-cons-id' => $consId,
            'x-timestamp' => $tStamp,
            'x-signature' => base64_encode($signature),
            'decrypt_key' => $consId . $secretKey . $tStamp,
        ];
    }

    /** AES-256-CBC + LZString, persis mekanisme VClaim. */
    public static function apotekDecrypt(string $key, ?string $string): ?string
    {
        if ($string === null || $string === '') {
            return null;
        }

        $keyHash = hex2bin(hash('sha256', $key));
        $iv = substr($keyHash, 0, 16);
        $output = openssl_decrypt(base64_decode($string), 'AES-256-CBC', $keyHash, OPENSSL_RAW_DATA, $iv);
        if ($output === false) {
            return null;
        }

        return \LZCompressor\LZString::decompressFromEncodedURIComponent($output);
    }

    public static function apotekSendResponse($message, $data, $code = 200, $url = null, $requestTransferTime = null, $payload = null)
    {
        $response = [
            'response' => $data,
            'metadata' => ['message' => $message, 'code' => $code],
        ];

        self::apotekLog($code, $response, $url, $payload, $requestTransferTime);

        return response()->json($response, is_numeric($code) ? (int) $code : 200);
    }

    public static function apotekSendError($error, $errorMessages = [], $code = 404, $url = null, $requestTransferTime = null, $payload = null)
    {
        $response = ['metadata' => ['message' => $error, 'code' => $code]];
        if (!empty($errorMessages)) {
            $response['response'] = $errorMessages;
        }

        self::apotekLog($code, $response, $url, $payload, $requestTransferTime);

        return response()->json($response, is_numeric($code) ? (int) $code : 404);
    }

    private static function apotekLog($code, array $response, $url, $payload, $requestTransferTime): void
    {
        DB::table('web_log_status')->insert([
            'code' => $code,
            'date_ref' => Carbon::now(),
            'response' => json_encode($response),
            'http_req' => $url,
            'http_payload' => $payload,
            'requestTransferTime' => $requestTransferTime,
        ]);
    }

    /**
     * Terjemahkan respons mentah BPJS jadi bentuk baku repo.
     *
     * Dekripsi dibungkus TOLERAN: kalau hasilnya tidak bisa diurai, isi mentahnya
     * dikembalikan apa adanya, bukan dilempar sebagai error. Alasannya praktis —
     * tidak semua endpoint apotek terbukti mengenkripsi respons, dan menampilkan
     * "gagal" untuk data yang sebenarnya sampai jauh lebih menyesatkan daripada
     * menampilkan data yang belum rapi. Kalau nanti terbukti ada endpoint yang
     * memang polos, catat di sini.
     */
    public static function apotekResponse($response, array $signature, string $url, $requestTransferTime = null)
    {
        $payload = $response->transferStats?->getRequest()?->getBody()?->__toString();

        if ($response->failed()) {
            return self::apotekSendError(
                $response->reason(),
                $response->json('response') ?? $response->body(),
                $response->status(),
                $url,
                $requestTransferTime,
                $payload
            );
        }

        $code = $response->json('metaData.code') ?? $response->json('metadata.code');
        $pesan = $response->json('metaData.message') ?? $response->json('metadata.message');
        $isi = $response->json('response');

        if ((string) $code === '200' && is_string($isi)) {
            $terurai = json_decode((string) self::apotekDecrypt($signature['decrypt_key'], $isi), true);
            if ($terurai !== null) {
                $isi = $terurai;
            }
        }

        return self::apotekSendResponse($pesan, $isi, $code ?? $response->status(), $url, $requestTransferTime, $payload);
    }

    /** Panggilan GET baku — timeout pendek supaya BPJS mati tidak membekukan layar. */
    private static function apotekGet(string $endpoint)
    {
        $url = rtrim((string) env('APOTEK_URL'), '/') . '/' . ltrim($endpoint, '/');

        try {
            $signature = self::apotekSignature();
            $response = Http::timeout(8)->connectTimeout(3)
                ->withHeaders($signature)
                ->get($url);

            return self::apotekResponse($response, $signature, $url, $response->transferStats?->getTransferTime());
        } catch (Exception $e) {
            return self::apotekSendError($e->getMessage(), null, 408, $url, null);
        }
    }

    /**
     * Panggilan POST baku.
     * Content-Type x-www-form-urlencoded padahal isinya JSON — itu memang kehendak
     * BPJS (sama seperti VClaim RencanaKontrol/insert), bukan salah tulis.
     */
    private static function apotekPost(string $endpoint, array $data)
    {
        $url = rtrim((string) env('APOTEK_URL'), '/') . '/' . ltrim($endpoint, '/');

        try {
            $signature = self::apotekSignature();
            $signature['Content-Type'] = 'application/x-www-form-urlencoded';

            $response = Http::timeout(8)->connectTimeout(3)
                ->withHeaders($signature)
                ->post($url, $data);

            return self::apotekResponse($response, $signature, $url, $response->transferStats?->getTransferTime());
        } catch (Exception $e) {
            return self::apotekSendError($e->getMessage(), null, 408, $url, null);
        }
    }

    /**
     * Panggilan DELETE baku — BPJS mengirim kriteria hapus lewat BODY, bukan URL.
     * Laravel HTTP client mendukungnya; yang perlu diingat cuma Content-Type-nya
     * tetap x-www-form-urlencoded seperti POST.
     */
    private static function apotekDelete(string $endpoint, array $data)
    {
        $url = rtrim((string) env('APOTEK_URL'), '/') . '/' . ltrim($endpoint, '/');

        try {
            $signature = self::apotekSignature();
            $signature['Content-Type'] = 'application/x-www-form-urlencoded';

            $response = Http::timeout(8)->connectTimeout(3)
                ->withHeaders($signature)
                ->delete($url, $data);

            return self::apotekResponse($response, $signature, $url, $response->transferStats?->getTransferTime());
        } catch (Exception $e) {
            return self::apotekSendError($e->getMessage(), null, 408, $url, null);
        }
    }

    /* ═══════════════════════════════════════════════════════════════════
     | REFERENSI
     ═══════════════════════════════════════════════════════════════════ */

    /**
     * Daftar Obat DPHO — GET referensi/dpho
     *
     * Penanda yang menentukan alur di SIMRS: `prb`, `kronis`, `kemo` (string
     * "True"/"False", BUKAN boolean JSON), plus `harga` sebagai plafon klaim.
     */
    public static function apotek_referensi_dpho()
    {
        return self::apotekGet('referensi/dpho');
    }

    /**
     * Daftar Poli — GET referensi/poli/{kode atau nama}
     *
     * Parameternya bisa KODE ("INT") maupun NAMA ("Penyakit Dalam") — BPJS mencari
     * keduanya. Hasilnya mengisi POLIRSP saat menyimpan resep; kode poli BPJS ini
     * BUKAN poli_id lokal kita, jadi jangan disamakan.
     */
    public static function apotek_referensi_poli(string $cari)
    {
        $validator = Validator::make(
            ['cari' => $cari],
            ['cari' => 'required'],
            ['required' => ':attribute wajib diisi.'],
            ['cari' => 'Kode atau nama poli']
        );

        if ($validator->fails()) {
            return self::apotekSendError($validator->errors()->first(), null, 400);
        }

        return self::apotekGet('referensi/poli/' . rawurlencode($cari));
    }

    /**
     * Pencarian Fasilitas Kesehatan — GET referensi/ppk/{jenisFaskes}/{namaFaskes}
     * jenisFaskes: 1 = Faskes 1 (FKTP) · 2 = Faskes 2 / RS
     */
    public static function apotek_referensi_faskes($jenisFaskes, string $namaFaskes)
    {
        $validator = Validator::make(
            compact('jenisFaskes', 'namaFaskes'),
            ['jenisFaskes' => 'required|in:1,2', 'namaFaskes' => 'required'],
            ['required' => ':attribute wajib diisi.', 'in' => ':attribute hanya 1 (FKTP) atau 2 (RS).'],
            ['jenisFaskes' => 'Jenis faskes', 'namaFaskes' => 'Nama faskes']
        );

        if ($validator->fails()) {
            return self::apotekSendError($validator->errors()->first(), null, 400);
        }

        return self::apotekGet("referensi/ppk/{$jenisFaskes}/" . rawurlencode($namaFaskes));
    }

    /**
     * Setting Apotek — GET referensi/settingppk/read/{kodeApotek}
     *
     * Sumber identitas apoteker, kepala, verifikator & petugas yang dicetak di
     * berkas klaim. `checkstock` ("True"/"False") menentukan apakah BPJS ikut
     * memvalidasi stok saat obat disimpan — pengaruhnya nyata ke alur penyimpanan.
     * Default parameternya kode apotek kita sendiri (APOTEK_KDPPK).
     */
    public static function apotek_referensi_setting(?string $kodeApotek = null)
    {
        $kodeApotek = $kodeApotek ?: (string) env('APOTEK_KDPPK');

        if ($kodeApotek === '') {
            return self::apotekSendError('Kode apotek kosong — isi APOTEK_KDPPK di .env atau kirim parameternya.', null, 400);
        }

        return self::apotekGet('referensi/settingppk/read/' . rawurlencode($kodeApotek));
    }

    /** Daftar Spesialistik — GET referensi/spesialistik */
    public static function apotek_referensi_spesialistik()
    {
        return self::apotekGet('referensi/spesialistik');
    }

    /**
     * Pencarian Obat — GET referensi/obat/{kdJenisObat}/{tglResep}/{filter}
     *
     * BEDA dari DPHO: yang ini menyaring menurut jenis obat DAN tanggal resep, jadi
     * harga yang keluar adalah harga yang berlaku pada tanggal itu — dipakai saat
     * memilih obat untuk satu resep. DPHO adalah katalog penuh tanpa konteks tanggal.
     *
     * kdJenisObat: 1 = PRB · 2 = Kronis belum stabil · 3 = Kemoterapi
     */
    public static function apotek_referensi_obat($kdJenisObat, string $tglResep, string $filter)
    {
        $validator = Validator::make(
            compact('kdJenisObat', 'tglResep', 'filter'),
            [
                'kdJenisObat' => 'required|in:1,2,3',
                'tglResep' => 'required|date_format:Y-m-d',
                'filter' => 'required',
            ],
            [
                'required' => ':attribute wajib diisi.',
                'in' => ':attribute tidak dikenal BPJS.',
                'date_format' => ':attribute harus format Y-m-d.',
            ],
            [
                'kdJenisObat' => 'Jenis obat (1 PRB / 2 Kronis / 3 Kemoterapi)',
                'tglResep' => 'Tanggal resep',
                'filter' => 'Kata kunci pencarian',
            ]
        );

        if ($validator->fails()) {
            return self::apotekSendError($validator->errors()->first(), null, 400);
        }

        return self::apotekGet("referensi/obat/{$kdJenisObat}/{$tglResep}/" . rawurlencode($filter));
    }

    /* ═══════════════════════════════════════════════════════════════════
     | SEP
     ═══════════════════════════════════════════════════════════════════ */

    /**
     * Data No Kunjungan/SEP — GET sep/{noSep}
     *
     * Titik awal seluruh alur: dari SEP asal inilah resep apotek dibuat. Respons
     * bukan sekadar verifikasi peserta — ia MENGISI field resep berikutnya:
     *
     *   response.poli      → POLIRSP  pada apotek_resep_insert()
     *   response.noSep     → REFASALSJP (SEP asal)
     *   response.flagprb   → penentu KDJNSOBAT: "1" berarti peserta PRB, jadi
     *                        jenis obatnya 1 (PRB). Selain itu barulah dipilih
     *                        2 (kronis belum stabil) atau 3 (kemoterapi) menurut
     *                        keadaan klinis — flag ini yang memutuskan lebih dulu.
     *   response.namaprb   → nama program PRB, penjelas bagi petugas
     *   response.kodedokter → KdDokter (di contoh katalog bisa kosong)
     *
     * Karena itu memanggil ini lebih dulu bukan sekadar pengecekan: tanpa `poli`
     * dan `flagprb` dari sini, POLIRSP dan KDJNSOBAT hanya bisa ditebak.
     */
    public static function apotek_sep(string $noSep)
    {
        $validator = Validator::make(
            ['noSep' => $noSep],
            ['noSep' => 'required|digits:19'],
            ['required' => ':attribute wajib diisi.', 'digits' => ':attribute harus :digits digit.'],
            ['noSep' => 'Nomor SEP']
        );

        if ($validator->fails()) {
            return self::apotekSendError($validator->errors()->first(), null, 400);
        }

        return self::apotekGet('sep/' . $noSep);
    }

    /* ═══════════════════════════════════════════════════════════════════
     | RESEP
     ═══════════════════════════════════════════════════════════════════ */

    /**
     * Simpan Resep — POST sjpresep/v3/insert
     *
     * Field wajib menurut Trust Mark:
     *   TGLSJP, REFASALSJP (SEP asal), POLIRSP, KDJNSOBAT, NORESEP,
     *   IDUSERSJP, TGLRSP, TGLPELRSP, KdDokter, iterasi
     *
     * KDJNSOBAT: 1 = Obat PRB · 2 = Obat Kronis Belum Stabil · 3 = Obat Kemoterapi
     * iterasi  : 0 = Non Iterasi · 1 = Iterasi
     */
    public static function apotek_resep_insert(array $r)
    {
        $validator = Validator::make($r, [
            'TGLSJP' => 'required',
            'REFASALSJP' => 'required',
            'POLIRSP' => 'required',
            'KDJNSOBAT' => 'required|in:1,2,3',
            'NORESEP' => 'required',
            'IDUSERSJP' => 'required',
            'TGLRSP' => 'required',
            'TGLPELRSP' => 'required',
            'iterasi' => 'required|in:0,1',
        ], [
            'required' => ':attribute wajib diisi.',
            'in' => ':attribute tidak dikenal BPJS.',
        ], [
            // Nama field BPJS HURUF BESAR tanpa pemisah; tanpa label eksplisit Laravel
            // memecahnya jadi "n o r e s e p" dan pesannya jadi tak terbaca petugas.
            'TGLSJP' => 'Tanggal SJP',
            'REFASALSJP' => 'SEP asal',
            'POLIRSP' => 'Poli resep',
            'KDJNSOBAT' => 'Jenis obat (1 PRB / 2 Kronis / 3 Kemoterapi)',
            'NORESEP' => 'Nomor resep',
            'IDUSERSJP' => 'ID user SJP',
            'TGLRSP' => 'Tanggal resep',
            'TGLPELRSP' => 'Tanggal pelayanan resep',
            'iterasi' => 'Iterasi (0/1)',
        ]);

        if ($validator->fails()) {
            return self::apotekSendError($validator->errors()->first(), null, 400);
        }

        return self::apotekPost('sjpresep/v3/insert', $r);
    }

    /**
     * Hapus Resep — DELETE hapusresep
     *
     * Menghapus SELURUH SJP resep, berbeda dari apotek_pelayanan_hapus() yang cuma
     * mencabut satu baris obat. Kriterianya di body: nosjp + refasalsjp + noresep.
     *
     * Perhatikan ejaan: di sini semuanya HURUF KECIL (nosjp, refasalsjp, noresep),
     * padahal saat menyimpan resep field yang sama ditulis HURUF BESAR (NORESEP,
     * REFASALSJP). Menyalin nama field dari method simpan ke sini akan ditolak.
     */
    public static function apotek_resep_hapus(array $r)
    {
        $validator = Validator::make($r, [
            'nosjp' => 'required',
            'refasalsjp' => 'required',
            'noresep' => 'required',
        ], [
            'required' => ':attribute wajib diisi.',
        ], [
            'nosjp' => 'No. SJP apotek',
            'refasalsjp' => 'SEP asal',
            'noresep' => 'Nomor resep',
        ]);

        if ($validator->fails()) {
            return self::apotekSendError($validator->errors()->first(), null, 400);
        }

        return self::apotekDelete('hapusresep', $r);
    }

    /**
     * Daftar Resep — POST daftarresep
     *
     * Mencari resep milik apotek kita pada rentang tanggal. POST meski sifatnya
     * membaca — itu memang bentuk yang diminta katalog.
     *
     * JnsTgl menentukan tanggal MANA yang disaring, dan ini menentukan hasil:
     *   TGLPELSJP → tanggal pelayanan SJP (kapan obat dilayani)
     *   TGLRSP    → tanggal resep (kapan dokter menulis)
     * Salah pilih membuat resep yang ditulis akhir bulan tapi dilayani bulan
     * berikutnya masuk periode yang keliru saat rekap klaim.
     *
     * KdJnsObat: 0 semua · 1 PRB · 2 Kronis belum stabil · 3 Kemoterapi.
     * kdppk default APOTEK_KDPPK; boleh dikosongkan pemanggil.
     *
     * EJAAN FIELD CAMPUR dan itu memang kehendak BPJS: `kdppk` huruf kecil,
     * sisanya kapital campur (KdJnsObat, JnsTgl, TglMulai, TglAkhir). Jangan
     * "dirapikan" jadi seragam.
     */
    public static function apotek_resep_daftar(array $r)
    {
        $r['kdppk'] = $r['kdppk'] ?? (string) env('APOTEK_KDPPK');

        $validator = Validator::make($r, [
            'kdppk' => 'required',
            'KdJnsObat' => 'required|in:0,1,2,3',
            'JnsTgl' => 'required|in:TGLPELSJP,TGLRSP',
            'TglMulai' => 'required|date_format:Y-m-d H:i:s',
            'TglAkhir' => 'required|date_format:Y-m-d H:i:s|after_or_equal:TglMulai',
        ], [
            'required' => ':attribute wajib diisi.',
            'in' => ':attribute tidak dikenal BPJS.',
            'date_format' => ':attribute harus format Y-m-d H:i:s.',
            'after_or_equal' => ':attribute tidak boleh mendahului tanggal mulai.',
        ], [
            'kdppk' => 'Kode apotek (kdppk)',
            'KdJnsObat' => 'Jenis obat (0 semua / 1 PRB / 2 Kronis / 3 Kemoterapi)',
            'JnsTgl' => 'Jenis tanggal (TGLPELSJP atau TGLRSP)',
            'TglMulai' => 'Tanggal mulai',
            'TglAkhir' => 'Tanggal akhir',
        ]);

        if ($validator->fails()) {
            return self::apotekSendError($validator->errors()->first(), null, 400);
        }

        return self::apotekPost('daftarresep', $r);
    }

    /* ═══════════════════════════════════════════════════════════════════
     | OBAT
     ═══════════════════════════════════════════════════════════════════ */

    /**
     * Simpan Obat Non Racikan — POST obatnonracikan/v3/insert
     *
     * NOSJP = SJP apotek hasil apotek_resep_insert(), BUKAN SEP asal.
     * SIGNA1OBT/SIGNA2OBT = aturan pakai (berapa kali × berapa), JHO = jumlah hari obat.
     */
    public static function apotek_obat_nonracikan_insert(array $r)
    {
        $validator = Validator::make($r, [
            'NOSJP' => 'required',
            'NORESEP' => 'required',
            'KDOBT' => 'required',
            'NMOBAT' => 'required',
            'SIGNA1OBT' => 'required|numeric',
            'SIGNA2OBT' => 'required|numeric',
            'JMLOBT' => 'required|numeric',
            'JHO' => 'required|numeric',
        ], [
            'required' => ':attribute wajib diisi.',
            'numeric' => ':attribute harus angka.',
        ], [
            'NOSJP' => 'No. SJP apotek',
            'NORESEP' => 'Nomor resep',
            'KDOBT' => 'Kode obat DPHO',
            'NMOBAT' => 'Nama obat',
            'SIGNA1OBT' => 'Signa 1 (berapa kali)',
            'SIGNA2OBT' => 'Signa 2 (berapa banyak)',
            'JMLOBT' => 'Jumlah obat',
            'JHO' => 'Jumlah hari obat',
        ]);

        if ($validator->fails()) {
            return self::apotekSendError($validator->errors()->first(), null, 400);
        }

        return self::apotekPost('obatnonracikan/v3/insert', $r);
    }

    /**
     * Simpan Obat Racikan — POST obatracikan/v3/insert
     *
     * Bedanya dari non-racikan cuma DUA field, dan keduanya wajib:
     *   JNSROBT    penanda grup racikan, mis. "R.01". SEMUA bahan dalam satu racikan
     *              dikirim satu per satu dengan JNSROBT yang SAMA — itulah yang
     *              menyatukan mereka jadi satu racikan di sisi BPJS. Salah/berbeda
     *              nilainya, bahan yang sama akan terhitung sebagai racikan terpisah.
     *   PERMINTAAN jumlah permintaan racikan (berapa bungkus/puyer diminta), berbeda
     *              dari JMLOBT yang jumlah bahannya.
     */
    public static function apotek_obat_racikan_insert(array $r)
    {
        $validator = Validator::make($r, [
            'NOSJP' => 'required',
            'NORESEP' => 'required',
            'JNSROBT' => 'required',
            'KDOBT' => 'required',
            'NMOBAT' => 'required',
            'SIGNA1OBT' => 'required|numeric',
            'SIGNA2OBT' => 'required|numeric',
            'PERMINTAAN' => 'required|numeric',
            'JMLOBT' => 'required|numeric',
            'JHO' => 'required|numeric',
        ], [
            'required' => ':attribute wajib diisi.',
            'numeric' => ':attribute harus angka.',
        ], [
            'NOSJP' => 'No. SJP apotek',
            'NORESEP' => 'Nomor resep',
            'JNSROBT' => 'Kode grup racikan (mis. R.01)',
            'KDOBT' => 'Kode obat DPHO',
            'NMOBAT' => 'Nama obat',
            'SIGNA1OBT' => 'Signa 1 (berapa kali)',
            'SIGNA2OBT' => 'Signa 2 (berapa banyak)',
            'PERMINTAAN' => 'Jumlah permintaan racikan',
            'JMLOBT' => 'Jumlah obat',
            'JHO' => 'Jumlah hari obat',
        ]);

        if ($validator->fails()) {
            return self::apotekSendError($validator->errors()->first(), null, 400);
        }

        return self::apotekPost('obatracikan/v3/insert', $r);
    }

    /**
     * Update Stok Obat — POST UpdateStokObat/updatestok
     *
     * AWAS DUA HAL:
     *  1. Nama field kode obat di sini `KDOBAT`, sedangkan di insert obat `KDOBT` —
     *     beda satu huruf. Salah satu ejaan akan diterima HTTP 200 tapi stoknya
     *     tidak pernah berubah, dan tak ada pesan yang menjelaskan.
     *  2. Path-nya mengandung huruf besar (`UpdateStokObat/updatestok`), tidak
     *     seperti endpoint apotek lain yang huruf kecil semua.
     *
     * Dipakai mendorong stok dari SIMRS ke Apotek Online; relevan hanya bila
     * `checkstock` pada Setting Apotek bernilai "True".
     */
    public static function apotek_update_stok(array $r)
    {
        $validator = Validator::make($r, [
            'KDOBAT' => 'required',
            'STOK' => 'required|numeric',
        ], [
            'required' => ':attribute wajib diisi.',
            'numeric' => ':attribute harus angka.',
        ], [
            'KDOBAT' => 'Kode obat DPHO (field KDOBAT, bukan KDOBT)',
            'STOK' => 'Jumlah stok',
        ]);

        if ($validator->fails()) {
            return self::apotekSendError($validator->errors()->first(), null, 400);
        }

        return self::apotekPost('UpdateStokObat/updatestok', $r);
    }

    /* ═══════════════════════════════════════════════════════════════════
     | PELAYANAN OBAT
     ═══════════════════════════════════════════════════════════════════ */

    /**
     * Daftar Pelayanan Obat — GET obat/daftar/{noSepApotek}
     *
     * Parameternya SJP APOTEK (hasil apotek_resep_insert), bukan SEP asal — meski
     * katalog menuliskannya "Nomor Kunjungan/SEP". Respons memuat detailsep +
     * listobat: itulah cara memastikan obat yang kita kirim benar-benar tercatat.
     */
    public static function apotek_pelayanan_daftar(string $noSepApotek)
    {
        $validator = Validator::make(
            ['noSepApotek' => $noSepApotek],
            ['noSepApotek' => 'required'],
            ['required' => ':attribute wajib diisi.'],
            ['noSepApotek' => 'No. SJP apotek']
        );

        if ($validator->fails()) {
            return self::apotekSendError($validator->errors()->first(), null, 400);
        }

        return self::apotekGet('obat/daftar/' . rawurlencode($noSepApotek));
    }

    /**
     * Riwayat Pelayanan Obat — GET riwayatobat/{tglAwal}/{tglAkhir}/{noKartu}
     *
     * Riwayat per PESERTA lintas SJP — dipakai memeriksa apakah obat kronis yang
     * sama sudah pernah ditebus pada periode itu, sebelum resep baru dikirim.
     */
    public static function apotek_pelayanan_riwayat(string $tglAwal, string $tglAkhir, string $noKartu)
    {
        $validator = Validator::make(
            compact('tglAwal', 'tglAkhir', 'noKartu'),
            [
                'tglAwal' => 'required|date_format:Y-m-d',
                'tglAkhir' => 'required|date_format:Y-m-d|after_or_equal:tglAwal',
                'noKartu' => 'required|digits:13',
            ],
            [
                'required' => ':attribute wajib diisi.',
                'date_format' => ':attribute harus format Y-m-d.',
                'after_or_equal' => ':attribute tidak boleh mendahului tanggal awal.',
                'digits' => ':attribute harus :digits digit.',
            ],
            ['tglAwal' => 'Tanggal awal', 'tglAkhir' => 'Tanggal akhir', 'noKartu' => 'Nomor kartu peserta']
        );

        if ($validator->fails()) {
            return self::apotekSendError($validator->errors()->first(), null, 400);
        }

        return self::apotekGet("riwayatobat/{$tglAwal}/{$tglAkhir}/{$noKartu}");
    }

    /**
     * Hapus Pelayanan Obat — DELETE pelayanan/obat/hapus/
     *
     * Menghapus SATU BARIS obat dari sebuah SJP apotek, bukan seluruh resepnya.
     * Kriterianya dikirim di body: nosepapotek + noresep + kodeobat + tipeobat.
     *
     * `tipeobat` di contoh katalog bernilai "N" (non-racikan). Nilai untuk racikan
     * TIDAK tertulis di katalog — jangan menebak "R" tanpa memastikan, karena salah
     * tipe berarti baris yang dimaksud tidak ketemu dan BPJS membalas seolah tak
     * ada yang perlu dihapus.
     *
     * Perhatikan garis miring di ujung path — ditulis begitu di katalog.
     */
    public static function apotek_pelayanan_hapus(array $r)
    {
        $validator = Validator::make($r, [
            'nosepapotek' => 'required',
            'noresep' => 'required',
            'kodeobat' => 'required',
            'tipeobat' => 'required',
        ], [
            'required' => ':attribute wajib diisi.',
        ], [
            'nosepapotek' => 'No. SJP apotek',
            'noresep' => 'Nomor resep',
            'kodeobat' => 'Kode obat DPHO',
            'tipeobat' => 'Tipe obat (mis. N untuk non-racikan)',
        ]);

        if ($validator->fails()) {
            return self::apotekSendError($validator->errors()->first(), null, 400);
        }

        return self::apotekDelete('pelayanan/obat/hapus/', $r);
    }

    /* ═══════════════════════════════════════════════════════════════════
     | MONITORING & PRB
     ═══════════════════════════════════════════════════════════════════ */

    /**
     * Data Klaim — GET monitoring/klaim/{bulan}/{tahun}/{jenisObat}/{status}
     *
     * URUTAN PARAMETER: bulan DULU baru tahun — kebalikan dari PRB rekap peserta
     * di bawah yang tahun dulu. Tertukar tidak menghasilkan error, cuma data kosong.
     *
     * jenisObat: 0 semua · 1 PRB · 2 Kronis belum stabil · 3 Kemoterapi
     * status   : 1 belum diverifikasi · 2 sudah verifikasi
     *
     * Respons sudah berupa REKAP siap pakai, bukan daftar mentah:
     *   response.rekap.jumlahdata           jumlah SJP
     *   response.rekap.totalbiayapengajuan  total yang kita ajukan
     *   response.rekap.totalbiayasetuju     total yang disetujui BPJS
     *   response.rekap.listsep[]            rincian per SJP, masing-masing dengan
     *                                       biayapengajuan vs biayasetuju
     *
     * Selisih pengajuan vs setuju itulah angka yang dikejar saat verifikasi. Pada
     * status 1 (belum diverifikasi) `biayasetuju` wajar bernilai 0 — jangan
     * ditafsirkan sebagai klaim ditolak.
     */
    public static function apotek_monitoring_klaim($bulan, $tahun, $jenisObat = 0, $status = 1)
    {
        $validator = Validator::make(
            compact('bulan', 'tahun', 'jenisObat', 'status'),
            [
                'bulan' => 'required|numeric|between:1,12',
                'tahun' => 'required|digits:4',
                'jenisObat' => 'required|in:0,1,2,3',
                'status' => 'required|in:1,2',
            ],
            [
                'required' => ':attribute wajib diisi.',
                'between' => ':attribute harus 1-12.',
                'digits' => ':attribute harus :digits digit.',
                'in' => ':attribute tidak dikenal BPJS.',
                'numeric' => ':attribute harus angka.',
            ],
            ['bulan' => 'Bulan', 'tahun' => 'Tahun', 'jenisObat' => 'Jenis obat', 'status' => 'Status verifikasi']
        );

        if ($validator->fails()) {
            return self::apotekSendError($validator->errors()->first(), null, 400);
        }

        return self::apotekGet("monitoring/klaim/{$bulan}/{$tahun}/{$jenisObat}/{$status}");
    }

    /**
     * Rekap Peserta PRB — GET Prb/rekappeserta/tahun/{tahun}/bulan/{bulan}
     * Peserta yang BARU di-PRB-kan oleh RS pada bulan tersebut.
     *
     * URUTAN PARAMETER tahun DULU baru bulan — kebalikan dari monitoring klaim
     * di atas. Tertukar tidak error di sisi BPJS, cuma hasilnya kosong.
     *
     * EMPAT SIFAT DATA yang tidak disebut di katalog tapi terbaca dari contohnya:
     *  1. BARIS GANDA. Di contoh resmi, 16 baris ternyata hanya 8 peserta-tanggal
     *     unik — SETIAP baris muncul persis dua kali. Kalau ditampilkan apa adanya,
     *     rekapnya kelihatan dua kali lipat. Penyaring duplikat sebaiknya dipasang
     *     di pemanggil (kunci: NomorKaPst + TglSRB + Obat), bukan di sini, supaya
     *     data mentahnya tetap utuh untuk penelusuran.
     *  2. `TglSRB` berformat d/m/Y H:i:s — BEDA dari endpoint apotek lain yang
     *     memakai Y-m-d. Jangan diparsing dengan pola yang sama.
     *  3. `Obat` adalah SATU STRING berisi banyak obat dipisah koma, bukan array.
     *     Memecahnya dengan koma berisiko: nama obat sendiri mengandung koma,
     *     mis. "Analog Insulin Long Acting inj 100 UI/ml, flexpen 3 ml".
     *  4. `NamaPeserta` bisa TERSAMAR ("ZA*****") dan `DPJP` berupa "Tenaga Medis
     *     <id>", bukan nama dokter. Jangan dijadikan patokan pencocokan.
     */
    public static function apotek_prb_rekap_peserta($tahun, $bulan)
    {
        $validator = Validator::make(
            compact('tahun', 'bulan'),
            ['tahun' => 'required|digits:4', 'bulan' => 'required|numeric|between:1,12'],
            [
                'required' => ':attribute wajib diisi.',
                'digits' => ':attribute harus :digits digit.',
                'between' => ':attribute harus 1-12.',
                'numeric' => ':attribute harus angka.',
            ],
            ['tahun' => 'Tahun', 'bulan' => 'Bulan']
        );

        if ($validator->fails()) {
            return self::apotekSendError($validator->errors()->first(), null, 400);
        }

        return self::apotekGet("Prb/rekappeserta/tahun/{$tahun}/bulan/{$bulan}");
    }

    /* ═══════════════════════════════════════════════════════════════════
     | CAKUPAN KATALOG — LENGKAP per 16/08/2026
     |
     | Seluruh menu Apotek di Trust Mark sudah punya method:
     |   Referensi     dpho · poli · ppk · settingppk · spesialistik · obat
     |   Obat          non racikan · racikan · update stok
     |   Pelayanan Obat daftar · riwayat · hapus
     |   Resep         simpan · hapus · daftar
     |   SEP           cari no kunjungan/SEP
     |   Monitoring    data klaim
     |   PRB           rekap peserta
     |
     | Belum satu pun teruji ke BPJS: CID 6323 masih dibalas
     | "Unauthorized! Consumer ID is expired!" — menunggu diaktifkan BPJS.
     ═══════════════════════════════════════════════════════════════════ */
}
