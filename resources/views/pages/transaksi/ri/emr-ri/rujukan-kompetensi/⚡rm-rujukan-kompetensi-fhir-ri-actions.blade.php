<?php

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Ri\EmrRITrait;
use App\Http\Traits\SATUSEHAT\SatuSehatRujukanTrait;
use App\Support\RujukanTampil;
use App\Support\Options\RujukanOptions;

new class extends Component {
    private const SPECIALITY_MANUAL = '__manual__';

    use EmrRITrait, SatuSehatRujukanTrait;

    public bool $isFormLocked = false;
    // string — mengikuti tipe prop induk rm-perencanaan-ri-actions
    public ?string $riHdrNo = null;

    // Referensi kunjungan — TIDAK di-bind ke form
    public array $dataDaftarRi = [];

    // State rujukan — dipersist ke node rujukanKompetensi di JSON RI
    public array $formRujukan = [];

    public string $infoKandidat = '';

    /* ═══════════════════════════════════════
     | MOUNT & DEFAULT
    ═══════════════════════════════════════ */
    public function mount(): void
    {
        $this->formRujukan = $this->defaultFormRujukan();
        if (empty($this->riHdrNo)) {
            return;
        }

        $data = $this->findDataRI($this->riHdrNo);
        if (empty($data)) {
            return;
        }
        $this->dataDaftarRi = $data;

        $tersimpan = $data['rujukanKompetensiFhir'] ?? [];
        if (!empty($tersimpan) && is_array($tersimpan)) {
            $this->formRujukan = array_replace($this->defaultFormRujukan(), $tersimpan);
        } else {
            $diagnosisPertama = collect($data['diagnosis'] ?? [])->first();
            $this->formRujukan['kodeDiagnosa'] = $diagnosisPertama['icdX'] ?? ($diagnosisPertama['diagId'] ?? '');
            $this->formRujukan['diagnosaDesc'] = $diagnosisPertama['diagDesc'] ?? '';
        }

        if ($this->checkEmrRIStatus($this->riHdrNo)) {
            $this->isFormLocked = true;
        }
    }

    /* ═══════════════════════════════════════
     | OPEN / CLOSE MODAL (pola modul-dokumen: kartu ringkas → tombol → x-modal)
    ═══════════════════════════════════════ */
    public function openModal(): void
    {
        if (empty($this->riHdrNo)) {
            return;
        }

        // Baca ulang saat dibuka: SEP/Encounter/diagnosa bisa terbit setelah panel
        // pertama kali dirender, jadi prasyarat harus dinilai dari data terkini.
        $data = $this->findDataRI($this->riHdrNo);
        if (empty($data)) {
            $this->dispatch('toast', type: 'error', message: 'Data kunjungan tidak ditemukan.');
            return;
        }
        $this->dataDaftarRi = $data;

        $tersimpan = $data['rujukanKompetensiFhir'] ?? [];
        if (!empty($tersimpan) && is_array($tersimpan)) {
            $this->formRujukan = array_replace($this->defaultFormRujukan(), $tersimpan);
        }

        $this->isFormLocked = $this->checkEmrRIStatus($this->riHdrNo);

        // Jawaban faskes tujuan datang dari sistem RS LAIN — tidak ada pemberitahuan
        // yang mendorong ke kita, jadi status tersimpan bisa basi. Disegarkan sekali
        // saat modal dibuka, HANYA bila memang ada tugas yang masih menggantung:
        // yang sudah accepted/rejected itu final, dan memanggil ulang cuma buang kuota.
        $this->segarkanStatusApprovalBilaMenggantung();

        $this->dispatch('open-modal', name: 'rujukan-kompetensi-fhir-ri-' . $this->riHdrNo);
    }

    public function closeModal(): void
    {
        $this->dispatch('close-modal', name: 'rujukan-kompetensi-fhir-ri-' . $this->riHdrNo);
    }

    private function defaultFormRujukan(): array
    {
        return [
            // Tujuan layanan di RS lain: 'ranap' | 'igd'. Pasien ranap bisa perlu
            // dirujuk gawat darurat, bukan hanya pindah rawat inap.
            'jalur' => $this->formRujukan['jalur'] ?? 'ranap',
            'kodeDiagnosa' => '',
            'diagnosaDesc' => '',
            // 5 pertanyaan GAWAT DARURAT Q100 (IGD tanpa validasi ICD-9/10)
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
            // Jejaring wilayah — valueCoding administrative-area, kode tanpa titik
            'kodePropinsi' => '35',
            'namaPropinsi' => 'JAWA TIMUR',
            'kodeKabupaten' => '3504',
            'namaKabupaten' => 'KABUPATEN TULUNGAGUNG',
            'deskripsi' => '',
            // clinical-speciality utk CarePlan.activity (mis. LY133 Syaraf - Stroke)
            'specialityCode' => '',
            'specialityDisplay' => '',
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


    /**
     * Wilayah jejaring rujukan dipilih lewat LOV kabupaten (pola master pasien):
     * satu kali pilih mengisi kabupaten SEKALIGUS propinsinya, jadi pasangan kode
     * tidak mungkin lagi tidak sinkron gara-gara diketik terpisah. Kode di
     * rsmst_kabupatens memang sudah tanpa titik ('3504'/'35'), persis bentuk yang
     * diminta Q101 — tidak perlu dibersihkan lagi.
     */
    #[On('lov.selected.rujukanWilayahRI')]
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
    /** Penanda petugas sengaja mengetik kode di luar daftar. */
    public bool $specialityManual = false;

    public function pilihSpeciality(string $kode): void
    {
        if ($this->isFormLocked) {
            return;
        }

        if ($kode === self::SPECIALITY_MANUAL) {
            $this->specialityManual = true;
            return;
        }

        $this->specialityManual = false;

        if ($kode === '') {
            $this->formRujukan['specialityCode'] = '';
            $this->formRujukan['specialityDisplay'] = '';
            return;
        }

        // Kode DAN namanya diisi sepasang supaya tidak pernah berpasangan salah.
        $this->formRujukan['specialityCode'] = $kode;
        $this->formRujukan['specialityDisplay'] = $this->specialityOptions()[$kode] ?? '';
    }

    /**
     * Kotak ketik manual muncul kalau petugas memilihnya, ATAU kalau kode
     * tersimpan memang di luar daftar — daftar kita belum lengkap, jadi record
     * lama tidak boleh jadi tak terbaca hanya karena kodenya tak dikenal.
     */
    public function specialityManualAktif(): bool
    {
        $kode = trim((string) ($this->formRujukan['specialityCode'] ?? ''));

        return $this->specialityManual || ($kode !== '' && !isset($this->specialityOptions()[$kode]));
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
    /**
     * Tanggal Rujukan untuk DITAMPILKAN, format dd/mm/yyyy.
     *
     * Jalur FHIR tak punya field tglRujukan tersendiri — rujukan terbit pada saat
     * dikirim, jadi tanggalnya diambil dari 'dikirimPada' yang sudah tersimpan
     * (tak ada kunci baru di JSON). Sebelum terkirim: tanggal hari ini, yaitu
     * tanggal yang AKAN menempel di rujukannya.
     */
    public function tanggalRujukanTampil(): string
    {
        $dikirim = trim((string) ($this->formRujukan['hasil']['dikirimPada'] ?? ''));

        if ($dikirim === '') {
            return now(config('app.timezone'))->format('d/m/Y');
        }

        // 'dikirimPada' berbentuk 'd/m/Y H:i:s' — cukup ambil bagian tanggalnya.
        return explode(' ', $dikirim, 2)[0];
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

    /** Ganti tujuan = kriteria & kandidat lama tidak berlaku lagi. */
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

    public function toggleKriteriaIgd(string $linkId): void
    {
        if ($this->isFormLocked) {
            return;
        }
        $this->formRujukan['kriteriaIgd'][$linkId] = !($this->formRujukan['kriteriaIgd'][$linkId] ?? false);
    }

    public function langkahRujukan(): array
    {
        $sudahKirim = !empty($this->formRujukan['hasil']['noRujukanSatuSehat']);
        $adaTugas = !empty($this->formRujukan['carePlanId']);
        $adaKandidat = ($this->formRujukan['kandidatIdx'] ?? null) !== null;
        $statusApproval = (string) ($this->formRujukan['statusApproval'] ?? '');

        $keRanap = ($this->formRujukan['jalur'] ?? 'ranap') === 'ranap';
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
        if (empty($this->riHdrNo)) {
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
            $kurang[] = 'Encounter SATUSEHAT RI belum terkirim (menu Satu Sehat → Encounter)';
        }
        if (empty($this->patientUuid())) {
            $kurang[] = 'IHS Pasien (patient_uuid) kosong di Master Pasien';
        }
        if (empty($this->dokterUuid())) {
            $kurang[] = 'IHS Dokter (dr_uuid) kosong di Master Dokter';
        }
        return $kurang;
    }

    public function encounterUuid(): string
    {
        return (string) ($this->dataDaftarRi['satusehat']['encounterId'] ?? '');
    }

    private function patientUuid(): string
    {
        $regNo = $this->dataDaftarRi['regNo'] ?? '';
        return $regNo === '' ? '' : (string) (DB::table('rsmst_pasiens')->where('reg_no', $regNo)->value('patient_uuid') ?? '');
    }

    private function dokterUuid(): string
    {
        $drId = $this->dataDaftarRi['drId'] ?? '';
        return $drId === '' ? '' : (string) (DB::table('rsmst_doctors')->where('dr_id', $drId)->value('dr_uuid') ?? '');
    }

    private function dokterNama(): string
    {
        $drId = $this->dataDaftarRi['drId'] ?? '';
        return $drId === '' ? '' : (string) (DB::table('rsmst_doctors')->where('dr_id', $drId)->value('dr_name') ?? '');
    }

    /* ═══════════════════════════════════════
     | DIAGNOSA
    ═══════════════════════════════════════ */
    public function pilihDiagnosa(int $index): void
    {
        $diagnosa = $this->dataDaftarRi['diagnosis'][$index] ?? null;
        if (!$diagnosa) {
            return;
        }
        $this->setDiagnosaRujukan($diagnosa['icdX'] ?? ($diagnosa['diagId'] ?? ''), $diagnosa['diagDesc'] ?? '');
    }

    #[On('lov.selected.rujukanKompetensiDiagnosaRI')]
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
        // Diagnosa berubah → kandidat lama tidak berlaku
        $this->formRujukan['taskKandidatId'] = '';
        $this->formRujukan['kandidatList'] = [];
        $this->formRujukan['kandidatIdx'] = null;
        $this->infoKandidat = '';
    }

    /* ═══════════════════════════════════════
     | LANGKAH 1 — CARI KANDIDAT (pra permintaan + pencarian)
    ═══════════════════════════════════════ */
    public function cariKandidat(): void
    {
        $this->infoKandidat = '';
        $kurang = $this->prasyaratKurang();
        if (!empty($kurang)) {
            $this->dispatch('toast', type: 'error', message: 'Data belum siap: ' . implode('; ', $kurang) . '.');
            return;
        }
        $keRanap = ($this->formRujukan['jalur'] ?? 'ranap') === 'ranap';
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
                $this->dispatch('toast', type: 'error', message: 'Kriteria Tindakan Medis butuh kode ICD-9-CM (menentukan kandidat RS).');
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
            'jalur' => $this->formRujukan['jalur'] ?? 'ranap',
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
        if ($this->isFormLocked) {
            return;
        }

        $kandidat = $this->formRujukan['kandidatList'][$index] ?? null;
        if (!$kandidat) {
            return;
        }

        // Menekan baris yang SUDAH terpilih = membatalkan pilihan. Tanpa ini
        // togglenya cuma bisa menyala dan tak pernah padam — kontrol yang
        // bentuknya menjanjikan dua arah tapi jalannya satu arah.
        if ($this->formRujukan['kandidatIdx'] === $index) {
            $this->formRujukan['kandidatIdx'] = null;
            $this->infoKandidat = '';
            return;
        }

        $this->formRujukan['kandidatIdx'] = $index;
        $this->infoKandidat = RujukanTampil::infoTujuan($kandidat);
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

        // PENJAGA 1 — state lokal. Tiap tekan melahirkan Task+CarePlan BARU di
        // faskes tujuan; tak ada yang menyatukannya di sana. Kotak masuk RS tujuan
        // pernah terisi 10 permintaan kembar untuk satu pasien (4 di antaranya
        // dalam menit yang sama) karena tombol ini ditekan berulang saat balasan
        // lambat. Diperiksa SEBELUM kandidat: kalau tugasnya sudah ada, yang perlu
        // dilakukan petugas bukan memilih kandidat.
        if (!empty($this->formRujukan['taskApprovalId'])) {
            $this->dispatch('toast', type: 'error', message: 'Tugas rujukan untuk kunjungan ini sudah ada. Pakai "Kirim Ulang Tugas" kalau mau menggantinya — tugas lama dibatalkan dulu supaya tak menumpuk di faskes tujuan.');
            return;
        }
        if (!empty($this->formRujukan['identifierTask'])) {
            $this->dispatch('toast', type: 'error', message: 'Tugas rujukan sudah terkirim tapi id-nya belum terbaca. Tekan "Pulihkan ID Tugas Rujukan" — JANGAN kirim ulang, tugasnya sudah ada di sana.');
            return;
        }

        $kandidat = $this->formRujukan['kandidatList'][$this->formRujukan['kandidatIdx'] ?? -1] ?? null;
        if (!$kandidat) {
            $this->dispatch('toast', type: 'error', message: 'Pilih kandidat faskes tujuan dulu.');
            return;
        }

        // PENJAGA 2 — tanya server. State lokal bisa kosong padahal tugasnya sudah
        // ada di sana: draft gagal tersimpan, atau dikirim dari perangkat/petugas
        // lain. Kalau ketemu yang masih hidup, ADOPSI id-nya — jangan buat yang
        // kedua. Ini sekaligus menutup sebab duplikat yang paling sering: id yang
        // gagal terbaca dari balasan bundle kini ditemukan sendiri, tanpa menunggu
        // petugas menekan "Pulihkan ID".
        $tugasLama = $this->rujukanPulihkanTugasTerakhir($this->encounterUuid());
        if (!empty($tugasLama['ditemukan']) && !empty($tugasLama['aktif'])) {
            $this->formRujukan['taskApprovalId'] = $tugasLama['taskId'];
            $this->formRujukan['carePlanId'] = $tugasLama['carePlanId'];
            if ($tugasLama['ownerOrgId'] !== '') {
                $this->formRujukan['approvalOrgId'] = $tugasLama['ownerOrgId'];
            }
            $this->simpanDraft('Adopsi tugas rujukan yang sudah ada (Task ' . $tugasLama['taskId'] . ')');
            $this->dispatch('toast', type: 'warning', message: 'Kunjungan ini SUDAH punya tugas rujukan di SATUSEHAT (Task ' . $tugasLama['taskId'] . ') — id-nya diambil, tidak dibuat yang baru. Tekan Cek Status untuk melihat jawabannya.');
            return;
        }
        if (trim($this->formRujukan['specialityCode']) === '') {
            $this->dispatch('toast', type: 'error', message: 'Isi kode layanan clinical-speciality dulu (mis. LY133 Syaraf - Stroke dan Cerebro Vaskuler).');
            return;
        }

        $identifierTask = (string) Str::uuid();
        $identifierCarePlan = (string) Str::uuid();

        $respon = $this->rujukanBundleApproval([
            'identifierTask' => $identifierTask,
            'identifierCarePlan' => $identifierCarePlan,
            'encounterId' => $this->encounterUuid(),
            'patientUuid' => $this->patientUuid(),
            'patientName' => (string) ($this->dataDaftarRi['regName'] ?? ''),
            'practitionerUuid' => $this->dokterUuid(),
            'practitionerName' => $this->dokterNama(),
            'orgTujuanId' => $kandidat['orgId'],
            'orgTujuanNama' => $kandidat['nama'],
            'jalur' => $this->formRujukan['jalur'] ?? 'ranap',
            'deskripsi' => trim($this->formRujukan['deskripsi']) !== '' ? trim($this->formRujukan['deskripsi']) : 'Rujukan rawat inap — ' . $this->formRujukan['kodeDiagnosa'] . ' ' . $this->formRujukan['diagnosaDesc'],
            'specialityCode' => trim($this->formRujukan['specialityCode']),
            'specialityDisplay' => trim($this->formRujukan['specialityDisplay']) !== '' ? trim($this->formRujukan['specialityDisplay']) : trim($this->formRujukan['specialityCode']),
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
        $this->simpanDraft('Kirim tugas rujukan ranap → ' . $kandidat['nama']);

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

    /**
     * Segarkan jawaban faskes tujuan bila tugas rujukan masih menggantung.
     * Sengaja diam: dipanggil saat membuka modal, jadi kegagalan koneksi tidak
     * boleh menyembur jadi toast — status lama tetap dipakai apa adanya.
     */
    private function segarkanStatusApprovalBilaMenggantung(): void
    {
        if (trim((string) ($this->formRujukan['taskApprovalId'] ?? '')) === '') {
            return;
        }
        if (in_array($this->formRujukan['statusApproval'] ?? '', ['accepted', 'rejected'], true)) {
            return;
        }

        $statusTerbaca = $this->ambilStatusApproval();
        if (!$statusTerbaca['terverifikasi'] || $statusTerbaca['status'] === '') {
            return;
        }

        $this->formRujukan['statusApproval'] = $statusTerbaca['status'];
        $this->simpanDraft('Jawaban faskes tujuan: ' . $statusTerbaca['status']);
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

    /**
     * Kirim ULANG tugas rujukan: batalkan yang sekarang dulu, baru kirim baru.
     *
     * Menggantikan penekanan berulang tombol Kirim Tugas Rujukan, yang dulu
     * menumpuk tugas kembar di kotak masuk faskes tujuan. Kalau pembatalan gagal,
     * pengiriman TIDAK dilanjutkan — lebih baik petugas mencoba lagi daripada
     * meninggalkan dua tugas hidup untuk satu kunjungan.
     */
    public function kirimUlangTugasRujukan(): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form read-only.');
            return;
        }
        if (empty($this->formRujukan['taskApprovalId'])) {
            $this->kirimTugasRujukan();
            return;
        }

        $this->batalkanTugas();

        // batalkanTugas() mengosongkan taskApprovalId hanya bila server menerima
        // pembatalannya; masih terisi = gagal, dan toast-nya sudah keluar dari sana.
        if (!empty($this->formRujukan['taskApprovalId'])) {
            return;
        }

        $this->kirimTugasRujukan();
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

        $respon = $this->rujukanServiceRequest([
            'occurrenceDateTime' => $this->rujukanTanggalRencanaIso($this->formRujukan['tglRencanaKunjungan']) ?: null,
            'performerTypeKode' => $this->formRujukan['performerTypeKode'],
            'conditionIds' => $this->dataDaftarRi['satusehat']['conditionIds'] ?? [],

            'identifier' => (string) Str::uuid(),
            'carePlanId' => $this->formRujukan['carePlanId'],
            'jalur' => $this->formRujukan['jalur'] ?? 'ranap',
            'deskripsi' => trim($this->formRujukan['deskripsi']) !== '' ? trim($this->formRujukan['deskripsi']) : 'Rujukan rawat inap — ' . $this->formRujukan['kodeDiagnosa'],
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
        $this->simpanDraft('Kirim Rujukan Kompetensi ranap → ' . $kandidat['nama'] . ' (No SS ' . $nomor . ')');
        $this->dispatch('toast', type: 'success', message: 'Rujukan ranap terkirim. No SATUSEHAT ' . $nomor);
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
        // Identifier yang kita kirim ikut dibersihkan: ia jejak untuk memulihkan
        // id tugas yang MASIH hidup. Tugas ini baru saja dibatalkan, jadi
        // membiarkannya membuat kotak peringatan "Pulihkan ID" muncul untuk
        // tugas yang sudah mati — dan penjaga di kirimTugasRujukan() ikut
        // menghalangi pengiriman berikutnya.
        $this->formRujukan['identifierTask'] = '';
        $this->formRujukan['identifierCarePlan'] = '';
        $this->formRujukan['statusApproval'] = '';
        $this->formRujukan['approvalOrgId'] = '';
        $this->formRujukan['approvalOrgNama'] = '';
        $this->formRujukan['hasil'] = [];
        $this->simpanDraft('Batalkan tugas rujukan ranap (Task ' . $taskLama . ')');
        $this->dispatch('toast', type: 'success', message: 'Tugas rujukan dibatalkan.');
    }

    /* ═══════════════════════════════════════
     | PERSIST & ERROR
    ═══════════════════════════════════════ */
    private function simpanDraft(?string $catatanAudit = null): void
    {
        if (empty($this->riHdrNo)) {
            return;
        }
        try {
            DB::transaction(function () use ($catatanAudit) {
                $this->lockRIRow($this->riHdrNo);
                $data = $this->findDataRI($this->riHdrNo) ?? [];
                if (empty($data)) {
                    return;
                }
                $data['rujukanKompetensiFhir'] = $this->formRujukan;
                $this->updateJsonRI($this->riHdrNo, $data);
                $this->dataDaftarRi = $data;
                if ($catatanAudit) {
                    $this->appendAdminLogRI((int) $this->riHdrNo, $catatanAudit, 'MR');
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
    /**
     * Cetak Surat Pengantar Rujukan + Resume Klinis Pasien Rujukan.
     *
     * Format ditetapkan Kemkes 27/08/2026 (calon Kepmenkes) dan berlaku untuk SEMUA
     * rujukan. Isinya dirakit komponen headless bersama supaya keenam panel memakai
     * satu cetakan yang sama.
     */
    public function cetakSuratRujukan(): void
    {
        $this->dispatch('cetak-surat-rujukan.open',
            jalur: 'ri',
            noKunjungan: (string) $this->riHdrNo,
            node: 'rujukanKompetensiFhir');
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
                    <svg class="w-5 h-5 text-indigo-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
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
                        wire:target="openModal" :disabled="!$riHdrNo" class="gap-2">
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
                Rujukan pasien rawat inap ke ruang rawat inap RS lain, dikirim langsung ke SATUSEHAT (FHIR).
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
    <x-modal name="rujukan-kompetensi-fhir-ri-{{ $riHdrNo }}" size="full" height="full" focusable>
        <div class="flex flex-col min-h-[calc(100vh-8rem)]">

            {{-- HEADER --}}
            <div class="px-6 py-5 border-b border-hairline dark:border-gray-700">
                <div class="flex items-start justify-between gap-4">
                    <div class="flex items-center gap-3">
                        <div class="flex items-center justify-center w-8 h-8 rounded-lg bg-indigo-500/10">
                            <svg class="w-5 h-5 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 15L12 18.75 15.75 15m-7.5-6L12 5.25 15.75 9" />
                            </svg>
                        </div>
                        <div>
                            <h2 class="text-lg font-semibold text-ink dark:text-gray-100">Rujukan Berbasis Kompetensi</h2>
                            <p class="mt-0.5 text-xs text-muted dark:text-gray-400">
                                Rawat Inap → ranap RS lain · dikirim langsung ke SATUSEHAT (FHIR)
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        @if ($sudahTerkirim)
                            <x-badge variant="success">Terkirim</x-badge>
                        @endif
                        {{-- Tombol tutup di pojok kanan header, pola x-icon-button
                             yang dipakai modal modul-dokumen. --}}
                        <x-icon-button color="gray" type="button" wire:click="closeModal" title="Tutup">
                            <span class="sr-only">Tutup</span>
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd"
                                    d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                    clip-rule="evenodd" />
                            </svg>
                        </x-icon-button>
                    </div>
                </div>
            </div>

            {{-- BODY --}}
            <div class="flex-1 px-4 py-4 overflow-y-auto bg-surface-soft dark:bg-gray-950/20">
                <div class="max-w-full mx-auto space-y-4">
                    {{-- Siapa pasiennya — rujukan terbit atas nama orang, dan
                         modal ini penuh isian teknis yang tak menyebut nama sama sekali. --}}
                    <livewire:pages::transaksi.ri.display-pasien-ri.display-pasien-ri :riHdrNo="$riHdrNo"
                        wire:key="rujukan-kompetensi-fhir-ri-display-pasien-{{ $riHdrNo ?? 'init' }}" />

    {{-- PRASYARAT --}}
    @php $prasyaratKurang = $this->prasyaratKurang(); @endphp
    @if (!empty($formRujukan['hasil']['noRujukanSatuSehat']))
        <div class="p-3 space-y-1 text-sm border border-green-200 rounded-lg bg-green-50 dark:bg-green-950 dark:border-green-900">
            <p class="font-semibold text-green-800 dark:text-green-200">Rujukan rawat inap sudah terkirim</p>
            <table class="text-gray-700 dark:text-gray-200">
                <tr><td class="pr-3">No Rujukan SATUSEHAT</td><td class="font-mono font-semibold">{{ $formRujukan['hasil']['noRujukanSatuSehat'] }}</td></tr>
                <tr><td class="pr-3">ServiceRequest</td><td class="font-mono">{{ $formRujukan['hasil']['serviceRequestId'] ?? '-' }}</td></tr>
                <tr><td class="pr-3">Tanggal Rujukan</td><td class="font-semibold">{{ $this->tanggalRujukanTampil() }}</td></tr>
                <tr><td class="pr-3">Tujuan</td><td>{{ $formRujukan['hasil']['tujuanNama'] ?? '-' }}</td></tr>
                <tr><td class="pr-3">Dikirim</td><td>{{ $formRujukan['hasil']['dikirimPada'] ?? '-' }} oleh {{ $formRujukan['hasil']['dikirimOleh'] ?? '-' }}</td></tr>
            </table>

            {{-- Surat Pengantar Rujukan + Resume Klinis — format Kemkes (calon Kepmenkes),
                 wajib untuk SEMUA rujukan. Komponen cetaknya headless di halaman EMR. --}}
            <div class="pt-2">
                <x-outline-button type="button" wire:click="cetakSuratRujukan"
                    wire:loading.attr="disabled" wire:target="cetakSuratRujukan">
                    <span wire:loading.remove wire:target="cetakSuratRujukan">Cetak Surat Rujukan</span>
                    <span wire:loading wire:target="cetakSuratRujukan">Menyiapkan...</span>
                </x-outline-button>
            </div>
        </div>
    @else

    {{-- Panduan pemakaian — komponen bersama 3 panel. --}}
    <div class="mb-3">
        <x-rujukan-kompetensi.panduan-kirim :jalurGanda="true" />
    </div>

    {{-- Prasyarat & penanda langkah disandingkan: keduanya keterangan keadaan,
         bukan isian — ditumpuk ke bawah cuma mendorong formulir menjauh. --}}
    <div class="grid items-start grid-cols-1 gap-3 mb-3 lg:grid-cols-2">
        @if (!empty($prasyaratKurang) && empty($formRujukan['hasil']['noRujukanSatuSehat']))
            <div x-data="{ buka: false }"
                class="overflow-hidden text-sm border border-red-200 rounded-lg bg-red-50 dark:bg-red-950 dark:border-red-900">
                <button type="button" x-on:click="buka = !buka"
                    class="flex items-center justify-between w-full gap-2 px-3 py-2 font-semibold text-left text-red-800 dark:text-red-200">
                    <span>Belum bisa <em>mengirim</em> rujukan — {{ count($prasyaratKurang) }} hal perlu dilengkapi</span>
                    <svg class="w-4 h-4 transition-transform shrink-0" x-bind:class="buka && 'rotate-180'"
                        fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>
                <ul x-show="buka" x-cloak class="px-3 pb-3 ml-4 list-disc text-red-800 dark:text-red-200">
                    @foreach ($prasyaratKurang as $itemKurang)
                        <li>{{ $itemKurang }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
        {{-- Penanda langkah — buka-tutup, default tertutup: yang dibutuhkan sehari-hari cuma "sedang di langkah apa", dan itu sudah tertulis di kepalanya. --}}
        <div x-data="{ buka: false }"
            class="overflow-hidden bg-canvas border border-hairline rounded-lg dark:bg-gray-800 dark:border-gray-700">
            <button type="button" x-on:click="buka = !buka"
                class="flex items-center justify-between w-full gap-2 px-3 py-2 text-sm font-semibold text-left text-gray-700 dark:text-gray-200">
                <span>Langkah: <span class="font-normal text-muted dark:text-gray-400">{{ collect($this->langkahRujukan())->firstWhere('state', 'current')['title'] ?? (collect($this->langkahRujukan())->contains(fn($l) => $l['state'] === 'error') ? 'ada yang ditolak' : 'selesai') }}</span></span>
                <svg class="w-4 h-4 transition-transform shrink-0" x-bind:class="buka && 'rotate-180'"
                    fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
            </button>
            <div x-show="buka" x-cloak class="px-3 pb-3 overflow-x-auto">
                <x-stepper :steps="$this->langkahRujukan()" />
            </div>
    </div>
    </div>

            {{-- Dua kelompok langkah disandingkan: layar modal cukup lebar, dan
         petugas perlu melihat kandidat terpilih (kiri) sambil mengisi
         tugas rujukan (kanan). Menumpuk ke bawah di layar sempit. --}}
    <div class="grid grid-cols-1 gap-3 lg:grid-cols-2 items-start">
    {{-- LANGKAH 1 — DIAGNOSA, KRITERIA, WILAYAH → CARI KANDIDAT --}}
            <div class="p-3 space-y-3 bg-canvas border border-hairline rounded-lg dark:bg-gray-800 dark:border-gray-700">
                <p class="flex flex-wrap items-center gap-1.5 text-sm font-semibold text-gray-700 dark:text-gray-200"><x-step-number :n="1" /><span>Diagnosa &amp; Kriteria</span><span class="text-muted-soft">→</span><x-step-number :n="2" /><span>Pilih Kandidat</span></p>

                {{-- Tujuan layanan di RS lain — menentukan pertanyaan kriteria DAN
                     kategori rencana yang dikirim, jadi harus dipilih paling awal. --}}
                <div>
                    <x-input-label value="Tujuan Layanan di RS Lain" class="mb-1" />
                    <div class="flex flex-wrap gap-3">
                        <x-radio-button label="Rawat Inap" value="ranap" name="jalurRujukanRi-{{ $riHdrNo }}"
                            wire:model.live="formRujukan.jalur" :disabled="$isFormLocked" />
                        <x-radio-button label="IGD (gawat darurat)" value="igd" name="jalurRujukanRi-{{ $riHdrNo }}"
                            wire:model.live="formRujukan.jalur" :disabled="$isFormLocked" />
                    </div>
                </div>

                <div class="flex flex-wrap gap-2">
                    @forelse ($dataDaftarRi['diagnosis'] ?? [] as $indexDiagnosa => $diagnosa)
                        @php $kodeIni = $diagnosa['icdX'] ?? ($diagnosa['diagId'] ?? ''); @endphp
                        <button type="button" wire:click="pilihDiagnosa({{ $indexDiagnosa }})" @disabled($isFormLocked)
                            class="px-2 py-1 text-xs rounded-lg border {{ $formRujukan['kodeDiagnosa'] === $kodeIni ? 'bg-indigo-600 text-white border-transparent' : 'bg-canvas text-gray-700 border-hairline dark:bg-gray-700 dark:text-gray-200 dark:border-gray-600' }}">
                            {{ $kodeIni }} {{ \Illuminate\Support\Str::limit($diagnosa['diagDesc'] ?? '', 28) }}
                        </button>
                    @empty
                        <p class="text-sm text-muted-soft">Belum ada diagnosa EMR.</p>
                    @endforelse
                </div>

                <div class="max-w-md">
                    <livewire:lov.diagnosa.lov-diagnosa label="Cari Diagnosa Rujukan (ICD-10)"
                        target="rujukanKompetensiDiagnosaRI"
                        :initialDiagnosaId="$formRujukan['kodeDiagnosa'] ?: null" :disabled="$isFormLocked"
                        wire:key="lov-diagnosa-rujukan-kompetensi-fhir-ri-{{ $riHdrNo }}" />
                    <p class="mt-1 text-xs text-muted-soft">Dipakai mencari kandidat RS. @if (filled($formRujukan['kodeDiagnosa'] ?? ''))<span class="font-mono font-semibold text-ink dark:text-gray-200">Kode terkirim: {{ $formRujukan['kodeDiagnosa'] }}</span>@endif</p>
                </div>

                @if (($formRujukan['jalur'] ?? 'ranap') === 'igd')
                    <div class="space-y-2">
                        <p class="text-xs text-muted-soft">Kriteria gawat darurat (centang yang sesuai, minimal satu):</p>
                        @foreach ($this->pertanyaanIgd() as $linkId => $teks)
                            <x-toggle :current="($formRujukan['kriteriaIgd'][$linkId] ?? false) ? 'Ya' : 'Tidak'"
                                trueValue="Ya" falseValue="Tidak" :disabled="$isFormLocked"
                                onColor="bg-rose-600" wireClick="toggleKriteriaIgd('{{ $linkId }}')" label="{{ $teks }}" />
                        @endforeach
                    </div>
                @else
                <div class="space-y-2">
                    <p class="text-xs text-muted-soft">Pilih <b>tepat satu</b> kriteria:</p>
                    <div class="grid grid-cols-1 gap-2 md:grid-cols-3">
                        <x-radio-button label="Terapi/Pengobatan" value="terapi" name="kriteriaRanap-{{ $riHdrNo }}"
                            wire:model.live="formRujukan.kriteriaPilih" :disabled="$isFormLocked" />
                        <x-radio-button label="Tindakan Medis (ICD-9-CM)" value="tindakan" name="kriteriaRanap-{{ $riHdrNo }}"
                            wire:model.live="formRujukan.kriteriaPilih" :disabled="$isFormLocked" />
                        <x-radio-button label="Upaya Diagnosis" value="upaya" name="kriteriaRanap-{{ $riHdrNo }}"
                            wire:model.live="formRujukan.kriteriaPilih" :disabled="$isFormLocked" />
                    </div>
                    @if ($formRujukan['kriteriaPilih'] === 'tindakan')
                        <div class="max-w-xs">
                            <x-input-label value="Kode Tindakan ICD-9-CM" class="mb-1" />
                            <x-text-input wire:model.blur="formRujukan.kriteriaIcd9" placeholder="mis. 01.24"
                                :disabled="$isFormLocked" class="w-full" />
                            <p class="mt-1 text-xs text-muted-soft">Harus valid & sesuai diagnosa — menentukan kandidat RS.</p>
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
                        target="rujukanWilayahRI" :initialKabId="$formRujukan['kodeKabupaten'] ?: null"
                        :readonly="$isFormLocked"
                        wire:key="lov-wilayah-rujukan-ri-{{ $riHdrNo }}" />
                    <p class="mt-1 text-xs text-muted-soft">
                        Terpilih:
                        <strong>{{ $formRujukan['namaKabupaten'] ?: '-' }}</strong>
                        ({{ $formRujukan['kodeKabupaten'] ?: '-' }})
                        &middot; Prov. <strong>{{ $formRujukan['namaPropinsi'] ?: '-' }}</strong>
                        ({{ $formRujukan['kodePropinsi'] ?: '-' }})
                    </p>
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

                <x-rujukan-kompetensi.kandidat-tabel :rows="$formRujukan['kandidatList']"
                    :selectedIndex="$formRujukan['kandidatIdx']" :disabled="$isFormLocked" />
            </div>

            {{-- LANGKAH 2 & 3 — TUGAS RUJUKAN + SERVICEREQUEST --}}
            <div class="p-3 space-y-3 bg-canvas border border-hairline rounded-lg dark:bg-gray-800 dark:border-gray-700">
                <p class="flex flex-wrap items-center gap-1.5 text-sm font-semibold text-gray-700 dark:text-gray-200"><x-step-number :n="3" /><span>Kirim Tugas Rujukan</span><span class="text-muted-soft">→</span><x-step-number :n="4" /><span>Persetujuan Faskes</span><span class="text-muted-soft">→</span><x-step-number :n="5" /><span>Kirim Rujukan</span></p>
                <x-rujukan-kompetensi.identitas-kiriman :encounterId="$this->encounterUuid()" />

                <div class="grid grid-cols-1 gap-3">
                    {{-- Satu layanan = satu pilihan. Kotak kode & nama hanya muncul
                         saat petugas memang memilih "ketik manual", supaya tiga field
                         tidak berebut menjelaskan nilai yang sama. --}}
                    <div>
                        <x-input-label value="Kode Layanan (clinical-speciality)" class="mb-1" />
                        <x-select-input wire:change="pilihSpeciality($event.target.value)" :disabled="$isFormLocked" class="w-full">
                            <option value="">— belum dipilih —</option>
                            @foreach ($this->specialityOptions() as $kodeLayanan => $namaLayanan)
                                <option value="{{ $kodeLayanan }}"
                                    @selected(!$this->specialityManualAktif() && ($formRujukan['specialityCode'] ?? '') === $kodeLayanan)>
                                    {{ $kodeLayanan }} — {{ $namaLayanan }}
                                </option>
                            @endforeach
                            <option value="__manual__" @selected($this->specialityManualAktif())>Lainnya — ketik manual</option>
                        </x-select-input>
                        <p class="mt-1 text-xs text-muted-soft">
                            @if (filled($formRujukan['specialityCode'] ?? ''))
                                <span class="font-mono font-semibold text-ink dark:text-gray-200">Kode terkirim: {{ $formRujukan['specialityCode'] }}</span>
                                {{ filled($formRujukan['specialityDisplay'] ?? '') ? '— ' . $formRujukan['specialityDisplay'] : '' }}
                            @else
                                Katalog clinical-speciality resmi belum dibagikan Kemkes; daftar ini hanya
                                kode yang sudah terbukti dipakai.
                            @endif
                        </p>
                    </div>

                    @if ($this->specialityManualAktif())
                        <div>
                            <x-input-label value="Kode Layanan (ketik manual)" class="mb-1" />
                            <x-text-input wire:model.blur="formRujukan.specialityCode" placeholder="mis. LY133"
                                :disabled="$isFormLocked" class="w-full" />
                        </div>
                        <div>
                            <x-input-label value="Nama Layanan" class="mb-1" />
                            <x-text-input wire:model.blur="formRujukan.specialityDisplay" placeholder="mis. Syaraf - Stroke dan Cerebro Vaskuler"
                                :disabled="$isFormLocked" class="w-full" />
                        </div>
                    @endif
                    <div>
                        <x-input-label value="Tgl. Rencana Kunjungan di RS Tujuan" class="mb-1" />
                        <x-text-input wire:model.blur="formRujukan.tglRencanaKunjungan" placeholder="dd/mm/yyyy"
                            :disabled="$isFormLocked" class="w-full" />
                        <p class="mt-1 text-xs text-muted-soft">Dikirim sebagai occurrenceDateTime — kapan pasien direncanakan dilayani, bukan jam pengiriman.</p>
                    </div>
                    {{-- Tanggal Rujukan tak bisa diketik — ia terisi saat Kirim Rujukan
                         ditekan. Ditampilkan tetap sebagai label supaya petugas tahu
                         tanggal apa yang menempel di dokumen rujukannya. --}}
                    <div>
                        <x-input-label value="Tanggal Rujukan" class="mb-1" />
                        <x-text-input :value="$this->tanggalRujukanTampil()" disabled readonly class="w-full" />
                        <p class="mt-1 text-xs text-muted-soft">
                            {{ empty($formRujukan['hasil']['dikirimPada'])
                                ? 'Terisi otomatis saat Kirim Rujukan ditekan.'
                                : 'Tanggal terbitnya rujukan ini.' }}
                        </p>
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
                    </div>
                @endif
            </div>
    </div>
    @endif
                </div>
            </div>

            {{-- FOOTER --}}
            <div class="sticky bottom-0 z-10 px-6 py-4 bg-canvas border-t border-hairline dark:bg-gray-900 dark:border-gray-700">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    {{-- Keterangan singkat DI DEKAT tombolnya: dua tombol ini paling
                         sering tertukar — yang satu menanyakan kesediaan, yang satu
                         menerbitkan rujukan resmi. --}}
                    <div class="min-w-0 text-sm text-muted dark:text-gray-400">
                        <p>Perubahan tersimpan otomatis ke kunjungan ini — aman ditutup lalu dilanjutkan nanti.</p>
                        {{-- Syaratnya SAMA dengan tombolnya: begitu rujukan terbit,
                             tombolnya hilang, jadi penjelasannya ikut hilang — kalau
                             tidak, layar menjelaskan tombol yang sudah tak ada. --}}
                        @if (empty($formRujukan['hasil']['noRujukanSatuSehat']) && !$isFormLocked)
                        <ul class="mt-1 space-y-0.5 text-xs">
                            <li>
                                <span class="font-semibold text-ink dark:text-gray-200">Kirim Tugas Rujukan</span> —
                                menanyakan kesediaan RS tujuan. Belum merujuk, belum ada nomor rujukan.
                                Jawabannya dilihat di <span class="font-semibold text-ink dark:text-gray-200">Persetujuan Faskes</span>
                                (badge di panel kanan; tombol <span class="font-semibold">Cek Status</span> di footer ini untuk
                                menanyakan ulang — SATUSEHAT tidak memberi tahu sendiri):
                                <span class="font-semibold text-success-deep dark:text-green-300">Diterima</span> →
                                lanjut Kirim Rujukan;
                                <span class="font-semibold text-error-deep dark:text-red-300">Ditolak</span> →
                                pilih kandidat lain lalu kirim tugas rujukan ulang;
                                <span class="font-semibold">belum dijawab</span> → boleh lanjut, muncul peringatan.
                            </li>
                            <li>
                                <span class="font-semibold text-ink dark:text-gray-200">Kirim Rujukan</span> —
                                menerbitkan rujukan resmi &amp; Nomor Rujukan Nasional. Diblokir bila RS tujuan menolak.
                            </li>
                        </ul>
                        @endif
                    </div>

                    {{-- Dua tombol kirim ditaruh di footer yang selalu menempel: dulu
                         terkubur di kolom kanan dan harus digulir. --}}
                    <div class="flex flex-wrap items-center justify-end gap-2 ml-auto">
                        @if (empty($formRujukan['hasil']['noRujukanSatuSehat']) && !$isFormLocked)
                            @if (empty($formRujukan['taskApprovalId']))
                                <x-outline-button type="button" wire:click="kirimTugasRujukan"
                                    wire:loading.attr="disabled" wire:target="kirimTugasRujukan"
                                    title="Langkah 3 — menanyakan kesediaan faskes tujuan">
                                    <span wire:loading.remove wire:target="kirimTugasRujukan" class="inline-flex items-center gap-2">
                                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 12L3.269 3.126A59.768 59.768 0 0 1 21.485 12 59.77 59.77 0 0 1 3.27 20.876L5.999 12Zm0 0h7.5" />
                                    </svg>
                                        Kirim Tugas Rujukan
                                    </span>
                                    <span wire:loading wire:target="kirimTugasRujukan" class="inline-flex items-center gap-1"><x-loading /> Mengirim tugas...</span>
                                </x-outline-button>
                            @else
                                {{-- Tugasnya sudah ada. Tombol yang SAMA tak boleh dipakai lagi:
                                     tiap tekan melahirkan tugas kembar di kotak masuk faskes
                                     tujuan. Jadi ia berganti nama & pekerjaan — membatalkan
                                     yang lama dulu, baru mengirim yang baru. --}}
                                <x-outline-button type="button" wire:click="kirimUlangTugasRujukan"
                                    wire:confirm="Kirim ulang tugas rujukan? Tugas yang sekarang DIBATALKAN dulu supaya tidak menumpuk di faskes tujuan."
                                    wire:loading.attr="disabled" wire:target="kirimUlangTugasRujukan"
                                    title="Batalkan tugas yang ada, lalu kirim tugas baru ke kandidat terpilih">
                                    <span wire:loading.remove wire:target="kirimUlangTugasRujukan" class="inline-flex items-center gap-2">
                                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992V4.356m0 4.992-3.181-3.183a8.25 8.25 0 0 0-13.803 3.7M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7" />
                                    </svg>
                                        Kirim Ulang Tugas
                                    </span>
                                    <span wire:loading wire:target="kirimUlangTugasRujukan" class="inline-flex items-center gap-1"><x-loading /> Membatalkan & mengirim...</span>
                                </x-outline-button>
                            @endif
                        @endif

                        {{-- Dua tombol yang bekerja pada TUGAS yang barusan dikirim, jadi
                             tempatnya menempel pada tombolnya — bukan terkubur di panel
                             tengah yang harus digulir. Syaratnya tetap seperti semula:
                             keduanya hanya ada setelah tugas rujukan punya id. --}}
                        @if (!empty($formRujukan['taskApprovalId']))
                            <x-secondary-button type="button" wire:click="cekStatusApproval"
                                wire:loading.attr="disabled" wire:target="cekStatusApproval"
                                title="Tanyakan ulang jawaban faskes tujuan — SATUSEHAT tidak memberi tahu sendiri">
                                <span wire:loading.remove wire:target="cekStatusApproval" class="inline-flex items-center gap-2">
                                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                                    </svg>
                                    Cek Status
                                </span>
                                <span wire:loading wire:target="cekStatusApproval" class="inline-flex items-center gap-1"><x-loading /> Mengecek...</span>
                            </x-secondary-button>

                            @if (!$isFormLocked)
                                {{-- Merah tipis, bukan tombol merah penuh: ia bersebelahan
                                     dengan tombol kirim, jadi bobotnya sengaja diturunkan
                                     supaya tak mudah terklik. wire:confirm tetap ada. --}}
                                <x-outline-button type="button" wire:click="batalkanTugas"
                                    wire:confirm="Batalkan tugas rujukan ini?"
                                    wire:loading.attr="disabled" wire:target="batalkanTugas"
                                    class="!text-red-600 !bg-red-50 !border-red-200 hover:!bg-red-100 hover:!text-red-700 hover:!border-red-300 dark:!text-red-400 dark:!bg-red-900/20 dark:!border-red-800/30 dark:hover:!bg-red-900/30"
                                    title="Tarik kembali tugas rujukan yang sudah dikirim">
                                    <span wire:loading.remove wire:target="batalkanTugas" class="inline-flex items-center gap-2">
                                        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M9.75 9.75l4.5 4.5m0-4.5-4.5 4.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                        </svg>
                                        Batalkan Tugas
                                    </span>
                                    <span wire:loading wire:target="batalkanTugas" class="inline-flex items-center gap-1"><x-loading /> Membatalkan...</span>
                                </x-outline-button>
                            @endif
                        @endif

                        @if (empty($formRujukan['hasil']['noRujukanSatuSehat']) && !$isFormLocked)
                            <svg class="w-4 h-4 text-muted-soft shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                            </svg>

                            <x-primary-button type="button" wire:click="kirimRujukan"
                                wire:loading.attr="disabled" wire:target="kirimRujukan"
                                title="Langkah 5 — menerbitkan rujukan resmi & nomor rujukan nasional">
                                <span wire:loading.remove wire:target="kirimRujukan" class="inline-flex items-center gap-2">
                                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                </svg>
                                    Kirim Rujukan
                                </span>
                                <span wire:loading wire:target="kirimRujukan" class="inline-flex items-center gap-1"><x-loading /> Mengirim rujukan...</span>
                            </x-primary-button>
                        @endif

                        <x-secondary-button type="button" wire:click="closeModal">Tutup</x-secondary-button>
                    </div>
                </div>
            </div>

        </div>
    </x-modal>
</div>
