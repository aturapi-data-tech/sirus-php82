<?php

/*
|--------------------------------------------------------------------------
| LOV Diagnosa ICD-10 — picker diagnosa standar (SATU-satunya)
|--------------------------------------------------------------------------
|
| Semua field diagnosa di aplikasi ini memakai komponen ini: EMR RJ/UGD/RI,
| SEP/VClaim, coder iDRG, coder INACBG, dan master. JANGAN membuat autocomplete
| diagnosa sendiri — aturan kode mana yang boleh dipilih ada di sini, sekali,
| supaya tidak ada jalur yang lolos aturan.
|
| Dokumen lengkap: docs/diagnosa-architecture.md §2 (skill: diagnosa-flow) — di sana
| ada tabel SELURUH 12 call site beserta status blockHeader/blockIm/blockNonPrimary-nya.
| Perbarui tabel itu setiap menambah pemakaian LOV baru:
|   grep -rn "lov.diagnosa.lov-diagnosa" resources/views --include=*.blade.php -A3
|
|
| STANDAR PEMAKAIAN
| -----------------
| Tag komponen di bawah sengaja ditulis TANPA tanda kurang-dari: Blade
| mengompilasi tag komponen walau berada di dalam komentar, dan itu memicu
| error "Undefined variable $component" saat halaman dirender.
|
|   livewire:lov.diagnosa.lov-diagnosa
|       label="Diagnosa *"
|       target="rjFormDiagnosaVclaim"       <- WAJIB, unik per form
|       :initialDiagnosaId="$diagnosaId"    <- opsional, mode edit (reactive)
|       :blockNonPrimary="true"             <- opsional, tolak kode accpdx='N'
|       :blockIm="true"                     <- opsional, khusus coder INACBG
|       :blockHeader="false"                <- opsional, izinkan kode kategori
|                                              (default true = ditolak)
|       :disabled="$isFormLocked"           <- opsional, form terkunci
|       wire:key="lov-diag-{$noTransaksi}"  <- WAJIB kalau ada >1 LOV di halaman
|
| Parent menangkap hasilnya lewat dua event bernama sesuai `target`:
|
|   #[On('lov.selected.rjFormDiagnosaVclaim')]
|   public function onDiagnosa(string $target, array $payload): void { ... }
|
|   #[On('lov.cleared.rjFormDiagnosaVclaim')]
|   public function onDiagnosaCleared(string $target): void { ... }
|
| Isi $payload — 3 identitas + 4 flag master, jadi parent TIDAK perlu query
| ulang ke master (lihat jebakan icdx kembar di bawah):
|
|   ['diag_id' => '...', 'diag_desc' => '...', 'icdx' => '...',
|    'valid_code' => int, 'accpdx' => 'Y|N', 'asterisk' => int, 'im' => int]
|
| Aturan memilih field mana yang dikirim ke mana:
| - ke sistem eksternal (BPJS SEP, E-Klaim iDRG/INACBG) → `icdx`
| - untuk join / simpan internal (rstxn_*dtls) → `diag_id`
|
|
| CARA KERJA
| ----------
| 1. mount() → loadInitialData(): kalau `initialDiagnosaId` diisi, baris dicari
|    by `diag_id` DULU, baru fallback by `icdx`, lalu tampil sebagai terpilih
|    (mode edit). Prop-nya #[Reactive] supaya ikut berubah saat parent berpindah
|    record tanpa perlu remount.
| 2. updatedSearch(): minimal 2 karakter, UPPER + LIKE di `diag_id`/`icdx`/
|    `diag_desc`, limit 50 baris, urut `icdx` lalu `diag_desc`. Tidak ada
|    auto-select walau ketikan cocok persis — pilihan tetap ditentukan pengguna.
| 3. Dropdown menampilkan SEMUA baris hasil pencarian, TERMASUK yang terblokir:
|    barisnya merah + badge alasannya. Ini disengaja — koder harus tahu kodenya
|    memang ADA tapi tidak boleh dipakai, bukan mengira "tidak ditemukan" lalu
|    mencari-cari kode lain.
| 4. choose() menjalankan 3 guard, masing-masing membatalkan pilihan
|    dengan toast error (lihat GUARD di bawah). Baru setelah lolos semuanya,
|    dispatchSelected() menyimpan state terpilih + melempar `lov.selected.*`.
| 5. clearSelected() (tombol "Ubah") mengosongkan pilihan + melempar
|    `lov.cleared.*`. Saat `disabled` = true tombolnya hilang dan clear ditolak.
| 6. Navigasi papan tuts: panah atas/bawah menggeser `selectedIndex`, Enter
|    memilih baris tersorot, Escape mengosongkan daftar. Event `lov-scroll`
|    dipakai Alpine untuk menggulirkan baris tersorot ke area tampak.
|
| Guard-nya ditegakkan di choose() (server), BUKAN cuma di tampilan: badge dan
| warna merah hanya penanda visual, sedangkan klik baris tetap sampai ke server.
|
|
| GUARD — kode mana yang tidak boleh dipilih
| ------------------------------------------
| DUA URUSAN BERBEDA, jangan tertukar — ini sumber kerancuan nama prop lama
| (`primaryOnly`, sudah di-rename jadi `blockNonPrimary`):
|
|   boleh DIPILIH atau tidak      -> ditentukan DI SINI, lewat prop block*
|   jadi PRIMARY atau SECONDARY   -> ditentukan komponen pemakai, di add() /
|                                    setKategori() (lookup accpdx, jaga
|                                    single-Primary invariant)
|
| Jadi `blockNonPrimary="false"` TIDAK berarti "kode ini jadi primer". Artinya
| cuma: kode yang tak boleh primer tetap BOLEH DIPILIH — nanti komponennya yang
| menaruhnya sebagai Secondary.
|
| Ketiga prop dibaca satu arah: true = kode itu DITOLAK, false = boleh dipilih.
| Masing-masing memblokir satu properti kode di master:
|
|   blockHeader     -> valid_code = 0
|   blockIm         -> im = 1
|   blockNonPrimary -> accpdx = 'N'
|
| Empat flag di RSMST_MSTDIAGS (kelola di /master/diagnosa). Jumlah baris di
| bawah = kondisi master saat dokumen ini ditulis, sekadar gambaran skalanya:
|
|   valid_code = 0   4.192 baris   kode block/parent header (mis. A74 "Other
|                                  diseases caused by chlamydiae", E11, K29),
|                                  bukan kode leaf. -> GUARD 1, aktif bila
|                                  `blockHeader` = true (DEFAULT).
|
|   accpdx = 'N'    27.558 baris   tidak boleh jadi diagnosa PRIMER (boleh jadi
|                                  sekunder). -> GUARD 2, aktif bila
|                                  `blockNonPrimary` = true.
|
|   asterisk = 1       852 baris   kode asterisk, wajib dipasangkan dengan
|                                  etiologi (dagger). Semuanya (852/852) sudah
|                                  ber-accpdx='N', jadi aturan "asterisk tak
|                                  boleh primer" otomatis ikut GUARD 2 — di
|                                  sini hanya diberi badge. Validasi pasangan
|                                  dagger-asterisk BELUM ada.
|
|   im = 1           1.416 baris   Indonesian Modification. Dipakai grouper
|                                  iDRG, tetapi DITOLAK E-Klaim INACBG.
|                                  -> GUARD 3, aktif bila `blockIm` = true.
|
| SEMUA guard bisa dilepas per-field. Coder INACBG memang melepas ketiganya
| (`:blockHeader="false"` + `:blockIm="false"`, tanpa `blockNonPrimary`) karena di sana
| penentu akhir adalah `validcode` dari respons E-Klaim, bukan flag lokal — tiap
| baris coder sudah menampilkan badge Valid / Tidak Valid / IM tidak berlaku.
|
| Kenapa GUARD 3 perlu terpisah: 1.413 dari 1.416 kode IM ber-valid_code=1 dan
| accpdx='Y', jadi lolos GUARD 1 & 2. Tanpa `blockIm`, kode IM masuk coder
| INACBG dan baru ketahuan salah setelah klaim dikirim lalu dibalas
| validcode=0 oleh E-Klaim. Jangan nyalakan di LOV iDRG — di sana kode IM
| memang yang dipakai. Penandaannya di App\Support\Terminologi\KodeIm (dua sumber:
| kolom `im` DAN deskripsi berakhiran "(IM)").
|
|
| JEBAKAN
| -------
| - 288 icdx KEMBAR di master (baris seed E-Klaim + baris legacy). Baris legacy
|   sering ber-valid_code=0/accpdx='N' default. JANGAN pernah lookup flag by
|   `icdx` memakai value()/first() — bisa kena baris yang salah. Karena itu
|   payload LOV sudah membawa keempat flag; kalau parent tetap harus memeriksa
|   ulang, pakai pola exists() (lihat docs §4) atau KodeIm::adalahKode().
| - Baris kembar `*X`: 266 icdx punya DUA baris — baris seed Kemenkes (mis.
|   diag_id `I10`, valid_code=1) dan baris legacy (diag_id `I10X`, valid_code=0
|   karena tidak ada di berkas referensi). Di dropdown keduanya muncul dengan
|   icdx sama, satu merah satu tidak. Jadi kalau kode LENGKAP seperti I10 / J00 /
|   K30 terasa "terblokir", solusinya memilih baris yang tidak merah — BUKAN
|   melepas `blockHeader`. 261 dari 266 baris legacy itu ber-diag_id akhiran "X".
| - Lookup by `diag_id` saja aman (PK unik).
| - Beberapa LOV di satu halaman WAJIB `wire:key` berbeda, kalau tidak Livewire
|   menganggapnya komponen yang sama dan isian keduanya saling tertukar.
|
*/

