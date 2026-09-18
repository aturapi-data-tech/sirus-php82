<?php
// resources/views/pages/transaksi/ri/emr-ri/modul-dokumen/form-pindah-antar-ruang-ri/rm-form-pindah-antar-ruang-ri-actions.blade.php

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Http\Traits\Txn\Ri\EmrRITrait;
use App\Http\Traits\Concerns\WithRenderVersioningTrait;
use App\Http\Traits\Concerns\WithValidationToastTrait;

new class extends Component {
    use EmrRITrait, WithRenderVersioningTrait, WithValidationToastTrait;

    public bool $isFormLocked = false;
    public ?string $riHdrNo = null;
    public bool $disabled = false;
    /** IRISAN dokumen: cabang `formPindahAntarRuangRI` + ruangan asal pasien saat ini. */
    public array $daftarPindah = [];
    public string $roomId = '';
    public string $roomDesc = '';

    /** Dokumen dibaca sebagai variabel LOKAL; hanya irisan + skalar yang disimpan. */
    private function serapIrisan(array $data): void
    {
        $this->daftarPindah = $data['formPindahAntarRuangRI'] ?? [];
        $this->roomId = (string) ($data['roomId'] ?? '');
        $this->roomDesc = (string) ($data['roomDesc'] ?? '');
    }

    public array $renderVersions = [];
    protected array $renderAreas = ['modal-form-pindah-ri'];

    /** Track entry yang sedang di-edit (null = entry baru) */
    public ?string $editingTglPindah = null;

    // Layar aktif di modal: 'daftar' (riwayat pindah) atau 'form' (isian baru/edit).
    // Formulir sengaja tidak nongkrong bersama daftarnya — pola baku modul dokumen.
    public string $layar = 'daftar';

    public array $newPindah = [
        'tglPindah' => '',
        'tglTerima' => '',
        'dariRoomId' => '',
        'dariRoomDesc' => '',
        'dariBedNo' => '',
        'keRoomId' => '',
        'keRoomDesc' => '',
        'keBedNo' => '',
        'alasanPindah' => '',
        'kondisiKirim' => [
            'sistolik' => '',
            'diastolik' => '',
            'frekuensiNafas' => '',
            'frekuensiNadi' => '',
            'suhu' => '',
            'spo2' => '',
            'gda' => '',
            'gcs' => '',
            'keadaanPasien' => '',
        ],
        'kondisiTerima' => [
            'sistolik' => '',
            'diastolik' => '',
            'frekuensiNafas' => '',
            'frekuensiNadi' => '',
            'suhu' => '',
            'spo2' => '',
            'gda' => '',
            'gcs' => '',
            'keadaanPasien' => '',
        ],
        'petugasPengirim' => '',
        'petugasPengirimCode' => '',
        'petugasPengirimDate' => '',
        'petugasPenerima' => '',
        'petugasPenerimaCode' => '',
        'petugasPenerimaDate' => '',
    ];

    public array $listPindah = [];

    /* ===============================
     | MOUNT
     =============================== */
    public function mount(?string $riHdrNo = null, bool $disabled = false): void
    {
        $this->riHdrNo = $riHdrNo ?: null;
        $this->disabled = $disabled;
        $this->registerAreas(['modal-form-pindah-ri']);

        if ($this->riHdrNo) {
            $data = $this->findDataRI($this->riHdrNo);
            if ($data) {
                $this->serapIrisan($data);
                $this->listPindah = $data['formPindahAntarRuangRI'] ?? [];
                $this->isFormLocked = $this->checkEmrRIStatus($this->riHdrNo) || $disabled;
            }
        }
    }

    /* ===============================
     | OPEN MODAL
     =============================== */
    public function openModal(): void
    {
        if (!$this->riHdrNo || $this->disabled) {
            return;
        }

        $this->resetNewPindah();
        $this->editingTglPindah = null;
        $this->resetValidation();

        $data = $this->findDataRI($this->riHdrNo);
        if (!$data) {
            $this->dispatch('toast', type: 'error', message: 'Data RI tidak ditemukan.');
            return;
        }

        $this->serapIrisan($data);
        $this->listPindah = $data['formPindahAntarRuangRI'] ?? [];

        // Auto-fill "Dari ruang" dari kamar pasien saat ini
        $this->newPindah['dariRoomId'] = $data['roomId'] ?? '';
        $this->newPindah['dariRoomDesc'] = $data['roomDesc'] ?? '';

        $this->isFormLocked = $this->checkEmrRIStatus($this->riHdrNo) || $this->disabled;
        $this->incrementVersion('modal-form-pindah-ri');
        $this->layar = 'daftar';


        $this->dispatch('open-modal', name: "rm-form-pindah-ri-{$this->riHdrNo}");
    }

    public function closeModal(): void
    {
        $this->dispatch('close-modal', name: "rm-form-pindah-ri-{$this->riHdrNo}");
    }

    /* ===============================
     | CETAK — dispatch ke child component (per-entry)
     =============================== */
    public function cetakPindahRi(string $tglPindah): void
    {
        if (!$this->riHdrNo) {
            return;
        }
        $this->dispatch('cetak-form-pindah-antar-ruang-ri.open', riHdrNo: $this->riHdrNo, tglPindah: $tglPindah);
    }

    /* ===============================
     | LOAD ENTRY UNTUK DI-EDIT/LANJUTKAN
     =============================== */
    public function editPindah(string $tglPindah): void
    {
        if ($this->isFormLocked) {
            return;
        }

        $entry = collect($this->listPindah)->firstWhere('tglPindah', $tglPindah);
        if (!$entry) {
            $this->dispatch('toast', type: 'error', message: 'Entry tidak ditemukan.');
            return;
        }

        if ($this->isEntryLocked($entry)) {
            $this->dispatch('toast', type: 'warning', message: 'Entry sudah selesai (kedua TTD lengkap), tidak dapat diedit.');
            return;
        }

        $this->editingTglPindah = $tglPindah;
        $this->resetValidation();
        $this->newPindah = array_replace_recursive($this->newPindah, $entry);
        $this->incrementVersion('modal-form-pindah-ri');
        $this->layar = 'form';
    }

    public function batalEdit(): void
    {
        $this->editingTglPindah = null;
        $this->resetNewPindah();
        $this->newPindah['dariRoomId'] = $this->roomId;
        $this->newPindah['dariRoomDesc'] = $this->roomDesc;
        $this->resetValidation();
        $this->incrementVersion('modal-form-pindah-ri');
        $this->layar = 'daftar';
    }

    /** Layar formulir sedang tampil? Saat terkunci, formulir tak pernah dirender. */
    public function diForm(): bool
    {
        return !$this->isFormLocked && ($this->editingTglPindah !== null || $this->layar === 'form');
    }

    /** Buka formulir kosong untuk entri baru. */
    public function tambahEntri(): void
    {
        if ($this->isFormLocked || $this->disabled) {
            $this->dispatch('toast', type: 'error', message: 'Form read-only, tidak dapat menambah entri.');
            return;
        }
        $this->batalEdit();      // kosongkan formulir (sekaligus balik ke daftar)…
        $this->layar = 'form';   // …lalu naikkan formulirnya
    }

    /** Tutup formulir, kembali ke daftar entri. Formulir selalu ditinggalkan kosong. */
    public function kembaliKeDaftar(): void
    {
        $this->batalEdit();
    }

    /** Cek apakah entry sudah final (kedua TTD ada) */
    public function isEntryLocked(array $entry): bool
    {
        return !empty($entry['petugasPengirim']) && !empty($entry['petugasPenerima']);
    }

    /* ===============================
     | LOV ROOM TUJUAN — listener
     =============================== */
    /* ===============================
     | VALIDATION
     | Stage 1 (pengirim): tglPindah, ke ruang, alasan wajib
     | Stage 2 (penerima): tambahan tglTerima wajib
     =============================== */
    protected function rules(): array
    {
        $rules = [
            'newPindah.tglPindah' => 'required|date_format:d/m/Y H:i:s',
            // Yang wajib NAMA ruangannya. keRoomId cuma tautan ke master dan sengaja
            // tidak diwajibkan: ruangan yang belum terdaftar boleh diketik apa adanya.
            'newPindah.keRoomDesc' => 'required|string|max:200',
            'newPindah.alasanPindah' => 'required|string|max:500',
        ];

        // Kalau penerima sudah TTD, tglTerima wajib
        if (!empty($this->newPindah['petugasPenerima'])) {
            $rules['newPindah.tglTerima'] = 'required|date_format:d/m/Y H:i:s';
        }

        return $rules;
    }

    protected function messages(): array
    {
        return [
            'required' => ':attribute wajib diisi.',
            'date_format' => ':attribute harus format dd/mm/yyyy HH:ii:ss.',
            'max' => ':attribute maksimal :max karakter.',
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'newPindah.tglPindah' => 'Tanggal pindah',
            'newPindah.tglTerima' => 'Tanggal terima',
            'newPindah.keRoomDesc' => 'Ruang tujuan',
            'newPindah.alasanPindah' => 'Alasan pindah',
        ];
    }

    /* ===============================
     | TANGGAL — set sekarang
     =============================== */
    public function setTglPindahSekarang(): void
    {
        if ($this->isFormLocked) {
            return;
        }
        $this->newPindah['tglPindah'] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
    }

    public function setTglTerimaSekarang(): void
    {
        if ($this->isFormLocked) {
            return;
        }
        $this->newPindah['tglTerima'] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
    }

    /* ===============================
     | TTD PETUGAS PENGIRIM & PENERIMA
     =============================== */
    public function setPetugasPengirim(): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form read-only.');
            return;
        }
        if (!empty($this->newPindah['petugasPengirim'])) {
            $this->dispatch('toast', type: 'warning', message: 'Petugas Pengirim sudah TTD.');
            return;
        }

        // Validasi TTV area KIRIM sebelum TTD (rules; tiru RJ: Nadi, Nafas, Suhu — sistolik/diastolik/spo2 opsional)
        $this->validateWithToast([
            'newPindah.kondisiKirim.frekuensiNadi' => 'required',
            'newPindah.kondisiKirim.frekuensiNafas' => 'required',
            'newPindah.kondisiKirim.suhu' => 'required',
        ], ['required' => ':attribute wajib diisi sebelum TTD Pengirim.'], [
            'newPindah.kondisiKirim.frekuensiNadi' => 'Nadi (saat dikirim)',
            'newPindah.kondisiKirim.frekuensiNafas' => 'Nafas (saat dikirim)',
            'newPindah.kondisiKirim.suhu' => 'Suhu (saat dikirim)',
        ]);

        // Validasi logistik (ruang & alasan) SEBELUM set TTD — agar TTD tak "nyangkut" tanpa tersimpan.
        // (tglPindah di-auto-set di bawah, tak perlu divalidasi di sini)
        $this->validateWithToast([
            'newPindah.keRoomDesc' => 'required',
            'newPindah.alasanPindah' => 'required',
        ], ['required' => ':attribute wajib diisi sebelum TTD Pengirim.'], [
            'newPindah.keRoomDesc' => 'Ke Ruangan',
            'newPindah.alasanPindah' => 'Alasan Pindah',
        ]);

        $this->newPindah['petugasPengirim'] = auth()->user()->myuser_name ?? '';
        $this->newPindah['petugasPengirimCode'] = auth()->user()->myuser_code ?? '';
        $this->newPindah['petugasPengirimDate'] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');

        // Auto-set tglPindah kalau belum diisi
        if (empty($this->newPindah['tglPindah'])) {
            $this->newPindah['tglPindah'] = $this->newPindah['petugasPengirimDate'];
        }

        // Auto-simpan entri saat TTD (TANPA reset form → bisa lanjut TTD Penerima).
        // Lacak entri ini agar TTD Penerima meng-UPDATE, bukan menambah dobel.
        $this->save(false);
        if ($this->editingTglPindah === null && !empty($this->newPindah['tglPindah'])) {
            $this->editingTglPindah = $this->newPindah['tglPindah'];
        }
    }

    public function setPetugasPenerima(): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form read-only.');
            return;
        }
        if (empty($this->newPindah['petugasPengirim'])) {
            $this->dispatch('toast', type: 'error', message: 'Petugas Pengirim harus TTD terlebih dahulu.');
            return;
        }
        if (!empty($this->newPindah['petugasPenerima'])) {
            $this->dispatch('toast', type: 'warning', message: 'Petugas Penerima sudah TTD.');
            return;
        }

        // Validasi TTV area TERIMA sebelum TTD (rules; tiru RJ: Nadi, Nafas, Suhu)
        $this->validateWithToast([
            'newPindah.kondisiTerima.frekuensiNadi' => 'required',
            'newPindah.kondisiTerima.frekuensiNafas' => 'required',
            'newPindah.kondisiTerima.suhu' => 'required',
        ], ['required' => ':attribute wajib diisi sebelum TTD Penerima.'], [
            'newPindah.kondisiTerima.frekuensiNadi' => 'Nadi (saat diterima)',
            'newPindah.kondisiTerima.frekuensiNafas' => 'Nafas (saat diterima)',
            'newPindah.kondisiTerima.suhu' => 'Suhu (saat diterima)',
        ]);

        $this->newPindah['petugasPenerima'] = auth()->user()->myuser_name ?? '';
        $this->newPindah['petugasPenerimaCode'] = auth()->user()->myuser_code ?? '';
        $this->newPindah['petugasPenerimaDate'] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');

        // Auto-set tglTerima
        if (empty($this->newPindah['tglTerima'])) {
            $this->newPindah['tglTerima'] = $this->newPindah['petugasPenerimaDate'];
        }

        // Auto-simpan (UPDATE entri) saat TTD Penerima — entri lengkap → persist + reset form.
        if ($this->editingTglPindah === null && !empty($this->newPindah['tglPindah'])) {
            $this->editingTglPindah = $this->newPindah['tglPindah'];
        }
        $this->save();
    }

    /* ===============================
     | SAVE — tambah baru / update existing
     =============================== */
    public function save(bool $resetAfter = true): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form read-only, tidak dapat menyimpan.');
            return;
        }

        $this->validateWithToast();

        if (empty($this->newPindah['petugasPengirim'])) {
            $this->dispatch('toast', type: 'error', message: 'Petugas Pengirim belum TTD.');
            return;
        }

        try {
            DB::transaction(function () {
                $this->lockRIRow($this->riHdrNo);

                $data = $this->findDataRI($this->riHdrNo);
                if (empty($data)) {
                    throw new \RuntimeException('Data RI tidak ditemukan, simpan dibatalkan.');
                }

                if (!isset($data['formPindahAntarRuangRI']) || !is_array($data['formPindahAntarRuangRI'])) {
                    $data['formPindahAntarRuangRI'] = [];
                }

                if ($this->editingTglPindah !== null) {
                    // UPDATE existing entry
                    $found = false;
                    foreach ($data['formPindahAntarRuangRI'] as $index => $pindah) {
                        if (($pindah['tglPindah'] ?? '') === $this->editingTglPindah) {
                            // Cegah override entry yang sudah locked
                            if ($this->isEntryLocked($pindah)) {
                                throw new \RuntimeException('Entry sudah final, tidak dapat diubah.');
                            }
                            $data['formPindahAntarRuangRI'][$index] = $this->newPindah;
                            $found = true;
                            break;
                        }
                    }
                    if (!$found) {
                        throw new \RuntimeException('Entry yang akan diupdate tidak ditemukan.');
                    }
                } else {
                    // ADD new entry
                    $data['formPindahAntarRuangRI'][] = $this->newPindah;
                }

                $this->updateJsonRI((int) $this->riHdrNo, $data);
                $this->serapIrisan($data);
                $this->listPindah = $data['formPindahAntarRuangRI'];

                $this->appendAdminLogRI((int) $this->riHdrNo, ($this->editingTglPindah === null ? 'Buat' : 'Update') . ' Form Pindah Antar Ruang — entri ' . ($this->newPindah['tglPindah'] ?: '-'), 'MR');
            });

            $this->incrementVersion('modal-form-pindah-ri');

            $isFinal = !empty($this->newPindah['petugasPenerima']);
            $msg = $isFinal ? 'Form Pindah berhasil diselesaikan (kedua TTD lengkap).' : 'Form Pindah disimpan — menunggu TTD Penerima.';
            $this->dispatch('toast', type: 'success', message: $msg);

            // Reset form untuk entri berikutnya — hanya via tombol Simpan manual.
            // Saat auto-save dari TTD Pengirim, $resetAfter=false agar form tetap (bisa lanjut Penerima).
            if ($resetAfter) {
                $this->editingTglPindah = null;
                $this->resetNewPindah();
                $this->newPindah['dariRoomId'] = $this->roomId;
                $this->newPindah['dariRoomDesc'] = $this->roomDesc;
            }
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menyimpan: ' . $e->getMessage());
        }
    }

    /* ===============================
     | HAPUS — hanya entry yang BELUM final
     =============================== */
    public function hapus(string $tglPindah): void
    {
        if (!auth()->user()?->can('dokumen.hapus')) {
            $this->dispatch('toast', type: 'error', message: 'Anda tidak berwenang menghapus entri.');
            return;
        }
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form read-only.');
            return;
        }

        $entry = collect($this->listPindah)->firstWhere('tglPindah', $tglPindah);
        if ($entry && $this->isEntryLocked($entry)) {
            $this->dispatch('toast', type: 'error', message: 'Entry sudah final (kedua TTD), tidak dapat dihapus.');
            return;
        }

        try {
            DB::transaction(function () use ($tglPindah) {
                $this->lockRIRow($this->riHdrNo);

                $data = $this->findDataRI($this->riHdrNo);
                if (empty($data) || !isset($data['formPindahAntarRuangRI'])) {
                    throw new \RuntimeException('Data tidak ditemukan.');
                }

                $data['formPindahAntarRuangRI'] = collect($data['formPindahAntarRuangRI'])
                    ->reject(fn($item) => ($item['tglPindah'] ?? '') === $tglPindah)
                    ->values()
                    ->toArray();

                $this->updateJsonRI((int) $this->riHdrNo, $data);
                $this->serapIrisan($data);
                $this->listPindah = $data['formPindahAntarRuangRI'];

                $this->appendAdminLogRI((int) $this->riHdrNo, 'Hapus Form Pindah Antar Ruang — entri ' . ($tglPindah ?: '-'), 'MR');
            });

            $this->incrementVersion('modal-form-pindah-ri');
            $this->dispatch('toast', type: 'success', message: 'Riwayat pindah berhasil dihapus.');
            if ($this->editingTglPindah === $tglPindah) {
                $this->batalEdit();
            }
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal: ' . $e->getMessage());
        }
    }

    /* ===============================
     | BUKA KUNCI — cabut TTD penerima, entri kembali Transit (Gate dokumen.bukaKunci)
     =============================== */
    public function bukaKunci(string $tglPindah): void
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
            DB::transaction(function () use ($tglPindah) {
                $this->lockRIRow($this->riHdrNo);
                $data = $this->findDataRI($this->riHdrNo);
                $list = is_array($data['formPindahAntarRuangRI'] ?? null) ? $data['formPindahAntarRuangRI'] : [];
                $index = collect($list)->search(fn($item) => ($item['tglPindah'] ?? '') === $tglPindah);
                if ($index === false) {
                    throw new \RuntimeException('Entri tidak ditemukan.');
                }
                $list[$index]['petugasPenerima'] = '';
                $list[$index]['petugasPenerimaCode'] = '';
                $list[$index]['petugasPenerimaDate'] = '';
                $data['formPindahAntarRuangRI'] = array_values($list);
                $this->updateJsonRI((int) $this->riHdrNo, $data);
                $this->serapIrisan($data);
                $this->listPindah = $data['formPindahAntarRuangRI'];
                $pembukaKunci = auth()->user()->myuser_name ?? '-';
                $this->appendAdminLogRI((int) $this->riHdrNo, 'Buka kunci Form Pindah Antar Ruang (' . ($tglPindah ?: '-') . ') oleh ' . $pembukaKunci . ' — TTD penerima dicabut, entri kembali Transit', 'MR');
            });
            $this->incrementVersion('modal-form-pindah-ri');
            $this->dispatch('toast', type: 'success', message: 'Kunci dibuka — TTD penerima dicabut, entri kembali Transit.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal membuka kunci: ' . $e->getMessage());
        }
    }

    /* ===============================
     | RESET HELPERS
     =============================== */
    private function resetNewPindah(): void
    {
        $this->newPindah = [
            'tglPindah' => '',
            'tglTerima' => '',
            'dariRoomId' => $this->roomId,
            'dariRoomDesc' => $this->roomDesc,
            'dariBedNo' => '',
            'keRoomId' => '',
            'keRoomDesc' => '',
            'keBedNo' => '',
            'alasanPindah' => '',
            'kondisiKirim' => [
                'sistolik' => '',
                'diastolik' => '',
                'frekuensiNafas' => '',
                'frekuensiNadi' => '',
                'suhu' => '',
                'spo2' => '',
                'gda' => '',
                'gcs' => '',
                'keadaanPasien' => '',
            ],
            'kondisiTerima' => [
                'sistolik' => '',
                'diastolik' => '',
                'frekuensiNafas' => '',
                'frekuensiNadi' => '',
                'suhu' => '',
                'spo2' => '',
                'gda' => '',
                'gcs' => '',
                'keadaanPasien' => '',
            ],
            'petugasPengirim' => '',
            'petugasPengirimCode' => '',
            'petugasPengirimDate' => '',
            'petugasPenerima' => '',
            'petugasPenerimaCode' => '',
            'petugasPenerimaDate' => '',
        ];
        $this->layar = 'daftar';   // mengosongkan formulir = kembali ke daftar
    }

    protected function resetForm(): void
    {
        $this->resetVersion();
        $this->isFormLocked = false;
        $this->listPindah = [];
        $this->editingTglPindah = null;
        $this->resetNewPindah();
    }
};
?>

