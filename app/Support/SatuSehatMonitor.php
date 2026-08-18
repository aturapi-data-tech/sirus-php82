<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Monitoring pengiriman SATUSEHAT per bulan — mana kunjungan yang sudah & belum dikirim,
 * plus hitungan capaian mengikuti slide Kemenkes "Alur Penilaian RME untuk Akreditasi RS".
 *
 * Sumber datanya node `satusehat` di JSON EMR tiap kunjungan (tak ada tabel terpisah), jadi
 * deteksinya memakai INSTR pada CLOB — bukan JSON_VALUE, yang tak didukung Oracle di sini.
 * Penanda sengaja memakai `"kunci":["` sehingga array KOSONG (`[]`) tidak ikut terhitung.
 *
 * Ukuran: satu bulan RJ (2.938 baris) = ±0,24 detik.
 */
class SatuSehatMonitor
{
    /** Modul → tabel, kolom tanggal, PK, kolom CLOB JSON. */
    public const MODUL = [
        'RJ' => ['tabel' => 'rstxn_rjhdrs', 'tanggal' => 'rj_date', 'pk' => 'rj_no', 'json' => 'datadaftarpolirj_json', 'label' => 'Rawat Jalan'],
        'UGD' => ['tabel' => 'rstxn_ugdhdrs', 'tanggal' => 'rj_date', 'pk' => 'rj_no', 'json' => 'datadaftarugd_json', 'label' => 'IGD'],
        'RI' => ['tabel' => 'rstxn_rihdrs', 'tanggal' => 'entry_date', 'pk' => 'rihdr_no', 'json' => 'datadaftarri_json', 'label' => 'Rawat Inap'],
    ];

    /**
     * 10 resource wajib (usulan Agustus Kemenkes). `penanda` = potongan teks yang HARUS ada
     * di JSON bila resource itu sudah terkirim; beberapa resource punya lebih dari satu
     * sumber (lab & radiologi) sehingga penandanya berupa daftar (OR).
     */
    public const RESOURCE = [
        'encounter' => ['label' => 'Kunjungan', 'fhir' => 'Encounter', 'penanda' => ['"encounterId":"']],
        'condition' => ['label' => 'Diagnosis', 'fhir' => 'Condition', 'penanda' => ['"conditionIds":["']],
        'observation' => ['label' => 'Observasi', 'fhir' => 'Observation', 'penanda' => ['"observationIds":["']],
        'procedure' => ['label' => 'Tindakan', 'fhir' => 'Procedure', 'penanda' => ['"procedureIds":["']],
        'medicationRequest' => ['label' => 'Peresepan', 'fhir' => 'MedicationRequest', 'penanda' => ['"medicationRequestIds":["']],
        'medicationDispense' => ['label' => 'Obat diambil', 'fhir' => 'MedicationDispense', 'penanda' => ['"medicationDispenseIds":["']],
        'serviceRequest' => ['label' => 'Permintaan Penunjang', 'fhir' => 'ServiceRequest', 'penanda' => ['"labServiceRequestIds":["', '"radServiceRequestIds":["']],
        'specimen' => ['label' => 'Spesimen Lab', 'fhir' => 'Specimen', 'penanda' => ['"labSpecimenIds":["']],
        'imagingStudy' => ['label' => 'Radiologi', 'fhir' => 'ImagingStudy', 'penanda' => []],
        'diagnosticReport' => ['label' => 'Hasil Penunjang', 'fhir' => 'DiagnosticReport', 'penanda' => ['"labDiagnosticReportIds":["', '"radDiagnosticReportIds":["']],
    ];

    /** 6 resource yang dinilai bulan Juli (konsistensi/growth). */
    public const RESOURCE_JULI = ['encounter', 'condition', 'medicationRequest', 'medicationDispense', 'specimen', 'imagingStudy'];

