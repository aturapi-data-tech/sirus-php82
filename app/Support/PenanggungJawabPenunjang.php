<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Dokter penanggung jawab unit penunjang (Laboratorium / Radiologi).
 *
 * RS ini belum punya mekanisme penunjukan PJ yang eksplisit, jadi PJ ditentukan
 * secara konvensi: dokter aktif pada poli unit tersebut, diurutkan alfabetik dan
 * diambil yang pertama. Konvensi itu sebelumnya ditulis ulang di cetakan hasil
 * lab; dikumpulkan di sini supaya cetakan dan kiriman SATUSEHAT tidak bisa
 * menyebut orang yang berbeda untuk pemeriksaan yang sama.
 *
 * Begitu ada master penunjukan PJ yang sesungguhnya, hanya berkas ini yang
 * perlu diubah.
 */
class PenanggungJawabPenunjang
{
    public const POLI_LABORATORIUM = 22;
    public const POLI_RADIOLOGI = 15;

    /** Cache per-request: satu poli cukup ditanyakan sekali per permintaan. */
    private static array $cache = [];

    /**
     * Baris dokter PJ, atau null bila poli itu tidak punya dokter aktif.
     *
     * @return object|null {dr_id, dr_name, dr_uuid}
     */
    public static function dokter(int $poliId): ?object
    {
        if (!array_key_exists($poliId, self::$cache)) {
            self::$cache[$poliId] = DB::table('rsmst_doctors')
                ->where('poli_id', $poliId)
                ->where('active_status', '1')
                ->orderBy('dr_name')
                ->first(['dr_id', 'dr_name', 'dr_uuid']);
        }

        return self::$cache[$poliId];
    }

    /**
     * Referensi FHIR Practitioner untuk dipakai sebagai ServiceRequest.performer.
     *
     * Mengembalikan array KOSONG bila PJ-nya tidak ada atau dr_uuid-nya belum
     * diisi — kondisi nyata, bukan teoretis: per 16/08/2026 dokter PJ radiologi
     * belum punya dr_uuid sementara PJ laboratorium sudah. Pemanggil yang menerima
     * array kosong sebaiknya tidak mengirim performer sama sekali, biar
     * ServiceRequestTrait memakai dokter pengirim sebagai pengganti.
     *
     * @return array{reference?: string, display?: string}
     */
    public static function practitionerRef(int $poliId): array
    {
        $dokter = self::dokter($poliId);
        $uuid = trim((string) ($dokter->dr_uuid ?? ''));

        if ($uuid === '') {
            return [];
        }

        return [
            'reference' => 'Practitioner/' . $uuid,
            'display' => (string) ($dokter->dr_name ?? ''),
        ];
    }
}
