<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Obat NON-RACIKAN e-resep yang siap dikirim ke SATUSEHAT, sudah ber-kode KFA.
 *
 * JSON e-resep tidak menyimpan kode KFA sama sekali — yang ada `productId`. Kodenya
 * diambil dari master obat (`immst_products.product_id_satusehat`). Sebelum helper ini
 * ada, MedicationRequest & MedicationDispense masing-masing punya salinan logika ini,
 * dan salinan RJ/UGD sempat membaca key `kfaCode` yang tak pernah ada di JSON sehingga
 * 0 item terkirim sambil melapor "berhasil".
 *
 * Urutan keluarannya PENTING: dipakai membangun ulang pasangan resep→penyerahan untuk
 * kunjungan yang dikirim sebelum peta item dicatat (lihat MedicationRequestItem).
 *
 * Untuk racikan, lihat [[RacikanKfa]].
 */
class ObatKfa
{
    /**
     * @param  int|null  $obatTanpaKfa  diisi jumlah item yang dilewati (productId kosong
     *                                  atau master belum punya KFA) — WAJIB dilaporkan
     *                                  pemanggil, jangan dibuang diam-diam.
     * @return array<int, array{code:string, display:string, productId:string, qty:int}>
     */
    public static function nonRacikanList(array $data, ?int &$obatTanpaKfa = null): array
    {
        $obatTanpaKfa = 0;
        $itemList = [];

        foreach (EresepJson::lembar($data) as $lembar) {
            foreach ($lembar['nonRacikan'] as $obat) {
                $productId = trim((string) ($obat['productId'] ?? ''));
                if ($productId === '') {
                    $obatTanpaKfa++;
                    continue;
                }
                $itemList[] = [
                    'productId' => $productId,
                    'productName' => (string) ($obat['productName'] ?? ''),
                    'qty' => (int) ($obat['qty'] ?? 1) ?: 1,
                ];
            }
        }

        if ($itemList === []) {
            return [];
        }

        $kfaMap = DB::table('immst_products')
            ->whereIn('product_id', array_values(array_unique(array_column($itemList, 'productId'))))
            ->get(['product_id', 'product_id_satusehat', 'product_name_satusehat'])
            ->keyBy('product_id');

        $obatKfaList = [];
        foreach ($itemList as $obat) {
            $master = $kfaMap->get($obat['productId']);
            $kfaCode = trim((string) ($master->product_id_satusehat ?? ''));
            if ($kfaCode === '') {
                $obatTanpaKfa++;
                continue;
            }
            $obatKfaList[] = [
                'code' => $kfaCode,
                'display' => trim((string) ($master->product_name_satusehat ?? '')) ?: $obat['productName'],
                'productId' => $obat['productId'],
                'qty' => $obat['qty'],
            ];
        }

        return $obatKfaList;
    }
}
