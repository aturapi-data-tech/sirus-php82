<?php

namespace App\Http\Traits\SATUSEHAT;

use Illuminate\Support\Facades\Http;

/**
 * Cari & periksa kode KFA (Kamus Farmasi dan Alat Kesehatan) lewat API KFA v2 Kemkes.
 *
 * API ini ada di host yang sama dengan FHIR SATUSEHAT (SATUSEHAT_BASE_URL tanpa /fhir-r4/v1)
 * dan memakai token OAuth yang sama. Hanya GET terminologi, tanpa data pasien, jadi tidak
 * dicatat ke web_log_status seperti kiriman resource.
 *
 *   Detail : GET {host}/kfa-v2/products?identifier=kfa&code={kode}
 *            kode tak dikenal → HTTP 200 dengan "result": null (bukan 404)
 *   Cari   : GET {host}/kfa-v2/products/all?page=1&size=..&product_type=farmasi&keyword=..
 *            → items.data[]
 */
trait KfaTrait
{
    use SatuSehatTrait;

    protected function alamatKfa(): string
    {
        $host = preg_replace('~/fhir-r4/v1/?$~', '', rtrim((string) $this->baseUrl, '/'));

        return rtrim($host, '/') . '/kfa-v2';
    }

    /**
     * Cari produk farmasi berdasarkan nama. Gagal/timeout → null (bedakan dari "tidak ditemukan" = []).
     *
     * @return array<int, array>|null
     */
    protected function cariProdukKfa(string $keyword, int $jumlah = 15): ?array
    {
        $this->initializeSatuSehat();

        try {
            $response = Http::timeout(8)
                ->withToken($this->getAccessToken())
                ->get($this->alamatKfa() . '/products/all', [
                    'page' => 1,
                    'size' => $jumlah,
                    'product_type' => 'farmasi',
                    'keyword' => $keyword,
                ]);
        } catch (\Throwable) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        return collect($response->json('items.data') ?? [])
            ->map(fn(array $produk) => $this->ringkasProdukKfa($produk))
            ->filter(fn(array $produk) => $produk['kfa_code'] !== '')
            ->values()
            ->all();
    }

    /**
     * Periksa satu kode KFA. Kode tak dikenal dibedakan dari server gagal, supaya gangguan
     * API tidak terbaca sebagai kode salah.
     *
     * @return array{status: 'ada'|'tidak_ada'|'gagal', produk: ?array}
     */
    protected function periksaKodeKfa(string $kode): array
    {
        $this->initializeSatuSehat();

        try {
            $response = Http::timeout(8)
                ->withToken($this->getAccessToken())
                ->get($this->alamatKfa() . '/products', ['identifier' => 'kfa', 'code' => $kode]);
        } catch (\Throwable) {
            return ['status' => 'gagal', 'produk' => null];
        }

        if (! $response->successful()) {
            return ['status' => 'gagal', 'produk' => null];
        }

        $produk = $response->json('result');
        if (! \is_array($produk) || empty($produk['kfa_code'])) {
            return ['status' => 'tidak_ada', 'produk' => null];
        }

        return ['status' => 'ada', 'produk' => $this->ringkasProdukKfa($produk)];
    }

    /** Ambil field yang dipakai layar saja (balasan KFA besar: deskripsi, ATC, harga, dst.). */
    private function ringkasProdukKfa(array $produk): array
    {
        $zatAktif = collect($produk['active_ingredients'] ?? [])
            ->map(fn($zat) => trim(($zat['zat_aktif'] ?? '') . ' ' . ($zat['kekuatan_zat_aktif'] ?? '')))
            ->filter()
            ->implode(', ');

        $nama = (string) ($produk['name'] ?? '');
        $aktif = ($produk['active'] ?? false) === true && ($produk['state'] ?? '') === 'valid'
            && ! str_contains(mb_strtolower($nama), '(tidak dipakai)');

        return [
            'kfa_code' => (string) ($produk['kfa_code'] ?? ''),
            'name' => $nama,
            'sediaan' => (string) ($produk['dosage_form']['name'] ?? ''),
            'zat_aktif' => $zatAktif,
            'pabrik' => (string) ($produk['manufacturer'] ?? ''),
            'satuan' => (string) ($produk['uom']['name'] ?? ''),
            'aktif' => $aktif,
            'pengganti_kode' => (string) ($produk['replacement']['product']['kfa_code'] ?? ''),
            'pengganti_nama' => (string) ($produk['replacement']['product']['name'] ?? ''),
        ];
    }
}
