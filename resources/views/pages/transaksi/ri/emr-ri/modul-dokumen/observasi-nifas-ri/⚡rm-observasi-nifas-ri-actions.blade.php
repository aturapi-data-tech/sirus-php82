<?php
// resources/views/pages/transaksi/ri/emr-ri/modul-dokumen/observasi-nifas-ri/rm-observasi-nifas-ri-actions.blade.php
// Dokumen VK/Kebidanan — Observasi Nifas (lembar pemantauan masa nifas / post-partum per titik-waktu).
// Pola: multi-entri append-only (Draft + Lanjutkan Pengisian + TTD-Kunci + Lihat read-only + tabel expandable),
// disimpan ke datadaftarri_json. Tiap entri = 1 LEMBAR berisi banyak baris titik-waktu; cetak per-lembar.
// Kunci entri stabil = createdAt. TTD = stempel nama user login (ttdSaya = FINALIZE/kunci), tanpa TTD gambar.
// [akr] = tambahan akreditasi (PP/PAP — Maternal Early Warning Score, ASI Eksklusif, Rawat Gabung).

use Livewire\Component;
use App\Http\Traits\Txn\Ri\EmrRITrait;
use App\Http\Traits\Master\MasterPasien\MasterPasienTrait;
use App\Http\Traits\Concerns\WithRenderVersioningTrait;
use App\Http\Traits\Concerns\WithValidationToastTrait;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;

