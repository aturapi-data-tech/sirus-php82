<?php

use Livewire\Component;
use Livewire\Attributes\On;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Ri\EmrRITrait;
use App\Http\Traits\BPJS\SisruteTrait;
use App\Support\RujukanTampil;
use App\Http\Traits\SATUSEHAT\EncounterTrait;

new class extends Component {
    // EncounterTrait dipakai HANYA untuk membaca Encounter saat memastikan
    // kecocokan pasien sebelum kirim. Diperiksa: nol nama method & properti yang
    // bertabrakan dengan EmrRITrait.
    use EmrRITrait, EncounterTrait;

    public bool $isFormLocked = false;
    public ?string $riHdrNo = null;

    // Referensi kunjungan — TIDAK di-bind ke form
    public array $dataDaftarRi = [];

    // State rujukan kompetensi — dipersist ke node rujukanKompetensi di JSON RJ
    // supaya retry setelah gangguan BPJS/SATUSEHAT tidak perlu isi ulang.
    public array $formRujukan = [];

    // Pesan status per langkah (bukan toast — menetap di panel)
    public string $infoKriteria = '';
    public string $infoKandidat = '';
    public string $infoKirim = '';

    /* ═══════════════════════════════════════
     | MOUNT & DEFAULT
    ═══════════════════════════════════════ */
    public function mount(): void
    {
        $this->formRujukan = $this->defaultFormRujukan();
        if (empty($this->riHdrNo)) {
            return;
        }

        $dataDaftarRi = $this->findDataRI($this->riHdrNo);
        if (empty($dataDaftarRi)) {
            return;
        }
        $this->dataDaftarRi = $dataDaftarRi;

        // Restore state tersimpan; merge di atas default supaya key baru tetap ada
        $tersimpan = $dataDaftarRi['rujukanKompetensi'] ?? [];
        if (!empty($tersimpan) && is_array($tersimpan)) {
            $this->formRujukan = array_replace($this->defaultFormRujukan(), $tersimpan);
        } else {
            $this->prefillDariKunjungan();
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
        $dataDaftarRi = $this->findDataRI($this->riHdrNo);
        if (empty($dataDaftarRi)) {
            $this->dispatch('toast', type: 'error', message: 'Data kunjungan RJ tidak ditemukan.');
            return;
        }
        $this->dataDaftarRi = $dataDaftarRi;

        $tersimpan = $dataDaftarRi['rujukanKompetensi'] ?? [];
        if (!empty($tersimpan) && is_array($tersimpan)) {
            $this->formRujukan = array_replace($this->defaultFormRujukan(), $tersimpan);
        }

        $this->isFormLocked = $this->checkEmrRIStatus($this->riHdrNo);

        $this->dispatch('open-modal', name: 'rujukan-kompetensi-ri-' . $this->riHdrNo);
    }

    public function closeModal(): void
    {
        $this->dispatch('close-modal', name: 'rujukan-kompetensi-ri-' . $this->riHdrNo);
    }

    private function defaultFormRujukan(): array
    {
        return [
            'kodeDiagnosa' => '',
            'diagnosaDesc' => '',
            'kodeSpesialis' => '',
            'kodeSarana' => '',
            'tglRencanaKunjungan' => Carbon::now(config('app.timezone'))->format('d/m/Y'),
            'poliRujukan' => '',
            'catatan' => '',
            // Kriteria — linkId DINAMIS per ICD-10, selalu dari server, jangan hardcode
            'kriteriaList' => [],
            'kriteriaSumber' => '',
            'kriteriaPilih' => '',
            'kriteriaIcd9' => '',
            'kriteriaIcd9Desc' => '',
            // Jejaring wilayah — opsi dari response GetKriteriaRujukan
            'propinsiOptions' => [],
            'kabupatenOptions' => [],
            'kodePropinsi' => '',
            'namaPropinsi' => '',
            'kodeKabupaten' => '',
            'namaKabupaten' => '',
            // Kandidat faskes
            'kandidatList' => [],
            'kandidatIdx' => null,
            // Hasil kirim — nomor WAJIB tersimpan di DB (syarat UAT)
            'hasil' => [],
        ];
    }

    private function prefillDariKunjungan(): void
    {
        $diagnosisPertama = collect($this->dataDaftarRi['diagnosis'] ?? [])->first();
        $this->formRujukan['kodeDiagnosa'] = $diagnosisPertama['icdX'] ?? ($diagnosisPertama['diagId'] ?? '');
        $this->formRujukan['diagnosaDesc'] = $diagnosisPertama['diagDesc'] ?? '';
        $this->formRujukan['kodeSpesialis'] = $this->dataDaftarRi['kdpolibpjs'] ?? '';
    }

    /* ═══════════════════════════════════════
     | PRASYARAT — kotak merah "Data belum siap"
    ═══════════════════════════════════════ */
    public function prasyaratKurang(): array
    {
        if (empty($this->riHdrNo)) {
            return [];
        }
        $kurang = [];
        if (empty(env('SATUSEHAT_ORGANIZATION_ID'))) {
            $kurang[] = 'SATUSEHAT_ORGANIZATION_ID belum diset di server';
        }
        if (empty($this->nomorSep())) {
            $kurang[] = 'SEP kunjungan ini belum terbit';
        }
        if (empty($this->encounterUuid())) {
            $kurang[] = 'Encounter SATUSEHAT belum terkirim (menu Satu Sehat → Encounter)';
        }
        if (empty($this->patientUuid())) {
            $kurang[] = 'IHS Pasien (patient_uuid) kosong di Master Pasien';
        }
        if (empty($this->dokterUuid())) {
            $kurang[] = 'IHS Dokter (dr_uuid) kosong di Master Dokter';
        }
        // Yang dikirim ke BPJS adalah formRujukan.kodeDiagnosa — boleh diisi lewat LOV
        // "Cari Diagnosa Rujukan" tanpa menunggu diagnosa EMR. Diagnosa EMR hanya
        // dipakai sebagai pra-isi + chip pintasan, jadi JANGAN dijadikan syarat.
        if (empty($this->formRujukan['kodeDiagnosa'] ?? '')) {
            $kurang[] = 'Diagnosa rujukan belum dipilih (Langkah 1 — Cari Diagnosa Rujukan)';
        }
        return $kurang;
    }

    /**
     * Pastikan nomor kartu BPJS, IHS pasien, dan Encounter menunjuk PASIEN YANG SAMA
     * (skenario UAT TC03).
     *
     * Ketiganya memang diturunkan dari satu kunjungan, jadi seharusnya mustahil beda.
     * Yang dijaga di sini keadaan yang tetap mungkin: node JSON kunjungan tersalin dari
     * kunjungan lain, atau encounterId basi setelah data dibetulkan manual. Kalau lolos,
     * rujukan terbit ATAS NAMA PASIEN LAIN di BPJS dan SATUSEHAT sekaligus — kesalahan
     * yang mahal dibatalkan, jadi ongkos satu GET sebelum kirim itu murah.
     *
     * Sengaja TIDAK dipanggil dari prasyaratKurang(): method itu dievaluasi tiap render,
     * dan memanggil API di sana berarti satu HTTP call untuk setiap kali panel digambar.
     *
     * @return array<int, string> daftar ketidakcocokan; kosong = aman
     */
    private function pasienTidakCocok(): array
    {
        $masalah = [];

        // 1. Kartu BPJS — perbandingan lokal, tanpa panggilan keluar.
        $kartuPasien = preg_replace('/\D/', '', (string) (DB::table('rsmst_pasiens')
            ->where('reg_no', $this->dataDaftarRi['regNo'] ?? '')
            ->value('nokartu_bpjs') ?? ''));
        $kartuSep = preg_replace('/\D/', '', (string) ($this->dataDaftarRi['sep']['reqSep']['request']['t_sep']['noKartu'] ?? ''));
        if ($kartuPasien !== '' && $kartuSep !== '' && $kartuPasien !== $kartuSep) {
            $masalah[] = "No. kartu BPJS di SEP ({$kartuSep}) berbeda dari master pasien ({$kartuPasien})";
        }

        // 2. Encounter benar-benar milik IHS pasien ini — satu-satunya pemeriksaan
        //    yang harus menyeberang ke SATUSEHAT.
        $patientUuid = $this->patientUuid();
        $encounterUuid = $this->encounterUuid();
        if ($patientUuid === '' || $encounterUuid === '') {
            return $masalah; // sudah ditangkap prasyaratKurang()
        }

        try {
            $this->initializeSatuSehat();
            $encounter = $this->getEncounter($encounterUuid);
        } catch (\Throwable $e) {
            // Gagal memeriksa BUKAN berarti tidak cocok. Menolak kirim karena SATUSEHAT
            // sedang gangguan akan memblokir pelayanan tanpa alasan yang sah.
            return $masalah;
        }

        $subjek = str_replace('Patient/', '', (string) ($encounter['subject']['reference'] ?? ''));
        if ($subjek !== '' && $subjek !== $patientUuid) {
            $masalah[] = "Encounter {$encounterUuid} milik pasien {$subjek}, bukan {$patientUuid}";
        }

        return $masalah;
    }

    private function nomorSep(): string
    {
        // View RI: rsview_rihdrs berkunci rihdr_no, bukan rj_no.
        return (string) (DB::table('rsview_rihdrs')->where('rihdr_no', $this->riHdrNo)->value('vno_sep') ?? '');
    }

    private function encounterUuid(): string
    {
        return (string) ($this->dataDaftarRi['satusehat']['encounterId'] ?? '');
    }

    private function patientUuid(): string
    {
        $regNo = $this->dataDaftarRi['regNo'] ?? ($this->dataDaftarRi['reg_no'] ?? '');
        if ($regNo === '') {
            return '';
        }
        return (string) (DB::table('rsmst_pasiens')->where('reg_no', $regNo)->value('patient_uuid') ?? '');
    }

    private function dokterUuid(): string
    {
        $drId = $this->dataDaftarRi['drId'] ?? ($this->dataDaftarRi['dr_id'] ?? '');
        if ($drId === '') {
            return '';
        }
        return (string) (DB::table('rsmst_doctors')->where('dr_id', $drId)->value('dr_uuid') ?? '');
    }

    /* ═══════════════════════════════════════
     | PILIH DIAGNOSA (dari daftar diagnosa EMR)
    ═══════════════════════════════════════ */
    public function pilihDiagnosa(int $index): void
    {
        $diagnosa = $this->dataDaftarRi['diagnosis'][$index] ?? null;
        if (!$diagnosa) {
            return;
        }
        $this->setDiagnosaRujukan($diagnosa['icdX'] ?? ($diagnosa['diagId'] ?? ''), $diagnosa['diagDesc'] ?? '');
    }

    // LOV diagnosa (blockHeader default) — pencarian bebas, kode kategori 3-karakter
    // otomatis terblokir sesuai aturan SATUSEHAT
    #[On('lov.selected.rujukanRajalDiagnosaRI')]
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

    /**
     * LOV poli. Yang dikirim ke BPJS adalah kd_poli_bpjs, bukan poli_id kita.
     * Payload null = user menekan "Ubah" untuk mengosongkan; kolomnya ikut
     * dikosongkan supaya tak ada kode basi yang ikut terkirim.
     */
    /**
     * Kriteria "Tindakan Medis" menuntut kode ICD-9-CM, dan kode itu ikut menentukan
     * kandidat RS — salah ketik satu digit menghasilkan daftar kandidat yang keliru
     * tanpa pesan error. Karena itu dipilih lewat LOV master, bukan diketik bebas
     * (skenario UAT TC02 juga mensyaratkan "terdapat pencarian procedure ICD-9").
     */
    #[On('lov.selected.rujukanRajalIcd9RI')]
    public function onLovIcd9Selected(string $target, array $payload): void
    {
        if ($this->isFormLocked) {
            return;
        }
        $kode = trim((string) ($payload['proc_id'] ?? ''));
        if ($kode === '') {
            $this->dispatch('toast', type: 'error', message: 'Data tindakan tidak valid.');
            return;
        }
        $this->formRujukan['kriteriaIcd9'] = $kode;
        $this->formRujukan['kriteriaIcd9Desc'] = trim((string) ($payload['proc_desc'] ?? ''));
        // Kandidat lama dihitung dari kriteria yang lama — biarkan tampil akan
        // menyesatkan, jadi dikosongkan supaya petugas mencari ulang.
        $this->formRujukan['kandidatList'] = [];
        $this->formRujukan['kandidatIdx'] = null;
        $this->simpanDraft();
    }

    #[On('lov.cleared.rujukanRajalIcd9RI')]
    public function onLovIcd9Cleared(string $target = ''): void
    {
        if ($this->isFormLocked) {
            return;
        }
        $this->formRujukan['kriteriaIcd9'] = '';
        $this->formRujukan['kriteriaIcd9Desc'] = '';
        $this->formRujukan['kandidatList'] = [];
        $this->formRujukan['kandidatIdx'] = null;
        $this->simpanDraft();
    }

    #[On('lov.selected.rujukanRajalSpesialisRI')]
    public function onLovSpesialisSelected(?array $payload = null, string $target = ''): void
    {
        if ($this->isFormLocked) {
            return;
        }
        $this->formRujukan['kodeSpesialis'] = $this->kodeBpjsDariPayloadPoli($payload);
    }

    #[On('lov.selected.rujukanRajalPoliRI')]
    public function onLovPoliRujukanSelected(?array $payload = null, string $target = ''): void
    {
        if ($this->isFormLocked) {
            return;
        }
        $this->formRujukan['poliRujukan'] = $this->kodeBpjsDariPayloadPoli($payload);
    }

    /**
     * Tolak poli yang belum dipetakan ke BPJS — kalau dibiarkan lolos, kolomnya
     * terisi string kosong dan rujukan ditolak jauh di belakang tanpa petunjuk.
     */
    private function kodeBpjsDariPayloadPoli(?array $payload): string
    {
        if ($payload === null) {
            return '';
        }

        $kodeBpjs = strtoupper(trim((string) ($payload['kd_poli_bpjs'] ?? '')));
        if ($kodeBpjs === '') {
            $this->dispatch('toast', type: 'error',
                message: 'Poli "' . ($payload['poli_desc'] ?? '-') . '" belum punya Kode BPJS di Master Poli, jadi belum bisa dipakai untuk rujukan.');
            return '';
        }

        return $kodeBpjs;
    }

    /**
     * Kebalikannya: form menyimpan kode BPJS, sedangkan lov-poli minta poli_id.
     * Mengembalikan null bila kodenya tak ada di master (mis. data lama) —
     * LOV tampil kosong, tapi kodenya tetap ditampilkan di bawah kolom.
     */
    private function poliIdDariKodeBpjs(string $kodeBpjs): ?string
    {
        $kodeBpjs = strtoupper(trim($kodeBpjs));
        if ($kodeBpjs === '') {
            return null;
        }

        $poliId = DB::table('rsmst_polis')
            ->whereRaw('UPPER(TRIM(kd_poli_bpjs)) = ?', [$kodeBpjs])
            ->value('poli_id');

        return $poliId === null ? null : (string) $poliId;
    }

    // Dibaca dari blade sebagai $this->poliIdSpesialis / $this->poliIdPoliRujukan.
    // Sengaja BUKAN #[Computed]: nilainya harus ikut berubah begitu handler LOV
    // menulis ulang formRujukan di request yang sama.
    public function getPoliIdSpesialisProperty(): ?string
    {
        return $this->poliIdDariKodeBpjs((string) ($this->formRujukan['kodeSpesialis'] ?? ''));
    }

    public function getPoliIdPoliRujukanProperty(): ?string
    {
        return $this->poliIdDariKodeBpjs((string) ($this->formRujukan['poliRujukan'] ?? ''));
    }

    private function setDiagnosaRujukan(string $kodeDiagnosa, string $diagnosaDesc): void
    {
        $this->formRujukan['kodeDiagnosa'] = $kodeDiagnosa;
        $this->formRujukan['diagnosaDesc'] = $diagnosaDesc;
        // Diagnosa berubah → kriteria & kandidat lama tidak berlaku (linkId dinamis per ICD-10)
        $this->formRujukan['kriteriaList'] = [];
        $this->formRujukan['kriteriaSumber'] = '';
        $this->formRujukan['kriteriaPilih'] = '';
        $this->formRujukan['kriteriaIcd9'] = '';
        $this->formRujukan['kriteriaIcd9Desc'] = '';
        $this->formRujukan['kandidatList'] = [];
        $this->formRujukan['kandidatIdx'] = null;
        $this->infoKriteria = '';
        $this->infoKandidat = '';
    }

    /* ═══════════════════════════════════════
     | LANGKAH 1 — AMBIL KRITERIA
    ═══════════════════════════════════════ */
    public function ambilKriteria(): void
    {
        $this->infoKriteria = '';
        $kode = trim($this->formRujukan['kodeDiagnosa'] ?? '');

        if (!preg_match('/^[A-Z][0-9]{2}\.[0-9]{1,2}$/', $kode)) {
            $this->dispatch('toast', type: 'error', message: "Kode diagnosa \"{$kode}\" bukan ICD-10 rinci ber-titik (contoh A02.0) — kode induk ditolak SATUSEHAT, pilih yang lebih spesifik lewat LOV.");
            return;
        }

        $respon = SisruteTrait::sisrute_get_kriteria_rujukan($kode, null, $this->encounterUuid())->getOriginalContent();
        $code = $respon['metadata']['code'] ?? 0;
        $message = $respon['metadata']['message'] ?? '';
        $data = $respon['response'] ?? [];

        if ($code != 200) {
            $this->dispatch('toast', type: 'error', message: "[{$code}] {$message}" . $this->hintKatalog($message));
            return;
        }

        // linkId dinamis per ICD-10 — beberapa bentuk response pernah terpantau
        $kriteria = $data['kriteriaRujukan'] ?? ($data['kriteria'] ?? ($data['response']['kriteriaRujukan'] ?? []));
        if (isset($kriteria['item']) && is_array($kriteria['item'])) {
            $kriteria = $kriteria['item'];
        }
        if (empty($kriteria) || !is_array($kriteria)) {
            $this->dispatch('toast', type: 'error', message: 'Response tidak memuat kriteria rujukan — kemungkinan gangguan pusat, coba lagi nanti.');
            return;
        }

        $this->formRujukan['kriteriaList'] = collect($kriteria)
            ->map(
                fn($item) => [
                    'linkId' => (string) ($item['linkId'] ?? ''),
                    'text' => (string) ($item['text'] ?? ''),
                    'type' => (string) ($item['type'] ?? 'boolean'),
                ],
            )
            ->filter(fn($item) => $item['linkId'] !== '')
            ->values()
            ->all();
        $this->formRujukan['kriteriaSumber'] = 'server';
        $this->formRujukan['kriteriaPilih'] = '';
        $this->formRujukan['kriteriaIcd9'] = '';
        $this->formRujukan['kriteriaIcd9Desc'] = '';

        $this->parseJejaringWilayah($data);
        $this->simpanDraft();
        $this->infoKriteria = '✓ Kriteria dimuat dari server (' . count($this->formRujukan['kriteriaList']) . ' item). Pilih TEPAT SATU.';
    }

    // Opsi wilayah datang dari response; simpan versi ramping saja (jangan bengkakkan CLOB)
    private function parseJejaringWilayah(array $data): void
    {
        $grup = $data['JejaringWilayah'] ?? ($data['jejaringWilayah'] ?? []);
        $items = collect(is_array($grup) ? $grup : [])->flatMap(fn($grupWilayah) => $grupWilayah['item'] ?? [])->values();

        $keOpsi = fn($item) => collect($item['answerOption'] ?? [])
            ->map(
                fn($opsi) => [
                    'code' => (string) ($opsi['valueCoding']['code'] ?? ''),
                    'display' => (string) ($opsi['valueCoding']['display'] ?? ''),
                ],
            )
            ->filter(fn($opsi) => $opsi['code'] !== '')
            ->values()
            ->all();

        foreach ($items as $item) {
            $teks = strtolower($item['text'] ?? '');
            if (str_contains($teks, 'provinsi')) {
                $this->formRujukan['propinsiOptions'] = $keOpsi($item);
            } elseif (str_contains($teks, 'kabupaten')) {
                $this->formRujukan['kabupatenOptions'] = $keOpsi($item);
            }
        }

        // Default wilayah kita bila belum terisi — kab_id legacy: Tulungagung 3504 / prov 35
        if ($this->formRujukan['kodePropinsi'] === '') {
            $jatim = collect($this->formRujukan['propinsiOptions'])->firstWhere('code', '35');
            $this->formRujukan['kodePropinsi'] = $jatim['code'] ?? '';
            $this->formRujukan['namaPropinsi'] = $jatim['display'] ?? '';
        }
        if ($this->formRujukan['kodeKabupaten'] === '') {
            $tulungagung = collect($this->formRujukan['kabupatenOptions'])->firstWhere('code', '3504');
            $this->formRujukan['kodeKabupaten'] = $tulungagung['code'] ?? '';
            $this->formRujukan['namaKabupaten'] = $tulungagung['display'] ?? '';
        }
        // Kabupaten hanya milik propinsi terpilih (kode kab berawalan kode prop)
        $kodeProp = $this->formRujukan['kodePropinsi'];
        if ($kodeProp !== '') {
            $this->formRujukan['kabupatenOptions'] = collect($this->formRujukan['kabupatenOptions'])->filter(fn($opsi) => str_starts_with($opsi['code'], $kodeProp))->values()->all();
        }
    }

    public function updatedFormRujukanKodePropinsi(string $value): void
    {
        $propinsi = collect($this->formRujukan['propinsiOptions'])->firstWhere('code', $value);
        $this->formRujukan['namaPropinsi'] = $propinsi['display'] ?? '';
        $this->formRujukan['kodeKabupaten'] = '';
        $this->formRujukan['namaKabupaten'] = '';
    }

    public function updatedFormRujukanKodeKabupaten(string $value): void
    {
        $kabupaten = collect($this->formRujukan['kabupatenOptions'])->firstWhere('code', $value);
        $this->formRujukan['namaKabupaten'] = $kabupaten['display'] ?? '';
    }

    /* ═══════════════════════════════════════
     | LANGKAH 2 — CARI FASKES (kandidat)
    ═══════════════════════════════════════ */
    private function bangunItemKriteria(): ?array
    {
        $terpilih = collect($this->formRujukan['kriteriaList'])->firstWhere('linkId', $this->formRujukan['kriteriaPilih']);
        if (!$terpilih) {
            return null;
        }

        // Validasi BPJS (Jul 2026): TEPAT SATU kriteria terisi; teks harus persis
        $jawaban = strtolower($terpilih['type']) === 'text' || str_contains(strtolower($terpilih['text']), 'tindakan') ? ['valueString' => trim($this->formRujukan['kriteriaIcd9'])] : ['valueBoolean' => true];

        return [
            'item' => [
                [
                    'linkId' => $terpilih['linkId'],
                    'text' => $terpilih['text'],
                    'answer' => [$jawaban],
                ],
            ],
        ];
    }

    private function tglRencanaCarbon(): ?Carbon
    {
        try {
            return Carbon::createFromFormat('d/m/Y', $this->formRujukan['tglRencanaKunjungan'] ?? '');
        } catch (\Throwable) {
            return null;
        }
    }

    public function cariFaskes(): void
    {
        $this->infoKandidat = '';

        if (empty($this->formRujukan['kriteriaSumber'])) {
            $this->dispatch('toast', type: 'error', message: 'Ambil kriteria dari server dulu (Langkah 1).');
            return;
        }
        $itemKriteria = $this->bangunItemKriteria();
        if (!$itemKriteria) {
            $this->dispatch('toast', type: 'error', message: 'Pilih TEPAT SATU kriteria rujukan dulu.');
            return;
        }
        $terpilih = collect($this->formRujukan['kriteriaList'])->firstWhere('linkId', $this->formRujukan['kriteriaPilih']);
        if (isset($itemKriteria['item'][0]['answer'][0]['valueString']) && $itemKriteria['item'][0]['answer'][0]['valueString'] === '') {
            $this->dispatch('toast', type: 'error', message: "Kriteria \"{$terpilih['text']}\" butuh kode tindakan ICD-9-CM (menentukan kandidat RS).");
            return;
        }
        $tglRencana = $this->tglRencanaCarbon();
        if (!$tglRencana) {
            $this->dispatch('toast', type: 'error', message: 'Tanggal rencana kunjungan tidak valid (format dd/mm/yyyy).');
            return;
        }

        $payload = [
            'kodeFaskesSatuSehat' => (string) env('SATUSEHAT_ORGANIZATION_ID'),
            'kodeSpesialis' => (string) ($this->formRujukan['kodeSpesialis'] ?? ''),
            'kodeSarana' => (string) ($this->formRujukan['kodeSarana'] ?? ''),
            'kodeDiagnosa' => (string) $this->formRujukan['kodeDiagnosa'],
            // Dua format tanggal berbeda memang aturan BPJS — jangan "dirapikan"
            'tglRencanaKunjungan' => $tglRencana->format('Y-m-d'),
            'estimasiRujuk' => $tglRencana->format('d-m-Y'),
            'kriteriaRujukan' => $itemKriteria,
            'codeJejaringWilayah' => [
                'kodePropinsi' => (string) $this->formRujukan['kodePropinsi'],
                'namaPropinsi' => (string) $this->formRujukan['namaPropinsi'],
                'kodeKabupaten' => (string) $this->formRujukan['kodeKabupaten'],
                'namaKabupaten' => (string) $this->formRujukan['namaKabupaten'],
            ],
            'encounter' => ['reference' => 'Encounter/' . $this->encounterUuid()],
        ];

        $respon = SisruteTrait::sisrute_get_faskes_rujukan($payload)->getOriginalContent();
        $code = $respon['metadata']['code'] ?? 0;
        $message = $respon['metadata']['message'] ?? '';
        $data = $respon['response'] ?? [];

        if ($code != 200) {
            $this->dispatch('toast', type: 'error', message: "[{$code}] {$message}" . $this->hintKatalog($message));
            return;
        }

        // Bentuk list kandidat dari gateway BPJS bervariasi — ambil array pertama yang cocok
        $mentah = collect([$data['faskes'] ?? null, $data['list'] ?? null, $data['faskesRujukan'] ?? null, is_array($data) && array_is_list($data) ? $data : null])->first(fn($kandidat) => is_array($kandidat) && $kandidat !== []);

        $this->formRujukan['kandidatList'] = collect($mentah ?? [])
            ->map(
                fn($faskes) => [
                    'kdppk' => (string) ($faskes['kdppk'] ?? ($faskes['bpjs-code'] ?? '')),
                    // Response memberi "Organization/100027716" — awalan WAJIB dibuang.
                    // Nilai ini dikirim sebagai kdppkSatuSehatTujuanRujukan, sedangkan
                    // kodeFaskesSatuSehat milik kita dikirim polos; kalau formatnya beda
                    // BPJS menolak dengan "PPK tidak ditemukan di pemetaan".
                    'kodeFaskesSatuSehat' => str_replace('Organization/', '', (string) ($faskes['kodeFaskesSatuSehat'] ?? ($faskes['kemkes-code'] ?? ''))),
                    // nmppk = NAMA faskes. Jangan pakai nmkc — itu nama kota/kabupaten,
                    // sehingga seluruh baris tampil sama ("TULUNGAGUNG").
                    'nama' => (string) ($faskes['nmppk'] ?? ($faskes['nama'] ?? ($faskes['display'] ?? ''))),
                    'kota' => (string) ($faskes['nmkc'] ?? ''),
                    'alamat' => (string) ($faskes['alamatPpk'] ?? ''),
                    'telp' => (string) ($faskes['telpPpk'] ?? ''),
                    'kelas' => (string) ($faskes['kelas'] ?? ($faskes['strata'] ?? '')),
                    'strata' => (string) ($faskes['strataSatuSehat'] ?? ''),
                    'distance' => (string) ($faskes['distance'] ?? ''),
                    'persentase' => (string) ($faskes['persentase'] ?? ''),
                    'kapasitas' => (string) ($faskes['kapasitas'] ?? ''),
                    'jmlRujuk' => (string) ($faskes['jmlRujuk'] ?? ''),
                ],
            )
            ->values()
            ->all();
        $this->formRujukan['kandidatIdx'] = null;
        $this->simpanDraft();

        // Tanpa kandidat = memang tidak ada faskes yang cocok — bukan error
        $this->infoKandidat = empty($this->formRujukan['kandidatList']) ? 'Tidak ada kandidat faskes untuk kombinasi diagnosa/kriteria/wilayah ini. Diagnosa yang dinilai mampu ditangani sendiri memang tidak diberi kandidat.' : '✓ ' . count($this->formRujukan['kandidatList']) . ' kandidat ditemukan — pilih salah satu.';
    }

    // Kirim INDEKS, bukan string (argumen ber-& lewat wireClick rusak oleh double-escape)
    /**
     * Keadaan tiap langkah alur rujukan Rawat Jalan (SISRUTE). Dihitung dari data,
     * bukan disimpan — state tersendiri akan berbohong saat kandidat/kriteria
     * ter-reset karena diagnosa atau wilayah diganti.
     *
     * Hanya TIGA langkah: jalur BPJS tidak punya tahap persetujuan faskes tujuan
     * (accept/reject cuma berlaku untuk IGD & Ranap).
     */
    /** Dipakai template; kelasnya tak terjangkau dari zona template Volt. */
    public function rujukanJarakTampil($nilai): string
    {
        return RujukanTampil::jarak($nilai);
    }

    public function langkahRujukan(): array
    {
        $sudahKirim = !empty($this->formRujukan['hasil']['noRujukanSatuSehat']);
        $adaKandidat = ($this->formRujukan['kandidatIdx'] ?? null) !== null;
        $kriteriaTerisi = trim((string) $this->formRujukan['kriteriaPilih']) !== ''
            && ($this->formRujukan['kriteriaPilih'] !== 'tindakan' || trim((string) $this->formRujukan['kriteriaIcd9']) !== '');
        $dasarTerisi = trim((string) $this->formRujukan['kodeDiagnosa']) !== '' && $kriteriaTerisi;

        $keadaanLangkah = fn(bool $selesai, bool $aktif) => $selesai ? 'done' : ($aktif ? 'current' : 'todo');

        return [
            [
                'n' => 1,
                'title' => 'Diagnosa & Kriteria',
                'hint' => $dasarTerisi ? null : 'ambil kriteria lalu pilih satu',
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
                'title' => 'Kirim Rujukan',
                'hint' => $sudahKirim ? 'No. ' . $this->formRujukan['hasil']['noRujukanSatuSehat'] : 'terbit nomor BPJS & SATUSEHAT',
                'state' => $keadaanLangkah($sudahKirim, $adaKandidat),
            ],
        ];
    }

    public function pilihKandidat(int $index): void
    {
        if ($this->isFormLocked) {
            return;
        }

        $kandidat = $this->formRujukan['kandidatList'][$index] ?? null;
        if (!$kandidat) {
            return;
        }
        // Kandidat tanpa kode BPJS (string "null"/kosong) tidak sah untuk rujukan BPJS
        if ($kandidat['kdppk'] === '' || strtolower($kandidat['kdppk']) === 'null') {
            $this->dispatch('toast', type: 'error', message: "\"{$kandidat['nama']}\" tidak punya kode PPK BPJS — tidak bisa jadi tujuan rujukan BPJS.");
            return;
        }

        // Menekan baris yang SUDAH terpilih = membatalkan pilihan, supaya
        // togglenya tidak cuma bisa menyala.
        if ($this->formRujukan['kandidatIdx'] === $index) {
            $this->formRujukan['kandidatIdx'] = null;
            $this->infoKandidat = '';
            return;
        }

        $this->formRujukan['kandidatIdx'] = $index;
        $this->infoKandidat = "Tujuan: {$kandidat['nama']} (PPK {$kandidat['kdppk']} / SATUSEHAT {$kandidat['kodeFaskesSatuSehat']})";
    }

    /* ═══════════════════════════════════════
     | LANGKAH 3 — KIRIM RUJUKAN
    ═══════════════════════════════════════ */
    public function kirimRujukan(): void
    {
        $this->infoKirim = '';

        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form read-only, tidak dapat mengirim rujukan.');
            return;
        }
        $kurang = $this->prasyaratKurang();
        if (!empty($kurang)) {
            $this->dispatch('toast', type: 'error', message: 'Data belum siap: ' . implode('; ', $kurang) . '.');
            return;
        }
        $kandidat = $this->formRujukan['kandidatList'][$this->formRujukan['kandidatIdx'] ?? -1] ?? null;
        if (!$kandidat) {
            $this->dispatch('toast', type: 'error', message: 'Pilih kandidat faskes tujuan dulu (Langkah 2). Tujuan WAJIB dari daftar kandidat.');
            return;
        }
        $tidakCocok = $this->pasienTidakCocok();
        if (!empty($tidakCocok)) {
            $this->dispatch('toast', type: 'error', message: 'Data pasien tidak konsisten: ' . implode('; ', $tidakCocok) . '. Rujukan TIDAK dikirim.');
            return;
        }
        $itemKriteria = $this->bangunItemKriteria();
        $tglRencana = $this->tglRencanaCarbon();
        if (!$itemKriteria || !$tglRencana) {
            $this->dispatch('toast', type: 'error', message: 'Kriteria/tanggal rencana belum lengkap.');
            return;
        }

        $poliRujukan = trim($this->formRujukan['poliRujukan']) !== '' ? trim($this->formRujukan['poliRujukan']) : (string) ($this->formRujukan['kodeSpesialis'] ?? '');

        $payloadRujukan = [
            'noSep' => $this->nomorSep(),
            'tglRujukan' => Carbon::now(config('app.timezone'))->format('Y-m-d'),
            'tglRencanaKunjungan' => $tglRencana->format('Y-m-d'),
            // ppkDirujuk (BPJS) & kdppkSatuSehatTujuanRujukan WAJIB RS yang sama — dari kandidat
            'ppkDirujuk' => $kandidat['kdppk'],
            'jnsPelayanan' => '2',
            'catatan' => (string) ($this->formRujukan['catatan'] ?? ''),
            'diagRujukan' => (string) $this->formRujukan['kodeDiagnosa'],
            'tipeRujukan' => '0',
            'poliRujukan' => $poliRujukan,
            'user' => auth()->user()->name ?? 'Sirus',
            'satuSehatRujukan' => [
                'kodeFaskesSatuSehat' => (string) env('SATUSEHAT_ORGANIZATION_ID'),
                'idPasienSatuSehat' => $this->patientUuid(),
                'kdppkSatuSehatTujuanRujukan' => $kandidat['kodeFaskesSatuSehat'],
                'kdDokterSatuSehat' => $this->dokterUuid(),
                // Di Insert: UUID polos tanpa prefix "Encounter/" (sample lapangan 09/07/26)
                'encounter' => ['reference' => $this->encounterUuid()],
                'patientInstruction' => 'Rujukan ke ' . $kandidat['nama'],
                'kriteriaRujukan' => $itemKriteria,
                'keteranganRujukan' => (string) ($this->formRujukan['catatan'] ?? ''),
                'codeJejaringWilayah' => [
                    'kodePropinsi' => (string) $this->formRujukan['kodePropinsi'],
                    'namaPropinsi' => (string) $this->formRujukan['namaPropinsi'],
                    'kodeKabupaten' => (string) $this->formRujukan['kodeKabupaten'],
                    'namaKabupaten' => (string) $this->formRujukan['namaKabupaten'],
                ],
            ],
        ];

        $respon = SisruteTrait::sisrute_insert_rujukan($payloadRujukan)->getOriginalContent();
        $code = $respon['metadata']['code'] ?? 0;
        $message = $respon['metadata']['message'] ?? '';
        $data = $respon['response'] ?? [];

        if ($code != 200) {
            $this->dispatch('toast', type: 'error', message: "[{$code}] {$message}" . $this->hintKatalog($message));
            return;
        }

        $rujukan = $data['rujukan'] ?? $data;
        $hasil = [
            'noRujukan' => (string) ($rujukan['noRujukan'] ?? ''),
            'noRujukanSatuSehat' => (string) ($rujukan['noRujukanSatuSehat'] ?? ''),
            'serviceRequestId' => (string) ($rujukan['serviceRequestId'] ?? ''),
            'tglRujukan' => $payloadRujukan['tglRujukan'],
            'tujuanNama' => $kandidat['nama'],
            'tujuanPpk' => $kandidat['kdppk'],
            'tujuanSatuSehat' => $kandidat['kodeFaskesSatuSehat'],
            'dikirimOleh' => $payloadRujukan['user'],
            'dikirimPada' => Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s'),
        ];

        // Sukses SEJATI = nomor SATUSEHAT terbit. Tanpa itu = gagal walau resource terbentuk
        if ($hasil['noRujukanSatuSehat'] === '') {
            $this->dispatch('toast', type: 'error', message: 'BPJS merespons 200 tapi nomor Rujukan SATUSEHAT tidak terbit — gangguan pusat yang dikenal (kambuhan). Data TIDAK disimpan; coba kirim ulang nanti.');
            return;
        }

        $this->formRujukan['hasil'] = $hasil;
        $this->simpanDraft('Kirim Rujukan Kompetensi → ' . $kandidat['nama'] . ' (No ' . $hasil['noRujukan'] . ' / SS ' . $hasil['noRujukanSatuSehat'] . ')');
        $this->infoKirim = '';
        $this->dispatch('toast', type: 'success', message: 'Rujukan terkirim. No BPJS ' . $hasil['noRujukan'] . ', No SATUSEHAT ' . $hasil['noRujukanSatuSehat']);
    }

    /* ═══════════════════════════════════════
     | HAPUS RUJUKAN (method DELETE di gateway)
    ═══════════════════════════════════════ */
    public function hapusRujukan(): void
    {
        $noRujukan = $this->formRujukan['hasil']['noRujukan'] ?? '';
        if ($noRujukan === '' || $this->isFormLocked) {
            return;
        }

        $respon = SisruteTrait::sisrute_delete_rujukan($noRujukan, auth()->user()->name ?? 'Sirus')->getOriginalContent();
        $code = $respon['metadata']['code'] ?? 0;
        $message = $respon['metadata']['message'] ?? '';

        if ($code != 200) {
            $this->dispatch('toast', type: 'error', message: "Hapus gagal [{$code}] {$message}" . $this->hintKatalog($message));
            return;
        }

        $hasilLama = $this->formRujukan['hasil'];
        $this->formRujukan['hasil'] = [];
        $this->simpanDraft('Hapus Rujukan Kompetensi No ' . ($hasilLama['noRujukan'] ?? '-'));
        $this->dispatch('toast', type: 'success', message: 'Rujukan dibatalkan/dihapus.');
    }

    /* ═══════════════════════════════════════
     | PERSIST STATE — retry tanpa isi ulang
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
                $data['rujukanKompetensi'] = $this->formRujukan;
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

    /* ═══════════════════════════════════════
     | KATALOG ERROR → hint (docs/rujukan-kompetensi.md §4)
    ═══════════════════════════════════════ */
    private function hintKatalog(string $pesan): string
    {
        $pesanKecil = strtolower($pesan);
        $hint = match (true) {
            str_contains($pesanKecil, 'not registered for this service') => 'Cons-ID belum didaftarkan untuk service SISRUTE — ajukan aktivasi ke BPJS (bukan bug aplikasi).',
            str_contains($pesanKecil, 'consumer id is expired') => 'Masa berlaku Cons-ID habis — ajukan reaktivasi/extend ke BPJS (form pengajuan cons-id, keterangan "reaktivasi"); bukan bug aplikasi.',
            str_contains($pesanKecil, 'index was out of range') => 'Mapping faskes BPJS↔SATUSEHAT belum ada, atau kode faskes SATUSEHAT salah — lapor minta mapping.',
            str_contains($pesanKecil, 'tidak mengandung kriteria'), str_contains($pesanKecil, 'tidak mengandung faskes') => 'Cek ICD-10 sudah 4-karakter; kalau banyak faskes kena serentak = gangguan pusat, coba lagi nanti.',
            str_contains($pesanKecil, 'linkid') => 'Kriteria kedaluwarsa — klik Muat Ulang Kriteria lalu pilih ulang.',
            str_contains($pesanKecil, 'salah satu dari') => 'Hanya boleh TEPAT SATU kriteria terisi.',
            str_contains($pesanKecil, 'pemetaan') || str_contains($pesanKecil, 'tidak sesuai dengan ppk') => 'Pasangan kode BPJS↔SATUSEHAT tujuan tidak konsisten / RS belum termapping — pilih ulang dari kandidat.',
            str_contains($pesanKecil, 'gagal mendapatkan nomor rujukan') => 'Gangguan penerbitan nomor di pusat (dikenal kambuhan) — coba kirim ulang nanti, bukti otomatis terekam.',
            str_contains($pesanKecil, 'decimal') => 'Bug sisi pusat (dikenal sejak 11/08/2026) — tunggu perbaikan BPJS/SATUSEHAT.',
            str_contains($pesanKecil, 'nosep tidak ditemukan') => 'SEP belum sinkron di server BPJS — cek SEP lalu coba lagi.',
            str_contains($pesanKecil, 'coba lagi nanti'), str_contains($pesanKecil, 'tidak dapat dihubungi'), str_contains($pesanKecil, 'koneksi') => 'Gangguan jaringan BPJS/SATUSEHAT — data isian tersimpan, cukup klik ulang nanti.',
            default => '',
        };
        return $hint !== '' ? " — {$hint}" : '';
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
                        Rujukan Berbasis Kompetensi — Poli RS Lain (BPJS SISRUTE)
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
                Rujukan pasien rawat inap ke poli RS lain lewat BPJS (vclaim-sisrute-rest), yang meneruskannya
                ke SATUSEHAT. Alurnya tiga langkah: ambil kriteria sesuai diagnosa, cari kandidat faskes, lalu kirim.
            </p>

            @if ($sudahTerkirim)
                <div class="flex flex-wrap gap-x-6 gap-y-1 text-sm text-muted dark:text-gray-400">
                    <span>No. Rujukan BPJS: <strong class="text-ink dark:text-gray-200">{{ $formRujukan['hasil']['noRujukan'] ?? '-' }}</strong></span>
                    <span>No. SATUSEHAT: <strong class="text-ink dark:text-gray-200">{{ $formRujukan['hasil']['noRujukanSatuSehat'] }}</strong></span>
                </div>
            @endif
        </div>
    </div>

    {{-- ══ MODAL FORMULIR ══ --}}
    <x-modal name="rujukan-kompetensi-ri-{{ $riHdrNo }}" size="full" height="full" focusable>
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
                                Rawat Inap → poli RS lain · lewat BPJS (SISRUTE) yang meneruskan ke SATUSEHAT
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
                        wire:key="rujukan-kompetensi-ri-display-pasien-{{ $riHdrNo ?? 'init' }}" />

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
            <p class="mt-2 text-xs">Langkah 1 (Ambil Kriteria) &amp; 2 (Cari Kandidat) tetap bisa dijalankan sambil melengkapi ini.</p>
        </div>
    @endif

    {{-- HASIL (sudah terkirim) --}}
    @if (!empty($formRujukan['hasil']['noRujukanSatuSehat']))
        <div class="p-3 space-y-1 text-sm border border-green-200 rounded-lg bg-green-50 dark:bg-green-950 dark:border-green-900">
            <p class="font-semibold text-green-800 dark:text-green-200">Rujukan sudah terkirim</p>
            <table class="text-gray-700 dark:text-gray-200">
                <tr><td class="pr-3">No Rujukan BPJS</td><td class="font-mono font-semibold">{{ ($formRujukan['hasil']['noRujukan'] ?? '') ?: '-' }}</td></tr>
                <tr><td class="pr-3">No Rujukan SATUSEHAT</td><td class="font-mono font-semibold">{{ $formRujukan['hasil']['noRujukanSatuSehat'] }}</td></tr>
                <tr><td class="pr-3">Tujuan</td><td>{{ $formRujukan['hasil']['tujuanNama'] ?? '-' }} (PPK {{ $formRujukan['hasil']['tujuanPpk'] ?? '-' }})</td></tr>
                <tr><td class="pr-3">Dikirim</td><td>{{ $formRujukan['hasil']['dikirimPada'] ?? '-' }} oleh {{ $formRujukan['hasil']['dikirimOleh'] ?? '-' }}</td></tr>
            </table>
            @if (!$isFormLocked)
                <div class="pt-2">
                    <x-danger-button type="button" wire:click="hapusRujukan" wire:confirm="Batalkan/hapus rujukan ini di BPJS & SATUSEHAT?"
                        wire:loading.attr="disabled" wire:target="hapusRujukan">
                        <span wire:loading.remove wire:target="hapusRujukan">Batalkan Rujukan</span>
                        <span wire:loading wire:target="hapusRujukan" class="inline-flex items-center gap-1"><x-loading /> Membatalkan...</span>
                    </x-danger-button>
                </div>
            @endif
            @if ($infoKirim !== '')
                <p class="pt-1 text-red-700 dark:text-red-300">{{ $infoKirim }}</p>
            @endif
        </div>
    @else

    {{-- Panduan pemakaian — komponen bersama; varian SISRUTE (tanpa persetujuan). --}}
    <div class="mb-3">
        <x-rujukan.panduan-kirim jalur="sisrute" :jalurGanda="false" />
    </div>

    {{-- Penanda langkah: hanya 3, karena jalur BPJS tidak punya tahap persetujuan. --}}
    <div class="p-3 mb-3 overflow-x-auto bg-canvas border border-hairline rounded-lg dark:bg-gray-800 dark:border-gray-700">
        <x-stepper :steps="$this->langkahRujukan()" />
    </div>

        {{-- Tiga langkah sejajar kiri→kanan; menumpuk kembali di layar < 1280px --}}
        <div class="grid items-start grid-cols-1 gap-4 xl:grid-cols-3">

        {{-- LANGKAH 1 — DIAGNOSA & KRITERIA --}}
        <div class="p-3 space-y-3 bg-canvas border border-hairline rounded-lg dark:bg-gray-800 dark:border-gray-700">
            <p class="flex flex-wrap items-center gap-1.5 text-sm font-semibold text-gray-700 dark:text-gray-200"><x-step-number :n="1" /><span class="ml-0.5">Diagnosa &amp; Kriteria Rujukan</span></p>

            {{-- Pilih diagnosa dari EMR --}}
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

            {{-- LOV diagnosa — pencarian bebas; kode kategori 3-karakter terblokir otomatis --}}
            <div class="max-w-md">
                <livewire:lov.diagnosa.lov-diagnosa label="Cari Diagnosa Rujukan (ICD-10)"
                    target="rujukanRajalDiagnosaRI"
                        :initialDiagnosaId="$formRujukan['kodeDiagnosa'] ?: null" :disabled="$isFormLocked"
                    wire:key="lov-diagnosa-rujukan-kompetensi-{{ $riHdrNo }}" />
                    <p class="mt-1 text-xs text-muted-soft">Wajib ber-titik (A02.0) — kode induk ditolak SATUSEHAT. @if (filled($formRujukan['kodeDiagnosa'] ?? ''))<span class="font-mono font-semibold text-ink dark:text-gray-200">Kode terkirim: {{ $formRujukan['kodeDiagnosa'] }}</span>@endif</p>
                </div>

                <div class="grid grid-cols-1 gap-3">
                <div>
                    <livewire:lov.poli.lov-poli label="Kode Spesialis (poli BPJS)"
                        target="rujukanRajalSpesialisRI"
                        :initialPoliId="$this->poliIdSpesialis" :disabled="$isFormLocked"
                        wire:key="lov-poli-spesialis-rajal-ri-{{ $riHdrNo }}" />
                    <p class="mt-1 text-xs text-muted-soft">
                        Terisi otomatis dari poli kunjungan; ganti bila poli tujuan berbeda.
                        @if (filled($formRujukan['kodeSpesialis']))
                            <span class="font-mono font-semibold text-ink dark:text-gray-200">Kode terkirim: {{ $formRujukan['kodeSpesialis'] }}</span>
                        @endif
                    </p>
                </div>
                <div>
                    <x-input-label value="Tgl Rencana Kunjungan" class="mb-1" />
                    <x-text-input wire:model.blur="formRujukan.tglRencanaKunjungan" placeholder="dd/mm/yyyy"
                        :disabled="$isFormLocked" class="w-full" />
                    <p class="mt-1 text-xs text-muted-soft">Boleh hari ini.</p>
                </div>
            </div>

            <div class="flex flex-col items-start gap-2">
                <x-secondary-button type="button" wire:click="ambilKriteria" wire:loading.attr="disabled"
                    wire:target="ambilKriteria" :disabled="$isFormLocked">
                    <span wire:loading.remove wire:target="ambilKriteria">
                        {{ $formRujukan['kriteriaSumber'] === 'server' ? '🔄 Muat Ulang Kriteria' : '⬇ Ambil Kriteria dari Server' }}
                    </span>
                    <span wire:loading wire:target="ambilKriteria" class="inline-flex items-center gap-1"><x-loading /> Memuat kriteria...</span>
                </x-secondary-button>
                @if ($formRujukan['kriteriaSumber'] === 'server')
                    <span class="px-2 py-0.5 text-xs text-green-800 bg-green-100 rounded-full dark:bg-green-900 dark:text-green-200">✓ Dari Server</span>
                @else
                    <span class="px-2 py-0.5 text-xs text-amber-800 bg-amber-100 rounded-full dark:bg-amber-900 dark:text-amber-200">⚠ Belum dimuat</span>
                @endif
            </div>
            @if ($infoKriteria !== '')
                <p class="text-sm {{ str_starts_with($infoKriteria, '✓') ? 'text-green-700 dark:text-green-300' : 'text-red-700 dark:text-red-300' }}">{{ $infoKriteria }}</p>
            @endif

            {{-- Radio kriteria — TEPAT SATU --}}
            @if (!empty($formRujukan['kriteriaList']))
                <div class="space-y-2">
                    <p class="text-xs text-muted-soft">Pilih <b>tepat satu</b> kriteria (aturan BPJS sejak Juli 2026):</p>
                    {{-- satu kolom: kartu radio ini kini tinggal 1/3 lebar layar --}}
                    <div class="grid grid-cols-1 gap-2">
                        @foreach ($formRujukan['kriteriaList'] as $kriteria)
                            <x-radio-button :label="$kriteria['text'] . ' — linkId ' . $kriteria['linkId']"
                                :value="$kriteria['linkId']" name="kriteriaPilih-{{ $riHdrNo }}"
                                wire:model.live="formRujukan.kriteriaPilih" :disabled="$isFormLocked" />
                        @endforeach
                    </div>
                    @php
                        $kriteriaTerpilih = collect($formRujukan['kriteriaList'])->firstWhere('linkId', $formRujukan['kriteriaPilih']);
                        $butuhIcd9 = $kriteriaTerpilih && (strtolower($kriteriaTerpilih['type']) === 'text' || str_contains(strtolower($kriteriaTerpilih['text']), 'tindakan'));
                    @endphp
                    @if ($butuhIcd9)
                        <div class="max-w-md space-y-2">
                            <livewire:lov.procedure.lov-procedure label="Cari Tindakan Rujukan (ICD-9-CM)"
                                target="rujukanRajalIcd9RI" :disabled="$isFormLocked"
                                wire:key="lov-procedure-rujukan-kompetensi-{{ $riHdrNo }}" />
                            <div>
                                <x-input-label value="Kode Tindakan ICD-9-CM" class="mb-1" />
                                <x-text-input wire:model.live="formRujukan.kriteriaIcd9" :disabled="true" class="w-full" />
                                @if (filled($formRujukan['kriteriaIcd9Desc'] ?? ''))
                                    <p class="mt-1 text-xs text-body dark:text-gray-300">{{ $formRujukan['kriteriaIcd9Desc'] }}</p>
                                @endif
                                <p class="mt-1 text-xs text-muted-soft">Harus valid &amp; sesuai diagnosa — menentukan kandidat RS.</p>
                            </div>
                        </div>
                    @endif
                </div>

                {{-- Wilayah rujukan (auto dari server, bisa diganti) --}}
                <div class="grid grid-cols-1 gap-3">
                    <div>
                        <x-input-label value="Propinsi Jejaring" class="mb-1" />
                        <x-select-input wire:model.live="formRujukan.kodePropinsi" :disabled="$isFormLocked" class="w-full">
                            <option value="">Pilih propinsi</option>
                            @foreach ($formRujukan['propinsiOptions'] as $opsi)
                                <option value="{{ $opsi['code'] }}">{{ $opsi['code'] }} — {{ $opsi['display'] }}</option>
                            @endforeach
                        </x-select-input>
                    </div>
                    <div>
                        <x-input-label value="Kabupaten/Kota" class="mb-1" />
                        <x-select-input wire:model.live="formRujukan.kodeKabupaten" :disabled="$isFormLocked" class="w-full">
                            <option value="">Semua (tidak dibatasi)</option>
                            @foreach (collect($formRujukan['kabupatenOptions'])->filter(fn($opsi) => $formRujukan['kodePropinsi'] === '' || str_starts_with($opsi['code'], $formRujukan['kodePropinsi'])) as $opsi)
                                <option value="{{ $opsi['code'] }}">{{ $opsi['code'] }} — {{ $opsi['display'] }}</option>
                            @endforeach
                        </x-select-input>
                    </div>
                </div>
            @endif
        </div>

        {{-- LANGKAH 2 — KANDIDAT FASKES --}}
        <div class="p-3 space-y-3 bg-canvas border border-hairline rounded-lg dark:bg-gray-800 dark:border-gray-700">
            <div class="flex flex-col items-start gap-2">
                <p class="flex flex-wrap items-center gap-1.5 text-sm font-semibold text-gray-700 dark:text-gray-200"><x-step-number :n="2" /><span class="ml-0.5">Kandidat Faskes Tujuan</span></p>
                <x-secondary-button type="button" wire:click="cariFaskes" wire:loading.attr="disabled"
                    wire:target="cariFaskes" :disabled="$isFormLocked">
                    <span wire:loading.remove wire:target="cariFaskes">🔍 Cari Faskes</span>
                    <span wire:loading wire:target="cariFaskes" class="inline-flex items-center gap-1"><x-loading /> Mencari...</span>
                </x-secondary-button>
            </div>
            @if ($infoKandidat !== '')
                <p class="text-sm {{ str_starts_with($infoKandidat, '✓') || str_starts_with($infoKandidat, 'Tujuan:') ? 'text-green-700 dark:text-green-300' : 'text-muted-soft' }}">{{ $infoKandidat }}</p>
            @endif

            @if (!empty($formRujukan['kandidatList']))
                {{-- Kolom sempit (1/3 layar): PPK, kelas, jarak & beban dilebur ke sel Faskes
                     supaya kolom Aksi selalu kelihatan tanpa geser mendatar. --}}
                <div class="mt-2 overflow-x-auto border bg-canvas rounded-2xl border-hairline dark:border-gray-700">
                    <table class="ds-table">
                        <thead>
                            <tr>
                                <th class="ds-c w-10">No</th>
                                <th>Faskes Tujuan</th>
                                <th class="ds-c w-28">Pilih</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($formRujukan['kandidatList'] as $indexKandidat => $kandidat)
                                @php
                                    $tanpaPpk = $kandidat['kdppk'] === '' || strtolower($kandidat['kdppk']) === 'null';
                                    $terpilih = $formRujukan['kandidatIdx'] === $indexKandidat;
                                    // Sebagian alamatPpk sudah memuat nama kota di ekornya — jangan
                                    // ditempeli nmkc lagi supaya tak tertulis dua kali.
                                    $alamat = trim($kandidat['alamat'] ?? '');
                                    $kota = trim($kandidat['kota'] ?? '');
                                    $kotaSudahAda = $kota !== '' && stripos($alamat, $kota) !== false;
                                    $alamatKota = trim($alamat . ($kota !== '' && !$kotaSudahAda ? ' · ' . $kota : ''), ' ·');
                                @endphp
                                <tr class="{{ $terpilih ? 'bg-brand-green/5 dark:bg-brand-lime/5' : '' }}">
                                    <td class="ds-c ds-td-meta">{{ $indexKandidat + 1 }}</td>
                                    <td>
                                        <span class="ds-td-strong">{{ ($kandidat['nama'] ?? '') ?: '-' }}</span>
                                        @if (filled($alamatKota))
                                            <span class="block ds-td-meta">{{ $alamatKota }}</span>
                                        @endif
                                        <span class="flex flex-wrap items-center mt-1 gap-x-2 gap-y-1 text-xs text-muted dark:text-gray-400">
                                            @if ($tanpaPpk)
                                                <x-badge variant="gray">non-BPJS</x-badge>
                                            @else
                                                <span class="font-mono">PPK {{ $kandidat['kdppk'] }}</span>
                                            @endif
                                            @if (filled($kandidat['kelas'] ?? ''))
                                                <span>· Kelas {{ $kandidat['kelas'] }}</span>
                                            @endif
                                            @if (filled($kandidat['distance'] ?? ''))
                                                <span class="tabular-nums">· {{ $this->rujukanJarakTampil($kandidat['distance']) }}</span>
                                            @endif
                                            @if (($kandidat['jmlRujuk'] ?? '') !== '')
                                                <span class="tabular-nums" title="Rujukan masuk / kapasitas">· beban {{ $kandidat['jmlRujuk'] }}/{{ ($kandidat['kapasitas'] ?? '') ?: '-' }}</span>
                                            @endif
                                        </span>
                                    </td>
                                    {{-- wireClick dikirim INDEKS angka: nama faskes ber-& akan
                                         ter-escape ganda dan aksinya gagal diam-diam. --}}
                                    <td class="ds-c">
                                        <x-toggle :current="$terpilih ? 'Ya' : 'Tidak'" trueValue="Ya" falseValue="Tidak"
                                            :disabled="$isFormLocked || $tanpaPpk"
                                            wireClick="pilihKandidat({{ $indexKandidat }})"
                                            :label="$terpilih ? 'Dipilih' : ($tanpaPpk ? 'Tak bisa' : 'Pilih')" />
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        {{-- LANGKAH 3 — KIRIM --}}
        <div class="p-3 space-y-3 bg-canvas border border-hairline rounded-lg dark:bg-gray-800 dark:border-gray-700">
            <p class="flex flex-wrap items-center gap-1.5 text-sm font-semibold text-gray-700 dark:text-gray-200"><x-step-number :n="3" /><span class="ml-0.5">Kirim Rujukan</span></p>
            <div class="grid grid-cols-1 gap-3">
                <div>
                    <livewire:lov.poli.lov-poli label="Poli Rujukan (kode BPJS 3 huruf)"
                        placeholder="kosongkan = ikut kode spesialis"
                        target="rujukanRajalPoliRI"
                        :initialPoliId="$this->poliIdPoliRujukan" :disabled="$isFormLocked"
                        wire:key="lov-poli-poli-rujukan-kompetensi-ri-{{ $riHdrNo }}" />
                    <p class="mt-1 text-xs text-muted-soft">
                        Dibiarkan kosong = memakai Kode Spesialis di Langkah 1.
                        @if (filled($formRujukan['poliRujukan']))
                            <span class="font-mono font-semibold text-ink dark:text-gray-200">Kode terkirim: {{ $formRujukan['poliRujukan'] }}</span>
                        @endif
                    </p>
                </div>
                <div>
                    <x-input-label value="Catatan Rujukan" class="mb-1" />
                    <x-text-input wire:model.blur="formRujukan.catatan" placeholder="Catatan untuk faskes tujuan"
                        :disabled="$isFormLocked" class="w-full" />
                </div>
            </div>
            @if ($infoKirim !== '')
                <p class="text-sm text-red-700 dark:text-red-300">{{ $infoKirim }}</p>
            @endif
        </div>

        </div>{{-- /grid tiga langkah --}}
    @endif
                </div>
            </div>

            {{-- FOOTER --}}
            <div class="sticky bottom-0 z-10 px-6 py-4 bg-canvas border-t border-hairline dark:bg-gray-900 dark:border-gray-700">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <p class="text-sm text-muted dark:text-gray-400">
                        Perubahan tersimpan otomatis ke kunjungan ini — aman ditutup lalu dilanjutkan nanti.
                    </p>

                    {{-- Tombol kirim dipindah ke footer yang selalu menempel, seperti
                         panel UGD/Ranap. Angkanya mengikuti penanda langkah di atas. --}}
                    <div class="flex flex-wrap items-center justify-end gap-2 ml-auto">
                        @if (empty($formRujukan['hasil']['noRujukanSatuSehat']) && !$isFormLocked)
                            <x-primary-button type="button" wire:click="kirimRujukan"
                                wire:loading.attr="disabled" wire:target="kirimRujukan"
                                title="Langkah 3 — kirim ke BPJS, diteruskan ke SATUSEHAT">
                                <span wire:loading.remove wire:target="kirimRujukan" class="inline-flex items-center gap-2">
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
