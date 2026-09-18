<?php
// resources/views/pages/transaksi/ri/emr-ri/modul-dokumen/laporan-anestesi-ri/rm-laporan-anestesi-rj-actions.blade.php
// Laporan Anestesi — PAB 6 / RM 53. Multi-entri append-only:
// Draft (nyicil) + Lanjutkan Pengisian + TTD-Kunci (finalize) + Lihat (read-only) + tabel expandable.
// Disimpan ke datadaftarri_json key 'laporanAnestesiRJ'. Kunci entri stabil = createdAt.
// TTD ahli anestesiologi (setTtd) = FINALIZE/kunci; field kunci = 'ttd' (nama myuser_name).

use Livewire\Component;
use App\Http\Traits\Txn\Rj\EmrRJTrait;
use App\Http\Traits\Master\MasterPasien\MasterPasienTrait;
use App\Http\Traits\Concerns\WithRenderVersioningTrait;
use App\Http\Traits\Concerns\WithValidationToastTrait;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Support\TtdUser;

new class extends Component {
    use EmrRJTrait, MasterPasienTrait, WithRenderVersioningTrait, WithValidationToastTrait;

    public bool $isFormLocked = false;
    public ?string $rjNo = null;
    public ?string $regNo = null;
    public bool $disabled = false;

    public array $renderVersions = [];
    protected array $renderAreas = ['modal-laporan-anestesi-rj'];

    /** Key penyimpanan di datadaftarri_json */
    private string $jsonKey = 'laporanAnestesiRJ';

    // ── Laporan Anestesi — PAB 6 / RM 53 ──
    public array $newForm = [
        'tanggal' => '',
        'diagnosaPraBedah' => '',
        'diagnosaPascaBedah' => '',
        'jenisPembedahan' => '',
        'jenisAnestesi' => '',
        'lamaOperasi' => '',
        'lamaAnestesi' => '',
        // Keadaan pra bedah
        'tb' => '',
        'bb' => '',
        'golDarah' => '',
        'tensi' => '',
        'nadi' => '',
        'suhu' => '',
        'hb' => '',
        // Jalan nafas
        'jalanNafas' => '',
        'teknikAnestesi' => '',
        'teknikKhusus' => '',
        'pernafasan' => '',
        'posisi' => '',
        'infus' => '',
        'penyulitSelamaPembedahan' => '',
        // Monitoring sistem
        'saraf' => '',
        'sirkulasi' => '',
        'perfusi' => '',
        'gastrointestinal' => '',
        'ginjal' => '',
        'metabolik' => '',
        'hati' => '',
        'medikasiPraBedah' => '',
        'masalahAnestesi' => '',
        'masalahBedah' => '',
        'asa' => '',
        'keadaanAkhirPembedahan' => '',
        'penyulitPascaBedah' => '',
        // TTD ahli anestesiologi (auto)
        'ttd' => '',
        'ttdCode' => '',
        'ttdDate' => '',
    ];

    public array $laporanAnList = [];

    // Kunci entri yang sedang diedit (createdAt = kunci stabil). null = membuat entri baru.
    public ?string $editingKey = null;

    // Layar aktif di modal: 'daftar' (grid entri) atau 'form' (tambah/edit/lihat).
    // Formulir sengaja tidak nongkrong bersama daftarnya: dulu ia ikut tampil terus lalu
    // dikosongkan diam-diam sesudah tersimpan, dan petugas yang mengira itu masih formulir
    // yang tadi diisi mengetik ulang — tersimpan sebagai draft baru.
    public string $layar = 'daftar';

    // true = entri terkunci ditampilkan di form read-only (lihat saja).
    public bool $viewOnly = false;

    public array $asaOptions = ['ASA I', 'ASA II', 'ASA III', 'ASA IV', 'ASA V', 'ASA I-E', 'ASA II-E', 'ASA III-E', 'ASA IV-E', 'ASA V-E'];
    public array $jalanNafasOptions = ['Paten', 'Obstruksi'];
    public array $pernafasanOptions = ['Spontan', 'Kontrol', 'Assisted'];

    /* ===============================
     | MOUNT
     =============================== */
    public function mount(?string $rjNo = null, bool $disabled = false): void
    {
        $this->rjNo = $rjNo ?: null;
        $this->disabled = $disabled;
        $this->registerAreas(['modal-laporan-anestesi-rj']);

        if ($this->rjNo) {
            $data = $this->findDataRJ($this->rjNo);
            if ($data) {
                $this->regNo = $data['regNo'] ?? null;
                $this->laporanAnList = $data[$this->jsonKey] ?? [];
                $this->isFormLocked = $this->checkEmrRJStatus($this->rjNo) || $disabled;
            }
        }
    }

    /* ===============================
     | OPEN / CLOSE MODAL
     =============================== */
    public function openModal(): void
    {
        if (!$this->rjNo || $this->disabled) {
            return;
        }
        $this->resetNewForm();
        $this->editingKey = null;
        $this->viewOnly = false;
        $this->resetValidation();

        $data = $this->findDataRJ($this->rjNo);
        if (!$data) {
            $this->dispatch('toast', type: 'error', message: 'Data RJ tidak ditemukan.');
            return;
        }
        $this->regNo = $data['regNo'] ?? null;
        $this->laporanAnList = is_array($data[$this->jsonKey] ?? null) ? $data[$this->jsonKey] : [];
        $this->isFormLocked = $this->checkEmrRJStatus($this->rjNo) || $this->disabled;
        $this->incrementVersion('modal-laporan-anestesi-rj');
        $this->layar = 'daftar';
        $this->dispatch('open-modal', name: "rm-laporan-anestesi-rj-{$this->rjNo}");
    }

    public function closeModal(): void
    {
        $this->dispatch('close-modal', name: "rm-laporan-anestesi-rj-{$this->rjNo}");
    }

    protected function rules(): array
    {
        return [
            'newForm.tanggal' => 'required|date_format:d/m/Y H:i:s',
            'newForm.diagnosaPascaBedah' => 'required|string|max:500',
            'newForm.jenisPembedahan' => 'required|string|max:500',
            'newForm.jenisAnestesi' => 'required|string|max:200',
            'newForm.teknikAnestesi' => 'required|string|max:2000',
            'newForm.asa' => 'required|string',
        ];
    }

    protected function messages(): array
    {
        return [
            'required' => ':attribute wajib diisi.',
            'date_format' => 'Format :attribute harus dd/mm/yyyy HH:mm:ss.',
            'max' => ':attribute maksimal :max karakter.',
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'newForm.tanggal' => 'Tanggal/jam',
            'newForm.diagnosaPascaBedah' => 'Diagnosa pasca bedah',
            'newForm.jenisPembedahan' => 'Jenis pembedahan',
            'newForm.jenisAnestesi' => 'Jenis anestesi',
            'newForm.teknikAnestesi' => 'Teknik anestesi',
            'newForm.asa' => 'ASA',
        ];
    }

    /* ===============================
     | SET TANGGAL/JAM SEKARANG
     =============================== */
    public function setTanggalSekarang(): void
    {
        if ($this->isFormLocked || $this->viewOnly) {
            return;
        }
        $this->newForm['tanggal'] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
    }

    /* ===============================
     | HELPER — status & bentuk entri
     =============================== */
    // Entri FINAL/terkunci bila flag finalized true; entri lama (tanpa flag) yang sudah
    // ada TTD (nama ahli anestesiologi) dianggap final (kompatibilitas data lama).
    public function entryIsFinal(array $e): bool
    {
        return array_key_exists('finalized', $e) ? (bool) $e['finalized'] : !empty($e['ttd']);
    }

    // Susun array entri dari state form. $key = createdAt (kunci stabil); $finalized = status kunci.
    private function buildEntry(string $key, bool $finalized): array
    {
        $entry = [];
        foreach ($this->newForm as $k => $v) {
            $entry[$k] = $v;
        }
        $entry['createdAt'] = $key;
        $entry['finalized'] = $finalized;
        return $entry;
    }

    // Cek: minimal salah satu isian inti terisi (untuk draft).
    private function adaIsiInti(): bool
    {
        return collect(['jenisPembedahan', 'jenisAnestesi', 'teknikAnestesi', 'diagnosaPascaBedah'])
            ->contains(fn($k) => filled($this->newForm[$k] ?? null));
    }

    // Simpan entri (add/update by createdAt) dengan status $finalized. Dipakai draft & kunci.
    private function persistEntry(string $key, bool $finalized, string $logVerb): void
    {
        $entry = $this->buildEntry($key, $finalized);

        DB::transaction(function () use ($entry, $key, $logVerb) {
            $this->lockRJRow($this->rjNo);

            $fresh = $this->findDataRJ($this->rjNo) ?: [];
            if (empty($fresh)) {
                throw new \RuntimeException('Data RI tidak ditemukan, simpan dibatalkan.');
            }
            if (!isset($fresh[$this->jsonKey]) || !is_array($fresh[$this->jsonKey])) {
                $fresh[$this->jsonKey] = [];
            }

            $list = $fresh[$this->jsonKey];
            $idx = collect($list)->search(fn($it) => ($it['createdAt'] ?? '') === $key);
            if ($idx === false) {
                $list[] = $entry;
            } else {
                if ($this->entryIsFinal($list[$idx])) {
                    throw new \RuntimeException('Entri sudah terkunci, tidak dapat diubah.');
                }
                $list[$idx] = $entry;
            }
            $fresh[$this->jsonKey] = array_values($list);

            $this->updateJsonRJ((int) $this->rjNo, $fresh);
            $this->laporanAnList = $fresh[$this->jsonKey];

            $this->appendAdminLogRJ((int) $this->rjNo, $logVerb . ' Laporan Anestesi — ' . ($entry['jenisAnestesi'] ?: '-') . ' (' . $key . ')', 'MR');
        });
    }

    /* ===============================
     | SIMPAN DRAFT (nyicil, tanpa wajib TTD)
     =============================== */
    public function saveDraft(): void
    {
        if ($this->isFormLocked || $this->viewOnly) {
            $this->dispatch('toast', type: 'error', message: 'Form read-only, tidak dapat menyimpan.');
            return;
        }
        if (!$this->adaIsiInti()) {
            $this->dispatch('toast', type: 'error', message: 'Isi minimal salah satu: Jenis Pembedahan, Jenis Anestesi, Teknik Anestesi, atau Diagnosa Pasca Bedah.');
            return;
        }

        $key = $this->editingKey ?: Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');

        try {
            $this->persistEntry($key, false, 'Simpan draft');
            $this->editingKey = $key; // lanjut edit entri yang sama, tidak buat duplikat
            $this->incrementVersion('modal-laporan-anestesi-rj');
            $this->dispatch('toast', type: 'success', message: 'Draft tersimpan.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menyimpan draft: ' . $e->getMessage());
        }
    }

    /* ===============================
     | TTD PETUGAS = FINALIZE (kunci entri)
     | Stempel nama ahli anestesiologi (user login) + tgl/jam → kunci entri.
     =============================== */
    public function setTtd(): void
    {
        if ($this->isFormLocked || $this->viewOnly) {
            $this->dispatch('toast', type: 'error', message: 'Form read-only.');
            return;
        }

        // Validasi penuh sebelum kunci (ValidationException bubble → Livewire render $errors + toast).
        $this->validateWithToast();

        // Stempel TTD ahli anestesiologi = user login.
        $this->newForm['ttd'] = auth()->user()->myuser_name ?? '';
        $this->newForm['ttdCode'] = auth()->user()->myuser_code ?? '';
        $this->newForm['ttdDate'] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');

        $key = $this->editingKey ?: Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');

        try {
            $this->persistEntry($key, true, 'Kunci (TTD)');
            $this->resetNewForm();
            $this->editingKey = null;
            $this->viewOnly = false;
            $this->incrementVersion('modal-laporan-anestesi-rj');
            $this->dispatch('toast', type: 'success', message: 'Laporan anestesi ditandatangani & terkunci.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal mengunci: ' . $e->getMessage());
        }
    }

    /** Batalkan TTD pada form (saat draft/edit, sebelum finalize benar-benar tersimpan). */
    public function clearTtd(): void
    {
        if ($this->isFormLocked || $this->viewOnly) {
            return;
        }
        $this->newForm['ttd'] = '';
        $this->newForm['ttdCode'] = '';
        $this->newForm['ttdDate'] = '';
    }

    /* ===============================
     | EDIT / LIHAT / BATAL entri
     =============================== */
    // Muat 1 entri ke form atas (dipakai edit draft & lihat entri terkunci).
    private function hydrateFormFromEntry(array $entry, string $key): void
    {
        foreach ($this->newForm as $k => $v) {
            $this->newForm[$k] = $entry[$k] ?? (is_array($v) ? [] : '');
        }
        $this->editingKey = $key;
        $this->resetValidation();
        $this->incrementVersion('modal-laporan-anestesi-rj');
    }

    public function editEntry(string $key): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form read-only.');
            return;
        }
        $entry = collect($this->laporanAnList)->firstWhere('createdAt', $key);
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
        $entry = collect($this->laporanAnList)->firstWhere('createdAt', $key);
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
        $this->editingKey = null;
        $this->viewOnly = false;
        $this->resetValidation();
        $this->incrementVersion('modal-laporan-anestesi-rj');
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

    private function resetNewForm(): void
    {
        foreach ($this->newForm as $k => $v) {
            $this->newForm[$k] = is_array($v) ? [] : '';
        }
        $this->layar = 'daftar';   // mengosongkan formulir = kembali ke daftar
    }

    protected function resetForm(): void
    {
        $this->resetVersion();
        $this->isFormLocked = false;
        $this->laporanAnList = [];
        $this->resetNewForm();
        $this->editingKey = null;
        $this->viewOnly = false;
    }

    /* ===============================
     | CETAK (per-entri)
     =============================== */
    public function cetak(string $createdAt)
    {
        $entry = collect($this->laporanAnList)->firstWhere('createdAt', $createdAt);
        if (!$entry) {
            $this->dispatch('toast', type: 'error', message: 'Data tidak ditemukan.');
            return;
        }
        try {
            $identitasRs = DB::table('rsmst_identitases')->select('int_name', 'int_phone1', 'int_phone2', 'int_fax', 'int_address', 'int_city')->first();
            $pasienData = $this->findDataMasterPasien($this->regNo ?? '');
            $pasien = $pasienData['pasien'] ?? [];
            if (!empty($pasien['tglLahir'])) {
                try {
                    $pasien['thn'] = Carbon::createFromFormat('d/m/Y', $pasien['tglLahir'])->diff(Carbon::now(config('app.timezone')))->format('%y Thn, %m Bln %d Hr');
                } catch (\Throwable) {
                    $pasien['thn'] = '-';
                }
            }
            $ttdPath = null;
            $ttdCode = $entry['ttdCode'] ?? null;
            if ($ttdCode) {
                $path = DB::table('users')->where('myuser_code', $ttdCode)->value('myuser_ttd_image');
                if (!empty($path) && file_exists(TtdUser::pathBerkas($path))) {
                    $ttdPath = TtdUser::pathBerkas($path);
                }
            }
            $data = array_merge($pasien, [
                'dataRi' => $this->findDataRJ($this->rjNo) ?: [], 'form' => $entry, 'identitasRs' => $identitasRs,
                'ttdPath' => $ttdPath, 'tglCetak' => Carbon::now(config('app.timezone'))->translatedFormat('d F Y'),
            ]);
            set_time_limit(300);
            $pdf = Pdf::loadView('pages.components.modul-dokumen.ri.laporan-anestesi-ri.cetak-laporan-anestesi-ri-print', ['data' => $data])->setPaper('A4');
            $this->dispatch('toast', type: 'success', message: 'Berhasil mencetak laporan anestesi.');
            return response()->streamDownload(fn() => print $pdf->output(), 'laporan-anestesi-rj-' . ($pasien['regNo'] ?? $this->rjNo) . '.pdf');
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal cetak: ' . $e->getMessage());
        }
    }

    /* ===============================
     | HAPUS entri (final atau draft)
     =============================== */
    public function hapus(string $createdAt): void
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
            DB::transaction(function () use ($createdAt) {
                $this->lockRJRow($this->rjNo);
                $fresh = $this->findDataRJ($this->rjNo) ?: [];
                $fresh[$this->jsonKey] = collect($fresh[$this->jsonKey] ?? [])
                    ->reject(fn($item) => ($item['createdAt'] ?? '') === $createdAt)
                    ->values()
                    ->toArray();
                $this->updateJsonRJ((int) $this->rjNo, $fresh);
                $this->laporanAnList = $fresh[$this->jsonKey];
                $this->appendAdminLogRJ((int) $this->rjNo, 'Hapus Laporan Anestesi — ' . $createdAt, 'MR');
            });

            if ($this->editingKey === $createdAt) {
                $this->cancelEdit();
            }

            $this->incrementVersion('modal-laporan-anestesi-rj');
            $this->dispatch('toast', type: 'success', message: 'Laporan anestesi berhasil dihapus.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menghapus: ' . $e->getMessage());
        }
    }

    /* ===============================
     | BUKA KUNCI — cabut TTD petugas, entri kembali Draft (Gate dokumen.bukaKunci)
     =============================== */
    public function bukaKunci(string $createdAt): void
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
            DB::transaction(function () use ($createdAt) {
                $this->lockRJRow($this->rjNo);
                $fresh = $this->findDataRJ($this->rjNo) ?: [];
                $list = is_array($fresh[$this->jsonKey] ?? null) ? $fresh[$this->jsonKey] : [];
                $index = collect($list)->search(fn($item) => ($item['createdAt'] ?? '') === $createdAt);
                if ($index === false) {
                    throw new \RuntimeException('Entri tidak ditemukan.');
                }
                $list[$index]['finalized'] = false;
                $list[$index]['ttd'] = '';
                $list[$index]['ttdCode'] = '';
                $list[$index]['ttdDate'] = '';
                $fresh[$this->jsonKey] = array_values($list);
                $this->updateJsonRJ((int) $this->rjNo, $fresh);
                $this->laporanAnList = $fresh[$this->jsonKey];
                $pembukaKunci = auth()->user()->myuser_name ?? '-';
                $this->appendAdminLogRJ((int) $this->rjNo, 'Buka kunci Laporan Anestesi (' . $createdAt . ') oleh ' . $pembukaKunci . ' — TTD petugas dicabut, entri kembali draft', 'MR');
            });

            if ($this->editingKey === $createdAt) {
                $this->cancelEdit();
            }
            $this->incrementVersion('modal-laporan-anestesi-rj');
            $this->dispatch('toast', type: 'success', message: 'Kunci dibuka — TTD petugas dicabut, entri kembali Draft.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal membuka kunci: ' . $e->getMessage());
        }
    }
};
?>

