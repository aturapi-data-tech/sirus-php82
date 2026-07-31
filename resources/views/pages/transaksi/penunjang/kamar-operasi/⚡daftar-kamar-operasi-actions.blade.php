<?php

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;
use App\Http\Traits\Txn\Ri\EmrRITrait;
use App\Support\KamarOperasiTarif;

new class extends Component {
    use WithRenderVersioningTrait;
    // EmrRITrait dipakai untuk lockRIRow + appendAdminLogRI (audit log ke kunjungan induk).
    use EmrRITrait;

    public array $renderVersions = [];
    protected array $renderAreas = ['kamar-operasi-actions-modal'];

    /* =======================
     | State
     * ======================= */
    public string $okReg = '';
    public string $activeTab = 'Tindakan'; // Tindakan | BahanAlat | Omlop
    public array $headerData = [];

    /** Nilai 11 pos tarif; key = kolom rstxn_oks. Null = belum pernah diisi. */
    public array $tarif = [];

    /** Jasa on call (TIDAK ditagihkan ke pasien). */
    public array $oncall = [];

    /**
     * Baris siap-tampil untuk template.
     *
     * Zona `@php` di template Volt TIDAK mewarisi `use` dari blok class ini, jadi
     * menyebut KamarOperasiTarif di markup akan memaksa FQCN. Semua pemetaan +
     * lookup nama petugas karena itu diselesaikan di sini, dan template tinggal
     * memutar array — sesuai aturan repo (skill naming-conventions §2).
     */
    public array $crewRows = [];
    public array $posLainnyaRows = [];
    public array $oncallRows = [];

    /** Detail per tab. */
    public array $rowsTindakan = [];
    public array $rowsBahanAlat = [];
    public array $rowsOmlop = [];

    /** Crew terpilih — dipakai sebagai initial value LOV. */
    public ?string $drId = null;
    public ?string $drIdOk = null;
    public ?string $empIdAsistopr = null;
    public ?string $empIdAsistanes = null;
    public ?string $empIdInstrument = null;
    public ?string $empIdChangeanesdoc = null;

    /** Form tambah baris detail. */
    public ?string $formTindakanAccdocId = null;
    public string $formTindakanDesc = '';
    public ?int $formTindakanPrice = null;

    public ?string $formProductId = null;
    public string $formProductName = '';
    public string $formProductQty = '1';
    public ?int $formProductPrice = null;

    public ?string $formOmlopEmpId = null;
    public string $formOmlopName = '';

    /** Kunjungan rawat inap induk sudah tidak aktif → transfer biaya tidak boleh. */
    public bool $indukTerkunci = false;
    public string $indukTerkunciSebab = '';

    public int $sumTotal = 0;
    public int $sumOncall = 0;

    /**
     * Status 'A' = Proses Transaksi (bebas edit). Selain itu terkunci.
     * Default TERKUNCI: selama header belum termuat (modal belum dibuka, atau
     * pemuatan ditolak role gate) markup tarif tidak boleh tampil sebagai input.
     */
    public bool $isFormLocked = true;

    public array $EmrMenuKamarOperasi = [['ermMenuId' => 'Tindakan', 'ermMenuName' => 'Tindakan Operasi'], ['ermMenuId' => 'BahanAlat', 'ermMenuName' => 'Bahan dan Alat'], ['ermMenuId' => 'Omlop', 'ermMenuName' => 'Crew OM LOP']];

    public function mount(): void
    {
        $this->registerAreas($this->renderAreas);
    }

    /* =======================
     | Role gate
     * ======================= */
    private function isAllowedRole(): bool
    {
        $user = auth()->user();

        return $user ? $user->hasAnyRole(['Admin', 'Manager Umum', 'Supervisor Penunjang', 'Perawat']) : false;
    }

    /** Batal transaksi = eskalasi ke atasan, sejalan dengan modul Laboratorium. */
    private function isAllowedBatal(): bool
    {
        $user = auth()->user();

        return $user ? $user->hasAnyRole(['Admin', 'Supervisor Penunjang']) : false;
    }

    /* =======================
     | Open / Close
     * ======================= */
    #[On('kamar-operasi-actions.open')]
    public function openActions(string $okReg): void
    {
        if (!$this->isAllowedRole()) {
            $this->dispatch('toast', type: 'error', message: 'Anda tidak memiliki akses ke modul Kamar Operasi.');
            return;
        }

        $this->okReg = $okReg;
        $this->activeTab = 'Tindakan';
        $this->resetFormTambah();

        $this->findData();

        $this->incrementVersion('kamar-operasi-actions-modal');
        $this->dispatch('open-modal', name: 'kamar-operasi-actions');
    }

    public function closeActions(): void
    {
        $this->dispatch('close-modal', name: 'kamar-operasi-actions');
        $this->reset(['okReg', 'headerData', 'tarif', 'oncall', 'rowsTindakan', 'rowsBahanAlat', 'rowsOmlop', 'indukTerkunci', 'indukTerkunciSebab', 'sumTotal', 'sumOncall', 'isFormLocked', 'drId', 'drIdOk', 'empIdAsistopr', 'empIdAsistanes', 'empIdInstrument', 'empIdChangeanesdoc', 'crewRows', 'posLainnyaRows', 'oncallRows']);
        $this->resetFormTambah();
    }

    private function resetFormTambah(): void
    {
        $this->reset(['formTindakanAccdocId', 'formTindakanDesc', 'formTindakanPrice', 'formProductId', 'formProductName', 'formProductPrice', 'formOmlopEmpId', 'formOmlopName']);
        $this->formProductQty = '1';
    }

    /* =======================
     | LOAD — semua state dibaca ulang dari DB
     * ======================= */
    private function findData(): void
    {
        if ($this->okReg === '') {
            return;
        }

        $this->loadHeader();
        $this->loadDetail();
    }

    private function loadHeader(): void
    {
        $header = DB::table('rstxn_oks as o')
            ->join('rstxn_rihdrs as h', 'h.rihdr_no', '=', 'o.rihdr_no')
            ->join('rsmst_pasiens as p', 'p.reg_no', '=', 'h.reg_no')
            ->leftJoin('rsmst_rooms as r', 'r.room_id', '=', 'h.room_id')
            ->leftJoin('rsmst_doctors as dopr', 'dopr.dr_id', '=', 'o.dr_id')
            ->leftJoin('rsmst_doctors as danes', 'danes.dr_id', '=', 'o.dr_id_ok')
            ->leftJoin('rsmst_mstdiags as dg', 'dg.diag_id', '=', 'o.diag_id')
            ->select(
                'o.ok_reg',
                'o.rihdr_no',
                'o.ok_status',
                'o.dr_id',
                'o.dr_id_ok',
                'o.diag_id',
                'dg.diag_desc',
                'o.emp_id_asistopr',
                'o.emp_id_asistanes',
                'o.emp_id_instrument',
                'o.emp_id_changeanesdoc',
                DB::raw("to_char(o.ok_date,'dd/mm/yyyy hh24:mi:ss') as ok_date"),
                'h.reg_no',
                'h.ri_status',
                'p.reg_name',
                'p.sex',
                DB::raw("to_char(p.birth_date,'dd/mm/yyyy') as birth_date"),
                'p.address',
                'r.room_name',
                'dopr.dr_name as operator_name',
                'danes.dr_name as anestesi_name',
                ...array_keys(KamarOperasiTarif::POS),
                ...array_keys(KamarOperasiTarif::POS_ONCALL),
            )
            ->where('o.ok_reg', $this->okReg)
            ->first();

        $this->headerData = $header ? (array) $header : [];

        // Pos tarif dipisah ke $tarif — NULL dipertahankan (beda makna dengan 0:
        // NULL = belum pernah diisi, jadi boleh diisi otomatis oleh Hitung Tarif OK).
        $this->tarif = [];
        foreach (array_keys(KamarOperasiTarif::POS) as $kolom) {
            $nilai = $this->headerData[$kolom] ?? null;
            $this->tarif[$kolom] = $nilai === null ? null : (int) $nilai;
        }

        $this->oncall = [];
        foreach (array_keys(KamarOperasiTarif::POS_ONCALL) as $kolom) {
            $nilai = $this->headerData[$kolom] ?? null;
            $this->oncall[$kolom] = $nilai === null ? null : (int) $nilai;
        }

        $this->drId = $this->headerData['dr_id'] ?? null;
        $this->drIdOk = $this->headerData['dr_id_ok'] ?? null;
        $this->empIdAsistopr = $this->headerData['emp_id_asistopr'] ?? null;
        $this->empIdAsistanes = $this->headerData['emp_id_asistanes'] ?? null;
        $this->empIdInstrument = $this->headerData['emp_id_instrument'] ?? null;
        $this->empIdChangeanesdoc = $this->headerData['emp_id_changeanesdoc'] ?? null;

        $this->sumTotal = KamarOperasiTarif::total($this->tarif);
        $this->sumOncall = array_sum(array_map(fn($nilai) => (int) $nilai, $this->oncall));
        $this->isFormLocked = ($this->headerData['ok_status'] ?? 'A') !== 'A';

        $this->susunBarisTampilan();
        $this->evaluasiIndukTerkunci();
    }

    /** Target LOV per kolom crew — dipakai template dan listener lov.selected.*. */
    private const TARGET_LOV = [
        'dr_id' => 'kamar-operasi-operator',
        'dr_id_ok' => 'kamar-operasi-anestesi',
        'emp_id_changeanesdoc' => 'kamar-operasi-changeanesdoc',
        'emp_id_asistopr' => 'kamar-operasi-asistopr',
        'emp_id_asistanes' => 'kamar-operasi-asistanes',
        'emp_id_instrument' => 'kamar-operasi-instrument',
    ];

    /** Rakit baris crew + pos lainnya supaya template bebas logika & FQCN. */
    private function susunBarisTampilan(): void
    {
        // Nama karyawan diambil sekali untuk semua posisi — bukan satu query per baris
        // di dalam loop template seperti sebelumnya.
        $empIds = array_values(array_filter([$this->empIdChangeanesdoc, $this->empIdAsistopr, $this->empIdAsistanes, $this->empIdInstrument]));
        $namaKaryawan = $empIds === [] ? [] : DB::table('hrmst_employees')->whereIn('emp_id', $empIds)->pluck('name', 'emp_id')->all();

        $idPerKolom = [
            'dr_id' => $this->drId,
            'dr_id_ok' => $this->drIdOk,
            'emp_id_changeanesdoc' => $this->empIdChangeanesdoc,
            'emp_id_asistopr' => $this->empIdAsistopr,
            'emp_id_asistanes' => $this->empIdAsistanes,
            'emp_id_instrument' => $this->empIdInstrument,
        ];

        $this->crewRows = [];
        foreach (KamarOperasiTarif::CREW as $kolomCrew => $crew) {
            $kolomFee = $crew['fee'];
            $kolomOncall = $crew['oncall'];
            $idCrew = $idPerKolom[$kolomCrew] ?? null;

            $namaCrew = match ($kolomCrew) {
                'dr_id' => $this->headerData['operator_name'] ?? null,
                'dr_id_ok' => $this->headerData['anestesi_name'] ?? null,
                default => $idCrew ? $namaKaryawan[$idCrew] ?? null : null,
            };

            $this->crewRows[] = [
                'kolomCrew' => $kolomCrew,
                'label' => $crew['label'],
                'jenis' => $crew['jenis'],
                'target' => self::TARGET_LOV[$kolomCrew] ?? '',
                'idCrew' => $idCrew,
                'namaCrew' => $namaCrew,
                'kolomFee' => $kolomFee,
                'labelFee' => KamarOperasiTarif::LABEL[$kolomFee] ?? $kolomFee,
                'nilaiFee' => $this->tarif[$kolomFee] ?? null,
                'persen' => KamarOperasiTarif::PERSEN_DARI_OPERATOR[$kolomFee] ?? null,
                'isTurunan' => in_array($kolomFee, KamarOperasiTarif::POS_TURUNAN_DETAIL, true),
                'isGajiDokter' => array_key_exists($kolomFee, KamarOperasiTarif::POS_GAJI_DOKTER),
                'kolomOncall' => $kolomOncall,
                'nilaiOncall' => $kolomOncall ? $this->oncall[$kolomOncall] ?? null : null,
            ];
        }

        $this->posLainnyaRows = [];
        foreach (KamarOperasiTarif::posTanpaCrew() as $kolom) {
            $this->posLainnyaRows[] = [
                'kolom' => $kolom,
                'label' => KamarOperasiTarif::LABEL[$kolom] ?? $kolom,
                'keterangan' => KamarOperasiTarif::POS[$kolom],
                'nilai' => $this->tarif[$kolom] ?? null,
                'isTurunan' => in_array($kolom, KamarOperasiTarif::POS_TURUNAN_DETAIL, true),
            ];
        }

        $this->oncallRows = [];
        foreach (KamarOperasiTarif::POS_ONCALL as $kolom => $label) {
            $this->oncallRows[] = ['kolom' => $kolom, 'label' => $label, 'nilai' => $this->oncall[$kolom] ?? null];
        }
    }

    private function loadDetail(): void
    {
        $this->rowsTindakan = DB::table('rstxn_okacts as t')
            ->leftJoin('rsmst_accdocs as a', 'a.accdoc_id', '=', 't.accdoc_id')
            ->select('t.okact_id', 't.accdoc_id', 'a.accdoc_desc', 't.okact_price')
            ->where('t.ok_reg', $this->okReg)
            ->orderBy('t.okact_id')
            ->get()
            ->map(fn($tindakan) => (array) $tindakan)
            ->toArray();

        $this->rowsBahanAlat = DB::table('rstxn_okobats as t')
            ->leftJoin('immst_products as p', 'p.product_id', '=', 't.product_id')
            ->select('t.okobat_id', 't.product_id', 'p.product_name', 't.okobat_qty', 't.okobat_price')
            ->where('t.ok_reg', $this->okReg)
            ->orderBy('t.okobat_id')
            ->get()
            ->map(fn($bahanAlat) => (array) $bahanAlat)
            ->toArray();

        $this->rowsOmlop = DB::table('rstxn_okomlops as t')
            ->leftJoin('hrmst_employees as e', 'e.emp_id', '=', 't.emp_id')
            ->select('t.omlop_dtl', 't.emp_id', 'e.name as emp_name', 't.omlop_fee', 't.oncallomlop_fee')
            ->where('t.ok_reg', $this->okReg)
            ->orderBy('t.omlop_dtl')
            ->get()
            ->map(fn($crewOmlop) => (array) $crewOmlop)
            ->toArray();
    }

    /**
     * Transfer DAN batal sama-sama hanya sah selama pasien masih dirawat
     * (`ri_status = 'I'`) — keduanya menulis ke tagihan rawat inap yang sudah
     * ditutup kalau pasien pulang. Ini menyamai aturan Batal Transfer UGD→RI:
     * begitu kunjungan RI tidak aktif lagi, pembatalan ikut tertutup.
     *
     * Yang disimpan hanya SEBAB-nya; kalimat lengkapnya disusun per aksi
     * (lihat pesanTerkunci) supaya tombol Batal tidak memakai kalimat transfer.
     */
    private function evaluasiIndukTerkunci(): void
    {
        $this->indukTerkunci = false;
        $this->indukTerkunciSebab = '';

        $riStatus = strtoupper($this->headerData['ri_status'] ?? '');
        if ($riStatus === '' || $riStatus === 'I') {
            return;
        }

        $this->indukTerkunciSebab = match ($riStatus) {
            'P', 'L' => 'Pasien sudah pulang',
            'F' => 'Kunjungan rawat inap dibatalkan',
            default => 'Kunjungan rawat inap tidak aktif',
        };
        $this->indukTerkunci = true;
    }

    /** Kalimat lengkap sesuai aksi yang sedang tertutup. */
    public function pesanTerkunci(string $aksi): string
    {
        if (!$this->indukTerkunci) {
            return '';
        }

        return $this->indukTerkunciSebab . ' — ' . match ($aksi) {
            'transfer' => 'biaya tidak bisa ditransfer ke rawat inap.',
            'batal' => 'transfer tidak bisa dibatalkan lagi.',
            default => 'transaksi terkunci.',
        };
    }

    /* =======================
     | Helper transaksi + retry
     |
     | PK detail (okact_id, okobat_id, omlop_dtl) dan ok_no semuanya global tanpa
     | sequence, jadi dua petugas bisa merebut nomor sama. Oracle menolak FOR UPDATE
     | pada query agregat (ORA-01786), jadi tabrakan ditangani dengan mengulang
     | seluruh transaksi (sudah rollback penuh) memakai nomor baru.
     * ======================= */
    private function jalankanDenganRetry(callable $aksi, string $pesanGagal): bool
    {
        for ($percobaan = 1; ; $percobaan++) {
            try {
                DB::transaction($aksi);

                return true;
            } catch (\RuntimeException $e) {
                $this->dispatch('toast', type: 'error', message: $e->getMessage());
                $this->findData();

                return false;
            } catch (\Illuminate\Database\QueryException $e) {
                if ($percobaan < 3 && str_contains($e->getMessage(), 'ORA-00001')) {
                    continue;
                }

                $this->dispatch('toast', type: 'error', message: $pesanGagal . ': ' . $e->getMessage());
                $this->findData();

                return false;
            } catch (\Exception $e) {
                $this->dispatch('toast', type: 'error', message: $pesanGagal . ': ' . $e->getMessage());
                $this->findData();

                return false;
            }
        }
    }

    /** Ambil baris rstxn_oks terkunci + pastikan masih boleh diubah. */
    private function kunciBarisOk(): object
    {
        $row = DB::table('rstxn_oks')->where('ok_reg', $this->okReg)->lockForUpdate()->first();

        if (!$row) {
            throw new \RuntimeException('Transaksi tidak ditemukan.');
        }

        if (($row->ok_status ?? 'A') !== 'A') {
            throw new \RuntimeException('Transaksi sudah selesai/dibatalkan — tidak bisa diubah.');
        }

        return $row;
    }

    private function catatLog(int $riHdrNo, string $keterangan): void
    {
        if ($riHdrNo <= 0) {
            return;
        }

        $this->lockRIRow($riHdrNo);
        $this->appendAdminLogRI($riHdrNo, $keterangan, 'ADMIN');
    }

    /** Guard umum untuk semua aksi ubah data. */
    private function bolehUbah(): bool
    {
        if (!$this->isAllowedRole()) {
            $this->dispatch('toast', type: 'error', message: 'Anda tidak memiliki akses.');

            return false;
        }

        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Transaksi sudah selesai/dibatalkan — data tidak bisa diubah.');
            $this->findData();

            return false;
        }

        return true;
    }

    private function selesaikanAksi(string $pesanSukses): void
    {
        $this->findData();
        $this->dispatch('refresh-after-kamar-operasi.saved');
        $this->dispatch('toast', type: 'success', message: $pesanSukses);
    }

    /* =======================
     | INLINE EDIT — satu pos tarif
     |
     | Tiap pos mengalikan tagihan pasien, jadi SETIAP perubahan diaudit
     | (nilai lama -> nilai baru) di dalam transaksi yang sama.
     * ======================= */
    /**
     * Hook wire:model dari x-text-input-number.
     *
     * Komponen itu menyinkron nilainya lewat `$wire.set('tarif.<kolom>', raw)` saat
     * blur, jadi penyimpanan ke DB dipicu dari sini — bukan dari x-on:change manual.
     * Hook `updated*` HANYA jalan untuk perubahan dari sisi klien; menulis
     * `$this->tarif` dari PHP (loadHeader) tidak memicunya, jadi tidak ada loop.
     */
    public function updatedTarif($value, $key): void
    {
        $this->updateTarif((string) $key, $value === null ? null : (string) $value);
    }

    public function updatedOncall($value, $key): void
    {
        $this->updateOncall((string) $key, $value === null ? null : (string) $value);
    }

    /** $key berbentuk "<indeks baris>.<kolom>". */
    public function updatedRowsOmlop($value, $key): void
    {
        [$indeks, $kolom] = array_pad(explode('.', (string) $key, 2), 2, null);

        if ($kolom === null || !isset($this->rowsOmlop[(int) $indeks]['omlop_dtl'])) {
            return;
        }

        $this->updateOmlopFee((int) $this->rowsOmlop[(int) $indeks]['omlop_dtl'], $kolom, $value === null ? null : (string) $value);
    }

    public function updateTarif(string $kolom, ?string $nilai): void
    {
        // Whitelist — nilai tak terduga DITOLAK, bukan jatuh ke else.
        // oprdoc_fee & equipment_fee sengaja TIDAK boleh diketik: keduanya
        // turunan dari tabel detail dan akan ditimpa oleh hitung ulang.
        $kolomBoleh = array_values(array_diff(array_keys(KamarOperasiTarif::POS), KamarOperasiTarif::POS_TURUNAN_DETAIL));

        $this->simpanKolomFee($kolom, $nilai, $kolomBoleh, KamarOperasiTarif::LABEL);
    }

    /** Jasa on call — tidak masuk tagihan pasien, tapi tetap diaudit. */
    public function updateOncall(string $kolom, ?string $nilai): void
    {
        $this->simpanKolomFee($kolom, $nilai, array_keys(KamarOperasiTarif::POS_ONCALL), KamarOperasiTarif::POS_ONCALL);
    }

    /**
     * @param  list<string>          $kolomBoleh
     * @param  array<string,string>  $labelPeta
     */
    private function simpanKolomFee(string $kolom, ?string $nilai, array $kolomBoleh, array $labelPeta): void
    {
        if (!in_array($kolom, $kolomBoleh, true)) {
            return;
        }

        if (!$this->bolehUbah()) {
            return;
        }

        // Validasi pakai rule Laravel, bukan cek manual.
        $bersih = str_replace(['.', ',', ' '], '', trim((string) $nilai));

        $validator = Validator::make(
            ['tarif' => $bersih === '' ? null : $bersih],
            ['tarif' => 'bail|nullable|integer|min:0|max:999999999'],
            ['tarif.integer' => 'Tarif harus berupa angka bulat.', 'tarif.min' => 'Tarif tidak boleh negatif.', 'tarif.max' => 'Tarif melebihi batas wajar.'],
        );

        if ($validator->fails()) {
            $this->dispatch('toast', type: 'error', message: $validator->errors()->first('tarif'));
            $this->findData();
            return;
        }

        $nilaiBaru = $bersih === '' ? null : (int) $bersih;
        $nilaiLama = $this->headerData[$kolom] ?? null;
        $nilaiLama = $nilaiLama === null ? null : (int) $nilaiLama;

        // Skip bila tidak berubah — tanpa query & tanpa toast.
        if ($nilaiLama === $nilaiBaru) {
            return;
        }

        $riHdrNo = (int) ($this->headerData['rihdr_no'] ?? 0);
        $label = $labelPeta[$kolom] ?? $kolom;
        // NULL punya makna sendiri (belum diisi → boleh diisi otomatis), jadi
        // ditulis eksplisit di log supaya tidak tertukar dengan nol rupiah.
        $teksLama = $nilaiLama === null ? '(belum diisi)' : 'Rp ' . number_format($nilaiLama);
        $teksBaru = $nilaiBaru === null ? '(belum diisi)' : 'Rp ' . number_format($nilaiBaru);

        $berhasil = $this->jalankanDenganRetry(function () use ($kolom, $nilaiBaru, $riHdrNo, $label, $teksLama, $teksBaru) {
            $this->kunciBarisOk();

            DB::table('rstxn_oks')->where('ok_reg', $this->okReg)->update([$kolom => $nilaiBaru]);

            $this->catatLog($riHdrNo, "Ubah tarif OK No.{$this->okReg} — {$label}: {$teksLama} → {$teksBaru}");
        }, 'Gagal menyimpan tarif');

        if ($berhasil) {
            $this->selesaikanAksi("{$label} disimpan.");
        }
    }

    /* =======================
     | CREW — dokter & petugas
     * ======================= */
    #[On('lov.selected.kamar-operasi-operator')]
    public function pilihOperator($target = null, $payload = null): void
    {
        $this->simpanCrew('dr_id', $payload['dr_id'] ?? null, 'Dr. Operator');
    }

    #[On('lov.selected.kamar-operasi-anestesi')]
    public function pilihAnestesi($target = null, $payload = null): void
    {
        $this->simpanCrew('dr_id_ok', $payload['dr_id'] ?? null, 'Dr. Anestesi');
    }

    #[On('lov.selected.kamar-operasi-asistopr')]
    public function pilihAsistopr($target = null, $payload = null): void
    {
        $this->simpanCrew('emp_id_asistopr', $payload['emp_id'] ?? null, 'Asisten Operator');
    }

    #[On('lov.selected.kamar-operasi-asistanes')]
    public function pilihAsistanes($target = null, $payload = null): void
    {
        $this->simpanCrew('emp_id_asistanes', $payload['emp_id'] ?? null, 'Asisten Anestesi');
    }

    #[On('lov.selected.kamar-operasi-instrument')]
    public function pilihInstrument($target = null, $payload = null): void
    {
        $this->simpanCrew('emp_id_instrument', $payload['emp_id'] ?? null, 'Instrument');
    }

    #[On('lov.selected.kamar-operasi-changeanesdoc')]
    public function pilihChangeanesdoc($target = null, $payload = null): void
    {
        $this->simpanCrew('emp_id_changeanesdoc', $payload['emp_id'] ?? null, 'Pengganti Anestesi');
    }

    /**
     * dr_id & dr_id_ok NOT NULL di rstxn_oks dan menentukan siapa yang menerima
     * jasa di laporan pendapatan dokter, jadi tidak boleh dikosongkan.
     */
    private function simpanCrew(string $kolom, ?string $nilai, string $label): void
    {
        $kolomBoleh = ['dr_id', 'dr_id_ok', 'emp_id_asistopr', 'emp_id_asistanes', 'emp_id_instrument', 'emp_id_changeanesdoc'];
        if (!in_array($kolom, $kolomBoleh, true)) {
            return;
        }

        if (!$this->bolehUbah()) {
            return;
        }

        $nilaiBaru = $nilai === null || trim((string) $nilai) === '' ? null : trim((string) $nilai);
        $wajibIsi = in_array($kolom, ['dr_id', 'dr_id_ok'], true);

        if ($wajibIsi && $nilaiBaru === null) {
            $this->dispatch('toast', type: 'error', message: "{$label} wajib diisi — pilih dokter penggantinya, jangan dikosongkan.");
            $this->findData();
            return;
        }

        $nilaiLama = $this->headerData[$kolom] ?? null;
        $nilaiLama = $nilaiLama === null || $nilaiLama === '' ? null : (string) $nilaiLama;

        if ($nilaiLama === $nilaiBaru) {
            return;
        }

        $riHdrNo = (int) ($this->headerData['rihdr_no'] ?? 0);
        $namaLama = $this->namaCrew($kolom, $nilaiLama);
        $namaBaru = $this->namaCrew($kolom, $nilaiBaru);
        $catatanGaji = isset(KamarOperasiTarif::POS_GAJI_DOKTER[$kolom === 'dr_id' ? 'oprdoc_fee' : 'anesdoc_fee']) && $wajibIsi ? ' (memindahkan pendapatan dokter di Laporan Pendapatan Jasa Dokter)' : '';

        $berhasil = $this->jalankanDenganRetry(function () use ($kolom, $nilaiBaru, $riHdrNo, $label, $namaLama, $namaBaru, $catatanGaji) {
            $this->kunciBarisOk();

            DB::table('rstxn_oks')->where('ok_reg', $this->okReg)->update([$kolom => $nilaiBaru]);

            $this->catatLog($riHdrNo, "Ubah crew OK No.{$this->okReg} — {$label}: {$namaLama} → {$namaBaru}{$catatanGaji}");
        }, 'Gagal menyimpan crew');

        if ($berhasil) {
            $this->selesaikanAksi("{$label} disimpan.");
        }
    }

    private function namaCrew(string $kolom, ?string $idCrew): string
    {
        if ($idCrew === null) {
            return '(kosong)';
        }

        $nama = in_array($kolom, ['dr_id', 'dr_id_ok'], true) ? DB::table('rsmst_doctors')->where('dr_id', $idCrew)->value('dr_name') : DB::table('hrmst_employees')->where('emp_id', $idCrew)->value('name');

        return ($nama ?: '?') . " ({$idCrew})";
    }

    /** Rumusnya tinggal satu tempat: App\Support\KamarOperasiTarif::hitungUlang(). */
    private function hitungUlangPosTurunan(object $row): array
    {
        return KamarOperasiTarif::hitungUlang($this->okReg, $row);
    }

    public function hitungTarifOk(): void
    {
        if (!$this->bolehUbah()) {
            return;
        }

        $riHdrNo = (int) ($this->headerData['rihdr_no'] ?? 0);

        $berhasil = $this->jalankanDenganRetry(function () use ($riHdrNo) {
            $row = $this->kunciBarisOk();

            [, $totalBaru, $berubah] = $this->hitungUlangPosTurunan($row);

            $ringkasan = $berubah === [] ? 'tidak ada pos yang berubah' : implode(', ', $berubah);
            $this->catatLog($riHdrNo, "Hitung Tarif OK No.{$this->okReg} — {$ringkasan}. Total Rp " . number_format($totalBaru));
        }, 'Gagal menghitung tarif');

        if ($berhasil) {
            $this->selesaikanAksi('Tarif OK dihitung ulang — total Rp ' . number_format($this->sumTotal) . '.');
        }
    }

    /* =======================
     | TAB 1 — TINDAKAN OPERASI (rstxn_okacts)
     * ======================= */
    #[On('lov.selected.kamar-operasi-tindakan')]
    public function pilihTindakan($target = null, $payload = null): void
    {
        $this->formTindakanAccdocId = $payload['accdoc_id'] ?? null;
        $this->formTindakanDesc = $payload['accdoc_desc'] ?? '';
        // accdoc_price dari lov-jasa-dokter-ri sudah harga efektif per kelas kamar pasien.
        $this->formTindakanPrice = isset($payload['accdoc_price']) ? (int) $payload['accdoc_price'] : null;
    }

    public function tambahTindakan(): void
    {
        if (!$this->bolehUbah()) {
            return;
        }

        if (empty($this->formTindakanAccdocId)) {
            $this->dispatch('toast', type: 'error', message: 'Pilih jenis tindakan terlebih dahulu.');
            return;
        }

        $validator = Validator::make(['harga' => $this->formTindakanPrice], ['harga' => 'bail|required|integer|min:0|max:999999999'], ['harga.required' => 'Tarif tindakan wajib diisi.', 'harga.integer' => 'Tarif tindakan harus angka bulat.']);

        if ($validator->fails()) {
            $this->dispatch('toast', type: 'error', message: $validator->errors()->first('harga'));
            return;
        }

        $riHdrNo = (int) ($this->headerData['rihdr_no'] ?? 0);
        $accdocId = $this->formTindakanAccdocId;
        $harga = (int) $this->formTindakanPrice;
        $desc = $this->formTindakanDesc;

        $berhasil = $this->jalankanDenganRetry(function () use ($riHdrNo, $accdocId, $harga, $desc) {
            $row = $this->kunciBarisOk();

            $nomor = (int) DB::scalar('SELECT NVL(MAX(okact_id),0) + 1 FROM rstxn_okacts');

            DB::table('rstxn_okacts')->insert(['okact_id' => $nomor, 'accdoc_id' => $accdocId, 'okact_price' => $harga, 'ok_reg' => $this->okReg]);

            // Tindakan bertambah → jasa operator dan pos persentasenya ikut naik.
            [, $totalBaru] = $this->hitungUlangPosTurunan($row);

            $this->catatLog($riHdrNo, "Tambah tindakan OK No.{$this->okReg} — {$accdocId} {$desc} Rp " . number_format($harga) . '. Total Rp ' . number_format($totalBaru));
        }, 'Gagal menambah tindakan');

        if ($berhasil) {
            $this->resetFormTambah();
            $this->selesaikanAksi('Tindakan ditambahkan — total Rp ' . number_format($this->sumTotal) . '.');
        }
    }

    public function hapusTindakan(int $okactId): void
    {
        if (!$this->bolehUbah()) {
            return;
        }

        $riHdrNo = (int) ($this->headerData['rihdr_no'] ?? 0);

        $berhasil = $this->jalankanDenganRetry(function () use ($riHdrNo, $okactId) {
            $row = $this->kunciBarisOk();

            $baris = DB::table('rstxn_okacts')->where('okact_id', $okactId)->where('ok_reg', $this->okReg)->first();

            if (!$baris) {
                throw new \RuntimeException('Baris tindakan tidak ditemukan.');
            }

            DB::table('rstxn_okacts')->where('okact_id', $okactId)->where('ok_reg', $this->okReg)->delete();

            [, $totalBaru] = $this->hitungUlangPosTurunan($row);

            $this->catatLog($riHdrNo, "Hapus tindakan OK No.{$this->okReg} — {$baris->accdoc_id} Rp " . number_format((int) $baris->okact_price) . '. Total Rp ' . number_format($totalBaru));
        }, 'Gagal menghapus tindakan');

        if ($berhasil) {
            $this->selesaikanAksi('Tindakan dihapus — total Rp ' . number_format($this->sumTotal) . '.');
        }
    }

    /* =======================
     | TAB 2 — BAHAN DAN ALAT (rstxn_okobats)
     * ======================= */
    #[On('lov.selected.kamar-operasi-produk')]
    public function pilihProduk($target = null, $payload = null): void
    {
        $this->formProductId = $payload['product_id'] ?? null;
        $this->formProductName = $payload['product_name'] ?? '';
        $this->formProductPrice = isset($payload['sales_price']) ? (int) $payload['sales_price'] : null;
    }

    public function tambahBahanAlat(): void
    {
        if (!$this->bolehUbah()) {
            return;
        }

        if (empty($this->formProductId)) {
            $this->dispatch('toast', type: 'error', message: 'Pilih bahan/alat terlebih dahulu.');
            return;
        }

        $validator = Validator::make(
            ['qty' => str_replace(['.', ',', ' '], '', trim($this->formProductQty)), 'harga' => $this->formProductPrice],
            ['qty' => 'bail|required|integer|min:1|max:99999', 'harga' => 'bail|required|integer|min:0|max:999999999'],
            ['qty.required' => 'Qty wajib diisi.', 'qty.integer' => 'Qty harus angka bulat.', 'qty.min' => 'Qty minimal 1.', 'harga.required' => 'Harga wajib diisi.', 'harga.integer' => 'Harga harus angka bulat.'],
        );

        if ($validator->fails()) {
            $this->dispatch('toast', type: 'error', message: $validator->errors()->first());
            return;
        }

        $riHdrNo = (int) ($this->headerData['rihdr_no'] ?? 0);
        $productId = $this->formProductId;
        $productName = $this->formProductName;
        $qty = (int) str_replace(['.', ',', ' '], '', trim($this->formProductQty));
        $harga = (int) $this->formProductPrice;

        $berhasil = $this->jalankanDenganRetry(function () use ($riHdrNo, $productId, $productName, $qty, $harga) {
            $row = $this->kunciBarisOk();

            $nomor = (int) DB::scalar('SELECT NVL(MAX(okobat_id),0) + 1 FROM rstxn_okobats');

            DB::table('rstxn_okobats')->insert(['okobat_id' => $nomor, 'product_id' => $productId, 'okobat_qty' => $qty, 'okobat_price' => $harga, 'ok_reg' => $this->okReg]);

            [, $totalBaru] = $this->hitungUlangPosTurunan($row);

            $this->catatLog($riHdrNo, "Tambah bahan/alat OK No.{$this->okReg} — {$productId} {$productName} {$qty} x Rp " . number_format($harga) . '. Total Rp ' . number_format($totalBaru));
        }, 'Gagal menambah bahan/alat');

        if ($berhasil) {
            $this->resetFormTambah();
            $this->selesaikanAksi('Bahan/alat ditambahkan — total Rp ' . number_format($this->sumTotal) . '.');
        }
    }

    public function hapusBahanAlat(int $okobatId): void
    {
        if (!$this->bolehUbah()) {
            return;
        }

        $riHdrNo = (int) ($this->headerData['rihdr_no'] ?? 0);

        $berhasil = $this->jalankanDenganRetry(function () use ($riHdrNo, $okobatId) {
            $row = $this->kunciBarisOk();

            $baris = DB::table('rstxn_okobats')->where('okobat_id', $okobatId)->where('ok_reg', $this->okReg)->first();

            if (!$baris) {
                throw new \RuntimeException('Baris bahan/alat tidak ditemukan.');
            }

            DB::table('rstxn_okobats')->where('okobat_id', $okobatId)->where('ok_reg', $this->okReg)->delete();

            [, $totalBaru] = $this->hitungUlangPosTurunan($row);

            $this->catatLog($riHdrNo, "Hapus bahan/alat OK No.{$this->okReg} — {$baris->product_id} " . (int) $baris->okobat_qty . ' x Rp ' . number_format((int) $baris->okobat_price) . '. Total Rp ' . number_format($totalBaru));
        }, 'Gagal menghapus bahan/alat');

        if ($berhasil) {
            $this->selesaikanAksi('Bahan/alat dihapus — total Rp ' . number_format($this->sumTotal) . '.');
        }
    }

    /* =======================
     | TAB 3 — CREW OM LOP (rstxn_okomlops)
     |
     | Jasa per baris di sini TIDAK ditransfer ke tagihan pasien; yang masuk
     | tagihan adalah pos `omlop_fee` di header. Baris ini merinci siapa saja
     | petugasnya untuk keperluan jasa/penggajian.
     * ======================= */
    #[On('lov.selected.kamar-operasi-omlop')]
    public function pilihOmlop($target = null, $payload = null): void
    {
        $this->formOmlopEmpId = $payload['emp_id'] ?? null;
        $this->formOmlopName = $payload['name'] ?? '';
    }

    public function tambahOmlop(): void
    {
        if (!$this->bolehUbah()) {
            return;
        }

        if (empty($this->formOmlopEmpId)) {
            $this->dispatch('toast', type: 'error', message: 'Pilih petugas terlebih dahulu.');
            return;
        }

        $empId = $this->formOmlopEmpId;
        $empName = $this->formOmlopName;

        $sudahAda = collect($this->rowsOmlop)->contains(fn($crewOmlop) => (string) ($crewOmlop['emp_id'] ?? '') === (string) $empId);
        if ($sudahAda) {
            $this->dispatch('toast', type: 'error', message: 'Petugas tersebut sudah terdaftar di transaksi ini.');
            return;
        }

        $riHdrNo = (int) ($this->headerData['rihdr_no'] ?? 0);

        $berhasil = $this->jalankanDenganRetry(function () use ($riHdrNo, $empId, $empName) {
            $this->kunciBarisOk();

            $nomor = (int) DB::scalar('SELECT NVL(MAX(omlop_dtl),0) + 1 FROM rstxn_okomlops');

            DB::table('rstxn_okomlops')->insert(['omlop_dtl' => $nomor, 'emp_id' => $empId, 'ok_reg' => $this->okReg]);

            $this->catatLog($riHdrNo, "Tambah crew OM LOP OK No.{$this->okReg} — {$empName} ({$empId})");
        }, 'Gagal menambah crew OM LOP');

        if ($berhasil) {
            $this->resetFormTambah();
            $this->selesaikanAksi('Crew OM LOP ditambahkan.');
        }
    }

    public function hapusOmlop(int $omlopDtl): void
    {
        if (!$this->bolehUbah()) {
            return;
        }

        $riHdrNo = (int) ($this->headerData['rihdr_no'] ?? 0);

        $berhasil = $this->jalankanDenganRetry(function () use ($riHdrNo, $omlopDtl) {
            $this->kunciBarisOk();

            $baris = DB::table('rstxn_okomlops')->where('omlop_dtl', $omlopDtl)->where('ok_reg', $this->okReg)->first();

            if (!$baris) {
                throw new \RuntimeException('Baris crew OM LOP tidak ditemukan.');
            }

            DB::table('rstxn_okomlops')->where('omlop_dtl', $omlopDtl)->where('ok_reg', $this->okReg)->delete();

            $this->catatLog($riHdrNo, "Hapus crew OM LOP OK No.{$this->okReg} — {$baris->emp_id}");
        }, 'Gagal menghapus crew OM LOP');

        if ($berhasil) {
            $this->selesaikanAksi('Crew OM LOP dihapus.');
        }
    }

    /** Jasa per baris OM LOP (jasa petugas, bukan tagihan pasien). */
    public function updateOmlopFee(int $omlopDtl, string $kolom, ?string $nilai): void
    {
        if (!in_array($kolom, ['omlop_fee', 'oncallomlop_fee'], true)) {
            return;
        }

        if (!$this->bolehUbah()) {
            return;
        }

        $bersih = str_replace(['.', ',', ' '], '', trim((string) $nilai));

        $validator = Validator::make(['jasa' => $bersih === '' ? null : $bersih], ['jasa' => 'bail|nullable|integer|min:0|max:999999999'], ['jasa.integer' => 'Jasa harus angka bulat.', 'jasa.min' => 'Jasa tidak boleh negatif.']);

        if ($validator->fails()) {
            $this->dispatch('toast', type: 'error', message: $validator->errors()->first('jasa'));
            $this->findData();
            return;
        }

        $nilaiBaru = $bersih === '' ? null : (int) $bersih;

        // Nilai lama HARUS dibaca dari DB, bukan dari $this->rowsOmlop: baris itu
        // ter-bind wire:model sehingga sudah berisi nilai BARU saat hook dipanggil —
        // membandingkannya akan selalu "tidak berubah" dan simpan tak pernah jalan.
        $barisDb = DB::table('rstxn_okomlops')->where('omlop_dtl', $omlopDtl)->where('ok_reg', $this->okReg)->first();

        if (!$barisDb) {
            $this->dispatch('toast', type: 'error', message: 'Baris crew OM LOP tidak ditemukan.');
            $this->findData();
            return;
        }

        $nilaiLama = $barisDb->{$kolom} === null ? null : (int) $barisDb->{$kolom};

        if ($nilaiLama === $nilaiBaru) {
            return;
        }

        $barisLama = collect($this->rowsOmlop)->firstWhere('omlop_dtl', $omlopDtl) ?? [];

        $riHdrNo = (int) ($this->headerData['rihdr_no'] ?? 0);
        $label = $kolom === 'omlop_fee' ? 'Jasa OM LOP' : 'Jasa On Call OM LOP';
        $namaPetugas = $barisLama['emp_name'] ?? ($barisLama['emp_id'] ?? '?');

        $berhasil = $this->jalankanDenganRetry(function () use ($omlopDtl, $kolom, $nilaiBaru, $riHdrNo, $label, $namaPetugas, $nilaiLama) {
            $this->kunciBarisOk();

            $terpengaruh = DB::table('rstxn_okomlops')->where('omlop_dtl', $omlopDtl)->where('ok_reg', $this->okReg)->update([$kolom => $nilaiBaru]);

            if ($terpengaruh === 0) {
                throw new \RuntimeException('Baris crew OM LOP tidak ditemukan.');
            }

            $teksLama = $nilaiLama === null ? '(belum diisi)' : 'Rp ' . number_format($nilaiLama);
            $teksBaru = $nilaiBaru === null ? '(belum diisi)' : 'Rp ' . number_format($nilaiBaru);
            $this->catatLog($riHdrNo, "Ubah {$label} OK No.{$this->okReg} — {$namaPetugas}: {$teksLama} → {$teksBaru}");
        }, 'Gagal menyimpan jasa OM LOP');

        if ($berhasil) {
            $this->selesaikanAksi("{$label} disimpan.");
        }
    }

    /* =======================
     | TRANSFER BIAYA KE RAWAT INAP (A -> L)
     |
     | Beda dari form legacy: seluruh INSERT + UPDATE status dibungkus SATU
     | transaksi. Legacy melakukan COMMIT di tengah, sehingga kegagalan pada pos
     | ke-sekian meninggalkan biaya separuh yang tidak bisa dibatalkan.
     * ======================= */
    public function transferBiayaInap(): void
    {
        if (!$this->isAllowedRole()) {
            $this->dispatch('toast', type: 'error', message: 'Anda tidak memiliki akses.');
            return;
        }

        if (($this->headerData['ok_status'] ?? 'A') !== 'A') {
            $this->dispatch('toast', type: 'error', message: 'Data sudah diproses.');
            return;
        }

        $riHdrNo = (int) ($this->headerData['rihdr_no'] ?? 0);
        if ($riHdrNo <= 0) {
            $this->dispatch('toast', type: 'error', message: 'Transaksi ini tidak terkait kunjungan rawat inap.');
            return;
        }

        $jumlahPos = 0;
        $totalTransfer = 0;

        $berhasil = $this->jalankanDenganRetry(function () use ($riHdrNo, &$jumlahPos, &$totalTransfer) {
            $jumlahPos = 0;
            $totalTransfer = 0;

            $row = $this->kunciBarisOk();

            $this->lockRIRow($riHdrNo);

            $riStatus = DB::table('rstxn_rihdrs')->where('rihdr_no', $riHdrNo)->value('ri_status');
            if (strtoupper((string) $riStatus) !== 'I') {
                throw new \RuntimeException('Proses dibatalkan: pasien sudah pulang atau bukan dalam status dirawat.');
            }

            // Oracle menolak FOR UPDATE pada query agregat (ORA-01786), jadi nomor
            // diambil MAX+1 seperti konvensi repo; tabrakan ditangani retry.
            $nomorBerikut = (int) DB::scalar('SELECT NVL(MAX(ok_no),0) FROM rstxn_rioks');

            foreach (KamarOperasiTarif::POS as $kolom => $keterangan) {
                $nilai = (int) ($row->{$kolom} ?? 0);
                if ($nilai <= 0) {
                    continue;
                }

                $nomorBerikut++;
                DB::table('rstxn_rioks')->insert(['ok_no' => $nomorBerikut, 'ok_date' => $row->ok_date, 'ok_desc' => $keterangan, 'ok_price' => $nilai, 'rihdr_no' => $riHdrNo, 'ok_reg' => $this->okReg]);

                $jumlahPos++;
                $totalTransfer += $nilai;
            }

            if ($jumlahPos === 0) {
                throw new \RuntimeException('Tidak ada tarif yang bisa ditransfer — hitung tarif OK terlebih dahulu.');
            }

            DB::table('rstxn_oks')->where('ok_reg', $this->okReg)->update(['ok_status' => 'L']);

            $this->appendAdminLogRI($riHdrNo, "Transfer biaya OK No.{$this->okReg} ke rawat inap — {$jumlahPos} pos, total Rp " . number_format($totalTransfer), 'ADMIN');
        }, 'Gagal transfer biaya');

        if (!$berhasil) {
            return;
        }

        $this->findData();
        $this->dispatch('refresh-after-kamar-operasi.saved');
        $this->dispatch('administrasi-ri.updated');
        $regName = $this->headerData['reg_name'] ?? '';
        $this->dispatch('toast', type: 'success', message: "Biaya operasi pasien {$regName} berhasil ditransfer ke biaya rawat inap.");
    }

    /* =======================
     | BATAL TRANSAKSI (L -> A) — hapus baris biaya di rawat inap
     * ======================= */
    public function batalkanTransaksi(): void
    {
        if (!$this->isAllowedBatal()) {
            $this->dispatch('toast', type: 'error', message: 'Anda tidak berhak membatalkan transaksi ini.');
            return;
        }

        if (($this->headerData['ok_status'] ?? '') !== 'L') {
            $this->dispatch('toast', type: 'error', message: 'Transaksi tidak bisa dibatalkan dari status ini.');
            return;
        }

        $riHdrNo = (int) ($this->headerData['rihdr_no'] ?? 0);
        $jumlahHapus = 0;

        $berhasil = $this->jalankanDenganRetry(function () use ($riHdrNo, &$jumlahHapus) {
            $row = DB::table('rstxn_oks')->where('ok_reg', $this->okReg)->lockForUpdate()->first();

            if (!$row) {
                throw new \RuntimeException('Transaksi tidak ditemukan.');
            }

            if (($row->ok_status ?? '') !== 'L') {
                throw new \RuntimeException('Status transaksi sudah berubah — silakan tutup dan buka ulang.');
            }

            if ($riHdrNo > 0) {
                $this->lockRIRow($riHdrNo);

                // Sama seperti Batal Transfer UGD→RI: begitu kunjungan RI tidak aktif,
                // pembatalan ikut tertutup — menghapus biaya dari tagihan yang sudah
                // ditutup membuat total kwitansi tidak lagi cocok.
                $riStatus = DB::table('rstxn_rihdrs')->where('rihdr_no', $riHdrNo)->value('ri_status');
                if (strtoupper((string) $riStatus) !== 'I') {
                    throw new \RuntimeException('Pasien sudah pulang — transfer tidak bisa dibatalkan lagi.');
                }

                $jumlahHapus = DB::table('rstxn_rioks')->where('ok_reg', $this->okReg)->where('rihdr_no', $riHdrNo)->delete();
            }

            DB::table('rstxn_oks')->where('ok_reg', $this->okReg)->update(['ok_status' => 'A']);

            if ($riHdrNo > 0) {
                $this->appendAdminLogRI($riHdrNo, "Batal transfer biaya OK No.{$this->okReg} — {$jumlahHapus} baris biaya dihapus, status kembali Proses Transaksi", 'ADMIN');
            }
        }, 'Gagal membatalkan transaksi');

        if (!$berhasil) {
            return;
        }

        $this->findData();
        $this->dispatch('refresh-after-kamar-operasi.saved');
        $this->dispatch('administrasi-ri.updated');
        $this->dispatch('toast', type: 'success', message: 'Pembatalan berhasil — status kembali ke Proses Transaksi.');
    }
};
?>

