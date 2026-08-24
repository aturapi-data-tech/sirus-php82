<?php

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Ugd\EmrUGDTrait;
use App\Http\Traits\SATUSEHAT\SatuSehatRujukanTrait;
use App\Support\Options\RujukanOptions;

new class extends Component {
    use EmrUGDTrait, SatuSehatRujukanTrait;

    public bool $isFormLocked = false;
    public ?int $rjNo = null;

    // Referensi kunjungan — TIDAK di-bind ke form
    public array $dataDaftarUGD = [];

    // State rujukan — dipersist ke node rujukanKompetensi di JSON UGD
    public array $formRujukan = [];

    public string $infoKandidat = '';

    /* ═══════════════════════════════════════
     | MOUNT & DEFAULT
    ═══════════════════════════════════════ */
    public function mount(): void
    {
        $this->formRujukan = $this->defaultFormRujukan();
        if (empty($this->rjNo)) {
            return;
        }

        $data = $this->findDataUGD($this->rjNo);
        if (empty($data)) {
            return;
        }
        $this->dataDaftarUGD = $data;

        $tersimpan = $data['rujukanKompetensi'] ?? [];
        if (!empty($tersimpan) && is_array($tersimpan)) {
            $this->formRujukan = array_replace($this->defaultFormRujukan(), $tersimpan);
        } else {
            $diagnosisPertama = collect($data['diagnosis'] ?? [])->first();
            $this->formRujukan['kodeDiagnosa'] = $diagnosisPertama['icdX'] ?? ($diagnosisPertama['diagId'] ?? '');
            $this->formRujukan['diagnosaDesc'] = $diagnosisPertama['diagDesc'] ?? '';
        }

        if ($this->checkEmrUGDStatus($this->rjNo)) {
            $this->isFormLocked = true;
        }
    }

    /* ═══════════════════════════════════════
     | OPEN / CLOSE MODAL (pola modul-dokumen: kartu ringkas → tombol → x-modal)
    ═══════════════════════════════════════ */
    public function openModal(): void
    {
        if (empty($this->rjNo)) {
            return;
        }

        // Baca ulang saat dibuka: SEP/Encounter/diagnosa bisa terbit setelah panel
        // pertama kali dirender, jadi prasyarat harus dinilai dari data terkini.
        $data = $this->findDataUGD($this->rjNo);
        if (empty($data)) {
            $this->dispatch('toast', type: 'error', message: 'Data kunjungan tidak ditemukan.');
            return;
        }
        $this->dataDaftarUGD = $data;

        $tersimpan = $data['rujukanKompetensi'] ?? [];
        if (!empty($tersimpan) && is_array($tersimpan)) {
            $this->formRujukan = array_replace($this->defaultFormRujukan(), $tersimpan);
        }

        $this->isFormLocked = $this->checkEmrUGDStatus($this->rjNo);

        $this->dispatch('open-modal', name: 'rujukan-kompetensi-ugd-' . $this->rjNo);
    }

    public function closeModal(): void
    {
        $this->dispatch('close-modal', name: 'rujukan-kompetensi-ugd-' . $this->rjNo);
    }

    private function defaultFormRujukan(): array
    {
        return [
            // Tujuan layanan di RS lain: 'igd' | 'ranap' — menentukan use case FHIR
            'jalur' => 'igd',
            'kodeDiagnosa' => '',
            'diagnosaDesc' => '',
            // 5 pertanyaan GAWAT DARURAT Q100 (IGD tidak ada validasi ICD-9/10)
            'kriteriaIgd' => [
                '000001' => false,
                '000002' => false,
                '000003' => false,
                '000004' => false,
                '000005' => false,
            ],
            // Kriteria ranap: tepat satu — 'terapi' | 'tindakan' | 'upaya'
            'kriteriaPilih' => '',
            'kriteriaIcd9' => '',
            'kodePropinsi' => '35',
            'namaPropinsi' => 'JAWA TIMUR',
            'kodeKabupaten' => '3504',
            'namaKabupaten' => 'KABUPATEN TULUNGAGUNG',
            'deskripsi' => '',
            // clinical-speciality — IGD default L03 Pelayanan Gawat Darurat
            'specialityCode' => 'L03',
            'specialityDisplay' => 'Pelayanan Gawat Darurat',
            // Kelompok Layanan & Jenis Tenaga Kesehatan Pelaksana — variabel
            // playbook v6.0; keduanya OPSIONAL, lihat App\Support\Options\RujukanOptions.
            'kelompokLayananKode' => '',
            'performerTypeKode' => '',
            // Rencana kunjungan di faskes tujuan → ServiceRequest.occurrenceDateTime
            'tglRencanaKunjungan' => now(config('app.timezone'))->format('d/m/Y'),
            'taskKandidatId' => '',
            'kandidatList' => [],
            'kandidatIdx' => null,
            'carePlanId' => '',
            'taskApprovalId' => '',
            // '' = belum dijawab | accepted | rejected
            'statusApproval' => '',
            // faskes yang DIKIRIMI tugas rujukan — pembanding saat menerbitkan ServiceRequest
            'approvalOrgId' => '',
            'approvalOrgNama' => '',
            // identifier yang kita kirim — pegangan menelusuri kalau id server tak terbaca
            'identifierTask' => '',
            'identifierCarePlan' => '',
            'hasil' => [],
        ];
    }

    public function pertanyaanIgd(): array
    {
        return [
            '000001' => 'Mengancam nyawa, membahayakan diri dan orang lain/lingkungan',
            '000002' => 'Adanya gangguan pada jalan nafas, pernafasan, dan sirkulasi',
            '000003' => 'Adanya penurunan kesadaran',
            '000004' => 'Adanya gangguan hemodinamik',
            '000005' => 'Memerlukan tindakan segera',
        ];
    }


    /**
     * Wilayah jejaring rujukan dipilih lewat LOV kabupaten (pola master pasien):
     * satu kali pilih mengisi kabupaten SEKALIGUS propinsinya, jadi pasangan kode
     * tidak mungkin lagi tidak sinkron gara-gara diketik terpisah. Kode di
     * rsmst_kabupatens memang sudah tanpa titik ('3504'/'35'), persis bentuk yang
     * diminta Q101 — tidak perlu dibersihkan lagi.
     */
    #[On('lov.selected.rujukanWilayahUGD')]
    public function onLovWilayahSelected(string $target, array $payload): void
    {
        if ($this->isFormLocked) {
            return;
        }

        $kodeKabupaten = trim((string) ($payload['kab_id'] ?? ''));
        $kodePropinsi = trim((string) ($payload['prop_id'] ?? ''));
        if ($kodeKabupaten === '' || $kodePropinsi === '') {
            $this->dispatch('toast', type: 'error', message: 'Data wilayah tidak lengkap.');
            return;
        }

        $this->formRujukan['kodeKabupaten'] = $kodeKabupaten;
        $this->formRujukan['namaKabupaten'] = trim((string) ($payload['kab_name'] ?? ''));
        $this->formRujukan['kodePropinsi'] = $kodePropinsi;
        $this->formRujukan['namaPropinsi'] = trim((string) ($payload['prop_name'] ?? ''));

        // Wilayah ganti = kandidat lama dihitung dari wilayah lama. Membiarkannya
        // tampil justru menyesatkan (pola sama seperti ganti diagnosa/kriteria).
        $this->formRujukan['kandidatList'] = [];
        $this->formRujukan['kandidatIdx'] = null;
        $this->infoKandidat = '';
    }

    /**
     * Pintasan kode layanan. Mengisi kode DAN namanya sekaligus supaya keduanya
     * tidak pernah berpasangan salah; '' = petugas mau mengetik manual.
     */
    public function pilihSpeciality(string $kode): void
    {
        if ($this->isFormLocked || $kode === '') {
            return;
        }
        $this->formRujukan['specialityCode'] = $kode;
        $this->formRujukan['specialityDisplay'] = $this->specialityOptions()[$kode] ?? '';
    }

    public function specialityOptions(): array
    {
        return RujukanOptions::CLINICAL_SPECIALITY;
    }

    /**
     * Keadaan tiap langkah alur rujukan — DIHITUNG dari data, bukan disimpan.
     * Menyimpan "langkah aktif" sebagai state tersendiri membuat stepper bisa
     * berbohong saat data berubah dari jalur lain (mis. kandidat ter-reset).
     *
     * Dipakai x-stepper supaya hubungan Tugas Rujukan -> Persetujuan -> Rujukan
     * terbaca: dua tombol kirim itu langkah BERBEDA, bukan pilihan.
     */
    public function langkahRujukan(): array
    {
        $sudahKirim = !empty($this->formRujukan['hasil']['noRujukanSatuSehat']);
        $adaTugas = !empty($this->formRujukan['carePlanId']);
        $adaKandidat = ($this->formRujukan['kandidatIdx'] ?? null) !== null;
        $statusApproval = (string) ($this->formRujukan['statusApproval'] ?? '');
        $keRanap = ($this->formRujukan['jalur'] ?? 'igd') === 'ranap';

        $kriteriaTerisi = $keRanap
            ? ($this->formRujukan['kriteriaPilih'] !== ''
                && ($this->formRujukan['kriteriaPilih'] !== 'tindakan' || trim((string) $this->formRujukan['kriteriaIcd9']) !== ''))
            : collect($this->formRujukan['kriteriaIgd'] ?? [])->contains(true);
        $dasarTerisi = trim((string) $this->formRujukan['kodeDiagnosa']) !== '' && $kriteriaTerisi;

        $keadaanLangkah = fn(bool $selesai, bool $aktif) => $selesai ? 'done' : ($aktif ? 'current' : 'todo');

        return [
            [
                'n' => 1,
                'title' => 'Diagnosa & Kriteria',
                'hint' => $dasarTerisi ? null : 'wajib diisi',
                'state' => $keadaanLangkah($dasarTerisi, true),
            ],
            [
                'n' => 2,
                'title' => 'Pilih Kandidat',
                'hint' => $adaKandidat ? ($this->formRujukan['kandidatList'][$this->formRujukan['kandidatIdx']]['nama'] ?? null) : null,
                'state' => $keadaanLangkah($adaKandidat, $dasarTerisi),
            ],
            [
                'n' => 3,
                'title' => 'Kirim Tugas Rujukan',
                'hint' => $adaTugas ? 'terkirim' : 'minta kesediaan faskes',
                'state' => $keadaanLangkah($adaTugas, $adaKandidat),
            ],
            [
                'n' => 4,
                'title' => 'Persetujuan Faskes',
                'hint' => match ($statusApproval) {
                    'accepted' => 'diterima',
                    'rejected' => 'ditolak — pilih faskes lain',
                    default => $adaTugas ? 'belum dijawab' : null,
                },
                'state' => $statusApproval === 'rejected'
                    ? 'error'
                    : $keadaanLangkah($statusApproval === 'accepted', $adaTugas),
            ],
            [
                'n' => 5,
                'title' => 'Kirim Rujukan',
                'hint' => $sudahKirim ? 'No. ' . $this->formRujukan['hasil']['noRujukanSatuSehat'] : 'terbit nomor rujukan',
                // Aktif hanya setelah faskes menerima — supaya tidak ada dua langkah
                // menyala bersamaan. Menerbitkan rujukan tanpa menunggu jawaban TETAP
                // diizinkan (lihat kirimRujukan), stepper cuma menunjukkan alur idealnya.
                'state' => $keadaanLangkah($sudahKirim, $statusApproval === 'accepted'),
            ],
        ];
    }

    /**
     * Opsi terminologi dibaca lewat method komponen, BUKAN RujukanOptions:: langsung
     * di template — blok <?php SFC dan template Volt dikompilasi terpisah, jadi
     * `use` di atas tidak menjangkau zona template (skill naming-conventions §2).
     */
    public function kelompokLayananOptions(): array
    {
        return RujukanOptions::KELOMPOK_LAYANAN;
    }

    public function performerTypeOptions(): array
    {
        return RujukanOptions::PERFORMER_TYPE;
    }

    /* ═══════════════════════════════════════
     | PRASYARAT
    ═══════════════════════════════════════ */
    public function prasyaratKurang(): array
    {
        if (empty($this->rjNo)) {
            return [];
        }
        $kurang = [];
        if (empty(env('SATUSEHAT_CLIENT_ID'))) {
            $kurang[] = 'Credential SATUSEHAT Rujukan (SATUSEHAT_CLIENT_ID) belum diset';
        }
        if (empty(env('SATUSEHAT_ORGANIZATION_ID'))) {
            $kurang[] = 'SATUSEHAT_ORGANIZATION_ID belum diset';
        }
        if (empty($this->encounterUuid())) {
            $kurang[] = 'Encounter SATUSEHAT UGD belum terkirim (menu Satu Sehat → Encounter)';
        }
        if (empty($this->patientUuid())) {
            $kurang[] = 'IHS Pasien (patient_uuid) kosong di Master Pasien';
        }
        if (empty($this->dokterUuid())) {
            $kurang[] = 'IHS Dokter (dr_uuid) kosong di Master Dokter';
        }
        return $kurang;
    }

    private function encounterUuid(): string
    {
        return (string) ($this->dataDaftarUGD['satusehat']['encounterId'] ?? '');
    }

    private function patientUuid(): string
    {
        $regNo = $this->dataDaftarUGD['regNo'] ?? '';
        return $regNo === '' ? '' : (string) (DB::table('rsmst_pasiens')->where('reg_no', $regNo)->value('patient_uuid') ?? '');
    }

    private function dokterUuid(): string
    {
        $drId = $this->dataDaftarUGD['drId'] ?? '';
        return $drId === '' ? '' : (string) (DB::table('rsmst_doctors')->where('dr_id', $drId)->value('dr_uuid') ?? '');
    }

    private function dokterNama(): string
    {
        $drId = $this->dataDaftarUGD['drId'] ?? '';
        return $drId === '' ? '' : (string) (DB::table('rsmst_doctors')->where('dr_id', $drId)->value('dr_name') ?? '');
    }

    /* ═══════════════════════════════════════
     | DIAGNOSA
    ═══════════════════════════════════════ */
    public function pilihDiagnosa(int $index): void
    {
        $diagnosa = $this->dataDaftarUGD['diagnosis'][$index] ?? null;
        if (!$diagnosa) {
            return;
        }
        $this->setDiagnosaRujukan($diagnosa['icdX'] ?? ($diagnosa['diagId'] ?? ''), $diagnosa['diagDesc'] ?? '');
    }

    #[On('lov.selected.rujukanKompetensiDiagnosaUGD')]
    public function onLovDiagnosaSelected(string $target, array $payload): void
    {
        if ($this->isFormLocked) {
            return;
        }
        $icdx = $payload['icdx'] ?? ($payload['diag_id'] ?? '');
        if ($icdx === '') {
            $this->dispatch('toast', type: 'error', message: 'Data diagnosa tidak valid.');
            return;
        }
        $this->setDiagnosaRujukan($icdx, $payload['diag_desc'] ?? ($payload['description'] ?? ''));
    }

    private function setDiagnosaRujukan(string $kodeDiagnosa, string $diagnosaDesc): void
    {
        $this->formRujukan['kodeDiagnosa'] = $kodeDiagnosa;
        $this->formRujukan['diagnosaDesc'] = $diagnosaDesc;
        $this->formRujukan['taskKandidatId'] = '';
        $this->formRujukan['kandidatList'] = [];
        $this->formRujukan['kandidatIdx'] = null;
        $this->infoKandidat = '';
    }

    // Ganti tujuan IGD↔Ranap → kriteria & kandidat lama tidak berlaku
    public function updatedFormRujukanJalur(): void
    {
        $this->formRujukan['taskKandidatId'] = '';
        $this->formRujukan['kandidatList'] = [];
        $this->formRujukan['kandidatIdx'] = null;
        $this->infoKandidat = '';
        if ($this->formRujukan['jalur'] === 'igd') {
            $this->formRujukan['specialityCode'] = 'L03';
            $this->formRujukan['specialityDisplay'] = 'Pelayanan Gawat Darurat';
        } else {
            $this->formRujukan['specialityCode'] = '';
            $this->formRujukan['specialityDisplay'] = '';
        }
    }

    /* ═══════════════════════════════════════
     | LANGKAH 1 — CARI KANDIDAT
    ═══════════════════════════════════════ */
    public function cariKandidat(): void
    {
        $this->infoKandidat = '';
        $kurang = $this->prasyaratKurang();
        if (!empty($kurang)) {
            $this->dispatch('toast', type: 'error', message: 'Data belum siap: ' . implode('; ', $kurang) . '.');
            return;
        }
        if (trim($this->formRujukan['kodeDiagnosa'] ?? '') === '') {
            $this->dispatch('toast', type: 'error', message: 'Pilih diagnosa dulu.');
            return;
        }
        $keRanap = $this->formRujukan['jalur'] === 'ranap';
        if ($keRanap) {
            if (!preg_match('/^[A-Z][0-9]{2}\.[0-9]{1,2}$/', $this->formRujukan['kodeDiagnosa'] ?? '')) {
                $this->dispatch('toast', type: 'error', message: 'Tujuan ranap: kode diagnosa harus ICD-10 rinci ber-titik (contoh I61.9).');
                return;
            }
            if (!in_array($this->formRujukan['kriteriaPilih'], ['terapi', 'tindakan', 'upaya'], true)) {
                $this->dispatch('toast', type: 'error', message: 'Tujuan ranap: pilih TEPAT SATU kriteria rujukan dulu.');
                return;
            }
            if ($this->formRujukan['kriteriaPilih'] === 'tindakan' && trim($this->formRujukan['kriteriaIcd9']) === '') {
                $this->dispatch('toast', type: 'error', message: 'Kriteria Tindakan Medis butuh kode ICD-9-CM.');
                return;
            }
        } elseif (!collect($this->formRujukan['kriteriaIgd'])->contains(true)) {
            $this->dispatch('toast', type: 'error', message: 'Centang minimal satu kriteria gawat darurat.');
            return;
        }

        // Identifier WAJIB unik SETIAP POST — termasuk retry
        $praPermintaan = $this->rujukanTaskPraPermintaan([
            'identifier' => (string) Str::uuid(),
            'encounterId' => $this->encounterUuid(),
            'diagnosaKode' => $this->formRujukan['kodeDiagnosa'],
            'diagnosaDesc' => $this->formRujukan['diagnosaDesc'],
        ]);
        if ($praPermintaan['code'] < 200 || $praPermintaan['code'] >= 300) {
            $this->dispatch('toast', type: 'error', message: 'Pra permintaan gagal [' . $praPermintaan['code'] . '] ' . $this->ringkasError($praPermintaan['body']));
            return;
        }

        $kandidat = $this->rujukanTaskPencarianKandidat([
            'kelompokLayananKode' => $this->formRujukan['kelompokLayananKode'],
            'jalur' => $keRanap ? 'ranap' : 'igd',
            'identifier' => (string) Str::uuid(),
            'encounterId' => $this->encounterUuid(),
            'patientUuid' => $this->patientUuid(),
            'diagnosaKode' => $this->formRujukan['kodeDiagnosa'],
            'diagnosaDesc' => $this->formRujukan['diagnosaDesc'],
            'wilayah' => [
                'kodePropinsi' => $this->formRujukan['kodePropinsi'],
                'namaPropinsi' => $this->formRujukan['namaPropinsi'],
                'kodeKabupaten' => $this->formRujukan['kodeKabupaten'],
                'namaKabupaten' => $this->formRujukan['namaKabupaten'],
            ],
            'kriteria' => $keRanap
                ? [
                    'terapi' => $this->formRujukan['kriteriaPilih'] === 'terapi',
                    'tindakanIcd9' => $this->formRujukan['kriteriaPilih'] === 'tindakan' ? trim($this->formRujukan['kriteriaIcd9']) : '',
                    'upayaDiagnosis' => $this->formRujukan['kriteriaPilih'] === 'upaya',
                ]
                : $this->formRujukan['kriteriaIgd'],
        ]);
        if ($kandidat['code'] < 200 || $kandidat['code'] >= 300) {
            $this->dispatch('toast', type: 'error', message: 'Pencarian kandidat gagal [' . $kandidat['code'] . '] ' . $this->ringkasError($kandidat['body']));
            return;
        }

        $this->formRujukan['taskKandidatId'] = (string) ($kandidat['body']['id'] ?? '');
        $this->formRujukan['kandidatList'] = $this->rujukanParseKandidat($kandidat['body']);
        $this->formRujukan['kandidatIdx'] = null;
        $this->simpanDraft();

        $this->infoKandidat = empty($this->formRujukan['kandidatList']) ? 'Permintaan kandidat terkirim (Task ' . $this->formRujukan['taskKandidatId'] . ') — kandidat belum keluar, klik "Cek Hasil Kandidat" beberapa saat lagi.' : '✓ ' . count($this->formRujukan['kandidatList']) . ' kandidat ditemukan — pilih salah satu.';
    }

    public function cekKandidat(): void
    {
        if (empty($this->formRujukan['taskKandidatId'])) {
            $this->dispatch('toast', type: 'error', message: 'Belum ada Task pencarian kandidat — jalankan Cari Kandidat dulu.');
            return;
        }
        $respon = $this->rujukanGetTask($this->formRujukan['taskKandidatId']);
        if ($respon['code'] < 200 || $respon['code'] >= 300) {
            $this->dispatch('toast', type: 'error', message: 'Cek kandidat gagal [' . $respon['code'] . '] ' . $this->ringkasError($respon['body']));
            return;
        }
        $task = $this->rujukanTaskDariResponse($respon['body']);
        $this->formRujukan['kandidatList'] = $this->rujukanParseKandidat($task);
        $this->simpanDraft();

        $status = (string) ($task['status'] ?? '-');
        $this->infoKandidat = empty($this->formRujukan['kandidatList']) ? "Status Task: {$status} — kandidat belum tersedia, coba cek lagi. (Tanpa kandidat sama sekali = memang tidak ada faskes yang cocok.)" : '✓ ' . count($this->formRujukan['kandidatList']) . " kandidat (status Task: {$status}) — pilih salah satu.";
    }

    // Kirim INDEKS, bukan string (aman dari double-escape argumen)
    public function pilihKandidat(int $index): void
    {
        $kandidat = $this->formRujukan['kandidatList'][$index] ?? null;
        if (!$kandidat) {
            return;
        }
        $this->formRujukan['kandidatIdx'] = $index;
        $this->infoKandidat = "Tujuan: {$kandidat['nama']} (Org {$kandidat['orgId']})";
    }

    /* ═══════════════════════════════════════
     | LANGKAH 2 — KIRIM TUGAS RUJUKAN (Bundle Task+CarePlan)
    ═══════════════════════════════════════ */
    public function kirimTugasRujukan(): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form read-only.');
            return;
        }
        $kandidat = $this->formRujukan['kandidatList'][$this->formRujukan['kandidatIdx'] ?? -1] ?? null;
        if (!$kandidat) {
            $this->dispatch('toast', type: 'error', message: 'Pilih kandidat faskes tujuan dulu.');
            return;
        }

        $keRanap = $this->formRujukan['jalur'] === 'ranap';
        if ($keRanap && trim($this->formRujukan['specialityCode']) === '') {
            $this->dispatch('toast', type: 'error', message: 'Tujuan ranap: isi kode layanan clinical-speciality dulu (mis. LY133).');
            return;
        }
        $labelJalur = $keRanap ? 'rawat inap' : 'gawat darurat';

        $identifierTask = (string) Str::uuid();
        $identifierCarePlan = (string) Str::uuid();

        $respon = $this->rujukanBundleApproval([
            'identifierTask' => $identifierTask,
            'identifierCarePlan' => $identifierCarePlan,
            'encounterId' => $this->encounterUuid(),
            'patientUuid' => $this->patientUuid(),
            'patientName' => (string) ($this->dataDaftarUGD['regName'] ?? ''),
            'practitionerUuid' => $this->dokterUuid(),
            'practitionerName' => $this->dokterNama(),
            'orgTujuanId' => $kandidat['orgId'],
            'orgTujuanNama' => $kandidat['nama'],
            'jalur' => $keRanap ? 'ranap' : 'igd',
            'deskripsi' => trim($this->formRujukan['deskripsi']) !== '' ? trim($this->formRujukan['deskripsi']) : 'Rujukan ' . $labelJalur . ' — ' . $this->formRujukan['kodeDiagnosa'] . ' ' . $this->formRujukan['diagnosaDesc'],
            'specialityCode' => trim($this->formRujukan['specialityCode']) !== '' ? trim($this->formRujukan['specialityCode']) : 'L03',
            'specialityDisplay' => trim($this->formRujukan['specialityDisplay']) !== '' ? trim($this->formRujukan['specialityDisplay']) : 'Pelayanan Gawat Darurat',
        ]);
        if ($respon['code'] < 200 || $respon['code'] >= 300) {
            $this->dispatch('toast', type: 'error', message: 'Kirim tugas rujukan gagal [' . $respon['code'] . '] ' . $this->ringkasError($respon['body']));
            return;
        }

        $carePlanId = $this->rujukanIdDariBundleResponse($respon['body'], 'CarePlan');
        $taskId = $this->rujukanIdDariBundleResponse($respon['body'], 'Task');

        $this->formRujukan['carePlanId'] = $carePlanId;
        $this->formRujukan['taskApprovalId'] = $taskId;
        $this->formRujukan['statusApproval'] = '';
        $this->formRujukan['approvalOrgId'] = (string) $kandidat['orgId'];
        $this->formRujukan['approvalOrgNama'] = (string) $kandidat['nama'];
        // Identifier yang KITA kirim disimpan sebagai jejak: kalau id server gagal
        // terbaca, inilah satu-satunya pegangan untuk menelusuri resource-nya.
        $this->formRujukan['identifierTask'] = $identifierTask;
        $this->formRujukan['identifierCarePlan'] = $identifierCarePlan;
        $this->simpanDraft('Kirim tugas rujukan ' . $labelJalur . ' → ' . $kandidat['nama']);

        // Bundle diterima TAPI id-nya tak terbaca: tugas rujukan SUDAH ada di
        // faskes tujuan, jadi jangan bilang sukses (petugas akan lanjut lalu
        // mentok) dan jangan pula suruh kirim ulang (menumpuk duplikat di sana).
        if ($carePlanId === '' || $taskId === '') {
            $this->dispatch('toast', type: 'error', message: 'Tugas rujukan TERKIRIM ke ' . $kandidat['nama'] . ', tapi id CarePlan/Task tidak terbaca dari balasan SATUSEHAT. JANGAN kirim ulang — tekan "Pulihkan ID Tugas Rujukan" untuk mengambilnya kembali.');
            return;
        }

        $this->dispatch('toast', type: 'success', message: 'Tugas rujukan terkirim ke ' . $kandidat['nama'] . ' — lanjut Kirim Rujukan (staging boleh tanpa menunggu approval).');
    }

    /* ═══════════════════════════════════════
     | LANGKAH 3 — KIRIM RUJUKAN (ServiceRequest)
    ═══════════════════════════════════════ */
    /* ═══════════════════════════════════════
     | STATUS PERSETUJUAN FASKES TUJUAN
     |
     | Tugas rujukan (langkah 1) hanya MENANYAKAN kesediaan; jawabannya tercatat di
     | Task.output faskes tujuan. Nilainya dibaca ULANG dari server, bukan dari state
     | lokal — jawaban datang dari sistem RS lain, jadi state kita selalu bisa basi.
    ═══════════════════════════════════════ */
    private function ambilStatusApproval(): array
    {
        $taskId = trim((string) ($this->formRujukan['taskApprovalId'] ?? ''));
        if ($taskId === '') {
            return ['status' => '', 'terverifikasi' => false];
        }

        try {
            $respon = $this->rujukanGetTask($taskId);
            if ($respon['code'] < 200 || $respon['code'] >= 300) {
                return ['status' => (string) ($this->formRujukan['statusApproval'] ?? ''), 'terverifikasi' => false];
            }

            $task = $this->rujukanTaskDariResponse($respon['body']);
            if (!$task) {
                return ['status' => (string) ($this->formRujukan['statusApproval'] ?? ''), 'terverifikasi' => false];
            }

            return ['status' => $this->rujukanKeputusanDariTask($task), 'terverifikasi' => true];
        } catch (\Throwable) {
            // Gangguan jaringan JANGAN menghapus keputusan yang sudah diketahui —
            // pakai catatan terakhir, tapi tandai belum terverifikasi.
            return ['status' => (string) ($this->formRujukan['statusApproval'] ?? ''), 'terverifikasi' => false];
        }
    }

    public function cekStatusApproval(): void
    {
        if (trim((string) ($this->formRujukan['taskApprovalId'] ?? '')) === '') {
            $this->dispatch('toast', type: 'error', message: 'Kirim Tugas Rujukan dulu — belum ada tugas yang bisa dicek.');
            return;
        }

        $statusTerbaca = $this->ambilStatusApproval();
        if (!$statusTerbaca['terverifikasi']) {
            $this->dispatch('toast', type: 'error', message: 'Gagal membaca status dari SATUSEHAT (gangguan/kuota). Coba lagi nanti.');
            return;
        }

        $this->formRujukan['statusApproval'] = $statusTerbaca['status'];
        $this->simpanDraft('Cek status persetujuan rujukan: ' . ($statusTerbaca['status'] ?: 'belum dijawab'));

        $this->dispatch('toast', type: $statusTerbaca['status'] === 'rejected' ? 'error' : 'success', message: match ($statusTerbaca['status']) {
            'accepted' => 'Faskes tujuan MENERIMA rujukan — silakan lanjut Kirim Rujukan.',
            'rejected' => 'Faskes tujuan MENOLAK rujukan. Pilih kandidat lain, jangan diteruskan.',
            default => 'Faskes tujuan belum menjawab.',
        });
    }

    /**
     * Ambil kembali id Task/CarePlan tugas rujukan yang sudah telanjur terkirim.
     * Alternatif dari mengirim ulang, yang akan menumpuk duplikat di faskes tujuan.
     */
    public function pulihkanTugasRujukan(): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form read-only.');
            return;
        }
        if (!empty($this->formRujukan['carePlanId']) && !empty($this->formRujukan['taskApprovalId'])) {
            $this->dispatch('toast', type: 'success', message: 'Id tugas rujukan sudah lengkap, tidak perlu dipulihkan.');
            return;
        }

        $tugasDitemukan = $this->rujukanPulihkanTugasTerakhir($this->encounterUuid());
        if (!$tugasDitemukan['ditemukan']) {
            $this->dispatch('toast', type: 'error', message: 'Tugas rujukan tidak ditemukan di SATUSEHAT (bisa jadi gangguan koneksi). Coba lagi nanti — jangan kirim ulang dulu.');
            return;
        }
        if ($tugasDitemukan['carePlanId'] === '' || $tugasDitemukan['taskId'] === '') {
            $this->dispatch('toast', type: 'error', message: 'Task ditemukan tapi CarePlan-nya tidak terbaca — laporkan Task ' . $tugasDitemukan['taskId'] . ' ke tim SATUSEHAT.');
            return;
        }

        $this->formRujukan['taskApprovalId'] = $tugasDitemukan['taskId'];
        $this->formRujukan['carePlanId'] = $tugasDitemukan['carePlanId'];
        if ($tugasDitemukan['ownerOrgId'] !== '') {
            $this->formRujukan['approvalOrgId'] = $tugasDitemukan['ownerOrgId'];
        }
        $this->simpanDraft('Pulihkan id tugas rujukan (Task ' . $tugasDitemukan['taskId'] . ')');
        $this->dispatch('toast', type: 'success', message: 'Id tugas rujukan dipulihkan — silakan lanjut Kirim Rujukan.');
    }

    public function kirimRujukan(): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form read-only.');
            return;
        }
        // Dua sebab yang dulu digabung jadi satu pesan "Kirim Tugas Rujukan dulu",
        // padahal obatnya beda: kandidat belum dipilih vs tugas rujukan belum dikirim.
        // Petugas jadi bolak-balik ditolak dua tombol yang saling menunjuk.
        $kandidat = $this->formRujukan['kandidatList'][$this->formRujukan['kandidatIdx'] ?? -1] ?? null;
        if (!$kandidat) {
            $this->dispatch('toast', type: 'error', message: 'Pilih kandidat faskes tujuan dulu (cari kandidat di Langkah 1).');
            return;
        }
        if (empty($this->formRujukan['carePlanId'])) {
            $this->dispatch('toast', type: 'error', message: 'Kirim Tugas Rujukan dulu (butuh CarePlan sebagai basedOn).');
            return;
        }

        // Tugas rujukan terkirim ke SATU faskes (Task.owner). Menerbitkan
        // ServiceRequest dengan performer faskes LAIN membuat rujukan menggantung:
        // yang diminta persetujuan A, yang dirujuk B. Bisa terjadi kalau kandidat
        // dicari ulang (mis. setelah ganti wilayah) lalu dipilih faskes berbeda.
        $orgTugas = trim((string) ($this->formRujukan['approvalOrgId'] ?? ''));
        if ($orgTugas !== '' && $orgTugas !== trim((string) $kandidat['orgId'])) {
            $this->dispatch('toast', type: 'error', message: 'Tugas rujukan tadi dikirim ke ' . ($this->formRujukan['approvalOrgNama'] ?: $orgTugas) . ', tapi kandidat yang dipilih sekarang ' . $kandidat['nama'] . '. Kirim Tugas Rujukan ulang ke faskes yang dipilih.');
            return;
        }

        // Penjagaan persetujuan: menolak = final, tidak boleh diterbitkan rujukannya.
        // Belum dijawab TIDAK diblokir — di staging jawaban sering tak pernah datang
        // dan itu akan mematikan uji coba; cukup diperingatkan supaya petugas sadar.
        $persetujuan = $this->ambilStatusApproval();
        $this->formRujukan['statusApproval'] = $persetujuan['status'];
        if ($persetujuan['status'] === 'rejected') {
            $this->dispatch('toast', type: 'error', message: 'Faskes tujuan MENOLAK tugas rujukan ini — rujukan tidak boleh diterbitkan. Pilih kandidat lain lalu kirim tugas rujukan ulang.');
            return;
        }
        if ($persetujuan['status'] !== 'accepted') {
            $this->dispatch('toast', type: 'warning', message: $persetujuan['terverifikasi']
                ? 'Perhatian: faskes tujuan BELUM menjawab tugas rujukan — rujukan tetap diterbitkan.'
                : 'Perhatian: status persetujuan tidak terverifikasi (gangguan koneksi) — rujukan tetap diterbitkan.');
        }

        $keRanap = $this->formRujukan['jalur'] === 'ranap';
        $respon = $this->rujukanServiceRequest([
            'occurrenceDateTime' => $this->rujukanTanggalRencanaIso($this->formRujukan['tglRencanaKunjungan']) ?: null,
            'performerTypeKode' => $this->formRujukan['performerTypeKode'],
            'conditionIds' => $this->dataDaftarUGD['satusehat']['conditionIds'] ?? [],

            'identifier' => (string) Str::uuid(),
            'carePlanId' => $this->formRujukan['carePlanId'],
            'jalur' => $keRanap ? 'ranap' : 'igd',
            'deskripsi' => trim($this->formRujukan['deskripsi']) !== '' ? trim($this->formRujukan['deskripsi']) : 'Rujukan ' . ($keRanap ? 'rawat inap' : 'gawat darurat') . ' — ' . $this->formRujukan['kodeDiagnosa'],
            'patientUuid' => $this->patientUuid(),
            'encounterId' => $this->encounterUuid(),
            'orgTujuanId' => $kandidat['orgId'],
            'orgTujuanNama' => $kandidat['nama'],
            'taskApprovalId' => $this->formRujukan['taskApprovalId'],
        ]);
        if ($respon['code'] < 200 || $respon['code'] >= 300) {
            $this->dispatch('toast', type: 'error', message: 'Kirim rujukan gagal [' . $respon['code'] . '] ' . $this->ringkasError($respon['body']));
            return;
        }

        $nomor = $this->rujukanNomorDariServiceRequest($respon['body']);
        if ($nomor === '') {
            $this->dispatch('toast', type: 'error', message: 'ServiceRequest terbentuk tapi nomor Rujukan SATUSEHAT tidak terbit — gangguan pusat yang dikenal; coba kirim ulang nanti. Data TIDAK disimpan sebagai sukses.');
            return;
        }

        $this->formRujukan['hasil'] = [
            'serviceRequestId' => (string) ($respon['body']['id'] ?? ''),
            'noRujukanSatuSehat' => $nomor,
            'tujuanNama' => $kandidat['nama'],
            'tujuanOrgId' => $kandidat['orgId'],
            'dikirimOleh' => auth()->user()->name ?? 'Sirus',
            'dikirimPada' => now(config('app.timezone'))->format('d/m/Y H:i:s'),
        ];
        $this->simpanDraft('Kirim Rujukan Kompetensi ' . ($keRanap ? 'ranap' : 'IGD') . ' → ' . $kandidat['nama'] . ' (No SS ' . $nomor . ')');
        $this->dispatch('toast', type: 'success', message: 'Rujukan ' . ($keRanap ? 'ranap' : 'IGD') . ' terkirim. No SATUSEHAT ' . $nomor);
    }

    /* ═══════════════════════════════════════
     | BATAL TUGAS RUJUKAN
    ═══════════════════════════════════════ */
    public function batalkanTugas(): void
    {
        if (empty($this->formRujukan['taskApprovalId']) || $this->isFormLocked) {
            return;
        }
        $respon = $this->rujukanTaskCancel($this->formRujukan['taskApprovalId']);
        if ($respon['code'] < 200 || $respon['code'] >= 300) {
            $this->dispatch('toast', type: 'error', message: 'Batal gagal [' . $respon['code'] . '] ' . $this->ringkasError($respon['body']));
            return;
        }
        $taskLama = $this->formRujukan['taskApprovalId'];
        $this->formRujukan['taskApprovalId'] = '';
        $this->formRujukan['carePlanId'] = '';
        $this->formRujukan['statusApproval'] = '';
        $this->formRujukan['approvalOrgId'] = '';
        $this->formRujukan['approvalOrgNama'] = '';
        $this->formRujukan['hasil'] = [];
        $this->simpanDraft('Batalkan tugas rujukan IGD (Task ' . $taskLama . ')');
        $this->dispatch('toast', type: 'success', message: 'Tugas rujukan dibatalkan.');
    }

    /* ═══════════════════════════════════════
     | PERSIST & ERROR
    ═══════════════════════════════════════ */
    private function simpanDraft(?string $catatanAudit = null): void
    {
        if (empty($this->rjNo)) {
            return;
        }
        try {
            DB::transaction(function () use ($catatanAudit) {
                $this->lockUGDRow($this->rjNo);
                $data = $this->findDataUGD($this->rjNo) ?? [];
                if (empty($data)) {
                    return;
                }
                $data['rujukanKompetensi'] = $this->formRujukan;
                $this->updateJsonUGD($this->rjNo, $data);
                $this->dataDaftarUGD = $data;
                if ($catatanAudit) {
                    $this->appendAdminLogUGD((int) $this->rjNo, $catatanAudit, 'MR');
                }
            });
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menyimpan state rujukan: ' . $e->getMessage());
        }
    }

    private function ringkasError($body): string
    {
        $teks = is_array($body) ? implode(' | ', collect($body['issue'] ?? [])->pluck('diagnostics')->filter()->take(2)->all()) ?: json_encode($body) : (string) $body;
        $teks = mb_substr($teks, 0, 300);
        $teksKecil = strtolower($teks);
        $hint = match (true) {
            str_contains($teksKecil, 'duplicate') => ' — identifier pernah dipakai; sistem sudah generate baru, coba klik ulang.',
            str_contains($teksKecil, 'tidak ditemukan') && str_contains($teksKecil, 'org') => ' — org belum terdaftar untuk uji rujukan; minta credential khusus ke tim SATUSEHAT.',
            str_contains($teksKecil, 'rate limit') || str_contains($teksKecil, 'quota') => ' — kuota API staging habis; hemat panggilan / lapor admin.',
            str_contains($teksKecil, 'gagal') && str_contains($teksKecil, 'koneksi') => ' — jaringan/gangguan pusat; data isian tersimpan, coba lagi nanti.',
            default => '',
        };
        return $teks . $hint;
    }
};
?>

