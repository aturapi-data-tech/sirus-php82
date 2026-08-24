<?php

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Ri\EmrRITrait;
use App\Http\Traits\SATUSEHAT\SatuSehatRujukanTrait;

new class extends Component {
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

        $tersimpan = $data['rujukanKompetensi'] ?? [];
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

        $tersimpan = $data['rujukanKompetensi'] ?? [];
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
            'taskKandidatId' => '',
            'kandidatList' => [],
            'kandidatIdx' => null,
            'carePlanId' => '',
            'taskApprovalId' => '',
            'hasil' => [],
        ];
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

    private function encounterUuid(): string
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
        if (!preg_match('/^[A-Z][0-9]{2}\.[0-9]{1,2}$/', $this->formRujukan['kodeDiagnosa'] ?? '')) {
            $this->dispatch('toast', type: 'error', message: 'Kode diagnosa harus ICD-10 rinci ber-titik (contoh I61.9).');
            return;
        }
        if (!in_array($this->formRujukan['kriteriaPilih'], ['terapi', 'tindakan', 'upaya'], true)) {
            $this->dispatch('toast', type: 'error', message: 'Pilih TEPAT SATU kriteria rujukan dulu.');
            return;
        }
        if ($this->formRujukan['kriteriaPilih'] === 'tindakan' && trim($this->formRujukan['kriteriaIcd9']) === '') {
            $this->dispatch('toast', type: 'error', message: 'Kriteria Tindakan Medis butuh kode ICD-9-CM (menentukan kandidat RS).');
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
            'jalur' => 'ranap',
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
            'kriteria' => [
                'terapi' => $this->formRujukan['kriteriaPilih'] === 'terapi',
                'tindakanIcd9' => $this->formRujukan['kriteriaPilih'] === 'tindakan' ? trim($this->formRujukan['kriteriaIcd9']) : '',
                'upayaDiagnosis' => $this->formRujukan['kriteriaPilih'] === 'upaya',
            ],
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
        if (trim($this->formRujukan['specialityCode']) === '') {
            $this->dispatch('toast', type: 'error', message: 'Isi kode layanan clinical-speciality dulu (mis. LY133 Syaraf - Stroke dan Cerebro Vaskuler).');
            return;
        }

        $respon = $this->rujukanBundleApproval([
            'identifierTask' => (string) Str::uuid(),
            'identifierCarePlan' => (string) Str::uuid(),
            'encounterId' => $this->encounterUuid(),
            'patientUuid' => $this->patientUuid(),
            'patientName' => (string) ($this->dataDaftarRi['regName'] ?? ''),
            'practitionerUuid' => $this->dokterUuid(),
            'practitionerName' => $this->dokterNama(),
            'orgTujuanId' => $kandidat['orgId'],
            'orgTujuanNama' => $kandidat['nama'],
            'jalur' => 'ranap',
            'deskripsi' => trim($this->formRujukan['deskripsi']) !== '' ? trim($this->formRujukan['deskripsi']) : 'Rujukan rawat inap — ' . $this->formRujukan['kodeDiagnosa'] . ' ' . $this->formRujukan['diagnosaDesc'],
            'specialityCode' => trim($this->formRujukan['specialityCode']),
            'specialityDisplay' => trim($this->formRujukan['specialityDisplay']) !== '' ? trim($this->formRujukan['specialityDisplay']) : trim($this->formRujukan['specialityCode']),
        ]);
        if ($respon['code'] < 200 || $respon['code'] >= 300) {
            $this->dispatch('toast', type: 'error', message: 'Kirim tugas rujukan gagal [' . $respon['code'] . '] ' . $this->ringkasError($respon['body']));
            return;
        }

        $this->formRujukan['carePlanId'] = $this->rujukanIdDariBundleResponse($respon['body'], 'CarePlan');
        $this->formRujukan['taskApprovalId'] = $this->rujukanIdDariBundleResponse($respon['body'], 'Task');
        $this->simpanDraft('Kirim tugas rujukan ranap → ' . $kandidat['nama']);
        $this->dispatch('toast', type: 'success', message: 'Tugas rujukan terkirim ke ' . $kandidat['nama'] . ' — lanjut Kirim Rujukan (staging boleh tanpa menunggu approval).');
    }

    /* ═══════════════════════════════════════
     | LANGKAH 3 — KIRIM RUJUKAN (ServiceRequest)
    ═══════════════════════════════════════ */
    public function kirimRujukan(): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form read-only.');
            return;
        }
        $kandidat = $this->formRujukan['kandidatList'][$this->formRujukan['kandidatIdx'] ?? -1] ?? null;
        if (!$kandidat || empty($this->formRujukan['carePlanId'])) {
            $this->dispatch('toast', type: 'error', message: 'Kirim Tugas Rujukan dulu (butuh CarePlan sebagai basedOn).');
            return;
        }

        $respon = $this->rujukanServiceRequest([
            'identifier' => (string) Str::uuid(),
            'carePlanId' => $this->formRujukan['carePlanId'],
            'jalur' => 'ranap',
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
                    <svg class="w-5 h-5 text-indigo-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 15L12 18.75 15.75 15m-7.5-6L12 5.25 15.75 9" />
                    </svg>
                    <h3 class="text-base font-semibold text-ink dark:text-gray-200">
                        Rujukan Berbasis Kompetensi — Ranap RS Lain (SATUSEHAT FHIR)
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
    <x-modal name="rujukan-kompetensi-ri-{{ $riHdrNo }}" size="full" height="full" focusable>
        <div class="flex flex-col min-h-[calc(100vh-8rem)]">

            {{-- HEADER --}}
            <div class="px-6 py-5 border-b border-hairline dark:border-gray-700">
                <div class="flex items-start justify-between gap-4">
                    <div class="flex items-center gap-3">
                        <div class="flex items-center justify-center w-10 h-10 rounded-xl bg-indigo-500/10">
                            <svg class="w-6 h-6 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 15L12 18.75 15.75 15m-7.5-6L12 5.25 15.75 9" />
                            </svg>
                        </div>
                        <div>
                            <h2 class="text-2xl font-semibold text-ink dark:text-gray-100">Rujukan Berbasis Kompetensi</h2>
                            <p class="mt-0.5 text-base text-muted dark:text-gray-400">
                                Rawat Inap → ranap RS lain · dikirim langsung ke SATUSEHAT (FHIR)
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
            <p class="font-semibold text-green-800 dark:text-green-200">Rujukan rawat inap sudah terkirim</p>
            <table class="text-gray-700 dark:text-gray-200">
                <tr><td class="pr-3">No Rujukan SATUSEHAT</td><td class="font-mono font-semibold">{{ $formRujukan['hasil']['noRujukanSatuSehat'] }}</td></tr>
                <tr><td class="pr-3">ServiceRequest</td><td class="font-mono">{{ $formRujukan['hasil']['serviceRequestId'] ?? '-' }}</td></tr>
                <tr><td class="pr-3">Tujuan</td><td>{{ $formRujukan['hasil']['tujuanNama'] ?? '-' }}</td></tr>
                <tr><td class="pr-3">Dikirim</td><td>{{ $formRujukan['hasil']['dikirimPada'] ?? '-' }} oleh {{ $formRujukan['hasil']['dikirimOleh'] ?? '-' }}</td></tr>
            </table>
        </div>
    @else

        {{-- LANGKAH 1 — DIAGNOSA, KRITERIA, WILAYAH → CARI KANDIDAT --}}
        <div class="p-3 space-y-3 bg-canvas border border-hairline rounded-lg dark:bg-gray-800 dark:border-gray-700">
            <p class="text-sm font-semibold text-gray-700 dark:text-gray-200">1. Diagnosa, Kriteria & Kandidat</p>

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
                    target="rujukanKompetensiDiagnosaRI" :disabled="$isFormLocked"
                    wire:key="lov-diagnosa-rujukan-kompetensi-ri-{{ $riHdrNo }}" />
            </div>

            <div>
                <x-input-label value="Kode Diagnosa (ICD-10 rinci)" class="mb-1" />
                <x-text-input wire:model.live="formRujukan.kodeDiagnosa" :disabled="true" class="w-full" />
            </div>

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

            <div class="grid grid-cols-1 gap-3">
                <div>
                    <x-input-label value="Kode Propinsi (tanpa titik)" class="mb-1" />
                    <x-text-input wire:model.blur="formRujukan.kodePropinsi" :disabled="$isFormLocked" class="w-full" />
                </div>
                <div>
                    <x-input-label value="Nama Propinsi" class="mb-1" />
                    <x-text-input wire:model.blur="formRujukan.namaPropinsi" :disabled="$isFormLocked" class="w-full" />
                </div>
                <div>
                    <x-input-label value="Kode Kabupaten/Kota (tanpa titik)" class="mb-1" />
                    <x-text-input wire:model.blur="formRujukan.kodeKabupaten" :disabled="$isFormLocked" class="w-full" />
                </div>
                <div>
                    <x-input-label value="Nama Kabupaten/Kota" class="mb-1" />
                    <x-text-input wire:model.blur="formRujukan.namaKabupaten" :disabled="$isFormLocked" class="w-full" />
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
                                <th class="px-3 py-2 text-center border-b">Bed</th>
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
                                    <td class="px-3 py-2 text-center tabular-nums">{{ ($kandidat['bed'] ?? '') !== '' ? $kandidat['bed'] : '-' }}</td>
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
            <p class="text-sm font-semibold text-gray-700 dark:text-gray-200">2. Kirim Tugas Rujukan & Rujukan</p>

            <div class="grid grid-cols-1 gap-3">
                <div>
                    <x-input-label value="Kode Layanan (clinical-speciality)" class="mb-1" />
                    <x-text-input wire:model.blur="formRujukan.specialityCode" placeholder="mis. LY133"
                        :disabled="$isFormLocked" class="w-full" />
                </div>
                <div>
                    <x-input-label value="Nama Layanan" class="mb-1" />
                    <x-text-input wire:model.blur="formRujukan.specialityDisplay" placeholder="mis. Syaraf - Stroke dan Cerebro Vaskuler"
                        :disabled="$isFormLocked" class="w-full" />
                </div>
                <div>
                    <x-input-label value="Deskripsi Rencana Rujukan" class="mb-1" />
                    <x-text-input wire:model.blur="formRujukan.deskripsi" placeholder="Alasan & kebutuhan penanganan di RS tujuan"
                        :disabled="$isFormLocked" class="w-full" />
                </div>
            </div>

            @if (!empty($formRujukan['taskApprovalId']))
                <p class="text-sm text-green-700 dark:text-green-300">✓ Tugas rujukan terkirim (Task {{ $formRujukan['taskApprovalId'] }}, CarePlan {{ $formRujukan['carePlanId'] }})</p>
            @endif

            @if (!$isFormLocked)
                <div class="flex flex-col items-start gap-2">
                    <x-secondary-button type="button" wire:click="kirimTugasRujukan" wire:loading.attr="disabled" wire:target="kirimTugasRujukan">
                        <span wire:loading.remove wire:target="kirimTugasRujukan">📨 Kirim Tugas Rujukan (Approval)</span>
                        <span wire:loading wire:target="kirimTugasRujukan" class="inline-flex items-center gap-1"><x-loading /> Mengirim tugas...</span>
                    </x-secondary-button>
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
