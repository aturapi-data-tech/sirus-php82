<?php
// resources/views/pages/transaksi/ugd/emr-ugd/modul-dokumen/penundaan-pelayanan/rm-penundaan-pelayanan-actions.blade.php

use Livewire\Component;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;
use App\Http\Traits\Txn\Ugd\EmrUGDTrait;
use App\Http\Traits\Concerns\WithRenderVersioningTrait;
use Illuminate\Validation\ValidationException;

new class extends Component {
    use EmrUGDTrait, WithRenderVersioningTrait;

    public bool $isFormLocked = false;
    public ?int $rjNo = null;
    public bool $disabled = false;
    public array $dataDaftarUGD = [];

    public array $renderVersions = [];
    protected array $renderAreas = ['modal-penundaan-pelayanan-ugd'];

    // ── Form entri baru ──
    public array $newForm = [
        'tglPemberitahuan' => '',
        'jenis' => '',
        'alasan' => '',
        'jadwalUlang' => '',
        'alternatif' => '',
        'respon' => '',
        'namaPenanda' => '',
        'hubunganPasien' => 'pasien',
        'pemberiInfo' => '',
        'pemberiInfoCode' => '',
        'pemberiInfoDate' => '',
    ];

    public string $signature = ''; // TTD pasien/keluarga untuk entri baru

    public array $penundaanList = [];

    public array $responOptions = ['Menerima penundaan', 'Memilih alternatif', 'Menolak'];

    public array $hubunganPasienOptions = [
        ['value' => 'pasien', 'label' => 'Pasien Sendiri'],
        ['value' => 'suami', 'label' => 'Suami'],
        ['value' => 'istri', 'label' => 'Istri'],
        ['value' => 'ayah', 'label' => 'Ayah'],
        ['value' => 'ibu', 'label' => 'Ibu'],
        ['value' => 'anak', 'label' => 'Anak'],
        ['value' => 'saudara', 'label' => 'Saudara'],
        ['value' => 'wali_hukum', 'label' => 'Wali Hukum'],
        ['value' => 'lainnya', 'label' => 'Lainnya'],
    ];

    // Kunci entri yang sedang diedit (signatureDate = kunci stabil, di-set saat entri pertama dibuat).
    // null = sedang membuat entri baru.
    public ?string $editingKey = null;

    // Layar aktif di modal: 'daftar' (grid entri) atau 'form' (tambah/edit/lihat).
    // Formulir sengaja tidak nongkrong bersama daftarnya: dulu ia ikut tampil terus lalu
    // dikosongkan diam-diam sesudah tersimpan, dan petugas yang mengira itu masih formulir
    // yang tadi diisi mengetik ulang — tersimpan sebagai draft baru.
    public string $layar = 'daftar';

    // true = entri terkunci sedang ditampilkan di form dalam mode read-only (lihat saja, tak bisa edit).
    public bool $viewOnly = false;

    /* ===============================
     | MOUNT
     =============================== */
    public function mount(?int $rjNo = null, bool $disabled = false): void
    {
        $this->rjNo = $rjNo ?: null;
        $this->disabled = $disabled;
        $this->registerAreas(['modal-penundaan-pelayanan-ugd']);

        if ($this->rjNo) {
            $data = $this->findDataUGD($this->rjNo);
            if ($data) {
                $this->dataDaftarUGD = $data;
                $this->penundaanList = $data['penundaanPelayananUGD'] ?? [];
                $this->isFormLocked = $this->checkEmrUGDStatus($this->rjNo) || $disabled;
            }
        }
    }

    /* ===============================
     | OPEN MODAL
     =============================== */
    public function openModal(): void
    {
        if (!$this->rjNo || $this->disabled) {
            return;
        }

        $this->resetNewForm();
        $this->signature = '';
        $this->editingKey = null;
        $this->viewOnly = false;
        $this->resetValidation();

        $data = $this->findDataUGD($this->rjNo);
        if (!$data) {
            $this->dispatch('toast', type: 'error', message: 'Data UGD tidak ditemukan.');
            return;
        }

        $this->dataDaftarUGD = $data;
        if (!isset($this->dataDaftarUGD['penundaanPelayananUGD']) || !is_array($this->dataDaftarUGD['penundaanPelayananUGD'])) {
            $this->dataDaftarUGD['penundaanPelayananUGD'] = [];
        }
        $this->penundaanList = $this->dataDaftarUGD['penundaanPelayananUGD'];
        $this->newForm['namaPenanda'] = $this->dataDaftarUGD['regName'] ?? '';
        $this->isFormLocked = $this->checkEmrUGDStatus($this->rjNo) || $this->disabled;
        $this->incrementVersion('modal-penundaan-pelayanan-ugd');

        $this->layar = 'daftar';

        $this->dispatch('open-modal', name: "rm-penundaan-pelayanan-ugd-{$this->rjNo}");
    }

    /* ===============================
     | CLOSE
     =============================== */
    public function closeModal(): void
    {
        $this->dispatch('close-modal', name: "rm-penundaan-pelayanan-ugd-{$this->rjNo}");
    }

    /* ===============================
     | VALIDATION
     =============================== */
    protected function rules(): array
    {
        return [
            'newForm.tglPemberitahuan' => 'required|date_format:d/m/Y H:i:s',
            'newForm.jenis' => 'required|string|max:500',
            'newForm.alasan' => 'required|string|max:1000',
            'newForm.jadwalUlang' => 'nullable|date_format:d/m/Y H:i:s',
            'newForm.alternatif' => 'nullable|string|max:1000',
            'newForm.respon' => 'required|string',
            'newForm.namaPenanda' => 'required|string|max:200',
            'newForm.hubunganPasien' => 'required|string|max:50',
            'signature' => 'required|string',
        ];
    }

    protected function messages(): array
    {
        return [
            'required' => ':attribute wajib diisi.',
            'date_format' => 'Format :attribute harus dd/mm/yyyy HH:mm:ss (cth: 25/06/2026 11:11:16).',
            'max' => ':attribute maksimal :max karakter.',
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'newForm.tglPemberitahuan' => 'Tanggal/jam pemberitahuan',
            'newForm.jenis' => 'Jenis pelayanan yang ditunda',
            'newForm.alasan' => 'Alasan penundaan/kelambatan',
            'newForm.jadwalUlang' => 'Jadwal ulang',
            'newForm.alternatif' => 'Alternatif yang ditawarkan',
            'newForm.respon' => 'Respon pasien/keluarga',
            'newForm.namaPenanda' => 'Nama pasien/keluarga',
            'newForm.hubunganPasien' => 'Hubungan dengan pasien',
            'signature' => 'Tanda tangan pasien/keluarga',
        ];
    }

    /* ===============================
     | SET TANGGAL SEKARANG
     =============================== */
    public function setTglPemberitahuanSekarang(): void
    {
        if ($this->isFormLocked || $this->viewOnly) {
            return;
        }
        $this->newForm['tglPemberitahuan'] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
    }

    public function setJadwalUlangSekarang(): void
    {
        if ($this->isFormLocked || $this->viewOnly) {
            return;
        }
        $this->newForm['jadwalUlang'] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
    }

    /* ===============================
     | SIGNATURE (pasien/keluarga)
     =============================== */
    public function setSignature(string $dataUrl): void
    {
        if ($this->isFormLocked || $this->viewOnly) {
            return;
        }
        $this->signature = $dataUrl;
        $this->incrementVersion('modal-penundaan-pelayanan-ugd');
    }

    public function clearSignature(): void
    {
        if ($this->isFormLocked || $this->viewOnly) {
            return;
        }
        $this->signature = '';
        $this->incrementVersion('modal-penundaan-pelayanan-ugd');
    }

    /* ===============================
     | TTD PETUGAS (Pemberi Informasi) = FINALIZE
     | Petugas TTD di akhir → validasi lengkap + kunci entri.
     =============================== */
    public function setPemberiInfo(): void
    {
        if ($this->isFormLocked || $this->viewOnly) {
            $this->dispatch('toast', type: 'error', message: 'Form read-only.');
            return;
        }
        if (empty($this->signature)) {
            $this->dispatch('toast', type: 'error', message: 'TTD pasien/keluarga wajib sebelum TTD petugas.');
            return;
        }

        try {
            $this->validate();
        } catch (ValidationException $e) {
            $this->dispatch('toast', type: 'error', message: 'Lengkapi kolom wajib sebelum TTD petugas.');
            throw $e;
        }

        // Stempel TTD petugas (pemberi informasi) = user login.
        $this->newForm['pemberiInfo'] = auth()->user()->myuser_name ?? '';
        $this->newForm['pemberiInfoCode'] = auth()->user()->myuser_code ?? '';
        $this->newForm['pemberiInfoDate'] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');

        $key = $this->editingKey ?: Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');

        try {
            $this->persistEntry($key, true, 'Kunci (TTD Petugas)');
            $this->resetNewForm();
            $this->newForm['namaPenanda'] = $this->dataDaftarUGD['regName'] ?? '';
            $this->signature = '';
            $this->editingKey = null;
            $this->viewOnly = false;
            $this->incrementVersion('modal-penundaan-pelayanan-ugd');
            $this->dispatch('toast', type: 'success', message: 'Penundaan ditandatangani petugas dan terkunci.');
            $this->dispatch('refresh-modul-dokumen-ugd-data', rjNo: $this->rjNo);
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal mengunci: ' . $e->getMessage());
        }
    }

    /* ===============================
     | HELPER — status & bentuk entri
     =============================== */
    // Entri dianggap FINAL/terkunci bila flag finalized true; entri lama (tanpa flag) yang sudah
    // ada TTD pasien dianggap final (kompatibilitas data lama).
    public function entryIsFinal(array $e): bool
    {
        return array_key_exists('finalized', $e) ? (bool) $e['finalized'] : !empty($e['signature']);
    }

    // Susun array entri dari state form. $key = signatureDate (kunci stabil); $finalized = status kunci.
    private function buildEntry(string $key, bool $finalized): array
    {
        return [
            'tglPemberitahuan' => $this->newForm['tglPemberitahuan'] ?? '',
            'jenis' => $this->newForm['jenis'] ?? '',
            'alasan' => $this->newForm['alasan'] ?? '',
            'jadwalUlang' => $this->newForm['jadwalUlang'] ?? '',
            'alternatif' => $this->newForm['alternatif'] ?? '',
            'respon' => $this->newForm['respon'] ?? '',
            'namaPenanda' => $this->newForm['namaPenanda'] ?? '',
            'hubunganPasien' => $this->newForm['hubunganPasien'] ?? 'pasien',
            'signature' => $this->signature,
            'signatureDate' => $key,
            'pemberiInfo' => $this->newForm['pemberiInfo'] ?? '',
            'pemberiInfoCode' => $this->newForm['pemberiInfoCode'] ?? '',
            'pemberiInfoDate' => $this->newForm['pemberiInfoDate'] ?? '',
            'finalized' => $finalized,
        ];
    }

    // Simpan entri (add/update by $key) dengan status $finalized. Dipakai draft & kunci.
    private function persistEntry(string $key, bool $finalized, string $logVerb): void
    {
        $entry = $this->buildEntry($key, $finalized);

        DB::transaction(function () use ($entry, $key, $logVerb) {
            $this->lockUGDRow($this->rjNo);

            $data = $this->findDataUGD($this->rjNo);
            if (empty($data)) {
                throw new \RuntimeException('Data UGD tidak ditemukan, simpan dibatalkan.');
            }
            if (!isset($data['penundaanPelayananUGD']) || !is_array($data['penundaanPelayananUGD'])) {
                $data['penundaanPelayananUGD'] = [];
            }

            $list = $data['penundaanPelayananUGD'];
            $idx = collect($list)->search(fn($it) => ($it['signatureDate'] ?? '') === $key);
            if ($idx === false) {
                $list[] = $entry;
            } else {
                if ($this->entryIsFinal($list[$idx])) {
                    throw new \RuntimeException('Entri sudah terkunci, tidak dapat diubah.');
                }
                $list[$idx] = $entry;
            }
            $data['penundaanPelayananUGD'] = array_values($list);

            $this->updateJsonUGD($this->rjNo, $data);
            $this->dataDaftarUGD = $data;
            $this->penundaanList = $data['penundaanPelayananUGD'];

            $this->appendAdminLogUGD((int) $this->rjNo, $logVerb . ' Penundaan Pelayanan UGD — jenis "' . ($entry['jenis'] ?: ($entry['alasan'] ?: '-')) . '" (' . $key . ')', 'MR');
        });
    }

    /* ===============================
     | SIMPAN DRAFT (nyicil, tanpa validasi lengkap)
     =============================== */
    public function saveDraft(): void
    {
        if ($this->isFormLocked || $this->viewOnly) {
            $this->dispatch('toast', type: 'error', message: 'Form read-only, tidak dapat menyimpan.');
            return;
        }
        if (trim($this->newForm['alasan'] ?? '') === '') {
            $this->dispatch('toast', type: 'error', message: 'Alasan penundaan wajib diisi untuk menyimpan draft.');
            return;
        }

        $key = $this->editingKey ?: Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');

        try {
            $this->persistEntry($key, false, 'Simpan draft');
            $this->editingKey = $key; // lanjut edit entri yang sama, tidak buat duplikat
            $this->incrementVersion('modal-penundaan-pelayanan-ugd');
            $this->dispatch('toast', type: 'success', message: 'Draft tersimpan.');
            $this->dispatch('refresh-modul-dokumen-ugd-data', rjNo: $this->rjNo);
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menyimpan draft: ' . $e->getMessage());
        }
    }

    /* ===============================
     | EDIT / LIHAT / BATAL entri
     =============================== */
    // Muat 1 entri ke form atas (dipakai edit draft & lihat entri terkunci).
    private function hydrateFormFromEntry(array $entry, string $key): void
    {
        $this->newForm = [
            'tglPemberitahuan' => $entry['tglPemberitahuan'] ?? '',
            'jenis' => $entry['jenis'] ?? '',
            'alasan' => $entry['alasan'] ?? '',
            'jadwalUlang' => $entry['jadwalUlang'] ?? '',
            'alternatif' => $entry['alternatif'] ?? '',
            'respon' => $entry['respon'] ?? '',
            'namaPenanda' => $entry['namaPenanda'] ?? '',
            'hubunganPasien' => $entry['hubunganPasien'] ?? 'pasien',
            'pemberiInfo' => $entry['pemberiInfo'] ?? '',
            'pemberiInfoCode' => $entry['pemberiInfoCode'] ?? '',
            'pemberiInfoDate' => $entry['pemberiInfoDate'] ?? '',
        ];
        $this->signature = $entry['signature'] ?? '';
        $this->editingKey = $key;
        $this->resetValidation();
        $this->incrementVersion('modal-penundaan-pelayanan-ugd');
    }

    public function editEntry(string $key): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form read-only.');
            return;
        }
        $entry = collect($this->penundaanList)->firstWhere('signatureDate', $key);
        if (!$entry) {
            $this->dispatch('toast', type: 'error', message: 'Entri tidak ditemukan.');
            return;
        }
        if ($this->entryIsFinal($entry)) {
            $this->dispatch('toast', type: 'warning', message: 'Entri sudah terkunci, tidak dapat diedit.');
            return;
        }

        $this->viewOnly = false;
        $this->hydrateFormFromEntry($entry, $key);
        $this->dispatch('toast', type: 'info', message: 'Draft dimuat untuk dilanjutkan.');
    }

    // Lihat entri terkunci: muat ke form atas dalam mode read-only.
    public function viewEntry(string $key): void
    {
        $entry = collect($this->penundaanList)->firstWhere('signatureDate', $key);
        if (!$entry) {
            $this->dispatch('toast', type: 'error', message: 'Entri tidak ditemukan.');
            return;
        }

        $this->viewOnly = true;
        $this->hydrateFormFromEntry($entry, $key);
        $this->dispatch('toast', type: 'info', message: 'Menampilkan entri terkunci (hanya lihat).');
    }

    public function cancelEdit(): void
    {
        $this->resetNewForm();
        $this->newForm['namaPenanda'] = $this->dataDaftarUGD['regName'] ?? '';
        $this->signature = '';
        $this->editingKey = null;
        $this->viewOnly = false;
        $this->resetValidation();
        $this->incrementVersion('modal-penundaan-pelayanan-ugd');
    }

    /** Layar formulir sedang tampil? Saat terkunci, formulir tak pernah dirender. */
    public function diForm(): bool
    {
        return !$this->isFormLocked && ($this->viewOnly || $this->editingKey !== null || $this->layar === 'form');
    }

    /** Buka formulir kosong untuk entri baru. */
    public function tambahEntri(): void
    {
        if ($this->isFormLocked || $this->disabled) {
            $this->dispatch('toast', type: 'error', message: 'Form read-only, tidak dapat menambah entri.');
            return;
        }
        $this->cancelEdit();     // kosongkan formulir (sekaligus balik ke daftar)…
        $this->layar = 'form';   // …lalu naikkan formulirnya
    }

    /** Tutup formulir, kembali ke daftar entri. Formulir selalu ditinggalkan kosong. */
    public function kembaliKeDaftar(): void
    {
        $this->cancelEdit();
    }

    /* ===============================
     | CETAK
     =============================== */
    public function cetak(string $signatureDate): void
    {
        if (!$this->rjNo) {
            $this->dispatch('toast', type: 'error', message: 'Nomor UGD tidak ditemukan.');
            return;
        }

        $entry = collect($this->penundaanList)->firstWhere('signatureDate', $signatureDate);
        if (!$entry) {
            $this->dispatch('toast', type: 'error', message: 'Data formulir tidak ditemukan.');
            return;
        }

        $this->dispatch('cetak-penundaan-pelayanan-ugd.open', rjNo: $this->rjNo, signatureDate: $signatureDate);
    }

    /* ===============================
     | HAPUS
     =============================== */
    public function hapus(string $signatureDate): void
    {
        if (!auth()->user()?->can('dokumen.hapus')) {
            $this->dispatch('toast', type: 'error', message: 'Anda tidak berwenang menghapus entri.');
            return;
        }
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form read-only, tidak dapat menghapus.');
            return;
        }

        try {
            DB::transaction(function () use ($signatureDate) {
                $this->lockUGDRow($this->rjNo);

                $data = $this->findDataUGD($this->rjNo);
                if (empty($data) || !isset($data['penundaanPelayananUGD'])) {
                    throw new \RuntimeException('Data formulir tidak ditemukan.');
                }

                $data['penundaanPelayananUGD'] = collect($data['penundaanPelayananUGD'])
                    ->reject(fn($item) => ($item['signatureDate'] ?? '') === $signatureDate)
                    ->values()
                    ->toArray();

                $this->updateJsonUGD($this->rjNo, $data);
                $this->dataDaftarUGD = $data;
                $this->penundaanList = $data['penundaanPelayananUGD'];
                $this->appendAdminLogUGD((int) $this->rjNo, 'Hapus Pemberitahuan Penundaan/Kelambatan — TTD ' . $signatureDate, 'MR');
            });

            $this->incrementVersion('modal-penundaan-pelayanan-ugd');
            $this->dispatch('toast', type: 'success', message: 'Formulir penundaan berhasil dihapus.');
            $this->dispatch('refresh-modul-dokumen-ugd-data', rjNo: $this->rjNo);
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menghapus: ' . $e->getMessage());
        }
    }

    /* ===============================
     | BUKA KUNCI — cabut TTD petugas, entri kembali Draft (Gate dokumen.bukaKunci)
     =============================== */
    public function bukaKunci(string $signatureDate): void
    {
        if (!auth()->user()?->can('dokumen.bukaKunci')) {
            $this->dispatch('toast', type: 'error', message: 'Anda tidak berwenang membuka kunci.');
            return;
        }
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form read-only.');
            return;
        }
        try {
            DB::transaction(function () use ($signatureDate) {
                $this->lockUGDRow($this->rjNo);
                $data = $this->findDataUGD($this->rjNo);
                $list = is_array($data['penundaanPelayananUGD'] ?? null) ? $data['penundaanPelayananUGD'] : [];
                $index = collect($list)->search(fn($item) => ($item['signatureDate'] ?? '') === $signatureDate);
                if ($index === false) {
                    throw new \RuntimeException('Entri tidak ditemukan.');
                }
                $list[$index]['finalized'] = false;
                $list[$index]['pemberiInfo'] = '';
                $list[$index]['pemberiInfoCode'] = '';
                $list[$index]['pemberiInfoDate'] = '';
                $data['penundaanPelayananUGD'] = array_values($list);
                $this->updateJsonUGD($this->rjNo, $data);
                $this->dataDaftarUGD = $data;
                $this->penundaanList = $data['penundaanPelayananUGD'];
                $pembukaKunci = auth()->user()->myuser_name ?? '-';
                $this->appendAdminLogUGD((int) $this->rjNo, 'Buka kunci Pemberitahuan Penundaan Pelayanan (' . $signatureDate . ') oleh ' . $pembukaKunci . ' — TTD petugas dicabut, entri kembali draft', 'MR');
            });
            $this->incrementVersion('modal-penundaan-pelayanan-ugd');
            $this->dispatch('toast', type: 'success', message: 'Kunci dibuka — TTD petugas dicabut, entri kembali Draft.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal membuka kunci: ' . $e->getMessage());
        }
    }

    /* ===============================
     | RESET
     =============================== */
    private function resetNewForm(): void
    {
        $this->newForm = [
            'tglPemberitahuan' => '',
            'jenis' => '',
            'alasan' => '',
            'jadwalUlang' => '',
            'alternatif' => '',
            'respon' => '',
            'namaPenanda' => '',
            'hubunganPasien' => 'pasien',
            'pemberiInfo' => '',
            'pemberiInfoCode' => '',
            'pemberiInfoDate' => '',
        ];
        $this->layar = 'daftar';   // mengosongkan formulir = kembali ke daftar
    }

    protected function resetForm(): void
    {
        $this->resetVersion();
        $this->isFormLocked = false;
        $this->dataDaftarUGD = [];
        $this->penundaanList = [];
        $this->resetNewForm();
        $this->signature = '';
        $this->editingKey = null;
        $this->viewOnly = false;
    }
};
?>