<div>
    {{-- ══ KARTU RINGKAS (inline di tab Tindak Lanjut) ══ --}}
    @php $sudahTerkirim = !empty($formRujukan['hasil']['noRujukanSatuSehat']); @endphp

    <div class="p-5 bg-canvas border border-hairline shadow-sm rounded-2xl dark:bg-gray-900 dark:border-gray-700">
        <div class="flex flex-col gap-3">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="flex flex-wrap items-center gap-2 min-w-0">
                    <svg class="w-5 h-5 text-rose-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 15L12 18.75 15.75 15m-7.5-6L12 5.25 15.75 9" />
                    </svg>
                    <h3 class="text-base font-semibold text-ink dark:text-gray-200">
                        Rujukan Berbasis Kompetensi — IGD/Ranap RS Lain (SATUSEHAT FHIR)
                    </h3>
                    @if ($sudahTerkirim)
                        <x-badge variant="success">Terkirim</x-badge>
                    @else
                        <x-badge variant="warning">Belum dikirim</x-badge>
                    @endif
                </div>

                <div class="flex shrink-0">
                    <x-primary-button type="button" wire:click="openModal" wire:loading.attr="disabled"
                        wire:target="openModal" :disabled="!$rjNo" class="gap-2">
                        <span wire:loading.remove wire:target="openModal" class="flex items-center gap-1.5">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                            </svg>
                            {{ $sudahTerkirim ? 'Lihat Rujukan' : 'Buat Rujukan' }}
                        </span>
                        <span wire:loading wire:target="openModal" class="flex items-center gap-1.5">
                            <x-loading class="w-4 h-4" /> Memuat...
                        </span>
                    </x-primary-button>
                </div>
            </div>

            <p class="text-base text-muted dark:text-gray-400">
                Rujukan pasien UGD ke IGD atau ruang rawat inap RS lain, dikirim langsung ke SATUSEHAT (FHIR).
                Alurnya: cari kandidat faskes, kirim tugas rujukan, lalu kirim rujukan (ServiceRequest).
            </p>

            @if ($sudahTerkirim)
                <div class="flex flex-wrap gap-x-6 gap-y-1 text-sm text-muted dark:text-gray-400">
                    <span>No. Rujukan SATUSEHAT: <strong class="text-ink dark:text-gray-200">{{ $formRujukan['hasil']['noRujukanSatuSehat'] }}</strong></span>
                    <span>ServiceRequest: <strong class="text-ink dark:text-gray-200">{{ $formRujukan['hasil']['serviceRequestId'] ?? '-' }}</strong></span>
                </div>
            @endif
        </div>
    </div>

    {{-- ══ MODAL FORMULIR ══ --}}
    <x-modal name="rujukan-kompetensi-ugd-{{ $rjNo }}" size="full" height="full" focusable>
        <div class="flex flex-col min-h-[calc(100vh-8rem)]">

            {{-- HEADER --}}
            <div class="px-6 py-5 border-b border-hairline dark:border-gray-700">
                <div class="flex items-start justify-between gap-4">
                    <div class="flex items-center gap-3">
                        <div class="flex items-center justify-center w-10 h-10 rounded-xl bg-rose-500/10">
                            <svg class="w-6 h-6 text-rose-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 15L12 18.75 15.75 15m-7.5-6L12 5.25 15.75 9" />
                            </svg>
                        </div>
                        <div>
                            <h2 class="text-2xl font-semibold text-ink dark:text-gray-100">Rujukan Berbasis Kompetensi</h2>
                            <p class="mt-0.5 text-base text-muted dark:text-gray-400">
                                UGD → IGD/Ranap RS lain · dikirim langsung ke SATUSEHAT (FHIR)
                            </p>
                        </div>
                    </div>
                    @if ($sudahTerkirim)
                        <x-badge variant="success">Terkirim</x-badge>
                    @endif
                </div>
            </div>

            {{-- BODY --}}
            <div class="flex-1 px-4 py-4 overflow-y-auto bg-surface-soft dark:bg-gray-950/20">
                <div class="max-w-full mx-auto space-y-4">
    {{-- PRASYARAT --}}
    @php $prasyaratKurang = $this->prasyaratKurang(); @endphp
    @if (!empty($prasyaratKurang) && empty($formRujukan['hasil']['noRujukanSatuSehat']))
        <div class="p-3 text-sm text-red-800 border border-red-200 rounded-lg bg-red-50 dark:bg-red-950 dark:text-red-200 dark:border-red-900">
            <p class="font-semibold">Belum bisa <em>mengirim</em> rujukan — lengkapi dulu:</p>
            <ul class="mt-1 ml-4 list-disc">
                @foreach ($prasyaratKurang as $itemKurang)
                    <li>{{ $itemKurang }}</li>
                @endforeach
            </ul>
        </div>
    @endif
    @if (!empty($formRujukan['hasil']['noRujukanSatuSehat']))
        <div class="p-3 space-y-1 text-sm border border-green-200 rounded-lg bg-green-50 dark:bg-green-950 dark:border-green-900">
            <p class="font-semibold text-green-800 dark:text-green-200">Rujukan IGD sudah terkirim</p>
            <table class="text-gray-700 dark:text-gray-200">
                <tr><td class="pr-3">No Rujukan SATUSEHAT</td><td class="font-mono font-semibold">{{ $formRujukan['hasil']['noRujukanSatuSehat'] }}</td></tr>
                <tr><td class="pr-3">ServiceRequest</td><td class="font-mono">{{ $formRujukan['hasil']['serviceRequestId'] ?? '-' }}</td></tr>
                <tr><td class="pr-3">Tujuan</td><td>{{ $formRujukan['hasil']['tujuanNama'] ?? '-' }}</td></tr>
                <tr><td class="pr-3">Dikirim</td><td>{{ $formRujukan['hasil']['dikirimPada'] ?? '-' }} oleh {{ $formRujukan['hasil']['dikirimOleh'] ?? '-' }}</td></tr>
            </table>
        </div>
    @else

    {{-- Stepper: menegaskan Tugas Rujukan (3) dan Kirim Rujukan (5) adalah
         langkah BERBEDA, dengan persetujuan faskes (4) di antaranya. --}}
    <div class="p-3 mb-3 overflow-x-auto bg-canvas border border-hairline rounded-lg dark:bg-gray-800 dark:border-gray-700">
        <x-stepper :steps="$this->langkahRujukan()" />
    </div>

        {{-- LANGKAH 1 — DIAGNOSA, KRITERIA IGD, WILAYAH → CARI KANDIDAT --}}
        <div class="p-3 space-y-3 bg-canvas border border-hairline rounded-lg dark:bg-gray-800 dark:border-gray-700">
            <p class="text-sm font-semibold text-gray-700 dark:text-gray-200">Langkah 1–2 · Diagnosa, Kriteria &amp; Kandidat</p>

            {{-- Tujuan layanan di RS lain — menentukan use case FHIR --}}
            <div class="space-y-1">
                <p class="text-xs text-muted-soft">Kebutuhan pasien di RS tujuan:</p>
                <div class="grid grid-cols-1 gap-2 md:grid-cols-2">
                    <x-radio-button label="IGD (gawat darurat)" value="igd" name="jalurRujukan-{{ $rjNo }}"
                        wire:model.live="formRujukan.jalur" :disabled="$isFormLocked" />
                    <x-radio-button label="Rawat Inap" value="ranap" name="jalurRujukan-{{ $rjNo }}"
                        wire:model.live="formRujukan.jalur" :disabled="$isFormLocked" />
                </div>
            </div>

            <div class="flex flex-wrap gap-2">
                @forelse ($dataDaftarUGD['diagnosis'] ?? [] as $indexDiagnosa => $diagnosa)
                    @php $kodeIni = $diagnosa['icdX'] ?? ($diagnosa['diagId'] ?? ''); @endphp
                    <button type="button" wire:click="pilihDiagnosa({{ $indexDiagnosa }})" @disabled($isFormLocked)
                        class="px-2 py-1 text-xs rounded-lg border {{ $formRujukan['kodeDiagnosa'] === $kodeIni ? 'bg-rose-600 text-white border-transparent' : 'bg-canvas text-gray-700 border-hairline dark:bg-gray-700 dark:text-gray-200 dark:border-gray-600' }}">
                        {{ $kodeIni }} {{ \Illuminate\Support\Str::limit($diagnosa['diagDesc'] ?? '', 28) }}
                    </button>
                @empty
                    <p class="text-sm text-muted-soft">Belum ada diagnosa EMR.</p>
                @endforelse
            </div>

            <div class="max-w-md">
                <livewire:lov.diagnosa.lov-diagnosa label="Cari Diagnosa Rujukan (ICD-10)"
                    target="rujukanKompetensiDiagnosaUGD" :disabled="$isFormLocked"
                    wire:key="lov-diagnosa-rujukan-kompetensi-ugd-{{ $rjNo }}" />
            </div>

            <div>
                <x-input-label value="Kode Diagnosa" class="mb-1" />
                <x-text-input wire:model.live="formRujukan.kodeDiagnosa" :disabled="true" class="w-full" />
            </div>

            @if (($formRujukan['jalur'] ?? 'igd') === 'igd')
                <div class="space-y-2">
                    <p class="text-xs text-muted-soft">Kriteria gawat darurat (centang yang sesuai, minimal satu):</p>
                    @foreach ($this->pertanyaanIgd() as $linkId => $teks)
                        <x-toggle wire:model.live="formRujukan.kriteriaIgd.{{ $linkId }}" :trueValue="true"
                            :falseValue="false" :disabled="$isFormLocked" onColor="bg-rose-600" label="{{ $teks }}" />
                    @endforeach
                </div>
            @else
                <div class="space-y-2">
                    <p class="text-xs text-muted-soft">Kriteria rujukan ranap — pilih <b>tepat satu</b>:</p>
                    <div class="grid grid-cols-1 gap-2 md:grid-cols-3">
                        <x-radio-button label="Terapi/Pengobatan" value="terapi" name="kriteriaRanapUgd-{{ $rjNo }}"
                            wire:model.live="formRujukan.kriteriaPilih" :disabled="$isFormLocked" />
                        <x-radio-button label="Tindakan Medis (ICD-9-CM)" value="tindakan" name="kriteriaRanapUgd-{{ $rjNo }}"
                            wire:model.live="formRujukan.kriteriaPilih" :disabled="$isFormLocked" />
                        <x-radio-button label="Upaya Diagnosis" value="upaya" name="kriteriaRanapUgd-{{ $rjNo }}"
                            wire:model.live="formRujukan.kriteriaPilih" :disabled="$isFormLocked" />
                    </div>
                    @if (($formRujukan['kriteriaPilih'] ?? '') === 'tindakan')
                        <div class="max-w-xs">
                            <x-input-label value="Kode Tindakan ICD-9-CM" class="mb-1" />
                            <x-text-input wire:model.blur="formRujukan.kriteriaIcd9" placeholder="mis. 01.24"
                                :disabled="$isFormLocked" class="w-full" />
                        </div>
                    @endif
                </div>
            @endif

            {{-- Kelompok Layanan — menyaring kandidat ke faskes yang melayani
                 kelompok ini. Opsional: kosong = tidak dikirim, biar tidak
                 menyaring kandidat diam-diam dengan kelompok yang keliru. --}}
            <div>
                <x-input-label value="Kelompok Layanan (opsional)" class="mb-1" />
                <x-select-input wire:model.live="formRujukan.kelompokLayananKode" :disabled="$isFormLocked" class="w-full">
                    <option value="">— tidak dikirim —</option>
                    @foreach ($this->kelompokLayananOptions() as $kodeKelompok => $namaKelompok)
                        <option value="{{ $kodeKelompok }}">{{ $namaKelompok }}</option>
                    @endforeach
                </x-select-input>
            </div>

            {{-- Wilayah dipilih sekali lewat LOV kabupaten — propinsinya ikut
                 terisi, jadi pasangan kode tak bisa lagi tidak sinkron. --}}
            <div>
                <livewire:lov.kabupaten.lov-kabupaten label="Jejaring Wilayah Rujukan (Kab/Kota)"
                    target="rujukanWilayahUGD" :initialKabId="$formRujukan['kodeKabupaten'] ?: null"
                    :readonly="$isFormLocked"
                    wire:key="lov-wilayah-rujukan-ugd-{{ $rjNo }}" />
                <p class="mt-1 text-xs text-muted-soft">
                    Terpilih:
                    <strong>{{ $formRujukan['namaKabupaten'] ?: '-' }}</strong>
                    ({{ $formRujukan['kodeKabupaten'] ?: '-' }})
                    &middot; Prov. <strong>{{ $formRujukan['namaPropinsi'] ?: '-' }}</strong>
                    ({{ $formRujukan['kodePropinsi'] ?: '-' }})
                </p>
            </div>
            </div>

            <div class="flex flex-col items-start gap-2">
                <x-secondary-button type="button" wire:click="cariKandidat" wire:loading.attr="disabled"
                    wire:target="cariKandidat" :disabled="$isFormLocked">
                    <span wire:loading.remove wire:target="cariKandidat">🔍 Cari Kandidat Faskes</span>
                    <span wire:loading wire:target="cariKandidat" class="inline-flex items-center gap-1"><x-loading /> Mengirim permintaan...</span>
                </x-secondary-button>
                @if (!empty($formRujukan['taskKandidatId']))
                    <x-secondary-button type="button" wire:click="cekKandidat" wire:loading.attr="disabled"
                        wire:target="cekKandidat">
                        <span wire:loading.remove wire:target="cekKandidat">🔄 Cek Hasil Kandidat</span>
                        <span wire:loading wire:target="cekKandidat" class="inline-flex items-center gap-1"><x-loading /> Mengecek...</span>
                    </x-secondary-button>
                @endif
            </div>
            @if ($infoKandidat !== '')
                <p class="text-sm {{ str_starts_with($infoKandidat, '✓') || str_starts_with($infoKandidat, 'Tujuan:') ? 'text-green-700 dark:text-green-300' : 'text-muted-soft' }}">{{ $infoKandidat }}</p>
            @endif

            @if (!empty($formRujukan['kandidatList']))
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm border border-hairline rounded-lg dark:border-gray-700">
                        <thead class="bg-surface-soft dark:bg-gray-800">
                            <tr class="text-left text-muted dark:text-gray-300">
                                <th class="px-3 py-2 border-b">Faskes</th>
                                <th class="px-3 py-2 border-b">Strata</th>
                                <th class="px-3 py-2 text-right border-b">Jarak</th>
                                <th class="px-3 py-2 text-right border-b">Waktu</th>
                                <th class="px-3 py-2 text-center border-b">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($formRujukan['kandidatList'] as $indexKandidat => $kandidat)
                                @php $terpilih = $formRujukan['kandidatIdx'] === $indexKandidat; @endphp
                                <tr class="border-b border-hairline dark:border-gray-700 {{ $terpilih ? 'bg-brand-lime/10 dark:bg-brand-lime/5' : '' }}">
                                    <td class="px-3 py-2">
                                        <span class="font-medium text-ink dark:text-gray-200">{{ ($kandidat['nama'] ?? '') ?: '-' }}</span>
                                        <span class="block text-xs text-muted dark:text-gray-400">Organization/{{ $kandidat['orgId'] }}</span>
                                    </td>
                                    <td class="px-3 py-2">{{ ($kandidat['strata'] ?? '') ?: '-' }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums">{{ ($kandidat['distance'] ?? '') ?: '-' }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums">{{ ($kandidat['estimatedTime'] ?? '') ?: '-' }}</td>
                                    <td class="px-3 py-2 text-center">
                                        @if ($terpilih)
                                            <x-badge variant="success">&#10003; Dipilih</x-badge>
                                        @else
                                            <x-secondary-button type="button" wire:click="pilihKandidat({{ $indexKandidat }})"
                                                :disabled="$isFormLocked" title="Jadikan faskes tujuan rujukan">
                                                Pilih
                                            </x-secondary-button>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        {{-- LANGKAH 2 & 3 — TUGAS RUJUKAN + SERVICEREQUEST --}}
        <div class="p-3 space-y-3 bg-canvas border border-hairline rounded-lg dark:bg-gray-800 dark:border-gray-700">
            <p class="text-sm font-semibold text-gray-700 dark:text-gray-200">Langkah 3–5 · Tugas Rujukan → Persetujuan → Rujukan</p>

            <div class="grid grid-cols-1 gap-3">
                <div>
                    <x-input-label value="Pilih Layanan (pintasan)" class="mb-1" />
                    <x-select-input wire:change="pilihSpeciality($event.target.value)" :disabled="$isFormLocked" class="w-full">
                        <option value="">— ketik manual di bawah —</option>
                        @foreach ($this->specialityOptions() as $kodeLayanan => $namaLayanan)
                            <option value="{{ $kodeLayanan }}" @selected(($formRujukan['specialityCode'] ?? '') === $kodeLayanan)>
                                {{ $kodeLayanan }} — {{ $namaLayanan }}
                            </option>
                        @endforeach
                    </x-select-input>
                    <p class="mt-1 text-xs text-muted-soft">
                        Katalog clinical-speciality resmi belum dibagikan Kemkes; daftar ini hanya
                        kode yang sudah terbukti dipakai. Kode lain tetap boleh diketik manual.
                    </p>
                </div>
                <div>
                    <x-input-label value="Kode Layanan (clinical-speciality)" class="mb-1" />
                    <x-text-input wire:model.blur="formRujukan.specialityCode"
                        placeholder="{{ ($formRujukan['jalur'] ?? 'igd') === 'igd' ? 'L03 (default IGD)' : 'mis. LY133' }}"
                        :disabled="$isFormLocked" class="w-full" />
                </div>
                <div>
                    <x-input-label value="Nama Layanan" class="mb-1" />
                    <x-text-input wire:model.blur="formRujukan.specialityDisplay"
                        placeholder="{{ ($formRujukan['jalur'] ?? 'igd') === 'igd' ? 'Pelayanan Gawat Darurat' : 'mis. Syaraf - Stroke dan Cerebro Vaskuler' }}"
                        :disabled="$isFormLocked" class="w-full" />
                </div>
                <div>
                    <x-input-label value="Tgl. Rencana Kunjungan di RS Tujuan" class="mb-1" />
                    <x-text-input wire:model.blur="formRujukan.tglRencanaKunjungan" placeholder="dd/mm/yyyy"
                        :disabled="$isFormLocked" class="w-full" />
                    <p class="mt-1 text-xs text-muted-soft">Dikirim sebagai occurrenceDateTime — kapan pasien direncanakan dilayani, bukan jam pengiriman.</p>
                </div>
                <div>
                    <x-input-label value="Jenis Tenaga Kesehatan Pelaksana (opsional)" class="mb-1" />
                    <x-select-input wire:model.live="formRujukan.performerTypeKode" :disabled="$isFormLocked" class="w-full">
                        <option value="">— tidak dikirim —</option>
                        @foreach ($this->performerTypeOptions() as $kodePelaksana => $namaPelaksana)
                            <option value="{{ $kodePelaksana }}">{{ $namaPelaksana }}</option>
                        @endforeach
                    </x-select-input>
                </div>
                <div>
                    <x-input-label value="Deskripsi Rencana Rujukan" class="mb-1" />
                    <x-text-input wire:model.blur="formRujukan.deskripsi" placeholder="Alasan & kebutuhan penanganan di RS tujuan"
                        :disabled="$isFormLocked" class="w-full" />
                </div>
            </div>

            @if (!empty($formRujukan['taskApprovalId']))
                <p class="text-sm text-green-700 dark:text-green-300">✓ Tugas rujukan terkirim (Task {{ $formRujukan['taskApprovalId'] }}, CarePlan {{ $formRujukan['carePlanId'] }})</p>

                @php $statusApproval = $formRujukan['statusApproval'] ?? ''; @endphp
                <div class="flex flex-wrap items-center gap-2 mt-1">
                    <span class="text-sm text-muted dark:text-gray-400">Jawaban faskes tujuan:</span>
                    @if ($statusApproval === 'accepted')
                        <x-badge variant="success">Diterima</x-badge>
                    @elseif ($statusApproval === 'rejected')
                        <x-badge variant="danger">Ditolak</x-badge>
                    @else
                        <x-badge variant="warning">Belum dijawab</x-badge>
                    @endif
                    <x-secondary-button type="button" wire:click="cekStatusApproval" wire:loading.attr="disabled" wire:target="cekStatusApproval" class="text-xs">
                        <span wire:loading.remove wire:target="cekStatusApproval">🔄 Cek Status</span>
                        <span wire:loading wire:target="cekStatusApproval" class="inline-flex items-center gap-1"><x-loading /> Mengecek...</span>
                    </x-secondary-button>
                </div>
                @if ($statusApproval === 'rejected')
                    <p class="mt-1 text-sm text-rose-700 dark:text-rose-300">
                        Rujukan tidak bisa diterbitkan ke faskes ini. Pilih kandidat lain lalu kirim tugas rujukan ulang.
                    </p>
                @elseif ($statusApproval !== 'accepted')
                    <p class="mt-1 text-xs text-muted-soft">
                        Rujukan tetap bisa diterbitkan tanpa menunggu jawaban (dibutuhkan saat uji coba), tapi di
                        pelayanan nyata sebaiknya tunggu <strong>Diterima</strong> dulu.
                    </p>
                @endif
            @endif

            @if (!$isFormLocked)
                <div class="flex flex-col items-start gap-2">
                    <x-secondary-button type="button" wire:click="kirimTugasRujukan" wire:loading.attr="disabled" wire:target="kirimTugasRujukan">
                        <span wire:loading.remove wire:target="kirimTugasRujukan">📨 Kirim Tugas Rujukan (Approval)</span>
                        <span wire:loading wire:target="kirimTugasRujukan" class="inline-flex items-center gap-1"><x-loading /> Mengirim tugas...</span>
                    </x-secondary-button>
                    {{-- Muncul hanya kalau tugas rujukan sudah terkirim tapi id-nya
                         belum terpegang — mengirim ulang akan menumpuk duplikat. --}}
                    @if (!empty($formRujukan['identifierTask']) && empty($formRujukan['carePlanId']))
                        <div class="w-full p-3 border rounded-lg border-amber-500 bg-warning-tint dark:bg-amber-900/20 dark:border-amber-700">
                            <p class="text-sm font-semibold text-warning-deep dark:text-amber-200">Tugas rujukan sudah terkirim, tapi id-nya belum terbaca</p>
                            <p class="mt-1 text-sm text-body dark:text-gray-300">
                                Jangan kirim ulang — tugasnya sudah ada di faskes tujuan. Ambil id-nya dari SATUSEHAT:
                            </p>
                            <x-secondary-button type="button" class="mt-2" wire:click="pulihkanTugasRujukan"
                                wire:loading.attr="disabled" wire:target="pulihkanTugasRujukan">
                                <span wire:loading.remove wire:target="pulihkanTugasRujukan">🔁 Pulihkan ID Tugas Rujukan</span>
                                <span wire:loading wire:target="pulihkanTugasRujukan" class="inline-flex items-center gap-1"><x-loading /> Mencari...</span>
                            </x-secondary-button>
                        </div>
                    @endif

                    <x-success-button type="button" wire:click="kirimRujukan" wire:loading.attr="disabled" wire:target="kirimRujukan">
                        <span wire:loading.remove wire:target="kirimRujukan">🚀 Kirim Rujukan (ServiceRequest)</span>
                        <span wire:loading wire:target="kirimRujukan" class="inline-flex items-center gap-1"><x-loading /> Mengirim rujukan...</span>
                    </x-success-button>
                    @if (!empty($formRujukan['taskApprovalId']))
                        <x-danger-button type="button" wire:click="batalkanTugas" wire:confirm="Batalkan tugas rujukan ini?"
                            wire:loading.attr="disabled" wire:target="batalkanTugas">
                            <span wire:loading.remove wire:target="batalkanTugas">Batalkan Tugas Rujukan</span>
                            <span wire:loading wire:target="batalkanTugas" class="inline-flex items-center gap-1"><x-loading /> Membatalkan...</span>
                        </x-danger-button>
                    @endif
                </div>
            @endif
        </div>
    @endif
                </div>
            </div>

            {{-- FOOTER --}}
            <div class="sticky bottom-0 z-10 px-6 py-4 bg-canvas border-t border-hairline dark:bg-gray-900 dark:border-gray-700">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <p class="text-sm text-muted dark:text-gray-400">
                        Perubahan tersimpan otomatis ke kunjungan ini — aman ditutup lalu dilanjutkan nanti.
                    </p>
                    <x-secondary-button type="button" wire:click="closeModal">Tutup</x-secondary-button>
                </div>
            </div>

        </div>
    </x-modal>
</div>
