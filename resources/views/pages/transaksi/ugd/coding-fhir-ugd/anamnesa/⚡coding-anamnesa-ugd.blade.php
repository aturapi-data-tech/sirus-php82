<?php
// resources/views/pages/transaksi/ugd/coding-fhir-ugd/anamnesa/coding-anamnesa-ugd.blade.php
//
// Coding SATUSEHAT — kelompok S (Anamnesa). Teks klinis dari EMR hanya DIBACA; yang diisi di
// sini kodenya:
// (port Coding SATUSEHAT RJ; nomor bab = Playbook IGD)
//   Keluhan Utama (4a)          satu kode
//   Riwayat Penyakit Sekarang   daftar + tanggal mulai — LOV, isi tanggal, Tambah  ┐ bab 4b
//   Riwayat Penyakit Dahulu     daftar + usia saat menderita — LOV, isi usia, Tambah┘ (App\Support\RiwayatPenyakitPribadi)
//   Riwayat Penyakit Keluarga   daftar hubungan + penyakit — 4d (App\Support\RiwayatPenyakitKeluarga)
//   Riwayat Pengobatan          daftar obat (KFA dari master) + status + aturan pakai — 4e (App\Support\RiwayatPengobatan);
//                               konteks teksnya = Rekonsiliasi Obat EMR UGD (tak ada isian teks tersendiri)
//   Alergi (4c)                 daftar alergen bila EMR "Ada alergi": jenis Obat (KFA) / Makanan /
//                               Lingkungan (SNOMED) + keparahan → Tambah (App\Support\AlergiAlergen)
//
// Markup tiap bagian ada di anamnesa/tabs/*.blade.php (pola emr-ugd/anamnesa/tabs).
//
// Simpan (event 'save-coding-fhir-ugd' dari host) HANYA menulis key kode di atas data segar
// (row lock) — teks klinis tak tersentuh. Form anamnesa EMR sebaliknya mempertahankan key kode
// dari DB saat simpan (rm-anamnesa-ugd-actions::pertahankanKodeCoding).

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Ugd\EmrUGDTrait;
use App\Http\Traits\Master\MasterPasien\MasterPasienTrait;
use App\Http\Traits\Concerns\WithRenderVersioningTrait;
use App\Http\Traits\Concerns\WithValidationToastTrait;
use App\Support\RiwayatPenyakitPribadi;
use App\Support\RiwayatPenyakitKeluarga;
use App\Support\RiwayatPengobatan;
use App\Support\AlergiAlergen;
use App\Support\Terminologi\ObatKfa;
use App\Support\Terminologi\StatusPsikologisMap;
use App\Support\Terminologi\AlergiSnomed;
use App\Support\Terminologi\KedatanganIgd;

