<?php

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;

use App\Http\Traits\Txn\Rj\EmrRJTrait;
new class extends Component {
    use EmrRJTrait;

    public string $formMode = 'create'; // create|edit
    public ?string $rjNo = null;
    public $disabledPropertyRjStatus = false;
    public ?string $kronisNotice = null;
    public array $dataDaftarPoliRJ = [];

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
        $now = Carbon::now(config('app.timezone'));
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

    public function closeModal(): void
    {
        $this->resetValidation();
        $this->resetForm();
        $this->dispatch('close-modal', name: 'rj-actions');
    }

    public function save(): void
    {
        // Set data primer (RJno, NoBooking, NoAntrian, dll)
        $this->setDataPrimer();
        // Validasi data Rawat Jalan
        $this->validateDataRJ();

        // Push data ke BPJS (Antrian dan Task ID)
        // $this->pushDataAntrian();

        $rjNo = $this->dataDaftarPoliRJ['rjNo'] ?? null;
        if (!$rjNo) {
            $this->dispatch('toast', type: 'error', message: 'RJ No kosong.');
            return;
        }

        $lockKey = "lock:rstxn_rjhdrs:{$rjNo}";

        try {
            Cache::lock($lockKey, 15)->block(5, function () use ($rjNo) {
                DB::transaction(function () use ($rjNo) {
                    $payload = [
                        'rj_no' => $rjNo,
                        'rj_date' => DB::raw("to_date('" . $this->dataDaftarPoliRJ['rjDate'] . "','dd/mm/yyyy hh24:mi:ss')"),
                        'reg_no' => $this->dataDaftarPoliRJ['regNo'],
                        'nobooking' => $this->dataDaftarPoliRJ['noBooking'],
                        'no_antrian' => $this->dataDaftarPoliRJ['noAntrian'],
                        'klaim_id' => $this->dataDaftarPoliRJ['klaimId'],
                        'poli_id' => $this->dataDaftarPoliRJ['poliId'],
                        'dr_id' => $this->dataDaftarPoliRJ['drId'],
                        'shift' => $this->dataDaftarPoliRJ['shift'],
                        'txn_status' => $this->dataDaftarPoliRJ['txnStatus'],
                        'rj_status' => $this->dataDaftarPoliRJ['rjStatus'],
                        'erm_status' => $this->dataDaftarPoliRJ['ermStatus'],
                        'pass_status' => $this->dataDaftarPoliRJ['passStatus'] == 'N' ? 'N' : 'O',
                        'cek_lab' => $this->dataDaftarPoliRJ['cekLab'],
                        'sl_codefrom' => $this->dataDaftarPoliRJ['slCodeFrom'],
                        'kunjungan_internal_status' => $this->dataDaftarPoliRJ['kunjunganInternalStatus'],
                        'waktu_masuk_pelayanan' => DB::raw("to_date('" . $this->dataDaftarPoliRJ['rjDate'] . "','dd/mm/yyyy hh24:mi:ss')"),
                        'vno_sep' => $this->dataDaftarPoliRJ['sep']['noSep'] ?? '',
                    ];
                    // Insert atau Update header RJ
                    if ($this->formMode === 'create') {
                        DB::table('rstxn_rjhdrs')->insert($payload);
                        $message = 'Data Rawat Jalan berhasil disimpan.';
                    } else {
                        DB::table('rstxn_rjhdrs')->where('rj_no', $rjNo)->update($payload);
                        $message = 'Data Rawat Jalan berhasil diperbarui.';
                    }
                    // 🔹 Merge JSON: ambil dari DB dulu
                    $oldData = $this->findDataRJ($rjNo);
                    // ✅ whitelist patch field supaya tidak sembarangan overwrite
                    $allowed = ['rjNo', 'regNo', 'regName', 'drId', 'drDesc', 'poliId', 'poliDesc', 'klaimId', 'klaimStatus', 'kunjunganId', 'rjDate', 'shift', 'noAntrian', 'noBooking', 'slCodeFrom', 'passStatus', 'rjStatus', 'txnStatus', 'ermStatus', 'cekLab', 'kunjunganInternalStatus', 'noReferensi', 'postInap', 'internal12', 'internal12Desc', 'internal12Options', 'kontrol12', 'kontrol12Desc', 'kontrol12Options', 'taskIdPelayanan', 'sep'];
                    $incomingRJ = array_intersect_key($this->dataDaftarPoliRJ, array_flip($allowed));

                    // Merge lama + incoming
                    $mergedRJ = array_replace_recursive($oldData, $incomingRJ);

                    // Safety: pastikan rjNo tetap sama
                    $mergedRJ['rjNo'] = $rjNo;

                    // Simpan JSON
                    $this->updateJsonRJ($rjNo, $mergedRJ);

                    // Reset form & tutup modal
                    // $this->resetForm();
                    // $this->resetValidation();
                    // $this->closeModal();

                    // Toast sukses
                    $this->dispatch('toast', type: 'success', message: $message);

                    // Trigger event refresh
                    $this->dispatch('master.daftar-rj.saved');
                });
            });
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menyimpan data: ' . $e->getMessage());
        }

        // Siapkan payload data untuk disimpan

        // Bersihkan notifikasi kronis
        // $this->clearKronisNotice();

        // Kirim notifikasi sukses

        $this->dispatch('toast', type: 'success', message: $message ?? 'Data Rawat Jalan berhasil disimpan.');

        // Trigger event untuk refresh data
        $this->dispatch('master.daftar-rj.saved');
    }

    private function setDataPrimer(): void
    {
        // 1. Set Klaim & Kunjungan dari form yang dipilih user
        // $this->dataDaftarPoliRJ['klaimId'] = $this->JenisKlaim['JenisKlaimId'];
        // $this->dataDaftarPoliRJ['kunjunganId'] = $this->JenisKunjungan['JenisKunjunganId'];

        // 2. Set status kunjungan internal jika jenis kunjungan = Internal (2)
        if ($this->dataDaftarPoliRJ['kunjunganId'] == 2) {
            $this->dataDaftarPoliRJ['kunjunganInternalStatus'] = '1';
        }

        // 3. Generate No Booking jika belum ada
        if (!$this->dataDaftarPoliRJ['noBooking']) {
            $this->dataDaftarPoliRJ['noBooking'] = Carbon::now(config('app.timezone'))->format('YmdHis') . 'RSIM';
        }

        // 4. Generate No RJ (nomor transaksi) jika belum ada
        if (!$this->dataDaftarPoliRJ['rjNo']) {
            $maxRjNo = DB::table('rstxn_rjhdrs')->max('rj_no');
            $this->dataDaftarPoliRJ['rjNo'] = $maxRjNo ? $maxRjNo + 1 : 1;
        }

        // 5. Generate No Antrian jika belum ada
        if (!$this->dataDaftarPoliRJ['noAntrian']) {
            if ($this->dataDaftarPoliRJ['klaimId'] != 'KR') {
                // Hitung jumlah antrian existing untuk dokter & tanggal yang sama (non-Kronis)
                $tglAntrian = Carbon::createFromFormat('d/m/Y H:i:s', $this->dataDaftarPoliRJ['rjDate'], config('app.timezone'))->format('dmY');

                $noUrutAntrian = DB::table('rstxn_rjhdrs')
                    ->where('dr_id', $this->dataDaftarPoliRJ['drId'])
                    ->where('klaim_id', '!=', 'KR')
                    ->whereRaw("to_char(rj_date, 'ddmmyyyy') = ?", [$tglAntrian])
                    ->count();

                // Untuk pasien non-Kronis, nomor antrian = urutan + 1
                $noAntrian = $noUrutAntrian + 1;
            } else {
                // Pasien Kronis mendapat nomor antrian khusus 999
                $noAntrian = 999;
            }

            $this->dataDaftarPoliRJ['noAntrian'] = $noAntrian;
        }
    }

    private function validateDataRJ(): array
    {
        // Attributes untuk nama field yang lebih user-friendly
        $attributes = [
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
            'dataPasien.pasien.identitas.idbpjs' => 'Nomor BPJS',
            'dataPasien.pasien.identitas.nik' => 'NIK',
        ];

        // Custom messages spesifik
        $customMessages = [
            // Required messages
            'dataDaftarPoliRJ.regNo.required' => ':attribute wajib diisi.',
            'dataDaftarPoliRJ.regNo.exists' => ':attribute tidak ditemukan dalam database pasien.',
            'dataDaftarPoliRJ.drId.required' => 'Dokter wajib dipilih.',
            'dataDaftarPoliRJ.drId.exists' => 'Dokter yang dipilih tidak valid.',
            'dataDaftarPoliRJ.drDesc.required' => 'Nama Dokter wajib diisi.',
            'dataDaftarPoliRJ.poliId.required' => 'Poli wajib dipilih.',
            'dataDaftarPoliRJ.poliId.exists' => 'Poli yang dipilih tidak valid.',
            'dataDaftarPoliRJ.poliDesc.required' => 'Nama Poli wajib diisi.',
            'dataDaftarPoliRJ.rjDate.required' => ':attribute wajib diisi.',
            'dataDaftarPoliRJ.rjNo.required' => ':attribute wajib diisi.',
            'dataDaftarPoliRJ.shift.required' => ':attribute wajib diisi.',
            'dataDaftarPoliRJ.noAntrian.required' => ':attribute wajib diisi.',
            'dataDaftarPoliRJ.noBooking.required' => ':attribute wajib diisi.',
            'dataDaftarPoliRJ.slCodeFrom.required' => ':attribute wajib diisi.',
            'dataDaftarPoliRJ.rjStatus.required' => ':attribute wajib diisi.',
            'dataDaftarPoliRJ.txnStatus.required' => ':attribute wajib diisi.',
            'dataDaftarPoliRJ.ermStatus.required' => ':attribute wajib diisi.',
            'dataDaftarPoliRJ.cekLab.required' => ':attribute wajib diisi.',
            'dataDaftarPoliRJ.kunjunganInternalStatus.required' => ':attribute wajib diisi.',

            // Format validation
            'dataDaftarPoliRJ.rjDate.date_format' => ':attribute harus dalam format: dd/mm/yyyy HH:ii:ss (contoh: 25/12/2024 13:30:00).',

            // Numeric validation
            'dataDaftarPoliRJ.rjNo.numeric' => ':attribute harus berupa angka.',
            'dataDaftarPoliRJ.noAntrian.numeric' => ':attribute harus berupa angka.',

            // Min/Max validation
            'dataDaftarPoliRJ.noAntrian.min' => ':attribute minimal :min.',
            'dataDaftarPoliRJ.noAntrian.max' => ':attribute maksimal :max.',
            'dataDaftarPoliRJ.noReferensi.min' => ':attribute minimal :min karakter.',
            'dataDaftarPoliRJ.noReferensi.max' => ':attribute maksimal :max karakter.',

            // In validation (enum)
            'dataDaftarPoliRJ.shift.in' => ':attribute harus salah satu dari: 1, 2, atau 3.',
            'dataDaftarPoliRJ.slCodeFrom.in' => ':attribute harus salah satu dari: 01 atau 02.',
            'dataDaftarPoliRJ.passStatus.in' => ':attribute harus salah satu dari: N (Baru) atau O (Lama).',
            'dataDaftarPoliRJ.rjStatus.in' => ':attribute harus salah satu dari: A (Antrian), L (Selesai), I (Transfer), atau F (Batal).',
            'dataDaftarPoliRJ.txnStatus.in' => ':attribute harus salah satu dari: A (Aktif), P (Proses), atau C (Selesai).',
            'dataDaftarPoliRJ.ermStatus.in' => ':attribute harus salah satu dari: A (Aktif), P (Proses), atau C (Selesai).',
            'dataDaftarPoliRJ.cekLab.in' => ':attribute harus salah satu dari: 0 (Tidak) atau 1 (Ya).',
            'dataDaftarPoliRJ.kunjunganInternalStatus.in' => ':attribute harus salah satu dari: 0 (Tidak) atau 1 (Ya).',

            'dataDaftarPoliRJ.klaimId.required' => ':attribute wajib diisi.',
            'dataDaftarPoliRJ.klaimId.exists' => ':attribute tidak ditemukan dalam database klaim.',

            // String validation
            'dataDaftarPoliRJ.kddrbpjs.string' => ':attribute harus berupa teks.',
            'dataDaftarPoliRJ.kdpolibpjs.string' => ':attribute harus berupa teks.',
            'dataDaftarPoliRJ.noBooking.string' => ':attribute harus berupa teks.',
            'dataDaftarPoliRJ.noReferensi.string' => ':attribute harus berupa teks.',

            // BPJS specific messages
            'dataPasien.pasien.identitas.idbpjs.required' => ':attribute wajib diisi untuk pasien BPJS.',
            'dataPasien.pasien.identitas.idbpjs.min' => ':attribute minimal :min digit.',
            'dataPasien.pasien.identitas.idbpjs.max' => ':attribute maksimal :max digit.',
            'dataPasien.pasien.identitas.nik.required' => ':attribute wajib diisi untuk pasien BPJS.',
            'dataPasien.pasien.identitas.nik.size' => ':attribute harus :size digit.',

            // KRONIS specific messages
            'dataDaftarPoliRJ.noAntrian.in' => ':attribute untuk pasien KRONIS harus 999.',
        ];

        // Rules validasi dasar
        $rules = [
            'dataDaftarPoliRJ.regNo' => ['bail', 'required', Rule::exists('rsmst_pasiens', 'reg_no')],
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
            'dataDaftarPoliRJ.txnStatus' => 'required|in:A,P,C',
            'dataDaftarPoliRJ.ermStatus' => 'required|in:A,P,C',
            'dataDaftarPoliRJ.cekLab' => 'required|in:0,1',
            'dataDaftarPoliRJ.kunjunganInternalStatus' => 'required|in:0,1',
            'dataDaftarPoliRJ.noReferensi' => 'nullable|string|min:3|max:19',
            'dataDaftarPoliRJ.klaimId' => 'required|exists:rsmst_klaimtypes,klaim_id',
        ];

        // Validasi khusus untuk BPJS
        if ($this->dataDaftarPoliRJ['klaimId'] == 'JM') {
            $rules['dataDaftarPoliRJ.noReferensi'] = ['bail', 'required', 'string', 'min:3', 'max:19'];
            $rules['dataPasien.pasien.identitas.idbpjs'] = ['bail', 'required', 'string', 'min:5', 'max:15'];
            $rules['dataPasien.pasien.identitas.nik'] = ['bail', 'required', 'string', 'size:16'];
        }

        // Validasi untuk pasien KRONIS
        if ($this->dataDaftarPoliRJ['klaimId'] == 'KR') {
            $rules['dataDaftarPoliRJ.noAntrian'] = 'required|numeric|in:999';
        }

        // Proses Validasi
        return $this->validate($rules, $customMessages, $attributes);
    }

    protected function resetForm(): void
    {
        $this->reset(['rjNo', 'dataDaftarPoliRJ']);

        // Reset default pilihan
        $this->klaimId = 'UM';
        $this->kunjunganId = '1';
        $this->kontrol12 = '1';
        $this->internal12 = '1';

        $this->formMode = 'create';

        $this->dataDaftarPoliRJ['regNo'] = '';
        $this->dataDaftarPoliRJ['regName'] = '';
        $this->dataDaftarPoliRJ['drId'] = null;
        $this->dataDaftarPoliRJ['drDesc'] = '';
        $this->dataDaftarPoliRJ['poliId'] = null;
        $this->dataDaftarPoliRJ['poliDesc'] = '';
    }

    protected function rules(): array
    {
        return [
            'tindakLanjut' => ['nullable', 'string', 'max:255'],
            'tglKontrol' => ['nullable', 'date'],
            'keterangan' => ['nullable', 'string'],
        ];
    }

    /* ===============================
     | SAVE
     =============================== */

    /* ===============================
     | DELETE
     =============================== */

    #[On('lov.selected')]
    public function handleLovSelected(string $target, array $payload): void
    {
        if ($target === 'rjFormPasien') {
            $this->dataDaftarPoliRJ['regNo'] = $payload['reg_no'] ?? '';
            $this->dataDaftarPoliRJ['regName'] = $payload['reg_name'] ?? '';
        }

        if ($target === 'rjFormDokter') {
            $this->dataDaftarPoliRJ['drId'] = $payload['dr_id'] ?? '';
            $this->dataDaftarPoliRJ['drDesc'] = $payload['dr_name'] ?? '';
            $this->dataDaftarPoliRJ['poliId'] = $payload['poli_id'] ?? '';
            $this->dataDaftarPoliRJ['poliDesc'] = $payload['poli_desc'] ?? '';
        }
    }

    public function updated($name, $value)
    {
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
        // mode edit: sync from dataDaftarPoliRJ
        if (!empty($this->dataDaftarPoliRJ['klaimId'])) {
            $this->klaimId = $this->dataDaftarPoliRJ['klaimId'];
        }

        if (!empty($this->dataDaftarPoliRJ['kunjunganId'])) {
            $this->kunjunganId = $this->dataDaftarPoliRJ['kunjunganId'];
        }
    }
};
?>


