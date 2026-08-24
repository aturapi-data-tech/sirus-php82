<?php

namespace App\Http\Traits\SATUSEHAT;

use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Support\Options\RujukanOptions;

/**
 * SATUSEHAT Rujukan (SRBK) — jalur FHIR LANGSUNG untuk Rawat Inap & Rawat Darurat.
 * Rawat Jalan TIDAK lewat sini (lewat BPJS vclaim-sisrute-rest, lihat SisruteTrait).
 *
 * Alur (Postman "30. Use Case - Rujukan Pasien V30062026"):
 *   1. POST Task referral-pre-request            → pra permintaan kandidat
 *   2. POST Task request-referral-candidate      → pencarian kandidat (Q100 kriteria + Q101 wilayah)
 *   3. GET  Task?_id=...                         → poll kandidat di output[]
 *   4. POST Bundle Task+CarePlan referral-approval → tugas rujukan ke faskes tujuan (Task.owner = tujuan)
 *   5. POST ServiceRequest                       → rujukan; sukses = identifier referral-number-satusehat
 *
 * Sisi FASKES TUJUAN (kotak masuk, lihat §7 di bawah):
 *   6. GET   Task?owner=<org kita>&code=referral-approval-request&_include=Task:based-on
 *   7. PATCH Task/<id>                           → status completed + output accepted/rejected
 *
 * Jebakan terdokumentasi (docs/rujukan-kompetensi.md §3):
 * - Task.identifier.value WAJIB unik SETIAP POST, termasuk retry (reuse → response
 *   tanpa contained/output yang menyesatkan, atau "Found duplicate: Task").
 * - Jejaring wilayah pakai valueCoding (valueString = 0 kandidat); kode wilayah tanpa titik.
 * - Jangan meng-echo extension providerAtribute kandidat ke resource yang dikirim.
 * - Field kosong jangan dikirim (validator menolak objek/field kosong).
 * - Memakai env SATUSEHAT_* yang sama dengan modul SATUSEHAT lain (satu blok, ganti
 *   lingkungan dgn mengomentari blok prod/dto di .env — pola yang sama dipakai VCLAIM,
 *   ANTRIAN, ICARE, APLICARES). Token di-cache terpisah ('satusehat_rujukan_access_token')
 *   supaya menyegarkan token rujukan tidak mengganggu modul lain.
 */
trait SatuSehatRujukanTrait
{
    /* ═══════════════════════════════════════
     | TOKEN & REQUEST
    ═══════════════════════════════════════ */
    protected function rujukanAccessToken()
    {
        return Cache::remember('satusehat_rujukan_access_token', 3500, function () {
            $url = env('SATUSEHAT_AUTH_URL') . "accesstoken?grant_type=client_credentials";

            $response = Http::timeout(10)
                ->withHeaders(['Content-Type' => 'application/x-www-form-urlencoded'])
                ->asForm()
                ->post($url, [
                    'client_id' => env('SATUSEHAT_CLIENT_ID'),
                    'client_secret' => env('SATUSEHAT_SECRET_ID'),
                ]);

            if ($response->successful()) {
                return $response->json()['access_token'];
            }

            throw new \Exception('Gagal ambil token SATUSEHAT Rujukan: ' . $response->body());
        });
    }

    /**
     * Request FHIR rujukan. Return ['code' => int, 'body' => array|string] —
     * TIDAK melempar pada 4xx/5xx supaya komponen bisa memetakan pesan error.
     * Payload & response mentah terekam di web_log_status.
     */
    protected function rujukanRequest(string $method, string $endpoint, ?array $data = null, string $contentType = 'application/json'): array
    {
        $url = env('SATUSEHAT_BASE_URL') . ltrim($endpoint, '/');

        try {
            $token = $this->rujukanAccessToken();
        } catch (\Throwable $e) {
            $this->logRujukan($url, 0, null, $e->getMessage(), null);
            return ['code' => 0, 'body' => $e->getMessage()];
        }

        try {
            $client = Http::timeout(10)->connectTimeout(3)->withToken($token);
            if (strtolower($method) === 'get') {
                $response = $client->get($url);
            } else {
                $response = $client
                    ->withHeaders(['Content-Type' => $contentType])
                    ->withBody(json_encode($data), $contentType)
                    ->send(strtoupper($method), $url);
            }
        } catch (\Throwable $e) {
            $this->logRujukan($url, 0, null, $e->getMessage(), $data !== null ? json_encode($data) : null);
            return ['code' => 0, 'body' => 'Koneksi SATUSEHAT gagal: ' . $e->getMessage()];
        }

        $this->logRujukan(
            $url,
            $response->status(),
            $response->transferStats?->getTransferTime(),
            $response->body(),
            $response->transferStats?->getRequest()?->getBody()?->__toString()
        );

        return ['code' => $response->status(), 'body' => $response->json() ?? $response->body()];
    }

    private function logRujukan(string $url, ?int $code, ?float $requestTransferTime, ?string $responseBody, ?string $payload): void
    {
        DB::table('web_log_status')->insert([
            'code' => $code,
            'date_ref' => Carbon::now(env('APP_TIMEZONE')),
            'response' => $responseBody,
            'http_req' => $url,
            'http_payload' => $payload,
            'requestTransferTime' => $requestTransferTime,
        ]);
    }

    protected function rujukanOrgId(): string
    {
        return (string) env('SATUSEHAT_ORGANIZATION_ID');
    }

    private function rujukanNowIso(): string
    {
        return Carbon::now('UTC')->format('Y-m-d\TH:i:sP');
    }

