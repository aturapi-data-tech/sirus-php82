<?php
// resources/views/pages/transaksi/ri/emr-ri/modul-dokumen/laporan-persalinan-ri/rm-laporan-persalinan-ri-actions.blade.php
// Dokumen VK/Kebidanan — Laporan Tindakan Persalinan (RM 44.c).
// Pola: multi-entri (Draft + Lanjutkan Pengisian + TTD-Kunci + Lihat read-only + tabel expandable),
// disimpan ke datadaftarri_json. Tiap entri = 1 laporan persalinan; cetak = SATU lembar per entri.
// Kunci entri stabil = createdAt. TTD = stempel nama user login (ttdSaya = FINALIZE/kunci), tanpa TTD gambar.
// [scan] = field dari form fisik; [akr] = tambahan akreditasi (PONEK / Prognas 1).

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
    protected array $renderAreas = ['modal-laporan-persalinan-ri'];

    /** Key penyimpanan di datadaftarri_json */
    private string $jsonKey = 'laporanPersalinanRI';

    public array $newForm = [
        // Jenis Partus
        'jenisPartus'         => '',   // Partus Spontan | Partus Buatan
        'indikasi'            => '',
        // BAYI — baris berulang: satu persalinan bisa menghasilkan bayi kembar (gemelli),
        // dan tiap bayi punya BB/PB/APGAR/jenis kelamin/keadaan sendiri. Lihat bayiKosong().
        'bayi'                => [],
        // PLASENTA
        'plasentaLahirTgl'    => '',
        'plasentaCara'        => '',   // Spontan | Manual
        'plasentaJenis'       => '',   // Lengkap | Tidak Lengkap
        'plasentaBerat'       => '',   // gr
        'plasentaDiameter'    => '',   // cm
        // TALI PUSAT
        'taliPusatInsersi'    => '',
        'taliPusatPanjang'    => '',   // cm
        // SELAPUT JANIN
        'selaputKeadaan'      => '',   // Lengkap | Tidak Lengkap
        'selaputRobekan'      => '',
        'selaputLain'         => '',
        // PERLUKAAN JALAN LAHIR
        'lukaPerineum'        => '',
        'episiotomi'          => '',   // Ya | Tidak
        'rupturaPerinei'      => '',   // Tidak | Tk I | Tk II | Tk III
        'lukaVagina'          => '',
        'lukaServiks'         => '',
        // KALA IV
        'kalaIvHb'            => '',
        'kalaIvSuhu'         => '',
        'kalaIvSistolik'     => '',   // mmHg
        'kalaIvDiastolik'    => '',   // mmHg
        'kalaIvNadi'         => '',
        'kalaIvRr'           => '',
        'kalaIvTfu'          => '',
        'kalaIvKontraksi'    => '',
        'perdarahanKalaIii'  => '',   // cc
        'perdarahanKalaIv'   => '',   // cc
        // TAMBAHAN AKREDITASI (PONEK / Prognas 1)
        'imdDilakukan'       => '',   // [akr] Ya | Tidak
        'imdTglJam'          => '',   // [akr] tgl+jam IMD — 'd/m/Y H:i:s'
        'imdDurasiMenit'     => '',   // [akr]
        'imdAlasanTidak'     => '',   // [akr]
        'rawatGabung'        => '',   // [akr] Ya | Tidak
        'asiKonseling'       => '',   // [akr] Ya | Tidak
        'pmkDilakukan'       => '',   // [akr] Ya | Tidak | Tidak Perlu (BBLR)
        // Penutup
        'ttd'                => '',   // nama penanda-tangan (myuser_name)
        'ttdDate'            => '',   // tgl/jam TTD (d/m/Y H:i:s)
        'ttdCode'            => '',   // myuser_code penanda-tangan
    ];

    // Field penyusun baris bayi baru (di atas tabel) — dikosongkan lagi tiap kali Tambah.
    public string $bayiLahir = '';
    public string $bayiJenisKelamin = '';
    public string $bayiKeadaan = '';
    public string $bayiBb = '';
    public string $bayiPb = '';
    public string $bayiApgar = '';
    public string $bayiResusitasi = '';
    public string $bayiUkKepalaBt = '';
    public string $bayiUkKepalaBp = '';
    public string $bayiUkKepalaFo = '';
    public string $bayiUkKepalaMo = '';
    public string $bayiUkKepalaOb = '';
    public string $bayiCaputSuksedanium = '';
    public string $bayiCephalHematoma = '';
    public string $bayiAtresiaAni = '';
    public string $bayiLain = '';

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

    protected function messages(): array
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
        $this->registerAreas(['modal-laporan-persalinan-ri']);

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

        $this->incrementVersion('modal-laporan-persalinan-ri');
        $this->layar = 'daftar';
        $this->dispatch('open-modal', name: 'laporan-persalinan-ri');
    }

    public function closeModal(): void
    {
        $this->dispatch('close-modal', name: 'laporan-persalinan-ri');
    }

    /* ===============================
     | SET TANGGAL/JAM SEKARANG
     =============================== */
    // Kolom tanggal+jam (tombol x-now-button) — format seragam repo 'dd/mm/yyyy HH:mm:ss'.
    public function setNow(string $field): void
    {
        if ($this->isFormLocked || $this->viewOnly) {
            return;
        }
        $this->newForm[$field] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
    }

    // Tgl/jam lahir bayi ada di baris input (bukan di newForm) → tombol jam tersendiri.
    public function setNowBayiLahir(): void
    {
        if ($this->isFormLocked || $this->viewOnly) {
            return;
        }
        $this->bayiLahir = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
    }

    /* ===============================
     | BARIS BAYI (tabel entri — kelahiran kembar / gemelli)
     =============================== */
    private function bayiKosong(): array
    {
        return [
            'lahir'            => '',   // tgl+jam lahir (d/m/Y H:i:s)
            'jenisKelamin'     => '',   // Laki-laki | Perempuan
            'keadaan'          => '',   // Hidup | Mati
            'bb'               => '',   // gr
            'pb'               => '',   // cm
            'apgar'            => '',   // mis 7-8-9
            'resusitasi'       => '',   // Ya | Tidak
            'ukKepalaBt'       => '',   // cm
            'ukKepalaBp'       => '',   // cm
            'ukKepalaFo'       => '',   // cm
            'ukKepalaMo'       => '',   // cm
            'ukKepalaOb'       => '',   // cm
            'caputSuksedanium' => '',
            'cephalHematoma'   => '',
            'atresiaAni'       => '',
            'lain'             => '',
            // Petugas penambah baris — di-stempel otomatis saat Tambah (pola Obat & Cairan RI).
            'petugas'          => '',   // myuser_name saat baris ditambahkan
            'petugasCode'      => '',   // myuser_code saat baris ditambahkan
        ];
    }

    /** Peta kolom bayi datar (entri sebelum rombakan) → kunci baris bayi[]. */
    private function petaBayiLegacy(): array
    {
        return [
            'bayiLahirTgl'     => 'lahir',
            'bayiJenisKelamin' => 'jenisKelamin',
            'bayiKeadaan'      => 'keadaan',
            'bayiBb'           => 'bb',
            'bayiPb'           => 'pb',
            'bayiApgar'        => 'apgar',
            'bayiResusitasi'   => 'resusitasi',
            'ukKepalaBt'       => 'ukKepalaBt',
            'ukKepalaBp'       => 'ukKepalaBp',
            'ukKepalaFo'       => 'ukKepalaFo',
            'ukKepalaMo'       => 'ukKepalaMo',
            'ukKepalaOb'       => 'ukKepalaOb',
            'caputSuksedanium' => 'caputSuksedanium',
            'cephalHematoma'   => 'cephalHematoma',
            'atresiaAni'       => 'atresiaAni',
            'bayiLain'         => 'lain',
        ];
    }

    /**
     * Baris bayi tahan data lama: sebelum rombakan, satu entri hanya memuat SATU bayi
     * dengan kolom datar berprefiks bayi... di akar entri. Entri seperti itu dibaca
     * sebagai satu baris bayi.
     */
    private function normalizeBayi(array $entry): array
    {
        if (is_array($entry['bayi'] ?? null)) {
            return array_values(
                array_map(
                    fn($baris) => array_replace(
                        $this->bayiKosong(),
                        is_array($baris) ? array_intersect_key($baris, $this->bayiKosong()) : [],
                    ),
                    $entry['bayi'],
                ),
            );
        }

        $legacy = $this->bayiKosong();
        foreach ($this->petaBayiLegacy() as $fieldLama => $kunciBaris) {
            $legacy[$kunciBaris] = (string) ($entry[$fieldLama] ?? '');
        }

        return collect($legacy)->contains(fn($nilai) => filled($nilai)) ? [$legacy] : [];
    }

    /** Baris bayi satu entri — dipakai tabel form, detail expand & cetak. */
    public function daftarBayi(array $entry): array
    {
        return $this->normalizeBayi($entry);
    }

    public function tambahBayi(): void
    {
        if ($this->isFormLocked || $this->viewOnly) {
            return;
        }

        // validate() didahulukan supaya kolom kosong tetap ditandai merah.
        $this->validateWithToast(
            [
                'bayiLahir' => ['required', 'string', 'date_format:d/m/Y H:i:s'],
                'bayiJenisKelamin' => ['nullable', 'string', 'in:Laki-laki,Perempuan'],
                'bayiKeadaan' => ['nullable', 'string', 'in:Hidup,Mati'],
                'bayiBb' => ['nullable', 'numeric'],
                'bayiPb' => ['nullable', 'numeric'],
                'bayiApgar' => ['nullable', 'string', 'max:50'],
                'bayiResusitasi' => ['nullable', 'string', 'in:Ya,Tidak'],
                'bayiUkKepalaBt' => ['nullable', 'numeric'],
                'bayiUkKepalaBp' => ['nullable', 'numeric'],
                'bayiUkKepalaFo' => ['nullable', 'numeric'],
                'bayiUkKepalaMo' => ['nullable', 'numeric'],
                'bayiUkKepalaOb' => ['nullable', 'numeric'],
                'bayiCaputSuksedanium' => ['nullable', 'string', 'max:100'],
                'bayiCephalHematoma' => ['nullable', 'string', 'max:100'],
                'bayiAtresiaAni' => ['nullable', 'string', 'max:100'],
                'bayiLain' => ['nullable', 'string', 'max:255'],
            ],
            ['bayiLahir.date_format' => 'Lahir — Tgl / Jam harus berformat dd/mm/yyyy HH:mm:ss.'],
            [
                'bayiLahir' => 'Lahir — Tgl / Jam',
                'bayiJenisKelamin' => 'Jenis Kelamin',
                'bayiKeadaan' => 'Keadaan',
                'bayiBb' => 'Berat',
                'bayiPb' => 'Panjang',
                'bayiApgar' => 'APGAR Score',
                'bayiResusitasi' => 'Resusitasi',
                'bayiUkKepalaBt' => 'Ukuran Kepala BT',
                'bayiUkKepalaBp' => 'Ukuran Kepala BP',
                'bayiUkKepalaFo' => 'Ukuran Kepala FO',
                'bayiUkKepalaMo' => 'Ukuran Kepala MO',
                'bayiUkKepalaOb' => 'Ukuran Kepala OB',
                'bayiCaputSuksedanium' => 'Caput Suksedanium',
                'bayiCephalHematoma' => 'Cephal Hematoma',
                'bayiAtresiaAni' => 'Atresia Ani',
                'bayiLain' => 'Bayi — Lain-lain',
            ],
        );

        $bayi = $this->normalizeBayi($this->newForm);
        $bayi[] = [
            'lahir'            => $this->bayiLahir,
            'jenisKelamin'     => $this->bayiJenisKelamin,
            'keadaan'          => $this->bayiKeadaan,
            'bb'               => $this->bayiBb,
            'pb'               => $this->bayiPb,
            'apgar'            => $this->bayiApgar,
            'resusitasi'       => $this->bayiResusitasi,
            'ukKepalaBt'       => $this->bayiUkKepalaBt,
            'ukKepalaBp'       => $this->bayiUkKepalaBp,
            'ukKepalaFo'       => $this->bayiUkKepalaFo,
            'ukKepalaMo'       => $this->bayiUkKepalaMo,
            'ukKepalaOb'       => $this->bayiUkKepalaOb,
            'caputSuksedanium' => $this->bayiCaputSuksedanium,
            'cephalHematoma'   => $this->bayiCephalHematoma,
            'atresiaAni'       => $this->bayiAtresiaAni,
            'lain'             => $this->bayiLain,
            'petugas'          => auth()->user()->myuser_name ?? '',
            'petugasCode'      => auth()->user()->myuser_code ?? '',
        ];
        $this->newForm['bayi'] = array_values($bayi);

        $this->resetBayiInput();
    }

    public function hapusBayi(int $index): void
    {
        if ($this->isFormLocked || $this->viewOnly) {
            return;
        }
        $bayi = $this->normalizeBayi($this->newForm);
        unset($bayi[$index]);
        $this->newForm['bayi'] = array_values($bayi);
    }

    private function resetBayiInput(): void
    {
        $this->bayiLahir = '';
        $this->bayiJenisKelamin = '';
        $this->bayiKeadaan = '';
        $this->bayiBb = '';
        $this->bayiPb = '';
        $this->bayiApgar = '';
        $this->bayiResusitasi = '';
        $this->bayiUkKepalaBt = '';
        $this->bayiUkKepalaBp = '';
        $this->bayiUkKepalaFo = '';
        $this->bayiUkKepalaMo = '';
        $this->bayiUkKepalaOb = '';
        $this->bayiCaputSuksedanium = '';
        $this->bayiCephalHematoma = '';
        $this->bayiAtresiaAni = '';
        $this->bayiLain = '';
    }

    /** Ukuran kepala satu bayi dirangkum jadi satu string (dipakai detail expand). */
    public function ringkasUkuranKepala(array $baris): string
    {
        return collect(['ukKepalaBt' => 'BT', 'ukKepalaBp' => 'BP', 'ukKepalaFo' => 'FO', 'ukKepalaMo' => 'MO', 'ukKepalaOb' => 'OB'])
            ->filter(fn(string $label, string $field) => filled($baris[$field] ?? null))
            ->map(fn(string $label, string $field) => $label . ' ' . $baris[$field] . ' cm')
            ->implode(', ');
    }

    /**
     * Data lama menyimpan TD Kala IV gabungan "120/80" — pecah ke sistolik/diastolik.
     * $sumber = entri asli (boleh masih memuat kalaIvTd).
     */
    private function pecahTdLegacy(array $form, array $sumber): array
    {
        if (blank($form['kalaIvSistolik'] ?? null) && blank($form['kalaIvDiastolik'] ?? null) && filled($sumber['kalaIvTd'] ?? null)) {
            [$tdSistolik, $tdDiastolik] = array_pad(explode('/', (string) $sumber['kalaIvTd'], 2), 2, '');
            $form['kalaIvSistolik'] = trim($tdSistolik);
            $form['kalaIvDiastolik'] = trim($tdDiastolik);
        }
        return $form;
    }

    /** TD Kala IV siap tampil "120/80" — tahan entri lama (kalaIvTd gabungan). */
    public function tdKalaIv(array $entry): string
    {
        $pecah = $this->pecahTdLegacy(
            [
                'kalaIvSistolik' => $entry['kalaIvSistolik'] ?? '',
                'kalaIvDiastolik' => $entry['kalaIvDiastolik'] ?? '',
            ],
            $entry,
        );

        if (blank($pecah['kalaIvSistolik']) && blank($pecah['kalaIvDiastolik'])) {
            return '-';
        }

        return ($pecah['kalaIvSistolik'] ?: '-') . '/' . ($pecah['kalaIvDiastolik'] ?: '-');
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
            'jenisPartus'      => $this->newForm['jenisPartus'] ?? '',
            'indikasi'         => $this->newForm['indikasi'] ?? '',
            'bayi'             => $this->normalizeBayi($this->newForm),
            'plasentaLahirTgl' => $this->newForm['plasentaLahirTgl'] ?? '',
            'plasentaCara'     => $this->newForm['plasentaCara'] ?? '',
            'plasentaJenis'    => $this->newForm['plasentaJenis'] ?? '',
            'plasentaBerat'    => $this->newForm['plasentaBerat'] ?? '',
            'plasentaDiameter' => $this->newForm['plasentaDiameter'] ?? '',
            'taliPusatInsersi' => $this->newForm['taliPusatInsersi'] ?? '',
            'taliPusatPanjang' => $this->newForm['taliPusatPanjang'] ?? '',
            'selaputKeadaan'   => $this->newForm['selaputKeadaan'] ?? '',
            'selaputRobekan'   => $this->newForm['selaputRobekan'] ?? '',
            'selaputLain'      => $this->newForm['selaputLain'] ?? '',
            'lukaPerineum'     => $this->newForm['lukaPerineum'] ?? '',
            'episiotomi'       => $this->newForm['episiotomi'] ?? '',
            'rupturaPerinei'   => $this->newForm['rupturaPerinei'] ?? '',
            'lukaVagina'       => $this->newForm['lukaVagina'] ?? '',
            'lukaServiks'      => $this->newForm['lukaServiks'] ?? '',
            'kalaIvHb'         => $this->newForm['kalaIvHb'] ?? '',
            'kalaIvSuhu'       => $this->newForm['kalaIvSuhu'] ?? '',
            'kalaIvSistolik'   => $this->newForm['kalaIvSistolik'] ?? '',
            'kalaIvDiastolik'  => $this->newForm['kalaIvDiastolik'] ?? '',
            'kalaIvNadi'       => $this->newForm['kalaIvNadi'] ?? '',
            'kalaIvRr'         => $this->newForm['kalaIvRr'] ?? '',
            'kalaIvTfu'        => $this->newForm['kalaIvTfu'] ?? '',
            'kalaIvKontraksi'  => $this->newForm['kalaIvKontraksi'] ?? '',
            'perdarahanKalaIii' => $this->newForm['perdarahanKalaIii'] ?? '',
            'perdarahanKalaIv' => $this->newForm['perdarahanKalaIv'] ?? '',
            'imdDilakukan'     => $this->newForm['imdDilakukan'] ?? '',
            'imdTglJam'        => $this->newForm['imdTglJam'] ?? '',
            'imdDurasiMenit'   => $this->newForm['imdDurasiMenit'] ?? '',
            'imdAlasanTidak'   => $this->newForm['imdAlasanTidak'] ?? '',
            'rawatGabung'      => $this->newForm['rawatGabung'] ?? '',
            'asiKonseling'     => $this->newForm['asiKonseling'] ?? '',
            'pmkDilakukan'     => $this->newForm['pmkDilakukan'] ?? '',
            'ttd'              => $this->newForm['ttd'] ?? '',
            'ttdCode'          => $this->newForm['ttdCode'] ?? '',
            'ttdDate'          => $this->newForm['ttdDate'] ?? '',
            'createdAt'        => $key,
            'finalized'        => $finalized,
        ];
    }

    // Cek: minimal jenis partus terisi ATAU sudah ada satu baris bayi.
    private function adaIntiPersalinan(): bool
    {
        return filled($this->newForm['jenisPartus'] ?? null) || count($this->normalizeBayi($this->newForm)) > 0;
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

            $this->appendAdminLogRI((int) $this->riHdrNo, $logVerb . ' Laporan Persalinan — ' . (($entry['jenisPartus'] ?? '') ?: '-') . ' (' . $key . ')', 'MR');
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
        if (!$this->adaIntiPersalinan()) {
            $this->dispatch('toast', type: 'error', message: 'Isi Jenis Partus atau tambahkan minimal satu baris data bayi.');
            return;
        }

        $key = $this->editingKey ?: Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');

        try {
            $this->persistEntry($key, false, 'Simpan draft');
            $this->editingKey = $key; // lanjut edit entri yang sama, tidak buat duplikat
            $this->incrementVersion('modal-laporan-persalinan-ri');
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
        if (!$this->adaIntiPersalinan()) {
            $this->dispatch('toast', type: 'error', message: 'Isi Jenis Partus atau tambahkan minimal satu baris data bayi sebelum TTD.');
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
            $this->incrementVersion('modal-laporan-persalinan-ri');
            $this->dispatch('toast', type: 'success', message: 'Laporan persalinan ditandatangani & terkunci.');
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
                $this->appendAdminLogRI((int) $this->riHdrNo, 'Buka kunci Laporan Tindakan Persalinan (' . $createdAt . ') oleh ' . $pembukaKunci . ' — TTD petugas dicabut', 'MR');
            });

            if ($this->editingKey === $createdAt) {
                $this->cancelEdit();
            }
            $this->incrementVersion('modal-laporan-persalinan-ri');
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
        // Entri lama = satu bayi datar (tanpa 'bayi') → dibaca jadi satu baris bayi.
        $this->newForm['bayi'] = $this->normalizeBayi($entry);
        // Entri lama menyimpan TD Kala IV gabungan "120/80".
        $this->newForm = $this->pecahTdLegacy($this->newForm, $entry);
        $this->resetBayiInput();
        $this->editingKey = $key;
        $this->resetValidation();
        $this->incrementVersion('modal-laporan-persalinan-ri');
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
        $this->incrementVersion('modal-laporan-persalinan-ri');
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
        $this->resetBayiInput();
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

                $this->appendAdminLogRI((int) $this->riHdrNo, 'Hapus Laporan Persalinan — ' . $createdAt, 'MR');
            });

            // Jika entri yang dihapus sedang di form, kosongkan form.
            if ($this->editingKey === $createdAt) {
                $this->cancelEdit();
            }

            $this->incrementVersion('modal-laporan-persalinan-ri');
            $this->dispatch('toast', type: 'success', message: 'Entri dihapus.');
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menghapus: ' . $e->getMessage());
        }
    }

    /* ===============================
     | CETAK (per-ENTRI: 1 laporan = 1 lembar)
     =============================== */
    public function cetak(string $createdAt)
    {
        $entry = collect($this->entriList)->firstWhere('createdAt', $createdAt);
        if (!$entry) {
            $this->dispatch('toast', type: 'error', message: 'Data laporan persalinan tidak ditemukan.');
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
                'dataRi'       => $this->dataDaftarRi,
                'form'         => $entry,
                'identitasRs'  => $identitasRs,
                'tglCetak'     => Carbon::now(config('app.timezone'))->translatedFormat('d F Y'),
            ]);

            set_time_limit(300);
            $pdf = Pdf::loadView('pages.components.modul-dokumen.ri.laporan-persalinan-ri.cetak-laporan-persalinan-ri-print', ['data' => $data])->setPaper('A4');

            return response()->streamDownload(fn() => print $pdf->output(), 'laporan-persalinan-' . ($pasien['regNo'] ?? $this->riHdrNo) . '.pdf');
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal cetak: ' . $e->getMessage());
        }
    }
};
?>

