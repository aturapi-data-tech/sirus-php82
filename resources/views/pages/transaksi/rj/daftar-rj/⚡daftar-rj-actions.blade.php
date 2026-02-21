<?php

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;

use App\Http\Traits\Txn\Rj\EmrRJTrait;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;
new class extends Component {
    use EmrRJTrait, WithRenderVersioningTrait;

    public string $formMode = 'create'; // create|edit
    public bool $isFormLocked = false;

    public ?string $rjNo = null;
    public $disabledPropertyRjStatus = false;
    public ?string $kronisNotice = null;
    public array $dataDaftarPoliRJ = ['passStatus' => 'O'];

    // renderVersions
    public array $renderVersions = [];
    protected array $renderAreas = ['modal', 'pasien', 'dokter'];

    public string $klaimId = 'UM';
    public array $klaimOptions = [['klaimId' => 'UM', 'klaimDesc' => 'UMUM'], ['klaimId' => 'JM', 'klaimDesc' => 'BPJS'], ['klaimId' => 'JR', 'klaimDesc' => 'JASA RAHARJA'], ['klaimId' => 'JML', 'klaimDesc' => 'Asuransi Lain'], ['klaimId' => 'KR', 'klaimDesc' => 'Kronis']];

    public string $kunjunganId = '1';
    public array $kunjunganOptions = [['kunjunganId' => '1', 'kunjunganDesc' => 'Rujukan FKTP'], ['kunjunganId' => '2', 'kunjunganDesc' => 'Rujukan Internal'], ['kunjunganId' => '3', 'kunjunganDesc' => 'Kontrol'], ['kunjunganId' => '4', 'kunjunganDesc' => 'Rujukan Antar RS']];

    // Kontrol 1/2 (untuk kunjungan Kontrol)
    public string $kontrol12 = '1';
    public array $kontrol12Options = [['kontrol12' => '1', 'kontrol12Desc' => 'Faskes Tingkat 1'], ['kontrol12' => '2', 'kontrol12Desc' => 'Faskes Tingkat 2 RS']];

    // Internal 1/2 (untuk kunjungan Internal)
    public string $internal12 = '1';
    public array $internal12Options = [['internal12' => '1', 'internal12Desc' => 'Faskes Tingkat 1'], ['internal12' => '2', 'internal12Desc' => 'Faskes Tingkat 2 RS']];

    /* ===============================
     | OPEN CREATE
     =============================== */
    #[On('daftar-rj.openCreate')]
    public function openCreate(): void
    {
        $this->resetForm();
        $this->formMode = 'create';
        $this->resetValidation();

        $this->dataDaftarPoliRJ = $this->getDefaultRJTemplate();

        // ===============================
        // Set Tanggal RJ (hari ini)
        // ===============================
        $now = Carbon::now();
        $this->dataDaftarPoliRJ['rjDate'] = $now->format('d/m/Y H:i:s');
        // ===============================
        // Set Shift berdasarkan jam sekarang
        // ===============================
        $nowTime = $now->format('H:i:s');

        $findShift = DB::table('rstxn_shiftctls')
            ->select('shift')
            ->whereRaw('? BETWEEN shift_start AND shift_end', [$nowTime])
            ->first();

        $this->dataDaftarPoliRJ['shift'] = $findShift->shift ?? 3;

        $this->dispatch('open-modal', name: 'rj-actions');
    }

    /* ===============================
     | OPEN EDIT
     =============================== */
    #[On('daftar-rj.openEdit')]
    public function openEdit(string $rjNo): void
    {
        // Reset state dulu
        $this->resetForm();
        $this->formMode = 'edit';
        $this->resetValidation();

        // Ambil data JSON dari DB
        $data = $this->findDataRJ($rjNo);

        if (!$data) {
            $this->dispatch('toast', type: 'error', message: 'Data Rawat Jalan tidak ditemukan.');
            return;
        }
        if ($this->checkRJStatus($rjNo)) {
            $this->isFormLocked = true;
            $this->dispatch('toast', type: 'warning', message: 'Data Rawat Jalan ini sudah selesai dan tidak bisa diubah.');
        }
        // Merge dengan template default supaya struktur tetap konsisten
        $this->dataDaftarPoliRJ = $data;

        // Sync property turunan agar radio/toggle tetap aktif
        $this->syncFromDataDaftarPoliRJ();

        // Buka modal
        $this->dispatch('open-modal', name: 'rj-actions');
    }

    public function closeModal(): void
    {
        $this->resetValidation();
        $this->resetForm();
        $this->dispatch('close-modal', name: 'rj-actions');
    }

    /* ===============================
     | SAVE
     =============================== */
    // public function save(): void lama
    // {
    //     // Set data primer (RJno, NoBooking, NoAntrian, dll)
    //     $this->setDataPrimer();
    //     // Validasi data Rawat Jalan
    //     $this->validateDataRJ();

    //     $rjNo = $this->dataDaftarPoliRJ['rjNo'] ?? null;
    //     if (!$rjNo) {
    //         $this->dispatch('toast', type: 'error', message: 'RJ No kosong.');
    //         return;
    //     }

    //     $lockKey = "lock:rstxn_rjhdrs:{$rjNo}";

    //     try {
    //         Cache::lock($lockKey, 15)->block(5, function () use ($rjNo) {
    //             DB::transaction(function () use ($rjNo) {
    //                 // ============================================
    //                 // PREPARE PAYLOAD
    //                 // ============================================
    //                 $payload = [
    //                     'rj_no' => $rjNo,
    //                     'rj_date' => DB::raw("to_date('" . $this->dataDaftarPoliRJ['rjDate'] . "','dd/mm/yyyy hh24:mi:ss')"),
    //                     'reg_no' => $this->dataDaftarPoliRJ['regNo'],
    //                     'nobooking' => $this->dataDaftarPoliRJ['noBooking'],
    //                     'no_antrian' => $this->dataDaftarPoliRJ['noAntrian'],
    //                     'klaim_id' => $this->dataDaftarPoliRJ['klaimId'],
    //                     'poli_id' => $this->dataDaftarPoliRJ['poliId'],
    //                     'dr_id' => $this->dataDaftarPoliRJ['drId'],
    //                     'shift' => $this->dataDaftarPoliRJ['shift'],
    //                     'txn_status' => $this->dataDaftarPoliRJ['txnStatus'],
    //                     'rj_status' => $this->dataDaftarPoliRJ['rjStatus'],
    //                     'erm_status' => $this->dataDaftarPoliRJ['ermStatus'],
    //                     'pass_status' => $this->dataDaftarPoliRJ['passStatus'],
    //                     'cek_lab' => $this->dataDaftarPoliRJ['cekLab'],
    //                     'sl_codefrom' => $this->dataDaftarPoliRJ['slCodeFrom'],
    //                     'kunjungan_internal_status' => $this->dataDaftarPoliRJ['kunjunganInternalStatus'],
    //                     'waktu_masuk_pelayanan' => DB::raw("to_date('" . $this->dataDaftarPoliRJ['rjDate'] . "','dd/mm/yyyy hh24:mi:ss')"),
    //                     'vno_sep' => $this->dataDaftarPoliRJ['sep']['noSep'] ?? '',
    //                 ];

    //                 // ============================================
    //                 // INSERT/UPDATE TABLE
    //                 // ============================================
    //                 if ($this->formMode === 'create') {
    //                     DB::table('rstxn_rjhdrs')->insert($payload);
    //                     $message = 'Data Rawat Jalan berhasil disimpan.';
    //                 } else {
    //                     DB::table('rstxn_rjhdrs')->where('rj_no', $rjNo)->update($payload);
    //                     $message = 'Data Rawat Jalan berhasil diperbarui.';
    //                 }

    //                 // ============================================
    //                 // UPDATE JSON DENGAN DATA TERBARU
    //                 // ============================================
    //                 // Untuk CREATE: langsung pakai data form
    //                 // Untuk EDIT: merge data form + data fresh dari database

    //                 if ($this->formMode === 'create') {
    //                     // Data baru, langsung pakai dari form
    //                     $mergedRJ = $this->dataDaftarPoliRJ;
    //                 } else {
    //                     $updatedData = $this->findDataRJ($rjNo);
    //                     // Whitelist field dari form
    //                     $allowed = ['rjNo', 'regNo', 'regName', 'drId', 'drDesc', 'poliId', 'poliDesc', 'klaimId', 'klaimStatus', 'kunjunganId', 'rjDate', 'shift', 'noAntrian', 'noBooking', 'slCodeFrom', 'passStatus', 'rjStatus', 'txnStatus', 'ermStatus', 'cekLab', 'kunjunganInternalStatus', 'noReferensi', 'postInap', 'internal12', 'internal12Desc', 'internal12Options', 'kontrol12', 'kontrol12Desc', 'kontrol12Options', 'taskIdPelayanan', 'sep'];

    //                     // Ambil field dari form yang diizinkan
    //                     $formData = array_intersect_key($this->dataDaftarPoliRJ, array_flip($allowed));

    //                     // Merge: prioritas data dari database, tapi timpa dengan form untuk field tertentu
    //                     $mergedRJ = array_replace_recursive($updatedData, $formData);
    //                 }

    //                 // Safety: pastikan rjNo tetap sama
    //                 $mergedRJ['rjNo'] = $rjNo;

    //                 // Simpan JSON
    //                 $this->updateJsonRJ($rjNo, $mergedRJ);

    //                 // Reset & notifikasi
    //                 $this->resetValidation();
    //                 $this->dispatch('toast', type: 'success', message: $message);
    //                 $this->dispatch('master.daftar-rj.saved');
    //             }); // End transaction
    //         }); // End cache lock
    //     } catch (\Throwable $e) {
    //         $this->dispatch('toast', type: 'error', message: 'Gagal menyimpan data: ' . $e->getMessage());
    //     }
    // }

    /**
     * Helper untuk mendapatkan label status
     */
    private function getStatusLabel(string $status): string
    {
        return match ($status) {
            'L' => 'selesai',
            'F' => 'dibatalkan',
            'I' => 'transfer',
            default => 'tidak aktif',
        };
    }

    private function setDataPrimer(): void
    {
        // Shortcut biar lebih rapi
        $data = &$this->dataDaftarPoliRJ;

        /*
    |--------------------------------------------------------------------------
    | 1. Status Kunjungan Internal
    |--------------------------------------------------------------------------
    */
        if (!empty($data['kunjunganId']) && $data['kunjunganId'] == 2) {
            $data['kunjunganInternalStatus'] = '1';
        }

        /*
    |--------------------------------------------------------------------------
    | 2. Generate No Booking
    |--------------------------------------------------------------------------
    */
        if (empty($data['noBooking'])) {
            $data['noBooking'] = Carbon::now()->format('YmdHis') . 'RSIM';
        }

        /*
    |--------------------------------------------------------------------------
    | 3. Generate No RJ
    |--------------------------------------------------------------------------
    */
        if (empty($data['rjNo'])) {
            $maxRjNo = DB::table('rstxn_rjhdrs')->max('rj_no');
            $data['rjNo'] = $maxRjNo ? $maxRjNo + 1 : 1;
        }

        /*
    |--------------------------------------------------------------------------
    | 4. Generate No Antrian
    |--------------------------------------------------------------------------
    */
        if (empty($data['noAntrian'])) {
            if (!empty($data['klaimId']) && $data['klaimId'] !== 'KR') {
                if (!empty($data['rjDate']) && !empty($data['drId'])) {
                    $tglAntrian = Carbon::createFromFormat('d/m/Y H:i:s', $data['rjDate'])->format('dmY');

                    $noUrutAntrian = DB::table('rstxn_rjhdrs')
                        ->where('dr_id', $data['drId'])
                        ->where('klaim_id', '!=', 'KR')
                        ->whereRaw("to_char(rj_date, 'ddmmyyyy') = ?", [$tglAntrian])
                        ->count();

                    $data['noAntrian'] = $noUrutAntrian + 1;
                }
            } else {
                // Pasien Kronis
                $data['noAntrian'] = 999;
            }
        }

        /*
    |--------------------------------------------------------------------------
    | 5. Task ID Pelayanan (Fix Bug)
    |--------------------------------------------------------------------------
    */
        if (empty($data['taskIdPelayanan'])) {
            $data['taskIdPelayanan'] = [];
        }

        if (empty($data['taskIdPelayanan']['taskId3']) && !empty($data['rjDate'])) {
            $data['taskIdPelayanan']['taskId3'] = $data['rjDate'];
        }
    }

    private function validateDataRJ(): array
    {
        // ===========================
        // Attributes (nama field user-friendly)
        // ===========================
        $attributes = [
            // Data Rawat Jalan
            'dataDaftarPoliRJ.regNo' => 'Nomor Registrasi Pasien',
            'dataDaftarPoliRJ.drId' => 'ID Dokter',
            'dataDaftarPoliRJ.drDesc' => 'Nama Dokter',
            'dataDaftarPoliRJ.poliId' => 'ID Poli',
            'dataDaftarPoliRJ.poliDesc' => 'Nama Poli',
            'dataDaftarPoliRJ.kddrbpjs' => 'Kode Dokter BPJS',
            'dataDaftarPoliRJ.kdpolibpjs' => 'Kode Poli BPJS',
            'dataDaftarPoliRJ.rjDate' => 'Tanggal Kunjungan',
            'dataDaftarPoliRJ.rjNo' => 'Nomor Kunjungan',
            'dataDaftarPoliRJ.shift' => 'Shift',
            'dataDaftarPoliRJ.noAntrian' => 'Nomor Antrian',
            'dataDaftarPoliRJ.noBooking' => 'Nomor Booking',
            'dataDaftarPoliRJ.slCodeFrom' => 'Kode Sumber',
            'dataDaftarPoliRJ.passStatus' => 'Status Pasien',
            'dataDaftarPoliRJ.rjStatus' => 'Status Rawat Jalan',
            'dataDaftarPoliRJ.txnStatus' => 'Status Transaksi',
            'dataDaftarPoliRJ.ermStatus' => 'Status EMR',
            'dataDaftarPoliRJ.cekLab' => 'Cek Laboratorium',
            'dataDaftarPoliRJ.kunjunganInternalStatus' => 'Status Kunjungan Internal',
            'dataDaftarPoliRJ.noReferensi' => 'Nomor Referensi',
            'dataDaftarPoliRJ.klaimId' => 'ID Klaim',

            // Data Pasien
        ];

        // ===========================
        // Custom Messages
        // ===========================
        $customMessages = [
            // ---- Data Rawat Jalan ----
            'dataDaftarPoliRJ.regNo.required' => ':attribute wajib diisi.',
            'dataDaftarPoliRJ.regNo.exists' => ':attribute tidak ditemukan dalam database pasien.',
            'dataDaftarPoliRJ.drId.required' => 'Dokter wajib dipilih.',
            'dataDaftarPoliRJ.drId.exists' => 'Dokter yang dipilih tidak valid.',
            'dataDaftarPoliRJ.drDesc.required' => 'Nama Dokter wajib diisi.',
            'dataDaftarPoliRJ.poliId.required' => 'Poli wajib dipilih.',
            'dataDaftarPoliRJ.poliId.exists' => 'Poli yang dipilih tidak valid.',
            'dataDaftarPoliRJ.poliDesc.required' => 'Nama Poli wajib diisi.',
            'dataDaftarPoliRJ.kddrbpjs.string' => ':attribute harus berupa teks.',
            'dataDaftarPoliRJ.kdpolibpjs.string' => ':attribute harus berupa teks.',
            'dataDaftarPoliRJ.rjDate.required' => ':attribute wajib diisi.',
            'dataDaftarPoliRJ.rjDate.date_format' => ':attribute harus dalam format: dd/mm/yyyy HH:ii:ss (contoh: 25/12/2024 13:30:00).',
            'dataDaftarPoliRJ.rjNo.required' => ':attribute wajib diisi.',
            'dataDaftarPoliRJ.rjNo.numeric' => ':attribute harus berupa angka.',
            'dataDaftarPoliRJ.shift.required' => ':attribute wajib diisi.',
            'dataDaftarPoliRJ.shift.in' => ':attribute harus salah satu dari: 1, 2, atau 3.',
            'dataDaftarPoliRJ.noAntrian.required' => ':attribute wajib diisi.',
            'dataDaftarPoliRJ.noAntrian.numeric' => ':attribute harus berupa angka.',
            'dataDaftarPoliRJ.noAntrian.min' => ':attribute minimal :min.',
            'dataDaftarPoliRJ.noAntrian.max' => ':attribute maksimal :max.',
            'dataDaftarPoliRJ.noAntrian.in' => ':attribute untuk pasien KRONIS harus 999.',
            'dataDaftarPoliRJ.noBooking.required' => ':attribute wajib diisi.',
            'dataDaftarPoliRJ.noBooking.string' => ':attribute harus berupa teks.',
            'dataDaftarPoliRJ.slCodeFrom.required' => ':attribute wajib diisi.',
            'dataDaftarPoliRJ.slCodeFrom.in' => ':attribute harus salah satu dari: 01 atau 02.',
            'dataDaftarPoliRJ.passStatus.in' => ':attribute harus salah satu dari: N (Baru) atau O (Lama).',
            'dataDaftarPoliRJ.rjStatus.required' => ':attribute wajib diisi.',
            'dataDaftarPoliRJ.rjStatus.in' => ':attribute harus salah satu dari: A (Antrian), L (Selesai), I (Transfer), atau F (Batal).',
            'dataDaftarPoliRJ.txnStatus.required' => ':attribute wajib diisi.',
            'dataDaftarPoliRJ.txnStatus.in' => ':attribute harus salah satu dari: A (Aktif), P (Proses), atau C (Selesai).',
            'dataDaftarPoliRJ.ermStatus.required' => ':attribute wajib diisi.',
            'dataDaftarPoliRJ.ermStatus.in' => ':attribute harus salah satu dari: A (Aktif), P (Proses), atau C (Selesai).',
            'dataDaftarPoliRJ.cekLab.required' => ':attribute wajib diisi.',
            'dataDaftarPoliRJ.cekLab.in' => ':attribute harus salah satu dari: 0 (Tidak) atau 1 (Ya).',
            'dataDaftarPoliRJ.kunjunganInternalStatus.required' => ':attribute wajib diisi.',
            'dataDaftarPoliRJ.kunjunganInternalStatus.in' => ':attribute harus salah satu dari: 0 (Tidak) atau 1 (Ya).',
            'dataDaftarPoliRJ.klaimId.required' => ':attribute wajib diisi.',
            'dataDaftarPoliRJ.klaimId.exists' => ':attribute tidak ditemukan dalam database klaim.',
            'dataDaftarPoliRJ.noReferensi.required' => ':attribute wajib diisi.',
            'dataDaftarPoliRJ.noReferensi.string' => ':attribute harus berupa teks.',
            'dataDaftarPoliRJ.noReferensi.min' => ':attribute minimal :min karakter.',
            'dataDaftarPoliRJ.noReferensi.max' => ':attribute maksimal :max karakter.',
        ];

        // ===========================
        // Rules Validasi
        // ===========================
        $rules = [
            // Data Rawat Jalan
            'dataDaftarPoliRJ.regNo' => 'bail|required|exists:rsmst_pasiens,reg_no',
            'dataDaftarPoliRJ.drId' => 'required|exists:rsmst_doctors,dr_id',
            'dataDaftarPoliRJ.drDesc' => 'required|string',
            'dataDaftarPoliRJ.poliId' => 'required|exists:rsmst_polis,poli_id',
            'dataDaftarPoliRJ.poliDesc' => 'required|string',
            'dataDaftarPoliRJ.kddrbpjs' => 'nullable|string',
            'dataDaftarPoliRJ.kdpolibpjs' => 'nullable|string',
            'dataDaftarPoliRJ.rjDate' => 'required|date_format:d/m/Y H:i:s',
            'dataDaftarPoliRJ.rjNo' => 'required|numeric',
            'dataDaftarPoliRJ.shift' => 'required|in:1,2,3',
            'dataDaftarPoliRJ.noAntrian' => 'required|numeric|min:1|max:999',
            'dataDaftarPoliRJ.noBooking' => 'required|string',
            'dataDaftarPoliRJ.slCodeFrom' => 'required|in:01,02',
            'dataDaftarPoliRJ.passStatus' => 'nullable|in:N,O',
            'dataDaftarPoliRJ.rjStatus' => 'required|in:A,L,I,F',
            'dataDaftarPoliRJ.txnStatus' => 'required|in:A,L,H',
            'dataDaftarPoliRJ.ermStatus' => 'required|in:A,L',
            'dataDaftarPoliRJ.cekLab' => 'required|in:0,1',
            'dataDaftarPoliRJ.kunjunganInternalStatus' => 'required|in:0,1',
            'dataDaftarPoliRJ.noReferensi' => 'nullable|string|min:3|max:19',
            'dataDaftarPoliRJ.klaimId' => 'required|exists:rsmst_klaimtypes,klaim_id',
        ];

        // Validasi khusus untuk BPJS
        if ($this->dataDaftarPoliRJ['klaimStatus'] === 'BPJS') {
            $rules['dataDaftarPoliRJ.noReferensi'] = 'bail|required|string|min:3|max:19';
        }

        // Validasi untuk pasien KRONIS
        if ($this->dataDaftarPoliRJ['klaimStatus'] === 'KRONIS') {
            $rules['dataDaftarPoliRJ.noAntrian'] = 'required|numeric|in:999';
        }

        // ===========================
        // Proses Validasi
        // ===========================
        return $this->validate($rules, $customMessages, $attributes);
    }

    protected function resetForm(): void
    {
        $this->reset(['rjNo', 'dataDaftarPoliRJ']);
        // Reset semua LOV ke versi 0
        $this->resetVersion();

        // Reset default pilihan
        $this->klaimId = 'UM';
        $this->kunjunganId = '1';
        $this->kontrol12 = '1';
        $this->internal12 = '1';

        $this->formMode = 'create';

        $this->dataDaftarPoliRJ['rjDate'] = Carbon::now()->format('d/m/Y H:i:s');
        $this->dataDaftarPoliRJ['regNo'] = '';
        $this->dataDaftarPoliRJ['regName'] = '';
        $this->dataDaftarPoliRJ['drId'] = null;
        $this->dataDaftarPoliRJ['drDesc'] = '';
        $this->dataDaftarPoliRJ['poliId'] = null;
        $this->dataDaftarPoliRJ['poliDesc'] = '';

        $this->dataDaftarPoliRJ['passStatus'] = 'O';
    }

    #[On('lov.selected.rjFormPasien')]
    public function rjFormPasien(string $target, array $payload): void
    {
        $this->dataDaftarPoliRJ['regNo'] = $payload['reg_no'] ?? '';
        $this->dataDaftarPoliRJ['regName'] = $payload['reg_name'] ?? '';
        $this->incrementVersion('pasien');
        $this->incrementVersion('modal');
    }

    #[On('lov.selected.rjFormDokter')]
    public function rjFormDokter(string $target, array $payload): void
    {
        $this->dataDaftarPoliRJ['drId'] = $payload['dr_id'] ?? '';
        $this->dataDaftarPoliRJ['drDesc'] = $payload['dr_name'] ?? '';
        $this->dataDaftarPoliRJ['poliId'] = $payload['poli_id'] ?? '';
        $this->dataDaftarPoliRJ['poliDesc'] = $payload['poli_desc'] ?? '';
        $this->incrementVersion('dokter');
        $this->incrementVersion('modal');
    }

    public function updated($name, $value)
    {
        // Increment LOV saat field tertentu berubah
        if ($name === 'dataDaftarPoliRJ.regNo') {
            $this->incrementVersion('pasien');
            $this->incrementVersion('modal');
        }

        if ($name === 'dataDaftarPoliRJ.drId') {
            $this->incrementVersion('dokter');
            $this->incrementVersion('modal');
        }

        if (in_array($name, ['klaimId', 'kunjunganId', 'kontrol12', 'internal12'])) {
            $this->incrementVersion('modal');
        }

        // Klaim
        if ($name === 'klaimId') {
            $this->klaimId = $value;
            $this->dataDaftarPoliRJ['klaimId'] = $value;
            $this->dataDaftarPoliRJ['klaimStatus'] = DB::table('rsmst_klaimtypes')->where('klaim_id', $value)->value('klaim_status') ?? 'UMUM';

            // Reset kunjunganId dan kontrol/internal
            $this->kunjunganId = '1';
            $this->dataDaftarPoliRJ['kunjunganId'] = '1';
            $this->resetKontrolInternal();
        }

        // Kunjungan
        if ($name === 'kunjunganId') {
            $this->kunjunganId = $value;
            $this->dataDaftarPoliRJ['kunjunganId'] = $value;

            // Reset post inap
            $this->dataDaftarPoliRJ['postInap'] = false;

            $this->resetKontrolInternal();
        }

        // Kontrol12
        if ($name === 'kontrol12') {
            $this->kontrol12 = $value;
            $this->dataDaftarPoliRJ['kontrol12'] = $value;
            $this->dataDaftarPoliRJ['kontrol12Desc'] = collect($this->kontrol12Options)->first(fn($option) => $option['kontrol12'] === $value)['kontrol12Desc'] ?? '-';
        }

        // Internal12
        if ($name === 'internal12') {
            $this->internal12 = $value;
            $this->dataDaftarPoliRJ['internal12'] = $value;
            $this->dataDaftarPoliRJ['internal12Desc'] = collect($this->internal12Options)->first(fn($option) => $option['internal12'] === $value)['internal12Desc'] ?? '-';
        }
    }

    /**
     * Reset kontrol12 dan internal12 ke default
     */
    private function resetKontrolInternal()
    {
        $this->kontrol12 = '1';
        $this->internal12 = '1';
        $this->dataDaftarPoliRJ['kontrol12'] = $this->kontrol12;
        $this->dataDaftarPoliRJ['internal12'] = $this->internal12;

        $this->dataDaftarPoliRJ['kontrol12Desc'] = collect($this->kontrol12Options)->first(fn($option) => $option['kontrol12'] === $this->kontrol12)['kontrol12Desc'] ?? '-';
        $this->dataDaftarPoliRJ['internal12Desc'] = collect($this->internal12Options)->first(fn($option) => $option['internal12'] === $this->internal12)['internal12Desc'] ?? '-';
    }

    private function syncFromDataDaftarPoliRJ(): void
    {
        // Klaim
        $this->klaimId = $this->dataDaftarPoliRJ['klaimId'] ?? 'UM';

        // Kunjungan
        $this->kunjunganId = $this->dataDaftarPoliRJ['kunjunganId'] ?? '1';

        // Kontrol 1/2
        $this->kontrol12 = $this->dataDaftarPoliRJ['kontrol12'] ?? '1';

        // Internal 1/2
        $this->internal12 = $this->dataDaftarPoliRJ['internal12'] ?? '1';

        // Optional: pastikan desc ikut terisi kalau ada
        $this->dataDaftarPoliRJ['kontrol12Desc'] = collect($this->kontrol12Options)->first(fn($o) => $o['kontrol12'] === $this->kontrol12)['kontrol12Desc'] ?? '-';

        $this->dataDaftarPoliRJ['internal12Desc'] = collect($this->internal12Options)->first(fn($o) => $o['internal12'] === $this->internal12)['internal12Desc'] ?? '-';
    }

    public function openVclaimModal()
    {
        // Validasi data yang diperlukan
        if (empty($this->dataDaftarPoliRJ['regNo'])) {
            $this->dispatch('toast', type: 'error', message: 'Silakan pilih pasien terlebih dahulu.');
            return;
        }

        // Check if patient is BPJS
        $isBpjs = ($this->dataDaftarPoliRJ['klaimStatus'] ?? '') === 'BPJS' || ($this->dataDaftarPoliRJ['klaimId'] ?? '') === 'JM';

        if (!$isBpjs) {
            $this->dispatch('toast', type: 'error', message: 'Fitur SEP hanya untuk pasien BPJS (Jenis Klaim JM).');
            return;
        }

        if (empty($this->dataDaftarPoliRJ['drId'])) {
            $this->dispatch('toast', type: 'error', message: 'Silakan pilih dokter/poli terlebih dahulu.');
            return;
        }

        // Ambil data SEP dari dataDaftarPoliRJ jika ada
        $sepData = $this->dataDaftarPoliRJ['sep'] ?? [];
        // Dispatch event ke komponen Vclaim dengan data lengkap termasuk SEP
        $this->dispatch('open-vclaim-modal', rjNo: $this->rjNo, regNo: $this->dataDaftarPoliRJ['regNo'], drId: $this->dataDaftarPoliRJ['drId'], drDesc: $this->dataDaftarPoliRJ['drDesc'], poliId: $this->dataDaftarPoliRJ['poliId'], poliDesc: $this->dataDaftarPoliRJ['poliDesc'], kdpolibpjs: $this->dataDaftarPoliRJ['kdpolibpjs'] ?? null, kunjunganId: $this->kunjunganId, kontrol12: $this->kontrol12, internal12: $this->internal12, postInap: $this->dataDaftarPoliRJ['postInap'] ?? false, noReferensi: $this->dataDaftarPoliRJ['noReferensi'] ?? null, sepData: $sepData);
    }

    #[On('sep-generated')]
    public function handleSepGenerated($reqSep)
    {
        // Simpan reqSep ke dalam struktur sep
        $this->dataDaftarPoliRJ['sep']['reqSep'] = $reqSep;

        // Set noReferensi dari data rujukan
        $this->dataDaftarPoliRJ['noReferensi'] = $reqSep['request']['t_sep']['rujukan']['noRujukan'] ?? ($this->dataDaftarPoliRJ['noReferensi'] ?? null);

        $this->incrementVersion('modal');
        $this->dispatch('toast', [
            'type' => 'success',
            'message' => 'Request SEP berhasil diterima',
        ]);
    }

    public function mount()
    {
        // Atau register manual
        $this->registerAreas(['modal', 'pasien', 'dokter']);

        $this->dataDaftarPoliRJ['rjDate'] = Carbon::now()->format('d/m/Y H:i:s');
    }
};