<div>
    @php $laCount = count($laporanAnList ?? []); @endphp

    <x-modul-dokumen.kartu judul="Laporan Anestesi"
        :jumlah="$laCount"
        satuan="laporan"
        :nonaktif="$disabled || !$rjNo">
        <x-slot:deskripsi>Laporan pelaksanaan anestesi (PAB 6 / RM 53): teknik anestesi, monitoring sistem organ selama pembedahan, masalah &amp; keadaan akhir, ditandatangani ahli anestesiologi.</x-slot:deskripsi>
        <div class="overflow-x-auto rounded-2xl border border-hairline dark:border-gray-700">
            <table class="min-w-full text-sm">
                <thead class="bg-surface-card dark:bg-gray-800">
                    <tr class="text-xs font-semibold tracking-wide text-left text-muted uppercase dark:text-gray-300">
                        <th class="px-3 py-2 border-b">Tanggal</th>
                        <th class="px-3 py-2 border-b">Jenis Anestesi</th>
                        <th class="px-3 py-2 border-b">Petugas (TTD)</th>
                        <th class="px-3 py-2 text-center border-b">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse (collect($laporanAnList)->sortByDesc(fn($entri) => strtotime(strtr(($entri['tanggal'] ?? '') ?: ($entri['createdAt'] ?? ''), '/', '-')))->values()->all() as $e)
                        <tr class="border-b border-hairline dark:border-gray-700">
                            <td class="px-3 py-2 font-medium text-ink dark:text-gray-200">{{ $e['tanggal'] ?: ($e['createdAt'] ?? '-') }}</td>
                            <td class="px-3 py-2 text-muted dark:text-gray-400">{{ $e['jenisAnestesi'] ? Str::limit($e['jenisAnestesi'], 40) : '-' }}</td>
                            <td class="px-3 py-2 text-muted dark:text-gray-400">
                                <x-modul-dokumen.status-ttd :nama="$e['ttd'] ?? ''" gaya="polos" />
                            </td>
                            <td class="px-3 py-2 text-center">
                                <x-modul-dokumen.status-entri :final="$this->entryIsFinal($e)" />
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

    <x-modal name="rm-laporan-anestesi-rj-{{ $rjNo ?? 'init' }}" size="full" height="full" focusable>
        <div class="flex flex-col min-h-full" wire:key="{{ $this->renderKey('modal-laporan-anestesi-rj', [$rjNo ?? 'new']) }}">
            <x-modul-dokumen.header judul="Laporan Anestesi"
                ikon="M3 12h4l2 5 4-10 2 5h6">
                PAB 6 / RM 53 — ahli anestesiologi. Tiap entri = 1 laporan; kunci lewat TTD.
            </x-modul-dokumen.header>

            {{-- DISPLAY PASIEN — paling atas, mengikuti pola EMR --}}
            <div class="px-4 pt-2">
                <livewire:pages::transaksi.rj.display-pasien-rj.display-pasien-rj :rjNo="$rjNo" wire:key="la-rj-display-pasien-{{ $rjNo ?? 'init' }}" />
            </div>

            <div class="flex-1 px-4 py-4 overflow-y-auto bg-surface-soft/70 dark:bg-gray-950/20">
                <div class="max-w-full mx-auto space-y-4">

                    @php $formReadOnly = $isFormLocked || $viewOnly; @endphp

                    @if ($isFormLocked)
                        <x-modul-dokumen.banner jenis="terkunci">
                            Mode tampilan saja (read-only) — pasien sudah pulang / EMR terkunci.
                        </x-modul-dokumen.banner>
                    @endif

                    @if ($viewOnly)
                        <x-modul-dokumen.banner jenis="lihat" />
                    @elseif ($editingKey && !$isFormLocked)
                        <x-modul-dokumen.banner jenis="lanjut" />
                    @endif

                    <div class="{{ $this->diForm() ? 'p-6 sm:p-8 bg-canvas border border-hairline shadow-sm rounded-2xl dark:bg-gray-900 dark:border-gray-700' : '' }} space-y-6">

                        {{-- ── FORM ENTRI (1 laporan) ── --}}
                        @if ($this->diForm())
                        <fieldset @disabled($formReadOnly) class="space-y-6">

                            <section class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div>
                                    <x-input-label value="Tanggal / Jam *" class="mb-1" />
                                    <div class="flex items-center gap-2">
                                        <x-text-input wire:model.live="newForm.tanggal" placeholder="dd/mm/yyyy HH:mm:ss" :error="$errors->has('newForm.tanggal')" class="w-full" />
                                        @if (!$formReadOnly) <x-now-button wire:click="setTanggalSekarang" /> @endif
                                    </div>
                                    <x-input-error :messages="$errors->get('newForm.tanggal')" class="mt-1" />
                                </div>
                                <div>
                                    <x-input-label value="Jenis Pembedahan *" class="mb-1" />
                                    <x-text-input wire:model.live="newForm.jenisPembedahan" :error="$errors->has('newForm.jenisPembedahan')" class="w-full" />
                                    <x-input-error :messages="$errors->get('newForm.jenisPembedahan')" class="mt-1" />
                                </div>
                                <div>
                                    <x-input-label value="Diagnosa Pra Bedah" class="mb-1" />
                                    <x-textarea wire:model.live="newForm.diagnosaPraBedah" :error="$errors->has('newForm.diagnosaPraBedah')" rows="2" class="w-full" />
                                </div>
                                <div>
                                    <x-input-label value="Diagnosa Pasca Bedah *" class="mb-1" />
                                    <x-textarea wire:model.live="newForm.diagnosaPascaBedah" :error="$errors->has('newForm.diagnosaPascaBedah')" rows="2" class="w-full" />
                                    <x-input-error :messages="$errors->get('newForm.diagnosaPascaBedah')" class="mt-1" />
                                </div>
                                <div>
                                    <x-input-label value="Jenis Anestesi *" class="mb-1" />
                                    <x-text-input wire:model.live="newForm.jenisAnestesi" :error="$errors->has('newForm.jenisAnestesi')" placeholder="cth: SAB / GA / Sedasi" class="w-full" />
                                    <x-input-error :messages="$errors->get('newForm.jenisAnestesi')" class="mt-1" />
                                </div>
                                <div class="grid grid-cols-2 gap-4">
                                    <div><x-input-label value="Lama Operasi" class="mb-1" /><x-text-input wire:model.live="newForm.lamaOperasi" :error="$errors->has('newForm.lamaOperasi')" class="w-full" /></div>
                                    <div><x-input-label value="Lama Anestesi" class="mb-1" /><x-text-input wire:model.live="newForm.lamaAnestesi" :error="$errors->has('newForm.lamaAnestesi')" class="w-full" /></div>
                                </div>
                            </section>

                            <section class="pt-6 border-t border-hairline dark:border-gray-700">
                                <h3 class="mb-3 text-base font-semibold text-ink dark:text-gray-200">Keadaan Pra Bedah</h3>
                                <div class="grid grid-cols-2 gap-4 md:grid-cols-4 lg:grid-cols-7">
                                    <div><x-input-label value="TB" class="mb-1" /><x-text-input wire:model.live="newForm.tb" :error="$errors->has('newForm.tb')" class="w-full" /></div>
                                    <div><x-input-label value="BB" class="mb-1" /><x-text-input wire:model.live="newForm.bb" :error="$errors->has('newForm.bb')" class="w-full" /></div>
                                    <div><x-input-label value="Gol. Darah" class="mb-1" /><x-text-input wire:model.live="newForm.golDarah" :error="$errors->has('newForm.golDarah')" class="w-full" /></div>
                                    <div><x-input-label value="Tensi" class="mb-1" /><x-text-input wire:model.live="newForm.tensi" :error="$errors->has('newForm.tensi')" class="w-full" /></div>
                                    <div><x-input-label value="Nadi" class="mb-1" /><x-text-input wire:model.live="newForm.nadi" :error="$errors->has('newForm.nadi')" class="w-full" /></div>
                                    <div><x-input-label value="Suhu" class="mb-1" /><x-text-input wire:model.live="newForm.suhu" :error="$errors->has('newForm.suhu')" class="w-full" /></div>
                                    <div><x-input-label value="Hb" class="mb-1" /><x-text-input wire:model.live="newForm.hb" :error="$errors->has('newForm.hb')" class="w-full" /></div>
                                </div>
                            </section>

                            <section class="pt-6 space-y-4 border-t border-hairline dark:border-gray-700">
                                <h3 class="text-base font-semibold text-ink dark:text-gray-200">Teknik Anestesi</h3>
                                <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                                    <div>
                                        <x-input-label value="Jalan Nafas" class="mb-1" />
                                        <x-select-input wire:model.live="newForm.jalanNafas" :error="$errors->has('newForm.jalanNafas')" class="w-full">
                                            <option value="">— pilih —</option>
                                            @foreach ($jalanNafasOptions as $opt) <option value="{{ $opt }}">{{ $opt }}</option> @endforeach
                                        </x-select-input>
                                    </div>
                                    <div>
                                        <x-input-label value="Pernafasan" class="mb-1" />
                                        <x-select-input wire:model.live="newForm.pernafasan" :error="$errors->has('newForm.pernafasan')" class="w-full">
                                            <option value="">— pilih —</option>
                                            @foreach ($pernafasanOptions as $opt) <option value="{{ $opt }}">{{ $opt }}</option> @endforeach
                                        </x-select-input>
                                    </div>
                                    <div><x-input-label value="Posisi" class="mb-1" /><x-text-input wire:model.live="newForm.posisi" :error="$errors->has('newForm.posisi')" class="w-full" /></div>
                                </div>
                                <div>
                                    <x-input-label value="Teknik Anestesi *" class="mb-1" />
                                    <x-textarea wire:model.live="newForm.teknikAnestesi" :error="$errors->has('newForm.teknikAnestesi')" rows="3" class="w-full" />
                                    <x-input-error :messages="$errors->get('newForm.teknikAnestesi')" class="mt-1" />
                                </div>
                                <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                                    <div><x-input-label value="Teknik Khusus" class="mb-1" /><x-text-input wire:model.live="newForm.teknikKhusus" :error="$errors->has('newForm.teknikKhusus')" class="w-full" /></div>
                                    <div><x-input-label value="Infus" class="mb-1" /><x-text-input wire:model.live="newForm.infus" :error="$errors->has('newForm.infus')" class="w-full" /></div>
                                    <div><x-input-label value="Penyulit Selama Pembedahan" class="mb-1" /><x-text-input wire:model.live="newForm.penyulitSelamaPembedahan" :error="$errors->has('newForm.penyulitSelamaPembedahan')" class="w-full" /></div>
                                </div>
                            </section>

                            <section class="pt-6 space-y-4 border-t border-hairline dark:border-gray-700">
                                <h3 class="text-base font-semibold text-ink dark:text-gray-200">Monitoring Sistem Organ</h3>
                                <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                                    <div><x-input-label value="Saraf (GCS)" class="mb-1" /><x-text-input wire:model.live="newForm.saraf" :error="$errors->has('newForm.saraf')" class="w-full" /></div>
                                    <div><x-input-label value="Sirkulasi" class="mb-1" /><x-text-input wire:model.live="newForm.sirkulasi" :error="$errors->has('newForm.sirkulasi')" class="w-full" /></div>
                                    <div><x-input-label value="Perfusi" class="mb-1" /><x-text-input wire:model.live="newForm.perfusi" :error="$errors->has('newForm.perfusi')" class="w-full" /></div>
                                    <div><x-input-label value="Gastrointestinal" class="mb-1" /><x-text-input wire:model.live="newForm.gastrointestinal" :error="$errors->has('newForm.gastrointestinal')" class="w-full" /></div>
                                    <div><x-input-label value="Ginjal" class="mb-1" /><x-text-input wire:model.live="newForm.ginjal" :error="$errors->has('newForm.ginjal')" class="w-full" /></div>
                                    <div><x-input-label value="Metabolik" class="mb-1" /><x-text-input wire:model.live="newForm.metabolik" :error="$errors->has('newForm.metabolik')" class="w-full" /></div>
                                    <div><x-input-label value="Hati" class="mb-1" /><x-text-input wire:model.live="newForm.hati" :error="$errors->has('newForm.hati')" class="w-full" /></div>
                                </div>
                            </section>

                            <section class="pt-6 space-y-4 border-t border-hairline dark:border-gray-700">
                                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                    <div><x-input-label value="Medikasi Pra Bedah" class="mb-1" /><x-text-input wire:model.live="newForm.medikasiPraBedah" :error="$errors->has('newForm.medikasiPraBedah')" class="w-full" /></div>
                                    <div>
                                        <x-input-label value="ASA *" class="mb-1" />
                                        <x-select-input wire:model.live="newForm.asa" :error="$errors->has('newForm.asa')" class="w-full">
                                            <option value="">— pilih —</option>
                                            @foreach ($asaOptions as $opt) <option value="{{ $opt }}">{{ $opt }}</option> @endforeach
                                        </x-select-input>
                                        <x-input-error :messages="$errors->get('newForm.asa')" class="mt-1" />
                                    </div>
                                    <div><x-input-label value="Masalah Anestesi" class="mb-1" /><x-text-input wire:model.live="newForm.masalahAnestesi" :error="$errors->has('newForm.masalahAnestesi')" class="w-full" /></div>
                                    <div><x-input-label value="Masalah Bedah" class="mb-1" /><x-text-input wire:model.live="newForm.masalahBedah" :error="$errors->has('newForm.masalahBedah')" class="w-full" /></div>
                                    <div><x-input-label value="Keadaan Akhir Pembedahan" class="mb-1" /><x-text-input wire:model.live="newForm.keadaanAkhirPembedahan" :error="$errors->has('newForm.keadaanAkhirPembedahan')" class="w-full" /></div>
                                    <div><x-input-label value="Penyulit Pasca Bedah" class="mb-1" /><x-text-input wire:model.live="newForm.penyulitPascaBedah" :error="$errors->has('newForm.penyulitPascaBedah')" class="w-full" /></div>
                                </div>
                            </section>

                            {{-- ══ TTD AHLI ANESTESIOLOGI & KUNCI ══ --}}
                            <x-signature.ttd-petugas :ttd="$newForm['ttd']" :date="$newForm['ttdDate'] ?? ''"
                                :code="$newForm['ttdCode'] ?? ''" :locked="$formReadOnly" sign="setTtd" clear="clearTtd"
                                title="Tanda Tangan Ahli Anestesiologi"
                                nameLabel="Ahli Anestesiologi" dateLabel="Waktu TTD"
                                signLabel="TTD Petugas & Kunci" clearLabel="Batal TTD" />
                            @if (!$formReadOnly)
                                <p class="-mt-2 text-xs text-center text-muted">Menandatangani = mengunci laporan anestesi ini.</p>
                            @endif
                        </fieldset>

                        {{-- ── DAFTAR LAPORAN TERSIMPAN (expandable) ── --}}
                        @endif
                        @unless ($this->diForm())
                        <x-modul-dokumen.tabel-daftar :kolom="['', 'Tanggal', 'Jenis Anestesi', 'Petugas (TTD)', 'Status' => 'text-center', 'Aksi' => 'text-center']">
                                        @forelse (collect($laporanAnList)->sortByDesc(fn($entri) => strtotime(strtr(($entri['tanggal'] ?? '') ?: ($entri['createdAt'] ?? ''), '/', '-')))->values()->all() as $entry)
                                            @php
                                                $isFinal = $this->entryIsFinal($entry);
                                                $rowKey = $entry['createdAt'] ?? '';
                                            @endphp
                                            {{-- Semua baris mulai TERTUTUP: daftar dipakai untuk MEMILIH entri, bukan
                                                 membacanya. Baris teratas yang terbuka sendiri bikin grid langsung panjang. --}}
                                            <tbody x-data="{ open: false }" class="border-b border-hairline dark:border-gray-700">
                                                <tr @click="open = !open"
                                                    class="cursor-pointer hover:bg-surface-soft dark:hover:bg-gray-800 {{ $editingKey && $editingKey === $rowKey ? 'bg-brand-lime/10 dark:bg-brand-lime/5' : '' }}">
                                                    <td class="px-2 py-3 text-center align-middle">
                                                        <svg class="w-4 h-4 mx-auto transition-transform text-muted" :class="{ 'rotate-90': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                                        </svg>
                                                    </td>
                                                    <td class="px-4 py-3 font-semibold align-middle text-ink dark:text-gray-100">
                                                        {{ $entry['tanggal'] ?: ($rowKey ?: '-') }}
                                                    </td>
                                                    <td class="px-4 py-3 align-middle text-muted dark:text-gray-300">
                                                        {{ $entry['jenisAnestesi'] ? Str::limit($entry['jenisAnestesi'], 40) : '-' }}
                                                    </td>
                                                    <td class="px-4 py-3 align-middle text-muted dark:text-gray-300">
                                                        <x-modul-dokumen.status-ttd :nama="$entry['ttd'] ?? ''" />
                                                    </td>
                                                    <td class="px-4 py-3 text-center align-middle">
                                                        <x-modul-dokumen.status-entri :final="$isFinal" />
                                                    </td>
                                                    <td class="px-4 py-3 text-center align-middle whitespace-nowrap" @click.stop>
                                                        <x-modul-dokumen.aksi-entri kunci="{{ $rowKey }}" :final="$isFinal" :terkunci="$isFormLocked"
                                                            judulLihat="Lihat detail (read-only) di form atas"
                                                            judulBukaKunci="Buka Kunci Laporan Anestesi"
                                                            konfirmasiHapus="Yakin hapus laporan ini?" />
                                                    </td>
                                                </tr>

                                                {{-- DETAIL (expand) --}}
                                                <tr x-show="open" x-cloak>
                                                    <td colspan="6" class="px-4 py-4 bg-surface-soft/60 dark:bg-gray-950/30">
                                                        <dl class="grid grid-cols-1 gap-x-8 gap-y-3 md:grid-cols-2">
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Tanggal / Jam</dt>
                                                                <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['tanggal'] ?: '-' }}</dd>
                                                            </div>
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Jenis Pembedahan</dt>
                                                                <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['jenisPembedahan'] ?: '-' }}</dd>
                                                            </div>
                                                            <div class="md:col-span-2">
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Diagnosa Pra Bedah</dt>
                                                                <dd class="mt-0.5 whitespace-pre-line text-ink dark:text-gray-200">{{ $entry['diagnosaPraBedah'] ?: '-' }}</dd>
                                                            </div>
                                                            <div class="md:col-span-2">
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Diagnosa Pasca Bedah</dt>
                                                                <dd class="mt-0.5 whitespace-pre-line text-ink dark:text-gray-200">{{ $entry['diagnosaPascaBedah'] ?: '-' }}</dd>
                                                            </div>
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Jenis Anestesi</dt>
                                                                <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['jenisAnestesi'] ?: '-' }}</dd>
                                                            </div>
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Lama Operasi / Anestesi</dt>
                                                                <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['lamaOperasi'] ?: '-' }} / {{ $entry['lamaAnestesi'] ?: '-' }}</dd>
                                                            </div>
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">TB / BB</dt>
                                                                <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['tb'] ?: '-' }} / {{ $entry['bb'] ?: '-' }}</dd>
                                                            </div>
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Gol. Darah</dt>
                                                                <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['golDarah'] ?: '-' }}</dd>
                                                            </div>
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Tensi / Nadi</dt>
                                                                <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['tensi'] ?: '-' }} / {{ $entry['nadi'] ?: '-' }}</dd>
                                                            </div>
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Suhu / Hb</dt>
                                                                <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['suhu'] ?: '-' }} / {{ $entry['hb'] ?: '-' }}</dd>
                                                            </div>
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Jalan Nafas</dt>
                                                                <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['jalanNafas'] ?: '-' }}</dd>
                                                            </div>
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Pernafasan</dt>
                                                                <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['pernafasan'] ?: '-' }}</dd>
                                                            </div>
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Posisi</dt>
                                                                <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['posisi'] ?: '-' }}</dd>
                                                            </div>
                                                            <div class="md:col-span-2">
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Teknik Anestesi</dt>
                                                                <dd class="mt-0.5 whitespace-pre-line text-ink dark:text-gray-200">{{ $entry['teknikAnestesi'] ?: '-' }}</dd>
                                                            </div>
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Teknik Khusus</dt>
                                                                <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['teknikKhusus'] ?: '-' }}</dd>
                                                            </div>
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Infus</dt>
                                                                <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['infus'] ?: '-' }}</dd>
                                                            </div>
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Penyulit Selama Pembedahan</dt>
                                                                <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['penyulitSelamaPembedahan'] ?: '-' }}</dd>
                                                            </div>
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Saraf (GCS)</dt>
                                                                <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['saraf'] ?: '-' }}</dd>
                                                            </div>
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Sirkulasi</dt>
                                                                <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['sirkulasi'] ?: '-' }}</dd>
                                                            </div>
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Perfusi</dt>
                                                                <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['perfusi'] ?: '-' }}</dd>
                                                            </div>
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Gastrointestinal</dt>
                                                                <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['gastrointestinal'] ?: '-' }}</dd>
                                                            </div>
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Ginjal</dt>
                                                                <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['ginjal'] ?: '-' }}</dd>
                                                            </div>
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Metabolik</dt>
                                                                <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['metabolik'] ?: '-' }}</dd>
                                                            </div>
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Hati</dt>
                                                                <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['hati'] ?: '-' }}</dd>
                                                            </div>
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Medikasi Pra Bedah</dt>
                                                                <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['medikasiPraBedah'] ?: '-' }}</dd>
                                                            </div>
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">ASA</dt>
                                                                <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['asa'] ?: '-' }}</dd>
                                                            </div>
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Masalah Anestesi</dt>
                                                                <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['masalahAnestesi'] ?: '-' }}</dd>
                                                            </div>
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Masalah Bedah</dt>
                                                                <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['masalahBedah'] ?: '-' }}</dd>
                                                            </div>
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Keadaan Akhir Pembedahan</dt>
                                                                <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['keadaanAkhirPembedahan'] ?: '-' }}</dd>
                                                            </div>
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Penyulit Pasca Bedah</dt>
                                                                <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['penyulitPascaBedah'] ?: '-' }}</dd>
                                                            </div>
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Petugas (TTD)</dt>
                                                                <dd class="mt-0.5">
                                                                    <x-modul-dokumen.status-ttd :nama="$entry['ttd'] ?? ''" :waktu="$entry['ttdDate'] ?? '-'" gaya="biasa" />
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

            <x-modul-dokumen.footer :formulir="$this->diForm()" :terkunci="$isFormLocked"
                :lihat="$viewOnly"
                :mengedit="$editingKey">
                Simpan draft dulu, lalu <strong>kunci</strong> lewat tombol <strong>TTD Petugas &amp; Kunci</strong>.
            </x-modul-dokumen.footer>

        </div>
    </x-modal>
</div>