<div>
    <x-modal name="kamar-operasi-actions" size="full" height="full" focusable>
        <div class="flex flex-col h-full" wire:key="{{ $this->renderKey('kamar-operasi-actions-modal', [$okReg ?: 'empty']) }}">

            {{-- ═══════════ HEADER ═══════════
            | Sengaja RINGKAS: hanya display pasien + kartu total. Rincian pos tarif
            | ditaruh di body bersebelahan dengan crew supaya area entry naik dan
            | tidak terdorong jauh ke bawah oleh header.
            ═══════════════════════════════════ --}}
            <div class="relative px-6 py-4 border-b border-hairline dark:border-gray-700">
                <div class="absolute inset-0 opacity-[0.06] dark:opacity-[0.10]"
                    style="background-image: radial-gradient(currentColor 1px, transparent 1px); background-size: 14px 14px;">
                </div>

                @php
                    $statusOk = $headerData['ok_status'] ?? 'A';
                    [$statusText, $statusVariant] = match ($statusOk) {
                        'A' => ['Proses Transaksi', 'warning'],
                        'L' => ['Transaksi Selesai', 'success'],
                        'F' => ['Dibatalkan', 'error'],
                        default => [$statusOk, 'gray'],
                    };
                @endphp

                <div class="relative flex items-start justify-between gap-4">
                    <div class="flex-1 min-w-0">
                        <livewire:pages::transaksi.penunjang.kamar-operasi.display-pasien-kamar-operasi.display-pasien-kamar-operasi
                            :okReg="$okReg" wire:key="display-pasien-kamar-operasi-{{ $okReg }}" />
                    </div>

                    {{-- Dua kartu total ditumpuk atas-bawah supaya header tidak melebar. --}}
                    <div class="flex flex-col self-end flex-shrink-0 gap-1.5 min-w-[190px]">
                        <div class="flex items-baseline justify-between gap-3 px-4 py-2 border rounded-2xl bg-brand-green/10 dark:bg-brand-lime/10 border-brand-green/20 dark:border-brand-lime/20">
                            <p class="text-xs font-medium tracking-wide uppercase text-brand-green dark:text-brand-lime whitespace-nowrap">
                                Tagihan Pasien
                            </p>
                            <p class="text-xl font-bold text-ink dark:text-white tabular-nums whitespace-nowrap">
                                Rp {{ number_format($sumTotal) }}
                            </p>
                        </div>
                        <div class="flex items-baseline justify-between gap-3 px-4 py-1.5 border rounded-2xl border-hairline bg-surface-soft dark:border-gray-700 dark:bg-gray-800/40">
                            <p class="text-xs font-medium tracking-wide uppercase text-muted whitespace-nowrap">
                                Jasa On Call
                                <span class="block text-xs normal-case text-muted-soft">tidak ditagihkan</span>
                            </p>
                            <p class="text-sm font-semibold text-ink dark:text-gray-200 tabular-nums whitespace-nowrap">
                                Rp {{ number_format($sumOncall) }}
                            </p>
                        </div>
                    </div>

                    <div class="flex flex-col items-end flex-shrink-0 gap-2">
                        <x-icon-button color="gray" type="button" wire:click="closeActions">
                            <span class="sr-only">Tutup</span>
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd"
                                    d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                    clip-rule="evenodd" />
                            </svg>
                        </x-icon-button>
                        @if (!empty($headerData))
                            <x-badge :variant="$statusVariant">{{ $statusText }}</x-badge>
                        @endif
                    </div>
                </div>
            </div>

            {{-- ═══════════ BODY ═══════════ --}}
            <div class="flex-1 px-4 py-4 overflow-y-auto bg-surface-soft/70 dark:bg-gray-950/20">
                <div class="max-w-full mx-auto space-y-4">

                    {{-- PERINGATAN KUNJUNGAN RI TIDAK AKTIF --}}
                    @if ($indukTerkunci)
                        <div class="flex items-start gap-2 px-4 py-3 text-sm border rounded-xl border-warning/30 bg-warning-tint text-warning-deep dark:border-amber-700 dark:bg-amber-900/20 dark:text-amber-200">
                            <svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                            <div>
                                <p class="font-semibold">{{ $indukTerkunciSebab }}</p>
                                @if ($statusOk === 'L')
                                    <p class="mt-1">
                                        Biaya operasi ini sudah masuk tagihan rawat inap dan
                                        <span class="font-semibold">tidak bisa dibatalkan lagi</span> —
                                        membatalkan berarti menghapus biaya dari tagihan yang sudah ditutup.
                                    </p>
                                @else
                                    <p class="mt-1">
                                        Biaya <span class="font-semibold">tidak bisa ditransfer</span> ke rawat inap
                                        karena kunjungannya sudah tidak aktif. Tarif masih boleh dilengkapi,
                                        tetapi tidak akan sampai ke tagihan pasien.
                                    </p>
                                @endif
                            </div>
                        </div>
                    @endif

                    {{-- Crew bersebelahan dengan pos tarif, 1 : 1.
                         Di layar sempit keduanya kembali bertumpuk. --}}
                    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2 lg:items-start">
                    {{-- CREW & JASA — nama petugas dipasangkan dengan jasanya sendiri
                         (Dr. Operator ↔ JD Operator, dst.) supaya petugas tidak perlu
                         mencocokkan dua daftar terpisah. --}}
                    {{-- enterBerikutnya(): rantai Enter antar input tarif dalam kartu ini
                         (skill livewire-input-patterns §7). Urutannya mengikuti urutan
                         input di DOM, jadi tidak perlu x-ref bernomor yang gampang basi
                         saat susunan pos berubah. blur() dulu karena x-text-input-number
                         menyinkron nilainya lewat $wire.set saat blur (§2). --}}
                    <div class="p-4 bg-canvas border border-hairline rounded-2xl dark:border-gray-700 dark:bg-gray-900"
                        x-data="{
                            enterBerikutnya(input) {
                                input.blur();
                                const daftar = [...$el.querySelectorAll('input[inputmode=numeric]')];
                                daftar[daftar.indexOf(input) + 1]?.focus();
                            }
                        }">
                        <div class="flex flex-wrap items-center gap-2 mb-1">
                            <h3 class="text-sm font-semibold text-body dark:text-gray-300">Crew &amp; Jasa Operasi</h3>
                            @if ($isFormLocked)
                                <x-badge variant="danger" class="text-xs whitespace-nowrap shrink-0">Read Only</x-badge>
                            @else
                                <span class="ml-auto text-xs italic text-muted">Tersimpan saat kursor berpindah</span>
                            @endif
                        </div>

                        {{-- Penjelasan semua penanda ditaruh di satu panel info (gaya
                             biru-info standar, default tertutup) — bukan disingkat di
                             badge tiap kartu yang justru bikin rancu. --}}
                        <div x-data="{ buka: false }"
                            class="mb-3 border rounded-lg border-blue-200 bg-blue-50 dark:bg-blue-900/20 dark:border-blue-800">
                            <button type="button" x-on:click="buka = !buka"
                                class="flex items-center justify-between w-full px-4 py-2 text-sm font-semibold text-blue-900 dark:text-blue-100">
                                <span class="flex items-center gap-2">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    Arti penanda pada tarif
                                </span>
                                <svg class="w-4 h-4 transition-transform" x-bind:class="buka &amp;&amp; 'rotate-180'"
                                    fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                </svg>
                            </button>

                            <div x-show="buka" x-cloak class="px-4 pb-3 space-y-2 text-sm text-blue-900 dark:text-blue-100">
                                <div class="flex items-start gap-2">
                                    <x-badge variant="warning" class="mt-0.5 shrink-0">Dokter</x-badge>
                                    <span>
                                        Nilainya <span class="font-semibold">ditagihkan ke pasien</span> seperti pos lain,
                                        <span class="font-semibold">dan sekaligus</span> tercatat sebagai
                                        <span class="font-semibold">pendapatan dokter</span> di Laporan Pendapatan Jasa Dokter.
                                        Jadi mengubah angka ini menggeser dua hal: tagihan pasien dan penghasilan dokter
                                        yang tercatat. Penandanya menyebut siapa penerimanya, bukan nama posnya
                                        (nama pos tetap JD Operator / JD Anestesi).
                                    </span>
                                </div>
                                <div class="flex items-start gap-2">
                                    <span class="mt-0.5 text-xs italic shrink-0 text-blue-700 dark:text-blue-300">otomatis</span>
                                    <span>Tidak bisa diketik — dijumlah sendiri dari tabel Tindakan Operasi atau Bahan dan Alat.</span>
                                </div>
                                <div class="flex items-start gap-2">
                                    <span class="mt-0.5 text-xs italic shrink-0 text-blue-700 dark:text-blue-300">50% / 10%</span>
                                    <span>
                                        Angka usulan, dihitung dari pos <span class="font-semibold">JD Operator</span>.
                                        Disegarkan tiap kali tombol
                                        <span class="font-semibold">Hitung Tarif OK</span> ditekan. Boleh Anda ubah manual —
                                        total tetap mengikuti nilai yang tersimpan, bukan persentasenya.
                                    </span>
                                </div>
                                <div class="flex items-start gap-2">
                                    <span class="mt-0.5 text-xs italic shrink-0 text-blue-700 dark:text-blue-300">On Call</span>
                                    <span>
                                        Tambahan jasa karena petugas dipanggil di luar jadwal.
                                        <span class="font-semibold">Tidak ditagihkan ke pasien</span> dan tidak ikut ditransfer
                                        ke biaya rawat inap — hanya catatan jasa petugas.
                                    </span>
                                </div>
                            </div>
                        </div>

                        @php
                            $ringkasTindakan = collect($rowsTindakan)->pluck('accdoc_desc')->filter()->implode(', ');
                        @endphp
                        <div class="grid grid-cols-1 gap-x-6 gap-y-1 pb-2 mb-3 text-sm border-b border-hairline dark:border-gray-700 sm:grid-cols-2 lg:grid-cols-1">
                            <div class="flex items-start gap-2">
                                <span class="shrink-0 text-muted">Tindakan:</span>
                                @if ($ringkasTindakan !== '')
                                    <span class="font-medium text-ink dark:text-gray-200">{{ $ringkasTindakan }}</span>
                                @else
                                    <span class="italic text-muted-soft dark:text-gray-500">Belum ada tindakan</span>
                                @endif
                            </div>
                            <div class="flex items-start gap-2">
                                <span class="shrink-0 text-muted">Diagnosa Pra-Op:</span>
                                @if (!empty($headerData['diag_desc']))
                                    <span class="font-medium text-warning-deep dark:text-amber-300">{{ $headerData['diag_desc'] }}</span>
                                @else
                                    <span class="italic text-muted-soft dark:text-gray-500">-</span>
                                @endif
                            </div>
                        </div>

                        {{-- KELOMPOK 1 — semua yang masuk tagihan pasien, dibingkai
                             sekali di tingkat kelompok (bukan per sel). --}}
                        <div class="p-2 border rounded-lg border-brand-green/30 bg-brand-green/5 dark:border-brand-lime/30 dark:bg-brand-lime/5">
                            <p class="px-1 mb-2 text-sm font-semibold tracking-wide uppercase text-brand-green dark:text-brand-lime">
                                Ditagihkan ke pasien
                            </p>

                        {{-- 6 crew disusun grid — 2 ke kanan di layar lebar. --}}
                        <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                            @foreach ($crewRows as $crew)
                                @php
                                    $kolomCrew = $crew['kolomCrew'];
                                    $kolomFee = $crew['kolomFee'];
                                    $kolomOncall = $crew['kolomOncall'];
                                    $isTurunan = $crew['isTurunan'];
                                    $isGajiDokter = $crew['isGajiDokter'];
                                    $persen = $crew['persen'];
                                    $nilaiFee = $crew['nilaiFee'];
                                    $nilaiOncall = $crew['nilaiOncall'];
                                    $idCrew = $crew['idCrew'];
                                    $namaCrew = $crew['namaCrew'];
                                    $targetLov = $crew['target'];
                                @endphp

                                <div wire:key="crew-{{ $kolomCrew }}"
                                    class="px-3 py-2 border rounded-xl bg-surface-soft dark:bg-gray-800/40 {{ $isGajiDokter ? 'border-warning/30 dark:border-amber-700' : 'border-hairline dark:border-gray-700' }}">

                                    <div class="flex flex-wrap items-center gap-x-2 gap-y-1 mb-1">
                                        <span class="text-sm font-semibold text-body dark:text-gray-300">{{ $crew['label'] }}</span>
                                        @if ($isGajiDokter)
                                            {{-- Label sengaja "Dokter", bukan "jasa dokter":
                                                 nama posnya sendiri sudah JD (Jasa Dokter),
                                                 dua-duanya berdampingan bikin salah tangkap. --}}
                                            <x-badge variant="warning" title="Angka ini masuk tagihan pasien DAN tercatat sebagai pendapatan dokter di Laporan Pendapatan Jasa Dokter. Lihat panel 'Arti penanda pada tarif'.">Dokter</x-badge>
                                        @endif
                                        <span class="ml-auto text-xs text-muted-soft">
                                            {{ $crew['labelFee'] }}
                                            @if ($isTurunan)
                                                <span class="italic" title="Dijumlah dari tabel tindakan">&middot; otomatis</span>
                                            @elseif ($persen !== null)
                                                <span class="italic" title="Usulan {{ $persen }}% dari pos JD Operator; disegarkan tiap kali tarif dihitung ulang. Boleh diedit — total tetap mengikuti nilai yang Anda isi.">&middot; {{ $persen }}%</span>
                                            @endif
                                        </span>
                                    </div>

                                    {{-- Nama petugas --}}
                                    @if ($isFormLocked)
                                        <p class="mb-1 text-sm font-medium text-ink dark:text-gray-200">{{ $namaCrew ?: '-' }}</p>
                                    @elseif ($crew['jenis'] === 'dokter')
                                        <div class="mb-1">
                                            <livewire:lov.dokter.lov-dokter :target="$targetLov" label=""
                                                :initialDrId="$idCrew" wire:key="lov-{{ $kolomCrew }}-{{ $okReg }}-{{ $idCrew }}" />
                                        </div>
                                    @else
                                        <div class="mb-1">
                                            <livewire:lov.karyawan-oncall.lov-karyawan-oncall :target="$targetLov" label=""
                                                :initialEmpId="$idCrew" wire:key="lov-{{ $kolomCrew }}-{{ $okReg }}-{{ $idCrew }}" />
                                        </div>
                                    @endif

                                    {{-- Jasa saja; on call dikelompokkan sendiri di bawah. --}}
                                    @if ($isFormLocked || $isTurunan)
                                        <p class="text-sm font-semibold text-ink dark:text-gray-200 tabular-nums">
                                            {{ $nilaiFee === null ? '—' : 'Rp ' . number_format($nilaiFee) }}
                                        </p>
                                    @else
                                        {{-- Simpan dipicu hook updatedTarif() saat komponen sync di blur. --}}
                                        <x-text-input-number wire:model="tarif.{{ $kolomFee }}"
                                            placeholder="belum diisi"
                                            x-on:keydown.enter.prevent="enterBerikutnya($el)" />
                                    @endif
                                </div>
                            @endforeach
                        </div>

                        {{-- POS TARIF LAINNYA — fasilitas, bahan, dan jasa kelompok
                             (tidak melekat pada satu orang). Masih di dalam kelompok
                             "Ditagihkan ke pasien", jadi tanpa bingkai sendiri. --}}
                        <div class="pt-2 mt-2 border-t border-brand-green/20 dark:border-brand-lime/20">
                            <div class="flex flex-wrap items-baseline px-1 mb-2 gap-x-2">
                                <h4 class="text-sm font-semibold text-body dark:text-gray-300">
                                    Pos Tarif Lainnya
                                </h4>
                                <span class="text-sm text-muted dark:text-gray-400">
                                    fasilitas, bahan, dan jasa kelompok
                                </span>
                            </div>

                            <div class="grid grid-cols-2 gap-1.5 sm:grid-cols-3">
                            @foreach ($posLainnyaRows as $pos)
                                @php
                                    $kolom = $pos['kolom'];
                                    $isTurunan = $pos['isTurunan'];
                                    $nilai = $pos['nilai'];
                                    $labelPos = $pos['label'];
                                @endphp
                                <div wire:key="pos-tarif-{{ $kolom }}"
                                    class="px-2.5 py-1.5 border rounded-xl border-hairline dark:border-gray-700 bg-surface-soft dark:bg-gray-800/40">
                                    <div class="flex items-center justify-between gap-1">
                                        <p class="text-sm truncate text-muted dark:text-gray-400"
                                            title="{{ $pos['keterangan'] }}">{{ $labelPos }}</p>
                                        @if ($isTurunan)
                                            <span class="text-xs italic shrink-0 text-muted-soft" title="Dijumlah dari tabel bahan dan alat">otomatis</span>
                                        @endif
                                    </div>

                                    @if ($isFormLocked || $isTurunan)
                                        <p class="text-sm font-semibold text-ink dark:text-gray-200 tabular-nums">
                                            {{ $nilai === null ? '—' : 'Rp ' . number_format($nilai) }}
                                        </p>
                                    @else
                                        <x-text-input-number wire:model="tarif.{{ $kolom }}"
                                            placeholder="belum diisi"
                                            x-on:keydown.enter.prevent="enterBerikutnya($el)" />
                                    @endif
                                </div>
                            @endforeach
                            </div>
                        </div>
                        </div>{{-- /kelompok "Ditagihkan ke pasien" --}}

                        {{-- KELOMPOK 2 — jasa petugas yang TIDAK masuk tagihan pasien. --}}
                        <div class="p-2 mt-3 border border-dashed rounded-lg border-hairline bg-surface-soft dark:border-gray-600 dark:bg-gray-800/40">
                            <div class="flex flex-wrap items-baseline px-1 mb-2 gap-x-2">
                                <p class="text-sm font-semibold tracking-wide uppercase text-muted dark:text-gray-400">
                                    Tidak ditagihkan ke pasien
                                </p>
                                <span class="text-sm text-muted dark:text-gray-400">
                                    jasa on call petugas &mdash; tidak ikut ditransfer ke biaya rawat inap
                                </span>
                                <span class="ml-auto text-sm font-semibold text-ink dark:text-gray-200 tabular-nums">
                                    Rp {{ number_format($sumOncall) }}
                                </span>
                            </div>

                            <div class="grid grid-cols-1 gap-1.5 sm:grid-cols-3">
                                @foreach ($oncallRows as $baris)
                                    <div wire:key="pos-oncall-{{ $baris['kolom'] }}" class="px-2 py-1">
                                        <p class="text-sm truncate text-muted dark:text-gray-400 mb-0.5">{{ $baris['label'] }}</p>
                                        @if ($isFormLocked)
                                            <p class="text-sm font-semibold text-ink dark:text-gray-200 tabular-nums">
                                                {{ $baris['nilai'] === null ? '—' : 'Rp ' . number_format($baris['nilai']) }}
                                            </p>
                                        @else
                                            <x-text-input-number wire:model="oncall.{{ $baris['kolom'] }}"
                                                placeholder="belum diisi"
                                                x-on:keydown.enter.prevent="enterBerikutnya($el)" />
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    {{-- SUB-TAB DETAIL — mengisi sisi kanan grid 1 : 1 --}}
                    <div x-data="{ tab: @entangle('activeTab') }"
                        class="overflow-hidden bg-canvas border border-hairline rounded-2xl dark:border-gray-700 dark:bg-gray-900">

                        <x-tabs variant="underline" class="flex-wrap p-2">
                            @foreach ($EmrMenuKamarOperasi as $menu)
                                <x-tab active-expr="tab === '{{ $menu['ermMenuId'] }}'"
                                    x-on:click="tab = '{{ $menu['ermMenuId'] }}'">
                                    {{ $menu['ermMenuName'] }}
                                    @php
                                        $count = match ($menu['ermMenuId']) {
                                            'Tindakan' => count($rowsTindakan),
                                            'BahanAlat' => count($rowsBahanAlat),
                                            'Omlop' => count($rowsOmlop),
                                            default => 0,
                                        };
                                    @endphp
                                    @if ($count > 0)
                                        <span class="ml-1 px-1.5 py-0.5 text-xs rounded-full bg-brand-green/20 text-brand-green">{{ $count }}</span>
                                    @endif
                                </x-tab>
                            @endforeach
                        </x-tabs>

                        <div class="p-4 min-h-[240px]">

                            {{-- TAB 1: TINDAKAN --}}
                            <div x-show="tab === 'Tindakan'" x-cloak>
                                <p class="mb-3 text-xs text-muted dark:text-gray-400">
                                    Total tindakan membentuk pos <span class="font-semibold">JD Operator</span> —
                                    ditagihkan ke pasien, sekaligus tercatat sebagai pendapatan dokter operator.
                                </p>

                                @unless ($isFormLocked)
                                    <div class="grid grid-cols-1 gap-3 p-3 mb-4 border rounded-xl border-hairline dark:border-gray-700 bg-surface-soft dark:bg-gray-800/40 lg:grid-cols-12">
                                        <div class="lg:col-span-7">
                                            <livewire:lov.jasa-dokter.lov-jasa-dokter-ri target="kamar-operasi-tindakan" label="Jenis Tindakan"
                                                :riHdrNo="(int) ($headerData['rihdr_no'] ?? 0)" :initialAccdocId="$formTindakanAccdocId"
                                                wire:key="lov-tindakan-{{ $okReg }}-{{ $formTindakanAccdocId }}" />
                                        </div>
                                        <div class="lg:col-span-3">
                                            <x-input-label value="Tarif" />
                                            {{-- Enter = langsung tambah. $el.blur() dulu karena
                                                 x-text-input-number menyinkron nilainya lewat
                                                 $wire.set saat blur (skill livewire-input-patterns §2). --}}
                                            <x-text-input-number wire:model="formTindakanPrice" placeholder="0"
                                                x-on:keydown.enter.prevent="$el.blur(); $wire.tambahTindakan()" />
                                        </div>
                                        <div class="flex items-end lg:col-span-2">
                                            <x-primary-button type="button" wire:click="tambahTindakan" wire:loading.attr="disabled"
                                                wire:target="tambahTindakan" class="w-full justify-center text-xs">
                                                <span wire:loading.remove wire:target="tambahTindakan">Tambah</span>
                                                <span wire:loading wire:target="tambahTindakan" class="flex items-center gap-1">
                                                    <x-loading /> ...
                                                </span>
                                            </x-primary-button>
                                        </div>
                                    </div>
                                @endunless

                                <div class="overflow-hidden bg-canvas border border-hairline rounded-2xl dark:border-gray-700 dark:bg-gray-900">

                                    <div class="overflow-x-auto">

                                        <table class="w-full text-sm text-left">
                                    <thead class="text-sm font-semibold tracking-wide text-left text-gray-600 uppercase dark:text-gray-300 bg-surface-soft dark:bg-gray-800/50">
                                        <tr>
                                            <th class="px-4 py-3">Kode</th>
                                            <th class="px-4 py-3">Jenis Tindakan</th>
                                            <th class="px-4 py-3 text-right">Tarif</th>
                                            @unless ($isFormLocked)
                                                <th class="px-4 py-3 text-center">Aksi</th>
                                            @endunless
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-hairline-soft dark:divide-gray-800">
                                        @forelse ($rowsTindakan as $row)
                                            <tr wire:key="tindakan-{{ $row['okact_id'] }}" class="transition hover:bg-surface-soft dark:hover:bg-gray-800/40">
                                                <td class="px-4 py-1.5 font-mono text-muted">{{ $row['accdoc_id'] ?? '-' }}</td>
                                                <td class="px-4 py-1.5 text-ink dark:text-gray-200">{{ $row['accdoc_desc'] ?? '-' }}</td>
                                                <td class="px-4 py-1.5 font-semibold text-right text-ink dark:text-gray-200 tabular-nums">
                                                    Rp {{ number_format($row['okact_price'] ?? 0) }}
                                                </td>
                                                @unless ($isFormLocked)
                                                    <td class="px-4 py-1.5 text-center">
                                                        <x-confirm-button variant="danger" action="hapusTindakan({{ $row['okact_id'] }})"
                                                            title="Hapus Tindakan" message="Hapus tindakan ini? Jasa dokter operator akan dihitung ulang."
                                                            confirmText="Ya, hapus" cancelText="Batal" class="!px-2 !py-1 text-xs">
                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                            </svg>
                                                        </x-confirm-button>
                                                    </td>
                                                @endunless
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="4" class="px-4 py-8 text-center text-muted-soft">Belum ada tindakan operasi</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                    @if (!empty($rowsTindakan))
                                        <tfoot class="border-t border-hairline bg-surface-soft dark:bg-gray-800/50 dark:border-gray-700">
                                            <tr>
                                                <td colspan="2" class="px-4 py-3 text-sm font-semibold text-muted dark:text-gray-400">Total</td>
                                                <td class="px-4 py-3 text-sm font-bold text-right text-ink dark:text-white">
                                                    Rp {{ number_format(collect($rowsTindakan)->sum('okact_price')) }}
                                                </td>
                                                @unless ($isFormLocked)
                                                    <td></td>
                                                @endunless
                                            </tr>
                                        </tfoot>
                                    @endif
                                    </table>
                                </div>
                                </div>
                            </div>

                            {{-- TAB 2: BAHAN DAN ALAT --}}
                            <div x-show="tab === 'BahanAlat'" x-cloak>
                                <p class="mb-3 text-xs text-muted dark:text-gray-400">
                                    Total di sini membentuk pos <span class="font-semibold">Bahan &amp; Alat</span> — ditagihkan ke pasien.
                                </p>

                                @unless ($isFormLocked)
                                    <div class="grid grid-cols-1 gap-3 p-3 mb-4 border rounded-xl border-hairline dark:border-gray-700 bg-surface-soft dark:bg-gray-800/40 lg:grid-cols-12">
                                        <div class="lg:col-span-5">
                                            <livewire:lov.product.lov-product target="kamar-operasi-produk" label="Bahan / Alat"
                                                :initialProductId="$formProductId" wire:key="lov-produk-{{ $okReg }}-{{ $formProductId }}" />
                                        </div>
                                        <div class="lg:col-span-2">
                                            <x-input-label value="Qty" />
                                            {{-- Rantai Enter: Qty → Harga → tambah. --}}
                                            <x-text-input-number wire:model="formProductQty" placeholder="1"
                                                x-on:keydown.enter.prevent="$el.blur(); $refs.hargaBahanAlat?.focus()" />
                                        </div>
                                        <div class="lg:col-span-3">
                                            <x-input-label value="Harga" />
                                            <x-text-input-number wire:model="formProductPrice" placeholder="0"
                                                x-ref="hargaBahanAlat"
                                                x-on:keydown.enter.prevent="$el.blur(); $wire.tambahBahanAlat()" />
                                        </div>
                                        <div class="flex items-end lg:col-span-2">
                                            <x-primary-button type="button" wire:click="tambahBahanAlat" wire:loading.attr="disabled"
                                                wire:target="tambahBahanAlat" class="w-full justify-center text-xs">
                                                <span wire:loading.remove wire:target="tambahBahanAlat">Tambah</span>
                                                <span wire:loading wire:target="tambahBahanAlat" class="flex items-center gap-1">
                                                    <x-loading /> ...
                                                </span>
                                            </x-primary-button>
                                        </div>
                                    </div>
                                @endunless

                                <div class="overflow-hidden bg-canvas border border-hairline rounded-2xl dark:border-gray-700 dark:bg-gray-900">

                                    <div class="overflow-x-auto">

                                        <table class="w-full text-sm text-left">
                                    <thead class="text-sm font-semibold tracking-wide text-left text-gray-600 uppercase dark:text-gray-300 bg-surface-soft dark:bg-gray-800/50">
                                        <tr>
                                            <th class="px-4 py-3">Kode</th>
                                            <th class="px-4 py-3">Nama Bahan / Alat</th>
                                            <th class="px-4 py-3 text-right">Qty</th>
                                            <th class="px-4 py-3 text-right">Harga</th>
                                            <th class="px-4 py-3 text-right">Subtotal</th>
                                            @unless ($isFormLocked)
                                                <th class="px-4 py-3 text-center">Aksi</th>
                                            @endunless
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-hairline-soft dark:divide-gray-800">
                                        @forelse ($rowsBahanAlat as $row)
                                            <tr wire:key="bahan-alat-{{ $row['okobat_id'] }}" class="transition hover:bg-surface-soft dark:hover:bg-gray-800/40">
                                                <td class="px-4 py-1.5 font-mono text-muted">{{ $row['product_id'] ?? '-' }}</td>
                                                <td class="px-4 py-1.5 text-ink dark:text-gray-200">{{ $row['product_name'] ?? '-' }}</td>
                                                <td class="px-4 py-1.5 text-right text-ink dark:text-gray-200 tabular-nums">{{ number_format($row['okobat_qty'] ?? 0) }}</td>
                                                <td class="px-4 py-1.5 text-right text-ink dark:text-gray-200 tabular-nums">Rp {{ number_format($row['okobat_price'] ?? 0) }}</td>
                                                <td class="px-4 py-1.5 font-semibold text-right text-ink dark:text-gray-200 tabular-nums">
                                                    Rp {{ number_format((int) ($row['okobat_qty'] ?? 0) * (int) ($row['okobat_price'] ?? 0)) }}
                                                </td>
                                                @unless ($isFormLocked)
                                                    <td class="px-4 py-1.5 text-center">
                                                        <x-confirm-button variant="danger" action="hapusBahanAlat({{ $row['okobat_id'] }})"
                                                            title="Hapus Bahan/Alat" message="Hapus baris bahan/alat ini? Pos Bahan &amp; Alat akan dihitung ulang."
                                                            confirmText="Ya, hapus" cancelText="Batal" class="!px-2 !py-1 text-xs">
                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                            </svg>
                                                        </x-confirm-button>
                                                    </td>
                                                @endunless
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="6" class="px-4 py-8 text-center text-muted-soft">Belum ada bahan dan alat</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                    @if (!empty($rowsBahanAlat))
                                        <tfoot class="border-t border-hairline bg-surface-soft dark:bg-gray-800/50 dark:border-gray-700">
                                            <tr>
                                                <td colspan="4" class="px-4 py-3 text-sm font-semibold text-muted dark:text-gray-400">Total</td>
                                                <td class="px-4 py-3 text-sm font-bold text-right text-ink dark:text-white">
                                                    Rp {{ number_format(collect($rowsBahanAlat)->sum(fn($bahanAlat) => (int) ($bahanAlat['okobat_qty'] ?? 0) * (int) ($bahanAlat['okobat_price'] ?? 0))) }}
                                                </td>
                                                @unless ($isFormLocked)
                                                    <td></td>
                                                @endunless
                                            </tr>
                                        </tfoot>
                                    @endif
                                    </table>
                                </div>
                                </div>
                            </div>

                            {{-- TAB 3: OM LOP --}}
                            <div x-show="tab === 'Omlop'" x-cloak>
                                <p class="mb-3 text-xs text-muted dark:text-gray-400">
                                    <span class="font-semibold">Tidak ditagihkan ke pasien.</span>
                                    Daftar <span class="font-semibold">orang</span> yang bertugas beserta jasanya —
                                    yang masuk tagihan adalah pos <span class="font-semibold">OM LOP</span> di atas.
                                    Kolom <span class="font-semibold">Jasa</span> = jasa saat bertugas;
                                    <span class="font-semibold">On Call</span> = tambahan karena dipanggil di luar jadwal.
                                    Keduanya jasa petugas, bukan biaya pasien.
                                </p>

                                @unless ($isFormLocked)
                                    <div class="grid grid-cols-1 gap-3 p-3 mb-4 border rounded-xl border-hairline dark:border-gray-700 bg-surface-soft dark:bg-gray-800/40 lg:grid-cols-12">
                                        <div class="lg:col-span-10">
                                            <livewire:lov.karyawan-oncall.lov-karyawan-oncall target="kamar-operasi-omlop" label="Petugas OM LOP"
                                                :initialEmpId="$formOmlopEmpId" wire:key="lov-omlop-{{ $okReg }}-{{ $formOmlopEmpId }}" />
                                        </div>
                                        <div class="flex items-end lg:col-span-2">
                                            <x-primary-button type="button" wire:click="tambahOmlop" wire:loading.attr="disabled"
                                                wire:target="tambahOmlop" class="w-full justify-center text-xs">
                                                <span wire:loading.remove wire:target="tambahOmlop">Tambah</span>
                                                <span wire:loading wire:target="tambahOmlop" class="flex items-center gap-1">
                                                    <x-loading /> ...
                                                </span>
                                            </x-primary-button>
                                        </div>
                                    </div>
                                @endunless

                                <div class="overflow-hidden bg-canvas border border-hairline rounded-2xl dark:border-gray-700 dark:bg-gray-900">

                                    <div class="overflow-x-auto">

                                        <table class="w-full text-sm text-left">
                                    <thead class="text-sm font-semibold tracking-wide text-left text-gray-600 uppercase dark:text-gray-300 bg-surface-soft dark:bg-gray-800/50">
                                        <tr>
                                            <th class="px-4 py-3">NIK</th>
                                            <th class="px-4 py-3">Nama Petugas</th>
                                            <th class="px-4 py-3 text-right" title="Jasa petugas saat bertugas — tidak ditagihkan ke pasien">Jasa Bertugas</th>
                                            <th class="px-4 py-3 text-right" title="Tambahan jasa karena dipanggil di luar jadwal — tidak ditagihkan ke pasien">Jasa On Call</th>
                                            @unless ($isFormLocked)
                                                <th class="px-4 py-3 text-center">Aksi</th>
                                            @endunless
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-hairline-soft dark:divide-gray-800">
                                        @forelse ($rowsOmlop as $indeksOmlop => $row)
                                            <tr wire:key="omlop-{{ $row['omlop_dtl'] }}" class="transition hover:bg-surface-soft dark:hover:bg-gray-800/40">
                                                <td class="px-4 py-1.5 font-mono text-muted">{{ $row['emp_id'] ?? '-' }}</td>
                                                <td class="px-4 py-1.5 text-ink dark:text-gray-200">{{ $row['emp_name'] ?? '-' }}</td>
                                                @foreach (['omlop_fee', 'oncallomlop_fee'] as $kolomJasa)
                                                    <td class="px-4 py-1.5 text-right">
                                                        @if ($isFormLocked)
                                                            <span class="text-ink dark:text-gray-200 tabular-nums">
                                                                {{ $row[$kolomJasa] === null ? '—' : 'Rp ' . number_format($row[$kolomJasa]) }}
                                                            </span>
                                                        @else
                                                            {{-- Simpan dipicu hook updatedRowsOmlop() saat blur. --}}
                                                            <x-text-input-number wire:model="rowsOmlop.{{ $indeksOmlop }}.{{ $kolomJasa }}"
                                                                placeholder="0" />
                                                        @endif
                                                    </td>
                                                @endforeach
                                                @unless ($isFormLocked)
                                                    <td class="px-4 py-1.5 text-center">
                                                        <x-confirm-button variant="danger" action="hapusOmlop({{ $row['omlop_dtl'] }})"
                                                            title="Hapus Crew OM LOP" message="Hapus petugas ini dari daftar crew OM LOP?"
                                                            confirmText="Ya, hapus" cancelText="Batal" class="!px-2 !py-1 text-xs">
                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                            </svg>
                                                        </x-confirm-button>
                                                    </td>
                                                @endunless
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="5" class="px-4 py-8 text-center text-muted-soft">Belum ada crew OM LOP</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                    </table>
                                </div>
                                </div>
                            </div>

                        </div>
                    </div>

                    </div>{{-- /grid: Crew & Jasa (1) : tab detail (1) --}}

                </div>
            </div>

            {{-- MODAL FOOTER --}}
            <div class="sticky bottom-0 z-10 px-6 py-4 bg-canvas border-t border-hairline dark:bg-gray-900 dark:border-gray-700">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    @php $statusOk = $headerData['ok_status'] ?? ''; @endphp

                    {{-- KIRI: Batal Transaksi (Admin / Supervisor Penunjang) --}}
                    @php
                        // Kalimat disusun per aksi: tombol Batal tidak boleh memakai
                        // alasan yang ditulis untuk transfer, dan sebaliknya.
                        $aksiTerkunci = $statusOk === 'L' ? 'batal' : 'transfer';
                        $pesanTerkunci = $this->pesanTerkunci($aksiTerkunci);
                    @endphp
                    <div class="flex items-center gap-2">
                        @if ($statusOk === 'L')
                            @hasanyrole(['Admin', 'Supervisor Penunjang'])
                                <span title="{{ $pesanTerkunci }}">
                                    <x-confirm-button variant="danger" action="batalkanTransaksi()"
                                        :disabled="$indukTerkunci" title="Batalkan Transaksi"
                                        message="Batalkan transfer biaya operasi ini? Seluruh baris biaya di rawat inap akan dihapus dan status kembali ke Proses Transaksi."
                                        confirmText="Ya, batalkan" cancelText="Batal" class="text-xs">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                                        </svg>
                                        Batal Transaksi
                                    </x-confirm-button>
                                </span>
                            @endhasanyrole
                        @endif

                        @if ($indukTerkunci)
                            <span class="inline-flex items-center gap-1 text-xs italic text-error dark:text-red-400">
                                <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                </svg>
                                {{ $pesanTerkunci }}
                            </span>
                        @endif
                    </div>

                    {{-- KANAN: Aksi utama --}}
                    <div class="flex items-center gap-2">
                        @if ($statusOk === 'A')
                            <x-secondary-button type="button" wire:click="hitungTarifOk" wire:loading.attr="disabled"
                                wire:target="hitungTarifOk" class="text-xs">
                                <span wire:loading.remove wire:target="hitungTarifOk">Hitung Tarif OK</span>
                                <span wire:loading wire:target="hitungTarifOk" class="flex items-center gap-1.5">
                                    <x-loading /> Menghitung...
                                </span>
                            </x-secondary-button>

                            <span title="{{ $pesanTerkunci }}">
                                <x-confirm-button variant="primary" action="transferBiayaInap()"
                                    :disabled="$indukTerkunci" title="Transfer Biaya ke Rawat Inap"
                                    message="Transfer seluruh pos tarif operasi ini ke biaya rawat inap? Setelah ditransfer, tarif tidak bisa diubah lagi."
                                    confirmText="Ya, transfer" cancelText="Batal" class="text-xs">
                                    Trf Biaya-INAP
                                </x-confirm-button>
                            </span>
                        @elseif ($statusOk === 'L')
                            <x-badge variant="success">Biaya sudah masuk tagihan rawat inap</x-badge>
                        @endif

                        <x-secondary-button type="button" wire:click="closeActions">Tutup</x-secondary-button>
                    </div>
                </div>
            </div>

        </div>
    </x-modal>
</div>
