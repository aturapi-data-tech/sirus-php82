<?php

use Livewire\Component;

// Tutorial standarisasi pengiriman data SATUSEHAT (FHIR R4, Kemenkes).
// Gaya sama dgn koding-transaksi/koding-master: sidebar per-submenu,
// snippet = nowdoc (aman compiler Blade). Sumber: docs/satusehat-api.md.
new class extends Component {
    public function snippets(): array
    {
        return [

'utc' => <<<'TXT'
// SatuSehatTrait::makeRequest() — aturan main #1: semua dateTime dikirim UTC+00.
// Sender TETAP menyusun waktu lokal (toIso8601String() → "+07:00"); konversi di SATU tempat:
if (strtolower($method) !== 'get') {
    $data = $this->waktuKeUtc($data);
}

protected function waktuKeUtc(mixed $data): mixed
{
    if (is_array($data)) {                       // telusuri seluruh payload, rekursif
        foreach ($data as $key => $nilai) {
            $data[$key] = $this->waktuKeUtc($nilai);
        }
        return $data;
    }
    // hanya string yang SELURUHNYA dateTime ISO berzona (+07:00 / Z)
    if (is_string($data) && preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(:\d{2}(\.\d+)?)?(Z|[+-]\d{2}:\d{2})$/', $data)) {
        return Carbon::parse($data)->utc()->format('Y-m-d\TH:i:sP');
    }
    return $data;                                 // tanggal saja, teks bebas, angka → utuh
}

// "2023-08-23T17:35:00+07:00"  → "2023-08-23T10:35:00+00:00"   (contoh resmi PDF/Kemkes)
// "1990-05-01" (birthDate)     → tetap
// "kontrol 2023-08-23T17:35:00+07:00 lagi" → tetap (bukan dateTime utuh)
//
// AWAS: uji kering yang MENIMPA makeRequest() melihat payload SEBELUM konversi.
// Payload wajib array (bukan stdClass) — objek tidak ditelusuri.
TXT,

'finish-encounter' => <<<'TXT'
// EncounterTrait::siapkanFinishEncounter($encounter, $akhirIso, $conditionIdList, $keluhanUtamaId)
// Dipakai BERSAMA RJ/UGD/RI. $encounter = hasil getEncounter() → PUT berisi SELURUH resource
// (aturan main #6: FHIR tak mengenal partial update).

$encounter['status'] = 'finished';
$encounter['period']['end'] = waktuTerakhir(period.start, $akhirIso);   // end ≥ start (FHIRPath)

// 1) statusHistory — tiap entri wajib start+end (Rule 10122):
//    end entri = start entri BERIKUTNYA; entri terakhir 'finished'.
// 2) location[] — yang masih terbuka ditutup: end = start lokasi berikutnya / waktu selesai,
//    status 'completed' (dulu kamar/poli terakhir 'active' selamanya).
// 3) classHistory — WAJIB (*classHistory[i].class & .period): 1 entri dari Encounter.class
//    sepanjang period. Satu Encounter SATUSEHAT tak pernah ganti jenis (IGD→RI = Encounter baru).
// 4) diagnosis — WAJIB (Rule 10457):
//      conditionIds        → use DD  "Discharge diagnosis", rank 1..n
//      chiefComplaintId    → use CC  "Chief complaint",     rank n+1   (Playbook bab 3 & 27)
//    (system http://terminology.hl7.org/CodeSystem/diagnosis-role)

// Pemanggil (kirim-encounter RJ/UGD/RI → finishInti):
$encounterTersimpan = $this->siapkanFinishEncounter(
    $this->getEncounter($satuSehat['encounterId']), $akhirIso, $conditionIdList,
    $satuSehat['chiefComplaintId'] ?? null
);
// + hospitalization.dischargeDisposition (DischargeDisposition::rawatJalan/gawatDarurat/rawatInap)
// + UGD: pasangKelasLayanan() untuk Encounter lama
$this->makeRequest('put', "Encounter/{$satuSehat['encounterId']}", $encounterTersimpan);
TXT,

'triaged-ugd' => <<<'TXT'
// ugd/satu-sehat/⚡kirim-encounter.blade.php — kirimInti()
// arrived (POST) → triaged (PUT) → in-progress (PUT) → finished (PUT saat finish)

$waktuDatangIso = $ugdDate->toIso8601String();                 // kolom rj_date

if (empty($satuSehat['encounterTriaged'])) {
    $tingkatTriase = KedatanganIgd::tingkatTriase($dataUGD)['tingkat'];   // P0–P3 (EMR / saran Screening)
    $jamTriase = trim($dataUGD['screening']['tanggalPelayanan'] ?? '')    // TTD Screening
        ?: trim($dataUGD['anamnesa']['pengkajianPerawatan']['jamDatang'] ?? '');  // cadangan
    if ($tingkatTriase !== '' && $jamTriase !== '') {
        $waktuTriaseIso = $this->waktuTerakhir($waktuDatangIso, parseDate($jamTriase));  // ≥ arrived
        $this->tambahStatusEncounter($encounterId, 'triaged', $waktuTriaseIso);
        $satuSehat['encounterTriaged'] = true;
        $this->saveResult($rjNo, $satuSehat);    // simpan SEGERA: in-progress gagal ≠ triaged dobel
    } else {
        $catatanTriase = ' — TANPA status triaged: ...';   // toast KUNING, Encounter tetap jalan
    }
}

$jamPeriksa = $dataUGD['perencanaan']['pengkajianMedis']['waktuPemeriksaan'] ?? '';
$waktuMulaiIso = $this->waktuTerakhir($waktuTriaseIso ?: $waktuDatangIso, parseDate($jamPeriksa));
$this->startRoomEncounter($encounterId, ['startDate' => $waktuMulaiIso, 'locationId' => $locationId]);

// EncounterTrait::tambahStatusEncounter($id, $status, $mulaiIso)
//   GET → status = $status, statusHistory[] += {status, period.start} → PUT utuh. Lokasi tak disentuh.
TXT,

'service-class' => <<<'TXT'
// EncounterTrait::pasangKelasLayanan($encounter, $kelasSystem, $kode, $display,
//                                    $indikator = 'kelas-tetap', $indikatorDisplay = 'Kelas Tetap Perawatan')
// Canonical: SATUSEHAT FHIR R4 IG (Simplifier) → Extensions/ServiceClass.
// Dipasang di SETIAP Encounter.location[] yang belum punya; aman dipanggil berulang.

"location": [{
  "location": { "reference": "Location/{uuid}" },
  "status": "active",
  "period": { "start": "..." },
  "extension": [{
    "url": "https://fhir.kemkes.go.id/r4/StructureDefinition/ServiceClass",
    "extension": [
      { "url": "value",
        "valueCodeableConcept": { "coding": [{
          "system": "http://terminology.kemkes.go.id/CodeSystem/locationServiceClass-Outpatient",
          "code": "reguler", "display": "Kelas Reguler" }] } },
      { "url": "upgradeClassIndicator",
        "valueCodeableConcept": { "coding": [{
          "system": "http://terminology.kemkes.go.id/CodeSystem/locationUpgradeClass",
          "code": "kelas-tetap", "display": "Kelas Tetap Perawatan" }] } }
    ]
  }]
}]

// Kewajiban per modul (Pemetaan Nilai Encounter, dok resmi Kemkes):
//   IGD : serviceClass.value & .upgradeClassIndicator BERTANDA *  → dipasang (reguler/kelas-tetap)
//   RJ  : tanpa *  → tidak dipasang (RS tak punya poli eksekutif)
//   RI  : tanpa *  → belum (usulan: 1/2/3/vip/vvip + naik/turun/titip-rawat dari riwayat kamar)
// Pemasangan UGD: createNewEncounter([... 'kelasLayanan' => self::KELAS_LAYANAN_IGD])
//                 + finishInti() melengkapi Encounter yang dibuat sebelum fitur ini.
TXT,

'careplan-ugd' => <<<'TXT'
// JSON datadaftarugd_json → perencanaan.dischargePlanning (bentuk SAMA dengan RI)
"dischargePlanning": {
  "pelayananBerkelanjutan": {
    "pelayananBerkelanjutan": "Ada",                 // Ada | Tidak Ada
    "ketPelayananBerkelanjutan": "",
    "pelayananBerkelanjutanData": [{
      "id": "uuid", "jenisPelayanan": "Perawatan luka", "ketJenis": "",
      "tempatFasyankes": "Puskesmas X", "tglRencana": "05/11/2026",
      "snomedCode": "225358003", "snomedDisplay": "Wound care",     // disimpan PER ENTRI
      "petugas": "...", "petugasCode": "...", "tglPencatatan": "29/10/2026 22:40:00"
    }]
  },
  "penggunaanAlatBantu": {
    "penggunaanAlatBantu": "Ada", "ketPenggunaanAlatBantu": "",
    "penggunaanAlatBantuData": [{
      "id": "uuid", "jenisAlat": "Kursi roda", "ketAlat": "", "durasi": "2 minggu",
      "sumberAlat": "Pinjam RS", "snomedCode": "58938008", "snomedDisplay": "WC - Wheelchair", ...
    }]
  }
}

// Kode dari App\Support\Options\DischargePlanningOptions (diverifikasi tx.fhir.org — JANGAN hafalan).

// → kartu ugd/satu-sehat/⚡kirim-perencanaan-pulang (CarePlanTrait::createCarePlanPemulangan)
POST /CarePlan
{ "status": "active", "intent": "plan", "title": "Perencanaan Pemulangan Pasien",
  "category": [{ "coding": [{ "system": "http://snomed.info/sct", "code": "736372004",
                              "display": "Discharge care plan" }] }],
  "description": "Pelayanan berkelanjutan: Perawatan luka di Puskesmas X, rencana 05/11/2026. ...",
  "subject": Patient, "encounter": Encounter, "author": Practitioner (dr_uuid DPJP UGD),
  "created": rj_date, "period": { "end": taskId7 → selesaiPemeriksaan },
  "activity": [
    { "detail": { "kind": "ServiceRequest", "status": "not-started", "code": SNOMED pelayanan, ... } },
    { "detail": { "kind": "DeviceRequest",  "status": "not-started", "code": SNOMED alat, ... } } ] }
// Hasil: satusehat.perencanaanPulangId → section "Perencanaan Perawatan" resume medis UGD/RI.
TXT,

'uji-siklus' => <<<'TXT'
// Uji kering SIKLUS Encounter dengan "server" tiruan di memori — tak ada yang terkirim.
$t = new class {
    use App\Http\Traits\SATUSEHAT\EncounterTrait;
    public array $server = [];
    public function makeRequest($method, $url, $payload = []) {
        if ($method === 'get') return $this->server;     // getEncounter() membaca "server"
        $this->server = $payload;                        // POST/PUT menimpa "server"
        return $payload;
    }
};
$t->createNewEncounter([... 'class_code' => 'EMER', 'startDate' => '2026-10-29T22:00:00+07:00',
                        'kelasLayanan' => [...]]);
$t->tambahStatusEncounter('x', 'triaged', '2026-10-29T22:05:00+07:00');
$t->startRoomEncounter('x', ['startDate' => '2026-10-29T22:20:00+07:00', 'locationId' => 'L']);
$akhir = $t->siapkanFinishEncounter($t->server, '2026-10-30T02:00:00+07:00', ['c1'], 'kk1');

// Hasil yang diharapkan:
//   statusHistory  arrived 22:00→22:05 · triaged 22:05→22:20 · in-progress 22:20→02:00 · finished
//   location       1 entri (tak dobel), completed, end 02:00, extension ServiceClass 1x
//   classHistory   EMER 22:00→02:00
//   diagnosis      DD (c1, rank 1) · CC (kk1, rank 2)
//
// Uji render komponen Livewire TANPA DB:
//   Livewire::test('pages::transaksi.ugd.emr-ugd.perencanaan.rm-perencanaan-ugd-actions')
//       ->set('dokumenTermuat', true)->set('perencanaan', $dataBuatan)->html();
// Jalankan dengan CACHE_STORE=array SESSION_DRIVER=array (DB lokal bisa mati / menunjuk PRODUKSI).
TXT,

'auth' => <<<'TXT'
// OAuth2 client_credentials — SatuSehatTrait::getAccessToken()
public function getAccessToken(): string
{
    return Cache::remember('satusehat_access_token', 3500, function () {
        $res = Http::timeout(10)->asForm()->post(
            env('SATUSEHAT_AUTH_URL') . 'accesstoken?grant_type=client_credentials',
            [
                'client_id'     => env('SATUSEHAT_CLIENT_ID'),
                'client_secret' => env('SATUSEHAT_SECRET_ID'),   // catat: _SECRET_ID
            ],
        );
        return $res->json()['access_token'];
    });
}

// TTL cache hardcoded 3500 dtk (~58 mnt) — nilai expires_in dari server DIABAIKAN.
// Header tiap panggilan FHIR:
//   Authorization: Bearer {token}
//   Organization-Id: {SATUSEHAT_ORGANIZATION_ID}   (tetap 100027469)
// Base URL: SATUSEHAT_BASE_URL -> .../fhir-r4/v1/   (PRODUCTION)
//
// Ganti environment = ganti NILAI env (tak ada toggle di kode).
//   Sandbox Kemkes: host api-satusehat-stg.kemkes.go.id
// AWAS: semua kredensial dibaca env() langsung TANPA wrapper config/*.php
//       -> integrasi mati SENYAP bila `php artisan config:cache` dijalankan.
TXT,

'dryrun' => <<<'TXT'
// Uji payload TANPA mengirim ke server: anonymous class yang memakai trait-nya,
// lalu makeRequest() ditimpa supaya payload DITANGKAP, bukan dikirim.
$dryRun = new class {
    use MedicationRequestTrait;

    public array $payloadList = [];

    public function makeRequest($method, $url, $payload = [])
    {
        $this->payloadList[] = $payload;
        return ['id' => 'dry-run-id'];
    }
};

$dryRun->createMedicationRequest([...]);
dump($dryRun->payloadList[0]);          // periksa struktur sebelum menembak API

// Kenapa wajib: .env menunjuk PRODUKSI (api-satusehat.kemkes.go.id, tanpa -stg),
// jadi trial-and-error ke API = mengotori data nasional. Cara ini juga jauh lebih
// cepat: satu kali jalan langsung kelihatan key mana yang kosong/salah bentuk.
TXT,

'transport' => <<<'TXT'
// makeRequest — SATU resource = SATU HTTP call (BUKAN FHIR Bundle)
public function makeRequest(string $method, string $endpoint, array $data = [])
{
    $res = Http::timeout(10)                          // tanpa connectTimeout/retry (backlog)
        ->withToken($this->getAccessToken())
        ->withHeaders(['Organization-Id' => env('SATUSEHAT_ORGANIZATION_ID')])
        ->{$method}(env('SATUSEHAT_BASE_URL') . $endpoint, $data);

    $this->logSatuSehat($res, $endpoint, $data);      // insert ke web_log_status

    if ($res->successful()) return $res->json();      // 2xx -> array
    throw new \Exception('API request failed: ' . $res->body());   // caller (blade) tangkap Throwable -> toast
}

// Tiap resource = POST/PUT terpisah: POST Encounter, POST Condition, POST Observation, ...
// Tabel audit web_log_status: code, date_ref, response, http_req,
//   http_payload, requestTransferTime -> sumber verifikasi tiap kiriman.
TXT,

'encounter-lifecycle' => <<<'TXT'
// Encounter = AKAR. Semua resource lain mereferensikan Encounter/{id},
//   Patient/{id}, Practitioner/{id}. Siklus status 3 tahap:
//
//   POST  /Encounter                 -> status "arrived"
//   PUT   /Encounter/{id}            -> "in-progress"  (startRoomEncounter)
//   PUT   /Encounter/{id}            -> "finished"     (hanya bila txnStatus=CLOSED / rjStatus=2)
//
// Kalau kirim Encounter GAGAL -> seluruh rangkaian berhenti (ROOT, wajib sukses).
// Prasyarat: rsmst_doctors.dr_uuid & rsmst_polis.poli_uuid TIDAK boleh kosong
//   -> kalau kosong, kirim Encounter berhenti dgn toast error.
// class = AMB (http://terminology.hl7.org/CodeSystem/v3-ActCode) = rawat jalan/ambulatory.
TXT,

'ihs' => <<<'TXT'
// IHS = identitas resource di SATUSEHAT (di-set SEKALI di master, bukan dilookup tiap kirim)

// Pasien — cari dulu, kalau kosong buat:
$ihs = $this->searchPatient(['nik' => $nik]);        // GET /Patient?identifier=.../nik|{nik}
if (! $ihs) $ihs = $this->createPatient($regNo);     // dari Master Pasien
// disimpan: rsmst_pasiens.patient_uuid (+ JSON pasien.identitas.patientUuid)

// Dokter        -> rsmst_doctors.dr_uuid   (di-set manual)
// Poli/Location -> rsmst_polis.poli_uuid   (di-set manual)
// Organization  -> env SATUSEHAT_ORGANIZATION_ID  (tetap)

// NIK wajib 16 digit; kalau tidak, identifier di-skip DIAM-DIAM (PatientTrait).
TXT,

'kirim-component' => <<<'TXT'
// ⚡kirim-<resource>-rj-actions.blade.php — satu tombol per-resource di UI RJ
new class extends Component {
    use SatuSehatTrait, ProcedureTrait, EmrRJTrait;

    public ?int  $rjNo = null;
    public array $ss = [];                 // node JSON 'satusehat' pada record RJ
    public bool  $hasEncounter = false;    // gate: Encounter harus sudah terkirim

    #[On('open-kirim-procedure-rj')]
    public function open(int $rjNo): void
    {
        $this->rjNo = $rjNo;
        $data = $this->findDataRJ($rjNo);              // baca CLOB (OracleLob)
        $this->ss = $data['satusehat'] ?? [];
        $this->hasEncounter = ! empty($this->ss['encounterId']);
    }

    public function kirimForCurrent(): void
    {
        if (! empty($this->ss['procedureIds'])) {      // idempotensi (guard lokal in-memory)
            $this->dispatch('toast', type: 'info', message: 'Sudah pernah dikirim.');
            return;
        }
        $ids = [];
        foreach ($tindakanList as $t) {
            if (empty($t['kodeIcd9'])) continue;       // item tanpa kode -> skip diam-diam
            $res   = $this->createProcedure($this->ss['encounterId'], $t);
            $ids[] = $res['id'];
        }
        $this->saveResult(['procedureIds' => $ids]);   // tulis balik node JSON satusehat
    }

    private function saveResult(array $patch): void
    {
        DB::transaction(function () use ($patch) {
            $this->lockRJRow($this->rjNo);             // row-lock anti race (pola RMW)
            $data = $this->findDataRJ($this->rjNo);
            $data['satusehat'] = array_replace($data['satusehat'] ?? [], $patch);
            $this->updateJsonRJ($this->rjNo, $data);
        });
    }
};
// Markup: tombol Kirim :disabled="!$hasEncounter" -> semua non-Encounter menunggu Encounter.
TXT,

'add-resource' => <<<'TXT'
// Menambah / mengaktifkan resource FHIR baru

// A) SUDAH ADA trait (Dispense / ServiceRequest / Specimen / DiagnosticReport / Allergy):
//    1. buat komponen kirim-<resource>-rj-actions.blade.php meniru kirim-procedure
//    2. render di satu-sehat-rj-actions.blade.php (deret tombol per-resource, ~baris 105-114)
//    3. gate :disabled="!$hasEncounter" + simpan id hasil ke node JSON 'satusehat'

// B) BELUM ada trait (Composition/Diet · Immunization):
//    ImagingStudy SUDAH ada trait (§PACS) — tinggal wire ke UI.
//    1. buat App\Http\Traits\SATUSEHAT\<Resource>Trait meniru ProcedureTrait
//    2. bangun payload FHIR R4 + POST /<Resource> via makeRequest()
//    3. WAJIB referensi Encounter/{id} + subject Patient/{id}

// UJI DI SANDBOX dulu (ganti env AUTH/BASE URL ke -stg).
// Verifikasi via tabel web_log_status (http_req / http_payload / response).
TXT,

'ss-imaging' => <<<'TXT'
// ImagingStudy — Radiologi.  POST /ImagingStudy
// ⚠️ ARSITEKTUR BARU SATUSEHAT: ImagingStudy dibuat DICOM ROUTER, bukan SIMRS.
//    Trait ini hanya untuk masa transisi — matikan begitu router terpasang,
//    kalau tidak satu studi tercatat dua kali.
// Saat ini masih dipanggil ⚡kirim-radiologi RJ/UGD/RI bila foto ada.
//
// UID DICOM: STUDY_UID dari Orthanc, fallback uidStudi() arc 2.25 (tak menunjuk gambar).

// 1) Cari UID asli dari Orthanc (OrthancTrait):
$uid = $this->cariStudyUid($radnumNo);  // null kalau belum ada di PACS

// 2) Kirim ke SATUSEHAT (ImagingStudyTrait):
$response = $this->postImagingStudy([
    'kunci'            => "rad-{$rjNo}-{$radDtl}",
    'patientId'        => $ihsPatient,
    'encounterId'      => $ihsEncounter,
    'started'          => $waktuIso,
    'modalityCode'     => 'DX',              // dari modalitasDariDeskripsi($radDesc)
    'modalityDisplay'  => 'Digital Radiography',
    'procedureCode'    => '36643-5',         // LOINC
    'procedureDisplay' => 'THORAX PA/AP',
    'referrerId'       => $ihsDokter,        // opsional
]);

// Trait menurunkan UID stabil dari kunci (deterministik, bukan random).
// SOP class Secondary Capture (1.2.840.10008.5.1.4.1.1.7) — jujur untuk upload.
// modalitasDariDeskripsi() konservatif: tak dikenali → OT (Other).
// Detail lengkap → panduan PACS (sidebar "PACS Orthanc & ImagingStudy").
TXT,

'ss-immunization' => <<<'TXT'
// Immunization — Imunisasi.  POST /Immunization
// GAP: belum ada modul imunisasi -> perlu form capture (vaksin, lot, rute, dosis).
public function createImmunization(array $data): array
{
    $payload = [
        'resourceType' => 'Immunization',
        'status'       => $data['status'] ?? 'completed',
        'vaccineCode'  => [
            'coding' => [[
                'system'  => 'http://sys-ids.kemkes.go.id/kfa',   // KFA vaksin
                'code'    => $data['kfaCode'],
                'display' => $data['kfaDisplay'],
            ]],
        ],
        'patient'            => ['reference' => 'Patient/'   . $data['patientId']],
        'encounter'          => ['reference' => 'Encounter/' . $data['encounterId']],
        'occurrenceDateTime' => $data['occurrence'] ?? throw new \RuntimeException('occurrence: tanggal wajib dari data'),
        'primarySource'      => true,
        'location'           => ['reference' => 'Location/' . $data['locationId']],
        'lotNumber'          => $data['lotNumber'] ?? null,
        'route' => [
            'coding' => [[
                'system'  => 'http://terminology.hl7.org/CodeSystem/v3-RouteOfAdministration',
                'code'    => $data['routeCode'] ?? 'IM',
                'display' => $data['routeDisplay'] ?? 'Injection, intramuscular',
            ]],
        ],
        'doseQuantity' => [
            'value'  => $data['doseValue'] ?? 0.5,
            'system' => 'http://unitsofmeasure.org',
            'code'   => 'mL',
        ],
        'performer' => [[
            'actor' => ['reference' => 'Practitioner/' . $data['performerId']],
        ]],
    ];
    return $this->makeRequest('post', '/Immunization', $payload);
}
// Sumber SIRUS: perlu modul/riwayat imunisasi baru (KFA vaksin dari master obat).
TXT,

'ss-nutrition' => <<<'TXT'
// NutritionOrder — Instruksi Gizi.  POST /NutritionOrder
public function createNutritionOrder(array $data): array
{
    $payload = [
        'resourceType' => 'NutritionOrder',
        'status'       => $data['status'] ?? 'active',
        'intent'       => 'order',
        'patient'      => ['reference' => 'Patient/'   . $data['patientId']],
        'encounter'    => ['reference' => 'Encounter/' . $data['encounterId']],
        'dateTime'     => $data['dateTime'] ?? throw new \RuntimeException('dateTime: tanggal wajib dari data'),
        'orderer'      => ['reference' => 'Practitioner/' . $data['ordererId']],
        'oralDiet'     => [
            'type' => [[
                'coding' => [[
                    'system'  => 'http://snomed.info/sct',
                    'code'    => $data['dietCode'],       // mis. 435801000124108
                    'display' => $data['dietDisplay'],
                ]],
                'text' => $data['dietText'],              // "Diet rendah garam", dst.
            ]],
        ],
    ];
    return $this->makeRequest('post', '/NutritionOrder', $payload);
}
// Sumber SIRUS: order diet EMR (role Gizi / diet RI).
TXT,

'ss-episode' => <<<'TXT'
// EpisodeOfCare — Episode Perawatan (utamanya RAWAT INAP).  POST /EpisodeOfCare
// Mengelompokkan banyak Encounter dlm satu episode -> Encounter.episodeOfCare[].
public function createEpisodeOfCare(array $data): array
{
    $payload = [
        'resourceType' => 'EpisodeOfCare',
        'identifier'   => [[
            'system' => 'http://sys-ids.kemkes.go.id/episodeofcare/' . $this->organizationId,
            'value'  => $data['episodeNo'],               // mis. rihdr_no
        ]],
        'status' => $data['status'] ?? 'active',          // active | finished | cancelled
        'type'   => [[
            'coding' => [[
                'system'  => 'http://terminology.hl7.org/CodeSystem/episodeofcare-type',
                'code'    => 'hacc',
                'display' => 'Home and Community Care',
            ]],
        ]],
        'patient'              => ['reference' => 'Patient/' . $data['patientId']],
        'managingOrganization' => ['reference' => 'Organization/' . $this->organizationId],
        'period' => array_filter([
            'start' => $data['start'] ?? throw new \RuntimeException('start: tanggal wajib dari data'),
            'end'   => $data['end'] ?? null,              // diisi saat pasien pulang
        ]),
        'careManager' => ['reference' => 'Practitioner/' . $data['careManagerId']],
    ];
    return $this->makeRequest('post', '/EpisodeOfCare', $payload);
}
// Sumber SIRUS: rstxn_rihdrs (satu episode per rawat inap; DPJP = careManager).
TXT,

'ss-clinical' => <<<'TXT'
// ClinicalImpression — Impresi Klinik (asesmen "A" di SOAP).  POST /ClinicalImpression
public function createClinicalImpression(array $data): array
{
    $payload = [
        'resourceType' => 'ClinicalImpression',
        'status'       => $data['status'] ?? 'completed',
        'description'  => $data['description'] ?? null,
        'subject'      => ['reference' => 'Patient/'   . $data['patientId']],
        'encounter'    => ['reference' => 'Encounter/' . $data['encounterId']],
        'effectiveDateTime' => $data['effective'] ?? throw new \RuntimeException('effective: tanggal wajib dari data'),
        'date'         => $data['date'] ?? throw new \RuntimeException('date: tanggal wajib dari data'),
        'assessor'     => ['reference' => 'Practitioner/' . $data['assessorId']],
        'summary'      => $data['summary'],               // teks asesmen klinis
        'finding'      => array_map(fn ($f) => [
            'itemCodeableConcept' => [
                'coding' => [[
                    'system'  => 'http://snomed.info/sct',
                    'code'    => $f['code'],
                    'display' => $f['display'],
                ]],
            ],
        ], $data['findings'] ?? []),
    ];
    return $this->makeRequest('post', '/ClinicalImpression', $payload);
}
// Sumber SIRUS: section Penilaian/Assessment EMR (narasi asesmen dokter).
TXT,

'ss-composition' => <<<'TXT'
// Composition — dokumen klinis terstruktur (dashboard label: "Diet").  POST /Composition
public function createComposition(array $data): array
{
    $payload = [
        'resourceType' => 'Composition',
        'identifier'   => [
            'system' => 'http://sys-ids.kemkes.go.id/composition/' . $this->organizationId,
            'value'  => $data['docNo'],
        ],
        'status' => $data['status'] ?? 'final',
        'type'   => [
            'coding' => [[
                'system'  => 'http://loinc.org',
                'code'    => $data['loincDocType'],       // jenis dokumen (LOINC)
                'display' => $data['loincDisplay'],
            ]],
        ],
        'subject'   => ['reference' => 'Patient/'   . $data['patientId']],
        'encounter' => ['reference' => 'Encounter/' . $data['encounterId']],
        'date'      => $data['date'] ?? throw new \RuntimeException('date: tanggal wajib dari data'),
        'author'    => [['reference' => 'Practitioner/' . $data['authorId']]],
        'title'     => $data['title'],
        'section'   => array_map(fn ($s) => [
            'title' => $s['title'],
            'code'  => ['coding' => [$s['code']]],
            'text'  => ['status' => 'generated', 'div' => $s['html']],
        ], $data['sections'] ?? []),
    ];
    return $this->makeRequest('post', '/Composition', $payload);
}
// Sumber SIRUS: narasi EMR (ringkasan/rencana). Catatan: label "Diet" dari Kemkes.
TXT,

'indeks-kirim' => <<<'TXT'
satusehat: {

  // ---- TETAP ADA, jangan diubah bentuknya --------------------------------
  // Dibaca SatuSehatMonitor (cocok string mentah ke CLOB), indikator status
  // di daftar RJ/UGD, dan kirim-resume-medis.
  "radServiceRequestIds":   ["..."],
  "radObservationIds":      ["..."],
  "radDiagnosticReportIds": ["..."],
  "radImagingStudyIds":     ["..."],

  // ---- penanda kelengkapan yang SESUNGGUHNYA -----------------------------
  // Kunci = identifier yang dipakai saat POST, jadi tiap bagian bisa dilacak
  // sampai ke ordernya. Inilah yang menentukan mana yang masih bolong.
  "radKirim": {
    "rad-673349-11408": { "sr": "uuid", "obs": "uuid", "dr": "uuid", "is": "uuid" }
  },
  "labKirim": {
    "673349-9912": { "sr": "uuid", "sp": "uuid", "obs": ["uuid", "uuid"], "dr": "uuid" }
  }
}

# Awalan kunci mengikuti identifier tiap sender:
#   radiologi : rad-        (RJ)  |  ugd-rad-  |  ri-rad-
#   lab       : (tanpa awalan, RJ)|  ugd-      |  ri-
TXT,

        ];
    }
};