<div>
    {{-- ══ SUMMARY CARD (inline di tab) ══ --}}
    @php $lpCount = count($entriList ?? []); @endphp
    <div class="p-5 border shadow-sm bg-canvas border-hairline rounded-2xl dark:bg-gray-900 dark:border-gray-700">
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div class="flex-1 min-w-0 space-y-2">
                {{-- JUDUL KARTU SEBARIS — judul · badge · deskripsi --}}
                <div class="flex items-baseline flex-1 gap-2 min-w-0">
                    <h3 class="truncate shrink-0 text-base font-semibold text-ink dark:text-gray-200">Laporan Tindakan Persalinan</h3>
                    @if ($lpCount > 0)
                        <x-badge class="shrink-0 whitespace-nowrap" variant="success">{{ $lpCount }} entri</x-badge>
                    @else
                        <x-badge class="shrink-0 whitespace-nowrap" variant="warning">Belum ada</x-badge>
                    @endif
                    <x-deskripsi-ringkas class="hidden sm:flex text-sm">Laporan tindakan persalinan (RM 44.c) — jenis partus, data bayi &amp; APGAR, plasenta, tali pusat, selaput janin, perlukaan jalan lahir, Kala IV, serta IMD/Rawat Gabung/ASI (PONEK/Prognas 1). Diisi Dokter.</x-deskripsi-ringkas>
                </div>
            </div>
            <div class="flex shrink-0">
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

            <div class="mt-3 overflow-x-auto rounded-2xl border border-hairline dark:border-gray-700">
                <table class="min-w-full text-sm">
                    <thead class="bg-surface-card dark:bg-gray-800">
                        <tr class="text-xs font-semibold tracking-wide text-left text-muted uppercase dark:text-gray-300">
                            <th class="px-3 py-2 border-b">Tgl / Jam</th>
                            <th class="px-3 py-2 border-b">Jenis Partus</th>
                            <th class="px-3 py-2 border-b">Petugas (TTD)</th>
                            <th class="px-3 py-2 text-center border-b">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse (array_reverse($entriList) as $entri)
                            <tr class="border-b border-hairline dark:border-gray-700">
                                <td class="px-3 py-2 font-medium text-ink dark:text-gray-200">{{ $entri['createdAt'] ?? '-' }}</td>
                                <td class="px-3 py-2 text-muted dark:text-gray-400">{{ ($entri['jenisPartus'] ?? '') ?: '-' }}</td>
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
                        @empty
                            <tr>
                                <td colspan="4" class="px-3 py-6 text-center text-muted-soft">Belum ada data tersimpan</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
    </div>

    {{-- ══ MODAL FORM ══ --}}
    <x-modal name="laporan-persalinan-ri" size="full" height="full" focusable>
        <div class="flex flex-col min-h-[calc(100vh-8rem)]"
             wire:key="{{ $this->renderKey('modal-laporan-persalinan-ri', [$riHdrNo ?? 'new']) }}">
            {{-- JUDUL + TOMBOL TUTUP SEBARIS — judul di kiri, X di kanan, paling atas modal --}}
            <div class="px-6 py-2.5 border-b shrink-0 bg-surface-soft border-hairline dark:border-gray-700">
                <div class="flex items-center gap-3 min-w-0 flex-1 min-w-0">
                    <div class="flex items-center gap-2.5">
                        <div class="flex items-center justify-center w-7 h-7 rounded-lg shrink-0 bg-brand-green/10 dark:bg-brand-lime/15">
                            <svg class="w-4 h-4 text-brand-green dark:text-brand-lime" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                        </div>
                        <div class="flex items-baseline gap-2 min-w-0">
                            <h2 class="text-sm truncate shrink-0 font-semibold text-ink dark:text-gray-100">Laporan Tindakan Persalinan</h2>
                            <p class="flex-1 min-w-0 truncate text-xs text-muted dark:text-gray-400">RM 44.c — kebidanan (VK). Tiap entri = 1 laporan. Diisi Dokter.</p>
                        </div>
                    </div>
                    <x-icon-button color="gray" type="button" wire:click="closeModal" class="ml-auto shrink-0 shrink-0">
                        <span class="sr-only">Tutup</span>
                        <svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                        </svg>
                    </x-icon-button>
                    @if ($this->diForm())
                    <div class="flex items-center gap-2">
                        @if (count($entriList) > 0)
                            <x-badge class="shrink-0 whitespace-nowrap" variant="info">{{ count($entriList) }} tersimpan</x-badge>
                        @endif
                        @if ($isFormLocked)
                            <x-badge class="shrink-0 whitespace-nowrap" variant="danger">Read Only</x-badge>
                        @endif
                    </div>
                </div>
            </div>

            {{-- DISPLAY PASIEN — paling atas, mengikuti pola EMR --}}
            <div class="px-4 pt-2">
                <livewire:pages::transaksi.ri.display-pasien-ri.display-pasien-ri :riHdrNo="$riHdrNo"
                    wire:key="laporan-persalinan-display-pasien-{{ $riHdrNo }}" />
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
                            Sedang melanjutkan entri <strong>{{ $editingKey }}</strong> — <strong>Simpan Perubahan</strong> menyimpan ke entri ini. Setelah tersimpan, Anda kembali ke daftar entri.
                        </div>
                    @endif

                    {{-- ── FORM ENTRI ── --}}
                    <fieldset @disabled($formReadOnly) class="space-y-4">

                        {{-- 1. Jenis Partus --}}
                        <x-border-form title="1. Jenis Partus">
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div>
                                    <x-input-label value="Jenis Partus" />
                                    <x-select-input wire:model="newForm.jenisPartus" class="w-full mt-1">
                                        <option value="">—</option>
                                        <option value="Partus Spontan">Partus Spontan</option>
                                        <option value="Partus Buatan">Partus Buatan</option>
                                    </x-select-input>
                                </div>
                                <div>
                                    <x-input-label value="Indikasi" />
                                    <x-text-input wire:model="newForm.indikasi" class="w-full mt-1" placeholder="Indikasi tindakan" />
                                </div>
                            </div>
                        </x-border-form>

                        {{-- 2. Bayi — tabel entri berulang (kelahiran kembar / gemelli) --}}
                        <x-border-form title="2. Bayi">
                            <div class="space-y-3">
                                <div class="overflow-x-auto bg-canvas border rounded-2xl border-hairline dark:border-gray-700">
                                    <table class="ds-table min-w-[1900px]">
                                        <thead>
                                            <tr>
                                                <th class="ds-c w-10">No</th>
                                                <th class="w-44">Lahir</th>
                                                <th class="w-32">JK</th>
                                                <th class="w-28">Keadaan</th>
                                                <th class="w-24">BB (gr)</th>
                                                <th class="w-24">PB (cm)</th>
                                                <th class="w-28">APGAR</th>
                                                <th class="w-28">Resusitasi</th>
                                                <th class="w-16">BT</th>
                                                <th class="w-16">BP</th>
                                                <th class="w-16">FO</th>
                                                <th class="w-16">MO</th>
                                                <th class="w-16">OB</th>
                                                <th class="w-32">Caput Suks.</th>
                                                <th class="w-32">Cephal Hemat.</th>
                                                <th class="w-28">Atresia Ani</th>
                                                <th class="w-40">Lain-lain</th>
                                                <th class="w-36">Petugas</th>
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
                                                            <x-text-input wire:model="bayiLahir" placeholder="dd/mm/yyyy HH:mm:ss" :error="$errors->has('bayiLahir')" class="w-full px-2" />
                                                            <x-now-button wire:click="setNowBayiLahir" class="!p-2 shrink-0" />
                                                        </div>
                                                        <x-input-error :messages="$errors->get('bayiLahir')" class="mt-1" />
                                                    </td>
                                                    <td>
                                                        <x-select-input wire:model="bayiJenisKelamin" :error="$errors->has('bayiJenisKelamin')" class="w-full px-1">
                                                            <option value="">—</option>
                                                            <option value="Laki-laki">Laki-laki</option>
                                                            <option value="Perempuan">Perempuan</option>
                                                        </x-select-input>
                                                    </td>
                                                    <td>
                                                        <x-select-input wire:model="bayiKeadaan" :error="$errors->has('bayiKeadaan')" class="w-full px-1">
                                                            <option value="">—</option>
                                                            <option value="Hidup">Hidup</option>
                                                            <option value="Mati">Mati</option>
                                                        </x-select-input>
                                                    </td>
                                                    <td><x-text-input type="number" wire:model="bayiBb" wire:keydown.enter.prevent="tambahBayi" :error="$errors->has('bayiBb')" class="w-full px-1" /></td>
                                                    <td><x-text-input type="number" wire:model="bayiPb" wire:keydown.enter.prevent="tambahBayi" :error="$errors->has('bayiPb')" class="w-full px-1" /></td>
                                                    <td><x-text-input wire:model="bayiApgar" wire:keydown.enter.prevent="tambahBayi" :error="$errors->has('bayiApgar')" class="w-full px-1" placeholder="7-8-9" /></td>
                                                    <td>
                                                        <x-select-input wire:model="bayiResusitasi" :error="$errors->has('bayiResusitasi')" class="w-full px-1">
                                                            <option value="">—</option>
                                                            <option value="Ya">Ya</option>
                                                            <option value="Tidak">Tidak</option>
                                                        </x-select-input>
                                                    </td>
                                                    <td><x-text-input type="number" step="0.1" wire:model="bayiUkKepalaBt" wire:keydown.enter.prevent="tambahBayi" :error="$errors->has('bayiUkKepalaBt')" class="w-full px-1" /></td>
                                                    <td><x-text-input type="number" step="0.1" wire:model="bayiUkKepalaBp" wire:keydown.enter.prevent="tambahBayi" :error="$errors->has('bayiUkKepalaBp')" class="w-full px-1" /></td>
                                                    <td><x-text-input type="number" step="0.1" wire:model="bayiUkKepalaFo" wire:keydown.enter.prevent="tambahBayi" :error="$errors->has('bayiUkKepalaFo')" class="w-full px-1" /></td>
                                                    <td><x-text-input type="number" step="0.1" wire:model="bayiUkKepalaMo" wire:keydown.enter.prevent="tambahBayi" :error="$errors->has('bayiUkKepalaMo')" class="w-full px-1" /></td>
                                                    <td><x-text-input type="number" step="0.1" wire:model="bayiUkKepalaOb" wire:keydown.enter.prevent="tambahBayi" :error="$errors->has('bayiUkKepalaOb')" class="w-full px-1" /></td>
                                                    <td><x-text-input wire:model="bayiCaputSuksedanium" wire:keydown.enter.prevent="tambahBayi" :error="$errors->has('bayiCaputSuksedanium')" class="w-full px-1" /></td>
                                                    <td><x-text-input wire:model="bayiCephalHematoma" wire:keydown.enter.prevent="tambahBayi" :error="$errors->has('bayiCephalHematoma')" class="w-full px-1" /></td>
                                                    <td><x-text-input wire:model="bayiAtresiaAni" wire:keydown.enter.prevent="tambahBayi" :error="$errors->has('bayiAtresiaAni')" class="w-full px-1" /></td>
                                                    <td><x-text-input wire:model="bayiLain" wire:keydown.enter.prevent="tambahBayi" :error="$errors->has('bayiLain')" class="w-full px-2" /></td>
                                                    <td class="ds-td-meta">{{ auth()->user()->myuser_name ?? '-' }}</td>
                                                    <td class="ds-c">
                                                        <x-primary-button type="button" wire:click="tambahBayi" wire:loading.attr="disabled" wire:target="tambahBayi" class="justify-center gap-1 w-full px-2 py-1.5 text-sm">
                                                            <span wire:loading.remove wire:target="tambahBayi" class="flex items-center gap-1">
                                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" /></svg>
                                                                Tambah
                                                            </span>
                                                            <span wire:loading wire:target="tambahBayi"><x-loading class="w-4 h-4" /></span>
                                                        </x-primary-button>
                                                    </td>
                                                </tr>
                                            @endif

                                            @forelse ($newForm['bayi'] ?? [] as $nomor => $baris)
                                                <tr wire:key="laporan-persalinan-bayi-{{ $nomor }}">
                                                    <td class="ds-c ds-td-meta">{{ $nomor + 1 }}</td>
                                                    <td class="ds-td-strong">{{ ($baris['lahir'] ?? '') ?: '-' }}</td>
                                                    <td>{{ ($baris['jenisKelamin'] ?? '') ?: '-' }}</td>
                                                    <td>{{ ($baris['keadaan'] ?? '') ?: '-' }}</td>
                                                    <td>{{ ($baris['bb'] ?? '') ?: '-' }}</td>
                                                    <td>{{ ($baris['pb'] ?? '') ?: '-' }}</td>
                                                    <td>{{ ($baris['apgar'] ?? '') ?: '-' }}</td>
                                                    <td>{{ ($baris['resusitasi'] ?? '') ?: '-' }}</td>
                                                    <td>{{ ($baris['ukKepalaBt'] ?? '') ?: '-' }}</td>
                                                    <td>{{ ($baris['ukKepalaBp'] ?? '') ?: '-' }}</td>
                                                    <td>{{ ($baris['ukKepalaFo'] ?? '') ?: '-' }}</td>
                                                    <td>{{ ($baris['ukKepalaMo'] ?? '') ?: '-' }}</td>
                                                    <td>{{ ($baris['ukKepalaOb'] ?? '') ?: '-' }}</td>
                                                    <td>{{ ($baris['caputSuksedanium'] ?? '') ?: '-' }}</td>
                                                    <td>{{ ($baris['cephalHematoma'] ?? '') ?: '-' }}</td>
                                                    <td>{{ ($baris['atresiaAni'] ?? '') ?: '-' }}</td>
                                                    <td>{{ ($baris['lain'] ?? '') ?: '-' }}</td>
                                                    <td class="ds-td-meta">{{ ($baris['petugas'] ?? '') ?: '-' }}</td>
                                                    <td class="ds-c">
                                                        @if (!$formReadOnly)
                                                            <x-confirm-button variant="danger-soft" :action="'hapusBayi(' . $nomor . ')'"
                                                                title="Hapus Bayi" :message="'Yakin hapus data bayi ke-' . ($nomor + 1) . ' dari laporan ini?'"
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
                                                    <td colspan="19" class="ds-c italic text-muted-soft">Belum ada data bayi. Untuk kelahiran kembar, tambahkan satu baris per bayi.</td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                                <p class="text-xs text-muted-soft">Ukuran kepala (cm): <strong>BT</strong> Bitemporalis, <strong>BP</strong> Biparietalis, <strong>FO</strong> Fronto-Occipitalis, <strong>MO</strong> Mento-Occipitalis, <strong>OB</strong> Sub-Occipito-Bregmatika.</p>
                                @if (!$formReadOnly)
                                    <p class="text-xs text-muted-soft">Isi baris paling atas lalu klik <strong>Tambah</strong> (atau tekan Enter). Untuk kelahiran kembar, tambahkan satu baris per bayi. Petugas penambah baris ikut tercatat.</p>
                                @endif
                            </div>
                        </x-border-form>

                        {{-- 3. Plasenta --}}
                        <x-border-form title="3. Plasenta">
                            <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-6">
                                <div class="sm:col-span-2">
                                    <x-input-label value="Lahir — Tgl / Jam" />
                                    <div class="flex gap-1 mt-1">
                                        <x-text-input wire:model="newForm.plasentaLahirTgl" class="w-full" placeholder="dd/mm/yyyy HH:mm:ss" />
                                        <x-now-button wire:click="setNow('plasentaLahirTgl')" :disabled="$formReadOnly" />
                                    </div>
                                </div>
                                <div>
                                    <x-input-label value="Cara Lahir" />
                                    <x-select-input wire:model="newForm.plasentaCara" class="w-full mt-1">
                                        <option value="">—</option>
                                        <option value="Spontan">Spontan</option>
                                        <option value="Manual">Manual</option>
                                    </x-select-input>
                                </div>
                                <div>
                                    <x-input-label value="Jenis" />
                                    <x-select-input wire:model="newForm.plasentaJenis" class="w-full mt-1">
                                        <option value="">—</option>
                                        <option value="Lengkap">Lengkap</option>
                                        <option value="Tidak Lengkap">Tidak Lengkap</option>
                                    </x-select-input>
                                </div>
                                <div><x-input-label value="Berat (gr)" /><x-text-input type="number" wire:model="newForm.plasentaBerat" class="w-full mt-1" /></div>
                                <div><x-input-label value="Diameter (cm)" /><x-text-input type="number" wire:model="newForm.plasentaDiameter" class="w-full mt-1" /></div>
                            </div>
                        </x-border-form>

                        {{-- 4. Tali Pusat --}}
                        <x-border-form title="4. Tali Pusat">
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div><x-input-label value="Insersi" /><x-text-input wire:model="newForm.taliPusatInsersi" class="w-full mt-1" placeholder="Sentral / Marginal / Velamentosa" /></div>
                                <div><x-input-label value="Panjang (cm)" /><x-text-input type="number" wire:model="newForm.taliPusatPanjang" class="w-full mt-1" /></div>
                            </div>
                        </x-border-form>

                        {{-- 5. Selaput Janin --}}
                        <x-border-form title="5. Selaput Janin">
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                                <div>
                                    <x-input-label value="Keadaan" />
                                    <x-select-input wire:model="newForm.selaputKeadaan" class="w-full mt-1">
                                        <option value="">—</option>
                                        <option value="Lengkap">Lengkap</option>
                                        <option value="Tidak Lengkap">Tidak Lengkap</option>
                                    </x-select-input>
                                </div>
                                <div><x-input-label value="Robekan" /><x-text-input wire:model="newForm.selaputRobekan" class="w-full mt-1" /></div>
                                <div><x-input-label value="Lain-lain" /><x-text-input wire:model="newForm.selaputLain" class="w-full mt-1" /></div>
                            </div>
                        </x-border-form>

                        {{-- 6. Perlukaan Jalan Lahir --}}
                        <x-border-form title="6. Perlukaan Jalan Lahir">
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                <div><x-input-label value="Luka Perineum" /><x-text-input wire:model="newForm.lukaPerineum" class="w-full mt-1" /></div>
                                <div>
                                    <x-input-label value="Episiotomi" />
                                    <x-select-input wire:model="newForm.episiotomi" class="w-full mt-1">
                                        <option value="">—</option>
                                        <option value="Ya">Ya</option>
                                        <option value="Tidak">Tidak</option>
                                    </x-select-input>
                                </div>
                                <div>
                                    <x-input-label value="Ruptura Perinei" />
                                    <x-select-input wire:model="newForm.rupturaPerinei" class="w-full mt-1">
                                        <option value="">—</option>
                                        <option value="Tidak">Tidak</option>
                                        <option value="Tk I">Tk I</option>
                                        <option value="Tk II">Tk II</option>
                                        <option value="Tk III">Tk III</option>
                                    </x-select-input>
                                </div>
                                <div><x-input-label value="Luka Vagina" /><x-text-input wire:model="newForm.lukaVagina" class="w-full mt-1" /></div>
                                <div><x-input-label value="Luka Serviks" /><x-text-input wire:model="newForm.lukaServiks" class="w-full mt-1" /></div>
                            </div>
                        </x-border-form>

                        {{-- 7. Kala IV --}}
                        <x-border-form title="7. Kala IV">
                            <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
                                <div><x-input-label value="Hb" /><x-text-input type="number" wire:model="newForm.kalaIvHb" class="w-full mt-1" /></div>
                                <div><x-input-label value="Suhu (°C)" /><x-text-input type="number" wire:model="newForm.kalaIvSuhu" class="w-full mt-1" /></div>
                                <div><x-input-label value="Sistolik (mmHg)" /><x-text-input type="number" wire:model="newForm.kalaIvSistolik" class="w-full mt-1" placeholder="120" /></div>
                                <div><x-input-label value="Diastolik (mmHg)" /><x-text-input type="number" wire:model="newForm.kalaIvDiastolik" class="w-full mt-1" placeholder="80" /></div>
                                <div><x-input-label value="Nadi (x/mnt)" /><x-text-input type="number" wire:model="newForm.kalaIvNadi" class="w-full mt-1" /></div>
                                <div><x-input-label value="RR (x/mnt)" /><x-text-input type="number" wire:model="newForm.kalaIvRr" class="w-full mt-1" /></div>
                                <div><x-input-label value="TFU" /><x-text-input wire:model="newForm.kalaIvTfu" class="w-full mt-1" /></div>
                                <div><x-input-label value="Kontraksi Uterus" /><x-text-input wire:model="newForm.kalaIvKontraksi" class="w-full mt-1" /></div>
                                <div><x-input-label value="Perdarahan Kala III (cc)" /><x-text-input type="number" wire:model="newForm.perdarahanKalaIii" class="w-full mt-1" /></div>
                                <div><x-input-label value="Perdarahan Kala IV (cc)" /><x-text-input type="number" wire:model="newForm.perdarahanKalaIv" class="w-full mt-1" /></div>
                            </div>
                        </x-border-form>

                        {{-- 8. IMD & Rawat Gabung (PONEK / Prognas 1) [akr] --}}
                        <x-border-form title="8. IMD, Rawat Gabung & ASI (PONEK / Prognas 1)">
                            {{-- [akr] --}}
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                                {{-- [akr] --}}
                                <div>
                                    <x-input-label value="IMD Dilakukan" />
                                    <x-select-input wire:model="newForm.imdDilakukan" class="w-full mt-1">
                                        <option value="">—</option>
                                        <option value="Ya">Ya</option>
                                        <option value="Tidak">Tidak</option>
                                    </x-select-input>
                                </div>
                                {{-- [akr] --}}
                                <div>
                                    <x-input-label value="IMD — Tgl / Jam" />
                                    <div class="flex gap-1 mt-1">
                                        <x-text-input wire:model="newForm.imdTglJam" placeholder="dd/mm/yyyy HH:mm:ss" class="w-full" />
                                        <x-now-button wire:click="setNow('imdTglJam')" :disabled="$formReadOnly" />
                                    </div>
                                </div>
                                {{-- [akr] --}}
                                <div><x-input-label value="IMD — Durasi (menit)" /><x-text-input type="number" wire:model="newForm.imdDurasiMenit" class="w-full mt-1" /></div>
                                {{-- [akr] --}}
                                <div><x-input-label value="Alasan bila IMD tidak" /><x-text-input wire:model="newForm.imdAlasanTidak" class="w-full mt-1" /></div>
                                {{-- [akr] --}}
                                <div>
                                    <x-input-label value="Rawat Gabung" />
                                    <x-select-input wire:model="newForm.rawatGabung" class="w-full mt-1">
                                        <option value="">—</option>
                                        <option value="Ya">Ya</option>
                                        <option value="Tidak">Tidak</option>
                                    </x-select-input>
                                </div>
                                {{-- [akr] --}}
                                <div>
                                    <x-input-label value="Konseling ASI" />
                                    <x-select-input wire:model="newForm.asiKonseling" class="w-full mt-1">
                                        <option value="">—</option>
                                        <option value="Ya">Ya</option>
                                        <option value="Tidak">Tidak</option>
                                    </x-select-input>
                                </div>
                                {{-- [akr] Perawatan Metode Kanguru untuk BBLR --}}
                                <div>
                                    <x-input-label value="PMK (Metode Kanguru) — BBLR" />
                                    <x-select-input wire:model="newForm.pmkDilakukan" class="w-full mt-1">
                                        <option value="">—</option>
                                        <option value="Ya">Ya</option>
                                        <option value="Tidak">Tidak</option>
                                        <option value="Tidak Perlu">Tidak Perlu</option>
                                    </x-select-input>
                                </div>
                            </div>
                        </x-border-form>

                        {{-- ══ TTD PETUGAS & KUNCI ══ --}}
                        <x-signature.ttd-petugas :ttd="$newForm['ttd']" :code="$newForm['ttdCode'] ?? ''"
                            :date="$newForm['ttdDate'] ?? ''" :locked="$formReadOnly" sign="ttdSaya" clear="hapusTtd"
                            title="Tanda Tangan Petugas"
                            nameLabel="Petugas (Dokter / Bidan)" dateLabel="Waktu TTD"
                            signLabel="TTD Petugas & Kunci" clearLabel="Batal TTD" />
                        @if (!$formReadOnly)
                            <p class="-mt-2 text-xs text-center text-muted">Menandatangani = mengunci laporan persalinan ini.</p>
                        @endif
                    </fieldset>

                    {{-- ── DAFTAR ENTRI TERSIMPAN (expandable) ── --}}
                    @endif
                    @unless ($this->diForm())
                    <x-border-form padding="p-0">
                            <div class="mb-3">
                                <span class="text-xs italic text-muted-soft">Klik baris untuk lihat detail lengkap</span>
                            </div>
                            <div class="overflow-x-auto rounded-2xl">
                                <table class="min-w-full text-sm">
                                    <thead class="sticky top-0 z-10 bg-surface-card dark:bg-gray-800">
                                        <tr class="text-xs font-semibold tracking-wide text-left text-muted uppercase dark:text-gray-300">
                                            <th class="whitespace-nowrap w-8 px-2 py-3 border-b bg-surface-card dark:bg-gray-800"></th>
                                            <th class="whitespace-nowrap px-4 py-3 border-b bg-surface-card dark:bg-gray-800">Tgl / Jam</th>
                                            <th class="whitespace-nowrap px-4 py-3 border-b bg-surface-card dark:bg-gray-800">Jenis Partus</th>
                                            <th class="whitespace-nowrap px-4 py-3 border-b bg-surface-card dark:bg-gray-800">Petugas (TTD)</th>
                                            <th class="whitespace-nowrap px-4 py-3 text-center border-b bg-surface-card dark:bg-gray-800">Status</th>
                                            <th class="whitespace-nowrap px-4 py-3 text-center border-b bg-surface-card dark:bg-gray-800">Aksi</th>
                                        </tr>
                                    </thead>
                                    @forelse (array_reverse($entriList) as $entry)
                                        @php
                                            $isFinal = $this->entryIsFinal($entry);
                                            $rowKey = $entry['createdAt'] ?? '';
                                            $daftarBayi = $this->daftarBayi($entry);
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
                                                    {{ ($entry['jenisPartus'] ?? '') ?: '-' }}
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
                                                        <x-secondary-button type="button" wire:click="cetak('{{ $rowKey }}')" wire:loading.attr="disabled" wire:target="cetak('{{ $rowKey }}')" class="gap-1.5" title="Cetak laporan ini">
                                                            <span wire:loading.remove wire:target="cetak('{{ $rowKey }}')" class="flex items-center gap-1.5">
                                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                                                                </svg>
                                                                Cetak
                                                            </span>
                                                            <span wire:loading wire:target="cetak('{{ $rowKey }}')" class="flex items-center gap-1.5"><x-loading class="w-5 h-5" /> Mencetak...</span>
                                                        </x-secondary-button>
                                                        </div>
                                                        @if (!$isFormLocked)
                                                            <div class="flex items-center justify-center gap-2">
                                                            @if ($isFinal)
                                                                @can('dokumen.bukaKunci')
                                                                    <x-confirm-button action="bukaKunci('{{ $rowKey }}')"
                                                                        title="Buka Kunci Laporan Tindakan Persalinan"
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
                                                            <x-outline-button type="button" wire:click.prevent="hapus('{{ $rowKey }}')" wire:confirm="Yakin hapus laporan persalinan ini?"
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
                                                <td colspan="6" class="px-4 py-4 bg-surface-soft/60 dark:bg-gray-950/30">
                                                    <dl class="grid grid-cols-1 gap-x-8 gap-y-3 md:grid-cols-2">
                                                        <div>
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Jenis Partus</dt>
                                                            <dd class="mt-0.5 text-ink dark:text-gray-200">{{ ($entry['jenisPartus'] ?? '') ?: '-' }}</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Indikasi</dt>
                                                            <dd class="mt-0.5 text-ink dark:text-gray-200">{{ ($entry['indikasi'] ?? '') ?: '-' }}</dd>
                                                        </div>
                                                        <div class="md:col-span-2">
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Bayi ({{ count($daftarBayi) }})</dt>
                                                            <dd class="mt-1 space-y-2">
                                                                @forelse ($daftarBayi as $nomor => $baris)
                                                                    <div class="p-2 text-sm border rounded-lg border-hairline dark:border-gray-700">
                                                                        <div class="font-medium text-ink dark:text-gray-200">
                                                                            Bayi {{ $nomor + 1 }} — {{ ($baris['lahir'] ?? '') ?: '-' }} ·
                                                                            {{ ($baris['jenisKelamin'] ?? '') ?: '-' }} ·
                                                                            {{ ($baris['keadaan'] ?? '') ?: '-' }}
                                                                        </div>
                                                                        <div class="text-muted dark:text-gray-400">
                                                                            BB {{ ($baris['bb'] ?? '') ?: '-' }} gr / PB {{ ($baris['pb'] ?? '') ?: '-' }} cm ·
                                                                            APGAR {{ ($baris['apgar'] ?? '') ?: '-' }} ·
                                                                            Resusitasi {{ ($baris['resusitasi'] ?? '') ?: '-' }}
                                                                        </div>
                                                                        <div class="text-muted dark:text-gray-400">
                                                                            Ukuran kepala: {{ $this->ringkasUkuranKepala($baris) ?: '-' }}
                                                                        </div>
                                                                        <div class="text-muted dark:text-gray-400">
                                                                            Caput Suksedanium: {{ ($baris['caputSuksedanium'] ?? '') ?: '-' }} ·
                                                                            Cephal Hematoma: {{ ($baris['cephalHematoma'] ?? '') ?: '-' }} ·
                                                                            Atresia Ani: {{ ($baris['atresiaAni'] ?? '') ?: '-' }} ·
                                                                            Lain-lain: {{ ($baris['lain'] ?? '') ?: '-' }}
                                                                        </div>
                                                                        <div class="text-muted dark:text-gray-400">
                                                                            Petugas: {{ ($baris['petugas'] ?? '') ?: '-' }}
                                                                        </div>
                                                                    </div>
                                                                @empty
                                                                    <span class="italic text-muted-soft">Belum ada data bayi.</span>
                                                                @endforelse
                                                            </dd>
                                                        </div>
                                                        <div>
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Plasenta Lahir — Tgl / Jam</dt>
                                                            <dd class="mt-0.5 text-ink dark:text-gray-200">{{ ($entry['plasentaLahirTgl'] ?? '') ?: '-' }}</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Plasenta — Cara / Jenis</dt>
                                                            <dd class="mt-0.5 text-ink dark:text-gray-200">{{ ($entry['plasentaCara'] ?? '') ?: '-' }} / {{ ($entry['plasentaJenis'] ?? '') ?: '-' }}</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Plasenta — Berat / Diameter</dt>
                                                            <dd class="mt-0.5 text-ink dark:text-gray-200">{{ ($entry['plasentaBerat'] ?? '') ?: '-' }} gr / {{ ($entry['plasentaDiameter'] ?? '') ?: '-' }} cm</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Tali Pusat — Insersi / Panjang</dt>
                                                            <dd class="mt-0.5 text-ink dark:text-gray-200">{{ ($entry['taliPusatInsersi'] ?? '') ?: '-' }} / {{ ($entry['taliPusatPanjang'] ?? '') ?: '-' }} cm</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Selaput Janin — Keadaan</dt>
                                                            <dd class="mt-0.5 text-ink dark:text-gray-200">{{ ($entry['selaputKeadaan'] ?? '') ?: '-' }}</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Selaput Janin — Robekan</dt>
                                                            <dd class="mt-0.5 text-ink dark:text-gray-200">{{ ($entry['selaputRobekan'] ?? '') ?: '-' }}</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Selaput Janin — Lain-lain</dt>
                                                            <dd class="mt-0.5 text-ink dark:text-gray-200">{{ ($entry['selaputLain'] ?? '') ?: '-' }}</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Luka Perineum</dt>
                                                            <dd class="mt-0.5 text-ink dark:text-gray-200">{{ ($entry['lukaPerineum'] ?? '') ?: '-' }}</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Episiotomi</dt>
                                                            <dd class="mt-0.5 text-ink dark:text-gray-200">{{ ($entry['episiotomi'] ?? '') ?: '-' }}</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Ruptura Perinei</dt>
                                                            <dd class="mt-0.5 text-ink dark:text-gray-200">{{ ($entry['rupturaPerinei'] ?? '') ?: '-' }}</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Luka Vagina / Serviks</dt>
                                                            <dd class="mt-0.5 text-ink dark:text-gray-200">{{ ($entry['lukaVagina'] ?? '') ?: '-' }} / {{ ($entry['lukaServiks'] ?? '') ?: '-' }}</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Kala IV — Hb / Suhu</dt>
                                                            <dd class="mt-0.5 text-ink dark:text-gray-200">{{ ($entry['kalaIvHb'] ?? '') ?: '-' }} / {{ ($entry['kalaIvSuhu'] ?? '') ?: '-' }}</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Kala IV — TD / Nadi / RR</dt>
                                                            <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $this->tdKalaIv($entry) }} / {{ ($entry['kalaIvNadi'] ?? '') ?: '-' }} / {{ ($entry['kalaIvRr'] ?? '') ?: '-' }}</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Kala IV — TFU / Kontraksi</dt>
                                                            <dd class="mt-0.5 text-ink dark:text-gray-200">{{ ($entry['kalaIvTfu'] ?? '') ?: '-' }} / {{ ($entry['kalaIvKontraksi'] ?? '') ?: '-' }}</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Perdarahan Kala III / IV (cc)</dt>
                                                            <dd class="mt-0.5 text-ink dark:text-gray-200">{{ ($entry['perdarahanKalaIii'] ?? '') ?: '-' }} / {{ ($entry['perdarahanKalaIv'] ?? '') ?: '-' }}</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">IMD Dilakukan [akr]</dt>
                                                            <dd class="mt-0.5 text-ink dark:text-gray-200">{{ ($entry['imdDilakukan'] ?? '') ?: '-' }}</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">IMD — Tgl / Jam / Durasi [akr]</dt>
                                                            <dd class="mt-0.5 text-ink dark:text-gray-200">{{ ($entry['imdTglJam'] ?? '') ?: '-' }} / {{ ($entry['imdDurasiMenit'] ?? '') ?: '-' }} mnt</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Alasan bila IMD tidak [akr]</dt>
                                                            <dd class="mt-0.5 text-ink dark:text-gray-200">{{ ($entry['imdAlasanTidak'] ?? '') ?: '-' }}</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Rawat Gabung [akr]</dt>
                                                            <dd class="mt-0.5 text-ink dark:text-gray-200">{{ ($entry['rawatGabung'] ?? '') ?: '-' }}</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Konseling ASI [akr]</dt>
                                                            <dd class="mt-0.5 text-ink dark:text-gray-200">{{ ($entry['asiKonseling'] ?? '') ?: '-' }}</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">PMK (Metode Kanguru) [akr]</dt>
                                                            <dd class="mt-0.5 text-ink dark:text-gray-200">{{ ($entry['pmkDilakukan'] ?? '') ?: '-' }}</dd>
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
                                                </td>
                                            </tr>
                                        </tbody>
                                    @empty
                                        <tbody>
                                            <tr>
                                                <td colspan="6" class="px-6 py-12">
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
