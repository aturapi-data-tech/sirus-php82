<?php

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Computed;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Support\OracleLob;

new class extends Component {
    use WithPagination;

    /**
     * Modul dokumen per layanan: node JSON => label chip. Isinya = modul yang punya viewer di tab
     * "Modul Dokumen" Rekam Medis Open (tempat chip ini bermuara saat diklik). Pola sama dengan
     * satusehat_items di Daftar RJ: SEMUA modul tampil, abu-abu = belum ada, hijau = sudah terisi.
     */
    private const MODUL_DOKUMEN = [
        'RJ' => [
            'generalConsentPasienRJ' => 'General Consent', 'informConsentPasienRJ' => 'Inform Consent',
            'pengkajianPreOpRJ' => 'Pengkajian Pre-Op', 'praAnestesiRJ' => 'Pra Anestesi', 'praInduksiRJ' => 'Pra Induksi',
            'surgicalSafetyChecklistRJ' => 'Surgical Safety Checklist', 'laporanOperasiRJ' => 'Laporan Operasi',
            'laporanAnestesiRJ' => 'Laporan Anestesi', 'pascaAnestesiRJ' => 'Pasca Anestesi', 'instruksiPascaBedahRJ' => 'Instruksi Pasca Bedah',
            'kriteriaRobsonRJ' => 'Kriteria Robson',
        ],
        'UGD' => [
            'generalConsentPasienUGD' => 'General Consent', 'informConsentPasienUGD' => 'Inform Consent',
            'trfUgd' => 'Transfer UGD ke RI', 'formPenjaminanOrientasiKamar' => 'Form Penjaminan', 'suratKematianUGD' => 'Surat Kematian',
            'pelaporanEsoUGD' => 'Pelaporan ESO', 'pengkajianAkhirHayatUGD' => 'Akhir Hayat', 'penolakanObatUGD' => 'Penolakan Obat',
            'penolakanResusitasiUGD' => 'Penolakan Resusitasi', 'secondOpinionUGD' => 'Second Opinion',
            'pengkajianPreOpUGD' => 'Pengkajian Pre-Op', 'praAnestesiUGD' => 'Pra Anestesi', 'praInduksiUGD' => 'Pra Induksi',
            'surgicalSafetyChecklistUGD' => 'Surgical Safety Checklist', 'laporanOperasiUGD' => 'Laporan Operasi',
            'laporanAnestesiUGD' => 'Laporan Anestesi', 'pascaAnestesiUGD' => 'Pasca Anestesi', 'instruksiPascaBedahUGD' => 'Instruksi Pasca Bedah',
            'kriteriaRobsonUGD' => 'Kriteria Robson',
        ],
        'RI' => [
            'generalConsentPasienRI' => 'General Consent', 'informConsentPasienRI' => 'Inform Consent', 'suratKematianRI' => 'Surat Kematian',
            'formMPP' => 'Case Manager (MPP)', 'edukasiPasien' => 'Edukasi Pasien', 'edukasiPasienTerintegrasi' => 'Edukasi Terintegrasi',
            'formPindahAntarRuangRI' => 'Pindah Antar Ruang',
            'pengkajianPreOpRI' => 'Pengkajian Pre-Op', 'praAnestesiRI' => 'Pra Anestesi', 'praInduksiRI' => 'Pra Induksi',
            'surgicalSafetyChecklistRI' => 'Surgical Safety Checklist', 'laporanOperasiRI' => 'Laporan Operasi',
            'laporanAnestesiRI' => 'Laporan Anestesi', 'pascaAnestesiRI' => 'Pasca Anestesi', 'instruksiPascaBedahRI' => 'Instruksi Pasca Bedah',
            'penundaanPelayananRI' => 'Penundaan Pelayanan', 'permintaanKerohanianRI' => 'Permintaan Kerohanian',
            'penolakanObatRI' => 'Penolakan Obat', 'penolakanResusitasiRI' => 'Penolakan Resusitasi', 'pulangApsRI' => 'Pulang APS',
            'secondOpinionRI' => 'Second Opinion', 'pengkajianAkhirHayatRI' => 'Akhir Hayat', 'pelaporanEsoRI' => 'Pelaporan ESO',
            'permintaanDarahRI' => 'Permintaan Darah',
            'pengkajianAwalObstetriRI' => 'Pengkajian Awal Obstetri', 'riwayatObstetriRI' => 'Riwayat Obstetri',
            'observasiPersalinanRI' => 'Observasi Persalinan', 'laporanPersalinanRI' => 'Laporan Persalinan', 'kriteriaRobsonRI' => 'Kriteria Robson', 'indikatorScRI' => 'Indikator SC',
            'observasiNifasRI' => 'Observasi Nifas', 'pengkajianAwalGinekologiRI' => 'Pengkajian Awal Ginekologi',
            'pengkajianAwalBayiRI' => 'Pengkajian Awal Bayi', 'pengkajianNeonatalPerawatRI' => 'Pengkajian Neonatal Perawat',
            'identifikasiBayiRI' => 'Identifikasi Bayi', 'catatanTerapiNeonatalRI' => 'Catatan Terapi Neonatal',
            'surveilansPlebitisRI' => 'Surveilans Plebitis', 'surveilansIskRI' => 'Surveilans ISK', 'surveilansVapRI' => 'Surveilans VAP',
            'surveilansHapRI' => 'Surveilans HAP', 'surveilansIloRI' => 'Surveilans ILO',
        ],
    ];

    /* -------------------------
     | Filter & Pagination state
     * ------------------------- */
    public string $searchKeyword = '';
    public string $filterBulan = ''; // mm/yyyy — bulan kunjungan
    public string $filterLayanan = ''; // '' | RJ | UGD | RI
    public int $itemsPerPage = 10;

    public function mount(): void
    {
        $this->filterBulan = Carbon::now(config('app.timezone'))->format('m/Y');
    }

    public function updatedSearchKeyword(): void
    {
        $this->resetPage();
    }

    public function updatedFilterBulan(): void
    {
        $this->resetPage();
    }

    public function updatedFilterLayanan(): void
    {
        $this->resetPage();
    }

    public function updatedItemsPerPage(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset(['searchKeyword', 'filterLayanan']);
        $this->filterBulan = Carbon::now(config('app.timezone'))->format('m/Y');
        $this->itemsPerPage = 10;
        $this->resetPage();
    }

    /* -------------------------
     | Buka Rekam Medis (tab Modul Dokumen: lihat + cetak)
     * ------------------------- */
    public function bukaRekamMedis(string $layanan, int $nomorKunjungan): void
    {
        // Cabang ini menentukan komponen/tabel tujuan — whitelist + if per nilai, tanpa else bawaan.
        if (!in_array($layanan, ['RJ', 'UGD', 'RI'], true)) {
            $this->dispatch('toast', type: 'error', message: 'Jenis layanan tidak dikenal.');
            return;
        }

        if ($layanan === 'RJ') {
            $this->dispatch('cetak-rekam-medis.open', rjNo: $nomorKunjungan);
        }
        if ($layanan === 'UGD') {
            $this->dispatch('cetak-rekam-medis-ugd.open', rjNo: $nomorKunjungan);
        }
        if ($layanan === 'RI') {
            $this->dispatch('cetak-rekam-medis-ri.open', riHdrNo: $nomorKunjungan);
        }
    }

    /* -------------------------
     | Computed queries
     * ------------------------- */

    /** Rentang bulan terpilih [awal, akhir]. Isian tak sah -> bulan berjalan. Sama dgn daftar bulanan Casemix. */
    private function rentangBulan(): array
    {
        try {
            $awal = Carbon::createFromFormat('m/Y', trim($this->filterBulan))->startOfMonth();
        } catch (\Exception $e) {
            $awal = Carbon::now(config('app.timezone'))->startOfMonth();
        }

        return [$awal, (clone $awal)->endOfMonth()];
    }

    /**
     * Query satu layanan — tabel, join, dan kolom tanggalnya MENGIKUTI daftar bulanan Casemix
     * (daftar-rj-bulanan / daftar-ugd-bulanan / daftar-ri-bulanan): RJ & UGD per tanggal kunjungan,
     * RI per TANGGAL PULANG. Kolom diseragamkan supaya ketiganya bisa digabung (unionAll).
     */
    private function queryLayanan(string $layanan)
    {
        [$awal, $akhir] = $this->rentangBulan();
        $searchKeyword = trim($this->searchKeyword);
        $uppercaseKeyword = mb_strtoupper($searchKeyword);

        if ($layanan === 'RI') {
            $queryBuilder = DB::table('rstxn_rihdrs as h')
                ->leftJoin('rsmst_pasiens as p', 'p.reg_no', '=', 'h.reg_no')
                ->leftJoin('rsmst_doctors as d', 'd.dr_id', '=', 'h.dr_id')
                ->leftJoin('rsmst_klaimtypes as k', 'k.klaim_id', '=', 'h.klaim_id')
                ->leftJoin('rsmst_rooms as r', 'r.room_id', '=', 'h.room_id')
                ->leftJoin('rsmst_bangsals as b', 'b.bangsal_id', '=', 'r.bangsal_id')
                ->select([
                    DB::raw("'RI' as layanan_status"), 'h.rihdr_no as txn_no', 'h.exit_date as txn_date',
                    DB::raw("to_char(h.exit_date,'dd/mm/yyyy hh24:mi') as txn_date_display"),
                    DB::raw("to_char(h.entry_date,'dd/mm/yyyy hh24:mi') as masuk_display"),
                    'h.reg_no', 'p.reg_name', 'p.sex', 'p.address',
                    DB::raw("to_char(p.birth_date,'dd/mm/yyyy') as birth_date_display"),
                    DB::raw('cast(null as number) as no_antrian'),
                    'r.room_name as unit_desc', 'b.bangsal_name as unit_ket', 'd.dr_name',
                    'h.klaim_id', 'k.klaim_desc', 'k.klaim_status', 'h.vno_sep', 'h.erm_status',
                ])
                ->whereBetween('h.exit_date', [$awal, $akhir]);
            $kolomNomor = 'h.rihdr_no';
        } else {
            $tabel = $layanan === 'UGD' ? 'rstxn_ugdhdrs' : 'rstxn_rjhdrs';
            $queryBuilder = DB::table($tabel . ' as h')
                ->join('rsmst_pasiens as p', 'p.reg_no', '=', 'h.reg_no')
                ->leftJoin('rsmst_polis as po', 'po.poli_id', '=', 'h.poli_id')
                ->leftJoin('rsmst_doctors as d', 'd.dr_id', '=', 'h.dr_id')
                ->leftJoin('rsmst_klaimtypes as k', 'k.klaim_id', '=', 'h.klaim_id')
                ->select([
                    DB::raw("'" . ($layanan === 'UGD' ? 'UGD' : 'RJ') . "' as layanan_status"), 'h.rj_no as txn_no', 'h.rj_date as txn_date',
                    DB::raw("to_char(h.rj_date,'dd/mm/yyyy hh24:mi') as txn_date_display"),
                    DB::raw('cast(null as varchar2(20)) as masuk_display'),
                    'h.reg_no', 'p.reg_name', 'p.sex', 'p.address',
                    DB::raw("to_char(p.birth_date,'dd/mm/yyyy') as birth_date_display"),
                    'h.no_antrian',
                    'po.poli_desc as unit_desc', DB::raw('cast(null as varchar2(60)) as unit_ket'), 'd.dr_name',
                    'h.klaim_id', 'k.klaim_desc', 'k.klaim_status', 'h.vno_sep', 'h.erm_status',
                ])
                ->whereBetween('h.rj_date', [$awal, $akhir]);
            $kolomNomor = 'h.rj_no';
        }

        if ($searchKeyword !== '' && mb_strlen($searchKeyword) >= 2) {
            $queryBuilder->where(function ($subQuery) use ($searchKeyword, $uppercaseKeyword, $kolomNomor) {
                if (ctype_digit($searchKeyword)) {
                    $subQuery->orWhere($kolomNomor, 'like', "%{$searchKeyword}%");
                }

                $subQuery
                    ->orWhere(DB::raw('UPPER(h.reg_no)'), 'like', "%{$uppercaseKeyword}%")
                    ->orWhere(DB::raw('UPPER(p.reg_name)'), 'like', "%{$uppercaseKeyword}%")
                    ->orWhere(DB::raw('UPPER(h.vno_sep)'), 'like', "%{$uppercaseKeyword}%");
            });
        }

        return $queryBuilder;
    }

    #[Computed]
    public function baseQuery()
    {
        // Satu layanan -> urutan PERSIS daftar bulanan Casemix.
        if ($this->filterLayanan === 'RJ' || $this->filterLayanan === 'UGD') {
            return $this->queryLayanan($this->filterLayanan)->orderBy('d.dr_name', 'desc')->orderBy('h.rj_date', 'desc')->orderBy('h.no_antrian', 'asc');
        }
        if ($this->filterLayanan === 'RI') {
            return $this->queryLayanan('RI')->orderByDesc('h.exit_date');
        }

        // Semua layanan -> gabungan ketiganya, terbaru di atas.
        $gabungan = $this->queryLayanan('RJ')->unionAll($this->queryLayanan('UGD'))->unionAll($this->queryLayanan('RI'));

        return DB::query()->fromSub($gabungan, 'k')->orderByDesc('k.txn_date')->orderByDesc('k.txn_no');
    }

    #[Computed]
    public function rows()
    {
        // Paginate di DB — dokumen EMR (CLOB) dibaca hanya untuk baris halaman aktif.
        $paginator = $this->baseQuery()->paginate($this->itemsPerPage);

        $paginator->setCollection($paginator->getCollection()->map(fn($row) => $this->transformRow($row)));

        return $paginator;
    }

    private function transformRow($row)
    {
        $json = $this->bacaDokumen($row->layanan_status, (int) $row->txn_no);

        // MODUL DOKUMEN — status terisi PER-MODUL. Daftar (list) => banyaknya entri;
        // dokumen tunggal => 1 bila ada nilai terisi; Case Manager => formA + formB.
        $jumlahEntri = function ($node, $isi) use (&$jumlahEntri) {
            if (!is_array($isi) || $isi === []) {
                return 0;
            }
            if ($node === 'formMPP') {
                return count($isi['formA'] ?? []) + count($isi['formB'] ?? []);
            }
            if (array_is_list($isi)) {
                return count($isi);
            }
            foreach ($isi as $nilai) {
                if (is_array($nilai) ? $jumlahEntri(null, $nilai) > 0 : filled($nilai)) {
                    return 1;
                }
            }

            return 0;
        };

        // RI: dr_id di rstxn_rihdrs = dokter PENERIMA. DPJP yang sebenarnya ada di Leveling Dokter
        // (Pengkajian Awal) — ditampilkan sama seperti Daftar RI.
        $row->leveling_dokter_list = $row->layanan_status === 'RI' ? ($json['pengkajianAwalPasienRawatInap']['levelingDokter'] ?? []) : [];

        $row->modul_dokumen_items = [];
        foreach (self::MODUL_DOKUMEN[$row->layanan_status] ?? [] as $node => $label) {
            $jumlah = $jumlahEntri($node, $json[$node] ?? null);
            $row->modul_dokumen_items[] = ['label' => $label, 'jumlah' => $jumlah, 'terisi' => $jumlah > 0];
        }

        return $row;
    }

    /** Dokumen EMR satu kunjungan sebagai array; [] bila kosong/rusak. Hanya baca. */
    private function bacaDokumen(string $layanan, int $nomorKunjungan): array
    {
        $sumber = [
            'RJ' => ['rstxn_rjhdrs', 'rj_no', 'datadaftarpolirj_json'],
            'UGD' => ['rstxn_ugdhdrs', 'rj_no', 'datadaftarugd_json'],
            'RI' => ['rstxn_rihdrs', 'rihdr_no', 'datadaftarri_json'],
        ][$layanan] ?? null;

        if ($sumber === null) {
            return [];
        }

        [$tabel, $kolomKunci, $kolomJson] = $sumber;
        $mentah = DB::table($tabel)->where($kolomKunci, $nomorKunjungan)->value($kolomJson);
        $dokumen = json_decode(OracleLob::read($mentah, $tabel, $kolomKunci, $nomorKunjungan, $kolomJson), true);

        return is_array($dokumen) ? $dokumen : [];
    }
};
?>


