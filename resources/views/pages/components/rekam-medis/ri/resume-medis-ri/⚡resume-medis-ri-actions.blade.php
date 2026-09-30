<?php

/**
 * ════════════════════════════════════════════════════════════════════════════════
 * Resume Medis Pasien Pulang (Editor & PDF Generator)
 * ════════════════════════════════════════════════════════════════════════════════
 *
 * Modul ini menangani pembuatan **Resume Medis** untuk pasien Rawat Inap
 * saat pulang. Output: dokumen ringkas berisi diagnosa, anamnesis, pemeriksaan,
 * tindakan, kondisi pulang, dst., yang ditandatangani DPJP dan dibawa pasien.
 *
 * ────────────────────────────────────────────────────────────────────────────────
 * 1. PENYIMPANAN DATA — di mana isi resume medis disimpan?
 * ────────────────────────────────────────────────────────────────────────────────
 *
 * Resume medis disimpan sebagai **HTML string** di kolom JSON di header RI:
 *
 *   Tabel  : `rstxn_rihdrs`
 *   Kolom  : `datadaftarri_json` (CLOB, JSON document)
 *   Path   : `resumeMedis`   ← langsung HTML string, tidak nested object
 *
 * Contoh struktur JSON di `datadaftarri_json`:
 *
 *   {
 *     "regNo": "00012345",
 *     "entryDate": "13/05/2026 00:50:24",
 *     "diagnosis": [ ... ],
 *     "procedureICDList": [ ... ],
 *     "pengkajianDokter": { "anamnesa": {...}, "fisik": "...", ... },
 *     "pengkajianAwalPasienRawatInap": { "bagian1DataUmum": {...}, ... },
 *     "perencanaan": { "tindakLanjut": { "tindakLanjut": "371827001", ... } },
 *     "resumeMedis": "<table>...HTML dari TinyMCE...</table>"
 *   }
 *
 * **Value** = output langsung dari TinyMCE editor — `<table>`, `<p>`, `<strong>`,
 * `<ol>`, dst. Tidak di-parse atau di-normalize; saat render PDF pakai `{!! !!}`
 * raw HTML.
 *
 * Kalau di future butuh metadata audit (savedAt/savedBy), tambahkan sebagai
 * **sibling key root-level** (mis. `resumeMedisSavedAt`, `resumeMedisSavedBy`),
 * **bukan** nested object — supaya path `resumeMedis` tetap konsisten = HTML.
 *
 * ────────────────────────────────────────────────────────────────────────────────
 * 2. CARA KERJA — alur open → edit → simpan → cetak
 * ────────────────────────────────────────────────────────────────────────────────
 *
 *   Step 1 (OPEN)
 *     • Tombol "Resume Medis" di EMR RI dispatch event
 *       `resume-medis-ri.open` dengan `riHdrNo`.
 *     • `open()` load `$dataRI` via `findDataRI()` (decode `datadaftarri_json`).
 *     • Cek `resumeMedis` — kalau sudah ada (sudah pernah disimpan), pakai itu.
 *       Kalau kosong, build template default via
 *       `buildPreFilledTemplate()` — auto-fill dari pengkajian + diagnosis +
 *       prosedur + tindak lanjut.
 *     • Dispatch `open-modal` → TinyMCE bootEditor → editor render dgn pre-fill.
 *
 *   Step 2 (EDIT)
 *     • TinyMCE editor edit `<table>` HTML — sync ke `$this->resumeMedis`
 *       via debounced events (input/change/keyup/blur/SetContent).
 *     • Tombol "Reset ke Default" → `resetToDefault()` → rebuild template dari
 *       data EMR terbaru → dispatch `resume-medis-ri.reload` →
 *       TinyMCE listener panggil `editor.setContent($wire.get('resumeMedis'))`.
 *
 *   Step 3 (SIMPAN)
 *     • Klik tombol "Simpan" → Alpine dispatch `resume-medis-ri.flush` window
 *       event → TinyMCE `flush()` push HTML editor terbaru ke `$this->resumeMedis`
 *       → `$nextTick(() => $wire.save())`.
 *     • Server `save()`: validate min 5 char teks, lock row, update JSON via
 *       `updateJsonRI()` (set key `resumeMedis` ke HTML string), commit, toast
 *       sukses. Modal tetap terbuka — user bisa terus edit.
 *
 *   Step 4 (CETAK PDF)
 *     • Klik "Cetak PDF" → flush event → `cetakPdf()` → render blade
 *       `resume-medis-ri-print.blade.php` via DomPDF dengan
 *       payload `[dataDaftarRi, dataPasien, resumeMedis]` → stream download.
 *     • PDF render = header pasien (auto dari `findDataMasterPasien`) +
 *       body `{!! $resumeMedis !!}` + footer TTD DPJP (digital dari
 *       `users.myuser_ttd_image` lookup via `dr_id` Utama).
 *     • **Penting:** Cetak PDF pakai isi **in-memory** (editor saat ini),
 *       bukan dari JSON DB. Jadi user boleh cetak preview tanpa save dulu.
 *
 *   Step 5 (CLOSE)
 *     • Tombol Batal atau X → `closeEditor()` → reset property → dispatch
 *       `close-modal` → TinyMCE `cleanupEditor()` (remove instance,
 *       filter null entries dari `tinymce.editors` global).
 *
 * ────────────────────────────────────────────────────────────────────────────────
 * 3. EMR STATUS LOCK — sengaja DI-SKIP untuk Resume Medis
 * ────────────────────────────────────────────────────────────────────────────────
 *
 * Modul EMR RI lain (mis. pengkajian dokter, perencanaan, form pindah ruang)
 * di-lock saat `ri_status != 'I'` (pasien sudah pulang/'P'). Polanya:
 *
 *   $this->isFormLocked = $this->checkEmrRIStatus($riHdrNo);
 *
 * **Resume Medis TIDAK mengikut pola ini.** Alasannya:
 *
 *   • Resume Medis biasanya dibuat **saat atau sesudah** pasien pulang.
 *     Pulang = `ri_status = 'P'`. Kalau lock pas 'P', justru momen Resume
 *     Medis paling dibutuhkan dia tidak bisa dibuat — counter-productive.
 *
 *   • DPJP juga sering perlu **edit/koreksi** resume medis pasca-pulang
 *     (typo, tambahan diagnosis komplikasi, revisi obat pulang) — terutama
 *     untuk klaim BPJS yang ditolak verifikator.
 *
 *   • Untuk audit, kita pakai `savedAt` + `savedBy` di JSON sebagai trail.
 *
 * **Kunci = TTD DPJP (sejak 2026-09-28), bukan status pulang.** Pola modul dokumen:
 * tombol "TTD DPJP & Kunci" men-stempel dokter login (nama + myuser_code + waktu) ke
 * `datadaftarri_json.resumeMedisTtd` sekaligus menyimpan isi editor, lalu resume
 * terkunci. Koreksi pasca-pulang tetap bisa: "Buka Kunci" (penanda tangan sendiri
 * atau Gate `dokumen.bukaKunci`) mencabut TTD, dokter mengoreksi, lalu TTD ulang.
 * Cetak memakai stempel tersimpan — dulu gambar TTD DPJP Utama ditempel otomatis
 * saat cetak walau dokternya belum pernah menyetujui isinya.
 *
 * ────────────────────────────────────────────────────────────────────────────────
 * 4. EVENTS YANG DIDISPATCH/LISTEN
 * ────────────────────────────────────────────────────────────────────────────────
 *
 *   LISTEN:
 *     • `resume-medis-ri.open` (Livewire, dari EMR RI button)
 *
 *   DISPATCH:
 *     • `open-modal` { name: 'resume-medis-ri' }   — buka modal
 *     • `close-modal` { name: 'resume-medis-ri' }  — tutup modal
 *     • `resume-medis-ri.reload`                   — trigger TinyMCE reload
 *     • `toast` { type, message }                         — global toast
 *
 *   WINDOW EVENT (dari Alpine, ditangkap TinyMCE factory):
 *     • `resume-medis-ri.flush` — paksa flush isi editor ke $wire sebelum action
 *
 * ────────────────────────────────────────────────────────────────────────────────
 * 5. FILE TERKAIT
 * ────────────────────────────────────────────────────────────────────────────────
 *
 *   • `resume-medis-ri-print.blade.php` — template PDF DomPDF
 *   • `resources/views/components/tinymce-editor.blade.php` — komponen editor
 *   • `resources/js/app.js` (Alpine factory `tinymceEditor`) — TinyMCE bootstrap
 *   • `App\Http\Traits\Txn\Ri\EmrRITrait`                — findDataRI, updateJsonRI, lockRIRow
 *   • `App\Http\Traits\Master\MasterPasien\MasterPasienTrait` — findDataMasterPasien
 *
 *   Dokumentasi tambahan:
 *   • `docs/tinymce-editor-pattern.md`   — pola pemakaian TinyMCE
 *   • `docs/ttd-pattern-pdf-print.md`    — pola TTD di blade print
 */

