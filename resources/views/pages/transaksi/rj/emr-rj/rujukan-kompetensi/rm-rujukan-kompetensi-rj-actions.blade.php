<?php

use Livewire\Component;
use Livewire\Attributes\On;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Rj\EmrRJTrait;
use App\Http\Traits\BPJS\SisruteTrait;

new class extends Component {
    use EmrRJTrait;

    public bool $isFormLocked = false;
    public ?int $rjNo = null;

    // Referensi kunjungan — TIDAK di-bind ke form
    public array $dataDaftarPoliRJ = [];

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
        if (empty($this->rjNo)) {
            return;
        }

        $dataDaftarPoliRJ = $this->findDataRJ($this->rjNo);
        if (empty($dataDaftarPoliRJ)) {
            return;
        }
        $this->dataDaftarPoliRJ = $dataDaftarPoliRJ;

        // Restore state tersimpan; merge di atas default supaya key baru tetap ada
        $tersimpan = $dataDaftarPoliRJ['rujukanKompetensi'] ?? [];
        if (!empty($tersimpan) && is_array($tersimpan)) {
            $this->formRujukan = array_replace($this->defaultFormRujukan(), $tersimpan);
        } else {
            $this->prefillDariKunjungan();
        }

        if ($this->checkEmrRJStatus($this->rjNo)) {
            $this->isFormLocked = true;
        }
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
        $diagnosisPertama = collect($this->dataDaftarPoliRJ['diagnosis'] ?? [])->first();
        $this->formRujukan['kodeDiagnosa'] = $diagnosisPertama['icdX'] ?? ($diagnosisPertama['diagId'] ?? '');
        $this->formRujukan['diagnosaDesc'] = $diagnosisPertama['diagDesc'] ?? '';
        $this->formRujukan['kodeSpesialis'] = $this->dataDaftarPoliRJ['kdpolibpjs'] ?? '';
    }

    /* ═══════════════════════════════════════
     | PRASYARAT — kotak merah "Data belum siap"
    ═══════════════════════════════════════ */
    public function prasyaratKurang(): array
    {
        if (empty($this->rjNo)) {
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
        if (empty($this->dataDaftarPoliRJ['diagnosis'])) {
            $kurang[] = 'Diagnosa EMR belum diisi';
        }
        return $kurang;
    }

    private function nomorSep(): string
    {
        return (string) (DB::table('rsview_rjkasir')->where('rj_no', $this->rjNo)->value('vno_sep') ?? '');
    }

    private function encounterUuid(): string
    {
        return (string) ($this->dataDaftarPoliRJ['satusehat']['encounterId'] ?? '');
    }

    private function patientUuid(): string
    {
        $regNo = $this->dataDaftarPoliRJ['regNo'] ?? ($this->dataDaftarPoliRJ['reg_no'] ?? '');
        if ($regNo === '') {
            return '';
        }
        return (string) (DB::table('rsmst_pasiens')->where('reg_no', $regNo)->value('patient_uuid') ?? '');
    }

    private function dokterUuid(): string
    {
        $drId = $this->dataDaftarPoliRJ['drId'] ?? ($this->dataDaftarPoliRJ['dr_id'] ?? '');
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
        $diagnosa = $this->dataDaftarPoliRJ['diagnosis'][$index] ?? null;
        if (!$diagnosa) {
            return;
        }
        $this->setDiagnosaRujukan($diagnosa['icdX'] ?? ($diagnosa['diagId'] ?? ''), $diagnosa['diagDesc'] ?? '');
    }

    // LOV diagnosa (blockHeader default) — pencarian bebas, kode kategori 3-karakter
    // otomatis terblokir sesuai aturan SATUSEHAT
    #[On('lov.selected.rujukanKompetensiDiagnosa')]
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
        // Diagnosa berubah → kriteria & kandidat lama tidak berlaku (linkId dinamis per ICD-10)
        $this->formRujukan['kriteriaList'] = [];
        $this->formRujukan['kriteriaSumber'] = '';
        $this->formRujukan['kriteriaPilih'] = '';
        $this->formRujukan['kriteriaIcd9'] = '';
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

        $this->parseJejaringWilayah($data);
        $this->simpanDraft();
        $this->infoKriteria = '✓ Kriteria dimuat dari server (' . count($this->formRujukan['kriteriaList']) . ' item). Pilih TEPAT SATU.';
    }

    // Opsi wilayah datang dari response; simpan versi ramping saja (jangan bengkakkan CLOB)
    private function parseJejaringWilayah(array $data): void
    {
        $grup = $data['JejaringWilayah'] ?? ($data['jejaringWilayah'] ?? []);
        $items = collect(is_array($grup) ? $grup : [])->flatMap(fn($g) => $g['item'] ?? [])->values();

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
                    'kodeFaskesSatuSehat' => (string) ($faskes['kodeFaskesSatuSehat'] ?? ($faskes['kemkes-code'] ?? '')),
                    'nama' => (string) ($faskes['nmkc'] ?? ($faskes['nama'] ?? ($faskes['display'] ?? ''))),
                    'kelas' => (string) ($faskes['kelas'] ?? ($faskes['strata'] ?? '')),
                    'distance' => (string) ($faskes['distance'] ?? ''),
                    'persentase' => (string) ($faskes['persentase'] ?? ''),
                    'jadwal' => (string) ($faskes['jadwal'] ?? ''),
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
    public function pilihKandidat(int $index): void
    {
        $kandidat = $this->formRujukan['kandidatList'][$index] ?? null;
        if (!$kandidat) {
            return;
        }
        // Kandidat tanpa kode BPJS (string "null"/kosong) tidak sah untuk rujukan BPJS
        if ($kandidat['kdppk'] === '' || strtolower($kandidat['kdppk']) === 'null') {
            $this->dispatch('toast', type: 'error', message: "\"{$kandidat['nama']}\" tidak punya kode PPK BPJS — tidak bisa jadi tujuan rujukan BPJS.");
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
        $itemKriteria = $this->bangunItemKriteria();
        $tglRencana = $this->tglRencanaCarbon();
        if (!$itemKriteria || !$tglRencana) {
            $this->dispatch('toast', type: 'error', message: 'Kriteria/tanggal rencana belum lengkap.');
            return;
        }

        $poliRujukan = trim($this->formRujukan['poliRujukan']) !== '' ? trim($this->formRujukan['poliRujukan']) : (string) ($this->formRujukan['kodeSpesialis'] ?? '');

        $tRujukan = [
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

        $respon = SisruteTrait::sisrute_insert_rujukan($tRujukan)->getOriginalContent();
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
            'tglRujukan' => $tRujukan['tglRujukan'],
            'tujuanNama' => $kandidat['nama'],
            'tujuanPpk' => $kandidat['kdppk'],
            'tujuanSatuSehat' => $kandidat['kodeFaskesSatuSehat'],
            'dikirimOleh' => $tRujukan['user'],
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
        if (empty($this->rjNo)) {
            return;
        }
        try {
            DB::transaction(function () use ($catatanAudit) {
                $this->lockRJRow($this->rjNo);
                $data = $this->findDataRJ($this->rjNo) ?? [];
                if (empty($data)) {
                    return;
                }
                $data['rujukanKompetensi'] = $this->formRujukan;
                $this->updateJsonRJ($this->rjNo, $data);
                $this->dataDaftarPoliRJ = $data;
                if ($catatanAudit) {
                    $this->appendAdminLogRJ((int) $this->rjNo, $catatanAudit, 'MR');
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

<div class="w-full p-4 space-y-4 border border-indigo-200 rounded-2xl bg-indigo-50/50 dark:bg-gray-900 dark:border-indigo-900">

    {{-- HEADER --}}
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-2">
            <svg class="w-5 h-5 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round"
                    d="M8.25 15L12 18.75 15.75 15m-7.5-6L12 5.25 15.75 9" />
            </svg>
            <h3 class="font-semibold text-gray-800 dark:text-gray-100">Rujukan Berbasis Kompetensi (SISRUTE)</h3>
        </div>
        @if (!empty($formRujukan['hasil']['noRujukanSatuSehat']))
            <span
                class="px-2 py-0.5 text-xs font-semibold text-green-800 bg-green-100 rounded-full dark:bg-green-900 dark:text-green-200">
                ✓ Terkirim
            </span>
        @endif
    </div>

    {{-- PRASYARAT --}}
    @php $prasyaratKurang = $this->prasyaratKurang(); @endphp
    @if (!empty($prasyaratKurang) && empty($formRujukan['hasil']['noRujukanSatuSehat']))
        <div class="p-3 text-sm text-red-800 border border-red-200 rounded-lg bg-red-50 dark:bg-red-950 dark:text-red-200 dark:border-red-900">
            <p class="font-semibold">Data belum siap — lengkapi dulu:</p>
            <ul class="mt-1 ml-4 list-disc">
                @foreach ($prasyaratKurang as $itemKurang)
                    <li>{{ $itemKurang }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- HASIL (sudah terkirim) --}}
    @if (!empty($formRujukan['hasil']['noRujukanSatuSehat']))
        <div class="p-3 space-y-1 text-sm border border-green-200 rounded-lg bg-green-50 dark:bg-green-950 dark:border-green-900">
            <p class="font-semibold text-green-800 dark:text-green-200">Rujukan sudah terkirim</p>
            <table class="text-gray-700 dark:text-gray-200">
                <tr><td class="pr-3">No Rujukan BPJS</td><td class="font-mono font-semibold">{{ $formRujukan['hasil']['noRujukan'] ?: '-' }}</td></tr>
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

        {{-- LANGKAH 1 — DIAGNOSA & KRITERIA --}}
        <div class="p-3 space-y-3 bg-canvas border border-hairline rounded-lg dark:bg-gray-800 dark:border-gray-700">
            <p class="text-sm font-semibold text-gray-700 dark:text-gray-200">1. Diagnosa & Kriteria Rujukan</p>

            {{-- Pilih diagnosa dari EMR --}}
            <div class="flex flex-wrap gap-2">
                @forelse ($dataDaftarPoliRJ['diagnosis'] ?? [] as $indexDiagnosa => $diagnosa)
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
                    target="rujukanKompetensiDiagnosa" :disabled="$isFormLocked"
                    wire:key="lov-diagnosa-rujukan-kompetensi-{{ $rjNo }}" />
            </div>

            <div class="grid grid-cols-1 gap-3">
                <div>
                    <x-input-label value="Kode Diagnosa (ICD-10 rinci)" class="mb-1" />
                    <x-text-input wire:model.live="formRujukan.kodeDiagnosa" :disabled="true" class="w-full" />
                    <p class="mt-1 text-xs text-muted-soft">Wajib ber-titik (A02.0) — kode induk ditolak SATUSEHAT.</p>
                </div>
                <div>
                    <x-input-label value="Kode Spesialis (poli BPJS)" class="mb-1" />
                    <x-text-input wire:model.blur="formRujukan.kodeSpesialis" :disabled="$isFormLocked" class="w-full" />
                </div>
                <div>
                    <x-input-label value="Tgl Rencana Kunjungan" class="mb-1" />
                    <x-text-input wire:model.blur="formRujukan.tglRencanaKunjungan" placeholder="dd/mm/yyyy"
                        :disabled="$isFormLocked" class="w-full" />
                    <p class="mt-1 text-xs text-muted-soft">Boleh hari ini.</p>
                </div>
            </div>

            <div class="flex items-center gap-2">
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
                    @foreach ($formRujukan['kriteriaList'] as $kriteria)
                        <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-200">
                            <input type="radio" name="kriteriaPilih-{{ $rjNo }}" value="{{ $kriteria['linkId'] }}"
                                wire:model.live="formRujukan.kriteriaPilih" @disabled($isFormLocked)
                                class="text-indigo-600 border-hairline focus:ring-indigo-500">
                            <span>{{ $kriteria['text'] }}
                                <span class="text-xs text-muted-soft">(linkId {{ $kriteria['linkId'] }})</span>
                            </span>
                        </label>
                    @endforeach
                    @php
                        $kriteriaTerpilih = collect($formRujukan['kriteriaList'])->firstWhere('linkId', $formRujukan['kriteriaPilih']);
                        $butuhIcd9 = $kriteriaTerpilih && (strtolower($kriteriaTerpilih['type']) === 'text' || str_contains(strtolower($kriteriaTerpilih['text']), 'tindakan'));
                    @endphp
                    @if ($butuhIcd9)
                        <div class="max-w-xs">
                            <x-input-label value="Kode Tindakan ICD-9-CM" class="mb-1" />
                            <x-text-input wire:model.blur="formRujukan.kriteriaIcd9" placeholder="mis. 01.24"
                                :disabled="$isFormLocked" class="w-full" />
                            <p class="mt-1 text-xs text-muted-soft">Harus valid & sesuai diagnosa — menentukan kandidat RS.</p>
                        </div>
                    @endif
                </div>

                {{-- Wilayah rujukan (auto dari server, bisa diganti) --}}
                <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
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
            <div class="flex items-center justify-between">
                <p class="text-sm font-semibold text-gray-700 dark:text-gray-200">2. Kandidat Faskes Tujuan</p>
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
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left border-b border-hairline text-muted-soft dark:border-gray-600">
                                <th class="py-1 pr-2">Faskes</th>
                                <th class="py-1 pr-2">PPK BPJS</th>
                                <th class="py-1 pr-2">Kelas</th>
                                <th class="py-1 pr-2">Jarak</th>
                                <th class="py-1 pr-2">Jadwal</th>
                                <th class="py-1"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($formRujukan['kandidatList'] as $indexKandidat => $kandidat)
                                @php $tanpaPpk = $kandidat['kdppk'] === '' || strtolower($kandidat['kdppk']) === 'null'; @endphp
                                <tr class="border-b border-hairline-soft dark:border-gray-700 {{ $formRujukan['kandidatIdx'] === $indexKandidat ? 'bg-indigo-50 dark:bg-indigo-950' : '' }}">
                                    <td class="py-1 pr-2">{{ $kandidat['nama'] }}</td>
                                    <td class="py-1 pr-2 font-mono">{{ $tanpaPpk ? '— non-BPJS' : $kandidat['kdppk'] }}</td>
                                    <td class="py-1 pr-2">{{ $kandidat['kelas'] }}</td>
                                    <td class="py-1 pr-2">{{ $kandidat['distance'] }}</td>
                                    <td class="py-1 pr-2 text-xs">{{ \Illuminate\Support\Str::limit($kandidat['jadwal'], 40) }}</td>
                                    <td class="py-1 text-right">
                                        @if ($formRujukan['kandidatIdx'] === $indexKandidat)
                                            <span class="text-xs font-semibold text-indigo-700 dark:text-indigo-300">✓ Dipilih</span>
                                        @else
                                            <button type="button" wire:click="pilihKandidat({{ $indexKandidat }})"
                                                @disabled($isFormLocked || $tanpaPpk)
                                                class="px-2 py-0.5 text-xs rounded border {{ $tanpaPpk ? 'text-muted-soft border-hairline cursor-not-allowed' : 'text-indigo-700 border-indigo-300 hover:bg-indigo-50 dark:text-indigo-300 dark:border-indigo-700' }}">
                                                Pilih
                                            </button>
                                        @endif
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
            <p class="text-sm font-semibold text-gray-700 dark:text-gray-200">3. Kirim Rujukan</p>
            <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                <div>
                    <x-input-label value="Poli Rujukan (kode BPJS 3 huruf)" class="mb-1" />
                    <x-text-input wire:model.blur="formRujukan.poliRujukan" placeholder="kosong = ikut kode spesialis"
                        :disabled="$isFormLocked" class="w-full" />
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
            @if (!$isFormLocked)
                <div class="flex justify-end">
                    <x-success-button type="button" wire:click="kirimRujukan" wire:loading.attr="disabled"
                        wire:target="kirimRujukan">
                        <span wire:loading.remove wire:target="kirimRujukan" class="inline-flex items-center gap-1">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.27 20.876L5.999 12zm0 0h7.5" />
                            </svg>
                            Kirim Rujukan ke BPJS & SATUSEHAT
                        </span>
                        <span wire:loading wire:target="kirimRujukan" class="inline-flex items-center gap-1">
                            <x-loading /> Mengirim rujukan...
                        </span>
                    </x-success-button>
                </div>
            @endif
        </div>
    @endif
</div>