<div>

    <x-page-title
        title="Arsip Modul Dokumen"
        subtitle="Kunjungan RJ, UGD & RI per bulan + status tiap modul dokumen — klik untuk membuka Rekam Medis (lihat & cetak)" />

    <div class="w-full h-[calc(100vh-5rem)] flex flex-col bg-surface-soft dark:bg-gray-900">
        <div class="flex flex-col flex-1 min-h-0 px-6 pt-2 pb-6">

            {{-- TOOLBAR: Search + Filter --}}
            <div
                class="sticky z-30 px-4 py-3 bg-surface-soft border-b border-hairline top-20 dark:bg-gray-900 dark:border-gray-700">

                <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                    {{-- SEARCH --}}
                    <div class="w-full lg:max-w-md">
                        <x-input-label for="searchKeyword" value="Cari Pasien" />
                        <x-text-input id="searchKeyword" type="text" wire:model.live.debounce.300ms="searchKeyword"
                            placeholder="Cari No. RM / nama pasien / no. kunjungan / no. SEP..." class="block w-full mt-1" />
                    </div>

                    {{-- RIGHT ACTIONS --}}
                    <div class="flex flex-wrap items-end justify-end gap-2">
                        <div class="w-full sm:w-40">
                            <x-input-label for="filterBulan" value="Bulan (RI = tgl pulang)" />
                            <x-text-input id="filterBulan" type="text" wire:model.live.debounce.500ms="filterBulan"
                                placeholder="mm/yyyy" maxlength="7" class="block w-full mt-1" />
                        </div>

                        <div class="w-full sm:w-52">
                            <x-input-label for="filterLayanan" value="Layanan" />
                            <x-select-input id="filterLayanan" wire:model.live="filterLayanan" class="block w-full mt-1">
                                <option value="">Semua layanan</option>
                                <option value="RJ">Rawat Jalan</option>
                                <option value="UGD">Unit Gawat Darurat</option>
                                <option value="RI">Rawat Inap</option>
                            </x-select-input>
                        </div>

                        <div class="w-full sm:w-28">
                            <x-input-label for="itemsPerPage" value="Per halaman" />
                            <x-select-input id="itemsPerPage" wire:model.live="itemsPerPage" class="block w-full mt-1">
                                <option value="5">5</option>
                                <option value="10">10</option>
                                <option value="15">15</option>
                                <option value="20">20</option>
                                <option value="50">50</option>
                            </x-select-input>
                        </div>

                        <x-toolbar-refresh-reset :label="null" />
                    </div>
                </div>
            </div>


            {{-- TABLE WRAPPER — baris kartu, sama dgn Pelayanan RJ / Pelayanan UGD / Daftar RI --}}
            <div
                class="mt-4 flex flex-col flex-1 min-h-0 bg-canvas border border-hairline shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">

                <div class="flex-1 min-h-0 overflow-x-auto overflow-y-auto rounded-t-2xl">
                    <table class="w-full min-w-full text-base -mt-3 border-separate border-spacing-y-3 table-fixed">

                        <thead class="sticky top-0 z-10 [&_th]:bg-surface-card dark:[&_th]:bg-gray-800">
                            <tr
                                class="text-sm font-semibold tracking-wide text-left text-muted uppercase dark:text-gray-300">
                                <th class="px-6 py-3 w-[26%]">Pasien</th>
                                <th class="px-6 py-3 w-[22%]">Poli / Ruang</th>
                                <th class="px-6 py-3 w-[36%]">Modul Dokumen</th>
                                <th class="px-6 py-3 w-[16%] text-center">Action</th>
                            </tr>
                        </thead>

                        <tbody>
                            @forelse($this->rows as $row)
                                @php
                                    $warnaLayanan = [
                                        'RJ' => 'bg-brand-green/10 text-brand-green dark:bg-brand-lime/15 dark:text-brand-lime',
                                        'UGD' => 'bg-error/10 text-error dark:bg-red-900/30 dark:text-red-300',
                                        'RI' => 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300',
                                    ][$row->layanan_status] ?? 'bg-surface-soft text-muted';
                                @endphp
                                <tr wire:key="arsip-dokumen-row-{{ $row->layanan_status }}-{{ $row->txn_no }}"
                                    class="transition rounded-2xl shadow-sm ring-1 ring-hairline dark:ring-gray-700
                                    {{ $row->erm_status === 'L'
                                        ? 'bg-emerald-50 dark:bg-emerald-900/10 hover:shadow-md hover:bg-brand-green/10 dark:hover:bg-emerald-900/20 border-l-4 border-emerald-500'
                                        : 'bg-canvas dark:bg-gray-900 hover:shadow-lg hover:bg-surface-soft dark:hover:bg-gray-800' }}">

                                    {{-- PASIEN --}}
                                    <td class="px-6 py-6 align-middle">
                                        <div class="flex items-center gap-4">
                                            <div class="flex flex-col items-center justify-center w-16 h-16 shrink-0 rounded-xl {{ $warnaLayanan }}">
                                                <span class="text-xl font-bold leading-none">{{ $row->layanan_status }}</span>
                                                <span class="text-[9px] font-medium mt-0.5 text-center leading-tight">
                                                    {{ filled($row->no_antrian) ? 'antrian ' . $row->no_antrian : 'layanan' }}
                                                </span>
                                            </div>
                                            <x-list.identitas-pasien :regNo="$row->reg_no" :nama="$row->reg_name" :sex="$row->sex"
                                                :tglLahir="$row->birth_date_display" :alamat="$row->address" />
                                        </div>
                                    </td>

                                    {{-- POLI / RUANG — RI mengikuti Daftar RI: bangsal, ruang, DPJP (Leveling Dokter), lalu dokter penerima --}}
                                    <td class="px-6 py-6 space-y-0.5 align-middle">
                                        @if ($row->layanan_status === 'RI')
                                            <div class="font-semibold text-blue-600 dark:text-blue-400 leading-tight">
                                                {{ $row->unit_ket ?: '-' }}
                                            </div>
                                            <div class="text-base text-ink dark:text-gray-200 leading-tight">
                                                {{ $row->unit_desc ?: '-' }}
                                            </div>
                                            @if (!empty($row->leveling_dokter_list))
                                                <div class="space-y-0.5">
                                                    <div class="text-xs text-muted-soft">DPJP:</div>
                                                    @foreach ($row->leveling_dokter_list as $dokterLeveling)
                                                        @if (!empty($dokterLeveling['drName']))
                                                            <div class="text-sm text-body dark:text-gray-200 leading-tight">
                                                                {{ $dokterLeveling['drName'] }}
                                                                @if (!empty($dokterLeveling['levelDokter']))
                                                                    <span class="text-xs text-muted">
                                                                        ({{ $dokterLeveling['levelDokter'] === 'RawatGabung' ? 'Rawat Gabung' : $dokterLeveling['levelDokter'] }})
                                                                    </span>
                                                                @endif
                                                            </div>
                                                        @endif
                                                    @endforeach
                                                </div>
                                            @endif
                                            <div class="text-xs italic text-muted dark:text-gray-400">
                                                Penerima: {{ $row->dr_name ?? '-' }}
                                            </div>
                                        @else
                                            <div class="font-semibold text-brand dark:text-emerald-400 leading-tight">
                                                {{ $row->unit_desc ?: ($row->layanan_status === 'UGD' ? 'Instalasi Gawat Darurat' : '-') }}
                                            </div>
                                            <div class="text-sm text-muted dark:text-gray-400 leading-tight">
                                                {{ $row->dr_name ?? '-' }}
                                            </div>
                                        @endif
                                        <div class="mt-0.5">
                                            <x-list.klaim-badge :status="$row->klaim_status" :desc="$row->klaim_desc" :id="$row->klaim_id" />
                                        </div>
                                        <div class="text-xs text-muted dark:text-gray-500 leading-tight">
                                            @if ($row->layanan_status === 'RI')
                                                Masuk {{ $row->masuk_display ?? '-' }} | Pulang {{ $row->txn_date_display ?? '-' }}
                                            @else
                                                {{ $row->txn_date_display ?? '-' }}
                                            @endif
                                            | No. {{ $row->txn_no }}
                                        </div>
                                        <x-list.sep-spri :sep="$row->vno_sep" />
                                    </td>

                                    {{-- MODUL DOKUMEN — status PER-MODUL, pola chip Satu Sehat di Daftar RJ.
                                         Abu-abu = belum ada, hijau = sudah terisi. Klik chip mana pun = buka Rekam Medis. --}}
                                    <td class="px-6 py-6 align-middle">
                                        <div class="flex flex-wrap items-center gap-1">
                                            @foreach ($row->modul_dokumen_items as $modulItem)
                                                <button type="button"
                                                    wire:click="bukaRekamMedis('{{ $row->layanan_status }}', {{ (int) $row->txn_no }})"
                                                    title="{{ $modulItem['label'] }} — {{ $modulItem['terisi'] ? 'sudah terisi (' . $modulItem['jumlah'] . ')' : 'belum ada' }} (klik untuk buka Rekam Medis)"
                                                    class="inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded border text-[9px] font-semibold leading-none transition {{ $modulItem['terisi'] ? 'bg-brand-green/10 text-brand-green border-brand-green/30 dark:bg-brand-lime/15 dark:text-brand-lime dark:border-brand-lime/30' : 'bg-surface-soft text-muted-soft border-hairline hover:bg-surface-strong hover:text-body dark:bg-gray-800 dark:text-gray-500 dark:border-gray-700 dark:hover:bg-gray-700' }}">
                                                    @if ($modulItem['terisi'])
                                                        <svg class="w-2 h-2 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="4"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                                                    @endif
                                                    {{ $modulItem['label'] }}@if ($modulItem['jumlah'] > 1) ({{ $modulItem['jumlah'] }})@endif
                                                </button>
                                            @endforeach
                                        </div>
                                    </td>

                                    {{-- ACTION --}}
                                    <td class="px-6 py-6 text-center align-middle">
                                        {{-- Ikon papan-klip = ikon "Rekam Medis" di menu titik-3 Daftar RI. whitespace-nowrap: label tetap sebaris.
                                             x-outline-button (hijau lembut): jelas AKTIF — x-secondary-button (putih/abu) terbaca seperti tombol mati. --}}
                                        <x-outline-button type="button" class="whitespace-nowrap"
                                            wire:click="bukaRekamMedis('{{ $row->layanan_status }}', {{ (int) $row->txn_no }})">
                                            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                                            </svg>
                                            Rekam Medis
                                        </x-outline-button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-6 py-16">
                                        <div class="flex flex-col items-center justify-center gap-3">
                                            <p class="text-base font-medium text-muted dark:text-gray-400">Tidak ada kunjungan pada bulan ini.</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- PAGINATION STICKY di bawah card --}}
                <div
                    class="sticky bottom-0 z-10 px-4 py-3 bg-canvas border-t border-hairline rounded-b-2xl dark:bg-gray-900 dark:border-gray-700">
                    {{ $this->rows->links() }}
                </div>
            </div>


            {{-- Rekam Medis Open per layanan — tab "Modul Dokumen" di dalamnya = lihat + cetak --}}
            <livewire:pages::components.rekam-medis.rj.cetak-rekam-medis.cetak-rekam-medis-open
                wire:key="arsip-dokumen.rj.cetak-rekam-medis-open" />
            <livewire:pages::components.rekam-medis.ugd.cetak-rekam-medis.cetak-rekam-medis-open
                wire:key="arsip-dokumen.ugd.cetak-rekam-medis-open" />
            <livewire:pages::components.rekam-medis.ri.cetak-rekam-medis.cetak-rekam-medis-open
                wire:key="arsip-dokumen.ri.cetak-rekam-medis-open" />
        </div>
    </div>
</div>
