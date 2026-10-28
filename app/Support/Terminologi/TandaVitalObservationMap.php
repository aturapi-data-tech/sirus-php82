<?php

namespace App\Support\Terminologi;

/**
 * SUMBER TUNGGAL kode LOINC tanda vital RJ (Observation SATUSEHAT) — dipakai sender
 * kirim-observation RJ DAN layar Coding SATUSEHAT (coding-pemeriksaan-rj), supaya kode yang
 * ditampilkan ke petugas persis kode yang dikirim. Sumber data: pemeriksaan.tandaVital.
 *
 * Nilai disalin apa adanya dari sender RJ (yang sudah disamakan dengan RI) — UGD & RI masih
 * menulis petanya sendiri di sender masing-masing.
 */
final class TandaVitalObservationMap
{
    /** Panel tekanan darah + dua komponennya (key EMR: sistolik / distolik). */
    public const TEKANAN_DARAH = [
        'panel' => ['system' => 'http://loinc.org', 'code' => '85354-9', 'display' => 'Blood pressure panel with all children optional'],
        'sistolik' => ['system' => 'http://loinc.org', 'code' => '8480-6', 'display' => 'Systolic blood pressure'],
        'distolik' => ['system' => 'http://loinc.org', 'code' => '8462-4', 'display' => 'Diastolic blood pressure'],
        'unit' => 'mm[Hg]',
    ];

    /** Vital tunggal — key = key JSON EMR di pemeriksaan.tandaVital. */
    public const VITAL_TUNGGAL = [
        'frekuensiNadi'  => ['label' => 'Nadi',       'loinc' => '8867-4',  'display' => 'Heart rate',       'unit' => 'beats/minute',   'ucum' => '/min', 'satuanTampil' => 'x/menit'],
        'suhu'           => ['label' => 'Suhu',       'loinc' => '8310-5',  'display' => 'Body temperature', 'unit' => 'C',              'ucum' => 'Cel',  'satuanTampil' => '°C'],
        'frekuensiNafas' => ['label' => 'Pernafasan', 'loinc' => '9279-1',  'display' => 'Respiratory rate', 'unit' => 'breaths/minute', 'ucum' => '/min', 'satuanTampil' => 'x/menit'],
        'spo2'           => ['label' => 'SpO2',       'loinc' => '59408-5', 'display' => 'Oxygen saturation in Arterial blood by Pulse oximetry', 'unit' => '%', 'ucum' => '%', 'satuanTampil' => '%'],
    ];

    /**
     * Antropometri — key = key JSON EMR di pemeriksaan.nutrisi (Playbook RJ bab 4, disamakan dgn
     * alur apikesehatan.com; LOINC diverifikasi tx.fhir.org). Nilai kosong/"0" tidak dikirim.
     * IMT/LK/LILA tak ada di contoh resmi → belum dikirim.
     */
    public const ANTROPOMETRI = [
        'bb' => ['label' => 'Berat Badan',  'loinc' => '29463-7', 'display' => 'Body weight', 'unit' => 'kg', 'ucum' => 'kg', 'satuanTampil' => 'kg'],
        'tb' => ['label' => 'Tinggi Badan', 'loinc' => '8302-2',  'display' => 'Body height', 'unit' => 'cm', 'ucum' => 'cm', 'satuanTampil' => 'cm'],
    ];
}