new class extends Component {
    use EmrUGDTrait, MasterPasienTrait, WithRenderVersioningTrait, WithValidationToastTrait;

    public ?string $rjNo = null;
    public string $regNoPasien = '';

    /** Irisan yang dikoding — teks dari EMR (hanya tampil) + kodenya (diisi di sini). */
    public array $koding = [];

    /**
     * Daftar kode yang dipakai ulang lintas kunjungan lewat master pasien (pola alergi):
     * key koding => [node anamnesa, key daftar di node, key teks master, key daftar master].
     * Kode menempel pada teksnya: dipakai/diperbarui HANYA bila teks kunjungan = teks master.
     */
    private const DAFTAR_MASTER = [
        'dahulu'     => ['riwayatPenyakitDahulu',   'snomedList', 'riwayatPenyakitDahulu',   'riwayatPenyakitDahuluSnomedList'],
        'keluarga'   => ['riwayatPenyakitKeluarga', 'snomedList', 'riwayatPenyakitKeluarga', 'riwayatPenyakitKeluargaSnomedList'],
        // Riwayat pengobatan SENGAJA tidak: obat berbeda antar poli/kunjungan — diisi manual tiap
        // kunjungan (keputusan user 2026-10-28).
    ];

    /** Kedatangan (Playbook IGD 3a–3c) dari Pengkajian Perawatan + kode tetapnya — baca saja. */
    public array $kedatanganBaris = [];

    /** Status psikologis EMR yang dicentang + kode SNOMED tetapnya — baca saja (bab 8a). */
    public array $statusPsikologisBaris = [];

    /** Isian Tambah riwayat penyakit per jenis — kode dari LOV + tanggal mulai / usia. */
    public array $formRiwayat = [];

    /** Isian Tambah riwayat penyakit keluarga — hubungan + kode dari LOV + keterangan. */
    public array $formKeluarga = [];

    /** Isian Tambah riwayat pengobatan — obat dari LOV master (KFA) + status + aturan pakai. */
    public array $formObat = [];

    /** Isian Tambah alergen — jenis + kode (KFA/SNOMED dari LOV) + keparahan. */
    public array $formAlergi = [];

    /** Sidik isian kode terakhir yang tersimpan — simpan senyap (pindah tab) dilewati bila tak berubah. */
    public string $sidikKodingTersimpan = '';

    public array $renderVersions = [];
    protected array $renderAreas = ['lov-coding-riwayat-sekarang', 'lov-coding-riwayat-dahulu', 'lov-coding-riwayat-keluarga', 'lov-coding-riwayat-obat', 'lov-coding-alergi'];

    public function mount(?string $rjNo = null): void
    {
        $this->registerAreas(['lov-coding-riwayat-sekarang', 'lov-coding-riwayat-dahulu', 'lov-coding-riwayat-keluarga', 'lov-coding-riwayat-obat', 'lov-coding-alergi']);
        $this->resetFormRiwayat();
        $this->resetFormKeluarga();
        $this->resetFormObat();
        $this->resetFormAlergi();
        $this->rjNo = $rjNo;
        if (!empty($rjNo)) {
            $this->muatDariDokumen($this->findDataUGD($rjNo) ?? []);
        }
        $this->sidikKodingTersimpan = $this->sidikKoding();
    }

    private function sidikKoding(): string
    {
        return md5(json_encode($this->koding));
    }

    private function resetFormRiwayat(?string $jenis = null): void
    {
        $kosong = [
            RiwayatPenyakitPribadi::JENIS_SEKARANG => ['snomedCode' => '', 'snomedDisplayEn' => '', 'snomedDisplayId' => '', 'tglMulai' => ''],
            RiwayatPenyakitPribadi::JENIS_DAHULU => ['snomedCode' => '', 'snomedDisplayEn' => '', 'snomedDisplayId' => '', 'usiaTahun' => ''],
        ];
        if ($jenis === null) {
            $this->formRiwayat = $kosong;
            return;
        }
        $this->formRiwayat[$jenis] = $kosong[$jenis];
    }

    private function resetFormKeluarga(): void
    {
        $this->formKeluarga = ['hubungan' => '', 'snomedCode' => '', 'snomedDisplayEn' => '', 'snomedDisplayId' => '', 'keterangan' => ''];
    }

    private function resetFormObat(): void
    {
        $this->formObat = ['productId' => '', 'namaObat' => '', 'kfaCode' => '', 'kfaDisplay' => '', 'status' => 'active', 'aturanPakai' => ''];
    }

    private function resetFormAlergi(string $jenis = 'food'): void
    {
        $this->formAlergi = ['jenis' => $jenis, 'kode' => '', 'display' => '', 'nama' => '', 'productId' => '', 'keparahan' => 'low'];
    }

    /**
     * Daftar alergen awal: yang tersimpan; bila kosong → kode tunggal LAMA (kunjungan yang
     * dikode sebelum daftar ini ada); bila tetap kosong → daftar di master pasien, HANYA bila teks
     * alergi master = teks kunjungan ini (kode menempel pada teksnya).
     */
    private function alergenAwal(array $alergi): array
    {
        $alergenList = array_values((array) ($alergi['alergenList'] ?? []));
        if ($alergenList !== [] || ($alergi['adaAlergi'] ?? '') !== 'Ya') {
            return $alergenList;
        }

        if (($barisLama = AlergiAlergen::dariKodeLama($alergi)) !== null) {
            return [$barisLama];
        }

        $pasien = $this->findDataMasterPasien($this->regNoPasien)['pasien'] ?? [];
        $teks = trim((string) ($alergi['alergi'] ?? ''));

        return $teks !== '' && trim((string) ($pasien['alergi'] ?? '')) === $teks
            ? array_values((array) ($pasien['alergiAlergenList'] ?? []))
            : [];
    }

    private function muatDariDokumen(array $dataUGD): void
    {
        $anamnesa = $dataUGD['anamnesa'] ?? [];
        $alergi = AlergiSnomed::normalisasi($anamnesa['alergi'] ?? []);

        $this->regNoPasien = (string) ($dataUGD['regNo'] ?? '');
        $this->kedatanganBaris = $this->barisKedatangan($anamnesa['pengkajianPerawatan'] ?? [], KedatanganIgd::tingkatTriase($dataUGD));
        $catatanPsikologis = trim((string) ($anamnesa['statusPsikologis']['sebutstatusPsikologis'] ?? ''));
        $this->statusPsikologisBaris = array_values(array_map(fn($peta) => [
            'isi' => $peta['label'] . ($catatanPsikologis !== '' ? ' — ' . $catatanPsikologis : ''),
            'kode' => 'LOINC 8693-4 Mental status · nilai SNOMED ' . $peta['code'] . ' ' . $peta['display'] . ' (otomatis dari EMR)',
        ], StatusPsikologisMap::aktif($anamnesa['statusPsikologis'] ?? [])));
        $this->koding = [
            'keluhanUtama' => [
                'teks' => (string) ($anamnesa['keluhanUtama']['keluhanUtama'] ?? ''),
                'snomedCode' => (string) ($anamnesa['keluhanUtama']['snomedCode'] ?? ''),
                'snomedDisplayEn' => (string) ($anamnesa['keluhanUtama']['snomedDisplayEn'] ?? ''),
                'snomedDisplayId' => (string) ($anamnesa['keluhanUtama']['snomedDisplayId'] ?? ''),
            ],
            RiwayatPenyakitPribadi::JENIS_SEKARANG => [
                'teks' => (string) ($anamnesa['riwayatPenyakitSekarangUmum']['riwayatPenyakitSekarangUmum'] ?? ''),
                'snomedList' => array_values((array) ($anamnesa['riwayatPenyakitSekarangUmum']['snomedList'] ?? [])),
            ],
            RiwayatPenyakitPribadi::JENIS_DAHULU => [
                'teks' => (string) ($anamnesa['riwayatPenyakitDahulu']['riwayatPenyakitDahulu'] ?? ''),
                'snomedList' => array_values((array) ($anamnesa['riwayatPenyakitDahulu']['snomedList'] ?? [])),
            ],
            'keluarga' => [
                'teks' => (string) ($anamnesa['riwayatPenyakitKeluarga']['riwayatPenyakitKeluarga'] ?? ''),
                'snomedList' => array_values((array) ($anamnesa['riwayatPenyakitKeluarga']['snomedList'] ?? [])),
            ],
            'pengobatan' => [
                'teks' => $this->teksRekonsiliasiObat((array) ($anamnesa['rekonsiliasiObat'] ?? [])),
                'obatList' => array_values((array) ($anamnesa['riwayatPengobatan']['obatList'] ?? [])),
            ],
            'alergi' => [
                'adaAlergi' => (string) ($alergi['adaAlergi'] ?? ''),
                'teks' => (string) ($alergi['alergi'] ?? ''),
                'alergenList' => $this->alergenAwal($alergi),
            ],
        ];

        $this->koding = $this->isiDaftarDariMaster($this->koding, $this->findDataMasterPasien($this->regNoPasien)['pasien'] ?? []);
    }

    /* ── Keluhan Utama: satu kode ── */
    #[On('lov.selected.codingKeluhanUtamaSnomed')]
    public function onKeluhanUtamaSelected(string $target, array $payload): void
    {
        $this->koding['keluhanUtama']['snomedCode'] = (string) ($payload['snomed_code'] ?? '');
        $this->koding['keluhanUtama']['snomedDisplayEn'] = (string) ($payload['display_en'] ?? '');
        $this->koding['keluhanUtama']['snomedDisplayId'] = (string) ($payload['display_id'] ?? '');
    }

    #[On('lov.cleared.codingKeluhanUtamaSnomed')]
    public function onKeluhanUtamaCleared(string $target): void
    {
        $this->koding['keluhanUtama'] = [...$this->koding['keluhanUtama'], 'snomedCode' => '', 'snomedDisplayEn' => '', 'snomedDisplayId' => ''];
    }

    /* ── Riwayat Penyakit Pribadi (4b): LOV → isi tanggal/usia → Tambah ── */
    #[On('lov.selected.codingRiwayatSekarangSnomed')]
    public function onRiwayatSekarangSelected(string $target, array $payload): void
    {
        $this->isiKodeFormRiwayat(RiwayatPenyakitPribadi::JENIS_SEKARANG, $payload);
    }

    #[On('lov.cleared.codingRiwayatSekarangSnomed')]
    public function onRiwayatSekarangCleared(string $target): void
    {
        $this->isiKodeFormRiwayat(RiwayatPenyakitPribadi::JENIS_SEKARANG, []);
    }

    #[On('lov.selected.codingRiwayatDahuluSnomed')]
    public function onRiwayatDahuluSelected(string $target, array $payload): void
    {
        $this->isiKodeFormRiwayat(RiwayatPenyakitPribadi::JENIS_DAHULU, $payload);
    }

    #[On('lov.cleared.codingRiwayatDahuluSnomed')]
    public function onRiwayatDahuluCleared(string $target): void
    {
        $this->isiKodeFormRiwayat(RiwayatPenyakitPribadi::JENIS_DAHULU, []);
    }

    private function isiKodeFormRiwayat(string $jenis, array $payload): void
    {
        $this->formRiwayat[$jenis]['snomedCode'] = (string) ($payload['snomed_code'] ?? '');
        $this->formRiwayat[$jenis]['snomedDisplayEn'] = (string) ($payload['display_en'] ?? '');
        $this->formRiwayat[$jenis]['snomedDisplayId'] = (string) ($payload['display_id'] ?? '');
    }

    public function tambahRiwayat(string $jenis): void
    {
        if (!array_key_exists($jenis, RiwayatPenyakitPribadi::NODE)) {
            return;
        }

        $sekarang = $jenis === RiwayatPenyakitPribadi::JENIS_SEKARANG;
        $kunciKeterangan = $sekarang ? "formRiwayat.{$jenis}.tglMulai" : "formRiwayat.{$jenis}.usiaTahun";
        $this->validateWithToast(
            [
                "formRiwayat.{$jenis}.snomedCode" => ['required'],
                $kunciKeterangan => $sekarang
                    ? ['required', 'date_format:d/m/Y', 'before_or_equal:today']
                    : ['required', 'integer', 'min:0', 'max:120'],
            ],
            [],
            [
                "formRiwayat.{$jenis}.snomedCode" => 'Kode SNOMED',
                "formRiwayat.{$jenis}.tglMulai" => 'Mulai Diderita',
                "formRiwayat.{$jenis}.usiaTahun" => 'Usia Saat Menderita',
            ],
        );

        $form = $this->formRiwayat[$jenis];
        $snomedList = $this->koding[$jenis]['snomedList'] ?? [];
        if (RiwayatPenyakitPribadi::sudahAda($snomedList, $form['snomedCode'])) {
            $this->dispatch('toast', type: 'info', message: 'Kode riwayat penyakit itu sudah ada di daftar.');
            return;
        }

        $snomedList[] = RiwayatPenyakitPribadi::barisBaru($jenis, $form['snomedCode'], $form['snomedDisplayEn'], $form['snomedDisplayId'],
            (string) ($sekarang ? $form['tglMulai'] : $form['usiaTahun']));
        $this->koding[$jenis]['snomedList'] = $snomedList;

        $this->resetFormRiwayat($jenis);
        $this->incrementVersion($sekarang ? 'lov-coding-riwayat-sekarang' : 'lov-coding-riwayat-dahulu');
    }

    public function hapusRiwayat(string $jenis, int $index): void
    {
        if (isset($this->koding[$jenis]['snomedList'][$index])) {
            unset($this->koding[$jenis]['snomedList'][$index]);
            $this->koding[$jenis]['snomedList'] = array_values($this->koding[$jenis]['snomedList']);
        }
    }

    /* ── Riwayat Penyakit Keluarga (4d): hubungan + LOV + keterangan → Tambah ── */
    #[On('lov.selected.codingRiwayatKeluargaSnomed')]
    public function onRiwayatKeluargaSelected(string $target, array $payload): void
    {
        $this->formKeluarga['snomedCode'] = (string) ($payload['snomed_code'] ?? '');
        $this->formKeluarga['snomedDisplayEn'] = (string) ($payload['display_en'] ?? '');
        $this->formKeluarga['snomedDisplayId'] = (string) ($payload['display_id'] ?? '');
    }

    #[On('lov.cleared.codingRiwayatKeluargaSnomed')]
    public function onRiwayatKeluargaCleared(string $target): void
    {
        $this->formKeluarga = [...$this->formKeluarga, 'snomedCode' => '', 'snomedDisplayEn' => '', 'snomedDisplayId' => ''];
    }

    public function tambahKeluarga(): void
    {
        $this->validateWithToast(
            [
                'formKeluarga.hubungan' => ['required', 'in:' . implode(',', array_keys(RiwayatPenyakitKeluarga::HUBUNGAN))],
                'formKeluarga.snomedCode' => ['required'],
                'formKeluarga.keterangan' => ['nullable', 'string', 'max:200'],
            ],
            [],
            ['formKeluarga.hubungan' => 'Hubungan Keluarga', 'formKeluarga.snomedCode' => 'Kode SNOMED', 'formKeluarga.keterangan' => 'Keterangan'],
        );

        $form = $this->formKeluarga;
        $snomedList = $this->koding['keluarga']['snomedList'] ?? [];
        if (RiwayatPenyakitKeluarga::sudahAda($snomedList, $form['hubungan'], $form['snomedCode'])) {
            $this->dispatch('toast', type: 'info', message: 'Penyakit itu untuk hubungan yang sama sudah ada di daftar.');
            return;
        }

        $snomedList[] = RiwayatPenyakitKeluarga::barisBaru($form['hubungan'], $form['snomedCode'], $form['snomedDisplayEn'], $form['snomedDisplayId'], $form['keterangan']);
        $this->koding['keluarga']['snomedList'] = $snomedList;

        $this->resetFormKeluarga();
        $this->incrementVersion('lov-coding-riwayat-keluarga');
    }

    public function hapusKeluarga(int $index): void
    {
        if (isset($this->koding['keluarga']['snomedList'][$index])) {
            unset($this->koding['keluarga']['snomedList'][$index]);
            $this->koding['keluarga']['snomedList'] = array_values($this->koding['keluarga']['snomedList']);
        }
    }

    /* ── Riwayat Pengobatan (4e): obat dari master (KFA) + status + aturan pakai → Tambah ── */
    #[On('lov.selected.codingRiwayatObat')]
    public function onRiwayatObatSelected(string $target, ?array $payload): void
    {
        $productId = trim((string) ($payload['product_id'] ?? ''));
        $this->formObat = [...$this->formObat, 'productId' => $productId, 'namaObat' => (string) ($payload['product_name'] ?? ''), 'kfaCode' => '', 'kfaDisplay' => ''];
        if ($productId === '') {
            return;
        }

        $kfa = RiwayatPengobatan::kfaDariMaster($productId);
        if ($kfa === null) {
            $this->dispatch('toast', type: 'error', message: 'Obat ' . $this->formObat['namaObat'] . ' belum punya kode KFA di master obat — lengkapi dulu di Master Obat.');
            return;
        }
        $this->formObat = [...$this->formObat, ...$kfa];
    }

    public function tambahObat(): void
    {
        $this->validateWithToast(
            [
                'formObat.productId' => ['required'],
                'formObat.kfaCode' => ['required'],
                'formObat.status' => ['required', 'in:' . implode(',', array_keys(RiwayatPengobatan::STATUS))],
                'formObat.aturanPakai' => ['nullable', 'string', 'max:200'],
            ],
            ['formObat.kfaCode.required' => 'Obat ini belum punya kode KFA di master obat.'],
            ['formObat.productId' => 'Obat', 'formObat.kfaCode' => 'Kode KFA', 'formObat.status' => 'Status Konsumsi', 'formObat.aturanPakai' => 'Aturan Pakai'],
        );

        $form = $this->formObat;
        $obatList = $this->koding['pengobatan']['obatList'] ?? [];
        if (RiwayatPengobatan::sudahAda($obatList, $form['productId'])) {
            $this->dispatch('toast', type: 'info', message: 'Obat itu sudah ada di daftar riwayat pengobatan.');
            return;
        }

        $obatList[] = RiwayatPengobatan::barisBaru($form['productId'], $form['namaObat'], $form['kfaCode'], $form['kfaDisplay'], $form['status'], $form['aturanPakai']);
        $this->koding['pengobatan']['obatList'] = $obatList;

        $this->resetFormObat();
        $this->incrementVersion('lov-coding-riwayat-obat');
    }

    public function hapusObat(int $index): void
    {
        if (isset($this->koding['pengobatan']['obatList'][$index])) {
            unset($this->koding['pengobatan']['obatList'][$index]);
            $this->koding['pengobatan']['obatList'] = array_values($this->koding['pengobatan']['obatList']);
        }
    }

    /* ── Alergi (4c): jenis + kode + keparahan → Tambah. Obat = KFA master; lainnya = SNOMED ── */
    public function updatedFormAlergiJenis(): void
    {
        // Ganti jenis = kode lama tak berlaku (sistemnya beda) — LOV yang tampil pun berganti.
        $this->resetFormAlergi((string) $this->formAlergi['jenis']);
        $this->incrementVersion('lov-coding-alergi');
    }

    #[On('lov.selected.codingAlergiSnomed')]
    public function onAlergiSnomedSelected(string $target, array $payload): void
    {
        $this->formAlergi = [...$this->formAlergi, 'kode' => (string) ($payload['snomed_code'] ?? ''),
            'display' => (string) ($payload['display_en'] ?? ''), 'nama' => (string) (($payload['display_id'] ?? '') ?: ($payload['display_en'] ?? '')), 'productId' => ''];
    }

    #[On('lov.cleared.codingAlergiSnomed')]
    public function onAlergiSnomedCleared(string $target): void
    {
        $this->formAlergi = [...$this->formAlergi, 'kode' => '', 'display' => '', 'nama' => ''];
    }

    #[On('lov.selected.codingAlergiObat')]
    public function onAlergiObatSelected(string $target, ?array $payload): void
    {
        $productId = trim((string) ($payload['product_id'] ?? ''));
        $namaObat = (string) ($payload['product_name'] ?? '');
        $this->formAlergi = [...$this->formAlergi, 'productId' => $productId, 'nama' => $namaObat, 'kode' => '', 'display' => ''];
        if ($productId === '') {
            return;
        }

        $kfa = ObatKfa::kfaProduk($productId);
        if ($kfa === null) {
            $this->dispatch('toast', type: 'error', message: 'Obat ' . $namaObat . ' belum punya kode KFA di master obat — lengkapi dulu di Master Obat.');
            return;
        }
        $this->formAlergi = [...$this->formAlergi, 'kode' => $kfa['kfaCode'], 'display' => $kfa['kfaDisplay'] ?: $namaObat];
    }

    public function tambahAlergi(): void
    {
        if (($this->koding['alergi']['adaAlergi'] ?? '') !== 'Ya') {
            return;
        }

        $obat = $this->formAlergi['jenis'] === 'medication';
        $this->validateWithToast(
            [
                'formAlergi.jenis' => ['required', 'in:' . implode(',', array_keys(AlergiAlergen::JENIS))],
                'formAlergi.kode' => ['required'],
                'formAlergi.keparahan' => ['required', 'in:' . implode(',', array_keys(AlergiAlergen::KEPARAHAN))],
            ],
            ['formAlergi.kode.required' => $obat ? 'Pilih obat yang sudah punya kode KFA di master obat.' : 'Pilih kode SNOMED alergen.'],
            ['formAlergi.jenis' => 'Jenis Alergi', 'formAlergi.kode' => $obat ? 'Obat (KFA)' : 'Alergen (SNOMED)', 'formAlergi.keparahan' => 'Tingkat Keparahan'],
        );

        $form = $this->formAlergi;
        $alergenList = $this->koding['alergi']['alergenList'] ?? [];
        if (AlergiAlergen::sudahAda($alergenList, $form['jenis'], $form['kode'])) {
            $this->dispatch('toast', type: 'info', message: 'Alergen itu sudah ada di daftar.');
            return;
        }

        $alergenList[] = AlergiAlergen::barisBaru($form['jenis'], $form['kode'], $form['display'], $form['nama'], $form['keparahan'], $form['productId']);
        $this->koding['alergi']['alergenList'] = $alergenList;

        $this->resetFormAlergi($form['jenis']);
        $this->incrementVersion('lov-coding-alergi');
    }

    public function hapusAlergi(int $index): void
    {
        if (isset($this->koding['alergi']['alergenList'][$index])) {
            unset($this->koding['alergi']['alergenList'][$index]);
            $this->koding['alergi']['alergenList'] = array_values($this->koding['alergi']['alergenList']);
        }
    }

    /**
     * rjNo null = dari "Tutup dan Simpan" dirty guard modal gabungan emr-coding-ugd (event tanpa parameter).
     * $senyap = simpan otomatis saat pindah tab: tanpa toast sukses, dilewati bila kode tak berubah.
     * Hasil dikabarkan lewat 'coding-fhir-ugd.tersimpan' (berhasil); sukses juga 'refresh-after-ugd.saved'
     * (= event dirty guard EMR UGD → tanda "belum disimpan" hilang).
     */
    #[On('save-coding-fhir-ugd')]
    public function save(?string $rjNo = null, bool $senyap = false): void
    {
        if (empty($this->rjNo) || ($rjNo !== null && (string) $this->rjNo !== $rjNo)) {
            return;
        }
        if ($senyap && $this->sidikKoding() === $this->sidikKodingTersimpan) {
            $this->dispatch('coding-fhir-ugd.tersimpan', berhasil: true);
            return;
        }
        if (!auth()->user()?->can('emr.codingFhir')) {
            $this->dispatch('toast', type: 'error', message: 'Anda tidak berhak menyimpan Coding SATUSEHAT.');
            $this->dispatch('coding-fhir-ugd.tersimpan', berhasil: false);
            return;
        }

        try {
            DB::transaction(function () {
                $this->lockUGDRow($this->rjNo);
                $data = $this->findDataUGD($this->rjNo) ?? [];
                if (empty($data)) {
                    throw new \RuntimeException('Data UGD tidak ditemukan, simpan dibatalkan.');
                }

                // HANYA key kode yang ditulis, di atas data segar — teks klinis tak tersentuh.
                $keluhanUtama = $this->koding['keluhanUtama'];
                foreach (['snomedCode', 'snomedDisplayEn', 'snomedDisplayId'] as $kunci) {
                    $data['anamnesa']['keluhanUtama'][$kunci] = $keluhanUtama[$kunci] ?? '';
                }

                // Tanda dariMaster dibuang: begitu disimpan, barisnya sudah dikonfirmasi di kunjungan ini.
                $tanpaTanda = fn(array $daftar) => array_values(array_map(fn($baris) => array_diff_key((array) $baris, ['dariMaster' => true]), $daftar));
                foreach (RiwayatPenyakitPribadi::NODE as $jenis => $node) {
                    $data['anamnesa'][$node]['snomedList'] = $tanpaTanda($this->koding[$jenis]['snomedList'] ?? []);
                }
                $data['anamnesa']['riwayatPenyakitKeluarga']['snomedList'] = $tanpaTanda($this->koding['keluarga']['snomedList'] ?? []);
                $data['anamnesa']['riwayatPengobatan']['obatList'] = $tanpaTanda($this->koding['pengobatan']['obatList'] ?? []);

                // Alergi: daftar alergen hanya berlaku saat EMR menyatakan "Ya". Saat "Tidak", kodenya
                // (716186003) milik EMR — dibiarkan apa adanya. Kode tunggal lama dikosongkan: daftar
                // kini sumbernya (baris lamanya sudah ikut masuk daftar saat layar dibuka).
                $alergiDb = AlergiSnomed::normalisasi($data['anamnesa']['alergi'] ?? []);
                if (($alergiDb['adaAlergi'] ?? '') === 'Ya') {
                    $alergiDb['alergenList'] = array_values($this->koding['alergi']['alergenList'] ?? []);
                    $alergiDb = [...$alergiDb, 'snomedCode' => '', 'snomedDisplayEn' => '', 'snomedDisplayId' => ''];
                    $data['anamnesa']['alergi'] = $alergiDb;
                }
                $this->sinkronMasterPasien($data['anamnesa']);

                $this->updateJsonUGD((int) $this->rjNo, $data);
                $this->muatDariDokumen($data);

                $this->appendAdminLogUGD((int) $this->rjNo, 'Coding SATUSEHAT Anamnesa — keluhan utama ' . ($keluhanUtama['snomedCode'] ?: '-')
                    . ', riwayat sekarang ' . count($data['anamnesa']['riwayatPenyakitSekarangUmum']['snomedList']) . ' kode'
                    . ', riwayat dahulu ' . count($data['anamnesa']['riwayatPenyakitDahulu']['snomedList']) . ' kode'
                    . ', riwayat keluarga ' . count($data['anamnesa']['riwayatPenyakitKeluarga']['snomedList']) . ' kode'
                    . ', riwayat pengobatan ' . count($data['anamnesa']['riwayatPengobatan']['obatList']) . ' obat'
                    . ', alergi ' . (($data['anamnesa']['alergi']['adaAlergi'] ?? '') === 'Ya'
                        ? count($data['anamnesa']['alergi']['alergenList'] ?? []) . ' alergen' : 'tidak ada'), 'MR');
            });

            $this->sidikKodingTersimpan = $this->sidikKoding();
            if (!$senyap) {
                $this->dispatch('toast', type: 'success', message: 'Coding SATUSEHAT tersimpan.');
            }
            $this->dispatch('ugd-satu-sehat.refresh', rjNo: (string) $this->rjNo);
            $this->dispatch('refresh-after-ugd.saved');
            $this->dispatch('coding-fhir-ugd.tersimpan', berhasil: true);
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menyimpan coding: ' . $e->getMessage());
            $this->dispatch('coding-fhir-ugd.tersimpan', berhasil: false);
        }
    }

    /** Baris baca kedatangan + kode yang dikirim kirim-kedatangan (sumber kode sama: KedatanganIgd). Murni. */
    private function barisKedatangan(array $perawatan, array $triaseTerpilih): array
    {
        $transportasi = KedatanganIgd::transportasi($perawatan['saranaTransportasiId'] ?? null, $perawatan['saranaTransportasiDesc'] ?? null, $perawatan['saranaTransportasiKet'] ?? null);
        $suratRujukan = KedatanganIgd::membawaSuratRujukan($perawatan['caraMasukIgd'] ?? null);
        ['tingkat' => $tingkat, 'sumber' => $sumberTriase] = $triaseTerpilih;
        $triase = KedatanganIgd::triase($tingkat);

        return [
            ['label' => 'Sarana Transportasi', 'isi' => $transportasi['text'] ?? '',
             'kode' => 'LOINC ' . KedatanganIgd::KODE_TRANSPORTASI['code'] . ($transportasi === null ? ' — kosong, tidak dikirim'
                 : (isset($transportasi['code']) ? ' · nilai SNOMED ' . $transportasi['code'] . ' ' . $transportasi['display'] : ' · nilai dikirim sebagai teks (tanpa kode)'))],
            ['label' => 'Cara Masuk', 'isi' => (string) ($perawatan['caraMasukIgd'] ?? ''),
             'kode' => KedatanganIgd::KODE_SURAT_RUJUKAN['code'] . ' Surat Pengantar Rujukan' . ($suratRujukan === null ? ' — kosong, tidak dikirim' : ' · ' . ($suratRujukan ? 'Ya' : 'Tidak'))],
            ['label' => 'Tingkat Kegawatan', 'isi' => $tingkat !== '' ? KedatanganIgd::labelTriase($tingkat) . ($sumberTriase !== 'EMR' ? ' (' . $sumberTriase . ')' : '') : '',
             'kode' => 'LOINC ' . KedatanganIgd::KODE_TRIASE['code'] . ' CTAS' . ($triase !== null ? ' · ' . $triase['code'] . ' (level ' . $triase['display'] . ')'
                 : ($tingkat !== '' ? ' — tak ada padanan CTAS, tidak dikirim' : ' — kosong, tidak dikirim'))],
        ];
    }

    /** Ringkasan Rekonsiliasi Obat EMR UGD sebagai konteks baca untuk riwayat pengobatan. Murni. */
    private function teksRekonsiliasiObat(array $rekonsiliasiObat): string
    {
        return collect($rekonsiliasiObat)
            ->map(fn($obat) => trim(implode(' ', array_filter([
                trim((string) ($obat['namaObat'] ?? '')),
                trim((string) ($obat['dosis'] ?? '')),
                trim((string) ($obat['rute'] ?? '')),
            ], fn($bagian) => $bagian !== ''))))
            ->filter()
            ->implode("\n");
    }

    /** Daftar kosong + teks sama dengan master → isi dari kunjungan sebelumnya (ditandai dariMaster). Murni. */
    private function isiDaftarDariMaster(array $koding, array $pasienMaster): array
    {
        foreach (self::DAFTAR_MASTER as $kunciKoding => [$node, $kunciDaftar, $kunciTeksMaster, $kunciDaftarMaster]) {
            $teks = trim((string) ($koding[$kunciKoding]['teks'] ?? ''));
            if (($koding[$kunciKoding][$kunciDaftar] ?? []) !== [] || $teks === ''
                || trim((string) ($pasienMaster[$kunciTeksMaster] ?? '')) !== $teks) {
                continue;
            }
            $koding[$kunciKoding][$kunciDaftar] = array_map(
                fn($baris) => [...(array) $baris, 'dariMaster' => true],
                array_values((array) ($pasienMaster[$kunciDaftarMaster] ?? [])),
            );
        }

        return $koding;
    }

    /**
     * Kode yang dipakai ulang lintas kunjungan → master pasien, dalam SATU kali simpan. Tiap daftar
     * ikut HANYA bila teks master = teks kunjungan ini (aturan form anamnesa: kode menempel pada
     * teksnya — jangan menempel ke teks lain), supaya kunjungan berikutnya tak perlu mengoding ulang.
     * Alergi: daftar alergen bila "Ada alergi" (kode tunggal lama master dikosongkan).
     */
    private function sinkronMasterPasien(array $anamnesa): void
    {
        $pasienData = $this->findDataMasterPasien($this->regNoPasien);
        $pasienBaru = $this->pasienSetelahSinkron($pasienData['pasien'] ?? [], $anamnesa);
        if ($pasienBaru !== null) {
            $pasienData['pasien'] = [...$pasienBaru, 'regNo' => $this->regNoPasien];
            $this->updateJsonMasterPasien($this->regNoPasien, $pasienData);
        }
    }

    /** Isi master pasien sesudah sinkron; null = tak ada yang berubah. Murni (tanpa DB). */
    private function pasienSetelahSinkron(array $pasien, array $anamnesa): ?array
    {
        $berubah = false;

        $alergi = AlergiSnomed::normalisasi($anamnesa['alergi'] ?? []);
        $teksAlergiMaster = trim((string) ($pasien['alergi'] ?? ''));
        if (($alergi['adaAlergi'] ?? '') === 'Ya' && $teksAlergiMaster !== '' && $teksAlergiMaster === trim((string) ($alergi['alergi'] ?? ''))) {
            $pasien['alergiAlergenList'] = $alergi['alergenList'] ?? [];
            $pasien['alergiSnomedCode'] = '';
            $pasien['alergiSnomedDisplayEn'] = '';
            $pasien['alergiSnomedDisplayId'] = '';
            $berubah = true;
        }

        foreach (self::DAFTAR_MASTER as [$node, $kunciDaftar, $kunciTeksMaster, $kunciDaftarMaster]) {
            $teksMaster = trim((string) ($pasien[$kunciTeksMaster] ?? ''));
            if ($teksMaster === '' || $teksMaster !== trim((string) ($anamnesa[$node][$node] ?? ''))) {
                continue;
            }
            $pasien[$kunciDaftarMaster] = array_values((array) ($anamnesa[$node][$kunciDaftar] ?? []));
            $berubah = true;
        }

        return $berubah ? $pasien : null;
    }
};
?>

<x-border-form title="Anamnesa">
    <div class="space-y-3">
        @include('pages.transaksi.ugd.coding-fhir-ugd.anamnesa.tabs.kedatangan')
        @include('pages.transaksi.ugd.coding-fhir-ugd.anamnesa.tabs.keluhan')
        @include('pages.transaksi.ugd.coding-fhir-ugd.anamnesa.tabs.riwayat-penyakit')
        @include('pages.transaksi.ugd.coding-fhir-ugd.anamnesa.tabs.riwayat-keluarga')
        @include('pages.transaksi.ugd.coding-fhir-ugd.anamnesa.tabs.riwayat-pengobatan')
        @include('pages.transaksi.ugd.coding-fhir-ugd.anamnesa.tabs.alergi')
        @include('pages.transaksi.ugd.coding-fhir-ugd.anamnesa.tabs.status-psikologis')
    </div>
</x-border-form>
