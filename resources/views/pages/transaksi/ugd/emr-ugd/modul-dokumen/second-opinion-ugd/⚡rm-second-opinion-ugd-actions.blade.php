<?php
// resources/views/pages/transaksi/ugd/emr-ugd/modul-dokumen/second-opinion-ugd/rm-second-opinion-ugd-actions.blade.php
// Port dari Second Opinion UGD.

use Livewire\Component;
use App\Http\Traits\Txn\Ugd\EmrUGDTrait;
use App\Http\Traits\Master\MasterPasien\MasterPasienTrait;
use App\Http\Traits\Concerns\WithRenderVersioningTrait;
use App\Http\Traits\Concerns\WithValidationToastTrait;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Support\TtdUser;
use App\Support\TtdPasien;

new class extends Component {
    use EmrUGDTrait, MasterPasienTrait, WithRenderVersioningTrait, WithValidationToastTrait;

    public bool $isFormLocked = false;
    public ?int $rjNo = null;
    public ?string $regNo = null;
    public bool $disabled = false;
    /** Nama pasien untuk isian awal penanda tangan (dulu dibaca dari dokumen penuh). */
    public ?string $regName = null;

    public array $renderVersions = [];
    protected array $renderAreas = ['modal-second-opinion-ugd'];

    public array $newForm = [
        'tglPermintaan' => '',
        'kategori' => '',
        'uraian' => '',
        'alasan' => '',
        'namaPenanda' => '',
        'hubunganPasien' => 'pasien',
        'pemberiInfo' => '',
        'pemberiInfoCode' => '',
        'pemberiInfoDate' => '',
    ];

    public string $signature = '';

    public array $secondOpinionList = [];

    public array $kategoriOptions = ['Tindakan Medis', 'Pengobatan / Obat', 'Pemilihan Tenaga Medis'];

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

    public ?string $editingKey = null;

    // Layar aktif di modal: 'daftar' (grid entri) atau 'form' (tambah/edit/lihat).
    // Formulir sengaja tidak nongkrong bersama daftarnya: dulu ia ikut tampil terus lalu
    // dikosongkan diam-diam sesudah tersimpan, dan petugas yang mengira itu masih formulir
    // yang tadi diisi mengetik ulang — tersimpan sebagai draft baru.
    public string $layar = 'daftar';

    /** Dokumen dibaca sebagai variabel LOKAL; hanya irisan di bawah ini yang disimpan. */
    private function muatDariDokumen(array $data): void
    {
        $this->regNo = $data['regNo'] ?? null;
        $this->secondOpinionList = is_array($data['secondOpinionUGD'] ?? null) ? $data['secondOpinionUGD'] : [];
        $this->regName = $data['regName'] ?? null;
    }
    public bool $viewOnly = false;

    /* ===============================
     | MOUNT
     =============================== */
    public function mount(?int $rjNo = null, bool $disabled = false): void
    {
        $this->rjNo = $rjNo ?: null;
        $this->disabled = $disabled;
        $this->registerAreas(['modal-second-opinion-ugd']);

        if ($this->rjNo) {
            $data = $this->findDataUGD($this->rjNo);
            if ($data) {
                $this->muatDariDokumen($data);
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

        $this->muatDariDokumen($data);
        $this->newForm['namaPenanda'] = $this->regName ?? '';
        $this->isFormLocked = $this->checkEmrUGDStatus($this->rjNo) || $this->disabled;
        $this->incrementVersion('modal-second-opinion-ugd');

        $this->layar = 'daftar';

        $this->dispatch('open-modal', name: "rm-second-opinion-ugd-{$this->rjNo}");
    }

    /* ===============================
     | CLOSE
     =============================== */
    public function closeModal(): void
    {
        $this->dispatch('close-modal', name: "rm-second-opinion-ugd-{$this->rjNo}");
    }

    /* ===============================
     | VALIDATION
     =============================== */
    protected function rules(): array
    {
        return [
            'newForm.tglPermintaan' => 'required|date_format:d/m/Y H:i:s',
            'newForm.kategori' => 'required|string|max:200',
            'newForm.uraian' => 'required|string|max:1000',
            'newForm.alasan' => 'required|string|max:1000',
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
            'newForm.tglPermintaan' => 'Tanggal/jam permintaan',
            'newForm.kategori' => 'Kategori second opinion',
            'newForm.uraian' => 'Uraian permintaan',
            'newForm.alasan' => 'Alasan permintaan second opinion',
            'newForm.namaPenanda' => 'Nama pasien/keluarga',
            'newForm.hubunganPasien' => 'Hubungan dengan pasien',
            'signature' => 'Tanda tangan pasien/keluarga',
        ];
    }

    /* ===============================
     | SET TANGGAL SEKARANG
     =============================== */
    public function setNow(string $field): void
    {
        if ($this->isFormLocked || $this->viewOnly) {
            return;
        }
        $this->newForm[$field] = \Carbon\Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
    }

    /* ===============================
     | SIGNATURE (pasien/keluarga)
     =============================== */
    public function setSignature(string $dataUrl): void
    {
        if ($this->isFormLocked || $this->viewOnly) {
            return;
        }
        $this->signature = TtdPasien::simpan($dataUrl, $this->regNo);   // gambar ke RSTXN_TTDS; di sini cukup referensi "TTD:<no>"
        $this->incrementVersion('modal-second-opinion-ugd');
    }

    public function clearSignature(): void
    {
        if ($this->isFormLocked || $this->viewOnly) {
            return;
        }
        $this->signature = '';
        $this->incrementVersion('modal-second-opinion-ugd');
    }

    /* ===============================
     | TTD PETUGAS = FINALIZE
     =============================== */
    public function setPemberiInfo(): void
    {
        if ($this->isFormLocked || $this->viewOnly) {
            $this->dispatch('toast', type: 'error', message: 'Form read-only.');
            return;
        }
        // validate() HARUS duluan — guard yang return lebih awal bikin $errors
        // kosong, sehingga border & teks merah per-field tak pernah tampil.
        // TTD pasien sudah tercakup rule 'signature' => required.
        $this->validateWithToast();

        $this->newForm['pemberiInfo'] = auth()->user()->myuser_name ?? '';
        $this->newForm['pemberiInfoCode'] = auth()->user()->myuser_code ?? '';
        $this->newForm['pemberiInfoDate'] = \Carbon\Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');

        $key = $this->editingKey ?: \Carbon\Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');

        try {
            $this->persistEntry($key, true, 'Kunci (TTD Petugas)');
            $this->resetNewForm();
            $this->newForm['namaPenanda'] = $this->regName ?? '';
            $this->signature = '';
            $this->editingKey = null;
            $this->viewOnly = false;
            $this->incrementVersion('modal-second-opinion-ugd');
            $this->dispatch('toast', type: 'success', message: 'Second opinion ditandatangani petugas dan terkunci.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal mengunci: ' . $e->getMessage());
        }
    }

    /* ===============================
     | HELPER — status & bentuk entri
     =============================== */
    public function entryIsFinal(array $e): bool
    {
        return array_key_exists('finalized', $e) ? (bool) $e['finalized'] : !empty($e['signature']);
    }

    private function buildEntry(string $key, bool $finalized): array
    {
        return [
            'tglPermintaan' => $this->newForm['tglPermintaan'] ?? '',
            'kategori' => $this->newForm['kategori'] ?? '',
            'uraian' => $this->newForm['uraian'] ?? '',
            'alasan' => $this->newForm['alasan'] ?? '',
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

    private function persistEntry(string $key, bool $finalized, string $logVerb): void
    {
        $entry = $this->buildEntry($key, $finalized);

        DB::transaction(function () use ($entry, $key, $logVerb) {
            $this->lockUGDRow($this->rjNo);

            $data = $this->findDataUGD($this->rjNo);
            if (empty($data)) {
                throw new \RuntimeException('Data UGD tidak ditemukan, simpan dibatalkan.');
            }
            if (!isset($data['secondOpinionUGD']) || !is_array($data['secondOpinionUGD'])) {
                $data['secondOpinionUGD'] = [];
            }

            $list = $data['secondOpinionUGD'];
            $idx = collect($list)->search(fn($it) => ($it['signatureDate'] ?? '') === $key);
            if ($idx === false) {
                $list[] = $entry;
            } else {
                if ($this->entryIsFinal($list[$idx])) {
                    throw new \RuntimeException('Entri sudah terkunci, tidak dapat diubah.');
                }
                $list[$idx] = $entry;
            }
            $data['secondOpinionUGD'] = array_values($list);

            $this->updateJsonUGD($this->rjNo, $data);
            $this->muatDariDokumen($data);

            $this->appendAdminLogUGD((int) $this->rjNo, $logVerb . ' Second Opinion UGD — kategori "' . ($entry['kategori'] ?: '-') . '" (' . $key . ')', 'MR');
        });
    }

    /* ===============================
     | SIMPAN DRAFT
     =============================== */
    public function saveDraft(): void
    {
        if ($this->isFormLocked || $this->viewOnly) {
            $this->dispatch('toast', type: 'error', message: 'Form read-only, tidak dapat menyimpan.');
            return;
        }
        if (trim($this->newForm['kategori'] ?? '') === '') {
            $this->dispatch('toast', type: 'error', message: 'Kategori second opinion wajib diisi untuk menyimpan draft.');
            return;
        }

        $key = $this->editingKey ?: \Carbon\Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');

        try {
            $this->persistEntry($key, false, 'Simpan draft');
            $this->editingKey = $key;
            $this->incrementVersion('modal-second-opinion-ugd');
            $this->dispatch('toast', type: 'success', message: 'Draft tersimpan.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menyimpan draft: ' . $e->getMessage());
        }
    }

    /* ===============================
     | EDIT / LIHAT / BATAL entri
     =============================== */
    private function hydrateFormFromEntry(array $entry, string $key): void
    {
        $this->newForm = [
            'tglPermintaan' => $entry['tglPermintaan'] ?? '',
            'kategori' => $entry['kategori'] ?? '',
            'uraian' => $entry['uraian'] ?? '',
            'alasan' => $entry['alasan'] ?? '',
            'namaPenanda' => $entry['namaPenanda'] ?? '',
            'hubunganPasien' => $entry['hubunganPasien'] ?? 'pasien',
            'pemberiInfo' => $entry['pemberiInfo'] ?? '',
            'pemberiInfoCode' => $entry['pemberiInfoCode'] ?? '',
            'pemberiInfoDate' => $entry['pemberiInfoDate'] ?? '',
        ];
        $this->signature = $entry['signature'] ?? '';
        $this->editingKey = $key;
        $this->resetValidation();
        $this->incrementVersion('modal-second-opinion-ugd');
    }

    public function editEntry(string $key): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form read-only.');
            return;
        }
        $entry = collect($this->secondOpinionList)->firstWhere('signatureDate', $key);
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

    public function viewEntry(string $key): void
    {
        $entry = collect($this->secondOpinionList)->firstWhere('signatureDate', $key);
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
        $this->newForm['namaPenanda'] = $this->regName ?? '';
        $this->signature = '';
        $this->editingKey = null;
        $this->viewOnly = false;
        $this->resetValidation();
        $this->incrementVersion('modal-second-opinion-ugd');
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
    public function cetak(string $signatureDate)
    {
        $entry = collect($this->secondOpinionList)->firstWhere('signatureDate', $signatureDate);
        if (!$entry) {
            $this->dispatch('toast', type: 'error', message: 'Data formulir tidak ditemukan.');
            return;
        }

        try {
            $identitasRs = DB::table('rsmst_identitases')->select('int_name', 'int_phone1', 'int_phone2', 'int_fax', 'int_address', 'int_city')->first();
            $pasienData = $this->findDataMasterPasien($this->regNo ?? '');
            $pasien = $pasienData['pasien'] ?? [];

            if (!empty($pasien['tglLahir'])) {
                try {
                    $pasien['thn'] = \Carbon\Carbon::createFromFormat('d/m/Y', $pasien['tglLahir'])->diff(\Carbon\Carbon::now(config('app.timezone')))->format('%y Thn, %m Bln %d Hr');
                } catch (\Throwable) {
                    $pasien['thn'] = '-';
                }
            }

            $ttdPemberiPath = null;
            $pemberiCode = $entry['pemberiInfoCode'] ?? null;
            if ($pemberiCode) {
                $ttdPath = DB::table('users')->where('myuser_code', $pemberiCode)->value('myuser_ttd_image');
                if (!empty($ttdPath) && file_exists(TtdUser::pathBerkas($ttdPath))) {
                    $ttdPemberiPath = TtdUser::pathBerkas($ttdPath);
                }
            }

            $data = array_merge($pasien, [
                'dataRi' => $this->findDataUGD($this->rjNo) ?: [],
                'form' => $entry,
                'identitasRs' => $identitasRs,
                'ttdPemberiPath' => $ttdPemberiPath,
                'tglCetak' => \Carbon\Carbon::now(config('app.timezone'))->translatedFormat('d F Y'),
            ]);

            set_time_limit(300);

            $pdf = Pdf::loadView('pages.components.modul-dokumen.ugd.second-opinion.cetak-second-opinion-print', ['data' => $data])->setPaper('A4');

            $this->dispatch('toast', type: 'success', message: 'Berhasil mencetak formulir second opinion.');
            return response()->streamDownload(fn() => print $pdf->output(), 'second-opinion-ugd-' . ($pasien['regNo'] ?? $this->rjNo) . '.pdf');
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal cetak: ' . $e->getMessage());
        }
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
                if (empty($data) || !isset($data['secondOpinionUGD'])) {
                    throw new \RuntimeException('Data formulir tidak ditemukan.');
                }

                $data['secondOpinionUGD'] = collect($data['secondOpinionUGD'])
                    ->reject(fn($item) => ($item['signatureDate'] ?? '') === $signatureDate)
                    ->values()
                    ->toArray();

                $this->updateJsonUGD($this->rjNo, $data);
                $this->muatDariDokumen($data);

                $this->appendAdminLogUGD((int) $this->rjNo, 'Hapus Second Opinion UGD — TTD ' . $signatureDate, 'MR');
            });

            $this->incrementVersion('modal-second-opinion-ugd');
            $this->dispatch('toast', type: 'success', message: 'Formulir second opinion berhasil dihapus.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menghapus: ' . $e->getMessage());
        }
    }

    /* ===============================
     | BUKA KUNCI
     =============================== */
    public function bukaKunci(string $signatureDate): void
    {
        if (!auth()->user()?->can('dokumen.bukaKunci')) {
            $this->dispatch('toast', type: 'error', message: 'Anda tidak berwenang membuka kunci.');
            return;
        }

        try {
            DB::transaction(function () use ($signatureDate) {
                $this->lockUGDRow($this->rjNo);

                $data = $this->findDataUGD($this->rjNo);
                if (empty($data) || !isset($data['secondOpinionUGD'])) {
                    throw new \RuntimeException('Data formulir tidak ditemukan.');
                }

                $list = $data['secondOpinionUGD'];
                $idx = collect($list)->search(fn($it) => ($it['signatureDate'] ?? '') === $signatureDate);
                if ($idx === false) {
                    throw new \RuntimeException('Entri tidak ditemukan.');
                }

                $list[$idx]['finalized'] = false;
                $list[$idx]['pemberiInfo'] = '';
                $list[$idx]['pemberiInfoCode'] = '';
                $list[$idx]['pemberiInfoDate'] = '';

                $data['secondOpinionUGD'] = $list;

                $this->updateJsonUGD($this->rjNo, $data);
                $this->muatDariDokumen($data);

                $pelaku = auth()->user()->myuser_name ?? auth()->user()->name ?? 'unknown';
                $this->appendAdminLogUGD((int) $this->rjNo, 'Buka Kunci Second Opinion UGD — TTD ' . $signatureDate . ' oleh ' . $pelaku, 'MR');
            });

            $this->incrementVersion('modal-second-opinion-ugd');
            $this->dispatch('toast', type: 'success', message: 'Kunci entri dibuka — TTD petugas dicabut, TTD pasien/keluarga dipertahankan.');
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
            'tglPermintaan' => '',
            'kategori' => '',
            'uraian' => '',
            'alasan' => '',
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
        $this->secondOpinionList = [];
        $this->resetNewForm();
        $this->signature = '';
        $this->editingKey = null;
        $this->viewOnly = false;
    }
};
?>

<div>
    {{-- ══ SUMMARY CARD (inline) ══ --}}
    @php $secondOpinionCount = count($secondOpinionList ?? []); @endphp

    <x-modul-dokumen.kartu judul="Permintaan Second Opinion"
        :jumlah="$secondOpinionCount"
        satuan="catatan"
        :nonaktif="$disabled || !$rjNo">
        <x-slot:deskripsi>Formulir permintaan pendapat medis kedua (second opinion) atas tindakan medis, pengobatan/obat, atau pemilihan tenaga medis. Dapat lebih dari satu catatan.</x-slot:deskripsi>
        <div class="overflow-x-auto rounded-2xl border border-hairline dark:border-gray-700">
            <table class="min-w-full text-sm">
                <thead class="bg-surface-card dark:bg-gray-800">
                    <tr class="text-xs font-semibold tracking-wide text-left text-muted uppercase dark:text-gray-300">
                        <th class="px-3 py-2 border-b">Kategori</th>
                        <th class="px-3 py-2 border-b">Tanggal</th>
                        <th class="px-3 py-2 border-b">Petugas</th>
                        <th class="px-3 py-2 border-b text-center">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse (collect($secondOpinionList)->sortByDesc(fn($entri) => strtotime(strtr(($entri['tanggal'] ?? '') ?: ($entri['createdAt'] ?? ''), '/', '-')))->values()->all() as $entry)
                        <tr class="border-b border-hairline dark:border-gray-700">
                            <td class="px-3 py-2 font-medium text-ink dark:text-gray-200">
                                {{ Str::limit($entry['kategori'] ?? '-', 50) }}
                            </td>
                            <td class="px-3 py-2 text-muted dark:text-gray-400">{{ $entry['signatureDate'] ?? '-' }}</td>
                            <td class="px-3 py-2 text-muted dark:text-gray-400">
                                <x-modul-dokumen.status-ttd :nama="$entry['pemberiInfo'] ?? ''" gaya="polos" />
                            </td>
                            <td class="px-3 py-2 text-center">
                                <x-modul-dokumen.status-entri :final="$this->entryIsFinal($entry)" />
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-3 py-6 text-center text-muted-soft">Belum ada data tersimpan</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-modul-dokumen.kartu>

    {{-- ══ MODAL FORM ══ --}}
    <x-modal name="rm-second-opinion-ugd-{{ $rjNo ?? 'init' }}" size="full" height="full" focusable>
        <div class="flex flex-col min-h-full"
            wire:key="{{ $this->renderKey('modal-second-opinion-ugd', [$rjNo ?? 'new']) }}">
            <x-modul-dokumen.header judul="Permintaan Second Opinion"
                ikon="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"
                jalur="UGD" :jumlah="count($secondOpinionList)" :readOnly="$isFormLocked">
                Formulir permintaan pendapat medis kedua — tindakan, obat, atau tenaga medis
            </x-modul-dokumen.header>

            {{-- DISPLAY PASIEN — paling atas, mengikuti pola EMR --}}
            <div class="px-4 pt-2">
                <livewire:pages::transaksi.ugd.display-pasien-ugd.display-pasien-ugd :rjNo="$rjNo"
                    wire:key="so-ugd-display-pasien-{{ $rjNo ?? 'init' }}" />
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

                        {{-- ══ TANGGAL & KATEGORI (satu baris) ══ --}}
                        @if ($this->diForm())
                        <section>
                            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div>
                                    <x-input-label value="Tanggal / Jam Permintaan *" class="mb-1" />
                                    <div class="flex items-center gap-2">
                                        <x-text-input wire:model.live="newForm.tglPermintaan" placeholder="dd/mm/yyyy HH:mm:ss"
                                            :error="$errors->has('newForm.tglPermintaan')" :disabled="$formReadOnly"
                                            class="w-full" />
                                        @if (!$formReadOnly)
                                            <x-now-button wire:click="setNow('tglPermintaan')" :disabled="$formReadOnly" />
                                        @endif
                                    </div>
                                    <x-input-error :messages="$errors->get('newForm.tglPermintaan')" class="mt-1" />
                                </div>
                                <div>
                                    <x-input-label value="Kategori Permintaan *" class="mb-1" />
                                    <x-select-input wire:model.live="newForm.kategori" :error="$errors->has('newForm.kategori')"
                                        :disabled="$formReadOnly" class="w-full">
                                        <option value="">— Pilih kategori —</option>
                                        @foreach ($kategoriOptions as $opt)
                                            <option value="{{ $opt }}">{{ $opt }}</option>
                                        @endforeach
                                    </x-select-input>
                                    <x-input-error :messages="$errors->get('newForm.kategori')" class="mt-1" />
                                </div>
                            </div>
                        </section>

                        {{-- ══ URAIAN & ALASAN ══ --}}
                        <section class="pt-6 border-t border-hairline dark:border-gray-700">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <x-input-label value="Uraian Permintaan Second Opinion *" class="mb-1" />
                                    <x-textarea wire:model.live="newForm.uraian" :error="$errors->has('newForm.uraian')" rows="3"
                                        placeholder="Jelaskan tindakan/obat/tenaga medis yang dimintakan second opinion..."
                                        :disabled="$formReadOnly" class="w-full" />
                                    <x-input-error :messages="$errors->get('newForm.uraian')" class="mt-1" />
                                </div>
                                <div>
                                    <x-input-label value="Alasan Permintaan Second Opinion *" class="mb-1" />
                                    <x-textarea wire:model.live="newForm.alasan" :error="$errors->has('newForm.alasan')" rows="3"
                                        placeholder="Jelaskan alasan pasien/keluarga meminta second opinion..."
                                        :disabled="$formReadOnly" class="w-full" />
                                    <x-input-error :messages="$errors->get('newForm.alasan')" class="mt-1" />
                                </div>
                            </div>
                        </section>

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

                                {{-- Petugas (DPJP/PPA) --}}
                                <div class="flex flex-col">
                                    <div
                                        class="mb-2 text-sm font-semibold tracking-wide text-center text-muted uppercase dark:text-gray-400">
                                        Petugas (DPJP / PPA)
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
                                                <p class="text-xs text-center text-muted">Menandatangani = validasi &amp; mengunci second opinion ini.</p>
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

                        {{-- ══ DAFTAR TERSIMPAN ══ --}}
                        @endif
                        @unless ($this->diForm())
                            <x-modul-dokumen.tabel-daftar :kolom="['', 'Kategori', 'Tanggal Dibuat', 'Petugas', 'Status' => 'text-center', 'Aksi' => 'text-center']">
                                    @forelse (collect($secondOpinionList)->sortByDesc(fn($entri) => strtotime(strtr(($entri['tanggal'] ?? '') ?: ($entri['createdAt'] ?? ''), '/', '-')))->values()->all() as $entry)
                                        @php
                                            $entry = array_replace([
                                                'tglPermintaan' => '', 'kategori' => '', 'uraian' => '', 'alasan' => '',
                                                'namaPenanda' => '', 'hubunganPasien' => '',
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
                                                    {{ Str::limit($entry['kategori'] ?: '(tanpa kategori)', 50) }}
                                                </td>
                                                <td class="px-4 py-3 align-middle text-sm tabular-nums text-muted dark:text-gray-400">
                                                    {{ $rowKey ?: '-' }}
                                                </td>
                                                <td class="px-4 py-3 align-middle text-muted dark:text-gray-300">
                                                    <x-modul-dokumen.status-ttd :nama="$entry['pemberiInfo'] ?? ''" />
                                                </td>
                                                <td class="px-4 py-3 align-middle text-center">
                                                    <x-modul-dokumen.status-entri :final="$isFinal" />
                                                </td>
                                                <td class="px-4 py-3 align-middle text-center whitespace-nowrap" @click.stop>
                                                    <x-modul-dokumen.aksi-entri kunci="{{ $rowKey }}" :final="$isFinal" :terkunci="$isFormLocked"
                                                        :cetakHanyaFinal="true"
                                                        judulLihat="Lihat detail (read-only) di form atas"
                                                        pesanBukaKunci="Yakin buka kunci entri ini? TTD petugas akan dicabut."
                                                        konfirmasiHapus="Yakin hapus permintaan second opinion ini?" />
                                                </td>
                                            </tr>

                                            {{-- DETAIL (expand) --}}
                                            <tr x-show="open" x-cloak>
                                                <td colspan="6" class="px-4 py-4 bg-surface-soft/60 dark:bg-gray-950/30">
                                                    <dl class="grid grid-cols-1 gap-x-8 gap-y-3 md:grid-cols-2">
                                                        <div>
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Tgl / Jam Permintaan</dt>
                                                            <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['tglPermintaan'] ?: '-' }}</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Kategori</dt>
                                                            <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['kategori'] ?: '-' }}</dd>
                                                        </div>
                                                        <div class="md:col-span-2">
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Uraian Permintaan</dt>
                                                            <dd class="mt-0.5 whitespace-pre-line text-ink dark:text-gray-200">{{ $entry['uraian'] ?: '-' }}</dd>
                                                        </div>
                                                        <div class="md:col-span-2">
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Alasan Permintaan</dt>
                                                            <dd class="mt-0.5 whitespace-pre-line text-ink dark:text-gray-200">{{ $entry['alasan'] ?: '-' }}</dd>
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
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Petugas (DPJP / PPA)</dt>
                                                            <dd class="mt-0.5">
                                                                <x-modul-dokumen.status-ttd :nama="$entry['pemberiInfo'] ?? ''" :waktu="$entry['pemberiInfoDate'] ?? '-'" gaya="biasa" />
                                                            </dd>
                                                        </div>
                                                    </dl>
                                                </td>
                                            </tr>
                                        </tbody>
                                    @empty
                                        <x-modul-dokumen.baris-kosong :kolom="6" />
                                    @endforelse
                            </x-modul-dokumen.tabel-daftar>
                        @endunless

                    </div>
                </div>
            </div>

            {{-- FOOTER --}}
            <x-modul-dokumen.footer :formulir="$this->diForm()" :terkunci="$isFormLocked"
                :lihat="$viewOnly"
                :bisaSimpan="$rjNo && !$isFormLocked"
                :mengedit="$editingKey">
                Simpan draft dulu, lalu <strong>kunci</strong> lewat tombol <strong>TTD Petugas &amp; Kunci</strong> di kolom Petugas.
            </x-modul-dokumen.footer>

        </div>
    </x-modal>
</div>