use Livewire\Component;
use App\Support\Terminologi\KodeIm;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Reactive;

new class extends Component {
    /** target untuk membedakan LOV ini dipakai di form mana */
    public string $target = 'default';

    /** UI */
    public string $label = 'Cari Diagnosa (ICD 10)';
    public string $placeholder = 'Ketik kode/nama diagnosa...';

    /** state */
    public string $search = '';
    public array $options = [];
    public bool $isOpen = false;
    public int $selectedIndex = 0;

    /** selected state (buat mode selected + edit) */
    public ?array $selected = null;

    /**
     * Mode edit: parent bisa kirim diag_id yang sudah tersimpan.
     * Cukup kirim initialDiagnosaId, sisanya akan di-load dari DB.
     */
    #[Reactive]
    public ?string $initialDiagnosaId = null;

    /**
     * Mode disabled: jika true, tombol "Ubah" akan hilang saat selected.
     * Berguna untuk form yang sudah selesai/tidak boleh diedit.
     */
    public bool $disabled = false;

    /**
     * Tampilkan info tambahan di dropdown
     */
    public bool $showAdditionalInfo = true;

    /**
     * Kalau true, kode dgn accpdx='N' (tidak boleh jadi diagnosa primer) ditolak.
     *
     * Dulu bernama `primaryOnly` — di-rename karena namanya terbaca seperti urusan
     * PENEMPATAN (jadi primer / sekunder), padahal prop ini murni soal boleh-tidaknya
     * kode DIPILIH. Penempatan kategori tetap urusan komponen pemakai.
     *
     * GUARD 2. Status pemakaian: BELUM dipakai satu pun dari 12 call site. Semua
     * konsumen menegakkan aturan primer di server, bukan lewat prop ini:
     * - EMR diagnosis   : lookup `accpdx` by `diag_id` lalu tentukan kategori
     * - coder iDRG/INACBG: pola exists() (icdx OR diag_id) di add() & setKategori()
     *
     * Sebabnya field diagnosa di aplikasi ini SATU kotak untuk primer + sekunder:
     * kode accpdx='N' tetap boleh masuk, hanya dipaksa jadi Secondary. Prop ini baru
     * berguna kalau nanti ada field yang KHUSUS diagnosa primer (mis. diagAwal SEP
     * terpisah) — di situ menolak sejak pemilihan lebih jelas daripada memaksa
     * kategori setelah tersimpan.
     */
    public bool $blockNonPrimary = false;

    /**
     * Kalau true, kode Indonesian Modification (master `im`=1 atau deskripsi
     * berakhiran "(IM)") DITOLAK saat dipilih.
     *
     * GUARD 3. Status pemakaian: aktif di 3 call site coder iDRG (langkah koding
     * utama); mati di 9 lainnya termasuk coder INACBG yang justru dibuka penuh
     * sebagai lapis override.
     *
     * Perlu terpisah dari GUARD 1: 1.413 dari 1.416 kode IM ber-`valid_code`=1 DAN
     * accpdx='Y', jadi guard valid_code maupun accpdx tidak menangkapnya.
     *
     * E-Klaim menolak kode IM di KEDUA bridging. Buktinya komponen iDRG sendiri
     * menampilkan badge "Kode IM tidak diakui" + pesan "Kode IM tidak dikenali
     * e-klaim. Coba kode ICD-10 standar tanpa suffix IM." (kirim-diagnosa-idrg
     * baris 497 & 517). Menyalakan prop ini memindahkan penolakan itu ke depan —
     * saat memilih — supaya koder tidak perlu bolak-balik mengirim klaim dulu.
     *
     * Catatan: prop ini hanya menutup PEMILIHAN MANUAL. Jalur sync (syncFromEmr di
     * iDRG, syncFromIdrg di INACBG) tidak lewat add(), jadi kode IM dari EMR masih
     * bisa masuk daftar coder — memang disengaja, supaya kelihatan lalu diganti;
     * penandanya badge merah di kolom Keterangan.
     *
     * Penandanya App\Support\Terminologi\KodeIm.
     */
    public bool $blockIm = false;

    /**
     * Kalau true (DEFAULT), kode block/kategori (`valid_code`=0) ditolak.
     *
     * GUARD 1. Status pemakaian: menutup di 6 dari 12 call site (EMR diagnosis +
     * coder iDRG), DIBUKA di 6 lainnya (SEP/VClaim + coder INACBG). Pembaginya:
     * siapa penilai akhir kode — kalau ada sistem luar yang menolak & pesannya
     * sampai ke pengguna (E-Klaim di INACBG, BPJS di SEP), guard dibuka; kalau
     * tidak ada penilai luar (EMR) atau penolakan mahal karena harus kirim klaim
     * dulu (coder iDRG), guard ditutup. Tabel lengkap: docs §2.
     *
     * Konteksnya: 210.311 baris diagnosa EMR RJ lama memakai kode kategori seperti
     * E11 "Non-insulin-dependent diabetes mellitus" atau K29 "Gastritis and
     * duodenitis". Kode yang benar adalah anaknya (E11.9, K29.7).
     *
     * Catatan: kalau yang terasa terblokir justru kode LENGKAP (I10, J00, K30),
     * itu bukan kasus header — lihat bagian JEBAKAN soal baris kembar `*X`.
     */
    public bool $blockHeader = true;

    public function mount(): void
    {
        $this->loadInitialData();
    }

    protected function loadInitialData(): void
    {
        if (empty($this->initialDiagnosaId)) {
            return;
        }

        // Cek berdasarkan diag_id terlebih dahulu
        $row = DB::table('rsmst_mstdiags')->where('diag_id', $this->initialDiagnosaId)->first();

        // Jika tidak ditemukan, cek berdasarkan icdx
        if (!$row) {
            $row = DB::table('rsmst_mstdiags')->where('icdx', $this->initialDiagnosaId)->first();
        }
        if ($row) {
            $this->setSelectedFromRow($row);
        }
    }

    protected function setSelectedFromRow($row): void
    {
        $this->selected = [
            'diag_id' => (string) $row->diag_id,
            'diag_desc' => (string) ($row->diag_desc ?? ''),
            'icdx' => (string) ($row->icdx ?? ''),
            'valid_code' => (int) ($row->valid_code ?? 0),
            'accpdx' => (string) ($row->accpdx ?? 'N'),
            'asterisk' => (int) ($row->asterisk ?? 0),
            'im' => (int) ($row->im ?? 0),
        ];
    }

    public function updatedSearch(): void
    {
        // kalau sudah selected, jangan cari lagi
        if ($this->selected !== null) {
            return;
        }

        $keyword = trim($this->search);

        // minimal 2 char
        if (mb_strlen($keyword) < 2) {
            $this->closeAndResetList();
            return;
        }

        // Selalu tampilkan dropdown — user pilih manual (no auto-select on exact)
        $upperKeyword = mb_strtoupper($keyword);

        // Tampilkan SEMUA code (termasuk valid_code=0 / accpdx='N').
        // Code invalid akan ditandai visual + diblok di choose() dengan toast error.
        $query = DB::table('rsmst_mstdiags')
            ->where(function ($q) use ($upperKeyword) {
                $q->whereRaw('UPPER(diag_id) LIKE ?', ["%{$upperKeyword}%"])
                    ->orWhereRaw('UPPER(icdx) LIKE ?', ["%{$upperKeyword}%"])
                    ->orWhereRaw('UPPER(diag_desc) LIKE ?', ["%{$upperKeyword}%"]);
            })
            ->orderBy('icdx')
            ->orderBy('diag_desc');

        $rows = $query->limit(50)->get();

        $this->options = $rows
            ->map(function ($row) {
                return $this->mapRowToOption($row);
            })
            ->toArray();

        $this->isOpen = count($this->options) > 0;
        $this->selectedIndex = 0;

        if ($this->isOpen) {
            $this->dispatch('lov-scroll', id: $this->getId(), index: $this->selectedIndex);
        }
    }

    protected function mapRowToPayload($row): array
    {
        return [
            'diag_id' => (string) $row->diag_id,
            'diag_desc' => (string) ($row->diag_desc ?? ''),
            'icdx' => (string) ($row->icdx ?? ''),
            'valid_code' => (int) ($row->valid_code ?? 0),
            'accpdx' => (string) ($row->accpdx ?? 'N'),
            'asterisk' => (int) ($row->asterisk ?? 0),
            'im' => (int) ($row->im ?? 0),
        ];
    }

    protected function mapRowToOption($row): array
    {
        $diagId = (string) $row->diag_id;
        $icdx = (string) ($row->icdx ?? '');
        $diagDesc = (string) ($row->diag_desc ?? '');

        $displayCode = $icdx ?: $diagId;
        $displayText = $diagDesc ?: '-';

        return [
            // payload
            'diag_id' => $diagId,
            'diag_desc' => $diagDesc,
            'icdx' => $icdx,
            'valid_code' => (int) ($row->valid_code ?? 0),
            'accpdx' => (string) ($row->accpdx ?? 'N'),
            'asterisk' => (int) ($row->asterisk ?? 0),
            'im' => (int) ($row->im ?? 0),

            // UI
            'label' => $displayCode ? "{$displayCode} - {$displayText}" : $displayText,
            'code' => $displayCode,
            'description' => $diagDesc,
            'hint' => "Kode: {$displayCode}",
        ];
    }

    public function clearSelected(): void
    {
        // Jika disabled, tidak bisa clear selected
        if ($this->disabled) {
            return;
        }

        $this->selected = null;
        $this->resetLov();

        // Dispatch event ke parent bahwa selection di-clear
        $this->dispatch('lov.cleared.' . $this->target, target: $this->target);
    }

    public function close(): void
    {
        $this->isOpen = false;
    }

    public function resetLov(): void
    {
        $this->reset(['search', 'options', 'isOpen', 'selectedIndex']);
    }

    public function selectNext(): void
    {
        if (!$this->isOpen || count($this->options) === 0) {
            return;
        }

        $this->selectedIndex = ($this->selectedIndex + 1) % count($this->options);
        $this->dispatch('lov-scroll', id: $this->getId(), index: $this->selectedIndex);
    }

    public function selectPrevious(): void
    {
        if (!$this->isOpen || count($this->options) === 0) {
            return;
        }

        $this->selectedIndex--;
        if ($this->selectedIndex < 0) {
            $this->selectedIndex = count($this->options) - 1;
        }

        $this->dispatch('lov-scroll', id: $this->getId(), index: $this->selectedIndex);
    }

    public function choose(int $index): void
    {
        if (!isset($this->options[$index])) {
            return;
        }

        $opt = $this->options[$index];
        $code = $opt['icdx'] ?: $opt['diag_id'];

        // Guard 1: blok code invalid (parent/category placeholder).
        if ($this->blockHeader && (int) ($opt['valid_code'] ?? 0) !== 1) {
            $this->dispatch('toast', type: 'error', message: "Kode {$code} tidak valid (parent/category). Pilih kode leaf/spesifik.");
            return;
        }

        // Guard 2: kalau LOV ini untuk diagnosa primer, blok kode dgn accpdx='N'.
        if ($this->blockNonPrimary && ($opt['accpdx'] ?? 'N') !== 'Y') {
            $this->dispatch('toast', type: 'error', message: "Kode {$code} tidak boleh dipakai sebagai diagnosa primer (accpdx='N'), jadi tidak bisa dipilih di field ini.");
            return;
        }

        // Guard 3: kalau LOV ini untuk INACBG, blok kode Indonesian Modification.
        if ($this->blockIm && KodeIm::adalah($opt)) {
            $this->dispatch('toast', type: 'error', message: "Kode {$code} adalah kode IM (Indonesian Modification) — tidak berlaku di INACBG. Pilih kode ICD-10 non-IM.");
            return;
        }

        $payload = [
            'diag_id' => $opt['diag_id'] ?? '',
            'diag_desc' => $opt['diag_desc'] ?? '',
            'icdx' => $opt['icdx'] ?? '',
            'valid_code' => (int) ($opt['valid_code'] ?? 0),
            'accpdx' => (string) ($opt['accpdx'] ?? 'N'),
            'asterisk' => (int) ($opt['asterisk'] ?? 0),
            'im' => (int) ($opt['im'] ?? 0),
        ];

        $this->dispatchSelected($payload);
    }

    public function chooseHighlighted(): void
    {
        $this->choose($this->selectedIndex);
    }

    /* helpers */

    protected function closeAndResetList(): void
    {
        $this->options = [];
        $this->isOpen = false;
        $this->selectedIndex = 0;
    }

    protected function dispatchSelected(array $payload): void
    {
        // set selected -> UI berubah jadi nama + tombol ubah
        $this->selected = $payload;

        // bersihkan mode search
        $this->search = '';
        $this->options = [];
        $this->isOpen = false;
        $this->selectedIndex = 0;

        // emit ke parent
        $this->dispatch('lov.selected.' . $this->target, target: $this->target, payload: $payload);
    }

    public function updatedInitialDiagnosaId($value): void
    {
        // Reset state
        $this->selected = null;
        $this->search = '';
        $this->options = [];
        $this->isOpen = false;

        if (empty($value)) {
            return;
        }

        $row = DB::table('rsmst_mstdiags')->where('diag_id', $value)->first()
            ?? DB::table('rsmst_mstdiags')->where('icdx', $value)->first();

        if ($row) {
            $this->setSelectedFromRow($row);
        }
    }

    /**
     * Get display text for selected item
     */
    public function getSelectedDisplayProperty(): string
    {
        if (!$this->selected) {
            return '';
        }

        $code = $this->selected['icdx'] ?: $this->selected['diag_id'];
        $desc = $this->selected['diag_desc'] ?? '';

        return $code ? "{$code} - {$desc}" : $desc;
    }
};
?>