?>

<div>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=source-sans-3:400,500,600,700|jetbrains-mono:400,500&display=swap" rel="stylesheet" />
    <style>[x-cloak] { display: none !important; }</style>

    @php
        $snip = $this->snippets();

        $menuGroups = [
            'Mulai' => [
                'pendahuluan' => 'Pendahuluan',
                'arsitektur'  => 'Arsitektur & 2 Jalur',
                'autentikasi' => 'Autentikasi & Environment',
            ],
            'Pengiriman' => [
                'transport' => 'Transport & Logging',
                'ihs'       => 'Resolusi IHS Code',
                'urutan'    => 'Model & Urutan Kirim',
                'checklist' => 'Checklist & Langkah Kirim',
                'standar'   => 'Standarisasi per Resource',
                'indeks-kirim' => 'Indeks Per-Order Penunjang',
                'ri-ugd'    => 'Status per Modul (RJ/UGD/RI)',
                'coding'    => 'Coding SATUSEHAT & Alur apikesehatan',
            ],
            'Pembaruan 29/10 — PDF Petirs' => [
                'aturan-main'        => 'Aturan Main → Implementasi',
                'siklus-encounter'   => 'Siklus Encounter RJ/UGD/RI',
                'rencana-pemulangan' => 'Discharge Planning & CarePlan',
                'episode-tautan'     => 'EpisodeOfCare & Tautan Kunjungan',
            ],
            'Adopsi' => [
                'dashboard'  => 'Peta Dashboard SATUSEHAT',
                'pacs'       => 'PACS, DICOM Router & ImagingStudy',
                'belum-ada'  => 'Resource Belum Ada — Kirim',
                'uji-kirim'  => 'Pelajaran Uji Kirim',
                'backlog'    => 'Backlog & Gotcha',
                'tambah'     => 'Menambah Resource Baru',
                'glosarium'  => 'Glosarium FHIR',
            ],
        ];

        $labels = array_merge(...array_values($menuGroups));
    @endphp

    <div class="ds" style="min-height:100vh"
        x-data='{
            section: "pendahuluan",
            order: @json(array_keys($labels)),
            labels: @json($labels),
            idx() { return this.order.indexOf(this.section) },
            go(s) {
                this.section = s;
                history.replaceState(null, "", "#" + s);
                window.scrollTo({ top: 0, behavior: "smooth" });
            },
            init() {
                const h = window.location.hash.slice(1);
                if (this.order.includes(h)) this.section = h;
            }
        }'>
        <div class="ds-section" style="padding-top:32px; padding-bottom:96px">

            {{-- ============ HEADER ============ --}}
            <div class="flex items-center justify-between gap-2">
                <div class="flex items-center gap-2">
                    <span class="ds-spike"></span>
                    <span class="ds-title-sm" style="color:var(--ink)">RSI&nbsp;Madinah</span>
                    <a href="{{ route('panduan-dev') }}" wire:navigate
                        class="ds-body-sm hover:underline" style="color:var(--muted-soft)">/ Standarisasi UI</a>
                    <span class="ds-body-sm" style="color:var(--muted-soft)">/ Koding SATUSEHAT</span>
                </div>
                <div class="flex items-center gap-2">
                    <a href="{{ route('panduan-dev.koding-transaksi') }}" wire:navigate
                        class="ds-btn ds-btn-secondary" style="height:34px; padding:6px 12px; font-size:13px">← Tutorial Transaksi</a>
                    <x-theme-toggle />
                </div>
            </div>

            <div class="mt-8 grid grid-cols-1 gap-10 lg:grid-cols-[240px_1fr]">

                {{-- ============ SIDEBAR ============ --}}
                <aside class="self-start lg:sticky lg:top-24">
                    @foreach ($menuGroups as $group => $items)
                        <div class="mb-6">
                            <div class="ds-caption-up mb-2 px-3">{{ $group }}</div>
                            <div class="space-y-0.5">
                                @foreach ($items as $key => $label)
                                    <button type="button" x-on:click="go('{{ $key }}')"
                                        class="block w-full px-3 py-1.5 text-sm text-left rounded-lg transition-colors"
                                        :class="section === '{{ $key }}' ? 'font-semibold' : 'font-normal'"
                                        :style="section === '{{ $key }}'
                                            ? 'background:var(--surface-card); color:var(--ink)'
                                            : 'color:var(--body)'">
                                        {{ $label }}
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    @endforeach

                    <div class="px-3 pt-4" style="border-top:1px solid var(--hairline)">
                        <div class="ds-caption" style="color:var(--muted-soft)">
                            Prasyarat: <a href="{{ route('panduan-dev.koding-transaksi') }}" wire:navigate
                                class="hover:underline" style="color:var(--primary)">Tutorial Koding Transaksi</a><br>
                            Ruang lingkup aktif: <span class="ds-code">transaksi/rj</span> (RJ)<br>
                            Sumber: <span class="ds-code">docs/satusehat-api.md</span>
                        </div>
                    </div>
                </aside>

                {{-- ============ KONTEN ============ --}}
                <main style="min-width:0">

                    @include('pages.panduan-dev.koding-satusehat.koding-satusehat-dasar')

                    @include('pages.panduan-dev.koding-satusehat.koding-satusehat-transport')

                    @include('pages.panduan-dev.koding-satusehat.koding-satusehat-checklist')

                    @include('pages.panduan-dev.koding-satusehat.koding-satusehat-standar')

                    @include('pages.panduan-dev.koding-satusehat.koding-satusehat-indeks-kirim')

                    @include('pages.panduan-dev.koding-satusehat.koding-satusehat-ri-ugd')

                    @include('pages.panduan-dev.koding-satusehat.koding-satusehat-coding')

                    @include('pages.panduan-dev.koding-satusehat.koding-satusehat-petirs')

                    @include('pages.panduan-dev.koding-satusehat.koding-satusehat-dashboard')

                    @include('pages.panduan-dev.koding-satusehat.koding-satusehat-pacs')

                    @include('pages.panduan-dev.koding-satusehat.koding-satusehat-penutup')

                    {{-- ============ PREV / NEXT ============ --}}
                    <div class="flex items-center justify-between gap-3 mt-12 pt-6" style="border-top:1px solid var(--hairline)">
                        <button type="button" class="ds-btn ds-btn-secondary"
                            x-show="idx() > 0" x-cloak
                            x-on:click="go(order[idx() - 1])">
                            ← <span x-text="labels[order[idx() - 1]]"></span>
                        </button>
                        <span x-show="idx() === 0"></span>
                        <button type="button" class="ds-btn ds-btn-primary"
                            x-show="idx() < order.length - 1" x-cloak
                            x-on:click="go(order[idx() + 1])">
                            <span x-text="labels[order[idx() + 1]]"></span> →
                        </button>
                    </div>

                </main>
            </div>
        </div>
    </div>
</div>