<div>
    {{-- ══ SUMMARY CARD (inline) ══ --}}
    @php $ppCount = count($penundaanList ?? []); @endphp

    <x-modul-dokumen.kartu judul="Pemberitahuan Penundaan / Kelambatan Pelayanan"
        :jumlah="$ppCount"
        satuan="catatan"
        :nonaktif="$disabled || !$rjNo">
        <x-slot:deskripsi>Formulir pemberitahuan kepada pasien/keluarga atas penundaan atau kelambatan pelayanan beserta alasan dan alternatif yang ditawarkan. Dapat lebih dari satu catatan.</x-slot:deskripsi>
        <div class="overflow-x-auto rounded-2xl border border-hairline dark:border-gray-700">
            <table class="min-w-full text-sm">
                <thead class="bg-surface-card dark:bg-gray-800">
                    <tr class="text-xs font-semibold tracking-wide text-left text-muted uppercase dark:text-gray-300">
                        <th class="px-3 py-2 border-b">Jenis</th>
                        <th class="px-3 py-2 border-b">Tanggal</th>
                        <th class="px-3 py-2 border-b">Pemberi Informasi</th>
                        <th class="px-3 py-2 border-b">Respon</th>
                        <th class="px-3 py-2 border-b text-center">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse (collect($penundaanList)->sortByDesc(fn($entri) => strtotime(strtr(($entri['tanggal'] ?? '') ?: ($entri['createdAt'] ?? ''), '/', '-')))->values()->all() as $pp)
                        <tr class="border-b border-hairline dark:border-gray-700">
                            <td class="px-3 py-2 font-medium text-ink dark:text-gray-200">
                                {{ Str::limit($pp['jenis'] ?: ($pp['alasan'] ?? '-'), 50) ?: '-' }}
                            </td>
                            <td class="px-3 py-2 text-muted dark:text-gray-400">{{ $pp['signatureDate'] ?? '-' }}</td>
                            <td class="px-3 py-2 text-muted dark:text-gray-400">
                                <x-modul-dokumen.status-ttd :nama="$pp['pemberiInfo'] ?? ''" gaya="polos" />
                            </td>
                            <td class="px-3 py-2 text-muted dark:text-gray-400">{{ $pp['respon'] ?? '-' }}</td>
                            <td class="px-3 py-2 text-center">
                                <x-modul-dokumen.status-entri :final="$this->entryIsFinal($pp)" />
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-3 py-6 text-center text-muted-soft">Belum ada data tersimpan</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-modul-dokumen.kartu>

    {{-- ══ MODAL FORM ══ --}}
    <x-modal name="rm-penundaan-pelayanan-ugd-{{ $rjNo ?? 'init' }}" size="full" height="full" focusable>
        <div class="flex flex-col min-h-full"
            wire:key="{{ $this->renderKey('modal-penundaan-pelayanan-ugd', [$rjNo ?? 'new']) }}">
            <x-modul-dokumen.header judul="Pemberitahuan Penundaan / Kelambatan Pelayanan"
                ikon="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"
                jalur="UGD" :jumlah="count($penundaanList)" :readOnly="$isFormLocked">
                Formulir diisi & dijelaskan kepada pasien/keluarga — tampilan dapat diputar ke arah pasien
            </x-modul-dokumen.header>

            {{-- DISPLAY PASIEN — paling atas, mengikuti pola EMR --}}
            <div class="px-4 pt-2">
                <livewire:pages::transaksi.ugd.display-pasien-ugd.display-pasien-ugd :rjNo="$rjNo"
                    wire:key="pp-ugd-display-pasien-{{ $rjNo ?? 'init' }}" />
            </div>

            {{-- BODY --}}
            <div class="flex-1 px-4 py-4 bg-surface-soft/70 dark:bg-gray-950/20">
                <div class="max-w-full mx-auto space-y-4">

                    {{-- Display Pasien --}}

                    <div
                        class="{{ $this->diForm() ? 'p-6 sm:p-8 bg-canvas border border-hairline shadow-sm rounded-2xl dark:bg-gray-900 dark:border-gray-700' : '' }} space-y-6">

                        @php $formReadOnly = $isFormLocked || $viewOnly; @endphp

                        @if ($isFormLocked)
                            <x-modul-dokumen.banner jenis="terkunci" />
                        @endif

                        @if ($viewOnly)
                            <x-modul-dokumen.banner jenis="lihat" />
                        @elseif ($editingKey && !$isFormLocked)
                            <x-modul-dokumen.banner jenis="lanjut" />
                        @endif

                        {{-- ══ TANGGAL/JAM PEMBERITAHUAN ══ --}}
                        @if ($this->diForm())
                        <section>
                            <x-input-label value="Tanggal / Jam Pemberitahuan *" class="mb-1" />
                            <div class="flex items-center gap-2">
                                <x-text-input wire:model.live="newForm.tglPemberitahuan" placeholder="dd/mm/yyyy HH:mm:ss"
                                    :error="$errors->has('newForm.tglPemberitahuan')" :disabled="$formReadOnly"
                                    class="w-full max-w-xs" />
                                @if (!$formReadOnly)
                                    <x-now-button wire:click="setTglPemberitahuanSekarang" />
                                @endif
                            </div>
                            <x-input-error :messages="$errors->get('newForm.tglPemberitahuan')" class="mt-1" />
                        </section>

                        {{-- ══ JENIS PELAYANAN YANG DITUNDA / TERLAMBAT ══ --}}
                        <section class="pt-6 space-y-3 border-t border-hairline dark:border-gray-700">
                            <h3 class="text-base font-semibold text-ink dark:text-gray-200">
                                Pelayanan yang Ditunda / Terlambat *
                            </h3>
                            <x-textarea wire:model.live="newForm.jenis" :error="$errors->has('newForm.jenis')" rows="2"
                                placeholder="cth: Tindakan, Pengobatan, Pemeriksaan Penunjang (Lab), Radiologi, Operasi, Rawat Inap (daftar tunggu)..."
                                :disabled="$formReadOnly" class="w-full" />
                            <x-input-error :messages="$errors->get('newForm.jenis')" class="mt-1" />
                        </section>

                        {{-- ══ ALASAN & ALTERNATIF ══ --}}
                        <section class="pt-6 space-y-4 border-t border-hairline dark:border-gray-700">
                            <div>
                                <x-input-label value="Alasan Penundaan / Kelambatan *" class="mb-1" />
                                <x-textarea wire:model.live="newForm.alasan" :error="$errors->has('newForm.alasan')" rows="3"
                                    placeholder="Jelaskan alasan penundaan / kelambatan pelayanan..."
                                    :disabled="$formReadOnly" class="w-full" />
                                <x-input-error :messages="$errors->get('newForm.alasan')" class="mt-1" />
                            </div>

                            <div>
                                <x-input-label value="Jadwal Ulang" class="mb-1" />
                                <div class="flex items-center gap-2">
                                    <x-text-input wire:model.live="newForm.jadwalUlang" placeholder="dd/mm/yyyy HH:mm:ss"
                                        :error="$errors->has('newForm.jadwalUlang')" :disabled="$formReadOnly"
                                        class="w-full max-w-xs" />
                                    @if (!$formReadOnly)
                                        <x-now-button wire:click="setJadwalUlangSekarang" />
                                    @endif
                                </div>
                                <x-input-error :messages="$errors->get('newForm.jadwalUlang')" class="mt-1" />
                            </div>

                            <div>
                                <x-input-label value="Alternatif yang Ditawarkan (sesuai kebutuhan klinis)" class="mb-1" />
                                <x-textarea wire:model.live="newForm.alternatif" :error="$errors->has('newForm.alternatif')" rows="3"
                                    placeholder="Alternatif pelayanan/rujukan yang ditawarkan..."
                                    :disabled="$formReadOnly" class="w-full" />
                            </div>
                        </section>

                        {{-- ══ RESPON PASIEN/KELUARGA ══ --}}
                        <section class="pt-6 space-y-3 border-t border-hairline dark:border-gray-700">
                            <x-input-label value="Respon Pasien / Keluarga *" class="mb-1" />
                            <div class="flex flex-wrap gap-2">
                                @foreach ($responOptions as $opt)
                                    <x-radio-button :label="$opt" :value="$opt" name="respon"
                                        wire:model.live="newForm.respon" :disabled="$formReadOnly" />
                                @endforeach
                            </div>
                            <x-input-error :messages="$errors->get('newForm.respon')" class="mt-1" />
                        </section>

                        {{-- ══ CATATAN KEBIJAKAN ══ --}}
                        <div
                            class="px-4 py-3 text-sm border rounded-xl bg-surface-soft border-hairline text-muted dark:bg-gray-800 dark:border-gray-700 dark:text-gray-400">
                            Tidak berlaku untuk keterlambatan staf medis di RJ / IGD penuh. Onkologi &amp; transplantasi
                            mengikuti norma nasional. Dicatat di rekam medis (Lihat KE 2).
                        </div>

                        {{-- ══ TANDA TANGAN ══ --}}
                        <section class="pt-6 space-y-4 border-t border-hairline dark:border-gray-700">
                            <h3 class="text-base font-semibold text-ink dark:text-gray-200">
                                Tanda Tangan
                            </h3>

                            <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                                {{-- Pasien / Keluarga --}}
                                <div class="flex flex-col">
                                    <div
                                        class="mb-2 text-sm font-semibold tracking-wide text-center text-muted uppercase dark:text-gray-400">
                                        Pasien / Keluarga
                                    </div>
                                    <x-input-error :messages="$errors->get('signature')" class="mb-2" />
                                    @if (!empty($signature))
                                        <x-signature.signature-result :signature="$signature" :date="''"
                                            :disabled="$formReadOnly" wireMethod="clearSignature" />
                                    @elseif (!$formReadOnly)
                                        <x-signature.signature-pad wireMethod="setSignature" />
                                    @else
                                        <p class="py-8 text-base italic text-center text-muted-soft">Belum
                                            ditandatangani.</p>
                                    @endif

                                    <div class="mt-3">
                                        <x-input-label value="Nama Pasien / Keluarga *" class="mb-1" />
                                        <x-text-input wire:model.live="newForm.namaPenanda" :error="$errors->has('newForm.namaPenanda')"
                                            placeholder="Nama penanda tangan..." :disabled="$formReadOnly"
                                            class="w-full" />
                                        <x-input-error :messages="$errors->get('newForm.namaPenanda')" class="mt-1" />
                                    </div>

                                    <div class="mt-2">
                                        <x-input-label value="Hubungan dengan Pasien *" class="mb-1" />
                                        <x-select-input wire:model.live="newForm.hubunganPasien" :error="$errors->has('newForm.hubunganPasien')"
                                            :disabled="$formReadOnly" class="w-full">
                                            @foreach ($hubunganPasienOptions as $opt)
                                                <option value="{{ $opt['value'] }}">{{ $opt['label'] }}</option>
                                            @endforeach
                                        </x-select-input>
                                        <x-input-error :messages="$errors->get('newForm.hubunganPasien')" class="mt-1" />
                                    </div>
                                </div>

                                {{-- Pemberi Informasi (DPJP/PPA) --}}
                                <div class="flex flex-col">
                                    <div
                                        class="mb-2 text-sm font-semibold tracking-wide text-center text-muted uppercase dark:text-gray-400">
                                        Pemberi Informasi (DPJP / PPA)
                                    </div>
                                    @if (empty($newForm['pemberiInfo']))
                                        @if (!$formReadOnly)
                                            <div
                                                class="flex flex-col items-center justify-center flex-1 gap-2 p-6 border-2 border-gray-300 border-dashed rounded-xl dark:border-gray-700">
                                                <x-primary-button wire:click.prevent="setPemberiInfo"
                                                    wire:loading.attr="disabled" wire:target="setPemberiInfo"
                                                    class="gap-2">
                                                    <span wire:loading.remove wire:target="setPemberiInfo"
                                                        class="flex items-center gap-1.5">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                            viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                stroke-width="2"
                                                                d="M15.232 5.232l3.536 3.536M9 13l6.586-6.586a2 2 0 012.828 2.828L11.828 15.828a4 4 0 01-2.828 1.172H7v-2a4 4 0 011.172-2.828z" />
                                                        </svg>
                                                        TTD Petugas &amp; Kunci
                                                    </span>
                                                    <span wire:loading wire:target="setPemberiInfo">
                                                        <x-loading class="w-4 h-4" /> Mengunci...
                                                    </span>
                                                </x-primary-button>
                                                <p class="text-xs text-center text-muted">Menandatangani = validasi &amp; mengunci penundaan ini.</p>
                                            </div>
                                        @else
                                            <p class="py-8 text-base italic text-center text-muted-soft">Belum
                                                ditandatangani.</p>
                                        @endif
                                    @else
                                        {{-- Stempel petugas: gambar TTD + field nama readonly + kode/waktu — seragam dgn kolom pasien/saksi --}}
                                        <x-signature.ttd-petugas :framed="false" :ttd="$newForm['pemberiInfo']" :code="$newForm['pemberiInfoCode'] ?? ''"
                                            :date="$newForm['pemberiInfoDate'] ?? ''" :locked="true" nameLabel="Nama Pemberi Informasi" />
                                    @endif
                                </div>
                            </div>
                        </section>

                        {{-- ══ DAFTAR TERSIMPAN (expandable) ══ --}}
                        @endif
                        @unless ($this->diForm())
                            <x-border-form padding="p-0">
                            <div class="overflow-x-auto rounded-2xl">
                                <table class="min-w-full text-base">
                                    <thead class="sticky top-0 z-10 bg-surface-card dark:bg-gray-800">
                                        <tr class="text-xs font-semibold tracking-wide text-left text-muted uppercase dark:text-gray-300">
                                            <th class="whitespace-nowrap w-8 px-2 py-3 border-b bg-surface-card dark:bg-gray-800"></th>
                                            <th class="whitespace-nowrap px-4 py-3 border-b bg-surface-card dark:bg-gray-800">Jenis</th>
                                            <th class="whitespace-nowrap px-4 py-3 border-b bg-surface-card dark:bg-gray-800">Tanggal Dibuat</th>
                                            <th class="whitespace-nowrap px-4 py-3 border-b bg-surface-card dark:bg-gray-800">Pemberi Informasi</th>
                                            <th class="whitespace-nowrap px-4 py-3 border-b bg-surface-card dark:bg-gray-800">Respon</th>
                                            <th class="whitespace-nowrap px-4 py-3 border-b text-center bg-surface-card dark:bg-gray-800">Status</th>
                                            <th class="whitespace-nowrap px-4 py-3 border-b text-center bg-surface-card dark:bg-gray-800">Aksi</th>
                                        </tr>
                                    </thead>
                                    @forelse (collect($penundaanList)->sortByDesc(fn($entri) => strtotime(strtr(($entri['tanggal'] ?? '') ?: ($entri['createdAt'] ?? ''), '/', '-')))->values()->all() as $entry)
                                        @php
                                            // Normalisasi entri lama agar semua key ada (cegah "Undefined array key")
                                            $entry = array_replace([
                                                'tglPemberitahuan' => '', 'jenis' => '', 'alasan' => '', 'jadwalUlang' => '',
                                                'alternatif' => '', 'respon' => '', 'namaPenanda' => '', 'hubunganPasien' => '',
                                                'pemberiInfo' => '', 'pemberiInfoCode' => '', 'pemberiInfoDate' => '',
                                                'signature' => '', 'signatureDate' => '',
                                            ], $entry);
                                            $isFinal = $this->entryIsFinal($entry);
                                            $rowKey = $entry['signatureDate'] ?? '';
                                            $hubLabel = collect($hubunganPasienOptions)->firstWhere('value', $entry['hubunganPasien'] ?? '')['label'] ?? ($entry['hubunganPasien'] ?? '');
                                        @endphp
                                        {{-- Semua baris mulai TERTUTUP: daftar dipakai untuk MEMILIH entri, bukan
                                             membacanya. Baris teratas yang terbuka sendiri bikin grid langsung panjang. --}}
                                        <tbody x-data="{ open: false }" class="border-b border-hairline dark:border-gray-700">
                                            <tr @click="open = !open"
                                                class="cursor-pointer hover:bg-surface-soft dark:hover:bg-gray-800 {{ $editingKey && $editingKey === $rowKey ? 'bg-brand-lime/10 dark:bg-brand-lime/5' : '' }}">
                                                <td class="px-2 py-3 text-center align-middle">
                                                    <svg class="w-4 h-4 mx-auto text-muted transition-transform" :class="{ 'rotate-90': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                                    </svg>
                                                </td>
                                                <td class="px-4 py-3 align-middle font-semibold text-ink dark:text-gray-100">
                                                    {{ Str::limit(($entry['jenis'] ?: ($entry['alasan'] ?? '')) ?: '(tanpa jenis penundaan)', 50) }}
                                                </td>
                                                <td class="px-4 py-3 align-middle text-sm tabular-nums text-muted dark:text-gray-400">
                                                    {{ $rowKey ?: '-' }}
                                                </td>
                                                <td class="px-4 py-3 align-middle text-muted dark:text-gray-300">
                                                    <x-modul-dokumen.status-ttd :nama="$entry['pemberiInfo'] ?? ''" />
                                                </td>
                                                <td class="px-4 py-3 align-middle text-muted dark:text-gray-300">
                                                    {{ $entry['respon'] ?: '-' }}
                                                </td>
                                                <td class="px-4 py-3 align-middle text-center">
                                                    <x-modul-dokumen.status-entri :final="$isFinal" />
                                                </td>
                                                <td class="px-4 py-3 align-middle text-center whitespace-nowrap" @click.stop>
                                                    <x-modul-dokumen.aksi-entri kunci="{{ $rowKey }}" :final="$isFinal" :terkunci="$isFormLocked"
                                                        :cetakHanyaFinal="true"
                                                        judulLihat="Lihat detail (read-only) di form atas"
                                                        judulBukaKunci="Buka Kunci Pemberitahuan Penundaan Pelayanan"
                                                        konfirmasiHapus="Yakin hapus pemberitahuan ini?" />
                                                </td>
                                            </tr>

                                            {{-- DETAIL (expand) --}}
                                            <tr x-show="open" x-cloak>
                                                <td colspan="7" class="px-4 py-4 bg-surface-soft/60 dark:bg-gray-950/30">
                                                    <dl class="grid grid-cols-1 gap-x-8 gap-y-3 md:grid-cols-2">
                                                        <div>
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Tgl / Jam Pemberitahuan</dt>
                                                            <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['tglPemberitahuan'] ?: '-' }}</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Jenis Pelayanan Ditunda</dt>
                                                            <dd class="mt-0.5 whitespace-pre-line text-ink dark:text-gray-200">{{ $entry['jenis'] ?: '-' }}</dd>
                                                        </div>
                                                        <div class="md:col-span-2">
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Alasan Penundaan / Kelambatan</dt>
                                                            <dd class="mt-0.5 whitespace-pre-line text-ink dark:text-gray-200">{{ $entry['alasan'] ?: '-' }}</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Jadwal Ulang</dt>
                                                            <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['jadwalUlang'] ?: '-' }}</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Respon Pasien / Keluarga</dt>
                                                            <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['respon'] ?: '-' }}</dd>
                                                        </div>
                                                        <div class="md:col-span-2">
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Alternatif yang Ditawarkan</dt>
                                                            <dd class="mt-0.5 whitespace-pre-line text-ink dark:text-gray-200">{{ $entry['alternatif'] ?: '-' }}</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Nama Pasien / Keluarga</dt>
                                                            <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['namaPenanda'] ?: '-' }}@if ($hubLabel) <span class="text-muted">({{ $hubLabel }})</span>@endif</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">TTD Pasien / Keluarga</dt>
                                                            <dd class="mt-0.5">
                                                                <x-modul-dokumen.status-ttd :sudah="!empty($entry['signature'])" :waktu="$entry['signatureDate'] ?? '-'" gaya="biasa" />
                                                            </dd>
                                                        </div>
                                                        <div>
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Pemberi Informasi (Petugas)</dt>
                                                            <dd class="mt-0.5">
                                                                <x-modul-dokumen.status-ttd :nama="$entry['pemberiInfo'] ?? ''" :waktu="$entry['pemberiInfoDate'] ?? '-'" gaya="biasa" />
                                                            </dd>
                                                        </div>
                                                    </dl>
                                                </td>
                                            </tr>
                                        </tbody>
                                    @empty
                                        <tbody>
                                            <tr>
                                                <td colspan="7" class="px-6 py-12">
                                                    <div class="flex flex-col items-center justify-center gap-3">
                                                        <svg class="w-12 h-12 text-muted-soft" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" /></svg>
                                                        <p class="text-base font-medium text-muted dark:text-gray-400">Belum ada data tersimpan</p>
                                                    </div>
                                                </td>
                                            </tr>
                                        </tbody>
                                    @endforelse
                                </table>
                            </div>
                            </x-border-form>
                        @endunless

                    </div>
                </div>
            </div>

            {{-- FOOTER --}}
            <x-modul-dokumen.footer :formulir="$this->diForm()" :terkunci="$isFormLocked"
                :lihat="$viewOnly"
                :bisaSimpan="$rjNo && !$isFormLocked"
                :mengedit="$editingKey">
                Simpan draft dulu, lalu <strong>kunci</strong> lewat tombol <strong>TTD Petugas &amp; Kunci</strong> di kolom Pemberi Informasi.
            </x-modul-dokumen.footer>

        </div>
    </x-modal>

    {{-- Cetak component --}}
    <livewire:pages::components.modul-dokumen.ugd.penundaan-pelayanan.cetak-penundaan-pelayanan
        wire:key="cetak-penundaan-pelayanan-ugd-{{ $rjNo ?? 'init' }}" />
</div>