new class extends Component {
    use EmrRITrait, MasterPasienTrait, WithRenderVersioningTrait, WithValidationToastTrait;

    public bool $isFormLocked = false;
    public ?string $riHdrNo = null;
    public ?string $regNo = null;
    public bool $disabled = false;
    public array $dataDaftarRi = [];

    public array $renderVersions = [];
    protected array $renderAreas = ['modal-observasi-nifas-ri'];

    /** Key penyimpanan di datadaftarri_json */
    private string $jsonKey = 'observasiNifasRI';

    // SATU entri = SATU lembar observasi berisi BANYAK baris titik-waktu (rows),
    // pola tabel entri ala "Obat Pre Medikasi" di Pra Induksi. Dulu 1 titik-waktu =
    // 1 entri dokumen tersendiri; itu memaksa TTD & kunci berulang padahal cetaknya
    // memang satu lembar berisi semua titik-waktu.
    public array $newForm = [
        'rows'     => [],   // baris titik-waktu (lihat barisKosong())
        'ttd'      => '',   // nama penanda-tangan (myuser_name)
        'ttdDate'  => '',   // tgl/jam TTD (d/m/Y H:i:s)
        'ttdCode'  => '',   // myuser_code penanda-tangan
    ];

    // Field penyusun baris baru (di atas tabel) — dikosongkan lagi tiap kali Tambah.
    public string $barisTglJam = '';
    public string $barisSistolik = '';
    public string $barisDiastolik = '';
    public string $barisNadi = '';
    public string $barisRr = '';
    public string $barisSuhu = '';
    public string $barisEwsScore = '';
    public string $barisTfu = '';
    public string $barisKontraksiUterus = '';
    public string $barisLochiaJenis = '';
    public string $barisLochiaJumlah = '';
    public string $barisPerdarahanCc = '';
    public string $barisLukaJalanLahir = '';
    public string $barisBak = '';
    public string $barisBab = '';
    public string $barisLaktasi = '';
    public string $barisAsiEksklusif = '';
    public string $barisRawatGabung = '';
    public string $barisMobilisasi = '';
    public string $barisKeluhan = '';
    public string $barisAsuhanTindakan = '';

    public array $entriList = [];

    // Kunci entri yang sedang diedit (createdAt = kunci stabil, di-set saat entri pertama dibuat).
    // null = sedang membuat entri baru.
    public ?string $editingKey = null;

    // Layar aktif di modal: 'daftar' (grid entri) atau 'form' (tambah/edit/lihat).
    // Formulir sengaja tidak nongkrong bersama daftarnya: dulu ia ikut tampil terus lalu
    // dikosongkan diam-diam sesudah tersimpan, dan petugas yang mengira itu masih formulir
    // yang tadi diisi mengetik ulang — tersimpan sebagai draft baru.
    public string $layar = 'daftar';

    // true = entri terkunci sedang ditampilkan di form dalam mode read-only (lihat saja, tak bisa edit).
    public bool $viewOnly = false;

    protected function rules(): array
    {
        return [];
    }

    /* ===============================
     | MOUNT
     =============================== */
    public function mount(?string $riHdrNo = null, bool $disabled = false): void
    {
        $this->riHdrNo = $riHdrNo ?: null;
        $this->disabled = $disabled;
        $this->registerAreas(['modal-observasi-nifas-ri']);

        if ($this->riHdrNo) {
            $data = $this->findDataRI($this->riHdrNo);
            if ($data) {
                $this->dataDaftarRi = $data;
                $this->regNo = $data['regNo'] ?? null;
                $this->entriList = $data[$this->jsonKey] ?? [];
                $this->isFormLocked = $this->checkEmrRIStatus($this->riHdrNo) || $disabled;
            }
        }
    }

    /* ===============================
     | OPEN / CLOSE MODAL
     =============================== */
    public function openModal(): void
    {
        if (!$this->riHdrNo || $this->disabled) {
            return;
        }

        $this->resetNewForm();
        $this->editingKey = null;
        $this->viewOnly = false;
        $this->resetValidation();

        $data = $this->findDataRI($this->riHdrNo);
        if (!$data) {
            $this->dispatch('toast', type: 'error', message: 'Data RI tidak ditemukan.');
            return;
        }

        $this->dataDaftarRi = $data;
        $this->regNo = $data['regNo'] ?? null;
        if (!isset($this->dataDaftarRi[$this->jsonKey]) || !is_array($this->dataDaftarRi[$this->jsonKey])) {
            $this->dataDaftarRi[$this->jsonKey] = [];
        }
        $this->entriList = $this->dataDaftarRi[$this->jsonKey];
        $this->isFormLocked = $this->checkEmrRIStatus($this->riHdrNo) || $this->disabled;

        $this->incrementVersion('modal-observasi-nifas-ri');
        $this->layar = 'daftar';
        $this->dispatch('open-modal', name: 'observasi-nifas-ri');
    }

    public function closeModal(): void
    {
        $this->dispatch('close-modal', name: 'observasi-nifas-ri');
    }

    /* ===============================
     | SET TANGGAL/JAM SEKARANG
     =============================== */
    public function setNow(): void
    {
        if ($this->isFormLocked || $this->viewOnly) {
            return;
        }
        $this->barisTglJam = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
    }

    /* ===============================
     | BARIS TITIK-WAKTU (tabel entri ala Obat Pre Medikasi)
     =============================== */
    private function barisKosong(): array
    {
        return [
            'tglJam'          => '',   // titik-waktu pemantauan (d/m/Y H:i:s)
            'sistolik'        => '',   // tekanan darah sistolik (mmHg)
            'diastolik'       => '',   // tekanan darah diastolik (mmHg)
            'nadi'            => '',   // x/mnt
            'rr'              => '',   // x/mnt
            'suhu'            => '',   // °C
            'ewsScore'        => '',   // [akr] Maternal Early Warning Score
            'tfu'             => '',   // tinggi fundus uteri (mis. 2 jari bawah pusat)
            'kontraksiUterus' => '',   // Baik/Keras | Lembek
            'lochiaJenis'     => '',   // Rubra | Sanguinolenta | Serosa | Alba
            'lochiaJumlah'    => '',   // sedikit/sedang/banyak
            'perdarahanCc'    => '',   // cc
            'lukaJalanLahir'  => '',   // Tidak ada | Kering/Baik | Basah | Tanda Infeksi
            'bak'             => '',   // buang air kecil
            'bab'             => '',   // buang air besar
            'laktasi'         => '',   // Lancar | Tidak Lancar | Belum
            'asiEksklusif'    => '',   // [akr] Ya | Tidak
            'rawatGabung'     => '',   // [akr] Ya | Tidak
            'mobilisasi'      => '',   // teks
            'keluhan'         => '',   // teks
            'asuhanTindakan'  => '',   // asuhan/tindakan kebidanan
            // Petugas penambah baris — di-stempel otomatis saat Tambah (pola Obat & Cairan RI).
            // Sifat lembar ini evaluatif: tiap titik-waktu bisa diisi petugas/shift berbeda,
            // jadi TTD lembar saja tak cukup untuk menelusuri siapa mencatat apa.
            'petugas'         => '',   // myuser_name saat baris ditambahkan
            'petugasCode'     => '',   // myuser_code saat baris ditambahkan
        ];
    }

    /**
     * Baris entri tahan data lama: sebelum rombakan, satu entri = satu titik-waktu
     * (kolomnya datar tanpa 'rows'). Entri seperti itu dibaca sebagai satu baris.
     */
    private function normalizeRows(array $entry): array
    {
        if (is_array($entry['rows'] ?? null)) {
            return array_values(array_map(
                fn($baris) => $this->pecahTdLegacy(array_replace($this->barisKosong(), is_array($baris) ? $baris : []), is_array($baris) ? $baris : []),
                $entry['rows'],
            ));
        }

        $legacy = $this->pecahTdLegacy(array_replace($this->barisKosong(), array_intersect_key($entry, $this->barisKosong())), $entry);

        return collect($legacy)->contains(fn($nilai) => filled($nilai)) ? [$legacy] : [];
    }

    /** Data lama menyimpan TD gabungan "120/80" — pecah ke sistolik/diastolik. */
    private function pecahTdLegacy(array $baris, array $sumber): array
    {
        if (blank($baris['sistolik']) && blank($baris['diastolik']) && filled($sumber['td'] ?? null)) {
            [$tdSistolik, $tdDiastolik] = array_pad(explode('/', (string) $sumber['td'], 2), 2, '');
            $baris['sistolik'] = trim($tdSistolik);
            $baris['diastolik'] = trim($tdDiastolik);
        }
        return $baris;
    }

    public function tambahBaris(): void
    {
        if ($this->isFormLocked || $this->viewOnly) {
            return;
        }

        // validate() didahulukan supaya kolom kosong tetap ditandai merah.
        $this->validateWithToast(
            [
                'barisTglJam' => ['required', 'string', 'date_format:d/m/Y H:i:s'],
                'barisSistolik' => ['nullable', 'numeric'],
                'barisDiastolik' => ['nullable', 'numeric'],
                'barisNadi' => ['nullable', 'numeric'],
                'barisRr' => ['nullable', 'numeric'],
                'barisSuhu' => ['nullable', 'numeric'],
                'barisEwsScore' => ['nullable', 'numeric'],
                'barisTfu' => ['nullable', 'string', 'max:100'],
                'barisKontraksiUterus' => ['nullable', 'string', 'max:50'],
                'barisLochiaJenis' => ['nullable', 'string', 'max:50'],
                'barisLochiaJumlah' => ['nullable', 'string', 'max:50'],
                'barisPerdarahanCc' => ['nullable', 'numeric'],
                'barisLukaJalanLahir' => ['nullable', 'string', 'max:50'],
                'barisBak' => ['nullable', 'string', 'max:100'],
                'barisBab' => ['nullable', 'string', 'max:100'],
                'barisLaktasi' => ['nullable', 'string', 'max:50'],
                'barisAsiEksklusif' => ['nullable', 'string', 'max:20'],
                'barisRawatGabung' => ['nullable', 'string', 'max:20'],
                'barisMobilisasi' => ['nullable', 'string', 'max:255'],
                'barisKeluhan' => ['nullable', 'string', 'max:255'],
                'barisAsuhanTindakan' => ['nullable', 'string', 'max:1000'],
            ],
            ['barisTglJam.date_format' => 'Tgl / Jam harus berformat dd/mm/yyyy HH:mm:ss.'],
            [
                'barisTglJam' => 'Tgl / Jam',
                'barisSistolik' => 'Sistolik',
                'barisDiastolik' => 'Diastolik',
                'barisNadi' => 'Nadi',
                'barisRr' => 'RR',
                'barisSuhu' => 'Suhu',
                'barisEwsScore' => 'EWS Score',
                'barisTfu' => 'TFU',
                'barisKontraksiUterus' => 'Kontraksi Uterus',
                'barisLochiaJenis' => 'Lochia (Jenis)',
                'barisLochiaJumlah' => 'Lochia (Jumlah)',
                'barisPerdarahanCc' => 'Perdarahan (cc)',
                'barisLukaJalanLahir' => 'Luka Jalan Lahir',
                'barisBak' => 'BAK',
                'barisBab' => 'BAB',
                'barisLaktasi' => 'Laktasi',
                'barisAsiEksklusif' => 'ASI Eksklusif',
                'barisRawatGabung' => 'Rawat Gabung',
                'barisMobilisasi' => 'Mobilisasi',
                'barisKeluhan' => 'Keluhan',
                'barisAsuhanTindakan' => 'Asuhan / Tindakan Kebidanan',
            ],
        );

        $rows = $this->normalizeRows($this->newForm);
        $rows[] = [
            'tglJam'          => $this->barisTglJam,
            'sistolik'        => $this->barisSistolik,
            'diastolik'       => $this->barisDiastolik,
            'nadi'            => $this->barisNadi,
            'rr'              => $this->barisRr,
            'suhu'            => $this->barisSuhu,
            'ewsScore'        => $this->barisEwsScore,
            'tfu'             => $this->barisTfu,
            'kontraksiUterus' => $this->barisKontraksiUterus,
            'lochiaJenis'     => $this->barisLochiaJenis,
            'lochiaJumlah'    => $this->barisLochiaJumlah,
            'perdarahanCc'    => $this->barisPerdarahanCc,
            'lukaJalanLahir'  => $this->barisLukaJalanLahir,
            'bak'             => $this->barisBak,
            'bab'             => $this->barisBab,
            'laktasi'         => $this->barisLaktasi,
            'asiEksklusif'    => $this->barisAsiEksklusif,
            'rawatGabung'     => $this->barisRawatGabung,
            'mobilisasi'      => $this->barisMobilisasi,
            'keluhan'         => $this->barisKeluhan,
            'asuhanTindakan'  => $this->barisAsuhanTindakan,
            'petugas'         => auth()->user()->myuser_name ?? '',
            'petugasCode'     => auth()->user()->myuser_code ?? '',
        ];
        $this->newForm['rows'] = $this->urutKronologis($rows);

        $this->resetBarisInput();
    }

    public function hapusBaris(int $index): void
    {
        if ($this->isFormLocked || $this->viewOnly) {
            return;
        }
        $rows = $this->normalizeRows($this->newForm);
        unset($rows[$index]);
        $this->newForm['rows'] = array_values($rows);
    }

    private function resetBarisInput(): void
    {
        $this->barisTglJam = '';
        $this->barisSistolik = '';
        $this->barisDiastolik = '';
        $this->barisNadi = '';
        $this->barisRr = '';
        $this->barisSuhu = '';
        $this->barisEwsScore = '';
        $this->barisTfu = '';
        $this->barisKontraksiUterus = '';
        $this->barisLochiaJenis = '';
        $this->barisLochiaJumlah = '';
        $this->barisPerdarahanCc = '';
        $this->barisLukaJalanLahir = '';
        $this->barisBak = '';
        $this->barisBab = '';
        $this->barisLaktasi = '';
        $this->barisAsiEksklusif = '';
        $this->barisRawatGabung = '';
        $this->barisMobilisasi = '';
        $this->barisKeluhan = '';
        $this->barisAsuhanTindakan = '';
    }

    /** Baris satu lembar tersimpan, urut kronologis — dipakai tabel entri & detail. */
    public function barisLembar(array $entry): array
    {
        return $this->urutKronologis($this->normalizeRows($entry));
    }

    /** Rentang waktu lembar: "titik pertama – titik terakhir". */
    public function periodeLembar(array $rows): string
    {
        $tglJam = collect($rows)->pluck('tglJam')->filter()->values();
        if ($tglJam->isEmpty()) {
            return '-';
        }
        return $tglJam->count() === 1 ? $tglJam->first() : $tglJam->first() . ' – ' . $tglJam->last();
    }

    /** Urut kronologis — string dd/mm/yyyy TIDAK boleh di-sort leksikografis. */
    private function urutKronologis(array $rows): array
    {
        return collect($rows)
            ->sortBy(function ($baris) {
                try {
                    return Carbon::createFromFormat('d/m/Y H:i:s', $baris['tglJam'] ?? '')->timestamp;
                } catch (\Throwable) {
                    return 0;
                }
            })
            ->values()
            ->all();
    }

    /* ===============================
     | HELPER — status & bentuk entri
     =============================== */
    // Entri dianggap FINAL/terkunci bila flag finalized true; entri lama (tanpa flag) yang sudah
    // ada TTD (nama penanda) dianggap final (kompatibilitas data lama).
    public function entryIsFinal(array $entri): bool
    {
        return array_key_exists('finalized', $entri) ? (bool) $entri['finalized'] : !empty($entri['ttd']);
    }

    // Susun array entri dari state form. $key = createdAt (kunci stabil); $finalized = status kunci.
    private function buildEntry(string $key, bool $finalized): array
    {
        return [
            'rows'      => $this->urutKronologis($this->normalizeRows($this->newForm)),
            'ttd'       => $this->newForm['ttd'] ?? '',
            'ttdCode'   => $this->newForm['ttdCode'] ?? '',
            'ttdDate'   => $this->newForm['ttdDate'] ?? '',
            'createdAt' => $key,
            'finalized' => $finalized,
        ];
    }

    // Cek: lembar punya minimal satu baris titik-waktu.
    private function adaObservasiInti(): bool
    {
        return count($this->normalizeRows($this->newForm)) > 0;
    }

    // Simpan entri (add/update by createdAt) dengan status $finalized. Dipakai draft & kunci.
    private function persistEntry(string $key, bool $finalized, string $logVerb): void
    {
        $entry = $this->buildEntry($key, $finalized);

        DB::transaction(function () use ($entry, $key, $logVerb) {
            $this->lockRIRow($this->riHdrNo);

            $fresh = $this->findDataRI($this->riHdrNo) ?: [];
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

            $this->updateJsonRI((int) $this->riHdrNo, $fresh);
            $this->dataDaftarRi = $fresh;
            $this->entriList = $fresh[$this->jsonKey];

            $this->appendAdminLogRI((int) $this->riHdrNo, $logVerb . ' Observasi Nifas — ' . count($entry['rows'] ?? []) . ' titik-waktu (' . $key . ')', 'MR');
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
        if (!$this->adaObservasiInti()) {
            $this->dispatch('toast', type: 'error', message: 'Tambahkan minimal satu baris titik-waktu observasi.');
            return;
        }

        $key = $this->editingKey ?: Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');

        try {
            $this->persistEntry($key, false, 'Simpan draft');
            $this->editingKey = $key; // lanjut edit entri yang sama, tidak buat duplikat
            $this->incrementVersion('modal-observasi-nifas-ri');
            $this->dispatch('toast', type: 'success', message: 'Draft tersimpan.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menyimpan draft: ' . $e->getMessage());
        }
    }

    /* ===============================
     | TTD PETUGAS = FINALIZE (kunci entri)
     | Stempel nama user login + tgl/jam → kunci entri.
     =============================== */
    public function ttdSaya(): void
    {
        if ($this->isFormLocked || $this->viewOnly) {
            $this->dispatch('toast', type: 'error', message: 'Form read-only.');
            return;
        }
        if (!$this->adaObservasiInti()) {
            $this->dispatch('toast', type: 'error', message: 'Tambahkan minimal satu baris titik-waktu observasi sebelum TTD.');
            return;
        }

        // Stempel TTD petugas = user login.
        $this->newForm['ttd']     = auth()->user()->myuser_name ?? '';
        $this->newForm['ttdCode'] = auth()->user()->myuser_code ?? '';
        $this->newForm['ttdDate'] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');

        $key = $this->editingKey ?: Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');

        try {
            $this->persistEntry($key, true, 'Kunci (TTD)');
            $this->resetNewForm();
            $this->editingKey = null;
            $this->viewOnly = false;
            $this->incrementVersion('modal-observasi-nifas-ri');
            $this->dispatch('toast', type: 'success', message: 'Observasi ditandatangani & terkunci.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal mengunci: ' . $e->getMessage());
        }
    }

    /** Batalkan TTD pada form (saat draft/edit, sebelum finalize benar-benar tersimpan). */
    public function hapusTtd(): void
    {
        if ($this->isFormLocked || $this->viewOnly) {
            return;
        }
        $this->newForm['ttd']     = '';
        $this->newForm['ttdCode'] = '';
        $this->newForm['ttdDate'] = '';
    }

    /* ===============================
     | BUKA KUNCI (Gate dokumen.bukaKunci) — cabut TTD petugas, entri kembali Draft.
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
                $this->lockRIRow($this->riHdrNo);

                $fresh = $this->findDataRI($this->riHdrNo) ?: [];
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

                $this->updateJsonRI((int) $this->riHdrNo, $fresh);
                $this->dataDaftarRi = $fresh;
                $this->entriList = $fresh[$this->jsonKey];

                $pembukaKunci = auth()->user()->myuser_name ?? '-';
                $this->appendAdminLogRI((int) $this->riHdrNo, 'Buka kunci Observasi Nifas (' . $createdAt . ') oleh ' . $pembukaKunci . ' — TTD petugas dicabut', 'MR');
            });

            if ($this->editingKey === $createdAt) {
                $this->cancelEdit();
            }
            $this->incrementVersion('modal-observasi-nifas-ri');
            $this->dispatch('toast', type: 'success', message: 'Kunci dibuka — TTD petugas dicabut, entri kembali Draft.');
        } catch (\RuntimeException $exception) {
            $this->dispatch('toast', type: 'error', message: $exception->getMessage());
        } catch (\Throwable $exception) {
            $this->dispatch('toast', type: 'error', message: 'Gagal membuka kunci: ' . $exception->getMessage());
        }
    }

    /* ===============================
     | EDIT / LIHAT / BATAL entri
     =============================== */
    // Muat 1 entri ke form atas (dipakai edit draft & lihat entri terkunci). TANPA TTD gambar.
    private function hydrateFormFromEntry(array $entry, string $key): void
    {
        foreach ($this->newForm as $field => $bawaan) {
            $this->newForm[$field] = $entry[$field] ?? (is_array($bawaan) ? [] : '');
        }
        // Entri lama = satu titik-waktu datar (tanpa 'rows') → dibaca jadi satu baris.
        $this->newForm['rows'] = $this->urutKronologis($this->normalizeRows($entry));
        $this->resetBarisInput();
        $this->editingKey = $key;
        $this->resetValidation();
        $this->incrementVersion('modal-observasi-nifas-ri');
    }

    public function editEntry(string $key): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form read-only.');
            return;
        }
        $entry = collect($this->entriList)->firstWhere('createdAt', $key);
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
        $entry = collect($this->entriList)->firstWhere('createdAt', $key);
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
        $this->incrementVersion('modal-observasi-nifas-ri');
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
        foreach ($this->newForm as $field => $bawaan) {
            $this->newForm[$field] = is_array($bawaan) ? [] : '';
        }
        $this->resetBarisInput();
        $this->layar = 'daftar';   // mengosongkan formulir = kembali ke daftar
    }

    protected function resetForm(): void
    {
        $this->resetVersion();
        $this->isFormLocked = false;
        $this->dataDaftarRi = [];
        $this->entriList = [];
        $this->resetNewForm();
        $this->editingKey = null;
        $this->viewOnly = false;
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
                $this->lockRIRow($this->riHdrNo);

                $fresh = $this->findDataRI($this->riHdrNo) ?: [];
                $fresh[$this->jsonKey] = collect($fresh[$this->jsonKey] ?? [])
                    ->reject(fn($entri) => ($entri['createdAt'] ?? null) === $createdAt)
                    ->values()
                    ->all();

                $this->updateJsonRI((int) $this->riHdrNo, $fresh);
                $this->dataDaftarRi = $fresh;
                $this->entriList = $fresh[$this->jsonKey];

                $this->appendAdminLogRI((int) $this->riHdrNo, 'Hapus Observasi Nifas — ' . $createdAt, 'MR');
            });

            // Jika entri yang dihapus sedang di form, kosongkan form.
            if ($this->editingKey === $createdAt) {
                $this->cancelEdit();
            }

            $this->incrementVersion('modal-observasi-nifas-ri');
            $this->dispatch('toast', type: 'success', message: 'Entri dihapus.');
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menghapus: ' . $e->getMessage());
        }
    }

    /* ===============================
     | CETAK (per-LEMBAR: semua baris = 1 tabel monitoring)
     =============================== */
    public function cetakLembar(string $createdAt)
    {
        $entry = collect($this->entriList)->firstWhere('createdAt', $createdAt);
        if (!$entry) {
            $this->dispatch('toast', type: 'error', message: 'Lembar observasi tidak ditemukan.');
            return;
        }

        $rows = $this->urutKronologis($this->normalizeRows($entry));

        if (empty($rows)) {
            $this->dispatch('toast', type: 'error', message: 'Belum ada baris observasi untuk dicetak.');
            return;
        }

        try {
            $identitasRs = DB::table('rsmst_identitases')
                ->select('int_name', 'int_phone1', 'int_phone2', 'int_fax', 'int_address', 'int_city')->first();
            $pasienData = $this->findDataMasterPasien($this->regNo ?? '');
            $pasien = $pasienData['pasien'] ?? [];

            if (!empty($pasien['tglLahir'])) {
                try {
                    $pasien['thn'] = Carbon::createFromFormat('d/m/Y', $pasien['tglLahir'])
                        ->diff(Carbon::now(config('app.timezone')))->format('%y Thn, %m Bln %d Hr');
                } catch (\Throwable) {
                    $pasien['thn'] = '-';
                }
            }

            // TTD kini milik LEMBAR (entri), bukan per baris titik-waktu.
            $ttd = $entry['ttd'] ?? '';
            $ttdDate = $entry['ttdDate'] ?? '';

            // TTD (myuser_code -> myuser_ttd_image) untuk stempel di cetakan
            $ttdPath = null;
            $ttdCode = $entry['ttdCode'] ?? null;
            if ($ttdCode) {
                $ttdImg = DB::table('users')->where('myuser_code', $ttdCode)->value('myuser_ttd_image');
                if (!empty($ttdImg) && file_exists(public_path('storage/' . $ttdImg))) {
                    $ttdPath = public_path('storage/' . $ttdImg);
                }
            }

            $data = array_merge($pasien, [
                'ttdPath'      => $ttdPath,
                'dataRi'      => $this->dataDaftarRi,
                'rows'        => $rows,
                'ttd'         => $ttd,
                'ttdDate'     => $ttdDate,
                'identitasRs' => $identitasRs,
                'tglCetak'    => Carbon::now(config('app.timezone'))->translatedFormat('d F Y'),
            ]);

            set_time_limit(300);
            $pdf = Pdf::loadView('pages.components.modul-dokumen.ri.observasi-nifas-ri.cetak-observasi-nifas-ri-print', ['data' => $data])->setPaper('A4', 'landscape');

            return response()->streamDownload(fn() => print $pdf->output(), 'observasi-nifas-' . ($pasien['regNo'] ?? $this->riHdrNo) . '.pdf');
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal cetak: ' . $e->getMessage());
        }
    }
};
?>