use Carbon\Carbon;
use Livewire\Component;
use Livewire\Attributes\On;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use App\Http\Traits\Txn\Ri\EmrRITrait;
use App\Support\Terminologi\DischargeDisposition;
use App\Http\Traits\Master\MasterPasien\MasterPasienTrait;

new class extends Component {
    use EmrRITrait, MasterPasienTrait;

    public ?int $riHdrNo = null;
    public string $resumeMedis = '';

    /**
     * Ringkasan Pemulangan Pasien yang diisi PERAWAT (read-only referensi).
     * Ditarik dari `datadaftarri_json.ringkasanPulang` (+ metadata savedBy/savedAt)
     * dan ditampilkan sebagai panel referensi di modal Resume Medis — DPJP bisa
     * membaca/menyalin saat menyusun resume. TIDAK ikut disimpan di save() resume.
     */
    public string $ringkasanPulang = '';
    public string $ringkasanPulangSavedBy = '';
    public string $ringkasanPulangSavedAt = '';

    /** true = sudah TTD DPJP (resumeMedisTtd terisi) → editor & Simpan ditutup. */
    public bool $isFormLocked = false;

    /** Stempel TTD DPJP: ['nama', 'kode' (myuser_code), 'waktu' d/m/Y H:i:s]. Kosong = belum TTD. */
    public array $ttdDpjp = [];

    /* ═══════════════════════════════════════
     | OPEN — buka modal editor Resume Medis
     |
     | Flow:
     |   1. Load $dataRI dari JSON `datadaftarri_json` via findDataRI()
     |   2. Cek existing `resumeMedis` di JSON
     |      - Ada  → pakai HTML tersimpan (edit ulang)
     |      - Tidak → build template default dari data EMR (auto pre-fill)
     |   3. Dispatch open-modal → TinyMCE boot dgn pre-fill
    ═══════════════════════════════════════ */
    #[On('resume-medis-ri.open')]
    public function open(int $riHdrNo): void
    {
        $this->riHdrNo = $riHdrNo;
        $this->resetValidation();

        $dataRI = $this->findDataRI($riHdrNo);
        if (empty($dataRI)) {
            $this->dispatch('toast', type: 'error', message: 'Data Rawat Inap tidak ditemukan.');
            return;
        }

        // Kunci = TTD DPJP, bukan status pulang (doc block §3).
        $this->ttdDpjp = (array) data_get($dataRI, 'resumeMedisTtd', []);
        $this->isFormLocked = !empty($this->ttdDpjp['nama']);

        // Load existing dari path `resumeMedis` (HTML string) di datadaftarri_json.
        // Kalau kosong (belum pernah disimpan) → auto-build template default dari
        // data EMR terbaru via buildPreFilledTemplate().
        $existing = (string) data_get($dataRI, 'resumeMedis', '');
        $this->resumeMedis = $existing !== '' ? $existing : $this->buildPreFilledTemplate($dataRI);

        // Tarik Ringkasan Pemulangan Pasien (perawat) sebagai referensi read-only.
        $this->ringkasanPulang = (string) data_get($dataRI, 'ringkasanPulang', '');
        $this->ringkasanPulangSavedBy = (string) data_get($dataRI, 'ringkasanPulangSavedBy', '');
        $this->ringkasanPulangSavedAt = (string) data_get($dataRI, 'ringkasanPulangSavedAt', '');

        $this->dispatch('open-modal', name: 'resume-medis-ri');
    }

    /* ═══════════════════════════════════════
     | RESET — rebuild template default dari data EMR latest
     |
     | User klik tombol "Reset ke Default" di header modal. Akan:
     |  - Re-fetch $dataRI (kalau ada perubahan diagnosis/prosedur di EMR
     |    setelah modal dibuka)
     |  - Generate ulang template via buildPreFilledTemplate()
     |  - Push isi baru ke TinyMCE via event 'resume-medis-ri.reload'
     |
     | TIDAK menyentuh JSON DB. User harus klik Simpan untuk persist.
    ═══════════════════════════════════════ */
    public function resetToDefault(): void
    {
        if (empty($this->riHdrNo)) {
            $this->dispatch('toast', type: 'error', message: 'Sesi expired, buka ulang dari EMR RI.');
            return;
        }
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Resume sudah ditandatangani DPJP — buka kunci dulu untuk mengubah.');
            return;
        }

        $dataRI = $this->findDataRI($this->riHdrNo);
        if (empty($dataRI)) {
            $this->dispatch('toast', type: 'error', message: 'Data RI tidak ditemukan.');
            return;
        }

        $this->resumeMedis = $this->buildPreFilledTemplate($dataRI);
        $this->dispatch('resume-medis-ri.reload');
        $this->dispatch('toast', type: 'success', message: 'Template di-reset dari data EMR terbaru.');
    }

    /**
     * Build template Resume Medis dgn value pre-filled dari JSON RI:
     *  - Diagnosa Masuk         ← pengkajianAwalPasienRawatInap.bagian1DataUmum.diagnosaMasuk
     *  - Indikasi Rawat         ← pengkajianDokter.anamnesa.keluhanTambahan
     *  - Anamnesis              ← pengkajianDokter.anamnesa.keluhanUtama + riwayatPenyakit
     *  - Pemeriksaan Fisik      ← pengkajianAwalPasienRawatInap...tandaVital + pengkajianDokter.fisik
     *  - Pemeriksaan Penunjang  ← pengkajianDokter.hasilPemeriksaanPenunjang.{laboratorium, radiologi, penunjangLain}
     *  - Diagnosa Akhir         ← diagnosis[] filter kategoriDiagnosa = utama/primer
     *  - Komplikasi             ← diagnosis[] filter kategoriDiagnosa = komplikasi
     *  - Komorbid               ← diagnosis[] filter kategoriDiagnosa = komorbid/sekunder
     *  - Tindakan/Operasi       ← procedureICDList[]
     *  - Riwayat Alergi         ← pengkajianAwalPasienRawatInap.bagian2RiwayatAlergi.*
     *
     * Dokter tinggal isi sisa field manual (Obat Selama Rawat, Obat Pulang, Kondisi Pulang, Pengobatan Lanjutan, dst).
     */
    private function buildPreFilledTemplate(array $dataRI): string
    {
        $esc = fn($v) => e(trim((string) $v));

        // ── 1) Diagnosa Masuk ────────────────────────────────
        $diagnosaMasuk = $esc(data_get($dataRI, 'pengkajianAwalPasienRawatInap.bagian1DataUmum.diagnosaMasuk', ''));

        // ── 2) Indikasi Rawat ────────────────────────────────
        $indikasi = $esc(data_get($dataRI, 'pengkajianDokter.anamnesa.keluhanTambahan', ''));

        // ── 3) Anamnesis (keluhan utama + riwayat penyakit) ─
        $kuLine = trim((string) data_get($dataRI, 'pengkajianDokter.anamnesa.keluhanUtama', ''));
        $rpSekarang = trim((string) data_get($dataRI, 'pengkajianDokter.anamnesa.riwayatPenyakit.sekarang', ''));
        $rpDahulu = trim((string) data_get($dataRI, 'pengkajianDokter.anamnesa.riwayatPenyakit.dahulu', ''));
        $rpKeluarga = trim((string) data_get($dataRI, 'pengkajianDokter.anamnesa.riwayatPenyakit.keluarga', ''));
        $anamnesisParts = [];
        if ($kuLine !== '') $anamnesisParts[] = 'Keluhan utama: ' . e($kuLine);
        if ($rpSekarang !== '') $anamnesisParts[] = 'Riwayat penyakit sekarang: ' . e($rpSekarang);
        if ($rpDahulu !== '') $anamnesisParts[] = 'Riwayat penyakit dahulu: ' . e($rpDahulu);
        if ($rpKeluarga !== '') $anamnesisParts[] = 'Riwayat penyakit keluarga: ' . e($rpKeluarga);
        $anamnesis = implode('<br>', $anamnesisParts);

        // ── 4) Pemeriksaan Fisik (TTV + narasi fisik) ───────
        $tv = (array) data_get($dataRI, 'pengkajianAwalPasienRawatInap.bagian4PemeriksaanFisik.tandaVital', []);
        $td = trim((string) data_get($tv, 'sistolik', '') . '/' . (string) data_get($tv, 'distolik', ''));
        $ttvParts = [];
        if ($td !== '/' && $td !== '') $ttvParts[] = 'TD ' . e($td) . ' mmHg';
        if ($n = data_get($tv, 'frekuensiNadi'))  $ttvParts[] = 'N ' . e($n) . '/mnt';
        if ($r = data_get($tv, 'frekuensiNafas')) $ttvParts[] = 'RR ' . e($r) . '/mnt';
        if ($s = data_get($tv, 'suhu'))           $ttvParts[] = 'T ' . e($s) . '°C';
        $ttvLine = implode('; ', $ttvParts);
        $fisikNarasi = trim((string) data_get($dataRI, 'pengkajianDokter.fisik', ''));
        $pemFisik = trim($ttvLine . ($ttvLine && $fisikNarasi ? '<br>' : '') . e($fisikNarasi));

        // ── 5) Pemeriksaan Penunjang ────────────────────────
        $lab = trim((string) data_get($dataRI, 'pengkajianDokter.hasilPemeriksaanPenunjang.laboratorium', ''));
        $rad = trim((string) data_get($dataRI, 'pengkajianDokter.hasilPemeriksaanPenunjang.radiologi', ''));
        $lain = trim((string) data_get($dataRI, 'pengkajianDokter.hasilPemeriksaanPenunjang.penunjangLain', ''));
        $penunjangParts = [];
        if ($lab !== '')  $penunjangParts[] = 'Lab: ' . e($lab);
        if ($rad !== '')  $penunjangParts[] = 'Radiologi: ' . e($rad);
        if ($lain !== '') $penunjangParts[] = 'Lain: ' . e($lain);
        $penunjang = implode('<br>', $penunjangParts);

        // ── 6) Diagnosis (filter by kategori) ───────────────
        $dxList = collect(data_get($dataRI, 'diagnosis', []));
        $cari = function (array $keywords) use ($dxList) {
            return $dxList->first(function ($d) use ($keywords) {
                $k = strtolower((string) data_get($d, 'kategoriDiagnosa', ''));
                foreach ($keywords as $kw) {
                    if (str_contains($k, $kw)) return true;
                }
                return false;
            });
        };
        $dxFree = trim((string) data_get($dataRI, 'diagnosisFreeText', ''));
        $dxUtama = $cari(['utama', 'primer', 'primary']);
        $dxKompl = $cari(['komplikasi']);
        $dxKomor = $cari(['komorbid', 'sekunder', 'secondary']);

        $diagAkhirText = $esc(data_get($dxUtama, 'descDiagnosa', '')) ?: e($dxFree);
        $diagAkhirIcd = $esc(data_get($dxUtama, 'kdDiagnosa', ''));
        $komplikasiText = $esc(data_get($dxKompl, 'descDiagnosa', ''));
        $komplikasiIcd = $esc(data_get($dxKompl, 'kdDiagnosa', ''));
        $komorbidText = $esc(data_get($dxKomor, 'descDiagnosa', ''));
        $komorbidIcd = $esc(data_get($dxKomor, 'kdDiagnosa', ''));

        // ── 7) Tindakan / Operasi (procedureICDList) ────────
        $procList = collect(data_get($dataRI, 'procedureICDList', []))
            ->map(fn($p) => [
                'desc' => trim((string) data_get($p, 'descProcedure', '')),
                'icd' => trim((string) data_get($p, 'kdProcedure', '')),
            ])
            ->filter(fn($p) => $p['desc'] !== '')
            ->values();

        $tindakanHtml = '';
        if ($procList->isNotEmpty()) {
            foreach ($procList as $p) {
                $tindakanHtml .= '<li>' . e($p['desc']);
                if ($p['icd'] !== '') $tindakanHtml .= ' &nbsp;&nbsp;<em>ICD-9CM:</em> ' . e($p['icd']);
                $tindakanHtml .= '</li>';
            }
        } else {
            $tindakanHtml = '<li>&nbsp;&nbsp;<em>ICD-9CM:</em> </li><li>&nbsp;&nbsp;<em>ICD-9CM:</em> </li>';
        }

        // ── 8) Riwayat Alergi ← pengkajianDokter.anamnesa.jenisAlergi ───────
        // DULU membaca `pengkajianAwalPasienRawatInap.bagian2RiwayatAlergi.{alergiObat,
        // alergiMakanan, alergiLain}.desc` — node itu TIDAK PERNAH DITULIS siapa pun
        // (0 dari 61.446 record; satu-satunya penyebutnya adalah pembaca ini sendiri),
        // sehingga Riwayat Alergi di Resume Medis SELALU KOSONG. Sumber yang benar =
        // Pengkajian Dokter RI (2.039 record terisi), sejalan dgn RJ/UGD.
        $alergi = e(trim((string) data_get($dataRI, 'pengkajianDokter.anamnesa.jenisAlergi', '')));

        // Fallback: node lama, kalau suatu saat form pengkajian awal benar-benar mengisinya.
        if ($alergi === '') {
            $alergiParts = [];
            foreach ([
                'Obat' => 'alergiObat',
                'Makanan' => 'alergiMakanan',
                'Lain' => 'alergiLain',
            ] as $label => $key) {
                $v = trim((string) data_get($dataRI, "pengkajianAwalPasienRawatInap.bagian2RiwayatAlergi.{$key}.desc", ''));
                if ($v !== '') {
                    $alergiParts[] = $label . ': ' . e($v);
                }
            }
            $alergi = implode('; ', $alergiParts);
        }

        // ── 9) Kondisi Saat Pulang ← perencanaan.tindakLanjut.tindakLanjut ─
        // `tindakLanjut` menyimpan KUNCI INTERNAL (bentuknya mirip SNOMED tapi 2 di antaranya
        // salah arti — lihat App\Support\Terminologi\DischargeDisposition). Label diambil dari SUMBER
        // TUNGGAL di helper itu: dulu file ini punya peta sendiri yang menulis '371828006'
        // = "Perbaikan", padahal form menyebutnya "Pulang Tanpa Perbaikan" — makna TERBALIK
        // (komentar di peta lama pun sudah menulis "Pulang Tanpa Perbaikan" di sebelahnya).
        // Cetak ringkasan pulang bahkan menulis "Membaik": satu kode, empat arti berbeda.
        $tindakLanjutCode = trim((string) data_get($dataRI, 'perencanaan.tindakLanjut.tindakLanjut', ''));
        $tglPulang = (string) data_get($dataRI, 'perencanaan.tindakLanjut.tglPulang', '');
        $tglMeninggal = (string) data_get($dataRI, 'perencanaan.tindakLanjut.tglMeninggal', '');
        $ketTindakLanjut = trim((string) data_get($dataRI, 'perencanaan.tindakLanjut.keterangan', ''));

        $kondisiPulang = DischargeDisposition::label($tindakLanjutCode);
        if ($kondisiPulang === 'Meninggal' && $tglMeninggal !== '') {
            $kondisiPulang .= ' (' . $tglMeninggal . ')';
        }
        if ($kondisiPulang === 'Lain-lain' && $ketTindakLanjut !== '') {
            $kondisiPulang .= ' (' . $ketTindakLanjut . ')';
        }

        $isRujuk = $tindakLanjutCode === '306206005';
        $dirujukKe = $isRujuk ? e($ketTindakLanjut) : '';
        $alasanRujuk = $isRujuk ? e($ketTindakLanjut) : '';
        $kondisiPulang = e($kondisiPulang);

        // ── 10) Kontrol Ulang ← SKDP di datadaftarri_json.kontrol (pola sama ringkasan perawat) ─
        $kontrol = (array) data_get($dataRI, 'kontrol', []);
        $tglKontrolRaw = trim((string) data_get($kontrol, 'tglKontrol', ''));
        $kontrolHariTgl = '';
        if ($tglKontrolRaw !== '') {
            try {
                $kontrolHariTgl = Carbon::createFromFormat('d/m/Y', $tglKontrolRaw)->locale('id')->translatedFormat('l, d/m/Y');
            } catch (\Throwable) {
                $kontrolHariTgl = $tglKontrolRaw;
            }
        }
        $kontrolHariTgl = e($kontrolHariTgl);
        $poliKontrol = trim((string) data_get($kontrol, 'poliKontrolDesc', ''));
        $drKontrol = trim((string) data_get($kontrol, 'drKontrolDesc', ''));
        $tempatKontrol = e(trim($poliKontrol . ($drKontrol !== '' ? ' — ' . $drKontrol : '')));
        $pengobatanLanjutanCell = 'Poliklinik: ' . $tempatKontrol . ' &nbsp;&nbsp;<strong>Tanggal Kontrol:</strong> ' . $kontrolHariTgl;

        // ── Build final HTML — pakai <table> 2-kolom (Label | Value) ────
        // Tema diselaraskan dgn header identitas pasien (Tailwind): label
        // pakai `text-muted` (abu, normal — bukan <strong>), ukuran font
        // ikut wrapper `.resume-medis-content` (11px) di template cetak.
        // Border tipis #cbd5e1 inline (border-gray Tailwind rawan purge di PDF).
        $row = fn(string $label, string $value) =>
            "<tr><td class=\"text-muted align-top\" style=\"width: 200px; border: 1px solid #cbd5e1; padding: 3px 6px;\">{$label}</td>" .
            "<td class=\"align-top\" style=\"border: 1px solid #cbd5e1; padding: 3px 6px;\">{$value}</td></tr>";

        $tindakanCell = $procList->isNotEmpty()
            ? '<ol style="margin:0; padding-left: 18px;">' . $tindakanHtml . '</ol>'
            : '<ol style="margin:0; padding-left: 18px;"><li>ICD-9CM: </li><li>ICD-9CM: </li></ol>';

        $diagAkhirCell = $diagAkhirText . ($diagAkhirIcd !== '' ? ' &nbsp;&nbsp;<em>ICD-10:</em> ' . $diagAkhirIcd : ' &nbsp;&nbsp;<em>ICD-10:</em> ');
        $komplikasiCell = $komplikasiText . ($komplikasiIcd !== '' ? ' &nbsp;&nbsp;<em>ICD-10:</em> ' . $komplikasiIcd : ' &nbsp;&nbsp;<em>ICD-10:</em> ');
        $komorbidCell = $komorbidText . ($komorbidIcd !== '' ? ' &nbsp;&nbsp;<em>ICD-10:</em> ' . $komorbidIcd : ' &nbsp;&nbsp;<em>ICD-10:</em> ');
        $dirujukCell = $dirujukKe . ' &nbsp;&nbsp;<strong>Alasan:</strong> ' . $alasanRujuk;

        $rows = implode("\n", [
            $row('Diagnosa Masuk', $diagnosaMasuk),
            $row('Indikasi Rawat', $indikasi),
            $row('Anamnesis', $anamnesis),
            $row('Pemeriksaan Fisik', $pemFisik),
            $row('Pemeriksaan Penunjang', $penunjang),
            $row('Obat Selama Rawat', ''),
            $row('Diagnosa Akhir', $diagAkhirCell),
            $row('Komplikasi', $komplikasiCell),
            $row('Komorbid', $komorbidCell),
            $row('Tindakan / Operasi', $tindakanCell),
            $row('Riwayat Alergi', $alergi),
            $row('Obat / Terapi Pulang', ''),
            $row('Kondisi Saat Pulang', $kondisiPulang),
            $row('Dirujuk ke', $dirujukCell),
            $row('Pengobatan Lanjutan', $pengobatanLanjutanCell),
            $row('Segera Bawa ke RS Bila', ''),
        ]);

        return '<table class="w-full" style="border-collapse: collapse;">' . "\n" . $rows . "\n" . '</table>';
    }

    public function closeEditor(): void
    {
        $this->reset(['riHdrNo', 'resumeMedis', 'isFormLocked', 'ttdDpjp', 'ringkasanPulang', 'ringkasanPulangSavedBy', 'ringkasanPulangSavedAt']);
        $this->dispatch('close-modal', name: 'resume-medis-ri');
    }

    /* ═══════════════════════════════════════
     | SAVE — simpan ke JSON RI, modal tetap terbuka
     |
     | Tulis HTML editor langsung ke path `resumeMedis` (string) di
     | `rstxn_rihdrs.datadaftarri_json`. Pakai lockRIRow() untuk
     | concurrency safety (FOR UPDATE).
     |
     | Catatan: Resume Medis tidak mengikut EMR lock (lihat doc §3) —
     | DPJP boleh simpan sekalipun ri_status sudah 'P'.
    ═══════════════════════════════════════ */
    public function save(): void
    {
        if (empty($this->riHdrNo)) {
            $this->dispatch('toast', type: 'error', message: 'Sesi expired, buka ulang dari EMR RI.');
            return;
        }
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Resume sudah ditandatangani DPJP — buka kunci dulu untuk mengubah.');
            return;
        }

        $plain = trim(strip_tags((string) $this->resumeMedis));
        if (mb_strlen($plain) < 5) {
            $this->addError('resumeMedis', 'Resume medis harus diisi (minimal 5 karakter teks).');
            return;
        }

        $this->validate(
            ['resumeMedis' => 'required|string|max:65000'],
            ['resumeMedis.required' => 'Resume medis harus diisi.'],
        );

        $dataRI = $this->findDataRI($this->riHdrNo);
        if (empty($dataRI)) {
            $this->dispatch('toast', type: 'error', message: 'Data RI tidak ditemukan.');
            return;
        }

        try {
            DB::transaction(function () use (&$dataRI) {
                $this->lockRIRow($this->riHdrNo);
                // Simpan langsung sebagai HTML string di key `resumeMedis` —
                // tidak pakai nested object/metadata, supaya path JSON konsisten.
                $dataRI['resumeMedis'] = $this->resumeMedis;
                $this->updateJsonRI($this->riHdrNo, $dataRI);
            });
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal simpan: ' . $e->getMessage());
            return;
        }

        $this->dispatch('toast', type: 'success', message: 'Draft resume medis tersimpan — belum ditandatangani.');
    }

    /* ═══════════════════════════════════════
     | TTD DPJP = SIMPAN + KUNCI
     |
     | Aksi terakhir (pola modul dokumen): isi editor saat ini ikut disimpan, lalu
     | stempel dokter login ditulis ke `resumeMedisTtd`. Hanya role Dokter.
    ═══════════════════════════════════════ */
    public function tandaTanganDpjp(): void
    {
        if (empty($this->riHdrNo)) {
            $this->dispatch('toast', type: 'error', message: 'Sesi expired, buka ulang dari EMR RI.');
            return;
        }
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Resume sudah ditandatangani.');
            return;
        }
        if (!auth()->user()?->hasRole('Dokter')) {
            $this->dispatch('toast', type: 'error', message: 'TTD Resume Medis hanya untuk dokter (DPJP).');
            return;
        }

        $plain = trim(strip_tags((string) $this->resumeMedis));
        if (mb_strlen($plain) < 5) {
            $this->addError('resumeMedis', 'Resume medis harus diisi sebelum ditandatangani.');
            $this->dispatch('toast', type: 'error', message: 'Resume medis masih kosong.');
            return;
        }
        $this->validate(
            ['resumeMedis' => 'required|string|max:65000'],
            ['resumeMedis.required' => 'Resume medis harus diisi.'],
        );

        $stempel = [
            'nama' => (string) (auth()->user()->myuser_name ?? ''),
            'kode' => (string) (auth()->user()->myuser_code ?? ''),
            'waktu' => Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s'),
        ];

        try {
            DB::transaction(function () use ($stempel) {
                $this->lockRIRow($this->riHdrNo);
                $dataRI = $this->findDataRI($this->riHdrNo);
                if (empty($dataRI)) {
                    throw new \RuntimeException('Data RI tidak ditemukan.');
                }
                if (!empty(data_get($dataRI, 'resumeMedisTtd.nama'))) {
                    throw new \RuntimeException('Resume sudah ditandatangani ' . data_get($dataRI, 'resumeMedisTtd.nama') . '.');
                }
                $dataRI['resumeMedis'] = $this->resumeMedis;
                $dataRI['resumeMedisTtd'] = $stempel;
                $this->updateJsonRI($this->riHdrNo, $dataRI);
                $this->appendAdminLogRI((int) $this->riHdrNo, 'TTD DPJP & kunci Resume Medis — ' . $stempel['nama'], 'MR');
            });
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal TTD: ' . $e->getMessage());
            return;
        }

        $this->ttdDpjp = $stempel;
        $this->isFormLocked = true;
        $this->dispatch('toast', type: 'success', message: 'Resume medis ditandatangani DPJP dan terkunci.');
    }

    /** Boleh buka kunci: penanda tangan sendiri, atau role pemegang Gate dokumen.bukaKunci. */
    public function bolehBukaKunci(): bool
    {
        $kodeSaya = (string) (auth()->user()->myuser_code ?? '');

        return ($kodeSaya !== '' && $kodeSaya === (string) ($this->ttdDpjp['kode'] ?? ''))
            || Gate::allows('dokumen.bukaKunci');
    }

    /* Buka kunci = cabut TTD DPJP; isi resume tetap, bisa dikoreksi lalu TTD ulang. */
    public function bukaKunci(): void
    {
        if (empty($this->riHdrNo) || !$this->isFormLocked) {
            return;
        }
        if (!$this->bolehBukaKunci()) {
            $this->dispatch('toast', type: 'error', message: 'Hanya dokter penanda tangan atau pemegang wewenang buka kunci.');
            return;
        }

        $namaLama = (string) ($this->ttdDpjp['nama'] ?? '-');
        $pelaku = (string) (auth()->user()->myuser_name ?? auth()->user()->name ?? '-');

        try {
            DB::transaction(function () use ($namaLama, $pelaku) {
                $this->lockRIRow($this->riHdrNo);
                $dataRI = $this->findDataRI($this->riHdrNo);
                if (empty($dataRI)) {
                    throw new \RuntimeException('Data RI tidak ditemukan.');
                }
                unset($dataRI['resumeMedisTtd']);
                $this->updateJsonRI($this->riHdrNo, $dataRI);
                $this->appendAdminLogRI((int) $this->riHdrNo, "Buka kunci Resume Medis — TTD {$namaLama} dicabut (oleh {$pelaku})", 'MR');
            });
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal buka kunci: ' . $e->getMessage());
            return;
        }

        $this->ttdDpjp = [];
        $this->isFormLocked = false;
        $this->dispatch('toast', type: 'success', message: 'Kunci dibuka — TTD DPJP dicabut. Koreksi lalu tandatangani ulang.');
    }

    /* ═══════════════════════════════════════
     | CETAK PDF — generate PDF dari isi editor saat ini (in-memory)
    ═══════════════════════════════════════ */
    public function cetakPdf(): mixed
    {
        if (empty($this->riHdrNo)) {
            $this->dispatch('toast', type: 'error', message: 'Sesi expired, buka ulang dari EMR RI.');
            return null;
        }

        $plain = trim(strip_tags((string) $this->resumeMedis));
        if (mb_strlen($plain) < 5) {
            $this->addError('resumeMedis', 'Resume medis kosong — isi dulu sebelum dicetak.');
            $this->dispatch('toast', type: 'error', message: 'Resume medis kosong.');
            return null;
        }

        $dataRI = $this->findDataRI($this->riHdrNo);
        if (empty($dataRI)) {
            $this->dispatch('toast', type: 'error', message: 'Data RI tidak ditemukan.');
            return null;
        }

        $regNo = (string) ($dataRI['regNo'] ?? '');
        $pasienData = $regNo ? $this->findDataMasterPasien($regNo) : null;
        if (empty($pasienData)) {
            $this->dispatch('toast', type: 'error', message: 'Data pasien tidak ditemukan.');
            return null;
        }

        $pdf = Pdf::loadView(
            'pages.components.rekam-medis.ri.resume-medis-ri.resume-medis-ri-print',
            [
                'dataDaftarRi' => $dataRI,
                'dataPasien' => $pasienData,
                'resumeMedis' => $this->resumeMedis,
            ],
        )->setPaper('A4', 'portrait');

        $filename = 'resume-medis-ri-' . ($regNo ?: $this->riHdrNo) . '.pdf';
        $this->dispatch('toast', type: 'success', message: 'PDF di-generate.');
        return response()->streamDownload(fn() => print $pdf->output(), $filename);
    }
};
?>

