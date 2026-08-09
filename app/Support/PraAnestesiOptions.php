<?php

namespace App\Support;

/**
 * Opsi & peta label Pengkajian Pra Anestesi dan Pra Sedasi (PAB 4 / RM 50).
 * SATU sumber untuk form + tabel entri + cetak/viewer — jangan duplikasi label di blade.
 */
class PraAnestesiOptions
{
    /**
     * Checklist Fungsi Sistem Organ (RM 50) — slugGrup => ['label' => ..., 'items' => [key => label]].
     * Key item = key JSON di entri newForm['fungsiSistemOrgan'][key] = bool.
     * Tiap grup juga punya status "DBN / Dalam Batas Normal" (key "<slugGrup>Dbn") dan
     * toggle "Lain-lain" (key "<slugGrup>LainLain") + keterangan bebas
     * di newForm['fungsiSistemOrganLainKet'][slugGrup].
     */
    public static function fungsiSistemOrgan(): array
    {
        return [
            'pernafasan' => ['label' => 'Pernafasan', 'items' => [
                'asthma' => 'Asthma',
                'bronkhitis' => 'Bronkhitis',
                'ppok' => 'PPOK',
                'dyspnea' => 'Dyspnea',
                'orthopnea' => 'Orthopnea',
                'batukProduktif' => 'Batuk Produktif',
                'ispa' => 'ISPA',
                'tuberkulosis' => 'Tuberkulosis',
                'effusePleura' => 'Effuse Pleura',
                'pneumonia' => 'Pneumonia',
            ]],
            'kardiovaskuler' => ['label' => 'Kardiovaskuler', 'items' => [
                'ekgAbnormal' => 'EKG Abnormal',
                'angina' => 'Angina',
                'arterioSklerosisHeartDisease' => 'Arterio Sklerosis Heart Disease',
                'gagalJantungKongestif' => 'Gagal Jantung Kongestif',
                'disritmia' => 'Disritmia',
                'limitasiAktifitas' => 'Limitasi Aktifitas',
                'hipertensi' => 'Hipertensi',
                'infarkMiokard' => 'Infark Miokard',
                'murmur' => 'Murmur',
                'paceMaker' => 'Pace Maker',
                'demamReumatik' => 'Demam Reumatik',
                'penyakitKatub' => 'Penyakit Katub',
            ]],
            'neuroMuskuluskeletal' => ['label' => 'Neuro Muskuluskeletal', 'items' => [
                'arthritis' => 'Arthritis',
                'backProblems' => 'Back Problems',
                'cvaStrokeTia' => 'CVA / Stroke / TIA',
                'nyeriKepalaIcp' => 'Nyeri Kepala / ICP',
                'penurunanKesadaran' => 'Penurunan Kesadaran',
                'neuroMuskularDisease' => 'Neuro Muskular Disease',
                'kelemahanOtot' => 'Kelemahan Otot',
                'kejang' => 'Kejang',
                'paralisis' => 'Paralisis',
                'parestesia' => 'Parestesia',
                'pingsan' => 'Pingsan',
            ]],
            'renalEndokrin' => ['label' => 'Renal / Endokrin', 'items' => [
                'diabetesMelitus' => 'Diabetes Melitus',
                'gagalGinjalDialisis' => 'Gagal Ginjal / Dialisis',
                'penyakitThyroid' => 'Penyakit Thyroid',
                'retensiaUrine' => 'Retensia Urine',
                'isk' => 'ISK',
                'beratBadanTurun' => 'Berat Badan Turun',
            ]],
            'lainLain' => ['label' => 'Lain-lain', 'items' => [
                'bleedingTendencies' => 'Bleeding Tendencies',
                'imunosupresan' => 'Imunosupresan',
                'sickleCellDisTrait' => 'Sickle Cell Dis / Trait',
                'riwayatTranfusi' => 'Riwayat Tranfusi',
                'antikoagulan' => 'Antikoagulan',
                'anemia' => 'Anemia',
                'kanker' => 'Kanker',
                'dehidrasi' => 'Dehidrasi',
                'hemophilia' => 'Hemophilia',
                'kehamilan' => 'Kehamilan',
            ]],
        ];
    }

    /** Peta label datar key => label (untuk tampilan entri & cetak). */
    public static function fungsiSistemOrganLabels(): array
    {
        return collect(self::fungsiSistemOrgan())->pluck('items')->collapse()->all();
    }

    /**
     * Label item yang tercentang pada satu entri, per grup: labelGrup => [label, ...].
     * $checked = newForm['fungsiSistemOrgan']; $lainKet = newForm['fungsiSistemOrganLainKet'].
     * Toggle "Lain-lain" grup ikut ditampilkan beserta keterangannya.
     */
    public static function fungsiSistemOrganTerpilih(array $checked, array $lainKet = []): array
    {
        $hasil = [];
        foreach (self::fungsiSistemOrgan() as $slugGrup => $grup) {
            $labels = [];

            if (!empty($checked[$slugGrup . 'Dbn'])) {
                $labels[] = 'DBN (Dalam Batas Normal)';
            }

            $labels = array_merge($labels, collect($grup['items'])
                ->filter(fn($label, $key) => !empty($checked[$key]))
                ->values()->all());

            if (!empty($checked[$slugGrup . 'LainLain'])) {
                $keterangan = trim((string) ($lainKet[$slugGrup] ?? ''));
                $labels[] = 'Lain-lain' . ($keterangan !== '' ? ': ' . $keterangan : '');
            }

            if (count($labels) > 0) {
                $hasil[$grup['label']] = $labels;
            }
        }
        return $hasil;
    }
}