    /** Ekspresi SQL "resource ini sudah terkirim" untuk satu modul. */
    public static function ekspresiTerkirim(string $modul, string $resource): string
    {
        $kolomJson = self::MODUL[$modul]['json'];
        $penandaList = self::RESOURCE[$resource]['penanda'];

        if ($penandaList === []) {
            return '0';   // resource belum punya sender sama sekali (mis. ImagingStudy)
        }

        $syarat = array_map(
            fn($penanda) => "INSTR($kolomJson, " . self::kutip($penanda) . ") > 0",
            $penandaList
        );

        return 'CASE WHEN ' . implode(' OR ', $syarat) . ' THEN 1 ELSE 0 END';
    }

    /**
     * Rekap satu bulan untuk satu modul: total kunjungan + jumlah terkirim per resource.
     *
     * @param  string  $bulan  'YYYY-MM'
     * @return array{total:int, terkirim:array<string,int>}
     */
    public static function rekapBulan(string $modul, string $bulan): array
    {
        // Satu halaman memanggil rekap bulan yang sama berkali-kali (ringkasan, konsistensi,
        // capaian). Tanpa memo, tiap panggilan = satu pemindaian CLOB sebulan penuh.
        static $memo = [];
        $kunciMemo = $modul . '|' . $bulan;
        if (isset($memo[$kunciMemo])) {
            return $memo[$kunciMemo];
        }

        // Bulan yang sudah lewat tak berubah lagi kecuali ada kiriman susulan — cache
        // pendek supaya halaman tetap ringan tapi angka tak basi berjam-jam.
        $bulanIni = date('Y-m');
        if ($bulan < $bulanIni) {
            return $memo[$kunciMemo] = \Illuminate\Support\Facades\Cache::remember(
                "satusehat-monitor:$kunciMemo", now()->addMinutes(10),
                fn() => self::hitungRekapBulan($modul, $bulan)
            );
        }

        return $memo[$kunciMemo] = self::hitungRekapBulan($modul, $bulan);
    }

    /** Query sesungguhnya — dipisah supaya bisa di-memo & di-cache di atas. */
    private static function hitungRekapBulan(string $modul, string $bulan): array
    {
        $cfg = self::MODUL[$modul];
        $pilih = ['count(*) as total'];
        foreach (array_keys(self::RESOURCE) as $resource) {
            $pilih[] = 'sum(' . self::ekspresiTerkirim($modul, $resource) . ") as {$resource}";
        }

        $baris = DB::table($cfg['tabel'])
            ->selectRaw(implode(', ', $pilih))
            ->whereRaw("to_char({$cfg['tanggal']},'yyyy-mm') = ?", [$bulan])
            ->first();

        $terkirim = [];
        foreach (array_keys(self::RESOURCE) as $resource) {
            $terkirim[$resource] = (int) ($baris->{strtolower($resource)} ?? $baris->{$resource} ?? 0);
        }

        return ['total' => (int) ($baris->total ?? 0), 'terkirim' => $terkirim];
    }

    /** Rekap semua modul sekaligus + total gabungan. */
    public static function rekapBulanSemua(string $bulan): array
    {
        $perModul = [];
        $gabungan = ['total' => 0, 'terkirim' => array_fill_keys(array_keys(self::RESOURCE), 0)];

        foreach (array_keys(self::MODUL) as $modul) {
            $rekap = self::rekapBulan($modul, $bulan);
            $perModul[$modul] = $rekap;
            $gabungan['total'] += $rekap['total'];
            foreach ($rekap['terkirim'] as $resource => $jumlah) {
                $gabungan['terkirim'][$resource] += $jumlah;
            }
        }

        return ['perModul' => $perModul, 'gabungan' => $gabungan];
    }