<div>
    {{-- ══ SUMMARY CARD (inline di tab) ══ --}}
    @php $onCount = count($entriList ?? []); @endphp
    <div class="p-5 border shadow-sm bg-canvas border-hairline rounded-2xl dark:bg-gray-900 dark:border-gray-700">
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div class="flex-1 space-y-2">
                <div class="flex items-center gap-2">
                    <h3 class="text-base font-semibold text-ink dark:text-gray-200">Observasi Nifas</h3>
                    @if ($onCount > 0)
                        <x-badge variant="success">{{ $onCount }} lembar</x-badge>
                    @else
                        <x-badge variant="warning">Belum ada</x-badge>
                    @endif
                </div>
                <p class="text-sm text-muted dark:text-gray-400">
                    Lembar pemantauan masa nifas (post-partum) per titik-waktu — TD, nadi, RR, suhu,
                    Maternal Early Warning Score, TFU, kontraksi uterus, lochia, perdarahan, luka jalan lahir,
                    laktasi, ASI eksklusif &amp; rawat gabung. Satu entri = satu lembar berisi banyak titik-waktu.
                </p>
            </div>
            <div class="flex items-center gap-2 shrink-0">
                <x-primary-button type="button" wire:click="openModal" wire:loading.attr="disabled"
                    wire:target="openModal" :disabled="$disabled || !$riHdrNo" class="gap-2">
                    <span wire:loading.remove wire:target="openModal" class="flex items-center gap-1.5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                        </svg>
                        Buka Formulir
                    </span>
                    <span wire:loading wire:target="openModal" class="flex items-center gap-1.5">
                        <x-loading class="w-4 h-4" /> Memuat...
                    </span>
                </x-primary-button>
            </div>
        </div>

        @if ($onCount > 0)
            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full text-sm border border-hairline rounded-lg dark:border-gray-700">
                    <thead class="bg-surface-soft dark:bg-gray-800">
                        <tr class="text-left text-muted dark:text-gray-300">
                            <th class="px-3 py-2 border-b">Lembar Dibuat</th>
                            <th class="px-3 py-2 border-b">Baris</th>
                            <th class="px-3 py-2 border-b">Petugas (TTD)</th>
                            <th class="px-3 py-2 text-center border-b">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach (array_reverse($entriList) as $entri)
                            <tr class="border-b border-hairline dark:border-gray-700">
                                <td class="px-3 py-2 font-medium text-ink dark:text-gray-200">{{ ($entri['createdAt'] ?? '') ?: '-' }}</td>
                                <td class="px-3 py-2 text-muted dark:text-gray-400">{{ count($this->barisLembar($entri)) }} titik-waktu</td>
                                <td class="px-3 py-2 text-muted dark:text-gray-400">
                                    @if (!empty($entri['ttd'])){{ $entri['ttd'] }}@else<x-badge variant="danger">Belum TTD</x-badge>@endif
                                </td>
                                <td class="px-3 py-2 text-center">
                                    @if ($this->entryIsFinal($entri))
                                        <x-badge variant="info">Terkunci</x-badge>
                                    @else
                                        <x-badge variant="warning">Draft</x-badge>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- ══ MODAL FORM ══ --}}
    <x-modal name="observasi-nifas-ri" size="full" height="full" focusable>
        <div class="flex flex-col min-h-[calc(100vh-8rem)]"
             wire:key="{{ $this->renderKey('modal-observasi-nifas-ri', [$riHdrNo ?? 'new']) }}">

            {{-- DISPLAY PASIEN — paling atas, mengikuti pola EMR --}}
            <div class="px-4 pt-4">
                <livewire:pages::transaksi.ri.display-pasien-ri.display-pasien-ri :riHdrNo="$riHdrNo"
                    wire:key="observasi-nifas-display-pasien-{{ $riHdrNo }}" />
            </div>

            {{-- JUDUL RINGKAS --}}
            <div class="px-6 py-4 border-b shrink-0 bg-surface-soft border-hairline dark:border-gray-700">
                <div class="flex items-start justify-between gap-4">
                    <div class="flex items-center gap-2.5">
                        <div class="flex items-center justify-center w-8 h-8 rounded-lg bg-brand-green/10 dark:bg-brand-lime/15">
                            <svg class="w-4 h-4 text-brand-green dark:text-brand-lime" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                        </div>
                        <div>
                            <h2 class="text-lg font-semibold text-ink dark:text-gray-100">Observasi Nifas</h2>
                            <p class="mt-0.5 text-sm text-muted dark:text-gray-400">Lembar pemantauan masa nifas (VK) — satu lembar berisi banyak titik-waktu. Diisi Bidan / Perawat.</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        @if (count($entriList) > 0)
                            <x-badge variant="info">{{ count($entriList) }} tersimpan</x-badge>
                        @endif
                        @if ($isFormLocked)
                            <x-badge variant="danger">Read Only</x-badge>
                        @endif
                        <x-icon-button color="gray" type="button" wire:click="closeModal">
                            <span class="sr-only">Tutup</span>
                            <svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                            </svg>
                        </x-icon-button>
                    </div>
                </div>
            </div>

            {{-- BODY --}}
            <div class="flex-1 px-4 py-4 overflow-y-auto bg-surface-soft dark:bg-gray-950/20">
                <div class="max-w-full mx-auto space-y-4">

                    @php $formReadOnly = $isFormLocked || $viewOnly; @endphp

                    @if ($isFormLocked)
                        <div class="flex items-center gap-2 px-4 py-2.5 text-sm font-medium border rounded-lg text-amber-800 bg-amber-50 border-amber-200 dark:bg-amber-900/20 dark:border-amber-800 dark:text-amber-300">
                            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                            </svg>
                            Mode tampilan saja (read-only) — pasien sudah pulang / form terkunci.
                        </div>
                    @endif

                    @if ($viewOnly)
                        <div class="flex items-center gap-2 px-4 py-2.5 text-sm font-medium text-sky-700 bg-sky-50 border border-sky-200 rounded-lg dark:bg-sky-900/20 dark:border-sky-600 dark:text-sky-300">
                            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            </svg>
                            Menampilkan entri terkunci <strong>{{ $editingKey }}</strong> (hanya lihat) — klik <strong>Selesai Melihat</strong> untuk kembali ke form entri baru.
                        </div>
                    @elseif ($editingKey && !$isFormLocked)
                        <div class="flex items-center gap-2 px-4 py-2.5 text-sm font-medium text-brand-green bg-brand-lime/10 border border-brand-lime/40 rounded-lg dark:text-brand-lime dark:bg-brand-lime/5">
                            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                            </svg>
                            Sedang melanjutkan entri <strong>{{ $editingKey }}</strong> — <strong>Simpan Perubahan</strong> menyimpan ke entri ini; klik <strong>Entri Baru</strong> untuk menambah catatan lain.
                        </div>
                    @endif

                    {{-- ── FORM ENTRI (1 lembar = banyak titik-waktu) ── --}}
                    @if ($this->diForm())
                    <fieldset @disabled($formReadOnly) class="space-y-4">

                        {{-- Titik-waktu observasi — tabel entri (banyak baris per lembar) ala Obat Pre Medikasi --}}
                        <x-border-form title="Observasi Nifas (Titik-Waktu)">
                            <div class="space-y-2">
                                <div class="overflow-x-auto bg-canvas border rounded-2xl border-hairline dark:border-gray-700">
                                    <table class="ds-table min-w-[1840px]">
                                        <thead>
                                            <tr>
                                                <th class="ds-c w-10">No</th>
                                                <th class="w-44">Tgl / Jam</th>
                                                <th class="w-28">TD (mmHg)</th>
                                                <th class="w-16">Nadi</th>
                                                <th class="w-16">RR</th>
                                                <th class="w-16">Suhu</th>
                                                <th class="w-16">EWS</th>
                                                <th class="w-32">TFU</th>
                                                <th class="w-28">Kontraksi</th>
                                                <th class="w-52">Lochia (Jenis / Jumlah)</th>
                                                <th class="w-16">Drh (cc)</th>
                                                <th class="w-28">Laktasi</th>
                                                <th>Keluhan</th>
                                                <th class="w-32">Petugas</th>
                                                <th class="ds-c w-24">Aksi</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @if (!$formReadOnly)
                                                {{-- Baris ENTRI: sejajar kolom tabel, sekali Tambah langsung masuk daftar --}}
                                                <tr class="align-top bg-surface-soft/70 dark:bg-gray-800/40">
                                                    <td class="ds-c ds-td-meta">+</td>
                                                    <td>
                                                        <div class="flex gap-1">
                                                            <x-text-input wire:model="barisTglJam" placeholder="dd/mm/yyyy HH:mm:ss" :error="$errors->has('barisTglJam')" class="w-full px-2" />
                                                            <x-now-button wire:click="setNow" class="!p-2 shrink-0" />
                                                        </div>
                                                        <x-input-error :messages="$errors->get('barisTglJam')" class="mt-1" />
                                                    </td>
                                                    <td>
                                                        <div class="flex items-center gap-1">
                                                            <x-text-input type="number" wire:model="barisSistolik" wire:keydown.enter.prevent="tambahBaris" :error="$errors->has('barisSistolik')" class="w-full px-1" placeholder="120" />
                                                            <span class="text-muted-soft">/</span>
                                                            <x-text-input type="number" wire:model="barisDiastolik" wire:keydown.enter.prevent="tambahBaris" :error="$errors->has('barisDiastolik')" class="w-full px-1" placeholder="80" />
                                                        </div>
                                                    </td>
                                                    <td><x-text-input type="number" wire:model="barisNadi" wire:keydown.enter.prevent="tambahBaris" :error="$errors->has('barisNadi')" class="w-full px-1" /></td>
                                                    <td><x-text-input type="number" wire:model="barisRr" wire:keydown.enter.prevent="tambahBaris" :error="$errors->has('barisRr')" class="w-full px-1" /></td>
                                                    <td><x-text-input type="number" step="0.1" wire:model="barisSuhu" wire:keydown.enter.prevent="tambahBaris" :error="$errors->has('barisSuhu')" class="w-full px-1" /></td>
                                                    <td><x-text-input type="number" wire:model="barisEwsScore" wire:keydown.enter.prevent="tambahBaris" :error="$errors->has('barisEwsScore')" class="w-full px-1" placeholder="MEWS" /></td>
                                                    <td><x-text-input wire:model="barisTfu" wire:keydown.enter.prevent="tambahBaris" :error="$errors->has('barisTfu')" class="w-full px-1" placeholder="2 jari bwh pusat" /></td>
                                                    <td>
                                                        <x-select-input wire:model="barisKontraksiUterus" :error="$errors->has('barisKontraksiUterus')" class="w-full px-1 text-sm">
                                                            <option value="">—</option>
                                                            <option value="Baik/Keras">Baik/Keras</option>
                                                            <option value="Lembek">Lembek</option>
                                                        </x-select-input>
                                                    </td>
                                                    <td>
                                                        <div class="flex items-center gap-1">
                                                            <x-select-input wire:model="barisLochiaJenis" :error="$errors->has('barisLochiaJenis')" class="w-full px-1 text-sm">
                                                                <option value="">—</option>
                                                                <option value="Rubra">Rubra</option>
                                                                <option value="Sanguinolenta">Sanguinolenta</option>
                                                                <option value="Serosa">Serosa</option>
                                                                <option value="Alba">Alba</option>
                                                            </x-select-input>
                                                            <x-text-input wire:model="barisLochiaJumlah" wire:keydown.enter.prevent="tambahBaris" :error="$errors->has('barisLochiaJumlah')" class="w-full px-1" placeholder="sedang" />
                                                        </div>
                                                    </td>
                                                    <td><x-text-input type="number" wire:model="barisPerdarahanCc" wire:keydown.enter.prevent="tambahBaris" :error="$errors->has('barisPerdarahanCc')" class="w-full px-1" /></td>
                                                    <td>
                                                        <x-select-input wire:model="barisLaktasi" :error="$errors->has('barisLaktasi')" class="w-full px-1 text-sm">
                                                            <option value="">—</option>
                                                            <option value="Lancar">Lancar</option>
                                                            <option value="Tidak Lancar">Tidak Lancar</option>
                                                            <option value="Belum">Belum</option>
                                                        </x-select-input>
                                                    </td>
                                                    <td><x-text-input wire:model="barisKeluhan" wire:keydown.enter.prevent="tambahBaris" :error="$errors->has('barisKeluhan')" class="w-full px-2" placeholder="mis. nyeri luka, mules" /></td>
                                                    <td class="ds-td-meta">{{ auth()->user()->myuser_name ?? '-' }}</td>
                                                    <td class="ds-c">
                                                        <x-primary-button type="button" wire:click="tambahBaris" wire:loading.attr="disabled" wire:target="tambahBaris" class="justify-center gap-1 w-full px-2 py-1.5 text-sm">
                                                            <span wire:loading.remove wire:target="tambahBaris" class="flex items-center gap-1">
                                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" /></svg>
                                                                Tambah
                                                            </span>
                                                            <span wire:loading wire:target="tambahBaris"><x-loading class="w-4 h-4" /></span>
                                                        </x-primary-button>
                                                    </td>
                                                </tr>
                                            @endif

                                            @forelse ($newForm['rows'] ?? [] as $nomor => $baris)
                                                <tr wire:key="observasi-nifas-baris-{{ $nomor }}">
                                                    <td class="ds-c ds-td-meta">{{ $nomor + 1 }}</td>
                                                    <td class="ds-td-strong">{{ ($baris['tglJam'] ?? '') ?: '-' }}</td>
                                                    <td>{{ filled($baris['sistolik'] ?? '') || filled($baris['diastolik'] ?? '') ? ($baris['sistolik'] ?? '-') . '/' . ($baris['diastolik'] ?? '-') : '-' }}</td>
                                                    <td>{{ ($baris['nadi'] ?? '') ?: '-' }}</td>
                                                    <td>{{ ($baris['rr'] ?? '') ?: '-' }}</td>
                                                    <td>{{ ($baris['suhu'] ?? '') ?: '-' }}</td>
                                                    <td>{{ ($baris['ewsScore'] ?? '') ?: '-' }}</td>
                                                    <td>{{ ($baris['tfu'] ?? '') ?: '-' }}</td>
                                                    <td>{{ ($baris['kontraksiUterus'] ?? '') ?: '-' }}</td>
                                                    <td>{{ trim(($baris['lochiaJenis'] ?? '') . ' ' . ($baris['lochiaJumlah'] ?? '')) ?: '-' }}</td>
                                                    <td>{{ ($baris['perdarahanCc'] ?? '') ?: '-' }}</td>
                                                    <td>{{ ($baris['laktasi'] ?? '') ?: '-' }}</td>
                                                    <td>{{ ($baris['keluhan'] ?? '') ?: '-' }}</td>
                                                    <td class="ds-td-meta">{{ ($baris['petugas'] ?? '') ?: '-' }}</td>
                                                    <td class="ds-c">
                                                        @if (!$formReadOnly)
                                                            <x-confirm-button variant="danger-soft" :action="'hapusBaris(' . $nomor . ')'"
                                                                title="Hapus Baris" :message="'Yakin hapus baris titik-waktu ' . (($baris['tglJam'] ?? '') ?: 'ini') . ' dari lembar?'"
                                                                confirmText="Ya, hapus" cancelText="Batal" class="px-2 py-1">
                                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                                            </x-confirm-button>
                                                        @else
                                                            <span class="text-muted-soft">—</span>
                                                        @endif
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="15" class="ds-c italic text-muted-soft">Belum ada baris titik-waktu observasi.</td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>

                                @if (!$formReadOnly)
                                    {{-- Kolom lanjutan baris entri — 21 kolom nifas tak muat semua di satu baris tabel,
                                         jadi kolom yang lebih jarang berubah ditaruh di sini. Nilainya IKUT tersimpan
                                         ke baris yang sama saat tombol Tambah ditekan, lalu ikut dikosongkan. --}}
                                    <div class="p-3 border border-dashed rounded-xl border-hairline dark:border-gray-700">
                                        <p class="mb-2 text-xs font-semibold tracking-wide uppercase text-muted-soft">Kolom Lanjutan Baris Entri (ikut tersimpan saat Tambah)</p>
                                        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                                            <div>
                                                <x-input-label value="Luka Jalan Lahir" />
                                                <x-select-input wire:model="barisLukaJalanLahir" :error="$errors->has('barisLukaJalanLahir')" class="w-full mt-1">
                                                    <option value="">—</option>
                                                    <option value="Tidak ada">Tidak ada</option>
                                                    <option value="Kering/Baik">Kering/Baik</option>
                                                    <option value="Basah">Basah</option>
                                                    <option value="Tanda Infeksi">Tanda Infeksi</option>
                                                </x-select-input>
                                                <x-input-error :messages="$errors->get('barisLukaJalanLahir')" class="mt-1" />
                                            </div>
                                            <div><x-input-label value="BAK" /><x-text-input wire:model="barisBak" wire:keydown.enter.prevent="tambahBaris" :error="$errors->has('barisBak')" class="w-full mt-1" placeholder="spontan / kateter" /><x-input-error :messages="$errors->get('barisBak')" class="mt-1" /></div>
                                            <div><x-input-label value="BAB" /><x-text-input wire:model="barisBab" wire:keydown.enter.prevent="tambahBaris" :error="$errors->has('barisBab')" class="w-full mt-1" placeholder="sudah / belum" /><x-input-error :messages="$errors->get('barisBab')" class="mt-1" /></div>
                                            <div><x-input-label value="Mobilisasi" /><x-text-input wire:model="barisMobilisasi" wire:keydown.enter.prevent="tambahBaris" :error="$errors->has('barisMobilisasi')" class="w-full mt-1" placeholder="miring kiri/kanan, duduk, jalan" /><x-input-error :messages="$errors->get('barisMobilisasi')" class="mt-1" /></div>
                                            <div>
                                                <x-input-label value="ASI Eksklusif [akr]" />
                                                <x-select-input wire:model="barisAsiEksklusif" :error="$errors->has('barisAsiEksklusif')" class="w-full mt-1">
                                                    <option value="">—</option>
                                                    <option value="Ya">Ya</option>
                                                    <option value="Tidak">Tidak</option>
                                                </x-select-input>
                                                <x-input-error :messages="$errors->get('barisAsiEksklusif')" class="mt-1" />
                                            </div>
                                            <div>
                                                <x-input-label value="Rawat Gabung [akr]" />
                                                <x-select-input wire:model="barisRawatGabung" :error="$errors->has('barisRawatGabung')" class="w-full mt-1">
                                                    <option value="">—</option>
                                                    <option value="Ya">Ya</option>
                                                    <option value="Tidak">Tidak</option>
                                                </x-select-input>
                                                <x-input-error :messages="$errors->get('barisRawatGabung')" class="mt-1" />
                                            </div>
                                            <div class="col-span-2 sm:col-span-3 lg:col-span-2">
                                                <x-input-label value="Asuhan / Tindakan Kebidanan" />
                                                <x-textarea wire:model="barisAsuhanTindakan" rows="2" class="w-full mt-1"
                                                    placeholder="mis. observasi TTV, perawatan luka, edukasi menyusui, mobilisasi dini" />
                                                <x-input-error :messages="$errors->get('barisAsuhanTindakan')" class="mt-1" />
                                            </div>
                                        </div>
                                    </div>
                                    <p class="text-xs text-muted-soft">Isi baris paling atas lalu klik <strong>Tambah</strong> (atau tekan Enter). Petugas penambah baris ikut tercatat.</p>
                                @endif
                                <p class="text-xs italic text-muted-soft">Kolom lanjutan (luka jalan lahir, BAK/BAB, mobilisasi, ASI eksklusif, rawat gabung, asuhan) tampil di detail lembar tersimpan &amp; cetakan.</p>
                            </div>
                        </x-border-form>

                        {{-- ══ TTD PETUGAS & KUNCI ══ --}}
                        <x-signature.ttd-petugas :ttd="$newForm['ttd']" :code="$newForm['ttdCode'] ?? ''"
                            :date="$newForm['ttdDate'] ?? ''" :locked="$formReadOnly" sign="ttdSaya" clear="hapusTtd"
                            title="Tanda Tangan Petugas"
                            nameLabel="Petugas (Bidan / Perawat)" dateLabel="Waktu TTD"
                            signLabel="TTD Petugas & Kunci" clearLabel="Batal TTD" />
                        @if (!$formReadOnly)
                            <p class="-mt-2 text-xs text-center text-muted">Menandatangani = mengunci observasi ini.</p>
                        @endif
                    </fieldset>

                    {{-- ── DAFTAR BARIS OBSERVASI TERSIMPAN (expandable) ── --}}
                    @endif
                    @unless ($this->diForm())
                    <x-border-form title="Lembar Observasi Nifas Tersimpan">
                            <div class="mb-3">
                                <span class="text-xs italic text-muted-soft">Klik baris untuk lihat detail lengkap. Tiap lembar dicetak lewat tombol <strong>Cetak</strong> di barisnya.</span>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="min-w-full text-sm border border-hairline rounded-lg dark:border-gray-700">
                                    <thead class="bg-surface-soft dark:bg-gray-800">
                                        <tr class="text-left text-sm font-semibold tracking-wide uppercase text-muted dark:text-gray-300">
                                            <th class="w-8 px-2 py-3 border-b"></th>
                                            <th class="px-4 py-3 border-b">Lembar Dibuat</th>
                                            <th class="px-4 py-3 border-b">Baris</th>
                                            <th class="px-4 py-3 border-b">Periode Pemantauan</th>
                                            <th class="px-4 py-3 border-b">Petugas (TTD)</th>
                                            <th class="px-4 py-3 text-center border-b">Status</th>
                                            <th class="px-4 py-3 text-center border-b">Aksi</th>
                                        </tr>
                                    </thead>
                                    @forelse (array_reverse($entriList) as $entry)
                                        @php
                                            $isFinal = $this->entryIsFinal($entry);
                                            $rowKey = $entry['createdAt'] ?? '';
                                            $barisLembar = $this->barisLembar($entry);
                                            $periodeLembar = $this->periodeLembar($barisLembar);
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
                                                    {{ $rowKey ?: '-' }}
                                                </td>
                                                <td class="px-4 py-3 align-middle text-muted dark:text-gray-300">
                                                    {{ count($barisLembar) }} titik-waktu
                                                </td>
                                                <td class="px-4 py-3 align-middle text-muted dark:text-gray-300">
                                                    {{ $periodeLembar }}
                                                </td>
                                                <td class="px-4 py-3 align-middle text-muted dark:text-gray-300">
                                                    @if (!empty($entry['ttd']))
                                                        <span class="font-medium text-ink dark:text-gray-200">{{ $entry['ttd'] }}</span>
                                                    @else
                                                        <x-badge variant="danger">Belum TTD</x-badge>
                                                    @endif
                                                </td>
                                                <td class="px-4 py-3 text-center align-middle">
                                                    @if ($isFinal)
                                                        <x-badge variant="info">Terkunci</x-badge>
                                                    @else
                                                        <x-badge variant="warning">Draft</x-badge>
                                                    @endif
                                                </td>
                                                <td class="px-4 py-3 text-center align-middle whitespace-nowrap" @click.stop>
                                                    <div class="flex flex-wrap items-center justify-center gap-1.5">
                                                        <div class="flex items-center justify-center gap-2">
                                                        @if (!$isFinal && !$isFormLocked)
                                                            <x-primary-button type="button" wire:click="editEntry('{{ $rowKey }}')" wire:loading.attr="disabled" wire:target="editEntry('{{ $rowKey }}')" class="gap-1.5" title="Lanjutkan mengisi entri ini">
                                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                                </svg>
                                                                Lanjutkan Pengisian
                                                            </x-primary-button>
                                                        @endif
                                                        @if ($isFinal)
                                                            <x-secondary-button type="button" wire:click="viewEntry('{{ $rowKey }}')" wire:loading.attr="disabled" wire:target="viewEntry('{{ $rowKey }}')" class="gap-1.5" title="Lihat detail (read-only) di form atas">
                                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                                </svg>
                                                                Lihat
                                                            </x-secondary-button>
                                                        @endif
                                                        <x-secondary-button type="button" wire:click="cetakLembar('{{ $rowKey }}')" wire:loading.attr="disabled" wire:target="cetakLembar('{{ $rowKey }}')" class="gap-1.5" title="Cetak lembar observasi ini">
                                                            <span wire:loading.remove wire:target="cetakLembar('{{ $rowKey }}')" class="flex items-center gap-1.5">
                                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                                                                </svg>
                                                                Cetak
                                                            </span>
                                                            <span wire:loading wire:target="cetakLembar('{{ $rowKey }}')" class="flex items-center gap-1.5"><x-loading class="w-5 h-5" /> Mencetak...</span>
                                                        </x-secondary-button>
                                                        </div>
                                                        @if (!$isFormLocked)
                                                            <div class="flex items-center justify-center gap-2">
                                                            @if ($isFinal)
                                                                @can('dokumen.bukaKunci')
                                                                    <x-confirm-button action="bukaKunci('{{ $rowKey }}')"
                                                                        title="Buka Kunci Observasi Nifas"
                                                                        message="TTD petugas akan dicabut & entri kembali menjadi Draft — proses TTD diulang dari awal. Lanjutkan?"
                                                                        confirmText="Ya, Buka Kunci" class="gap-1.5">
                                                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                                                d="M8 11V7a4 4 0 118 0m-8 4h10a2 2 0 012 2v5a2 2 0 01-2 2H8a2 2 0 01-2-2v-5a2 2 0 012-2z" />
                                                                        </svg>
                                                                        Buka Kunci
                                                                    </x-confirm-button>
                                                                @endcan
                                                            @endif
                                                            @can('dokumen.hapus')
                                                            <x-outline-button type="button" wire:click.prevent="hapus('{{ $rowKey }}')" wire:confirm="Yakin hapus baris observasi ini?"
                                                                wire:loading.attr="disabled"
                                                                class="!text-red-600 !bg-red-50 !border-red-200 hover:!bg-red-100 hover:!text-red-700 hover:!border-red-300 dark:!text-red-400 dark:!bg-red-900/20 dark:!border-red-800/30 dark:hover:!bg-red-900/30 dark:hover:!text-red-300"
                                                                title="Hapus">
                                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                                </svg>
                                                            </x-outline-button>
                                                            @endcan
                                                            </div>
                                                        @endif
                                                    </div>
                                                </td>
                                            </tr>

                                            {{-- DETAIL (expand) --}}
                                            <tr x-show="open" x-cloak>
                                                <td colspan="7" class="px-4 py-4 bg-surface-soft/60 dark:bg-gray-950/30">
                                                    <dl class="grid grid-cols-1 gap-x-8 gap-y-3 mb-3 md:grid-cols-2">
                                                        <div>
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Periode Pemantauan</dt>
                                                            <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $periodeLembar }}</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Petugas (TTD)</dt>
                                                            <dd class="mt-0.5">
                                                                @if (!empty($entry['ttd']))
                                                                    <span class="text-ink dark:text-gray-200">{{ $entry['ttd'] }}</span>
                                                                    <span class="text-sm text-muted-soft">— {{ $entry['ttdDate'] ?? '-' }}</span>
                                                                @else
                                                                    <x-badge variant="danger">Belum TTD</x-badge>
                                                                @endif
                                                            </dd>
                                                        </div>
                                                    </dl>

                                                    <div class="overflow-x-auto bg-canvas border rounded-2xl border-hairline dark:border-gray-700">
                                                        <table class="ds-table">
                                                            <thead>
                                                                <tr>
                                                                    <th class="ds-c w-10">No</th>
                                                                    <th>Tgl / Jam</th>
                                                                    <th>TD (mmHg)</th>
                                                                    <th>Nadi</th>
                                                                    <th>RR</th>
                                                                    <th>Suhu</th>
                                                                    <th>EWS</th>
                                                                    <th>TFU</th>
                                                                    <th>Kontraksi</th>
                                                                    <th>Lochia</th>
                                                                    <th>Drh (cc)</th>
                                                                    <th>Luka</th>
                                                                    <th>BAK</th>
                                                                    <th>BAB</th>
                                                                    <th>Laktasi</th>
                                                                    <th>ASI</th>
                                                                    <th>Rawat Gabung</th>
                                                                    <th>Mobilisasi</th>
                                                                    <th>Keluhan</th>
                                                                    <th>Asuhan / Tindakan</th>
                                                                    <th>Petugas</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                @forelse ($barisLembar as $nomorBaris => $barisDetail)
                                                                    <tr>
                                                                        <td class="ds-c ds-td-meta">{{ $nomorBaris + 1 }}</td>
                                                                        <td class="ds-td-strong">{{ ($barisDetail['tglJam'] ?? '') ?: '-' }}</td>
                                                                        <td>{{ filled($barisDetail['sistolik'] ?? '') || filled($barisDetail['diastolik'] ?? '') ? ($barisDetail['sistolik'] ?? '-') . '/' . ($barisDetail['diastolik'] ?? '-') : '-' }}</td>
                                                                        <td>{{ ($barisDetail['nadi'] ?? '') ?: '-' }}</td>
                                                                        <td>{{ ($barisDetail['rr'] ?? '') ?: '-' }}</td>
                                                                        <td>{{ ($barisDetail['suhu'] ?? '') ?: '-' }}</td>
                                                                        <td>{{ ($barisDetail['ewsScore'] ?? '') ?: '-' }}</td>
                                                                        <td>{{ ($barisDetail['tfu'] ?? '') ?: '-' }}</td>
                                                                        <td>{{ ($barisDetail['kontraksiUterus'] ?? '') ?: '-' }}</td>
                                                                        <td>{{ trim(($barisDetail['lochiaJenis'] ?? '') . ' ' . ($barisDetail['lochiaJumlah'] ?? '')) ?: '-' }}</td>
                                                                        <td>{{ ($barisDetail['perdarahanCc'] ?? '') ?: '-' }}</td>
                                                                        <td>{{ ($barisDetail['lukaJalanLahir'] ?? '') ?: '-' }}</td>
                                                                        <td>{{ ($barisDetail['bak'] ?? '') ?: '-' }}</td>
                                                                        <td>{{ ($barisDetail['bab'] ?? '') ?: '-' }}</td>
                                                                        <td>{{ ($barisDetail['laktasi'] ?? '') ?: '-' }}</td>
                                                                        <td>{{ ($barisDetail['asiEksklusif'] ?? '') ?: '-' }}</td>
                                                                        <td>{{ ($barisDetail['rawatGabung'] ?? '') ?: '-' }}</td>
                                                                        <td>{{ ($barisDetail['mobilisasi'] ?? '') ?: '-' }}</td>
                                                                        <td>{{ ($barisDetail['keluhan'] ?? '') ?: '-' }}</td>
                                                                        <td>{{ ($barisDetail['asuhanTindakan'] ?? '') ?: '-' }}</td>
                                                                        <td class="ds-td-meta">{{ ($barisDetail['petugas'] ?? '') ?: '-' }}</td>
                                                                    </tr>
                                                                @empty
                                                                    <tr>
                                                                        <td colspan="21" class="ds-c italic text-muted-soft">Lembar ini belum berisi baris titik-waktu.</td>
                                                                    </tr>
                                                                @endforelse
                                                            </tbody>
                                                        </table>
                                                    </div>
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

            {{-- FOOTER --}}
            <div class="sticky bottom-0 z-10 px-6 py-3 border-t shrink-0 bg-surface-soft border-hairline dark:bg-gray-900 dark:border-gray-700">
                @if ($this->diForm())
                <div class="flex flex-wrap items-center justify-between gap-3">
                    @if ($viewOnly)
                        <p class="flex items-center gap-1.5 text-sm text-sky-600 dark:text-sky-400">
                            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            </svg>
                            <span>Mode lihat — entri terkunci, tidak dapat diubah.</span>
                        </p>
                    @elseif (!$isFormLocked)
                        <p class="flex items-center gap-1.5 text-sm text-muted dark:text-gray-400">
                            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span>Simpan draft dulu, lalu <strong>kunci</strong> lewat tombol <strong>TTD Petugas &amp; Kunci</strong>.</span>
                        </p>
                    @else
                        <span></span>
                    @endif

                    <div class="flex flex-wrap items-center justify-end gap-3">
                        <x-secondary-button type="button" wire:click="kembaliKeDaftar">Kembali ke Daftar</x-secondary-button>

                        @if ($viewOnly)
                            <x-primary-button wire:click.prevent="cancelEdit" wire:target="cancelEdit"
                                wire:loading.attr="disabled" class="gap-1.5 min-w-[160px] justify-center">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                                </svg>
                                Selesai Melihat
                            </x-primary-button>
                        @elseif (!$isFormLocked)
                            @if ($editingKey)
                                <x-outline-button wire:click.prevent="cancelEdit" wire:target="cancelEdit"
                                    wire:loading.attr="disabled" class="gap-1.5"
                                    title="Kosongkan form untuk menambah catatan lain — entri yang sudah tersimpan tidak berubah">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                    </svg>
                                    Entri Baru
                                </x-outline-button>
                            @endif
                            <x-primary-button wire:click.prevent="saveDraft" wire:loading.attr="disabled"
                                wire:target="saveDraft" class="gap-2 min-w-[160px] justify-center">
                                <span wire:loading.remove wire:target="saveDraft" class="flex items-center gap-1.5">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 21v-8H7v8M7 3v5h8M5 3h11l4 4v12a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2z" />
                                    </svg>
                                    {{ $editingKey ? 'Simpan Perubahan' : 'Simpan Draft' }}
                                </span>
                                <span wire:loading wire:target="saveDraft"><x-loading class="w-4 h-4" /> Menyimpan...</span>
                            </x-primary-button>
                        @endif
                    </div>
                </div>
                @else
                    <div class="flex flex-wrap items-center justify-end gap-2">
                        <x-secondary-button type="button" wire:click="closeModal">Tutup</x-secondary-button>
                        @unless ($isFormLocked)
                            <x-primary-button type="button" wire:click="tambahEntri" wire:target="tambahEntri"
                                wire:loading.attr="disabled" class="gap-1.5 min-w-[150px] justify-center">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                </svg>
                                Isi Formulir Baru
                            </x-primary-button>
                        @endunless
                    </div>
                @endif
            </div>

        </div>
    </x-modal>
</div>