<x-lov.dropdown :id="$this->getId()" :isOpen="$isOpen" :selectedIndex="$selectedIndex" close="close">
    <x-input-label :value="$label" />

    <div class="relative mt-1">
        @if ($selected === null)
            {{-- Mode cari --}}
            @if (!$disabled)
                <x-text-input type="text" class="block w-full" :placeholder="$placeholder" wire:model.live.debounce.250ms="search"
                    wire:keydown.escape.prevent="resetLov" wire:keydown.arrow-down.prevent="selectNext"
                    wire:keydown.arrow-up.prevent="selectPrevious" wire:keydown.enter.prevent="chooseHighlighted"
                    autocomplete="off" />
            @else
                <x-text-input type="text" class="block w-full bg-gray-100 cursor-not-allowed dark:bg-gray-800"
                    :placeholder="$placeholder" disabled />
            @endif
        @else
            {{-- Mode selected --}}
            <div class="flex items-center gap-2">
                <div class="flex-1">
                    <x-text-input type="text" class="block w-full bg-gray-50 dark:bg-gray-800" :value="$this->selectedDisplay"
                        disabled />
                </div>

                @if (!$disabled)
                    <x-secondary-button type="button" wire:click="clearSelected" class="px-4 whitespace-nowrap">
                        Ubah
                    </x-secondary-button>
                @endif
            </div>
        @endif

        {{-- dropdown hanya saat mode cari dan tidak disabled --}}
        @if ($isOpen && $selected === null && !$disabled)
            <div
                class="absolute z-50 w-full mt-2 overflow-hidden bg-white border border-gray-200 shadow-lg rounded-xl dark:bg-gray-900 dark:border-gray-700">
                <ul class="overflow-y-auto divide-y divide-gray-100 max-h-72 dark:divide-gray-800">
                    @foreach ($options as $index => $option)
                        @php
                            $isHeaderCode = (int) ($option['valid_code'] ?? 0) !== 1;
                            $isInvalid = $isHeaderCode && $blockHeader;
                            $isBlockedNonPrimary = $blockNonPrimary && ($option['accpdx'] ?? 'N') !== 'Y';
                            $isBlockedIm = $blockIm && App\Support\Terminologi\KodeIm::adalah($option);
                            $isBlocked = $isInvalid || $isBlockedNonPrimary || $isBlockedIm;
                            $rowClass = $isBlocked
                                ? 'bg-red-50 dark:bg-red-900/10 cursor-not-allowed'
                                : '';
                            $textClass = $isBlocked
                                ? 'text-red-700 dark:text-red-300'
                                : 'text-gray-900 dark:text-gray-100';
                        @endphp
                        <li wire:key="lov-diag-{{ $option['diag_id'] ?? $index }}-{{ $index }}"
                            x-ref="lovItem{{ $index }}" class="{{ $rowClass }}">
                            <x-lov.item wire:click="choose({{ $index }})" :active="$index === $selectedIndex">
                                <div class="flex items-start justify-between gap-2">
                                    <div class="font-semibold {{ $textClass }} flex-1">
                                        {{ $option['label'] ?? '-' }}
                                    </div>
                                    <div class="flex flex-wrap items-center gap-1 shrink-0">
                                        @if ($isHeaderCode && !$blockHeader)
                                            <span class="px-1.5 py-0.5 text-[10px] font-bold tracking-wide uppercase bg-amber-100 text-amber-800 rounded dark:bg-amber-900/30 dark:text-amber-300"
                                                title="Kode kategori/block — bukan kode leaf; tidak berlaku untuk klaim">KATEGORI</span>
                                        @endif
                                        @if (($option['accpdx'] ?? 'N') === 'N' && !$isInvalid)
                                            <span class="px-1.5 py-0.5 text-[10px] font-bold tracking-wide uppercase bg-amber-100 text-amber-800 rounded dark:bg-amber-900/30 dark:text-amber-300"
                                                title="Tidak boleh sebagai diagnosa primer">!PDX</span>
                                        @endif
                                        @if (!empty($option['asterisk']))
                                            <span class="px-1.5 py-0.5 text-[10px] font-bold tracking-wide uppercase bg-purple-100 text-purple-800 rounded dark:bg-purple-900/30 dark:text-purple-300"
                                                title="Kode asterisk — wajib pair dengan etiologi (dagger)">★</span>
                                        @endif
                                        @if ($isBlockedIm)
                                            <span class="px-1.5 py-0.5 text-[10px] font-bold tracking-wide uppercase bg-red-100 text-red-800 rounded dark:bg-red-900/30 dark:text-red-300"
                                                title="Kode Indonesian Modification — ditolak E-Klaim INACBG, pilih kode non-IM">iM ✕</span>
                                        @elseif (!empty($option['im']))
                                            <span class="px-1.5 py-0.5 text-[10px] font-bold tracking-wide uppercase bg-emerald-100 text-emerald-800 rounded dark:bg-emerald-900/30 dark:text-emerald-300"
                                                title="Kode spesifik iDRG Indonesian Modification">iM</span>
                                        @endif
                                    </div>
                                </div>

                                @if (!empty($option['hint']))
                                    <div class="text-xs text-gray-500 dark:text-gray-400">
                                        {{ $option['hint'] }}
                                    </div>
                                @endif
                            </x-lov.item>
                        </li>
                    @endforeach
                </ul>

                @if (mb_strlen(trim($search)) >= 2 && count($options) === 0)
                    <div class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
                        Data diagnosa tidak ditemukan.
                    </div>
                @endif
            </div>
        @endif
    </div>
</x-lov.dropdown>