<div>
    {{-- ══ SUMMARY CARD (inline di tab) ══ --}}
    @php
        // Field-lock: begitu KEDUA TTD ada, kunci input (tapi save() tetap jalan — pakai $isFormLocked utk guard save).
        $bothSigned = !empty($newPindah['petugasPengirim']) && !empty($newPindah['petugasPenerima']);
        $uiLocked = $isFormLocked || $bothSigned;
        $pindahCount = count($listPindah ?? []);
        $inTransitCount = collect($listPindah ?? [])
            ->filter(fn($pindah) => !empty($pindah['petugasPengirim']) && empty($pindah['petugasPenerima']))
            ->count();
    @endphp

    <x-modul-dokumen.kartu judul="Formulir Pindah Antar Ruang"
        tombol="Buka Form Pindah"
        :nonaktif="$disabled || !$riHdrNo">
        <x-slot:deskripsi>Serah-terima pasien antar ruang. Petugas Pengirim TTD dulu — entry tetap dapat dilanjutkan Petugas Penerima sampai keduanya TTD (terkunci).</x-slot:deskripsi>
        <x-slot:badge>
            @if ($pindahCount > 0)
                <x-badge class="shrink-0 whitespace-nowrap" variant="success">{{ $pindahCount }} riwayat</x-badge>
            @else
                <x-badge class="shrink-0 whitespace-nowrap" variant="warning">Belum ada</x-badge>
            @endif
            @if ($inTransitCount > 0)
                <x-badge class="shrink-0 whitespace-nowrap" variant="warning">{{ $inTransitCount }} dalam transit</x-badge>
            @endif
        </x-slot:badge>
        <x-slot:ringkasan>
            @if ($pindahCount > 0)
                <ul class="space-y-1 text-sm text-muted dark:text-gray-300 list-disc pl-5">
                    @foreach (array_slice($listPindah, -3) as $pindah)
                        <li>
                            <span class="font-medium">{{ $pindah['dariRoomDesc'] ?? '-' }}</span>
                            <span class="mx-1 text-xs text-muted-soft">→</span>
                            <span class="font-medium">{{ $pindah['keRoomDesc'] ?? '-' }}</span>
                            @if (!empty($pindah['tglPindah']))
                                <span class="text-xs text-muted-soft">— {{ $pindah['tglPindah'] }}</span>
                            @endif
                            @if (empty($pindah['petugasPenerima']))
                                <x-badge variant="warning" class="ml-1">Transit</x-badge>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-slot:ringkasan>
    </x-modul-dokumen.kartu>

    {{-- ══ MODAL FORM ══ --}}
    <x-modal name="rm-form-pindah-ri-{{ $riHdrNo ?? 'init' }}" size="full" height="full" focusable>
        <div class="flex flex-col min-h-full"
            wire:key="{{ $this->renderKey('modal-form-pindah-ri', [$riHdrNo ?? 'new']) }}">
            <x-modul-dokumen.header judul="Formulir Pindah Antar Ruang"
                ikon="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"
                jalur="RI" :jumlah="$pindahCount" :readOnly="$isFormLocked">
                Pengirim TTD &rarr; Penerima lanjutkan TTD &rarr; Final (terkunci)
                <x-slot:badge>
                    @if ($inTransitCount > 0)
                    <x-badge class="shrink-0 whitespace-nowrap" variant="warning">{{ $inTransitCount }} transit</x-badge>
                    @endif
                    @if ($editingTglPindah !== null)
                    <x-badge class="shrink-0 whitespace-nowrap" variant="warning">Mode: Lanjutkan Entry</x-badge>
                    @endif
                </x-slot:badge>
            </x-modul-dokumen.header>

            {{-- DISPLAY PASIEN — paling atas, mengikuti pola EMR --}}
            <div class="px-4 pt-2">
                <livewire:pages::transaksi.ri.display-pasien-ri.display-pasien-ri :riHdrNo="$riHdrNo"
                    wire:key="form-pindah-ri-display-pasien-{{ $riHdrNo ?? 'init' }}" />
            </div>

            {{-- BODY --}}
            <div class="flex-1 px-4 py-4 bg-surface-soft/70 dark:bg-gray-950/20">
                <div class="max-w-full mx-auto space-y-4">

                    @if ($this->diForm())
                    {{-- ══ PANDUAN PENGISIAN (collapsible) ══ --}}
                    <div x-data="{ open: false }"
                        class="overflow-hidden border rounded-2xl bg-blue-50 border-blue-200 dark:bg-blue-900/20 dark:border-blue-700">
                        <button type="button" @click="open = !open"
                            class="flex items-center justify-between w-full px-4 py-3 text-sm font-semibold text-blue-900 transition-colors hover:bg-blue-100 dark:text-blue-200 dark:hover:bg-blue-900/30">
                            <span class="flex items-center gap-2">
                                <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                Cara Pengisian Form Pindah
                            </span>
                            <svg class="w-4 h-4 transition-transform" :class="open ? 'rotate-180' : ''"
                                fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>

                        <div x-show="open" x-collapse class="px-4 pb-4 space-y-3 text-sm text-blue-900 dark:text-blue-200">
                            <p>
                                Form ini diisi <strong>dua tahap</strong> — mirip pencatatan oksigen (jam mulai &amp; jam stop):
                                Pengirim isi &amp; TTD dulu, lalu Penerima melanjutkan setelah pasien sampai di
                                ruang tujuan.
                            </p>

                            <ol class="space-y-2 ml-6 list-decimal">
                                <li>
                                    <strong>Petugas Pengirim</strong> (perawat ruang asal): isi
                                    <em>Tanggal Pindah</em>, <em>Ke Ruangan</em>, <em>Alasan</em>,
                                    <em>Kondisi Saat Dikirim</em> (TTV), lalu klik <strong>TTD Pengirim</strong> →
                                    <strong>Simpan</strong>.
                                    <div class="text-xs text-blue-700 dark:text-blue-300 mt-0.5">
                                        Entry masuk daftar bawah dengan status
                                        <span class="inline-flex items-center px-1.5 py-0.5 text-[10px] font-medium bg-amber-100 text-amber-800 rounded">
                                            Transit
                                        </span>
                                        — boleh ditutup; data tersimpan.
                                    </div>
                                </li>
                                <li>
                                    <strong>Petugas Penerima</strong> (perawat ruang tujuan): buka modal ini &amp;
                                    klik <strong>Lanjutkan Pengisian</strong> pada entri Transit → form ter-load dengan
                                    data pengirim.
                                </li>
                                <li>
                                    Isi <em>Kondisi Saat Diterima</em> (TTV), klik <strong>TTD Penerima</strong>,
                                    lalu <strong>Update Entry</strong>.
                                </li>
                                <li>
                                    Setelah <strong>kedua TTD lengkap</strong>, status berubah jadi
                                    <span class="inline-flex items-center px-1.5 py-0.5 text-[10px] font-medium bg-emerald-100 text-emerald-800 rounded">
                                        Selesai
                                    </span>
                                    dan entry <strong>terkunci permanen</strong> (tidak bisa diedit / dihapus).
                                </li>
                            </ol>

                            <p class="text-xs text-blue-700 dark:text-blue-300">
                                Catatan: tanggal/jam pindah &amp; terima auto-isi saat TTD jika kosong. Kalau pengirim
                                belum TTD, section &amp; tombol Penerima dikunci agar urutan tetap benar.
                            </p>
                        </div>
                    </div>

                    @if ($editingTglPindah !== null)
                        <div
                            class="flex items-center justify-between gap-3 px-4 py-3 text-sm border rounded-xl bg-amber-50 border-amber-200 text-amber-800 dark:bg-amber-900/20 dark:border-amber-700 dark:text-amber-200">
                            <div class="flex items-start gap-2">
                                <svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M13 10V3L4 14h7v7l9-11h-7z" />
                                </svg>
                                <div>
                                    <p class="font-semibold">Melanjutkan entry transit</p>
                                    <p class="mt-0.5">
                                        Entry tgl <strong>{{ $editingTglPindah }}</strong> — silakan TTD sebagai
                                        Petugas Penerima atau revisi data sebelum simpan akhir.
                                    </p>
                                </div>
                            </div>
                            <x-secondary-button type="button" wire:click="batalEdit" class="shrink-0">
                                Batal
                            </x-secondary-button>
                        </div>
                    @endif

                    <div
                        class="p-6 space-y-6 bg-canvas border border-hairline shadow-sm sm:p-8 rounded-2xl dark:bg-gray-900 dark:border-gray-700">

                        @if ($isFormLocked)
                            <x-modul-dokumen.banner jenis="terkunci" />
                        @endif

                        {{-- ══ ASAL & TUJUAN ══ --}}
                        <section class="space-y-4">
                            <h3 class="text-base font-semibold text-ink dark:text-gray-200">
                                Asal &amp; Tujuan
                            </h3>

                            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div>
                                    <x-input-label value="Tanggal Pindah (kirim) *" class="mb-1" />
                                    <div class="flex gap-2">
                                        <x-text-input wire:model.live="newPindah.tglPindah" :error="$errors->has('newPindah.tglPindah')"
                                            placeholder="dd/mm/yyyy hh:ii:ss" :disabled="$uiLocked" class="flex-1" />
                                        @if (!$uiLocked)
                                            <x-now-button wire:click="setTglPindahSekarang" />
                                        @endif
                                    </div>
                                    <x-input-error :messages="$errors->get('newPindah.tglPindah')" class="mt-1" />
                                </div>

                                <div>
                                    <x-input-label value="Tanggal Diterima"
                                        class="mb-1 {{ empty($newPindah['petugasPengirim']) ? 'text-muted-soft' : '' }}" />
                                    <div class="flex gap-2">
                                        <x-text-input wire:model.live="newPindah.tglTerima" :error="$errors->has('newPindah.tglTerima')"
                                            placeholder="dd/mm/yyyy hh:ii:ss"
                                            :disabled="$uiLocked || empty($newPindah['petugasPengirim'])"
                                            class="flex-1" />
                                        @if (!$uiLocked && !empty($newPindah['petugasPengirim']))
                                            <x-now-button wire:click="setTglTerimaSekarang" />
                                        @endif
                                    </div>
                                    <x-input-error :messages="$errors->get('newPindah.tglTerima')" class="mt-1" />
                                </div>
                            </div>

                            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div>
                                    <x-input-label value="Dari Ruangan" class="mb-1" />
                                    @if (!$uiLocked)
                                        {{-- Terisi otomatis dari ruangan pasien saat ini, TAPI tetap bisa diubah:
                                             pasien bisa saja sedang dititipkan di ruangan lain, atau data kamarnya
                                             belum sempat diperbarui saat form ini diisi. --}}
                                        <x-ruangan-combobox wire-model="newPindah.dariRoomId"
                                            wire-model-nama="newPindah.dariRoomDesc"
                                            enter-action="$wire.save()"
                                            :error="$errors->has('newPindah.dariRoomDesc')"
                                            placeholder="Ketik nama ruangan asal…" />
                                    @else
                                        <div
                                            class="px-3 py-2 text-sm border border-hairline bg-surface-soft rounded-md dark:bg-gray-800 dark:border-gray-700">
                                            <span class="font-semibold text-ink dark:text-gray-200">
                                                {{ $newPindah['dariRoomDesc'] ?: '-' }}
                                            </span>
                                        </div>
                                    @endif
                                    <x-input-error :messages="$errors->get('newPindah.dariRoomDesc')" class="mt-1" />
                                </div>

                                <div>
                                    <x-input-label value="Ke Ruangan *" class="mb-1" />
                                    @if (!$uiLocked)
                                        {{-- Ruangan asal disembunyikan: pindah ke ruangan yang sama tak punya arti. --}}
                                        <x-ruangan-combobox wire-model="newPindah.keRoomId"
                                            wire-model-nama="newPindah.keRoomDesc"
                                            enter-action="$wire.save()"
                                            :kecuali="$newPindah['dariRoomId'] ?? null"
                                            :error="$errors->has('newPindah.keRoomDesc')"
                                            placeholder="Ketik nama ruangan tujuan…" />
                                    @elseif (!empty($newPindah['keRoomDesc']))
                                        <div
                                            class="px-3 py-2 text-sm border border-hairline bg-surface-soft rounded-md dark:bg-gray-800 dark:border-gray-700">
                                            <span class="font-semibold text-ink dark:text-gray-200">
                                                {{ $newPindah['keRoomDesc'] }}
                                            </span>
                                            @if (!empty($newPindah['keBedNo']))
                                                <span class="text-muted">/ Bed {{ $newPindah['keBedNo'] }}</span>
                                            @endif
                                        </div>
                                    @else
                                        <p class="text-sm italic text-muted-soft">Belum dipilih.</p>
                                    @endif
                                    <x-input-error :messages="$errors->get('newPindah.keRoomDesc')" class="mt-1" />
                                </div>
                            </div>

                            <div>
                                <x-input-label value="Alasan Pindah *" class="mb-1" />
                                <x-textarea wire:model.live="newPindah.alasanPindah" :error="$errors->has('newPindah.alasanPindah')" rows="2"
                                    placeholder="Mis. perubahan kelas, kebutuhan ruang isolasi, permintaan keluarga..."
                                    :disabled="$uiLocked" />
                                <x-input-error :messages="$errors->get('newPindah.alasanPindah')" class="mt-1" />
                            </div>
                        </section>

                        {{-- ══ KONDISI SAAT KIRIM (kiri) & TERIMA (kanan) ══ --}}
                        <section class="pt-6 border-t border-hairline dark:border-gray-700">
                            <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">

                            {{-- ── Kiri: Kondisi Saat Dikirim (Pengirim) ── --}}
                            <div class="space-y-4 lg:pr-6 lg:border-r lg:border-hairline dark:lg:border-gray-700">
                            <div class="flex items-center justify-between gap-2 flex-wrap">
                                <h3 class="text-base font-semibold text-ink dark:text-gray-200">
                                    Kondisi Saat Dikirim
                                </h3>
                                <span class="text-xs text-muted">Diisi Petugas Pengirim</span>
                            </div>

                            <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
                                <div>
                                    <x-input-label value="TD Sistolik" class="mb-1" />
                                    <x-text-input wire:model.live="newPindah.kondisiKirim.sistolik" :error="$errors->has('newPindah.kondisiKirim.sistolik')" type="number"
                                        placeholder="mmHg" :disabled="$uiLocked" class="w-full" />
                                </div>
                                <div>
                                    <x-input-label value="TD Diastolik" class="mb-1" />
                                    <x-text-input wire:model.live="newPindah.kondisiKirim.diastolik" :error="$errors->has('newPindah.kondisiKirim.diastolik')" type="number"
                                        placeholder="mmHg" :disabled="$uiLocked" class="w-full" />
                                </div>
                                <div>
                                    <x-input-label value="Nadi" class="mb-1" />
                                    <x-text-input wire:model.live="newPindah.kondisiKirim.frekuensiNadi" :error="$errors->has('newPindah.kondisiKirim.frekuensiNadi')"
                                        type="number" placeholder="x/menit" :disabled="$uiLocked"
                                        class="w-full" />
                                </div>
                                <div>
                                    <x-input-label value="Nafas (RR)" class="mb-1" />
                                    <x-text-input wire:model.live="newPindah.kondisiKirim.frekuensiNafas" :error="$errors->has('newPindah.kondisiKirim.frekuensiNafas')"
                                        type="number" placeholder="x/menit" :disabled="$uiLocked"
                                        class="w-full" />
                                </div>
                                <div>
                                    <x-input-label value="Suhu" class="mb-1" />
                                    <x-text-input wire:model.live="newPindah.kondisiKirim.suhu" :error="$errors->has('newPindah.kondisiKirim.suhu')" type="number"
                                        step="0.1" placeholder="°C" :disabled="$uiLocked" class="w-full" />
                                </div>
                                <div>
                                    <x-input-label value="SpO2" class="mb-1" />
                                    <x-text-input wire:model.live="newPindah.kondisiKirim.spo2" :error="$errors->has('newPindah.kondisiKirim.spo2')" type="number"
                                        placeholder="%" :disabled="$uiLocked" class="w-full" />
                                </div>
                                <div>
                                    <x-input-label value="GDA" class="mb-1" />
                                    <x-text-input wire:model.live="newPindah.kondisiKirim.gda" :error="$errors->has('newPindah.kondisiKirim.gda')" type="number"
                                        placeholder="mg/dL" :disabled="$uiLocked" class="w-full" />
                                </div>
                                <div>
                                    <x-input-label value="GCS" class="mb-1" />
                                    <x-text-input wire:model.live="newPindah.kondisiKirim.gcs" :error="$errors->has('newPindah.kondisiKirim.gcs')"
                                        placeholder="E_M_V_" :disabled="$uiLocked" class="w-full" />
                                </div>
                            </div>

                            <div>
                                <x-input-label value="Keadaan Umum (saat dikirim)" class="mb-1" />
                                <x-textarea wire:model.live="newPindah.kondisiKirim.keadaanPasien" :error="$errors->has('newPindah.kondisiKirim.keadaanPasien')" rows="2"
                                    placeholder="Mis. sadar, lemah, terpasang infus RL..." :disabled="$uiLocked" />
                            </div>
                            </div>
                            {{-- ── end Kiri ── --}}

                            {{-- ── Kanan: Kondisi Saat Diterima (Penerima) ── --}}
                            @php $disableTerima = $uiLocked || empty($newPindah['petugasPengirim']); @endphp
                            <div class="space-y-4">
                            <div class="flex items-center justify-between gap-2 flex-wrap">
                                <h3
                                    class="text-base font-semibold {{ $disableTerima ? 'text-muted-soft' : 'text-ink dark:text-gray-200' }}">
                                    Kondisi Saat Diterima
                                </h3>
                                <span class="text-xs text-muted">
                                    @if ($disableTerima)
                                        Menunggu TTD Pengirim
                                    @else
                                        Diisi Petugas Penerima
                                    @endif
                                </span>
                            </div>

                            <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
                                <div>
                                    <x-input-label value="TD Sistolik" class="mb-1" />
                                    <x-text-input wire:model.live="newPindah.kondisiTerima.sistolik" :error="$errors->has('newPindah.kondisiTerima.sistolik')" type="number"
                                        placeholder="mmHg" :disabled="$disableTerima" class="w-full" />
                                </div>
                                <div>
                                    <x-input-label value="TD Diastolik" class="mb-1" />
                                    <x-text-input wire:model.live="newPindah.kondisiTerima.diastolik" :error="$errors->has('newPindah.kondisiTerima.diastolik')" type="number"
                                        placeholder="mmHg" :disabled="$disableTerima" class="w-full" />
                                </div>
                                <div>
                                    <x-input-label value="Nadi" class="mb-1" />
                                    <x-text-input wire:model.live="newPindah.kondisiTerima.frekuensiNadi" :error="$errors->has('newPindah.kondisiTerima.frekuensiNadi')"
                                        type="number" placeholder="x/menit" :disabled="$disableTerima"
                                        class="w-full" />
                                </div>
                                <div>
                                    <x-input-label value="Nafas (RR)" class="mb-1" />
                                    <x-text-input wire:model.live="newPindah.kondisiTerima.frekuensiNafas" :error="$errors->has('newPindah.kondisiTerima.frekuensiNafas')"
                                        type="number" placeholder="x/menit" :disabled="$disableTerima"
                                        class="w-full" />
                                </div>
                                <div>
                                    <x-input-label value="Suhu" class="mb-1" />
                                    <x-text-input wire:model.live="newPindah.kondisiTerima.suhu" :error="$errors->has('newPindah.kondisiTerima.suhu')" type="number"
                                        step="0.1" placeholder="°C" :disabled="$disableTerima" class="w-full" />
                                </div>
                                <div>
                                    <x-input-label value="SpO2" class="mb-1" />
                                    <x-text-input wire:model.live="newPindah.kondisiTerima.spo2" :error="$errors->has('newPindah.kondisiTerima.spo2')" type="number"
                                        placeholder="%" :disabled="$disableTerima" class="w-full" />
                                </div>
                                <div>
                                    <x-input-label value="GDA" class="mb-1" />
                                    <x-text-input wire:model.live="newPindah.kondisiTerima.gda" :error="$errors->has('newPindah.kondisiTerima.gda')" type="number"
                                        placeholder="mg/dL" :disabled="$disableTerima" class="w-full" />
                                </div>
                                <div>
                                    <x-input-label value="GCS" class="mb-1" />
                                    <x-text-input wire:model.live="newPindah.kondisiTerima.gcs" :error="$errors->has('newPindah.kondisiTerima.gcs')"
                                        placeholder="E_M_V_" :disabled="$disableTerima" class="w-full" />
                                </div>
                            </div>

                            <div>
                                <x-input-label value="Keadaan Umum (saat diterima)" class="mb-1" />
                                <x-textarea wire:model.live="newPindah.kondisiTerima.keadaanPasien" :error="$errors->has('newPindah.kondisiTerima.keadaanPasien')" rows="2"
                                    placeholder="Diisi setelah pasien diterima di ruang tujuan..."
                                    :disabled="$disableTerima" />
                            </div>
                            </div>
                            {{-- ── end Kanan ── --}}

                            </div>
                        </section>

                        {{-- ══ TANDA TANGAN ══ --}}
                        <section class="pt-6 space-y-4 border-t border-hairline dark:border-gray-700">
                            <h3 class="text-base font-semibold text-ink dark:text-gray-200">
                                Tanda Tangan
                            </h3>

                            <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                                {{-- Pengirim --}}
                                <x-signature.ttd-petugas :framed="false" :allowClear="false"
                                    :ttd="$newPindah['petugasPengirim']" :date="$newPindah['petugasPengirimDate'] ?? ''"
                                    :code="$newPindah['petugasPengirimCode'] ?? ''" :locked="$isFormLocked"
                                    sign="setPetugasPengirim" label="Petugas Pengirim" signLabel="TTD Pengirim" />

                                {{-- Penerima --}}
                                <x-signature.ttd-petugas :framed="false" :allowClear="false"
                                    :ttd="$newPindah['petugasPenerima']" :date="$newPindah['petugasPenerimaDate'] ?? ''"
                                    :code="$newPindah['petugasPenerimaCode'] ?? ''"
                                    :locked="$isFormLocked || empty($newPindah['petugasPengirim'])"
                                    sign="setPetugasPenerima" label="Petugas Penerima" signLabel="TTD Penerima"
                                    emptyText="Menunggu TTD Pengirim." />
                            </div>
                        </section>

                    </div>

                    @endif
                    {{-- ══ DAFTAR RIWAYAT PINDAH ══ --}}
                    @unless ($this->diForm())
                        <x-modul-dokumen.tabel-daftar :kolom="['', 'Tgl Kirim', 'Dari → Ke', 'Pengirim (TTD)', 'Penerima (TTD)', 'Status' => 'text-center', 'Aksi' => 'text-center w-64']">
                                @forelse (collect($listPindah)->sortByDesc(fn($entri) => strtotime(strtr($entri['tglPindah'] ?? '', '/', '-')))->values()->all() as $pindah)
                                    @php
                                        $rowLocked = !empty($pindah['petugasPengirim']) && !empty($pindah['petugasPenerima']);
                                        $kunciPindah = $pindah['tglPindah'] ?? '';
                                        // Ringkasan kondisi (TTV + GCS + keadaan) satu baris; bagian kosong dilewati.
                                        $ringkasKondisi = function (array $kondisi): string {
                                            $tekananDarah = trim(($kondisi['sistolik'] ?? '') . '/' . ($kondisi['diastolik'] ?? ''), '/');
                                            return collect([
                                                $tekananDarah !== '' ? "TD {$tekananDarah} mmHg" : null,
                                                filled($kondisi['frekuensiNadi'] ?? null) ? "Nadi {$kondisi['frekuensiNadi']}x/mnt" : null,
                                                filled($kondisi['frekuensiNafas'] ?? null) ? "RR {$kondisi['frekuensiNafas']}x/mnt" : null,
                                                filled($kondisi['suhu'] ?? null) ? "Suhu {$kondisi['suhu']} C" : null,
                                                filled($kondisi['spo2'] ?? null) ? "SpO2 {$kondisi['spo2']}%" : null,
                                                filled($kondisi['gda'] ?? null) ? "GDA {$kondisi['gda']}" : null,
                                                filled($kondisi['gcs'] ?? null) ? "GCS {$kondisi['gcs']}" : null,
                                                filled($kondisi['keadaanPasien'] ?? null) ? $kondisi['keadaanPasien'] : null,
                                            ])->filter()->implode(' · ');
                                        };
                                        $kondisiKirimTeks = $ringkasKondisi((array) ($pindah['kondisiKirim'] ?? []));
                                        $kondisiTerimaTeks = $ringkasKondisi((array) ($pindah['kondisiTerima'] ?? []));
                                    @endphp

                                    <tbody wire:key="pindah-{{ $kunciPindah ?: $loop->index }}" x-data="{ open: false }"
                                        class="border-b border-hairline dark:border-gray-700">
                                        <tr @click="open = !open"
                                            class="cursor-pointer align-top hover:bg-surface-soft dark:hover:bg-gray-800/60">
                                            <td class="px-2 py-3 text-center align-middle">
                                                <svg class="w-4 h-4 mx-auto text-muted transition-transform" :class="{ 'rotate-90': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                                </svg>
                                            </td>
                                            <td class="px-4 py-3 font-mono text-muted whitespace-nowrap align-middle dark:text-gray-300">{{ $kunciPindah ?: '-' }}</td>
                                            <td class="px-4 py-3 font-medium text-ink align-middle dark:text-white">
                                                {{ $pindah['dariRoomDesc'] ?? '-' }}
                                                <span class="text-muted-soft">→</span>
                                                {{ $pindah['keRoomDesc'] ?? '-' }}
                                            </td>
                                            <td class="px-4 py-3 align-middle text-muted dark:text-gray-300">
                                                <x-modul-dokumen.status-ttd :nama="$pindah['petugasPengirim'] ?? ''" :waktu="$pindah['petugasPengirimDate'] ?? null" />
                                            </td>
                                            <td class="px-4 py-3 align-middle text-muted dark:text-gray-300">
                                                <x-modul-dokumen.status-ttd :nama="$pindah['petugasPenerima'] ?? ''" :waktu="$pindah['petugasPenerimaDate'] ?? null" />
                                            </td>
                                            <td class="px-4 py-3 text-center align-middle">
                                                @if ($rowLocked)
                                                    <x-badge variant="info">Selesai</x-badge>
                                                @else
                                                    <x-badge variant="warning">Transit</x-badge>
                                                @endif
                                            </td>
                                            <td class="px-4 py-3 text-center align-middle whitespace-nowrap" @click.stop>
                                                <x-modul-dokumen.aksi-entri kunci="{{ $kunciPindah }}" :final="$rowLocked" :terkunci="$isFormLocked"
                                                    lanjut="editPindah"
                                                    lihat=""
                                                    cetak="cetakPindahRi"
                                                    :hapusHanyaDraft="true"
                                                    judulBukaKunci="Buka Kunci Form Pindah Antar Ruang"
                                                    pesanBukaKunci="TTD petugas penerima akan dicabut & entri kembali Transit untuk dikoreksi. Lanjutkan?"
                                                    konfirmasiHapus="Yakin hapus catatan pindah ini?" />
                                            </td>
                                        </tr>

                                        {{-- DETAIL (expand) --}}
                                        <tr x-show="open" x-cloak>
                                            <td colspan="7" class="px-4 py-4 bg-surface-soft/60 dark:bg-gray-950/30">
                                                <dl class="grid grid-cols-1 gap-x-8 gap-y-3 md:grid-cols-2">
                                                    <div class="md:col-span-2">
                                                        <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Alasan Pindah</dt>
                                                        <dd class="mt-0.5 text-ink dark:text-gray-200 whitespace-pre-line">{{ $pindah['alasanPindah'] ?? '-' }}</dd>
                                                    </div>
                                                    <div>
                                                        <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Dari Ruang / Bed</dt>
                                                        <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $pindah['dariRoomDesc'] ?? '-' }}@if (!empty($pindah['dariBedNo'])) <span class="text-muted">· Bed {{ $pindah['dariBedNo'] }}</span>@endif</dd>
                                                    </div>
                                                    <div>
                                                        <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Ke Ruang / Bed</dt>
                                                        <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $pindah['keRoomDesc'] ?? '-' }}@if (!empty($pindah['keBedNo'])) <span class="text-muted">· Bed {{ $pindah['keBedNo'] }}</span>@endif</dd>
                                                    </div>
                                                    <div>
                                                        <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Kondisi Saat Dikirim</dt>
                                                        <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $kondisiKirimTeks ?: '-' }}</dd>
                                                    </div>
                                                    <div>
                                                        <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Kondisi Saat Diterima</dt>
                                                        <dd class="mt-0.5 text-ink dark:text-gray-200">
                                                            {{ $kondisiTerimaTeks ?: '-' }}
                                                            @if (!empty($pindah['tglTerima']))
                                                                <span class="text-muted">({{ $pindah['tglTerima'] }})</span>
                                                            @endif
                                                        </dd>
                                                    </div>
                                                </dl>
                                            </td>
                                        </tr>
                                    </tbody>
                                @empty
                                    <x-modul-dokumen.baris-kosong :kolom="7" />
                                @endforelse
                        </x-modul-dokumen.tabel-daftar>
                    @endunless
                </div>
            </div>

            {{-- FOOTER --}}
            <x-modul-dokumen.footer :formulir="$this->diForm()" :terkunci="$isFormLocked"
                simpan="save"
                selesaiLihat=""
                :bisaSimpan="$riHdrNo && !$isFormLocked"
                :labelSimpan="$editingTglPindah !== null ? 'Update Entry Pindah' : 'Simpan Pindah Pasien'" />

        </div>
    </x-modal>

    {{-- Cetak component — dengerin event cetak-form-pindah-antar-ruang-ri.open --}}
    <livewire:pages::components.modul-dokumen.ri.form-pindah-antar-ruang-ri.cetak-form-pindah-antar-ruang-ri
        wire:key="cetak-form-pindah-ri-{{ $riHdrNo ?? 'init' }}" />
</div>