?>


<div>
    <x-modal name="rj-actions" size="full" height="full" focusable>
        {{-- CONTAINER UTAMA - SATU-SATUNYA WIRE:KEY --}}
        <div class="flex flex-col min-h-[calc(100vh-8rem)]"
            wire:key="{{ $this->renderKey('modal', [$formMode, $rjNo ?? 'new']) }}">

            {{-- HEADER --}}
            <div class="relative px-6 py-5 border-b border-gray-200 dark:border-gray-700">
                {{-- Background pattern --}}
                <div class="absolute inset-0 opacity-[0.06] dark:opacity-[0.10]"
                    style="background-image: radial-gradient(currentColor 1px, transparent 1px); background-size: 14px 14px;">
                </div>

                <div class="relative flex items-start justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-3">
                            {{-- Icon --}}
                            <div
                                class="flex items-center justify-center w-10 h-10 rounded-xl bg-brand-green/10 dark:bg-brand-lime/15">
                                <img src="{{ asset('images/Logogram black solid.png') }}" alt="RSI Madinah"
                                    class="block w-6 h-6 dark:hidden" />
                                <img src="{{ asset('images/Logogram white solid.png') }}" alt="RSI Madinah"
                                    class="hidden w-6 h-6 dark:block" />
                            </div>

                            {{-- Title & subtitle --}}
                            <div>
                                <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">
                                    {{ $formMode === 'edit' ? 'Ubah Data Rawat Jalan' : 'Tambah Data Rawat Jalan' }}
                                </h2>
                                <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                                    Kelola data pendaftaran dan pelayanan pasien rawat jalan.
                                </p>
                            </div>
                        </div>

                        {{-- Badge mode --}}
                        <div class="flex gap-2 mt-3">
                            <x-badge :variant="$formMode === 'edit' ? 'warning' : 'success'">
                                {{ $formMode === 'edit' ? 'Mode: Edit' : 'Mode: Tambah' }}
                            </x-badge>
                            @if ($isFormLocked)
                                <x-badge variant="danger">
                                    Read Only
                                </x-badge>
                            @endif
                        </div>
                    </div>

                    <div class="flex gap-4">
                        {{-- Tanggal RJ --}}
                        <div class="flex-1">
                            <x-input-label value="Tanggal RJ" />
                            <x-text-input wire:model.live="dataDaftarPoliRJ.rjDate" class="block w-full"
                                :error="$errors->has('dataDaftarPoliRJ.rjDate')" :disabled="$isFormLocked" />
                            <x-input-error :messages="$errors->get('dataDaftarPoliRJ.rjDate')" class="mt-1" />
                        </div>
                        {{-- Shift --}}
                        <div class="w-36">
                            <x-input-label value="Shift" />
                            <x-select-input wire:model.live="dataDaftarPoliRJ.shift" class="w-full mt-1 sm:w-36"
                                :error="$errors->has('dataDaftarPoliRJ.shift')" :disabled="$isFormLocked">
                                <option value="">-- Pilih Shift --</option>
                                <option value="1">Shift 1</option>
                                <option value="2">Shift 2</option>
                                <option value="3">Shift 3</option>
                            </x-select-input>
                            <x-input-error :messages="$errors->get('dataDaftarPoliRJ.shift')" class="mt-1" />
                        </div>
                    </div>

                    {{-- Close button --}}
                    <x-secondary-button type="button" wire:click="closeModal" class="!p-2">
                        <span class="sr-only">Close</span>
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd"
                                d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                clip-rule="evenodd" />
                        </svg>
                    </x-secondary-button>
                </div>
            </div>

            {{-- BODY --}}
            <div class="flex-1 px-4 py-4 bg-gray-50/70 dark:bg-gray-950/20">

                <div class="max-w-full mx-auto">
                    <div class="p-1 space-y-1" x-data
                        @keydown.enter.prevent="
                            if(!$wire.isFormLocked) {
                                let f = [...$el.querySelectorAll('input,select')]
                                    .filter(e => !e.disabled && e.type !== 'hidden');

                                let i = f.indexOf($event.target);

                                if(i > -1 && i < f.length - 1){
                                    f[i+1].focus();
                                }
                            }
                        ">
                        <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">

                            {{-- ========================= --}}
                            {{-- KOLOM KIRI --}}
                            {{-- ========================= --}}
                            <div
                                class="p-6 space-y-6 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-900 dark:border-gray-700">

                                {{-- Status Pasien --}}
                                <div>
                                    <div class="mt-2">
                                        <x-toggle wire:model.live="dataDaftarPoliRJ.passStatus" trueValue="N"
                                            falseValue="O" label="Pasien Baru" :disabled="$isFormLocked" />
                                    </div>
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                        Jika tidak dicentang maka dianggap Pasien Lama.
                                    </p>
                                    <x-input-error :messages="$errors->get('dataDaftarPoliRJ.passStatus')" class="mt-1" />
                                </div>

                                {{-- LOV Pasien --}}
                                <div class="mt-2">
                                    <livewire:lov.pasien.lov-pasien target="rjFormPasien" :initialRegNo="$dataDaftarPoliRJ['regNo'] ?? ''"
                                        :disabled="$isFormLocked" />
                                    <x-input-error :messages="$errors->get('dataDaftarPoliRJ.regNo')" class="mt-1" />
                                </div>

                                {{-- LOV Dokter --}}
                                <div class="mt-2">
                                    <livewire:lov.dokter.lov-dokter label="Cari Dokter - Poli" target="rjFormDokter"
                                        :initialDrId="$dataDaftarPoliRJ['drId'] ?? null" :disabled="$isFormLocked" />
                                    {{-- Error untuk Dokter --}}
                                    <x-input-error :messages="$errors->get('dataDaftarPoliRJ.drId')" class="mt-1" />
                                    <x-input-error :messages="$errors->get('dataDaftarPoliRJ.drDesc')" class="mt-1" />

                                    {{-- Error untuk Poli --}}
                                    <x-input-error :messages="$errors->get('dataDaftarPoliRJ.poliId')" class="mt-1" />
                                    <x-input-error :messages="$errors->get('dataDaftarPoliRJ.poliDesc')" class="mt-1" />
                                </div>

                                {{-- Jenis Klaim --}}
                                <div>
                                    <x-input-label value="Jenis Klaim" />
                                    <div class="grid grid-cols-5 gap-2 mt-2">
                                        @foreach ($klaimOptions ?? [] as $index => $klaim)
                                            <x-radio-button :label="$klaim['klaimDesc']" :value="(string) $klaim['klaimId']" name="klaimId"
                                                wire:model.live="klaimId" :disabled="$isFormLocked" />
                                        @endforeach
                                    </div>
                                </div>
                                <x-input-error :messages="$errors->get('dataDaftarPoliRJ.klaimId')" class="mt-1" />

                            </div>

                            {{-- ========================= --}}
                            {{-- KOLOM KANAN --}}
                            {{-- ========================= --}}
                            <div
                                class="p-6 space-y-6 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-900 dark:border-gray-700">

                                {{-- Jenis Kunjungan --}}
                                @if (($dataDaftarPoliRJ['klaimStatus'] ?? '') === 'BPJS' || ($dataDaftarPoliRJ['klaimId'] ?? '') === 'JM')
                                    <div>
                                        <x-input-label value="Jenis Kunjungan" />
                                        <div class="grid grid-cols-4 gap-2">
                                            @foreach ($kunjunganOptions ?? [] as $index => $kunjungan)
                                                <x-radio-button :label="$kunjungan['kunjunganDesc']" :value="$kunjungan['kunjunganId']" name="kunjunganId"
                                                    wire:model.live="kunjunganId" :disabled="$isFormLocked" />
                                            @endforeach
                                        </div>

                                        {{-- LOGIC POST INAP & KONTROL 1/2 --}}
                                        <div class="mt-2">
                                            @if (($dataDaftarPoliRJ['kunjunganId'] ?? '') === '3')
                                                <x-toggle wire:model.live="dataDaftarPoliRJ.postInap" trueValue="1"
                                                    falseValue="0" label="Post Inap" :disabled="$isFormLocked" />
                                            @endif

                                            <div class="grid grid-cols-2 gap-2 mt-2">
                                                {{-- Internal 1/2: tampil saat kunjungan Rujukan Internal --}}
                                                @if ($kunjunganId === '2')
                                                    @foreach ($internal12Options ?? [] as $index => $internal)
                                                        <x-radio-button :label="__($internal['internal12Desc'])"
                                                            value="{{ $internal['internal12'] }}" name="internal12"
                                                            wire:model.live="internal12" :disabled="$isFormLocked" />
                                                    @endforeach
                                                @endif

                                                {{-- Kontrol 1/2: tampil saat kunjungan Kontrol --}}
                                                @if ($kunjunganId === '3')
                                                    @foreach ($kontrol12Options ?? [] as $index => $kontrol)
                                                        <x-radio-button :label="__($kontrol['kontrol12Desc'])"
                                                            value="{{ $kontrol['kontrol12'] }}" name="kontrol12"
                                                            wire:model.live="kontrol12" :disabled="$isFormLocked" />
                                                    @endforeach
                                                @endif
                                            </div>
                                        </div>
                                    </div>

                                    {{-- No Referensi --}}
                                    <div class="space-y-3 ">
                                        <div class="grid">
                                            <x-input-label value="No Referensi" />
                                            <x-text-input wire:model.live="dataDaftarPoliRJ.noReferensi"
                                                :disabled="$isFormLocked" />
                                            <x-input-error :messages="$errors->get('dataDaftarPoliRJ.noReferensi')" />
                                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                                di isi dgn : (No Rujukan untun FKTP /FKTL) (SKDP untuk Kontrol / Rujukan
                                                Internal)
                                            </p>
                                        </div>

                                        {{-- Tombol untuk membuka modal Vclaim RJ Actions --}}
                                        <div class="flex flex-wrap items-center gap-2 mt-2">
                                            <x-secondary-button type="button" wire:click="openVclaimModal"
                                                class="gap-2 text-xs">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                    viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linecap="round"
                                                        stroke-width="2"
                                                        d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                                </svg>
                                                Kelola SEP BPJS
                                            </x-secondary-button>

                                            {{-- Tampilkan info SEP jika sudah ada --}}
                                            @if (!empty($dataDaftarPoliRJ['sep']['noSep']))
                                                <div
                                                    class="flex items-center gap-2 px-3 py-1 text-xs text-green-700 bg-green-100 rounded-full dark:bg-green-900/30 dark:text-green-300">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                        viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linecap="round"
                                                            stroke-width="2"
                                                            d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                    </svg>
                                                    SEP: {{ $dataDaftarPoliRJ['sep']['noSep'] }}
                                                </div>

                                                <x-secondary-button type="button" wire:click="cetakSEP"
                                                    class="gap-2 text-xs" title="Cetak SEP">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                        viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linecap="round"
                                                            stroke-width="2"
                                                            d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                                                    </svg>
                                                </x-secondary-button>
                                            @endif
                                        </div>

                                        {{-- Info SEP ringkas jika sudah ada --}}
                                        @if (!empty($dataDaftarPoliRJ['sep']['noSep']))
                                            <div
                                                class="flex items-center gap-2 px-3 py-2 mt-1 text-sm border border-blue-200 rounded-lg bg-blue-50 dark:bg-blue-900/20 dark:border-blue-800">
                                                <svg class="w-5 h-5 text-blue-500" fill="none"
                                                    stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linecap="round"
                                                        stroke-width="2"
                                                        d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                </svg>
                                                <div class="flex-1">
                                                    <span
                                                        class="text-xs font-medium text-blue-700 dark:text-blue-300">SEP
                                                        Aktif:</span>
                                                    <span
                                                        class="ml-2 font-mono text-sm font-semibold text-blue-800 dark:text-blue-200">
                                                        {{ $dataDaftarPoliRJ['sep']['noSep'] }}
                                                    </span>
                                                </div>
                                                <span class="text-xs text-blue-600 dark:text-blue-400">
                                                    {{ Carbon::parse($dataDaftarPoliRJ['sep']['resSep']['tglSEP'] ?? now())->format('d/m/Y') }}
                                                </span>
                                            </div>
                                        @endif

                                        {{-- Panggil komponen Livewire modal Vclaim --}}
                                        <livewire:pages::transaksi.rj.daftar-rj.vclaim-rj-actions :initialRjNo="$rjNo ?? null" />

                                        <div class="grid">
                                            <x-input-label value="No SEP" />
                                            <x-text-input wire:model.live="dataDaftarPoliRJ.sep.noSep"
                                                :disabled="$isFormLocked" />
                                            <x-input-error :messages="$errors->get('dataDaftarPoliRJ.sep.noSep')" class="mt-1" />
                                        </div>
                                    </div>
                                @endif
                            </div>

                        </div>
                    </div>
                </div>

            </div>

            {{-- FOOTER --}}
            <div
                class="sticky bottom-0 z-10 px-6 py-4 bg-white border-t border-gray-200 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex justify-between gap-3">
                    <x-primary-button wire:click.prevent="callFormPasien()" :disabled="$isFormLocked">
                        Master Pasien
                    </x-primary-button>
                    <div class="flex justify-between gap-3">
                        <x-secondary-button wire:click="closeModal">
                            Batal
                        </x-secondary-button>
                        <x-primary-button wire:click.prevent="save()" class="min-w-[120px]" :disabled="$isFormLocked">
                            {{ $isFormLocked ? 'Read Only' : 'Simpan' }}
                        </x-primary-button>
                    </div>
                </div>
            </div>

        </div>
    </x-modal>
</div>
