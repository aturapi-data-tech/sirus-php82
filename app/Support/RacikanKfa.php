<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Menyiapkan grup racikan e-resep untuk dikirim ke SATUSEHAT sebagai compound.
 *
 * SATUSEHAT menuntut kode KFA PER BAHAN (Medication.ingredient[]), sedangkan JSON
 * e-resep tidak menyimpan kode KFA sama sekali — yang ada `productId` (kadang kosong)
 * dan `productName`. Kelas ini yang memetakannya, dari JSON saja:
 *
 *   1. `productId` terisi  → ambil product_id_satusehat dari immst_products.
 *   2. `productId` kosong  → cocokkan `productName` ke master obat, dan HANYA diterima
 *      bila ada TEPAT SATU produk ber-KFA dengan nama itu.
 *
 * Aturan (2): probe 2026-08-03 atas 200 kunjungan RJ menemukan 38 nama bahan tanpa
 * productId (393 baris) dan semuanya cocok persis satu di master — tapi begitu ada dua
 * produk bernama sama (beda kekuatan/merek), menebak berarti salah obat. Karena itu
 * kandidat ganda DITOLAK, bukan diambil yang pertama.
 *
 * Grup yang tidak lolos WAJIB dilaporkan pemanggil, jangan dibuang diam-diam
 * (lihat catatan App\Support\EresepJson).
 */
class RacikanKfa
{
    /**
     * Grup racikan siap-kirim beserta alasan untuk yang gagal.
     *
     * @return array<int, array{noRacikan:string, jumlahBahan:int, siap:bool, alasan:string,
     *                          bahanList:array<int, array{code:string, display:string}>}>
     */
    public static function grupList(array $data): array
    {
        $grupList = [];
        foreach (EresepJson::lembar($data) as $lembar) {
            foreach ($lembar['racikan'] as $noRacikan => $bahanList) {
                $grupList[] = ['noRacikan' => (string) $noRacikan, 'bahanList' => $bahanList];
            }
        }
        if ($grupList === []) {
            return [];
        }

        [$kfaByProductId, $kfaByNama] = self::petaKfa($grupList);

        $hasil = [];
        foreach ($grupList as $grup) {
            $bahanSiap = [];
            $gagal = [];

            foreach ($grup['bahanList'] as $bahan) {
                $productId = trim((string) ($bahan['productId'] ?? ''));
                $productName = trim((string) ($bahan['productName'] ?? ''));

                $master = $productId !== ''
                    ? ($kfaByProductId[$productId] ?? null)
                    : ($kfaByNama[mb_strtoupper($productName)] ?? null);

                if ($master === null) {
                    $gagal[] = $productName !== '' ? $productName : '(tanpa nama)';
                    continue;
                }

                $bahanSiap[] = ['code' => $master['code'], 'display' => $master['display'] ?: $productName];
            }

            $hasil[] = [
                'noRacikan' => $grup['noRacikan'],
                'jumlahBahan' => count($grup['bahanList']),
                'siap' => $gagal === [] && $bahanSiap !== [],
                'alasan' => $gagal === [] ? '' : 'bahan tanpa padanan KFA: ' . implode(', ', $gagal),
                'bahanList' => $bahanSiap,
            ];
        }

        return $hasil;
    }

    /** Ringkasan untuk kartu: berapa grup siap kirim, berapa yang tidak. */
    public static function ringkas(array $data): array
    {
        $grupList = self::grupList($data);
        $siap = array_values(array_filter($grupList, fn($grup) => $grup['siap']));

        return [
            'total' => count($grupList),
            'siap' => count($siap),
            'takSiap' => count($grupList) - count($siap),
        ];
    }

    /**
     * Dua peta lookup master obat, diambil sekali untuk semua bahan:
     * productId → KFA, dan NAMA (huruf besar) → KFA bila namanya tak kembar.
     */
    private static function petaKfa(array $grupList): array
    {
        $productIdList = [];
        $namaList = [];
        foreach ($grupList as $grup) {
            foreach ($grup['bahanList'] as $bahan) {
                $productId = trim((string) ($bahan['productId'] ?? ''));
                if ($productId !== '') {
                    $productIdList[$productId] = true;
                    continue;
                }
                $nama = trim((string) ($bahan['productName'] ?? ''));
                if ($nama !== '') {
                    $namaList[mb_strtoupper($nama)] = true;
                }
            }
        }

        $kfaByProductId = [];
        if ($productIdList !== []) {
            $kfaByProductId = DB::table('immst_products')
                ->whereIn('product_id', array_keys($productIdList))
                ->whereRaw("product_id_satusehat IS NOT NULL AND LENGTH(TRIM(product_id_satusehat)) > 0")
                ->get(['product_id', 'product_id_satusehat', 'product_name_satusehat', 'product_name'])
                ->mapWithKeys(fn($baris) => [(string) $baris->product_id => [
                    'code' => trim((string) $baris->product_id_satusehat),
                    'display' => trim((string) ($baris->product_name_satusehat ?: $baris->product_name)),
                ]])
                ->all();
        }

        $kfaByNama = [];
        if ($namaList !== []) {
            $kandidat = DB::table('immst_products')
                ->whereIn(DB::raw('UPPER(TRIM(product_name))'), array_keys($namaList))
                ->whereRaw("product_id_satusehat IS NOT NULL AND LENGTH(TRIM(product_id_satusehat)) > 0")
                ->get(['product_id_satusehat', 'product_name_satusehat', 'product_name']);

            $perNama = [];
            foreach ($kandidat as $baris) {
                $perNama[mb_strtoupper(trim((string) $baris->product_name))][] = [
                    'code' => trim((string) $baris->product_id_satusehat),
                    'display' => trim((string) ($baris->product_name_satusehat ?: $baris->product_name)),
                ];
            }
            foreach ($perNama as $nama => $daftar) {
                // Nama kembar = ambigu → tidak dipakai, bahan itu dilaporkan gagal.
                if (count($daftar) === 1) {
                    $kfaByNama[$nama] = $daftar[0];
                }
            }
        }

        return [$kfaByProductId, $kfaByNama];
    }

    /**
     * Bentuk FHIR Medication.ingredient[] untuk satu grup.
     *
     * Kekuatan (strength) sengaja tidak diisi: JSON hanya menyimpan dosis sebagai teks
     * bebas ("1/2", "500mg", "sesuai bb"), menebak angkanya berisiko salah takar.
     */
    public static function fhirIngredient(array $bahanList): array
    {
        return array_map(fn($bahan) => [
            'itemCodeableConcept' => [
                'coding' => [[
                    'system' => 'http://sys-ids.kemkes.go.id/kfa',
                    'code' => $bahan['code'],
                    'display' => $bahan['display'],
                ]],
            ],
            'isActive' => true,
        ], $bahanList);
    }
}