    /**
     * Tanggal rencana kunjungan (dd/mm/yyyy dari form) → ISO 8601 zona WIB,
     * dipakai ServiceRequest.occurrenceDateTime.
     *
     * Jamnya sengaja 00:00 waktu setempat mengikuti contoh resmi: yang dijanjikan
     * ke faskes tujuan adalah TANGGAL layanan, bukan jam persis. Tanggal tak
     * terbaca → kembalikan string kosong supaya pemanggil jatuh ke now(), bukan
     * diam-diam mengirim epoch 1970.
     */
    protected function rujukanTanggalRencanaIso(?string $tanggal): string
    {
        $tanggal = trim((string) $tanggal);
        if ($tanggal === '') {
            return '';
        }

        // checkdate() DULU, jangan bersandar pada Carbon::createFromFormat: mode
        // lenient-nya menggulung tanggal mustahil tanpa melempar — '31/02/2026'
        // berubah diam-diam jadi 3 Maret, dan rujukan terkirim dengan tanggal
        // rencana yang tidak pernah diketik petugas.
        if (!preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})$#', $tanggal, $bagian)) {
            return '';
        }
        [, $hari, $bulan, $tahun] = $bagian;
        if (!checkdate((int) $bulan, (int) $hari, (int) $tahun)) {
            return '';
        }

        try {
            return Carbon::create((int) $tahun, (int) $bulan, (int) $hari, 0, 0, 0, config('app.timezone'))
                ->format('Y-m-d\TH:i:sP');
        } catch (\Throwable) {
            return '';
        }
    }

    /* ═══════════════════════════════════════
     | 1. TASK PRA PERMINTAAN RUJUKAN
     | $konteks: identifier, encounterId, diagnosaKode, diagnosaDesc
    ═══════════════════════════════════════ */
    protected function rujukanTaskPraPermintaan(array $konteks): array
    {
        $now = $this->rujukanNowIso();
        $task = [
            'resourceType' => 'Task',
            'identifier' => [[
                'system' => 'http://sys-ids.kemkes.go.id/task/' . $this->rujukanOrgId(),
                'value' => $konteks['identifier'],
            ]],
            'status' => 'requested',
            'intent' => 'instance-order',
            'priority' => 'routine',
            'code' => ['coding' => [[
                'system' => 'http://terminology.kemkes.go.id',
                'code' => 'referral-pre-request',
                'display' => 'Referral pre request',
            ]]],
            'authoredOn' => $now,
            'lastModified' => $now,
            'requester' => ['reference' => 'Organization/' . $this->rujukanOrgId()],
            'owner' => ['reference' => 'Organization/' . $this->rujukanOrgId()],
            'encounter' => ['reference' => 'Encounter/' . $konteks['encounterId']],
            'input' => [[
                'type' => ['coding' => [[
                    'system' => 'http://terminology.kemkes.go.id',
                    'code' => 'primary-diagnosis',
                    'display' => 'Primary Diagnosis',
                ]]],
                'valueCoding' => [
                    'system' => 'http://hl7.org/fhir/sid/icd-10',
                    'code' => $konteks['diagnosaKode'],
                    'display' => $konteks['diagnosaDesc'],
                ],
            ]],
        ];

        return $this->rujukanRequest('POST', 'Task', $task);
    }

    /* ═══════════════════════════════════════
     | 2. TASK PENCARIAN KANDIDAT
     | $konteks: jalur ('ranap'|'igd'), identifier, encounterId, patientUuid,
     |     diagnosaKode, diagnosaDesc, wilayah{kodePropinsi,namaPropinsi,kodeKabupaten,namaKabupaten},
     |     kriteria — ranap: {terapi:bool, tindakanIcd9:string, upayaDiagnosis:bool}
     |               — igd:   {q1..q5: bool} (5 pertanyaan GAWAT DARURAT)
     |     diagnosaSekunderKode/Desc (opsional)
    ═══════════════════════════════════════ */
    protected function rujukanTaskPencarianKandidat(array $konteks): array
    {
        $now = $this->rujukanNowIso();
        $idKriteria = 'qr-kriteria-' . $konteks['identifier'];
        $idArea = 'qr-area-' . $konteks['identifier'];

        // ── Q100 kriteria — struktur beda per jalur (Postman V30062026)
        if ($konteks['jalur'] === 'igd') {
            $pertanyaanIgd = [
                '000001' => 'Mengancam nyawa, membahayakan diri dan orang lain/lingkungan',
                '000002' => 'Adanya gangguan pada jalan nafas, pernafasan, dan sirkulasi',
                '000003' => 'Adanya penurunan kesadaran',
                '000004' => 'Adanya gangguan hemodinamik',
                '000005' => 'Memerlukan tindakan segera',
            ];
            $itemQ100 = [[
                'linkId' => '0',
                'text' => 'GAWAT DARURAT',
                'item' => collect($pertanyaanIgd)->map(fn($teks, $linkId) => [
                    'linkId' => $linkId,
                    'text' => $teks,
                    'answer' => [['valueBoolean' => (bool) ($konteks['kriteria'][$linkId] ?? false)]],
                ])->values()->all(),
            ]];
        } else {
            // Ranap: 3 item; satu yang terisi (linkId statis contoh Postman)
            $itemQ100 = [
                [
                    'linkId' => '3216',
                    'text' => 'Terapi/Pengobatan',
                    'answer' => [['valueBoolean' => (bool) ($konteks['kriteria']['terapi'] ?? false)]],
                ],
                [
                    'linkId' => '3215',
                    'text' => 'Tindakan Medis',
                    'answer' => [['valueString' => (string) ($konteks['kriteria']['tindakanIcd9'] ?? '')]],
                ],
                [
                    'linkId' => '3214',
                    'text' => 'Upaya Diagnosis',
                    'answer' => [['valueBoolean' => (bool) ($konteks['kriteria']['upayaDiagnosis'] ?? false)]],
                ],
            ];
        }

        // ── Q101 jejaring wilayah — WAJIB valueCoding, kode tanpa titik
        $itemQ101 = [[
            'linkId' => '1',
            'text' => 'Jejaring wilayah rujukan',
            'item' => [
                [
                    'linkId' => '1.1',
                    'text' => 'Provinsi',
                    'answer' => [['valueCoding' => [
                        'system' => 'http://sys-ids.kemkes.go.id/administrative-area',
                        'code' => $konteks['wilayah']['kodePropinsi'],
                        'display' => $konteks['wilayah']['namaPropinsi'],
                    ]]],
                ],
                [
                    'linkId' => '1.2',
                    'text' => 'Kabupaten/Kota',
                    'answer' => [['valueCoding' => [
                        'system' => 'http://sys-ids.kemkes.go.id/administrative-area',
                        'code' => $konteks['wilayah']['kodeKabupaten'],
                        'display' => $konteks['wilayah']['namaKabupaten'],
                    ]]],
                ],
            ],
        ]];

        $managementProcedure = $konteks['jalur'] === 'igd'
            ? ['code' => '385868005', 'display' => 'Emergency treatment management']
            : ['code' => '737481003', 'display' => 'Inpatient care management'];

        $input = [
            [
                'type' => ['coding' => [[
                    'system' => 'http://terminology.kemkes.go.id',
                    'code' => 'referral-criteria',
                    'display' => 'Referral Criteria',
                ]]],
                'valueReference' => ['reference' => '#' . $idKriteria, 'display' => 'Referral Criteria Response'],
            ],
            [
                'type' => ['coding' => [[
                    'system' => 'http://terminology.kemkes.go.id',
                    'code' => 'area',
                    'display' => 'Area',
                ]]],
                'valueReference' => ['reference' => '#' . $idArea, 'display' => 'Jejaring Wilayah Rujukan'],
            ],
            [
                'type' => ['coding' => [[
                    'system' => 'http://snomed.info/sct',
                    'code' => '119270007',
                    'display' => 'Management procedure',
                ]]],
                'valueCoding' => array_merge(['system' => 'http://snomed.info/sct'], $managementProcedure),
            ],
            [
                'type' => ['coding' => [[
                    'system' => 'http://terminology.kemkes.go.id',
                    'code' => 'primary-diagnosis',
                    'display' => 'Primary Diagnosis',
                ]]],
                'valueCoding' => [
                    'system' => 'http://hl7.org/fhir/sid/icd-10',
                    'code' => $konteks['diagnosaKode'],
                    'display' => $konteks['diagnosaDesc'],
                ],
            ],
        ];
        if (!empty($konteks['diagnosaSekunderKode'])) {
            $input[] = [
                'type' => ['coding' => [[
                    'system' => 'http://terminology.kemkes.go.id',
                    'code' => 'secondary-diagnosis',
                    'display' => 'Secondary diagnosis',
                ]]],
                'valueCoding' => [
                    'system' => 'http://hl7.org/fhir/sid/icd-10',
                    'code' => $konteks['diagnosaSekunderKode'],
                    'display' => $konteks['diagnosaSekunderDesc'] ?? '',
                ],
            ];
        }

        // Kelompok Layanan — variabel playbook v6.0 (Lampiran 4). Mempersempit
        // kandidat ke faskes yang melayani kelompok itu. Opsional: kalau petugas
        // tidak memilih, jangan kirim — kelompok yang salah menyaring kandidat
        // secara diam-diam, dan itu lebih buruk daripada tidak menyaring.
        if (!empty($konteks['kelompokLayananKode'])) {
            $input[] = [
                'type' => ['coding' => [[
                    'system' => 'http://terminology.kemkes.go.id',
                    'code' => 'TK000562',
                    'display' => 'Kelompok Layanan',
                ]]],
                'valueCoding' => [
                    'system' => 'http://terminology.kemkes.go.id',
                    'code' => $konteks['kelompokLayananKode'],
                    'display' => RujukanOptions::kelompokLayananDisplay($konteks['kelompokLayananKode']),
                ],
            ];
        }

        $task = [
            'resourceType' => 'Task',
            'contained' => [
                [
                    'resourceType' => 'QuestionnaireResponse',
                    'id' => $idKriteria,
                    'questionnaire' => 'https://fhir.kemkes.go.id/Questionnaire/Q100',
                    'status' => 'completed',
                    'subject' => ['reference' => 'Patient/' . $konteks['patientUuid']],
                    'encounter' => ['reference' => 'Encounter/' . $konteks['encounterId']],
                    'item' => $itemQ100,
                ],
                [
                    'resourceType' => 'QuestionnaireResponse',
                    'id' => $idArea,
                    'questionnaire' => 'https://fhir.kemkes.go.id/Questionnaire/Q101',
                    'status' => 'completed',
                    'subject' => ['reference' => 'Patient/' . $konteks['patientUuid']],
                    'encounter' => ['reference' => 'Encounter/' . $konteks['encounterId']],
                    'item' => $itemQ101,
                ],
            ],
            'identifier' => [[
                'system' => 'http://sys-ids.kemkes.go.id/task/' . $this->rujukanOrgId(),
                'value' => $konteks['identifier'],
            ]],
            'status' => 'requested',
            'intent' => 'instance-order',
            'priority' => 'routine',
            'code' => ['coding' => [[
                'system' => 'http://terminology.kemkes.go.id',
                'code' => 'request-referral-candidate',
                'display' => 'Request for referral candidate',
            ]]],
            'for' => ['reference' => 'Patient/' . $konteks['patientUuid']],
            'authoredOn' => $now,
            'lastModified' => $now,
            'requester' => ['reference' => 'Organization/' . $this->rujukanOrgId()],
            'owner' => ['reference' => 'Organization/' . $this->rujukanOrgId()],
            'encounter' => ['reference' => 'Encounter/' . $konteks['encounterId']],
            'input' => $input,
        ];

        return $this->rujukanRequest('POST', 'Task', $task);
    }

    /* ═══════════════════════════════════════
     | 3. GET TASK (poll kandidat / status) + parser kandidat
    ═══════════════════════════════════════ */
    protected function rujukanGetTask(string $taskId): array
    {
        return $this->rujukanRequest('GET', 'Task?_id=' . urlencode($taskId));
    }

    /**
     * Ambil resource Task pertama dari response GET (Bundle searchset atau resource langsung).
     */
    protected function rujukanTaskDariResponse($body): ?array
    {
        if (!is_array($body)) {
            return null;
        }
        if (($body['resourceType'] ?? '') === 'Task') {
            return $body;
        }
        foreach ($body['entry'] ?? [] as $entry) {
            if (($entry['resource']['resourceType'] ?? '') === 'Task') {
                return $entry['resource'];
            }
        }
        return null;
    }

    /**
     * Parse kandidat faskes dari Task.output[] — tiap kandidat membawa extension
     * providerAtribute (distance, estimated-time, strata, bpjs-code, kemkes-code,
     * info tempat tidur utk ranap). Parser toleran terhadap variasi bentuk.
     */
    protected function rujukanParseKandidat(?array $task): array
    {
        $kandidatList = [];
        foreach ($task['output'] ?? [] as $output) {
            $referensi = $output['valueReference'] ?? null;
            if (!$referensi) {
                continue;
            }
            $kandidat = [
                'orgId' => str_replace('Organization/', '', (string) ($referensi['reference'] ?? '')),
                'nama' => (string) ($referensi['display'] ?? ''),
                'distance' => '',
                'estimatedTime' => '',
                'strata' => '',
                'bpjsCode' => '',
                'bed' => '',
            ];
            $extensions = array_merge($output['extension'] ?? [], $referensi['extension'] ?? []);
            foreach ($extensions as $extension) {
                $daftar = isset($extension['extension']) && is_array($extension['extension']) ? $extension['extension'] : [$extension];
                foreach ($daftar as $subExtension) {
                    $urlKey = strtolower((string) ($subExtension['url'] ?? ''));
                    $nilai = $subExtension['valueString']
                        ?? ($subExtension['valueCode'] ?? ($subExtension['valueDecimal'] ?? ($subExtension['valueInteger'] ?? ($subExtension['valueQuantity']['value'] ?? null))));
                    if ($nilai === null) {
                        continue;
                    }
                    if (str_contains($urlKey, 'distance')) {
                        $kandidat['distance'] = (string) $nilai;
                    } elseif (str_contains($urlKey, 'time')) {
                        $kandidat['estimatedTime'] = (string) $nilai;
                    } elseif (str_contains($urlKey, 'strata')) {
                        $kandidat['strata'] = (string) $nilai;
                    } elseif (str_contains($urlKey, 'bpjs')) {
                        $kandidat['bpjsCode'] = (string) $nilai;
                    } elseif (str_contains($urlKey, 'bed') || str_contains($urlKey, 'tempat-tidur')) {
                        $kandidat['bed'] = (string) $nilai;
                    }
                }
            }
            if ($kandidat['orgId'] !== '' && $kandidat['orgId'] !== 'accepted') {
                $kandidatList[] = $kandidat;
            }
        }
        return $kandidatList;
    }

    /* ═══════════════════════════════════════
     | 4. BUNDLE TASK + CAREPLAN (referral-approval)
     | $konteks: identifierTask, identifierCarePlan, encounterId, patientUuid, patientName,
     |     practitionerUuid, practitionerName, orgTujuanId, orgTujuanNama,
     |     deskripsi, specialityCode, specialityDisplay, jalur ('ranap'|'igd')
     | Task.owner = Organization TUJUAN (kunci alur approval).
    ═══════════════════════════════════════ */

    /**
     * Kategori CarePlan penentu LAYANAN yang diminta — inilah satu-satunya beda
     * bundle ranap vs gawat darurat di Postman V30062026, dan yang dibaca faskes
     * tujuan untuk tahu permintaan ini masuk ke Ranap atau IGD.
     */
    protected function rujukanKategoriRencana(string $jalur): array
    {
        return $jalur === 'ranap'
            ? ['system' => 'http://snomed.info/sct', 'code' => '736353004', 'display' => 'Inpatient care plan']
            : ['system' => 'http://terminology.kemkes.go.id', 'code' => 'TK000068', 'display' => 'Emergency care plan'];
    }

    protected function rujukanBundleApproval(array $konteks): array
    {
        $now = $this->rujukanNowIso();
        $uuidTask = (string) Str::uuid();
        $uuidCarePlan = (string) Str::uuid();

        $bundle = [
            'resourceType' => 'Bundle',
            'type' => 'transaction',
            'meta' => ['tag' => [[
                'system' => 'http://terminology.kemkes.go.id',
                'code' => 'referral-approval',
                'display' => 'Referral approval',
            ]]],
            'entry' => [
                [
                    'fullUrl' => 'urn:uuid:' . $uuidTask,
                    'resource' => [
                        'resourceType' => 'Task',
                        'identifier' => [[
                            'system' => 'http://sys-ids.kemkes.go.id/task/' . $this->rujukanOrgId(),
                            'value' => $konteks['identifierTask'],
                        ]],
                        'basedOn' => [['reference' => 'urn:uuid:' . $uuidCarePlan]],
                        'status' => 'requested',
                        'intent' => 'instance-order',
                        'priority' => 'routine',
                        'code' => ['coding' => [[
                            'system' => 'http://terminology.kemkes.go.id',
                            'code' => 'referral-approval-request',
                            'display' => 'Referral approval request',
                        ]]],
                        'for' => ['reference' => 'Patient/' . $konteks['patientUuid']],
                        'executionPeriod' => ['start' => $now],
                        'authoredOn' => $now,
                        'lastModified' => $now,
                        'requester' => ['reference' => 'Organization/' . $this->rujukanOrgId()],
                        'owner' => ['reference' => 'Organization/' . $konteks['orgTujuanId']],
                        'encounter' => ['reference' => 'Encounter/' . $konteks['encounterId']],
                        'input' => [[
                            'type' => [
                                'coding' => [[
                                    'system' => 'http://terminology.kemkes.go.id',
                                    'code' => 'referral-task',
                                    'display' => 'Referral Task',
                                ]],
                                'text' => 'Penugasan Task Rujukan',
                            ],
                            'valueReference' => [
                                'reference' => 'Organization/' . $konteks['orgTujuanId'],
                                'display' => $konteks['orgTujuanNama'],
                            ],
                        ]],
                    ],
                    'request' => ['method' => 'POST', 'url' => 'Task'],
                ],
                [
                    'fullUrl' => 'urn:uuid:' . $uuidCarePlan,
                    'resource' => [
                        'resourceType' => 'CarePlan',
                        'identifier' => [
                            [
                                'system' => 'http://sys-ids.kemkes.go.id/careplan/' . $this->rujukanOrgId(),
                                'value' => $konteks['identifierCarePlan'],
                            ],
                            [
                                'system' => 'http://sys-ids.kemkes.go.id/careplan/authoring-organization',
                                'value' => $this->rujukanOrgId(),
                            ],
                        ],
                        'status' => 'active',
                        'intent' => 'plan',
                        'category' => [
                            ['coding' => [$this->rujukanKategoriRencana($konteks['jalur'] ?? 'igd')]],
                            ['coding' => [[
                                'system' => 'http://snomed.info/sct',
                                'code' => '3457005',
                                'display' => 'Patient referral',
                            ]]],
                        ],
                        'title' => 'Rencana Rujukan Pasien',
                        'description' => $konteks['deskripsi'],
                        'subject' => [
                            'reference' => 'Patient/' . $konteks['patientUuid'],
                            'display' => $konteks['patientName'],
                        ],
                        'encounter' => ['reference' => 'Encounter/' . $konteks['encounterId']],
                        'created' => $now,
                        'author' => [
                            'reference' => 'Practitioner/' . $konteks['practitionerUuid'],
                            'display' => $konteks['practitionerName'],
                        ],
                        'contributor' => [['reference' => 'Organization/' . $this->rujukanOrgId()]],
                        'activity' => [[
                            'detail' => [
                                'kind' => 'ServiceRequest',
                                'code' => [
                                    'coding' => [[
                                        'system' => 'http://terminology.kemkes.go.id/CodeSystem/clinical-speciality',
                                        'code' => $konteks['specialityCode'],
                                        'display' => $konteks['specialityDisplay'],
                                    ]],
                                    'text' => 'Permintaan Layanan ' . $konteks['specialityDisplay'],
                                ],
                                'status' => 'not-started',
                            ],
                        ]],
                    ],
                    'request' => ['method' => 'POST', 'url' => 'CarePlan'],
                ],
            ],
        ];

        return $this->rujukanRequest('POST', '', $bundle);
    }

    /**
     * Ambil id resource hasil Bundle transaction-response per tipe.
     */
    protected function rujukanIdDariBundleResponse($body, string $resourceType): string
    {
        foreach ((is_array($body) ? $body['entry'] ?? [] : []) as $entry) {
            $resource = $entry['resource'] ?? [];
            if (($resource['resourceType'] ?? '') === $resourceType && !empty($resource['id'])) {
                return (string) $resource['id'];
            }
            $location = (string) ($entry['response']['location'] ?? '');
            if (str_starts_with($location, $resourceType . '/')) {
                return explode('/', $location)[1] ?? '';
            }
        }
        return '';
    }

    /* ═══════════════════════════════════════
     | 5. SERVICEREQUEST (pengiriman rujukan)
     | $konteks: identifier, carePlanId, jalur, deskripsi, patientUuid, encounterId,
     |     orgTujuanId, orgTujuanNama, taskApprovalId (opsional, masuk supportingInfo)
    ═══════════════════════════════════════ */
    protected function rujukanServiceRequest(array $konteks): array
    {
        $kode = $konteks['jalur'] === 'igd'
            ? ['code' => '385868005', 'display' => 'Emergency treatment management']
            : ['code' => '737481003', 'display' => 'Inpatient care management'];

        $serviceRequest = [
            'resourceType' => 'ServiceRequest',
            'identifier' => [[
                'system' => 'http://sys-ids.kemkes.go.id/servicerequest/' . $this->rujukanOrgId(),
                'value' => $konteks['identifier'],
            ]],
            'basedOn' => [['reference' => 'CarePlan/' . $konteks['carePlanId']]],
            'status' => 'active',
            'intent' => 'original-order',
            'priority' => 'stat',
            'category' => [['coding' => [[
                'system' => 'http://snomed.info/sct',
                'code' => '3457005',
                'display' => 'Patient referral',
            ]]]],
            'code' => [
                'coding' => [array_merge(['system' => 'http://snomed.info/sct'], $kode)],
                'text' => $konteks['deskripsi'],
            ],
            'subject' => ['reference' => 'Patient/' . $konteks['patientUuid']],
            'encounter' => ['reference' => 'Encounter/' . $konteks['encounterId']],
            // occurrenceDateTime = KAPAN PASIEN DIRENCANAKAN DILAYANI di faskes
            // tujuan, bukan jam kita menekan kirim. Contoh resmi memakai tanggal
            // sesudah authoredOn Task/CarePlan-nya, jadi mengisinya dengan now()
            // membuat rujukan terbaca "dilayani saat ini juga" untuk pasien yang
            // dijadwalkan besok. Fallback ke now() hanya kalau pemanggil lupa.
            'occurrenceDateTime' => $konteks['occurrenceDateTime'] ?? $this->rujukanNowIso(),
            'requester' => [
                'reference' => 'Organization/' . $this->rujukanOrgId(),
                'display' => (string) env('SATUSEHAT_ORGANIZATION_NAME'),
            ],
            'performer' => [[
                'reference' => 'Organization/' . $konteks['orgTujuanId'],
                'display' => $konteks['orgTujuanNama'],
            ]],
            'locationCode' => [['coding' => [[
                'system' => 'http://terminology.hl7.org/CodeSystem/v3-RoleCode',
                'code' => 'HOSP',
                'display' => 'Hospital',
            ]]]],
            'patientInstruction' => 'Rujukan ke ' . $konteks['orgTujuanNama'],
        ];

        // Jenis Tenaga Kesehatan Pelaksana Rujukan (playbook v6.0). Opsional —
        // lihat RujukanOptions::PERFORMER_TYPE kenapa daftarnya belum lengkap.
        if (!empty($konteks['performerTypeKode'])) {
            $serviceRequest['performerType'] = ['coding' => [[
                'system' => 'http://snomed.info/sct',
                'code' => $konteks['performerTypeKode'],
                'display' => RujukanOptions::PERFORMER_TYPE[$konteks['performerTypeKode']] ?? '',
            ]]];
        }

        // Diagnosis Rujukan = ServiceRequest.reasonReference → resource Condition.
        // Dipungut dari Condition yang SUDAH terkirim di kunjungan ini; kalau modul
        // SATUSEHAT Condition belum dijalankan, daftarnya kosong dan field ini
        // tidak dikirim — jangan mengarang reference ke resource yang tak ada,
        // itu ditolak validator sebagai reference_not_found.
        $conditionIds = array_values(array_filter((array) ($konteks['conditionIds'] ?? [])));
        if ($conditionIds !== []) {
            $serviceRequest['reasonReference'] = array_map(
                fn($conditionId) => ['reference' => 'Condition/' . $conditionId],
                $conditionIds
            );
        }

        if (!empty($konteks['taskApprovalId'])) {
            $serviceRequest['supportingInfo'] = [[
                'display' => 'Task Respon Kandidat Faskes Rujukan',
                'reference' => 'Task/' . $konteks['taskApprovalId'],
            ]];
        }

        return $this->rujukanRequest('POST', 'ServiceRequest', $serviceRequest);
    }

    /**
     * Nomor rujukan SATUSEHAT dari identifier ServiceRequest response.
     * Tanpa nomor ini = GAGAL walau resource terbentuk.
     */
    protected function rujukanNomorDariServiceRequest($body): string
    {
        foreach ((is_array($body) ? $body['identifier'] ?? [] : []) as $identifier) {
            if (str_contains((string) ($identifier['system'] ?? ''), 'referral-number-satusehat')) {
                return (string) ($identifier['value'] ?? '');
            }
        }
        return '';
    }

    /* ═══════════════════════════════════════
     | 6. BATAL — PATCH Task status=cancelled (JSON Patch)
    ═══════════════════════════════════════ */
    protected function rujukanTaskCancel(string $taskId): array
    {
        return $this->rujukanRequest(
            'PATCH',
            'Task/' . $taskId,
            [['op' => 'replace', 'path' => '/status', 'value' => 'cancelled']],
            'application/json-patch+json'
        );
    }

    /* ═══════════════════════════════════════
     | 7. SISI FASKES TUJUAN — persetujuan/penolakan tugas rujukan MASUK
     | Postman: "Faskes Rujukan - Persetujuan/Penolakan Tugas Rujukan"
     | (ada di use case Rawat Inap & Rawat Darurat; Rawat Jalan tidak lewat sini)
    ═══════════════════════════════════════ */

    /**
     * Kotak masuk permintaan rujukan: Task yang owner-nya RS kita.
     * `_include=Task:based-on` menarik sekalian CarePlan-nya — di situlah nama
     * pasien, deskripsi klinis, layanan yang diminta, dan penanda jalur berada.
     */
    protected function rujukanTaskMasuk(): array
    {
        return $this->rujukanRequest(
            'GET',
            'Task?owner=' . urlencode($this->rujukanOrgId())
                . '&code=referral-approval-request&_include=Task:based-on'
        );
    }

    /**
     * Sisi PERUJUK — baca keputusan accepted/rejected dari faskes tujuan.
     * Parameter `encounter` sah sebagai filter (konfirmasi tim SATUSEHAT 14/08/26),
     * jadi tak perlu menyapu seluruh Task RS untuk memantau satu kunjungan.
     *
     * `_include=Task:based-on` ikut seperti di kotak masuk: nama pasien, layanan yang
     * diminta, dan penanda jalur hanya ada di CarePlan, tidak pernah di Task. Di arah
     * KELUAR ini CarePlan-nya kita sendiri yang membuat, jadi mestinya tidak kena
     * sensor consent seperti rujukan masuk — tapi penanganan tersensor tetap dipakai
     * supaya layar tidak bohong kalau ternyata kena juga.
     */
    protected function rujukanTaskByRequester(?string $encounterId = null): array
    {
        $endpoint = 'Task?code=referral-approval-request&requester=' . urlencode($this->rujukanOrgId())
            . '&_include=Task:based-on';
        if (!empty($encounterId)) {
            $endpoint .= '&encounter=' . urlencode($encounterId);
        }

        return $this->rujukanRequest('GET', $endpoint);
    }

    /**
     * Jawab satu tugas rujukan: status jadi completed + output berisi keputusan.
     * JSON Patch, content-type application/json-patch+json (sama dengan pembatalan).
     */
    protected function rujukanTaskRespon(string $taskId, string $keputusan): array
    {
        $keputusan = $keputusan === 'accepted' ? 'accepted' : 'rejected';

        $patch = [
            ['op' => 'replace', 'path' => '/status', 'value' => 'completed'],
            [
                'op' => 'add',
                'path' => '/output',
                'value' => [[
                    'type' => [
                        'coding' => [[
                            'system' => 'http://terminology.kemkes.go.id',
                            'code' => 'response-referral-task',
                            'display' => 'Response referral task',
                        ]],
                        'text' => 'Respon atas Task Rujukan',
                    ],
                    'valueCoding' => [
                        'system' => 'http://hl7.org/fhir/task-status',
                        'code' => $keputusan,
                        'display' => ucfirst($keputusan),
                    ],
                ]],
            ],
        ];

        return $this->rujukanRequest('PATCH', 'Task/' . $taskId, $patch, 'application/json-patch+json');
    }

    /**
     * Bundle searchset (Task + CarePlan hasil _include) → baris siap tampil.
     * Dipakai DUA layar: kotak masuk `Task?owner=<kita>` dan pemantauan rujukan
     * keluar `Task?requester=<kita>`. Bentuk bundle-nya identik, yang berbeda hanya
     * organisasi mana yang menarik untuk ditampilkan — karena itu tidak dipecah dua.
     * Terbaru di atas. Tahan bentuk: CarePlan boleh tidak ikut (baris tetap muncul,
     * kolomnya kosong) supaya permintaan tidak hilang dari kotak masuk hanya
     * karena rencana perawatannya gagal di-include.
     *
     * PENTING — CarePlan sering TIDAK ikut bukan karena perujuk lalai, melainkan
     * karena SATUSEHAT menyensornya: entry pengganti bertipe OperationOutcome
     * berbunyi "No consent available for CarePlan/<id>". Referensi yang disensor
     * dikumpulkan ke `diblokir` supaya UI bisa membedakan "perujuk tidak mengisi"
     * dari "kita tidak boleh membaca" — dua hal yang menuntut tindak lanjut beda.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function rujukanParsePermintaanMasuk($body): array
    {
        if (!is_array($body)) {
            return [];
        }

        $daftarRencana = [];
        $daftarTask = [];
        $diblokir = [];
        foreach ($body['entry'] ?? [] as $entry) {
            $resource = $entry['resource'] ?? [];
            $tipe = $resource['resourceType'] ?? '';
            if ($tipe === 'Task') {
                $daftarTask[] = $resource;
            } elseif ($tipe === 'CarePlan') {
                $daftarRencana[(string) ($resource['id'] ?? '')] = $resource;
            } elseif ($tipe === 'OperationOutcome') {
                $diblokir += $this->rujukanReferensiTersensor($resource);
            }
        }

        $baris = [];
        foreach ($daftarTask as $task) {
            $rencanaId = str_replace('CarePlan/', '', (string) ($task['basedOn'][0]['reference'] ?? ''));
            $rencana = $daftarRencana[$rencanaId] ?? [];
            $layanan = $rencana['activity'][0]['detail']['code'] ?? [];

            $baris[] = [
                'taskId' => (string) ($task['id'] ?? ''),
                'noPermintaan' => (string) ($task['identifier'][0]['value'] ?? ''),
                'statusTask' => (string) ($task['status'] ?? ''),
                'keputusan' => $this->rujukanKeputusanDariTask($task),
                'waktu' => (string) ($task['authoredOn'] ?? ($task['lastModified'] ?? '')),
                'pasienId' => str_replace('Patient/', '', (string) ($task['for']['reference'] ?? '')),
                'pasienNama' => (string) ($rencana['subject']['display'] ?? ''),
                // Dua sisi sekaligus: kotak masuk memakai perujukOrgId (requester),
                // pemantauan rujukan keluar memakai tujuanOrgId (owner). Task yang sama
                // dibaca dari dua arah, jadi keduanya dipungut di satu tempat.
                'perujukOrgId' => str_replace('Organization/', '', (string) ($task['requester']['reference'] ?? '')),
                'tujuanOrgId' => str_replace('Organization/', '', (string) ($task['owner']['reference'] ?? '')),
                'encounterId' => str_replace('Encounter/', '', (string) ($task['encounter']['reference'] ?? '')),
                'diagnosaId' => str_replace('Condition/', '', (string) ($task['reasonReference']['reference'] ?? '')),
                'rencanaId' => $rencanaId,
                'jalur' => $this->rujukanJalurDariRencana($rencana),
                'layananKode' => (string) ($layanan['coding'][0]['code'] ?? ''),
                'layananNama' => (string) ($layanan['coding'][0]['display'] ?? ($layanan['text'] ?? '')),
                'deskripsi' => (string) ($rencana['description'] ?? ''),
                'dokterPerujuk' => (string) ($rencana['author']['display'] ?? ''),
                // Semua kolom di atas yang bersumber CarePlan kosong berjamaah kalau
                // rencananya disensor. Penanda ini yang dipakai blade untuk memilih
                // kalimat "diblokir consent" alih-alih menuduh perujuk tidak mengisi.
                'rencanaDiblokir' => $rencanaId !== '' && !$rencana && isset($diblokir['CarePlan/' . $rencanaId]),
            ];
        }

        usort($baris, fn($a, $b) => strcmp($b['waktu'], $a['waktu']));

        return $baris;
    }

    /**
     * PERMINTAAN yang hilang seluruhnya karena consent — bukan sekadar rencananya.
     *
     * Bundle bisa menyensor Task-nya sendiri, bukan cuma CarePlan:
     *   "No consent available for Task/7a974c5c-…"   (contoh resmi Postman V30062026)
     * Bedanya besar. CarePlan tersensor cuma mengosongkan kolom — barisnya tetap ada
     * dan petugas masih bisa menjawab. TASK tersensor tidak menyisakan baris apa pun:
     * permintaan itu tak pernah tampil, tak bisa dijawab, dan tanpa penanda di layar
     * tidak ada yang tahu ia pernah datang — padahal perujuk menunggu dan disarankan
     * pindah kandidat setelah ±15 menit. Karena itu jumlahnya wajib dimunculkan.
     *
     * @return array<string, string> ['Task/<id>' => alasan]
     */
    protected function rujukanPermintaanTersensor($body): array
    {
        if (!is_array($body)) {
            return [];
        }

        $hasil = [];
        foreach ($body['entry'] ?? [] as $entry) {
            $resource = $entry['resource'] ?? [];
            if (($resource['resourceType'] ?? '') !== 'OperationOutcome') {
                continue;
            }

            foreach ($this->rujukanReferensiTersensor($resource) as $referensi => $alasan) {
                if (str_starts_with($referensi, 'Task/')) {
                    $hasil[$referensi] = $alasan;
                }
            }
        }

        return $hasil;
    }

    /**
     * Referensi yang DISENSOR SATUSEHAT dari sebuah OperationOutcome.
     * Bentuk yang dikirim (diamati langsung 15/08/2026):
     *   issue[].code       = "suppressed"
     *   issue[].details.text = "The operation did not return any information due to consent or privacy rules."
     *   issue[].diagnostics  = "No consent available for CarePlan/a71523c5-…"
     * Referensinya hanya ada di kalimat bebas `diagnostics`, jadi terpaksa dipungut
     * dengan regex — tak ada field terstruktur yang menyebut resource mana.
     * Tipe resource-nya TIDAK selalu CarePlan (Task juga disensor), karena itu
     * regexnya sengaja generik dan penyaringan tipe dikerjakan pemanggil.
     *
     * @return array<string, string> ['<Tipe>/<id>' => alasan]
     */
    protected function rujukanReferensiTersensor(array $outcome): array
    {
        $hasil = [];
        foreach ($outcome['issue'] ?? [] as $issue) {
            $diagnostics = (string) ($issue['diagnostics'] ?? '');
            $alasan = (string) ($issue['details']['text'] ?? $diagnostics);

            if (preg_match('~\b([A-Z][A-Za-z]+)/([A-Za-z0-9\-\.]{1,64})~', $diagnostics, $cocok)) {
                $hasil[$cocok[1] . '/' . $cocok[2]] = $alasan;
            }
        }

        return $hasil;
    }

    /**
     * Jalur yang diminta perujuk, dibaca dari kategori CarePlan
     * (736353004 Inpatient care plan vs TK000068 Emergency care plan).
     */
    protected function rujukanJalurDariRencana(array $rencana): string
    {
        foreach ($rencana['category'] ?? [] as $kategori) {
            foreach ($kategori['coding'] ?? [] as $coding) {
                $kode = (string) ($coding['code'] ?? '');
                if ($kode === '736353004') {
                    return 'ranap';
                }
                if ($kode === 'TK000068') {
                    return 'igd';
                }
            }
        }

        return '';
    }

    /**
     * Keputusan yang sudah tercatat di Task.output — '' berarti belum dijawab.
     */
    protected function rujukanKeputusanDariTask(array $task): string
    {
        foreach ($task['output'] ?? [] as $output) {
            $kode = strtolower((string) ($output['valueCoding']['code'] ?? ''));
            if ($kode === 'accepted' || $kode === 'rejected') {
                return $kode;
            }
        }

        return '';
    }

    /**
     * Nama RS dari Organization. Di-cache 1 hari; kegagalan TIDAK di-cache
     * supaya gangguan sesaat tidak membuat kolom perujuk kosong seharian.
     */
    protected function rujukanNamaOrganisasi(string $orgId): string
    {
        if ($orgId === '') {
            return '';
        }

        $kunci = 'satusehat_rujukan_org_' . $orgId;
        $tersimpan = Cache::get($kunci);
        if (is_string($tersimpan) && $tersimpan !== '') {
            return $tersimpan;
        }

        $hasil = $this->rujukanRequest('GET', 'Organization/' . urlencode($orgId));
        $nama = $hasil['code'] === 200 && is_array($hasil['body']) ? (string) ($hasil['body']['name'] ?? '') : '';
        if ($nama !== '') {
            Cache::put($kunci, $nama, 86400);
        }

        return $nama;
    }
}