<div>
    <x-modal name="rj-actions" size="full" height="full" focusable>
        <div class="flex flex-col min-h-[calc(100vh-8rem)]"
            wire:key="rj-actions-{{ $formMode }}-{{ $rjNo ?? 'new' }}">

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
                        <div class="mt-3">
                            <x-badge :variant="$formMode === 'edit' ? 'warning' : 'success'">
                                {{ $formMode === 'edit' ? 'Mode: Edit' : 'Mode: Tambah' }}
                            </x-badge>
                        </div>
                    </div>

                    <div class="flex gap-4">
                        {{-- Tanggal RJ --}}
                        <div class="flex-1">
                            <x-input-label value="Tanggal RJ" />
                            <x-text-input wire:model.live="dataDaftarPoliRJ.rjDate"
                                wire:key="rjDate-{{ $dataDaftarPoliRJ['rjDate'] ?? 'new' }}" class="block w-full"
                                :error="$errors->has('dataDaftarPoliRJ.rjDate')" />
                            <x-input-error :messages="$errors->get('dataDaftarPoliRJ.rjDate')" class="mt-1" />
                        </div>
                        {{-- Shift --}}
                        <div class="w-36">
                            <x-input-label value="Shift" />
                            <x-select-input wire:model.live="dataDaftarPoliRJ.shift" class="w-full mt-1 sm:w-36"
                                wire:key="shift-{{ $dataDaftarPoliRJ['shift'] ?? 'new' }}" :error="$errors->has('dataDaftarPoliRJ.shift')">
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
                            let f = [...$el.querySelectorAll('input,select,textarea')]
                                .filter(e => !e.disabled && e.type !== 'hidden');

                            let i = f.indexOf($event.target);

                            if(i > -1 && i < f.length - 1){
                                f[i+1].focus();
                            }
                        ">
                        <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">

                            {{-- ========================= --}}
                            {{-- KOLOM KIRI --}}
                            {{-- ========================= --}}
                            <div
                                class="p-6 space-y-6 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-900 dark:border-gray-700">



                                {{-- LOV Pasien --}}
                                <livewire:lov.pasien.lov-pasien target="rjFormPasien" :initialRegNo="$dataDaftarPoliRJ['regNo'] ?? ''"
                                    wire:key="'lov-pasien-' . ($dataDaftarPoliRJ['regNo'] ?? 'new')" />

                                <x-input-error :messages="$errors->get('dataDaftarPoliRJ.regNo')" class="mt-1" />
                                {{-- Jenis Klaim --}}
                                <div>
                                    <x-input-label value="Jenis Klaim" />
                                    <div class="grid grid-cols-5 gap-2 mt-2">
                                        @foreach ($klaimOptions ?? [] as $index => $klaim)
                                            <x-radio-button :label="$klaim['klaimDesc']" :value="(string) $klaim['klaimId']" name="klaimId"
                                                wire:model.live="klaimId" />
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
                                @if (($dataDaftarPoliRJ['klaimStatus'] ?? '') === 'BPJS')
                                    <div>
                                        <x-input-label value="Jenis Kunjungan" />
                                        <div class="grid grid-cols-4 gap-2 mt-2">
                                            @foreach ($kunjunganOptions ?? [] as $index => $kunjungan)
                                                <x-radio-button :label="$kunjungan['kunjunganDesc']" :value="$kunjungan['kunjunganId']" name="kunjunganId"
                                                    wire:model.live="kunjunganId" />
                                            @endforeach
                                        </div>

                                        {{-- LOGIC POST INAP & KONTROL 1/2 --}}
                                        <div class="mt-4">
                                            @if (($dataDaftarPoliRJ['kunjunganId'] ?? '') === '3')
                                                <x-check-box value="1" :label="__('Post Inap')"
                                                    wire:model="dataDaftarPoliRJ.postInap" />
                                            @endif

                                            <div class="grid grid-cols-2 gap-2 mt-2">
                                                {{-- Internal 1/2: tampil saat kunjungan Rujukan Internal --}}
                                                @if ($kunjunganId === '2')
                                                    @foreach ($internal12Options ?? [] as $index => $internal)
                                                        <x-radio-button :label="__($internal['internal12Desc'])"
                                                            value="{{ $internal['internal12'] }}" name="internal12"
                                                            wire:model.live="internal12" />
                                                    @endforeach
                                                @endif

                                                {{-- Kontrol 1/2: tampil saat kunjungan Kontrol --}}
                                                @if ($kunjunganId === '3')
                                                    @foreach ($kontrol12Options ?? [] as $index => $kontrol)
                                                        <x-radio-button :label="__($kontrol['kontrol12Desc'])"
                                                            value="{{ $kontrol['kontrol12'] }}" name="kontrol12"
                                                            wire:model.live="kontrol12" />
                                                    @endforeach
                                                @endif

                                            </div>

                                        </div>
                                    </div>


                                    {{-- No Referensi --}}
                                    <div class="pt-4 space-y-3 border-t">
                                        <div class="grid">
                                            <x-input-label value="No Referensi" />
                                            <x-text-input wire:model.live="dataDaftarPoliRJ.noReferensi" />
                                        </div>
                                        <div class="flex justify-between gap-2">
                                            <x-primary-button wire:click.prevent="clickrujukanPeserta()">
                                                Cek Referensi
                                            </x-primary-button>
                                            <x-primary-button wire:click.prevent="callRJskdp()">
                                                Buat SKDP
                                            </x-primary-button>
                                        </div>
                                    </div>
                                @endif


                                {{-- Dokter & Poli --}}
                                <div class="pt-4 space-y-4">
                                    {{-- <x-input-label value="Dokter & Poli" /> --}}

                                    {{-- Display Selected --}}
                                    {{-- <x-text-input class="w-full mt-1" :disabled="true"
                                        value="{{ ($dataDaftarPoliRJ['drId'] ?? '') .
                                            (isset($dataDaftarPoliRJ['drDesc']) ? ' - ' . $dataDaftarPoliRJ['drDesc'] : '') .
                                            (isset($dataDaftarPoliRJ['poliDesc']) ? ' | Poli: ' . $dataDaftarPoliRJ['poliDesc'] : '') }}" /> --}}

                                    {{-- LOV Dokter --}}
                                    <div class="mt-2">
                                        <livewire:lov.dokter.lov-dokter label="Cari Dokter - Poli" target="rjFormDokter"
                                            :initialDrId="$dataDaftarPoliRJ['drId'] ?? null"
                                            wire:key="'lov-dokter-rj-' . ($dataDaftarPoliRJ['drId'] ?? 'new')" />

                                        {{-- Error untuk Dokter --}}
                                        <x-input-error :messages="$errors->get('dataDaftarPoliRJ.drId')" class="mt-1" />
                                        <x-input-error :messages="$errors->get('dataDaftarPoliRJ.drDesc')" class="mt-1" />

                                        {{-- Error untuk Poli --}}
                                        <x-input-error :messages="$errors->get('dataDaftarPoliRJ.poliId')" class="mt-1" />
                                        <x-input-error :messages="$errors->get('dataDaftarPoliRJ.poliDesc')" class="mt-1" />
                                    </div>
                                </div>

                            </div>

                        </div>
                    </div>
                </div>

            </div>

            {{-- FOOTER --}}
            <div
                class="sticky bottom-0 z-10 px-6 py-4 bg-white border-t border-gray-200 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex justify-between gap-3">
                    <x-primary-button wire:click.prevent="callFormPasien()">
                        Master Pasien
                    </x-primary-button>
                    <div class="flex justify-between gap-3">
                        <x-secondary-button wire:click="closeModal">
                            Batal
                        </x-secondary-button>
                        <x-primary-button wire:click.prevent="save()" class="min-w-[120px]">
                            Simpan
                        </x-primary-button>
                    </div>
                </div>
            </div>

        </div>
    </x-modal>
</div>
