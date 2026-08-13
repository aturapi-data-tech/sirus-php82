<?php

use Livewire\Component;

// Tutorial standarisasi pengiriman data SATUSEHAT (FHIR R4, Kemenkes).
// Gaya sama dgn koding-transaksi/koding-master: sidebar per-submenu,
// snippet = nowdoc (aman compiler Blade). Sumber: docs/satusehat-api.md.
new class extends Component {
    public function snippets(): array
    {
        return [

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

// B) BELUM ada trait (Composition/Diet · ClinicalImpression · ImagingStudy ·
//                      Immunization · EpisodeOfCare · NutritionOrder):
//    1. buat App\Http\Traits\SATUSEHAT\<Resource>Trait meniru ProcedureTrait
//    2. bangun payload FHIR R4 + POST /<Resource> via makeRequest()
//    3. WAJIB referensi Encounter/{id} + subject Patient/{id}

// UJI DI SANDBOX dulu (ganti env AUTH/BASE URL ke -stg).
// Verifikasi via tabel web_log_status (http_req / http_payload / response).
TXT,

'ss-imaging' => <<<'TXT'
// ImagingStudy — Radiologi.  POST /ImagingStudy
// GAP: butuh DICOM UID (study/series/SOP). Modul radiologi kita upload-based,
//      UID DICOM TIDAK tersimpan -> generate OID sendiri atau kirim minimal.
public function createImagingStudy(array $data): array
{
    $payload = [
        'resourceType' => 'ImagingStudy',
        'identifier'   => [[
            'system' => 'urn:dicom:uid',
            'value'  => 'urn:oid:' . $data['studyUid'],
        ]],
        'status'    => 'available',
        'subject'   => ['reference' => 'Patient/'   . $data['patientId']],
        'encounter' => ['reference' => 'Encounter/' . $data['encounterId']],
        'started'   => $data['started'] ?? now()->toIso8601String(),
        'numberOfSeries'    => count($data['series']),
        'numberOfInstances' => $data['numberOfInstances'] ?? 1,
        'referrer'      => ['reference' => 'Practitioner/' . $data['referrerId']],
        'procedureCode' => [[
            'coding' => [[
                'system'  => 'http://hl7.org/fhir/sid/icd-9-cm',   // atau LOINC
                'code'    => $data['procedureCode'],
                'display' => $data['procedureDisplay'],
            ]],
        ]],
        'series' => array_map(fn ($s) => [
            'uid'      => $s['seriesUid'],
            'number'   => $s['number'] ?? 1,
            'modality' => [
                'system'  => 'http://dicom.nema.org/resources/ontology/DCM',
                'code'    => $s['modality'],           // CR, CT, US, MR, ...
                'display' => $s['modalityDisplay'],
            ],
            'numberOfInstances' => count($s['instances'] ?? [1]),
            'started'  => $s['started'] ?? now()->toIso8601String(),
        ], $data['series']),
    ];
    return $this->makeRequest('post', '/ImagingStudy', $payload);
}
// Sumber SIRUS: modul Radiologi (rstxn_*rads / rsview_rads). UID DICOM = gap.
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
        'occurrenceDateTime' => $data['occurrence'] ?? now()->toIso8601String(),
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
        'dateTime'     => $data['dateTime'] ?? now()->toIso8601String(),
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
            'start' => $data['start'] ?? now()->toIso8601String(),
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
        'effectiveDateTime' => $data['effective'] ?? now()->toIso8601String(),
        'date'         => now()->toIso8601String(),
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
        'date'      => $data['date'] ?? now()->toIso8601String(),
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
                'ri-ugd'    => 'Status per Modul (RJ/UGD/RI)',
            ],
            'Adopsi' => [
                'dashboard'  => 'Peta Dashboard SATUSEHAT',
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

                    @include('pages.panduan-dev.koding-satusehat.koding-satusehat-ri-ugd')

                    @include('pages.panduan-dev.koding-satusehat.koding-satusehat-dashboard')

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
