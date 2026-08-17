<?php
// ╔══════════════════════════════════════════════════════════════════════╗
// ║  PEMANTAUAN RUJUKAN KELUAR — sisi FASKES PERUJUK (SRBK/SATUSEHAT)    ║
// ╚══════════════════════════════════════════════════════════════════════╝
//
// Route: /rujukan/keluar → pages::transaksi.rujukan.rujukan-keluar.rujukan-keluar
//
// Kembaran /rujukan/masuk, dibalik arahnya. Di sana kita OWNER Task dan
// menjawab; di sini kita REQUESTER dan menunggu dijawab.
//
// Kenapa layar sendiri, bukan buka EMR lagi: jawaban RS tujuan datang MENYUSUL,
// bisa berjam-jam setelah pasien ditangani. Menyuruh petugas membuka EMR pasien
// satu per satu untuk mengecek berarti ia harus ingat siapa saja yang dirujuk
// hari itu. Di sini semuanya berjajar dalam satu daftar.
//
// ── DUA TAB, karena rujukan keluar RS ini memang lahir dari DUA MEKANISME ──
//
// Tab "Ranap & Gawat Darurat" — jalur FHIR LANGSUNG (RJ→IGD/Ranap, UGD, RI).
//   Sumber: API SATUSEHAT `Task?requester=<org kita>`. Punya siklus persetujuan,
//   jadi ada status Menunggu/Disetujui/Ditolak.
//
// Tab "Rawat Jalan (BPJS)" — rujukan poli ke RS lain, diorkestrasi BPJS
//   (vclaim-sisrute-rest). TIDAK membentuk Task FHIR dan TIDAK punya siklus
//   persetujuan: sekali Insert berhasil, rujukan langsung terbit. Karena itu
//   sumbernya DB LOKAL (node `rujukanKompetensi` di datadaftarpolirj_json) dan
//   kolomnya nomor rujukan, bukan status jawaban. Jangan disamakan bentuknya
//   dengan tab sebelah — perbedaan ini nyata, bukan kekurangan tampilan.
//
// Tiap panggilan SATUSEHAT terekam di web_log_status (payload + response mentah)
// lewat SatuSehatRujukanTrait. Karena tiap muat tab FHIR = 1 panggilan API (dan
// kuota staging pernah habis → 429), muat ulang otomatis sengaja DEFAULT MATI.

use Livewire\Component;
use Livewire\Attributes\Computed;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;
use App\Support\OracleLob;
use App\Http\Traits\SATUSEHAT\SatuSehatRujukanTrait;