<div>
    <x-modal name="resume-medis-ri" size="full" height="full" focusable>
        <div class="flex flex-col h-full">
            <div class="px-6 py-4 border-b border-hairline dark:border-gray-700">
                {{-- Judul & subjudul dihapus — header = identitas pasien sebelahan dengan tombol X. --}}
                @if (!empty($riHdrNo))
                    <div class="flex items-start gap-3">
                        <div class="flex-1 min-w-0">
                            <livewire:pages::transaksi.ri.display-pasien-ri.display-pasien-ri :riHdrNo="$riHdrNo"
                                wire:key="resume-medis-ri-display-pasien-header-{{ $riHdrNo }}" />
                            @if ($isFormLocked)
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 mt-2 rounded-full text-[10px] font-bold uppercase bg-red-100 text-red-700 dark:bg-red-900/50 dark:text-red-300">
                                    <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/></svg>
                                    Ditandatangani DPJP — terkunci
                                </span>
                            @endif
                        </div>
                        <x-icon-button color="gray" type="button" wire:click="closeEditor" class="shrink-0">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                            </svg>
                        </x-icon-button>
                    </div>
                @else
                    <div class="flex items-center justify-end">
                        <x-icon-button color="gray" type="button" wire:click="closeEditor" class="shrink-0">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                            </svg>
                        </x-icon-button>
                    </div>
                @endif
            </div>

            {{-- Body BOLEH scroll sendiri (scrollbar kanan). Editor mengisi sisa tinggi modal di
                 layar besar, tapi punya tinggi minimum (rm-editor-wrap / rm-kunci-box) supaya di
                 layar pendek / zoom browser tidak terjepit jadi satu baris — kelebihannya
                 digulir lewat body. --}}
            <style>
                .rm-editor-wrap .tox-tinymce { height: 100% !important; }
                .rm-editor-wrap, .rm-kunci-box { min-height: 480px; }
                .rm-kunci-content { font-size: 12px; line-height: 1.45; color: #1f2937; }
                .rm-kunci-content table { border-collapse: collapse; width: 100%; }
                .rm-kunci-content td, .rm-kunci-content th { border: 1px solid #cbd5e1; padding: 3px 6px; vertical-align: top; }
                .rm-kunci-content .text-muted { color: #6b7280; }
                .dark .rm-kunci-content { color: #d1d5db; }
            </style>
            <div class="flex flex-col flex-1 min-h-0 px-6 py-4 overflow-y-auto">
                {{-- Referensi: Ringkasan Pemulangan Pasien (perawat) — read-only, collapsible.
                     Ditarik dari datadaftarri_json.ringkasanPulang. DPJP bisa baca/salin
                     saat menyusun resume; tidak ikut tersimpan di resume. --}}
                @if (trim(strip_tags($ringkasanPulang)) !== '')
                    <div x-data="{ openRef: false }"
                        class="mb-2 border border-teal-200 rounded-lg dark:border-teal-800 bg-teal-50/40 dark:bg-teal-900/10 shrink-0">
                        <button type="button" x-on:click="openRef = !openRef"
                            class="flex items-center justify-between w-full px-3 py-2 text-left">
                            <span class="text-xs font-semibold text-teal-700 dark:text-teal-300">
                                Ringkasan Pemulangan Pasien (Perawat) — referensi
                                @if ($ringkasanPulangSavedBy) <span class="font-normal text-teal-600 dark:text-teal-400">· oleh {{ $ringkasanPulangSavedBy }}</span> @endif
                                @if ($ringkasanPulangSavedAt) <span class="font-normal text-teal-600 dark:text-teal-400">· {{ $ringkasanPulangSavedAt }}</span> @endif
                            </span>
                            <svg class="w-4 h-4 text-teal-600 transition-transform dark:text-teal-400"
                                :class="openRef && 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                        <div x-show="openRef" x-cloak class="px-3 pb-3 overflow-auto max-h-64">
                            {{-- Style scoped (bukan Tailwind arbitrary, agar tabel obat ringkasan
                                 tetap bergaris tanpa perlu rebuild CSS). --}}
                            <style>
                                .rp-ref-content { font-size: 11px; line-height: 1.4; color: #374151; }
                                .rp-ref-content table { border-collapse: collapse; width: 100%; }
                                .rp-ref-content td, .rp-ref-content th { border: 1px solid #cbd5e1; padding: 3px 6px; vertical-align: top; }
                                .rp-ref-content .text-muted { color: #6b7280; }
                                .dark .rp-ref-content { color: #d1d5db; }
                            </style>
                            <div class="rp-ref-content">
                                {!! $ringkasanPulang !!}
                            </div>
                        </div>
                    </div>
                @else
                    <div class="flex items-center gap-1.5 px-3 py-2 mb-2 text-[11px] text-muted dark:text-gray-500 bg-surface-soft dark:bg-gray-800/40 border border-hairline dark:border-gray-700 rounded-lg shrink-0">
                        <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <span>Ringkasan Pemulangan Pasien (perawat) belum diisi — tidak ada referensi yang bisa ditampilkan.</span>
                    </div>
                @endif

                {{-- Banner status baku modul dokumen: terkunci = sudah TTD DPJP. --}}
                @if ($isFormLocked)
                    <x-modul-dokumen.banner jenis="terkunci" class="mb-3 shrink-0">
                        Resume sudah ditandatangani {{ $ttdDpjp['nama'] ?? 'DPJP' }}{{ !empty($ttdDpjp['waktu']) ? ' (' . $ttdDpjp['waktu'] . ')' : '' }}
                        — terkunci, tidak dapat diubah.
                    </x-modul-dokumen.banner>
                @endif

                <div class="flex flex-wrap items-center justify-between mb-1 gap-x-2 shrink-0">
                    <x-input-label value="Isi Resume Medis" required class="!mb-0" />
                    <span class="text-xs text-muted dark:text-gray-400">Identitas pasien terisi otomatis saat dicetak; TTD dari stempel DPJP di bawah. Editor mendukung teks ala Word + tabel.</span>
                </div>

                {{-- Terkunci: tampilkan isi tersimpan read-only. Editor TIDAK dibuang dari DOM
                     (wire:ignore + boot saat open-modal) — cukup disembunyikan, supaya setelah
                     Buka Kunci editor langsung bisa dipakai tanpa buka ulang modal. --}}
                @if ($isFormLocked)
                    <div class="flex-1 min-h-0 p-3 mt-1 overflow-auto border rounded-md rm-kunci-box border-hairline dark:border-gray-700 bg-surface-soft dark:bg-gray-800/40">
                        <div class="rm-kunci-content">{!! $resumeMedis !!}</div>
                    </div>
                @endif
                <div @class(['flex-1 min-h-0 mt-1 rm-editor-wrap', 'hidden' => $isFormLocked])>
                    <x-tinymce-editor
                        name="resumeMedis"
                        placeholder="Ketik isi resume medis (Diagnosa Masuk, Anamnesis, Pemeriksaan, Diagnosa Akhir, Tindakan, Obat Pulang, Kondisi Pulang, dll)..."
                        height="600"
                        modal-event="resume-medis-ri"
                        flush-event="resume-medis-ri.flush"
                        reload-event="resume-medis-ri.reload"
                        :content-style="'body{font-family:sans-serif;font-size:11px;line-height:1.4;color:#1f2937;} table{border-collapse:collapse;width:100%;} table td,table th{border:1px solid #cbd5e1;padding:3px 6px;vertical-align:top;} .text-muted{color:#6b7280;}'"
                        class="h-full" />
                </div>
                @error('resumeMedis')
                    <p class="mt-1 text-xs text-red-500 shrink-0">{{ $message }}</p>
                @enderror

                {{-- ══ TANDA TANGAN DPJP ══ — aksi terakhir yang sekaligus mengunci. --}}
                <div class="flex flex-col gap-4 pt-3 mt-3 border-t shrink-0 border-hairline dark:border-gray-700 sm:flex-row sm:items-end sm:justify-between">
                    <div class="text-xs text-muted dark:text-gray-400 sm:max-w-md">
                        @if ($isFormLocked)
                            Untuk koreksi, <strong>Buka Kunci</strong> (kiri bawah) mencabut TTD DPJP; tandatangani ulang sesudah mengoreksi.
                        @else
                            <strong>Simpan Draft</strong> boleh berkali-kali selama resume disusun.
                            <strong>TTD DPJP &amp; Kunci</strong> menyimpan isi editor terakhir lalu mengunci resume. Hanya dokter.
                        @endif
                    </div>
                    <div class="w-full sm:w-72">
                        <x-signature.ttd-petugas :framed="false" label="Dokter Penanggung Jawab Pelayanan"
                            :ttd="$ttdDpjp['nama'] ?? ''" :code="$ttdDpjp['kode'] ?? ''" :date="$ttdDpjp['waktu'] ?? ''"
                            :locked="$isFormLocked" :canSign="auth()->user()?->hasRole('Dokter')" :allowClear="false"
                            sign="tandaTanganDpjp" signLabel="TTD DPJP &amp; Kunci" nameLabel="Nama DPJP"
                            emptyText="Belum ditandatangani DPJP." />
                        {{-- Tak berwenang TTD: tombol tetap tampil (nonaktif) + alasannya, supaya tidak
                             disangka fiturnya belum ada. --}}
                        @if (!$isFormLocked && !auth()->user()?->hasRole('Dokter'))
                            <div class="pt-2">
                                <x-primary-button type="button" disabled class="justify-center w-full gap-1.5 opacity-60 cursor-not-allowed"
                                    title="TTD DPJP hanya dapat dilakukan akun Dokter.">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                    </svg>
                                    TTD DPJP &amp; Kunci
                                </x-primary-button>
                                <p class="mt-1 text-xs text-amber-700 dark:text-amber-400">TTD DPJP hanya dapat dilakukan akun Dokter.</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="sticky bottom-0 z-10 flex items-center justify-between gap-2 px-6 py-3 border-t border-hairline bg-canvas dark:bg-gray-900 dark:border-gray-700 shrink-0">
                {{-- Pojok kiri: Reset ke Default (draft) atau Buka Kunci (terkunci) — pola modul dokumen. --}}
                @if ($isFormLocked)
                    @if ($this->bolehBukaKunci())
                        <x-confirm-button variant="warning-soft" action="bukaKunci()" title="Buka Kunci Resume Medis"
                            message="TTD DPJP akan dicabut dan resume kembali bisa diedit. Lanjutkan?"
                            confirmText="Ya, Buka Kunci" wire:key="buka-kunci-resume-{{ $riHdrNo }}">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M8 11V7a4 4 0 118 0m-4 8v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2z" />
                            </svg>
                            Buka Kunci
                        </x-confirm-button>
                    @else
                        <span></span>
                    @endif
                @else
                    <x-secondary-button type="button"
                        wire:click="resetToDefault"
                        wire:confirm="Reset isi Resume Medis ke template default dari data EMR terbaru? Perubahan yang belum disimpan akan hilang."
                        wire:loading.attr="disabled" wire:target="resetToDefault"
                        class="text-xs">
                        <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                        <span wire:loading.remove wire:target="resetToDefault">Reset ke Default</span>
                        <span wire:loading wire:target="resetToDefault"><x-loading /> Reset...</span>
                    </x-secondary-button>
                @endif

                {{-- Aksi kanan: Batal · Cetak · Simpan Draft (Simpan Draft hilang saat terkunci) --}}
                <div class="flex items-center gap-2">
                    <x-secondary-button type="button" wire:click="closeEditor">Batal</x-secondary-button>

                    {{-- Cetak PDF (in-memory: tidak save dulu, langsung render). --}}
                    <x-secondary-button type="button"
                        x-on:click="window.dispatchEvent(new Event('resume-medis-ri.flush')); $nextTick(() => $wire.cetakPdf())"
                        wire:loading.attr="disabled" wire:target="cetakPdf,save">
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0110.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0l.229 2.523a1.125 1.125 0 01-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0021 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 00-1.913-.247M6.34 18H5.25A2.25 2.25 0 013 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 011.913-.247m10.5 0a48.536 48.536 0 00-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659M18 10.5h.008v.008H18V10.5z"/></svg>
                        <span wire:loading.remove wire:target="cetakPdf">Cetak PDF</span>
                        <span wire:loading wire:target="cetakPdf"><x-loading /> Cetak...</span>
                    </x-secondary-button>

                    {{-- Simpan Draft (modal tetap terbuka, toast sukses) — bisa dicicil; TIDAK mengunci.
                         Terkunci (sudah TTD) → disembunyikan, sama dengan footer modul dokumen. --}}
                    @unless ($isFormLocked)
                        <x-primary-button type="button"
                            x-on:click="window.dispatchEvent(new Event('resume-medis-ri.flush')); $nextTick(() => $wire.save())"
                            wire:loading.attr="disabled" wire:target="save,cetakPdf"
                            class="gap-2 min-w-[160px] justify-center">
                            <span wire:loading.remove wire:target="save" class="flex items-center gap-1.5">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 21v-8H7v8M7 3v5h8M5 3h11l4 4v12a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2z" />
                                </svg>
                                Simpan Draft
                            </span>
                            <span wire:loading wire:target="save"><x-loading /> Menyimpan...</span>
                        </x-primary-button>
                    @endunless
                </div>
            </div>
        </div>
    </x-modal>
</div>