    /**
     * Penilaian bulan JULI: konsistensi pengiriman 2 bulan sebelumnya.
     *
     *   growth resource x = kiriman[bulan n-1] / kiriman[bulan n-2] × 100%
     *   rata-rata capaian = Σ growth / 6
     *
     * Pembagi 0 → growth null (tak terdefinisi), TIDAK dianggap 0 maupun 100 —
     * dua-duanya klaim yang tak ada dasarnya. Yang null dikeluarkan dari rata-rata
     * dan dilaporkan apa adanya.
     */
    public static function konsistensi(string $bulanPenilaian): array
    {
        $bulanSatu = self::geserBulan($bulanPenilaian, -1);
        $bulanDua = self::geserBulan($bulanPenilaian, -2);

        $rekapSatu = self::rekapBulanSemua($bulanSatu)['gabungan']['terkirim'];
        $rekapDua = self::rekapBulanSemua($bulanDua)['gabungan']['terkirim'];

        $growth = [];
        foreach (self::RESOURCE_JULI as $resource) {
            $pembilang = $rekapSatu[$resource] ?? 0;
            $penyebut = $rekapDua[$resource] ?? 0;
            $growth[$resource] = [
                'bulanSatu' => $pembilang,
                'bulanDua' => $penyebut,
                'nilai' => $penyebut > 0 ? round($pembilang / $penyebut * 100, 1) : null,
            ];
        }

        $terhitung = array_filter(array_column($growth, 'nilai'), fn($nilai) => $nilai !== null);

        return [
            'bulanSatu' => $bulanSatu,
            'bulanDua' => $bulanDua,
            'growth' => $growth,
            'rataRata' => $terhitung === [] ? null : round(array_sum($terhitung) / count(self::RESOURCE_JULI), 1),
            'takTerhitung' => count(self::RESOURCE_JULI) - count($terhitung),
        ];
    }

    /**
     * Usulan AGUSTUS: capaian = (kelengkapan × 40%) + (konsistensi × 60%).
     *
     * PERHATIAN: rubrik resmi "skor kelengkapan" belum ada di slide — yang tertulis hanya
     * "skoring kelengkapan pengiriman tiap modul". Di sini kelengkapan diartikan:
     * berapa persen resource wajib yang RELEVAN untuk modul itu sudah pernah terkirim
     * bulan ini. Angkanya transparan supaya bisa dikoreksi begitu rubrik resminya keluar.
     */
    public static function capaian(string $bulanPenilaian): array
    {
        $bulanNilai = self::geserBulan($bulanPenilaian, -1);
        $rekap = self::rekapBulanSemua($bulanNilai);

        $kelengkapanModul = [];
        foreach ($rekap['perModul'] as $modul => $data) {
            $relevan = 0;
            $terpenuhi = 0;
            foreach (self::RESOURCE as $resource => $meta) {
                if ($meta['penanda'] === []) {
                    continue;   // belum ada sender-nya → tak adil dihitung sebagai kegagalan modul
                }
                $relevan++;
                if (($data['terkirim'][$resource] ?? 0) > 0) {
                    $terpenuhi++;
                }
            }
            $kelengkapanModul[$modul] = [
                'terpenuhi' => $terpenuhi,
                'relevan' => $relevan,
                'nilai' => $relevan > 0 ? round($terpenuhi / $relevan * 100, 1) : null,
            ];
        }

        $nilaiModul = array_filter(array_column($kelengkapanModul, 'nilai'), fn($nilai) => $nilai !== null);
        $kelengkapan = $nilaiModul === [] ? null : round(array_sum($nilaiModul) / count($nilaiModul), 1);

        $konsistensi = self::konsistensi($bulanPenilaian);
        $nilaiKonsistensi = $konsistensi['rataRata'];

        $capaian = ($kelengkapan === null || $nilaiKonsistensi === null)
            ? null
            : round($kelengkapan * 0.4 + min($nilaiKonsistensi, 100) * 0.6, 1);

        return [
            'bulanNilai' => $bulanNilai,
            'kelengkapanModul' => $kelengkapanModul,
            'kelengkapan' => $kelengkapan,
            'konsistensi' => $nilaiKonsistensi,
            'capaian' => $capaian,
            'rincianKonsistensi' => $konsistensi,
        ];
    }

    /** 'YYYY-MM' digeser n bulan. */
    public static function geserBulan(string $bulan, int $n): string
    {
        [$tahun, $bulanKe] = array_map('intval', explode('-', $bulan));

        return date('Y-m', mktime(0, 0, 0, $bulanKe + $n, 1, $tahun));
    }

    /** Literal string untuk SQL — penanda JSON mengandung kutip ganda, bukan tunggal. */
    private static function kutip(string $teks): string
    {
        return "'" . str_replace("'", "''", $teks) . "'";
    }
}