new class extends Component {
    use SatuSehatRujukanTrait;

    public string $tabAktif = 'fhir'; // fhir | rj

    /* ── Tab FHIR (SATUSEHAT) ────────────────────────────────────── */

    /** Baris mentah hasil parse Bundle Task+CarePlan. */
    public array $daftarRujukan = [];

    /**
     * Rujukan yang DISEMBUNYIKAN SATUSEHAT karena consent — tidak punya baris
     * di tabel, jadi satu-satunya jejaknya di layar adalah spanduk peringatan.
     * ['Task/<id>' => alasan]
     */
    public array $rujukanTersensor = [];

    // Dua-duanya default SEMUA, sama dengan kotak masuk: layar ini dibuka untuk
    // melihat nasib semua rujukan yang sudah dikirim, bukan cuma yang menggantung.
    public string $filterStatus = ''; // '' (semua) | menunggu | accepted | rejected
    public string $filterJalur = ''; // '' (semua) | ranap | igd

    public bool $muatOtomatis = false;
    public string $waktuMuat = '';
    public string $pesanGangguan = '';
    public bool $sudahPernahMuat = false;

    /* ── Tab RJ (BPJS/SISRUTE, sumber DB lokal) ──────────────────── */

    public array $daftarRJ = [];
    public string $dariTanggal = '';
    public string $sampaiTanggal = '';
    public bool $sudahMuatRJ = false;
    public string $pesanRJ = '';

    /* ── Bersama ─────────────────────────────────────────────────── */

    public string $searchKeyword = '';

    public function mount(): void
    {
        $hariIni = Carbon::now(env('APP_TIMEZONE'));
        $this->dariTanggal = $hariIni->copy()->startOfMonth()->format('Y-m-d');
        $this->sampaiTanggal = $hariIni->format('Y-m-d');

        $this->muatRujukan();
    }

    /**
     * Tab RJ menyapu CLOB, jadi TIDAK ikut dimuat saat halaman dibuka —
     * baru ditarik kalau tabnya benar-benar dilihat, dan cukup sekali.
     */
    public function setTab(string $tab): void
    {
        $this->tabAktif = in_array($tab, ['fhir', 'rj'], true) ? $tab : 'fhir';
        $this->searchKeyword = '';

        if ($this->tabAktif === 'rj' && !$this->sudahMuatRJ) {
            $this->muatRujukanRJ();
        }
    }

    /**
     * Tarik daftar rujukan keluar dari SATUSEHAT. Gangguan pusat = kondisi normal:
     * tampilkan pesan ramah + tombol coba lagi, jangan kosongkan daftar yang sudah
     * tampil supaya petugas tidak kehilangan konteks.
     */
    public function muatRujukan(): void
    {
        $this->sudahPernahMuat = true;
        $hasil = $this->rujukanTaskByRequester();

        if ($hasil['code'] < 200 || $hasil['code'] >= 300) {
            $this->pesanGangguan = 'Gagal membaca daftar rujukan keluar [' . $hasil['code'] . '] — ' . $this->ringkasError($hasil['body']);
            return;
        }

        $this->pesanGangguan = '';
        $baris = $this->rujukanParsePermintaanMasuk($hasil['body']);
        $this->rujukanTersensor = $this->rujukanPermintaanTersensor($hasil['body']);

        // Nama RS tujuan tidak ikut di Task; ambil sekali per organisasi (di-cache 1 hari).
        $namaOrganisasi = [];
        foreach ($baris as $index => $satu) {
            $orgId = $satu['tujuanOrgId'];
            if ($orgId !== '' && !array_key_exists($orgId, $namaOrganisasi)) {
                $namaOrganisasi[$orgId] = $this->rujukanNamaOrganisasi($orgId);
            }
            $baris[$index]['tujuanNama'] = $namaOrganisasi[$orgId] ?? '';
        }

        $this->daftarRujukan = $baris;
        $this->waktuMuat = Carbon::now(env('APP_TIMEZONE'))->format('d/m/Y H:i:s');
    }

    /**
     * Rujukan RJ (BPJS) — dari DB lokal, bukan API. Tidak ada endpoint BPJS
     * "daftar rujukan keluar saya", jadi satu-satunya sumber adalah jejak yang
     * kita simpan sendiri saat Insert berhasil.
     *
     * Penyaringan dua lapis, sengaja:
     *   1. rentang tanggal → membatasi baris yang CLOB-nya perlu disentuh sama
     *      sekali (rstxn_rjhdrs besar; menyapu seluruhnya tidak sehat),
     *   2. INSTR '"rujukanKompetensi"' → petik kandidat. Tanda kutip penutup itu
     *      penting: tanpa itu ia ikut mencocoki "rujukanKompetensiFhir", yaitu
     *      node rujukan RJ→IGD/Ranap yang sudah tampil di tab sebelah.
     * Baru setelah itu JSON-nya dibuka dan disaring pada nomor rujukan SATUSEHAT —
     * penanda kiriman BERHASIL (draft yang belum terkirim menyimpan hasil kosong).
     */
    public function muatRujukanRJ(): void
    {
        $this->sudahMuatRJ = true;
        $this->pesanRJ = '';

        try {
            $rows = DB::table('rstxn_rjhdrs as h')
                ->join('rsmst_pasiens as p', 'p.reg_no', '=', 'h.reg_no')
                ->leftJoin('rsmst_doctors as d', 'd.dr_id', '=', 'h.dr_id')
                ->leftJoin('rsmst_polis as pol', 'pol.poli_id', '=', 'h.poli_id')
                ->select([
                    'h.rj_no',
                    'h.reg_no',
                    'p.reg_name',
                    DB::raw("to_char(h.rj_date, 'dd/mm/yyyy') as tgl_kunjungan"),
                    'd.dr_name',
                    'pol.poli_desc',
                    'h.datadaftarpolirj_json',
                ])
                ->whereRaw("h.rj_date >= to_date(?, 'yyyy-mm-dd')", [$this->dariTanggal])
                ->whereRaw("h.rj_date < to_date(?, 'yyyy-mm-dd') + 1", [$this->sampaiTanggal])
                ->whereRaw("INSTR(h.datadaftarpolirj_json, '\"rujukanKompetensi\"') > 0")
                ->orderByDesc('h.rj_date')
                ->get();
        } catch (\Throwable $e) {
            $this->pesanRJ = 'Gagal membaca data rujukan rawat jalan: ' . Str::limit($e->getMessage(), 180);
            $this->daftarRJ = [];
            return;
        }

        $baris = [];
        foreach ($rows as $row) {
            $json = OracleLob::read($row->datadaftarpolirj_json ?? null, 'rstxn_rjhdrs', 'rj_no', $row->rj_no, 'datadaftarpolirj_json');
            if ($json === '') {
                continue;
            }

            $data = json_decode($json, true);
            if (!is_array($data)) {
                continue;
            }

            $node = $data['rujukanKompetensi'] ?? [];
            $hasil = $node['hasil'] ?? [];
            if (trim((string) ($hasil['noRujukanSatuSehat'] ?? '')) === '') {
                continue; // draft / percobaan gagal — bukan rujukan yang terbit
            }

            $baris[] = [
                'rjNo' => (string) $row->rj_no,
                'regNo' => (string) $row->reg_no,
                'pasienNama' => (string) $row->reg_name,
                'tglKunjungan' => (string) $row->tgl_kunjungan,
                'poliAsal' => (string) ($row->poli_desc ?? ''),
                'dokterAsal' => (string) ($row->dr_name ?? ''),
                'noRujukan' => (string) ($hasil['noRujukan'] ?? ''),
                'noRujukanSatuSehat' => (string) ($hasil['noRujukanSatuSehat'] ?? ''),
                'serviceRequestId' => (string) ($hasil['serviceRequestId'] ?? ''),
                'tglRujukan' => (string) ($hasil['tglRujukan'] ?? ''),
                'tujuanNama' => (string) ($hasil['tujuanNama'] ?? ''),
                'tujuanPpk' => (string) ($hasil['tujuanPpk'] ?? ''),
                'tujuanSatuSehat' => (string) ($hasil['tujuanSatuSehat'] ?? ''),
                'dikirimOleh' => (string) ($hasil['dikirimOleh'] ?? ''),
                'dikirimPada' => (string) ($hasil['dikirimPada'] ?? ''),
                'diagnosaKode' => (string) ($node['kodeDiagnosa'] ?? ''),
                'diagnosaDesc' => (string) ($node['diagnosaDesc'] ?? ''),
                'poliRujukan' => (string) ($node['poliRujukan'] ?? ($node['kodeSpesialis'] ?? '')),
            ];
        }

        $this->daftarRJ = $baris;
        $this->waktuMuat = Carbon::now(env('APP_TIMEZONE'))->format('d/m/Y H:i:s');
    }

    public function bukaDetail(int $indeks): void
    {
        $baris = $this->daftarRujukan[$indeks] ?? null;
        if (!$baris) {
            return;
        }

        $this->dispatch('rujukan-keluar-actions.open', rujukan: $baris);
    }

    /**
     * Filter dikerjakan di memori — sekali tarik, banyak saring.
     * Menambah filter TIDAK menambah panggilan API.
     */
    #[Computed]
    public function rows(): array
    {
        $kataKunci = trim(strtolower($this->searchKeyword));

        return array_values(
            array_filter($this->daftarRujukan, function (array $baris) use ($kataKunci) {
                if ($this->filterStatus === 'menunggu' && !$this->menunggu($baris)) {
                    return false;
                }
                if (in_array($this->filterStatus, ['accepted', 'rejected'], true) && $baris['keputusan'] !== $this->filterStatus) {
                    return false;
                }
                if ($this->filterJalur !== '' && $baris['jalur'] !== $this->filterJalur) {
                    return false;
                }
                if ($kataKunci === '') {
                    return true;
                }

                $gabungan = strtolower(implode(' ', [$baris['pasienNama'], $baris['pasienId'], $baris['noPermintaan'], $baris['tujuanNama'] ?? '', $baris['tujuanOrgId'], $baris['layananNama']]));

                return str_contains($gabungan, $kataKunci);
            }),
        );
    }

    #[Computed]
    public function rowsRJ(): array
    {
        $kataKunci = trim(strtolower($this->searchKeyword));
        if ($kataKunci === '') {
            return $this->daftarRJ;
        }

        return array_values(
            array_filter($this->daftarRJ, function (array $baris) use ($kataKunci) {
                $gabungan = strtolower(implode(' ', [$baris['pasienNama'], $baris['regNo'], $baris['noRujukan'], $baris['noRujukanSatuSehat'], $baris['tujuanNama'], $baris['tujuanPpk'], $baris['diagnosaDesc']]));

                return str_contains($gabungan, $kataKunci);
            }),
        );
    }

    /** Ringkasan jumlah per status — dihitung dari data mentah, bukan hasil filter. */
    #[Computed]
    public function rekap(): array
    {
        $rekap = ['menunggu' => 0, 'accepted' => 0, 'rejected' => 0, 'batal' => 0];
        foreach ($this->daftarRujukan as $baris) {
            if ($baris['statusTask'] === 'cancelled') {
                $rekap['batal']++;
            } elseif ($this->menunggu($baris)) {
                $rekap['menunggu']++;
            } elseif ($baris['keputusan'] === 'accepted') {
                $rekap['accepted']++;
            } elseif ($baris['keputusan'] === 'rejected') {
                $rekap['rejected']++;
            }
        }

        return $rekap;
    }

    /** Belum dijawab = belum ada output keputusan DAN belum dibatalkan. */
    public function menunggu(array $baris): bool
    {
        return $baris['keputusan'] === '' && $baris['statusTask'] !== 'cancelled';
    }

    public function waktuTampil(string $iso): string
    {
        if ($iso === '') {
            return '-';
        }

        try {
            return Carbon::parse($iso)->timezone(env('APP_TIMEZONE'))->format('d/m/Y H:i');
        } catch (\Throwable $e) {
            return $iso;
        }
    }

    /** Ambil pesan terpakai dari OperationOutcome / body apa pun tanpa membanjiri toast. */
    public function ringkasError($body): string
    {
        if (is_string($body)) {
            return Str::limit($body, 180);
        }
        if (is_array($body)) {
            $pesan = $body['issue'][0]['details']['text'] ?? ($body['issue'][0]['diagnostics'] ?? ($body['message'] ?? null));
            if ($pesan) {
                return Str::limit((string) $pesan, 180);
            }
            return Str::limit(json_encode($body), 180);
        }

        return 'Tidak ada keterangan.';
    }
};
?>

