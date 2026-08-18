<?php

use Livewire\Component;

// Tutorial standarisasi koding domain TRANSAKSI (RJ/UGD/RI: pendaftaran → pelayanan →
// kasir + lintas-modul EMR / modul dokumen / administrasi).
// Gaya sama dgn koding-master: sidebar per-submenu, snippet = nowdoc (aman compiler Blade).
new class extends Component {
    public function snippets(): array
    {
        return [

'clob-read' => <<<'TXT'
// Detail transaksi disimpan sebagai SATU kolom JSON CLOB di tabel header
// (rstxn_rjhdrs.datadaftarpolirj_json, dst.) — bukan puluhan tabel normalized.

// Cara baca yang benar — lewat trait jalur:
$data = $this->findDataRJ($rjNo);        // EmrRJTrait  → rsview_rjkasir
$data = $this->findDataUGD($ugdNo);      // EmrUGDTrait → rstxn_ugdhdrs
$data = $this->findDataRI($riHdrNo);     // EmrRITrait  → rsview_rihdrs

// Di balik trait: App\Support\OracleLob::read(raw, table, keyCol, keyVal, lobCol)
// → baca locator CLOB dgn aman; kalau kena ORA-01555/ORA-22924 (snapshot too old
//   setelah save-all), otomatis re-fetch lewat statement segar.

// JANGAN: TO_CHAR(kolom_json) / DBMS_LOB.SUBSTR di query —
//         JSON > 32.767 byte TERPOTONG diam-diam → data korup saat disimpan balik.
TXT,

'rmw' => <<<'TXT'
// Pola tulis JSON yang WAJIB: read-modify-write dalam transaksi + row lock.
DB::transaction(function () use ($rjNo) {
    $this->lockRJRow($rjNo);                     // SELECT ... FOR UPDATE — wajib duluan
    $data = $this->findDataRJ($rjNo);            // baca JSON TERKINI (setelah lock)

    $data['anamnesa']['keluhanUtama'] = '...';   // mutasi array di PHP

    $this->updateJsonRJ($rjNo, $data);           // tulis balik (validasi rjNo payload cocok)
});

// JANGAN: findData di awal request → update di akhir TANPA lock.
// Dua user menyimpan bersamaan = perubahan salah satunya HILANG (last write wins).
// Catatan RI: findDataRI membaca VIEW (rsview_rihdrs) — lock tetap ke tabel aslinya.
TXT,

'pendaftaran-save' => <<<'TXT'
// KERANGKA SAVE PENDAFTARAN — ⚡daftar-rj-actions.blade.php (dipadatkan).
// Modal actions terpisah (event daftar-rj.create.open / daftar-rj.edit.open),
// pola sama dgn form master — bedanya: nomor transaksi, no antrian, dan
// sederet guard jadwal.

#[On('lov.selected.rjFormPasien')]   // LOV pasien → isi regNo + identitas
#[On('lov.selected.rjFormDokter')]   // LOV dokter → poli, shift, jadwal

public function save(): void
{
    $this->validateDataRJ();                    // validasi Indonesia paling atas

    // GUARD kuota (warning, TIDAK memblok simpan): terdaftar (rjhdrs)
    //   + booking MJKN status 'Belum' >= kuota jadwal → toast "Kuota penuh".
    // GUARD shift: shiftMismatchMessage() — jam daftar vs shift jadwal dokter.

    DB::transaction(function () {
        // CREATE — nomor transaksi dihitung DI DALAM transaksi:
        $rjNo = (string) ((int) DB::table('rstxn_rjhdrs')->max('rj_no') + 1);

        DB::table('rstxn_rjhdrs')->insert($this->buildPayload($rjNo));
        // payload = kolom HEADER saja: rj_no, rj_date (to_date), reg_no,
        // no_antrian, klaim_id, poli_id, dr_id, shift + STATUS AWAL:
        // txn_status 'A' · rj_status 'A' · erm_status 'A' · pass_status 'O'
    });
    // EDIT — update by rj_no; nomor & no antrian TIDAK dihitung ulang.
}

// No antrian = max gabungan rjhdrs + booking MJKN per dokter/poli/tanggal.
// Kolom booking bertipe VARCHAR2 → wajib to_number() supaya max-nya numeric
// (urutan leksikal membuat '9' > '10').
private function hitungNoAntrian(string $drId, Carbon $tgl): int { ... }

// PENTING: kolom JSON (datadaftarpolirj_json) TIDAK diisi saat pendaftaran —
// baru terbentuk saat modul lain menulis (EMR, task-id, administrasi).
// Karena itu semua pembaca JSON wajib toleran kosong (findDataRJ ?? []) —
// juga demi entry lama dari Oracle Dev 6i (dual-system).

// Setelah tersimpan, aksi lanjutan = komponen SIBLING terpisah:
// vclaim-rj-actions (SEP) · satu-sehat-rj-actions · cetak etiket
// (print-agent localhost) · task-id antrean BPJS (AntrianTrait).
TXT,

'list-query' => <<<'TXT'
#[Computed]
public function baseQuery()
{
    // Subquery penunjang (lab/rad) di-SCOPE ke rentang tanggal via JOIN ke header.
    // Tanpa scope ini Oracle full-scan jutaan baris riwayat → list lemot.
    $lab = DB::table('lbtxn_checkuphdrs as l')
        ->join('rstxn_rjhdrs as a', 'a.rj_no', '=', 'l.rj_no')
        ->whereBetween(DB::raw('trunc(a.rj_date)'), [$start, $end])
        ->select('l.rj_no', DB::raw('count(*) as jml_lab'))
        ->groupBy('l.rj_no');

    return DB::table('rstxn_rjhdrs as a')
        ->leftJoinSub($lab, 'lab', 'lab.rj_no', '=', 'a.rj_no')
        ->whereBetween(DB::raw('trunc(a.rj_date)'), [$start, $end])
        ->orderByDesc('a.rj_no');
}

#[Computed]
public function rows()
{
    // Paginate di DB — lalu transform HANYA page aktif (±10 baris).
    $p = $this->baseQuery()->paginate($this->itemsPerPage);
    $p->getCollection()->transform(fn ($r) => $this->transformRjRow($r));
    return $p;
}

// transformRjRow(): DI SINILAH OracleLob::read + json_decode dilakukan —
// decode CLOB hanya untuk baris yang tampil, bukan seluruh hasil query.
TXT,

'emr-host' => <<<'TXT'
// EMR = MODAL full-screen (bukan route), di-embed sebagai sibling di pelayanan.
// Host dibuka via event dari list, lalu MENYEBARKAN event open ke tiap section:

#[On('emr-rj.rekam-medis.open')]
public function openRekamMedisPerawat(int $rjNo): void
{
    $this->resetForm();
    $this->rjNo = $rjNo;
    $this->dataDaftarPoliRJ = $this->findDataRJ($rjNo);

    if ($this->checkEmrRJStatus($rjNo)) {
        $this->isFormLocked = true;              // EMR terkunci → semua section read-only
    }

    $this->dispatch('open-modal', name: 'rm-perawat-actions');
    $this->dispatch('open-rm-anamnesa-rj', $rjNo);       // S
    $this->dispatch('open-rm-pemeriksaan-rj', $rjNo);    // O
    $this->dispatch('open-rm-penilaian-rj', $rjNo);      // A
    $this->dispatch('open-rm-diagnosa-rj', $rjNo);       // A
    $this->dispatch('open-rm-perencanaan-rj', $rjNo);    // P
}
TXT,

'emr-section' => <<<'TXT'
{{-- Host me-mount tiap section sebagai CHILD livewire — selalu :rjNo + wire:key --}}
<livewire:pages::transaksi.rj.emr-rj.anamnesa.rm-anamnesa-rj-actions
    :rjNo="$rjNo" wire:key="anamnesa-rj-{{ $rjNo }}" />

{{-- Save-all: dirty-modal mem-broadcast event save ke tiap section --}}
<x-dirty-modal-content name="rm-perawat-actions" event="refresh-after-rj.saved"
    :save-events="[
        'save-rm-anamnesa-rj',
        'save-rm-pemeriksaan-rj',
        'save-rm-diagnosa-rj',
        'save-rm-perencanaan-rj',
    ]" :wireKey="$this->renderKey('modal-emr-rj', [$rjNo ?? 'new'])">

{{-- Tombol "Simpan Semua" — tiap section menyimpan dgn toast SILENT
     (satu toast gabungan, bukan 5 toast beruntun) --}}
events.forEach(e => Livewire.dispatch(e, { silent: true }))
TXT,

'emr-section-skeleton' => <<<'TXT'
// ⚡rm-<section>-rj-actions.blade.php — KERANGKA UTUH satu section EMR
// (disarikan dari rm-perencanaan-rj-actions, section acuan paling ramping).
// ── BAGIAN 1: blok PHP kelas Volt (di antara tag php pembuka & penutup) ──

use Livewire\Component;
use App\Http\Traits\Txn\Rj\EmrRJTrait;
use App\Http\Traits\Concerns\WithRenderVersioningTrait;
use App\Http\Traits\Concerns\WithValidationToastTrait;

new class extends Component {
    use EmrRJTrait, WithRenderVersioningTrait, WithValidationToastTrait;

    public ?int  $rjNo = null;
    public bool  $isFormLocked = false;
    public array $dataDaftarPoliRJ = [];              // cache JSON CLOB kunjungan

    public array $renderVersions = [];
    protected array $renderAreas = ['modal-<section>-rj'];

    public function mount(): void
    {
        $this->registerAreas(['modal-<section>-rj']);
    }

    // 1) OPEN — host menyebarkan open-rm-<section>-rj saat EMR dibuka
    #[On('open-rm-<section>-rj')]
    public function openSection($rjNo): void
    {
        if (empty($rjNo)) return;
        $this->rjNo = $rjNo;
        $this->resetValidation();

        $data = $this->findDataRJ($rjNo);                     // baca CLOB (OracleLob)
        if (! $data) {
            $this->dispatch('toast', type: 'error', message: 'Data RJ tidak ditemukan.');
            return;
        }

        $this->dataDaftarPoliRJ = $data;
        $this->dataDaftarPoliRJ['<keySection>'] ??= $this->getDefault(); // backfill record lama
        $this->incrementVersion('modal-<section>-rj');        // remount area → state segar
        $this->isFormLocked = $this->checkEmrRJStatus($rjNo); // EMR sudah dikunci?
    }

    // 2) DEFAULT — struktur key JSON milik section ini
    //    (record dari SIMRS lama / entry lama belum punya key ini)
    private function getDefault(): array
    {
        return ['field1' => '', 'field2' => ''];
    }

    // 3) SAVE — dipicu tombol sendiri ATAU broadcast save-all (silent=true)
    #[On('save-rm-<section>-rj')]
    public function save(bool $silent = false): void
    {
        if ($this->isFormLocked) return;
        $this->validateWithToast($rules, $messages, $attributes);

        DB::transaction(function () {
            $this->lockRJRow($this->rjNo);                    // row-lock anti race
            $data = $this->findDataRJ($this->rjNo) ?? [];
            // set HANYA key milik section ini — key section lain tak tersentuh:
            $data['<keySection>'] = $this->dataDaftarPoliRJ['<keySection>'] ?? [];
            $this->updateJsonRJ($this->rjNo, $data);
            $this->dataDaftarPoliRJ = $data;
        });

        $this->afterSave('<Section> tersimpan.', $silent);    // lihat kartu berikutnya
    }
};

// ── BAGIAN 2: MARKUP (setelah tag php penutup) ──
{{-- container ber-wire:key renderKey; SEMUA input hormati $isFormLocked,
     numerik pakai wire:model.blur (bukan .live) --}}
<div>
    <div class="flex flex-col w-full"
         wire:key="{{ $this->renderKey('modal-<section>-rj', [$rjNo ?? 'new']) }}">
        {{-- field-field section --}}
    </div>
</div>
TXT,

'emr-save' => <<<'TXT'
// Di dalam SECTION (mis. anamnesa): listener save menerima flag silent.
#[On('save-rm-anamnesa-rj')]
public function save(bool $silent = false): void
{
    // validateWithToast() = validate() + auto-toast error (WithValidationToastTrait)
    $this->validateWithToast($rules, $messages, $attributes);

    DB::transaction(function () {
        $this->lockRJRow($this->rjNo);
        $data = $this->findDataRJ($this->rjNo);
        $data['anamnesa'] = array_replace($data['anamnesa'] ?? [], $this->formAnamnesa);
        $this->updateJsonRJ($this->rjNo, $data);
    });

    if (! $silent) {
        $this->dispatch('toast', type: 'success', message: 'Anamnesa tersimpan.');
    }
}
TXT,

'emr-after-save' => <<<'TXT'
// SETELAH save — tiap section menutup save()-nya dgn helper afterSave():
private function afterSave(string $message, bool $silent = false): void
{
    $this->incrementVersion('modal-anamnesa-rj');   // remount area → state segar
    $this->dispatch('refresh-after-rj.saved');      // kabari halaman list

    if (! $silent) {                                // silent saat dipanggil save-all
        $this->dispatch('toast', type: 'success', message: $message);
    }
}

// ...dan halaman list (pelayanan-rj) mendengarkan utk refresh presisi:
#[On('refresh-after-rj.saved')]
public function refreshAfterSaved(): void
{
    $this->incrementVersion('pelayanan-rj-toolbar');
    $this->resetPage();   // computed baseQuery re-run → status & % EMR ikut segar
}

// Padanan jalur: refresh-after-ugd.saved → pelayanan-ugd,
//                refresh-after-ri.saved  → daftar-ri (+ display-pasien-ri).
TXT,

'emr-eresep' => <<<'TXT'
// E-RESEP (diisi dokter) — modal SIBLING di atas EMR, bukan section SOAP.
// pages/transaksi/rj/eresep-rj/: host + tab NonRacikan + tab Racikan.
//
// Pemicu (2 tombol): header EMR (emr-rj) & tab Terapi di section Perencanaan
//   → dispatch('emr-rj.eresep.open', rjNo) → host buka modal 2 tab
//   → host menyebarkan open-eresep-non-racikan-rj / open-eresep-racikan-rj.

// NON-RACIKAN — obat dipilih via LOV product, target unik per tab:
#[On('lov.selected.eresepRjObatNonRacikan')]
public function eresepRjObatNonRacikan(string $target, array $payload): void { ... }

// insertProduct = DUAL-WRITE dalam SATU transaksi + lockRJRow:
public function insertProduct(): void
{
    DB::transaction(function () {
        $this->lockRJRow($this->rjNo);

        // 1) baris BILLING — dibaca apotek & administrasi:
        DB::table('rstxn_rjobats')->insert([ /* rjobat_dtl, product_id, qty, harga */ ]);

        // 2) key JSON 'eresep' — tampilan EMR (qty, signaX/signaHari, catatanKhusus):
        $data['eresep'][] = [ /* payload LOV + signa */ ];
        $this->updateJsonRJ($this->rjNo, $data);
    });
}
// update/remove obat juga sinkron DUA tempat itu dalam transaksi yang sama.
// RACIKAN sama polanya — key JSON 'eresepRacikan' + noRacikan (R1, R2…) + dosis/takar.

// Tombol "Simpan ke Terapi" di host — generate teks resep ke section Perencanaan:
public function saveAllEreseptoTerapi(): void
{
    // guard: pasien sudah pulang = terkunci (checkRJStatus)
    // format per baris: "R/ {nama} | No. {qty} | S {X}dd{hari} ({catatan})"
    $data['perencanaan']['terapi']['terapi'] = $eresepText . PHP_EOL . $eresepRacikanText;
    $this->updateJsonRJ($this->rjNo, $data);
    $this->dispatch('emr-rj.rekam-medis.open', $this->rjNo);   // reopen EMR, tanpa toast
}
// Setelah tersimpan, resep tampil di antrian-apotek-rj utk dilayani apoteker.

// Padanan jalur: eresep-ugd → dual-write ke rstxn_ugdobats;
// eresep-ri  → JSON saja: eresepHdr[n].eresep (multi-resep per rawatan) —
//              billing RI menyusul per-item via imtxn_sls* saat apotek memproses.
TXT,

'emr-penunjang' => <<<'TXT'
// ORDER PENUNJANG dari SECTION PEMERIKSAAN — LAB, RADIOLOGI, KAMAR OPERASI.
// Komponen: emr-rj/pemeriksaan/penunjang/{laborat,radiologi,kamar-operasi}/rm-*-rj-actions
// (+ rm-daftar-* utk tampil hasil, + laborat LUAR utk hasil dari luar RS).
// Pola & letaknya sama persis di EMR UGD dan EMR RI (tab Pelayanan Penunjang).

// Modal picker: pilih item dari master (multi-select, cari + paginate).
// Diagnosis/Keterangan Klinis WAJIB — order tanpa indikasi klinis ditolak:
public array  $selectedItems = [];        // [clabitem_id => item]
public string $klinisDesc    = '';        // rules: required

// KIRIM LAB — kirimLaboratorium():
public function kirimLaboratorium(): void
{
    // guard: minimal 1 item + pasien belum pulang (checkRJStatus)
    DB::transaction(function () use ($rjData) {
        $checkupNo = DB::scalar('SELECT NVL(MAX(TO_NUMBER(checkup_no)) + 1, 1) FROM lbtxn_checkuphdrs');

        DB::table('lbtxn_checkuphdrs')->insert([
            'checkup_no'     => $checkupNo,
            'dr_id'          => $rjData->dr_id,   // dokter PENGIRIM — dari rstxn_rjhdrs
            'checkup_status' => 'P',              // P = baru masuk antrian lab
            'klinis_desc'    => $this->klinisDesc,
            /* reg_no, checkup_date, shift, ... */
        ]);

        foreach ($this->selectedItems as $item) {
            $this->insertItemAndChildren($checkupNo, $item);  // item PAKET → child ikut
        }
    });

    $this->appendAdminLogRJ((int) $this->rjNo, 'Order Lab — ...', 'MR');
    $this->dispatch('laborat-order-terkirim');    // section Pemeriksaan refresh daftar
}
// → order muncul di modul Penunjang Laborat (siklus status P → C → H → F).

// KIRIM RADIOLOGI — kirimRadiologi(): pola sama, target rstxn_rjrads
// (rad_dtl max+1, klinis_desc juga wajib) → modul Radiologi (upload-based,
// TIDAK punya siklus status P/C/H/F seperti lab).

// KIRIM KAMAR OPERASI — kirimKamarOperasi(): TIDAK punya tabel order terpisah.
// Order LANGSUNG membuat header rstxn_oks status 'A' — sama dengan transaksi yang
// dibuat petugas OK sendiri; pembedanya cuma audit log (MR vs ADMIN).
//   status_rjri = 'RJ'|'UGD'|'RI' · ref_no = rj_no (RJ/UGD) / rihdr_no (RI)
//   rihdr_no HANYA diisi untuk RI — kolomnya FK ke rstxn_rihdrs.
// Rencana tindakan opsional → rstxn_okacts, lalu KamarOperasiTarif::hitungUlang().
// Biaya BARU masuk tagihan setelah petugas OK menekan Trf Biaya (ok_status 'A'→'L').
// LOV tarif: RJ/UGD pakai lov-jasa-dokter (tarif dasar), RI pakai varian -ri
// (harga per kelas kamar). Detail: docs/kamar-operasi-modul.md.

// HASIL KEMBALI ke EMR — petugas lab menekan kirim hasil, Pemeriksaan menerima:
#[On('laborat-kirim-penunjang')]
public function terimaPenunjangLaborat(string $text): void
{
    // teks hasil masuk key JSON penunjang milik section Pemeriksaan
    // + appendAdminLogRJ + afterSave(...) → tampil di EMR & ikut cetakan
}
TXT,

'dokumen-flow' => <<<'TXT'
// Modul dokumen bertanda tangan = dua tahap: DRAFT → TTD-KUNCI.

// 1) Draft — boleh simpan sebagian; ikut dipanggil save-all EMR (silent):
#[On('save-rm-general-consent-rj')]
public function save(bool $silent = false): void
{
    // simpan apa adanya ke JSON (belum divalidasi lengkap, belum dikunci)
}

// 2) Finalize — TTD petugas = validasi LENGKAP + kunci form:
public function setPetugasPemeriksa(): void
{
    if ($this->isFormLocked) return;            // EMR locked / sudah TTD → tolak

    $this->validateWithToast($rulesLengkap, ...);
    // stempel: nama + myuser_code (ttdCode) + tanggal → isFormLocked = true

    $this->dispatch('refresh-modul-dokumen-rj-data', rjNo: $this->rjNo);
}

// Teks klausul dokumen legal = VERSIONING (App\Support\*Clause) —
// cetak ulang record lama memakai redaksi SAAT DITANDATANGANI.
// Baca docs/clause-versioning.md sebelum mengubah teks klausul apa pun.
TXT,

'dokumen-skeleton' => <<<'TXT'
// KERANGKA FORM MULTI-ENTRI — pola TERBARU (penundaan-pelayanan-ri /
// akhir-hayat-ri / permintaan-kerohanian-ri). Satu kunjungan bisa punya
// BANYAK entri;
// tiap entri hidup sendiri: Draft (bebas edit) → TTD (terkunci selamanya).

public bool   $isFormLocked = false;   // EMR terkunci / prop disabled
public bool   $viewOnly     = false;   // mode "Lihat" dari tabel entri
public string $signature    = '';      // TTD pasien/keluarga — dataURL signature-pad
public string $editingKey   = '';      // signatureDate entri = KUNCI STABIL
public array  $newForm      = [
    /* field-field form..., */
    'clauseVersion' => PenundaanClause::CURRENT,   // stempel versi klausul
];

public function mount(?string $riHdrNo = null, bool $disabled = false): void
{
    // muat list entri dari JSON; isFormLocked = checkEmrRIStatus() || $disabled
}

// DRAFT — boleh sebagian; entri yang sama terus di-update (tidak duplikat):
public function saveDraft(): void
{
    if ($this->isFormLocked || $this->viewOnly) return;

    $key = $this->editingKey ?: Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
    $this->persistEntry($key, false, 'Simpan draft');
    $this->editingKey = $key;              // lanjut edit entri yang sama
}

// TTD PETUGAS — validasi LENGKAP + TTD pasien wajib, lalu kunci permanen:
public function setPemberiInfo(): void
{
    // validate() penuh; guard signature pasien tidak boleh kosong
    $this->persistEntry($key, true, 'Kunci (TTD Petugas)');
}

// SATU pintu tulis — semua guard hidup di sini:
private function persistEntry(string $key, bool $finalized, string $logVerb): void
{
    DB::transaction(function () use ($key, $finalized, $logVerb) {
        $this->lockRIRow($this->riHdrNo);
        $data = $this->findDataRI($this->riHdrNo);

        $list = $data['penundaanPelayananRI'] ?? [];
        $idx  = collect($list)->search(fn($it) => ($it['signatureDate'] ?? '') === $key);

        if ($idx === false) {
            $list[] = $entry;                                  // entri baru
        } elseif ($this->entryIsFinal($list[$idx])) {
            throw new \RuntimeException('Entri sudah terkunci, tidak dapat diubah.');
        } else {
            $list[$idx] = $entry;                              // update draft
        }

        $data['penundaanPelayananRI'] = array_values($list);
        $this->updateJsonRI((int) $this->riHdrNo, $data);
        $this->appendAdminLogRI((int) $this->riHdrNo, $logVerb . ' ...', 'MR');
    });
}

// clauseVersion distempel saat entri DIBUAT dan dipertahankan saat edit —
// cetak ulang selalu memakai redaksi klausul SAAT DITANDATANGANI
// (registry App\Support\*Clause; baca docs/clause-versioning.md).
TXT,

'dokumen-clause' => <<<'TXT'
// CLAUSE VERSIONING — kenapa: teks klausul dokumen legal bisa berubah karena
// kebijakan (contoh nyata: transisi INA-CBG → iDRG). Cetak ulang record LAMA
// wajib memakai redaksi SAAT DITANDATANGANI, bukan redaksi terbaru.

// 1) Teks hidup di CLASS REGISTRY per-versi — bukan hardcoded di komponen/cetak:
//    app/Support/GeneralConsentClause.php (juga: PenjaminanClause, dst.)
class GeneralConsentClause
{
    public const CURRENT = 'v1';

    public static function get(string $context, ?string $version = null): array
    {
        $reg = self::registry();
        $ver = $version && isset($reg[$version]) ? $version : self::CURRENT;
        return $reg[$ver][$context] ?? [];
    }

    private static function registry(): array
    {
        return [
            'v1' => [ 'rj' => [...], 'ugd' => [...], 'ri' => [...] ],
            // versi baru = TAMBAH 'v2' + naikkan CURRENT — 'v1' JANGAN diubah
            // (versi lama = arsip legal). Bagian dinamis (%WALI% %HUB% %RS%)
            // diinterpolasi komponen via strtr, bukan disimpan di registry.
        ];
    }
}

// 2) Record MENSTEMPEL versi saat dibuat (di defaultConsent()/buildEntry()):
'clauseVersion' => GeneralConsentClause::CURRENT,

// 3) Cetak & Lihat me-render versi TERSIMPAN — fallback 'v1', BUKAN null:
//    record legacy pra-versioning tak punya stempel → wajib redaksi TERTUA
//    (?? null berarti CURRENT — salah utk cetak!). Di blade cetak, komponen
//    consent (x-consent.general-consent-rj dkk.) menerima prop version:
//      :version="$consent['clauseVersion'] ?? 'v1'"

// 4) Form entri BARU boleh ?? null (→ CURRENT); entri lama teruskan versi tersimpan.

// VERSIONING vs SNAPSHOT — jangan salah pilih:
//   Versioning (registry) → TEKS KLAUSUL, jarang berubah.
//   Snapshot (salin nilai ke entri) → DATA sering berubah per record,
//   mis. tarif/fasilitas kelas kamar: simpan salinan nama+tarif+fasilitas
//   saat buildEntry(); cetak prefer snapshot, fallback master utk legacy.

// WAJIB baca docs/clause-versioning.md (+ skill clause-versioning) SEBELUM
// mengubah teks klausul apa pun atau membuat dokumen ber-TTD baru.
TXT,

'dokumen-cetak' => <<<'TXT'
// POLA CETAK / PDF — berlaku utk SEMUA cetakan (dokumen, kwitansi, e-resep,
// hasil penunjang), bukan hanya modul dokumen.

// 1) Header identitas pasien = SATU komponen standar (x-pdf.identitas-pasien):
//    No RM · nama (gender) · tgl lahir (umur) · alamat · NIK.
//    Umur SELALU dihitung dari birth_date — kolom thn/bln/hari di master
//    adalah snapshot lama yang tidak pernah di-refresh.
//    Gender: mapping eksplisit L/P/- (JANGAN binary ==1 ? 'L' : 'P').

// 2) Blok TTD: pola h-16 + text-center (+ &nbsp; penahan tinggi) —
//    JANGAN flex / mx-auto / <br>; layout flex bergeser di PDF renderer.
//    Detail: docs/ttd-pattern-pdf-print.md.

// 3) Kelas Tailwind ARBITRARY tidak dirender di PDF:
//    text-[10px] / mt-[3mm] hilang DIAM-DIAM → utk ukuran cetak pakai
//    inline style (style="font-size:10px").

// 4) Viewer "Lihat" = iframe me-render blade cetak yang SAMA
//    (docs/dokumen-view-pattern.md) — satu sumber utk layar & kertas,
//    plus navigasi antar-record di dalam viewer.

// 5) Teks klausul di cetak = versi TERSIMPAN (lihat kartu clause versioning);
//    lokasi file cetak: pages/components/modul-dokumen/<jalur>/<form>/.
TXT,

'administrasi' => <<<'TXT'
// Administrasi = modal rekap biaya per kunjungan. Tiap POS = file partial sendiri
// (jasa-dokter, jasa-medis, jasa-karyawan, obat, laboratorium, radiologi,
//  kamar operasi, lain-lain...).

public int $sumTotalRJ = 0;

public function sumAll(): void
{
    $this->sumTotalRJ =
        $this->sumRsAdmin + $this->sumRjAdmin + $this->sumPoliPrice
        + $this->sumJasaKaryawan + $this->sumJasaDokter + $this->sumJasaMedis
        + $this->sumObat + $this->sumLaboratorium + $this->sumRadiologi
        + $this->sumKamarOperasi + $this->sumLainLain;
}

// Selesai administrasi → setSelesaiAdministrasiStatus()
// → pasien naik ke atas di antrian kasir (wire:poll.30s).
// RI: pos lebih banyak (visit, konsul, room, pindah-kamar, OK, obat-pinjam,
//     bon-resep, transfer UGD/RJ) dan billing per-item ke imtxn_slshdrs/slsdtls.
TXT,

'administrasi-pos' => <<<'TXT'
// KERANGKA SATU POS — lain-lain-rj (pos paling generik).
// Satu pos = satu child Livewire ber-:rjNo yang baca-tulis TABEL BILLING
// (rstxn_rjothers dst.) — BUKAN JSON CLOB; JSON hanya utk stempel AdministrasiRj.

// Muat baris pos: tabel billing join master-nya
$this->rjLainLain = DB::table('rstxn_rjothers')
    ->join('rsmst_others', 'rsmst_others.other_id', 'rstxn_rjothers.other_id')
    ->where('rstxn_rjothers.rj_no', $rjNo)
    ->orderBy('rstxn_rjothers.rjo_dtl')->get();

// Tambah item: LOV target unik per pos + nomor detail max+1
#[On('lov.selected.lain-lain-rj')]
public function onLainLainSelected(?array $payload): void { /* isi form dari payload */ }

public function insertLainLain(): void
{
    // guard rj_status 'A' — lihat kartu "Model pengunci" di bawah
    DB::transaction(function () {
        $last = DB::table('rstxn_rjothers')
            ->select(DB::raw('nvl(max(rjo_dtl)+1,1) as rjo_dtl_max'))->first();
        DB::table('rstxn_rjothers')->insert([ /* rjo_dtl, rj_no, other_id, other_price */ ]);
    });

    $this->dispatch('administrasi-rj.updated');   // ← host menghitung ulang sumAll()
}
// Edit inline: startEdit / saveEdit / cancelEdit — SETIAP mutasi dispatch
// administrasi-rj.updated; listener rj.administrasi-selesai men-disable pos.

// SISI HOST — embed tiap pos sebagai tab + dengarkan mutasi:
<livewire:pages::transaksi.rj.administrasi-rj.lain-lain-rj :rjNo="$rjNo" />

#[On('administrasi-rj.updated')]
public function onPosUpdated(): void
{
    $this->sumAll();          // grand total & breakdown selalu segar
}
TXT,

'kasir-post' => <<<'TXT'
// Posting bayar (contoh kasir RI) — selalu dalam transaksi + lock:
public function postTransaksi(): void
{
    $this->validateWithToast(['bayar' => 'required|numeric|min:0'], ...);

    DB::transaction(function () {
        DB::table('imtxn_slshdrs')->where('sls_no', $this->slsNo)
            ->lockForUpdate()->first();

        // bayar < total → jadi BON (sls_bon) + insert rstxn_ribonobats
        // bayar ≥ total → lunas, hitung kembalian
        DB::table('imtxn_slshdrs')->where('sls_no', $this->slsNo)->update([...]);
    });
}
TXT,

'role-audit' => <<<'TXT'
{{-- Guard aksi per role (Spatie) — contoh posting bayar kasir --}}
@hasanyrole('Admin|Tu|Manager Umum|Supervisor Tu')
    <x-primary-button type="button" wire:click="postTransaksi">Posting Bayar</x-primary-button>
@endhasanyrole
TXT,

'audit-log' => <<<'TXT'
// Audit log terpadu — setiap aksi admin/MR yang mengubah data pasien
// dicatat ke JSON (AdministrasiRJ.userLogs) dgn kategori ADMIN atau MR:
$this->appendAdminLogRJ($rjNo, 'Ubah tanggal kunjungan 01-07 → 02-07', 'ADMIN');
$this->appendAdminLogUGD($ugdNo, 'Koreksi diagnosa oleh Casemix', 'MR');

// Ditampilkan di tab "Log Aktivitas" EMR. Teks lewat App\Support\LogText::sanitize.
TXT,

'lock-model' => <<<'TXT'
// MODEL PENGUNCI — E-Resep, Kirim Lab, dan Kirim Radiologi semuanya menulis
// baris TAGIHAN yang dibaca Administrasi, maka ketiganya tunduk pada lapisan
// kunci yang sama. Sumber kebenaran: rstxn_rjhdrs.rj_status.

// L1 · KUNCI FINANSIAL — rj_status (checkRJStatus = rj_status !== 'A'):
//   'A' aktif    → resep / order / pos administrasi masih boleh berubah
//   'L' lunas    → di-set kasir saat posting bayar (txn_status 'L', bon = 'H')
//   'I' transfer → kasir memindahkan biaya RJ ke transaksi UGD
//   batal posting → kembali 'A' (tagihan bisa diubah lagi)
// SEMUA pintu mutasi tagihan memeriksa ini: insertProduct e-resep,
// kirimLaboratorium / kirimRadiologi, pos administrasi, posting kasir (idempoten).

// L2 · KUNCI URUTAN — order penunjang yang MASIH MENGGANTUNG menahan kasir.
// Berlaku untuk penunjang yang biayanya baru masuk SETELAH ditransfer petugas:
if ($this->checkLabPendingRJ($this->rjNo)) {      // ada checkup_status = 'P'
    // 'Hasil Laborat belum selesai, pembayaran tidak bisa diproses.'
}
if ($this->checkOkPendingRJ($this->rjNo)) {       // ada rstxn_oks ok_status = 'A'
    // 'Transaksi Kamar Operasi No. X belum ditransfer ke biaya rawat jalan...'
    // nomornya dari daftarOkPendingRJ() — supaya petugas tahu mana yang ditunggu.
}
// → tagihan belum final; posting ditolak sampai order penunjang itu selesai.
// Guard yang sama dipasang di transfer antar-unit (RJ→UGD, UGD→RI), karena
// transfer mengubah rj_status jadi 'I' padahal Trf Biaya mensyaratkan 'A'.
//
// Radiologi & Apotek TIDAK punya guard ini — biayanya langsung masuk saat
// diorder, tidak ada jeda yang perlu dijaga.

// L3 · KUNCI KEPEMILIKAN — administrasi yang sudah disimpan petugas lain:
//   JSON AdministrasiRj.userLog terisi → 'Administrasi sudah tersimpan oleh X'.

// L4 · KUNCI KONKURENSI — setiap tulis: DB::transaction + lockRJRow /
//   lockForUpdate (bab 03) — dua user tidak saling menimpa.

// L5 · KUNCI KLINIS (longgar, beda dgn finansial):
//   - erm_status: checkEmrRJStatus saat ini SENGAJA selalu false — kebijakan:
//     EMR tetap bisa diedit, cukup terjejak appendAdminLog (tab Log Aktivitas)
//   - dokumen ber-TTD: isFormLocked per-form setelah tanda tangan (bab 07)

// + KUNCI LINTAS JALUR: transfer RJ→UGD juga men-set
//   rsmst_pasiens.lockstatus = 'UGD' — pasien dipegang SATU jalur aktif.
TXT,

'api-trait' => <<<'TXT'
// SATU TRAIT PER API EKSTERNAL — pola sama utk semua: VclaimTrait, AntrianTrait,
// AplicaresTrait, iCareTrait, SirsTrait, iDrgTrait, SatuSehatTrait.
// Template + checklist lengkap: docs/trait-template-api-eksternal.md
// (ikuti polanya → log otomatis tampil di /database-monitor/log-bpjs).
//
// Tiga grup method di tiap trait:
//   Response helpers : sendResponse() / sendError()  — bentuk seragam + logging
//   Auth & crypto    : signature() / stringDecrypt() — BPJS: HMAC-SHA256 +
//                      AES + LZString; iDRG: AES-CBC; SATU SEHAT: OAuth2
//   API methods      : SATU method statis per endpoint

// CONTOH 1 — VClaim: buat SEP (VclaimTrait::sep_insert):
$signature = self::signature();                    // cons_id + timestamp + HMAC
$response  = Http::timeout(8)->connectTimeout(3)   // WAJIB — tanpa ini worker freeze
    ->withHeaders($signature)
    ->post($url, $SEPJsonReq);
return self::response_decrypt($response, $signature, $url,
    $response->transferStats->getTransferTime());  // decrypt AES+LZString + log

// CONTOH 2 — Antrean BPJS: lapor task-id tiap tahap pelayanan:
AntrianTrait::update_antrean($kodebooking, $taskid, $waktu, $jenisresep);
// taskId 3–7 = tahapan pelayanan (tiba di poli → obat diserahkan), 99 = batal.
// Stempelnya juga disimpan di JSON taskIdPelayanan — dipakai badge status list.
// Guard idempoten: taskId N butuh taskId N-1 sudah ada.

// CONTOH 3 — SATU SEHAT: token OAuth2 di-CACHE, bukan login tiap request:
Cache::remember('satusehat_access_token', 3500, function () {
    // POST accesstoken?grant_type=client_credentials → access_token
});
$client = Http::timeout(8)->connectTimeout(3)->withToken($token);

// Aturan memanggil dari Livewire:
//   - selalu try/catch → toast; kegagalan API tidak boleh jadi error 500;
//   - respons penting DISIMPAN (JSON kunjungan / tabel log) utk audit & retry;
//   - jangan panggil API di computed/render — hanya dari aksi user.
TXT,

'adopsi-tree' => <<<'TXT'
transaksi/<jalur>/
├── daftar-<jalur>/            # pendaftaran + list harian (list + actions modal)
├── daftar-<jalur>-bulanan/    # rekap bulanan
├── pelayanan-<jalur>/         # antrian dokter/perawat (RI: TIDAK ADA — dari daftar-ri)
├── emr-<jalur>/               # host modal + section per-folder + modul-dokumen/
│   ├── erm-<jalur>.blade.php
│   ├── anamnesa/  pemeriksaan/  penilaian/  diagnosa/  perencanaan/
│   └── modul-dokumen/         # form dokumen bertanda tangan
├── administrasi-<jalur>/      # pos biaya (modal, dibuka dari EMR & antrian kasir)
├── antrian-kasir-<jalur>/     # antrian kasir (wire:poll.30s)
├── antrian-apotek-<jalur>/    # antrian apotek / e-resep
├── display-pasien-<jalur>/    # kartu identitas pasien (header EMR, administrasi, dll)
├── eresep-<jalur>/            # e-resep racikan + non-racikan
└── idrg/                      # bridging casemix iDRG
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
                'alur'        => 'Alur Pasien & Routing',
                'data'        => 'Data Inti & JSON CLOB',
            ],
            'Tahapan' => [
                'pendaftaran'  => 'Pendaftaran',
                'list'         => 'List Transaksi & Performa',
                'emr'          => 'EMR (Rekam Medis)',
                'dokumen'      => 'Modul Dokumen',
                'administrasi' => 'Administrasi & Kasir',
            ],
            'Adopsi' => [
                'tambah-fitur' => 'Alur: Tambah Fitur',
                'ranjau'       => 'Ranjau Umum',
                'adopsi'       => 'Checklist Adopsi',
                'referensi'    => 'Trait & Referensi',
                'glosarium'    => 'Glosarium Istilah',
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
                    <span class="ds-body-sm" style="color:var(--muted-soft)">/ Koding Transaksi</span>
                </div>
                <div class="flex items-center gap-2">
                    <a href="{{ route('panduan-dev.koding-master') }}" wire:navigate
                        class="ds-btn ds-btn-secondary" style="height:34px; padding:6px 12px; font-size:13px">← Tutorial Master</a>
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
                            Prasyarat: <a href="{{ route('panduan-dev.koding-master') }}" wire:navigate
                                class="hover:underline" style="color:var(--primary)">Tutorial Koding Master</a><br>
                            Acuan jalur terlengkap: <span class="ds-code">transaksi/rj</span>
                        </div>
                    </div>
                </aside>

                {{-- ============ KONTEN ============ --}}
                <main style="min-width:0">

                    @include('pages.panduan-dev.koding-transaksi.koding-transaksi-dasar')

                    @include('pages.panduan-dev.koding-transaksi.koding-transaksi-pendaftaran')

                    @include('pages.panduan-dev.koding-transaksi.koding-transaksi-emr-dokumen')

                    @include('pages.panduan-dev.koding-transaksi.koding-transaksi-administrasi')

                    @include('pages.panduan-dev.koding-transaksi.koding-transaksi-tambah-fitur')

                    @include('pages.panduan-dev.koding-transaksi.koding-transaksi-penutup')

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