<div>
    <x-page-title title="Pemantauan Rujukan Keluar"
        subtitle="Rujukan yang RS kita kirim ke faskes lain — Ranap & Gawat Darurat lewat SATUSEHAT, Rawat Jalan lewat BPJS" />

    {{-- Pemicu muat ulang berkala dipisah jadi elemen sendiri: directive di dalam
         atribut adalah jebakan compiler Blade yang sudah pernah menggigit repo ini.
         Hanya tab FHIR yang perlu — data RJ ada di DB sendiri, tidak berubah diam-diam. --}}
    @if ($muatOtomatis && $tabAktif === 'fhir')
        <div wire:poll.60s="muatRujukan" class="hidden"></div>
    @endif

    <div class="w-full h-[calc(100vh-5rem)] flex flex-col bg-surface-soft dark:bg-gray-800">
        <div class="flex flex-col flex-1 min-h-0 px-6 pt-2 pb-6">

            {{-- TAB --}}
            <x-tabs variant="underline">
                <x-tab :active="$tabAktif === 'fhir'" color="blue" wire:click="setTab('fhir')">
                    Ranap &amp; Gawat Darurat (SATUSEHAT)
                </x-tab>
                <x-tab :active="$tabAktif === 'rj'" color="emerald" wire:click="setTab('rj')">
                    Rawat Jalan (BPJS)
                </x-tab>
            </x-tabs>

            {{-- TOOLBAR --}}
            <div
                class="sticky z-30 px-4 py-3 bg-surface-soft border-b border-hairline top-20 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex flex-wrap items-end gap-3">

                    <div class="w-full sm:flex-1">
                        <x-input-label value="Pencarian" class="sr-only" />
                        <div class="relative mt-1">
                            <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                <svg class="w-4 h-4 text-muted" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                </svg>
                            </div>
                            <x-text-input wire:model.live.debounce.300ms="searchKeyword" class="block w-full pl-10"
                                placeholder="{{ $tabAktif === 'rj' ? 'Cari nama pasien / No. RM / nomor rujukan / RS tujuan...' : 'Cari nama pasien / RS tujuan / nomor permintaan...' }}" />
                        </div>
                    </div>

                    @if ($tabAktif === 'fhir')
                        <div class="w-full sm:w-auto">
                            <x-input-label value="Layanan Diminta" />
                            <x-select-input wire:model.live="filterJalur" class="w-full mt-1 sm:w-56">
                                <option value="">Semua Layanan</option>
                                <option value="ranap">Rawat Inap</option>
                                <option value="igd">Gawat Darurat</option>
                            </x-select-input>
                        </div>

                        <div class="w-full sm:w-auto">
                            <x-input-label value="Status" />
                            <x-select-input wire:model.live="filterStatus" class="w-full mt-1 sm:w-52">
                                <option value="">Semua Status</option>
                                <option value="menunggu">Menunggu Jawaban</option>
                                <option value="accepted">Disetujui</option>
                                <option value="rejected">Ditolak</option>
                            </x-select-input>
                        </div>
                    @else
                        {{-- Rentang tanggal membatasi baris yang CLOB-nya disentuh — bukan
                             sekadar kenyamanan, tapi penjaga performa kueri ini. --}}
                        <div class="w-full sm:w-auto">
                            <x-input-label value="Kunjungan Dari" />
                            <x-text-input type="date" wire:model="dariTanggal" class="w-full mt-1 sm:w-44" />
                        </div>
                        <div class="w-full sm:w-auto">
                            <x-input-label value="Sampai" />
                            <x-text-input type="date" wire:model="sampaiTanggal" class="w-full mt-1 sm:w-44" />
                        </div>
                    @endif

                    <div class="flex items-center gap-3 ml-auto">
                        @if ($tabAktif === 'fhir')
                            <div
                                title="Tiap muat ulang = 1 panggilan API SATUSEHAT. Nyalakan hanya saat menunggu jawaban.">
                                <x-toggle wire:model.live="muatOtomatis" :trueValue="true" :falseValue="false"
                                    label="Muat ulang tiap 60 detik" />
                            </div>

                            <x-outline-button type="button" wire:click="muatRujukan" wire:loading.attr="disabled"
                                wire:target="muatRujukan" class="whitespace-nowrap">
                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                    stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                </svg>
                                <span wire:loading.remove wire:target="muatRujukan">Muat Ulang</span>
                                <span wire:loading wire:target="muatRujukan">Memuat...</span>
                            </x-outline-button>
                        @else
                            <x-outline-button type="button" wire:click="muatRujukanRJ" wire:loading.attr="disabled"
                                wire:target="muatRujukanRJ" class="whitespace-nowrap">
                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                    stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                </svg>
                                <span wire:loading.remove wire:target="muatRujukanRJ">Tampilkan</span>
                                <span wire:loading wire:target="muatRujukanRJ">Memuat...</span>
                            </x-outline-button>
                        @endif
                    </div>

                </div>
            </div>

            {{-- REKAP + JEJAK WAKTU MUAT --}}
            <div
                class="mt-4 px-4 py-3 bg-canvas border border-hairline shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                <div class="flex flex-wrap items-center gap-2">
                    @if ($tabAktif === 'fhir')
                        @php $rekap = $this->rekap; @endphp
                        <span class="mr-1 text-sm font-semibold text-muted dark:text-gray-300">Rujukan keluar:</span>
                        <x-badge variant="warning">Menunggu: {{ $rekap['menunggu'] }}</x-badge>
                        <x-badge variant="success">Disetujui: {{ $rekap['accepted'] }}</x-badge>
                        <x-badge variant="danger">Ditolak: {{ $rekap['rejected'] }}</x-badge>
                        @if ($rekap['batal'] > 0)
                            <x-badge variant="gray">Dibatalkan: {{ $rekap['batal'] }}</x-badge>
                        @endif
                    @else
                        <span class="mr-1 text-sm font-semibold text-muted dark:text-gray-300">Rujukan rawat
                            jalan:</span>
                        <x-badge variant="success">Terbit: {{ count($daftarRJ) }}</x-badge>
                        <span class="text-sm text-muted dark:text-gray-400">
                            Rujukan poli lewat BPJS terbit seketika — tidak menunggu persetujuan RS tujuan.
                        </span>
                    @endif

                    @if ($waktuMuat !== '')
                        <span class="ml-auto text-xs text-muted-soft">Terakhir dimuat {{ $waktuMuat }}</span>
                    @endif
                </div>
            </div>

            @if ($tabAktif === 'fhir')

                {{-- RUJUKAN TERSEMBUNYI — tidak punya baris di tabel, jadi tanpa spanduk ini
                     petugas tidak punya cara apa pun untuk tahu ada rujukan yang tidak terbaca.
                     Di arah keluar ini mestinya tidak pernah terjadi (CarePlan & Task kita
                     sendiri), jadi kalau muncul, itu justru temuan yang layak dilaporkan. --}}
                @if (count($rujukanTersensor) > 0)
                    <div
                        class="mt-4 px-4 py-3 border rounded-2xl bg-warning-tint border-amber-200 dark:bg-amber-900/20 dark:border-amber-800">
                        <div class="flex flex-wrap items-start gap-x-3 gap-y-1">
                            <span class="text-sm font-semibold text-warning-deep dark:text-amber-200">
                                {{ count($rujukanTersensor) }} rujukan tidak dapat ditampilkan
                            </span>
                            <span class="text-sm text-warning-deep dark:text-amber-200">
                                SATUSEHAT menyembunyikannya karena aturan consent/privasi — padahal rujukan ini
                                kita sendiri yang mengirim. Statusnya terpaksa dicek lewat EMR pasiennya.
                            </span>
                            <span class="w-full text-xs text-warning-deep dark:text-amber-200">
                                Referensi: {{ implode(', ', array_keys($rujukanTersensor)) }}
                            </span>
                        </div>
                    </div>
                @endif

                {{-- GANGGUAN PUSAT — bukan edge case, tampilkan apa adanya + tombol coba lagi --}}
                @if ($pesanGangguan !== '')
                    <div
                        class="mt-4 px-4 py-3 border rounded-2xl bg-error-tint border-red-200 dark:bg-red-900/20 dark:border-red-800">
                        <div class="flex flex-wrap items-center gap-3">
                            <span class="text-sm font-semibold text-error-deep dark:text-red-200">
                                Layanan SATUSEHAT sedang tidak bisa dihubungi
                            </span>
                            <span class="text-sm text-error-deep dark:text-red-200">{{ $pesanGangguan }}</span>
                            <x-outline-button type="button" wire:click="muatRujukan" class="ml-auto">
                                Coba Lagi
                            </x-outline-button>
                        </div>
                    </div>
                @endif

                {{-- TABEL — RANAP & GAWAT DARURAT --}}
                <div
                    class="mt-4 flex flex-col flex-1 min-h-0 bg-canvas border border-hairline shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">

                    <div class="flex-1 min-h-0 overflow-x-auto overflow-y-auto rounded-t-2xl">
                        <table class="min-w-full text-base -mt-3 border-separate border-spacing-y-3">
                            <thead class="sticky top-0 z-10 [&_th]:bg-surface-card dark:[&_th]:bg-gray-800">
                                <tr
                                    class="text-sm font-semibold tracking-wide text-left text-muted uppercase dark:text-gray-300">
                                    <th class="px-6 py-3 min-w-[260px]">Pasien</th>
                                    <th class="px-6 py-3 min-w-[260px]">RS Tujuan</th>
                                    <th class="px-6 py-3 min-w-[240px]">Layanan Diminta</th>
                                    <th class="px-6 py-3 min-w-[160px]">Waktu Dikirim</th>
                                    <th class="px-6 py-3 min-w-[150px]">Jawaban RS Tujuan</th>
                                    <th class="w-40 px-6 py-3 text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($this->rows as $indeks => $baris)
                                    <tr class="transition bg-canvas dark:bg-gray-900
                                           rounded-2xl shadow-sm ring-1 ring-hairline dark:ring-gray-700
                                           hover:shadow-lg hover:bg-surface-soft dark:hover:bg-gray-800"
                                        wire:key="rujukan-keluar-{{ $baris['taskId'] }}">

                                        @php
                                            // CarePlan disensor SATUSEHAT → nama, layanan & jalur kosong berjamaah.
                                            $barisDiblokir = (bool) ($baris['rencanaDiblokir'] ?? false);
                                        @endphp

                                        <td class="px-6 py-4 rounded-l-2xl">
                                            <div class="font-semibold text-ink dark:text-gray-100">
                                                {{ $baris['pasienNama'] !== ''
                                                    ? $baris['pasienNama']
                                                    : ($barisDiblokir
                                                        ? '(tersembunyi — consent belum ada)'
                                                        : '(nama tidak terbaca)') }}
                                            </div>
                                            <div class="text-sm text-muted dark:text-gray-400">
                                                IHS: {{ $baris['pasienId'] !== '' ? $baris['pasienId'] : '-' }}
                                            </div>
                                            <div class="text-xs text-muted-soft">No. Permintaan:
                                                {{ $baris['noPermintaan'] !== '' ? $baris['noPermintaan'] : '-' }}
                                            </div>
                                        </td>

                                        <td class="px-6 py-4">
                                            <div class="font-medium text-ink dark:text-gray-100">
                                                {{ $baris['tujuanNama'] !== '' ? $baris['tujuanNama'] : '(nama RS belum terbaca)' }}
                                            </div>
                                            <div class="text-sm text-muted dark:text-gray-400">
                                                Org ID: {{ $baris['tujuanOrgId'] !== '' ? $baris['tujuanOrgId'] : '-' }}
                                            </div>
                                            @if ($baris['dokterPerujuk'] !== '')
                                                <div class="text-xs text-muted-soft">DPJP perujuk:
                                                    {{ $baris['dokterPerujuk'] }}</div>
                                            @endif
                                        </td>

                                        <td class="px-6 py-4">
                                            @if ($baris['jalur'] === 'ranap')
                                                <x-badge variant="info">Rawat Inap</x-badge>
                                            @elseif ($baris['jalur'] === 'igd')
                                                <x-badge variant="danger">Gawat Darurat</x-badge>
                                            @elseif ($barisDiblokir)
                                                <x-badge variant="warning">Jalur tersembunyi</x-badge>
                                            @else
                                                <x-badge variant="gray">Layanan tidak dikenali</x-badge>
                                            @endif
                                            <div class="mt-1 text-sm text-muted dark:text-gray-400">
                                                {{ $baris['layananNama'] !== ''
                                                    ? $baris['layananNama']
                                                    : ($barisDiblokir
                                                        ? 'Detail diblokir SATUSEHAT (consent)'
                                                        : '-') }}
                                                @if ($baris['layananKode'] !== '')
                                                    <span class="text-muted-soft">({{ $baris['layananKode'] }})</span>
                                                @endif
                                            </div>
                                        </td>

                                        <td class="px-6 py-4 text-sm text-muted dark:text-gray-300">
                                            {{ $this->waktuTampil($baris['waktu']) }}
                                        </td>

                                        <td class="px-6 py-4">
                                            @if ($baris['statusTask'] === 'cancelled')
                                                <x-badge variant="gray">Dibatalkan</x-badge>
                                            @elseif ($baris['keputusan'] === 'accepted')
                                                <x-badge variant="success">Disetujui</x-badge>
                                            @elseif ($baris['keputusan'] === 'rejected')
                                                <x-badge variant="danger">Ditolak</x-badge>
                                            @else
                                                <x-badge variant="warning">Menunggu Jawaban</x-badge>
                                            @endif
                                        </td>

                                        <td class="px-6 py-4 text-center rounded-r-2xl">
                                            <x-outline-button type="button" wire:click="bukaDetail({{ $indeks }})">
                                                Lihat Detail
                                            </x-outline-button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="px-6 py-10 text-center text-muted dark:text-gray-400">
                                            @if (!$sudahPernahMuat)
                                                Daftar rujukan keluar belum dimuat.
                                            @elseif ($pesanGangguan !== '')
                                                Daftar tidak dapat dibaca — lihat keterangan gangguan di atas.
                                            @elseif (count($daftarRujukan) > 0)
                                                Tidak ada rujukan yang cocok dengan filter.
                                            @else
                                                Belum ada rujukan Rawat Inap / Gawat Darurat yang dikirim RS ini.
                                                Rujukan poli ke RS lain ada di tab Rawat Jalan (BPJS).
                                            @endif
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

            @else

                {{-- GAGAL BACA DB --}}
                @if ($pesanRJ !== '')
                    <div
                        class="mt-4 px-4 py-3 border rounded-2xl bg-error-tint border-red-200 dark:bg-red-900/20 dark:border-red-800">
                        <div class="flex flex-wrap items-center gap-3">
                            <span class="text-sm font-semibold text-error-deep dark:text-red-200">
                                Data rujukan rawat jalan tidak dapat dibaca
                            </span>
                            <span class="text-sm text-error-deep dark:text-red-200">{{ $pesanRJ }}</span>
                            <x-outline-button type="button" wire:click="muatRujukanRJ" class="ml-auto">
                                Coba Lagi
                            </x-outline-button>
                        </div>
                    </div>
                @endif

                {{-- TABEL — RAWAT JALAN (BPJS) --}}
                <div
                    class="mt-4 flex flex-col flex-1 min-h-0 bg-canvas border border-hairline shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">

                    <div class="flex-1 min-h-0 overflow-x-auto overflow-y-auto rounded-t-2xl">
                        <table class="min-w-full text-base -mt-3 border-separate border-spacing-y-3">
                            <thead class="sticky top-0 z-10 [&_th]:bg-surface-card dark:[&_th]:bg-gray-800">
                                <tr
                                    class="text-sm font-semibold tracking-wide text-left text-muted uppercase dark:text-gray-300">
                                    <th class="px-6 py-3 min-w-[260px]">Pasien</th>
                                    <th class="px-6 py-3 min-w-[240px]">Poli Asal</th>
                                    <th class="px-6 py-3 min-w-[260px]">RS Tujuan</th>
                                    <th class="px-6 py-3 min-w-[240px]">Diagnosa</th>
                                    <th class="px-6 py-3 min-w-[260px]">Nomor Rujukan</th>
                                    <th class="px-6 py-3 min-w-[170px]">Dikirim</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($this->rowsRJ as $baris)
                                    <tr class="transition bg-canvas dark:bg-gray-900
                                           rounded-2xl shadow-sm ring-1 ring-hairline dark:ring-gray-700
                                           hover:shadow-lg hover:bg-surface-soft dark:hover:bg-gray-800"
                                        wire:key="rujukan-rj-{{ $baris['rjNo'] }}">

                                        <td class="px-6 py-4 rounded-l-2xl">
                                            <div class="font-semibold text-ink dark:text-gray-100">
                                                {{ $baris['pasienNama'] !== '' ? $baris['pasienNama'] : '-' }}
                                            </div>
                                            <div class="text-sm text-muted dark:text-gray-400">
                                                No. RM: {{ $baris['regNo'] }}
                                            </div>
                                            <div class="text-xs text-muted-soft">Kunjungan
                                                {{ $baris['tglKunjungan'] }}</div>
                                        </td>

                                        <td class="px-6 py-4">
                                            <div class="font-medium text-ink dark:text-gray-100">
                                                {{ $baris['poliAsal'] !== '' ? $baris['poliAsal'] : '-' }}
                                            </div>
                                            @if ($baris['dokterAsal'] !== '')
                                                <div class="text-sm text-muted dark:text-gray-400">
                                                    {{ $baris['dokterAsal'] }}</div>
                                            @endif
                                            @if ($baris['poliRujukan'] !== '')
                                                <div class="text-xs text-muted-soft">Poli tujuan:
                                                    {{ $baris['poliRujukan'] }}</div>
                                            @endif
                                        </td>

                                        <td class="px-6 py-4">
                                            <div class="font-medium text-ink dark:text-gray-100">
                                                {{ $baris['tujuanNama'] !== '' ? $baris['tujuanNama'] : '-' }}
                                            </div>
                                            <div class="text-sm text-muted dark:text-gray-400">
                                                PPK: {{ $baris['tujuanPpk'] !== '' ? $baris['tujuanPpk'] : '-' }}
                                            </div>
                                            @if ($baris['tujuanSatuSehat'] !== '')
                                                <div class="text-xs text-muted-soft">SATUSEHAT:
                                                    {{ $baris['tujuanSatuSehat'] }}</div>
                                            @endif
                                        </td>

                                        <td class="px-6 py-4 text-sm text-muted dark:text-gray-300">
                                            <span class="font-mono">{{ $baris['diagnosaKode'] !== '' ? $baris['diagnosaKode'] : '-' }}</span>
                                            @if ($baris['diagnosaDesc'] !== '')
                                                <div>{{ $baris['diagnosaDesc'] }}</div>
                                            @endif
                                        </td>

                                        <td class="px-6 py-4 text-sm">
                                            <div class="text-muted dark:text-gray-400">BPJS:
                                                <span class="font-mono font-semibold text-ink dark:text-gray-100">
                                                    {{ $baris['noRujukan'] !== '' ? $baris['noRujukan'] : '-' }}</span>
                                            </div>
                                            <div class="text-muted dark:text-gray-400">SATUSEHAT:
                                                <span class="font-mono font-semibold text-ink dark:text-gray-100">
                                                    {{ $baris['noRujukanSatuSehat'] }}</span>
                                            </div>
                                            @if ($baris['tglRujukan'] !== '')
                                                <div class="text-xs text-muted-soft">Tgl rujukan:
                                                    {{ $baris['tglRujukan'] }}</div>
                                            @endif
                                        </td>

                                        <td class="px-6 py-4 text-sm text-muted dark:text-gray-300 rounded-r-2xl">
                                            <div>{{ $baris['dikirimPada'] !== '' ? $baris['dikirimPada'] : '-' }}</div>
                                            @if ($baris['dikirimOleh'] !== '')
                                                <div class="text-xs text-muted-soft">oleh {{ $baris['dikirimOleh'] }}
                                                </div>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="px-6 py-10 text-center text-muted dark:text-gray-400">
                                            @if (!$sudahMuatRJ)
                                                Pilih rentang tanggal lalu klik Tampilkan.
                                            @elseif ($pesanRJ !== '')
                                                Data tidak dapat dibaca — lihat keterangan di atas.
                                            @elseif (count($daftarRJ) > 0)
                                                Tidak ada rujukan yang cocok dengan pencarian.
                                            @else
                                                Tidak ada rujukan rawat jalan yang terbit pada rentang tanggal ini.
                                            @endif
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

            @endif

        </div>
    </div>

    <livewire:pages::transaksi.rujukan.rujukan-keluar.rujukan-keluar-actions />
</div>
