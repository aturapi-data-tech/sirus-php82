<?php

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;
use App\Support\CakupanLoincLab;
use App\Support\KategoriNilaiRujukanLab;
use App\Http\Traits\Concerns\WithRenderVersioningTrait;
use App\Http\Traits\SATUSEHAT\LoincTrait;

new class extends Component {
    use WithRenderVersioningTrait, LoincTrait;

    public string $formMode = 'create';
    public ?array $hasilCekLoinc = null;

    /**
     * Baris kategori untuk Jenis Nilai Rujukan Kategori (lowhigh_status 'K'), mis. TUBEX:
     * [['nilaiMin' => '0', 'nilaiMax' => '2', 'label' => 'NEGATIF', 'status' => 'N', 'kritis' => false], ...].
     * Disimpan ke LBMST_CLABITEM_KATEGORIS; lihat App\Support\KategoriNilaiRujukanLab.
     */
    public array $kategoriList = [];

    /**
     * Kode Pemeriksaan dibuat otomatis dari nama selama true (hanya saat Tambah). Mengetik kode sendiri
     * mematikannya; tombol Generate menyalakannya lagi.
     */
    public bool $kodeOtomatis = true;

    /**
     * Item ini anggota panel/paket = punya induk (clabitem_group). BUKAN is_group: di sistem is_group='Y'
     * berarti item header panel (dibuang kirim-lab SATUSEHAT), dan datanya '0' di hampir semua item.
     */
    public bool $bagianPanel = false;

    /** Teks nilai rujukan untuk cetakan (normal_m/normal_f) pada jenis Semikuantitatif; kosong = dibuat dari kategori. */
    public string $teksRujukanKategori = '';

    /** Jenis tersimpan saat form dibuka — untuk peringatan "baris kategori akan dihapus" saat jenis diganti. */
    public string $jenisAwal = '';
    public array $renderVersions = [];
    protected array $renderAreas = ['modal'];

    public array $formClabitem = [
        'clabitem_id' => '',
        'clabitem_desc' => '',
        'clab_id' => '',
        'product_id' => '',
        'is_group' => '',
        'clabitem_group' => '',
        'price' => '0',
        'dosage' => '0',
        'unit_desc' => '',
        'item_seq' => '',
        'item_code' => '',
        'normal_m' => '',
        'normal_f' => '',
        'lowhigh_status' => '',
        'hidden_status' => '',
        'low_limit_m' => '',
        'high_limit_m' => '',
        'low_limit_f' => '',
        'high_limit_f' => '',
        'low_limit_k' => '',
        'high_limit_k' => '',
        'critical_low_m' => '',
        'critical_high_m' => '',
        'critical_low_f' => '',
        'critical_high_f' => '',
        'critical_low_k' => '',
        'critical_high_k' => '',
        'unit_convert' => '',
        'loinc_code' => '',
        'loinc_display' => '',
        'nilai_kritis' => 'N', // Y=alert nilai kritis aktif, N=tidak
        'status' => '',
    ];

    /* --- Composite PK lama (untuk edit) — wajib public agar persist antar Livewire request.
         Saat klik Simpan, server butuh nilai PK ASLI untuk WHERE clause UPDATE.
         Private properties di-reset tiap request, sehingga UPDATE kena 0 row. --- */
    public string $oldClabitemId = '';
    public string $oldClabId = '';
    public string $oldProductId = '';

    public function mount(): void
    {
        $this->registerAreas(['modal']);
    }

    // =========================================================
    // CLABITEM
    // =========================================================

    #[On('master.laborat.openCreateClabitem')]
    public function openCreateClabitem(string $clabId): void
    {
        $this->resetAll();
        $this->formMode = 'create';
        $this->formClabitem['clab_id'] = $clabId;
        $this->kodeOtomatis = true;
        $this->incrementVersion('modal');
        $this->dispatch('open-modal', name: 'master-laborat-clabitem');
        // Kode mengikuti nama, jadi isian pertama yang diketik adalah Nama Pemeriksaan.
        $this->dispatch('focus-clabitem-desc');
    }

    #[On('master.laborat.openEditClabitem')]
    public function openEditClabitem(string $clabitemId, string $clabId, string $productId): void
    {
        $row = DB::table('lbmst_clabitems')->where('clabitem_id', $clabitemId)->where('clab_id', $clabId)->where('product_id', $productId)->first();

        if (!$row) {
            return;
        }

        $this->resetAll();
        $this->formMode = 'edit';
        $this->kodeOtomatis = false;
        $this->oldClabitemId = $clabitemId;
        $this->oldClabId = $clabId;
        $this->oldProductId = $productId;

        $this->formClabitem = [
            'clabitem_id' => (string) $row->clabitem_id,
            'clabitem_desc' => (string) ($row->clabitem_desc ?? ''),
            'clab_id' => (string) $row->clab_id,
            'product_id' => (string) $row->product_id,
            'is_group' => (string) ($row->is_group ?? ''),
            'clabitem_group' => (string) ($row->clabitem_group ?? ''),
            'price' => (string) ($row->price ?? '0'),
            'dosage' => (string) ($row->dosage ?? '0'),
            'unit_desc' => (string) ($row->unit_desc ?? ''),
            'item_seq' => (string) ($row->item_seq ?? ''),
            'item_code' => (string) ($row->item_code ?? ''),
            'normal_m' => (string) ($row->normal_m ?? ''),
            'normal_f' => (string) ($row->normal_f ?? ''),
            'lowhigh_status' => (string) ($row->lowhigh_status ?? ''),
            'hidden_status' => (string) ($row->hidden_status ?? ''),
            'low_limit_m' => (string) ($row->low_limit_m ?? ''),
            'high_limit_m' => (string) ($row->high_limit_m ?? ''),
            'low_limit_f' => (string) ($row->low_limit_f ?? ''),
            'high_limit_f' => (string) ($row->high_limit_f ?? ''),
            'low_limit_k' => (string) ($row->low_limit_k ?? ''),
            'high_limit_k' => (string) ($row->high_limit_k ?? ''),
            'critical_low_m' => (string) ($row->critical_low_m ?? ''),
            'critical_high_m' => (string) ($row->critical_high_m ?? ''),
            'critical_low_f' => (string) ($row->critical_low_f ?? ''),
            'critical_high_f' => (string) ($row->critical_high_f ?? ''),
            'critical_low_k' => (string) ($row->critical_low_k ?? ''),
            'critical_high_k' => (string) ($row->critical_high_k ?? ''),
            'unit_convert' => (string) ($row->unit_convert ?? ''),
            'loinc_code' => (string) ($row->loinc_code ?? ''),
            'loinc_display' => (string) ($row->loinc_display ?? ''),
            'nilai_kritis' => (string) ($row->nilai_kritis ?? 'N'),
            'status' => (string) ($row->status ?? ''),
        ];

        $this->bagianPanel = trim((string) ($row->clabitem_group ?? '')) !== '';
        $this->jenisAwal = $this->formClabitem['lowhigh_status'];
        if ($this->jenisAwal === KategoriNilaiRujukanLab::JENIS) {
            KategoriNilaiRujukanLab::lupakan($clabitemId);
            $this->kategoriList = array_map(fn(array $kategori) => [
                'nilaiMin' => $kategori['nilaiMin'] === null ? '' : (string) (0 + $kategori['nilaiMin']),
                'nilaiMax' => $kategori['nilaiMax'] === null ? '' : (string) (0 + $kategori['nilaiMax']),
                'nilaiTeks' => $kategori['nilaiTeks'] ?? '',
                'label' => $kategori['label'],
                'status' => $kategori['status'],
                'kritis' => $kategori['kritis'],
            ], KategoriNilaiRujukanLab::untukItem($clabitemId));
            $this->teksRujukanKategori = (string) ($row->normal_m ?? '');
        }

        $this->incrementVersion('modal');
        $this->dispatch('open-modal', name: 'master-laborat-clabitem');
        $this->dispatch('focus-clabitem-desc');
    }

    /* =========================================================
     * KODE PEMERIKSAAN OTOMATIS
     * Pola kode lama (198 dari 228 item): 2 karakter awal nama + nomor urut 5 digit, mis. TU00211
     * (TUBEX), S.00022 (S.TYPHY O). Nomor = terbesar di pola itu + 1, lalu dicek sampai tak terpakai.
     * ========================================================= */
    public function updatedFormClabitemClabitemDesc(): void
    {
        if ($this->formMode === 'create' && $this->kodeOtomatis) {
            $this->formClabitem['clabitem_id'] = $this->buatKodePemeriksaan();
        }
    }

    public function updatedFormClabitemClabitemId(): void
    {
        // Diketik sendiri → jangan ditimpa lagi saat nama berubah.
        $this->kodeOtomatis = false;
        $this->formClabitem['clabitem_id'] = mb_strtoupper(trim($this->formClabitem['clabitem_id']));
    }

    public function generateKodePemeriksaan(): void
    {
        if ($this->formMode !== 'create') {
            return;
        }
        $this->kodeOtomatis = true;
        $this->formClabitem['clabitem_id'] = $this->buatKodePemeriksaan();
        $this->resetValidation('formClabitem.clabitem_id');
    }

    private function buatKodePemeriksaan(): string
    {
        // 2 karakter awal nama (huruf/angka/titik), tanpa awalan "*", "-", spasi; kurang → diisi X.
        $nama = mb_strtoupper(trim((string) ($this->formClabitem['clabitem_desc'] ?? '')));
        $awalan = substr(preg_replace('/[^A-Z0-9.]/', '', ltrim($nama, " *-")), 0, 2);
        $awalan = str_pad($awalan, 2, 'X');

        $terbesar = (int) DB::table('lbmst_clabitems')
            ->whereRaw("REGEXP_LIKE(clabitem_id, '^..[0-9]{5}$')")
            ->max(DB::raw('TO_NUMBER(SUBSTR(clabitem_id, 3, 5))'));

        for ($nomor = $terbesar + 1; $nomor < $terbesar + 1000; $nomor++) {
            $kode = $awalan . str_pad((string) $nomor, 5, '0', STR_PAD_LEFT);
            if (!DB::table('lbmst_clabitems')->where('clabitem_id', $kode)->exists()) {
                return $kode;
            }
        }

        return $awalan . str_pad((string) ($terbesar + 1), 5, '0', STR_PAD_LEFT);
    }

    /* =========================================================
     * JENIS NILAI RUJUKAN KATEGORI
     * ========================================================= */
    public function updatedFormClabitemLowhighStatus(string $jenis): void
    {
        // Pindah ke Kategori dengan daftar kosong → siapkan satu baris supaya langsung bisa diisi.
        if ($jenis === KategoriNilaiRujukanLab::JENIS && $this->kategoriList === []) {
            $this->tambahKategori();
        }
    }

    public function tambahKategori(): void
    {
        $this->kategoriList[] = ['nilaiTeks' => '', 'nilaiMin' => '', 'nilaiMax' => '', 'label' => '', 'status' => 'N', 'kritis' => false];
    }

    public function hapusKategori(int $index): void
    {
        unset($this->kategoriList[$index]);
        $this->kategoriList = array_values($this->kategoriList);
        $this->resetValidation();
    }

    /** Kategori bentuk numerik (untuk teks normal & simpan). Baris kosong total dilewati. */
    private function kategoriTerisi(): array
    {
        $daftar = [];
        foreach ($this->kategoriList as $kategori) {
            $teks = mb_strtoupper(trim((string) ($kategori['nilaiTeks'] ?? '')));
            $min = trim((string) ($kategori['nilaiMin'] ?? ''));
            $max = trim((string) ($kategori['nilaiMax'] ?? ''));
            $label = mb_strtoupper(trim((string) ($kategori['label'] ?? '')));
            if ($teks === '' && $min === '' && $max === '' && $label === '') {
                continue;
            }
            $daftar[] = [
                'nilaiTeks' => $teks,
                'nilaiMin' => $teks === '' && is_numeric($min) ? (float) $min : null,
                'nilaiMax' => $teks === '' && is_numeric($max) ? (float) $max : null,
                'label' => $label,
                'status' => in_array($kategori['status'] ?? '', ['N', 'B', 'A'], true) ? $kategori['status'] : 'N',
                'kritis' => (bool) ($kategori['kritis'] ?? false),
            ];
        }

        return $daftar;
    }

    /** Validasi baris kategori; error ditaruh per baris (kategoriList.{i}.{kolom}). */
    private function kategoriValid(array $kategoriSimpan): bool
    {
        $this->resetValidation('kategoriList');
        $valid = true;

        if ($kategoriSimpan === []) {
            $this->addError('kategoriList', 'Isi minimal satu kategori.');
            return false;
        }

        foreach ($this->kategoriList as $index => $kategori) {
            $teks = trim((string) ($kategori['nilaiTeks'] ?? ''));
            $min = trim((string) ($kategori['nilaiMin'] ?? ''));
            $max = trim((string) ($kategori['nilaiMax'] ?? ''));
            $label = trim((string) ($kategori['label'] ?? ''));
            if ($teks === '' && $min === '' && $max === '' && $label === '') {
                continue;
            }
            if ($teks !== '' && ($min !== '' || $max !== '')) {
                $this->addError("kategoriList.{$index}.nilaiTeks", 'Isi Teks Hasil ATAU Min/Max, bukan keduanya.');
                $valid = false;
                continue;
            }
            if (mb_strlen($teks) > 100) {
                $this->addError("kategoriList.{$index}.nilaiTeks", 'Teks Hasil maksimal 100 karakter.');
                $valid = false;
            }
            if ($label === '' || mb_strlen($label) > 50) {
                $this->addError("kategoriList.{$index}.label", 'Label wajib diisi, maks. 50 karakter.');
                $valid = false;
            }
            foreach (['nilaiMin' => $min, 'nilaiMax' => $max] as $kolom => $nilai) {
                if ($nilai !== '' && !is_numeric($nilai)) {
                    $this->addError("kategoriList.{$index}.{$kolom}", 'Harus angka.');
                    $valid = false;
                }
            }
            if ($teks === '' && $min === '' && $max === '') {
                $this->addError("kategoriList.{$index}.nilaiTeks", 'Isi Teks Hasil, atau Min/Max untuk hasil angka.');
                $valid = false;
            } elseif (is_numeric($min) && is_numeric($max) && (float) $min > (float) $max) {
                $this->addError("kategoriList.{$index}.nilaiMax", 'Max tidak boleh lebih kecil dari Min.');
                $valid = false;
            }
        }

        if ($valid && mb_strlen($this->teksRujukanUntukSimpan($kategoriSimpan)) > 100) {
            $this->addError('teksRujukanKategori', 'Nilai rujukan di cetakan maksimal 100 karakter. Persingkat teksnya.');
            $valid = false;
        }

        return $valid;
    }

    /** Teks rujukan yang disimpan: isian manual bila ada, selain itu dibuat dari kategori (ASCII, maks 100). */
    private function teksRujukanUntukSimpan(array $kategoriSimpan): string
    {
        $manual = trim($this->teksRujukanKategori);

        return $manual !== '' ? mb_strtoupper($manual) : KategoriNilaiRujukanLab::teksNormal($kategoriSimpan);
    }

    public function isiTeksRujukanDariKategori(): void
    {
        $this->teksRujukanKategori = KategoriNilaiRujukanLab::teksNormal($this->kategoriTerisi());
        $this->resetValidation('teksRujukanKategori');
    }

    /** Pratinjau teks nilai rujukan yang akan ditulis ke normal_m/normal_f. */
    public function teksNormalKategori(): string
    {
        return KategoriNilaiRujukanLab::teksNormal($this->kategoriTerisi());
    }

    #[On('master.laborat.deleteClabitem')]
    public function deleteClabitem(string $clabitemId, string $clabId, string $productId): void
    {
        try {
            $deleted = DB::table('lbmst_clabitems')->where('clabitem_id', $clabitemId)->where('clab_id', $clabId)->where('product_id', $productId)->delete();

            if ($deleted === 0) {
                $this->dispatch('toast', type: 'error', message: 'Data pemeriksaan tidak ditemukan.');
                return;
            }

            try {
                DB::table('lbmst_clabitem_kategoris')->where('clabitem_id', $clabitemId)->delete();
            } catch (QueryException) {
                // Tabel kategori belum dibuat di database ini: tidak ada yang perlu dihapus.
            }
            KategoriNilaiRujukanLab::lupakan($clabitemId);

            $this->dispatch('toast', type: 'success', message: 'Pemeriksaan berhasil dihapus.');
            CakupanLoincLab::lupakan();
            $this->dispatch('master.laborat.saved', entity: 'clabitem');
        } catch (QueryException $e) {
            if (str_contains($e->getMessage(), 'ORA-02292')) {
                $this->dispatch('toast', type: 'error', message: 'Pemeriksaan tidak bisa dihapus karena masih dipakai di data lain.');
                return;
            }
            throw $e;
        }
    }

    /** Tab form tempat sebuah isian berada — untuk membuka tab yang memuat error saat Simpan gagal. */
    private function tabUntukIsian(string $kunci): string
    {
        return match (true) {
            str_starts_with($kunci, 'kategoriList') => 'nilai-rujukan',
            (bool) preg_match('/\.(unit_desc|lowhigh_status|normal_[mf]|low_limit_|high_limit_|critical_|unit_convert|nilai_kritis)/', $kunci) => 'nilai-rujukan',
            (bool) preg_match('/\.(price|status|product_id|dosage)$/', $kunci) => 'tarif',
            (bool) preg_match('/\.(item_code|loinc_)/', $kunci) => 'mapping',
            default => 'pemeriksaan',
        };
    }

    private function bukaTabError(array $kunciError): void
    {
        if ($kunciError === []) {
            return;
        }
        $this->dispatch('master-lab-buka-tab', tab: $this->tabUntukIsian((string) $kunciError[0]));
    }

    public function save(): void
    {
        try {
        $this->validate(
            [
                'formClabitem.clabitem_id' => 'required|string|max:10',
                'formClabitem.clabitem_desc' => 'required|string|max:100',
                'formClabitem.clab_id' => 'required|string|max:5',
                'formClabitem.product_id' => 'nullable|string|max:10|exists:immst_products,product_id',
                'formClabitem.is_group' => 'nullable|string|max:10',
                'formClabitem.clabitem_group' => 'nullable|string|max:10',
                'formClabitem.price' => 'nullable|integer|min:0',
                'formClabitem.dosage' => 'nullable|integer|min:0',
                'formClabitem.unit_desc' => 'nullable|string|max:50',
                'formClabitem.item_seq' => 'nullable|numeric|min:0',
                'formClabitem.item_code' => 'nullable|string|max:25',
                'formClabitem.normal_m' => 'nullable|string|max:100',
                'formClabitem.normal_f' => 'nullable|string|max:100',
                'formClabitem.lowhigh_status' => 'nullable|string|max:3',
                'formClabitem.hidden_status' => 'nullable|string|max:3',
                'formClabitem.low_limit_m' => 'nullable|numeric',
                'formClabitem.high_limit_m' => 'nullable|numeric',
                'formClabitem.low_limit_f' => 'nullable|numeric',
                'formClabitem.high_limit_f' => 'nullable|numeric',
                'formClabitem.low_limit_k' => 'nullable|numeric',
                'formClabitem.high_limit_k' => 'nullable|numeric',
                'formClabitem.critical_low_m' => 'nullable|numeric',
                'formClabitem.critical_high_m' => 'nullable|numeric',
                'formClabitem.critical_low_f' => 'nullable|numeric',
                'formClabitem.critical_high_f' => 'nullable|numeric',
                'formClabitem.critical_low_k' => 'nullable|numeric',
                'formClabitem.critical_high_k' => 'nullable|numeric',
                'formClabitem.unit_convert' => 'nullable|numeric',
                'formClabitem.loinc_code' => 'nullable|string|max:20',
                'formClabitem.loinc_display' => 'nullable|string|max:200',
                'formClabitem.nilai_kritis' => 'nullable|in:Y,N',
                'formClabitem.status' => 'nullable|string|max:10',
            ],
            [
                'formClabitem.product_id.exists' => 'Reagen / BHP tidak ditemukan di master obat & barang.',
                'required' => ':attribute wajib diisi.',
                'integer' => ':attribute harus bilangan bulat.',
                'numeric' => ':attribute harus berupa angka.',
                'min' => ':attribute minimal :min.',
                'max' => ':attribute maksimal :max karakter.',
                'in' => 'Pilihan :attribute tidak valid.',
            ],
            [
                'formClabitem.clabitem_id' => 'Kode Pemeriksaan',
                'formClabitem.clabitem_desc' => 'Nama Pemeriksaan (Parameter)',
                'formClabitem.clab_id' => 'Kategori Lab',
                'formClabitem.product_id' => 'Reagen / BHP',
                'formClabitem.price' => 'Tarif Pemeriksaan',
                'formClabitem.unit_desc' => 'Satuan Hasil (UCUM)',
                'formClabitem.dosage' => 'Kebutuhan Reagen per Tes',
                'formClabitem.item_seq' => 'Urutan di Hasil',
                'formClabitem.item_code' => 'Kode Parameter Alat',
                'formClabitem.normal_m' => 'Nilai Rujukan Laki-laki',
                'formClabitem.normal_f' => 'Nilai Rujukan Perempuan',
                'formClabitem.low_limit_m' => 'Batas Bawah Laki-laki',
                'formClabitem.high_limit_m' => 'Batas Atas Laki-laki',
                'formClabitem.low_limit_f' => 'Batas Bawah Perempuan',
                'formClabitem.high_limit_f' => 'Batas Atas Perempuan',
                'formClabitem.low_limit_k' => 'Batas Bawah Anak',
                'formClabitem.high_limit_k' => 'Batas Atas Anak',
                'formClabitem.critical_low_m' => 'Batas Kritis Bawah Laki-laki',
                'formClabitem.critical_high_m' => 'Batas Kritis Atas Laki-laki',
                'formClabitem.critical_low_f' => 'Batas Kritis Bawah Perempuan',
                'formClabitem.critical_high_f' => 'Batas Kritis Atas Perempuan',
                'formClabitem.critical_low_k' => 'Batas Kritis Bawah Anak',
                'formClabitem.critical_high_k' => 'Batas Kritis Atas Anak',
                'formClabitem.unit_convert' => 'Faktor Konversi Satuan Alat',
                'formClabitem.loinc_code' => 'Kode LOINC',
                'formClabitem.loinc_display' => 'Nama LOINC',
            ],
        );
        } catch (ValidationException $e) {
            $this->bukaTabError(array_keys($e->errors()));
            $this->dispatch('toast', type: 'error', message: $e->validator->errors()->first() . ' Periksa isian yang ditandai merah.');
            throw $e;
        }

        $jenisKategori = ($this->formClabitem['lowhigh_status'] ?? '') === KategoriNilaiRujukanLab::JENIS;
        $kategoriSimpan = [];
        if ($jenisKategori) {
            $kategoriSimpan = $this->kategoriTerisi();
            if (!$this->kategoriValid($kategoriSimpan)) {
                $this->dispatch('master-lab-buka-tab', tab: 'nilai-rujukan');
                $this->dispatch('toast', type: 'error', message: 'Periksa baris Kategori Hasil yang ditandai merah.');
                return;
            }
        }

        // product_id = produk/reagen dari immst_products; bukan bagian PK (PK hanya clabitem_id).
        // Kosong disimpan '0' seperti mayoritas item lama (kolom NOT NULL, '0' = tanpa produk).
        $payload = [
            'clabitem_desc' => $this->formClabitem['clabitem_desc'],
            'product_id' => trim($this->formClabitem['product_id'] ?? '') ?: '0',
            'is_group' => $this->formClabitem['is_group'] ?: null,
            'clabitem_group' => $this->bagianPanel ? ($this->formClabitem['clabitem_group'] ?: null) : null,
            'price' => (int) ($this->formClabitem['price'] ?: 0),
            'dosage' => (int) ($this->formClabitem['dosage'] ?: 0),
            'unit_desc' => $this->formClabitem['unit_desc'] ?: null,
            'item_seq' => $this->formClabitem['item_seq'] !== '' ? (int) $this->formClabitem['item_seq'] : null,
            'item_code' => $this->formClabitem['item_code'] ?: null,
            'normal_m' => $this->formClabitem['normal_m'] ?: null,
            'normal_f' => $this->formClabitem['normal_f'] ?: null,
            'lowhigh_status' => $jenisKategori ? KategoriNilaiRujukanLab::JENIS : (in_array($this->formClabitem['lowhigh_status'], ['Y', '1']) ? 'Y' : null),
            // Nilai baku pembaca: 'Y' = sembunyi, 'N'/kosong = tampil (data lama form ini sempat menulis '1').
            'hidden_status' => in_array($this->formClabitem['hidden_status'], ['Y', '1'], true) ? 'Y' : 'N',
            'low_limit_m' => $this->formClabitem['low_limit_m'] !== '' ? (float) $this->formClabitem['low_limit_m'] : null,
            'high_limit_m' => $this->formClabitem['high_limit_m'] !== '' ? (float) $this->formClabitem['high_limit_m'] : null,
            'low_limit_f' => $this->formClabitem['low_limit_f'] !== '' ? (float) $this->formClabitem['low_limit_f'] : null,
            'high_limit_f' => $this->formClabitem['high_limit_f'] !== '' ? (float) $this->formClabitem['high_limit_f'] : null,
            'low_limit_k' => $this->formClabitem['low_limit_k'] !== '' ? (float) $this->formClabitem['low_limit_k'] : null,
            'high_limit_k' => $this->formClabitem['high_limit_k'] !== '' ? (float) $this->formClabitem['high_limit_k'] : null,
            'critical_low_m' => $this->formClabitem['critical_low_m'] !== '' ? (float) $this->formClabitem['critical_low_m'] : null,
            'critical_high_m' => $this->formClabitem['critical_high_m'] !== '' ? (float) $this->formClabitem['critical_high_m'] : null,
            'critical_low_f' => $this->formClabitem['critical_low_f'] !== '' ? (float) $this->formClabitem['critical_low_f'] : null,
            'critical_high_f' => $this->formClabitem['critical_high_f'] !== '' ? (float) $this->formClabitem['critical_high_f'] : null,
            'critical_low_k' => $this->formClabitem['critical_low_k'] !== '' ? (float) $this->formClabitem['critical_low_k'] : null,
            'critical_high_k' => $this->formClabitem['critical_high_k'] !== '' ? (float) $this->formClabitem['critical_high_k'] : null,
            'unit_convert' => $this->formClabitem['unit_convert'] !== '' ? (float) $this->formClabitem['unit_convert'] : null,
            'loinc_code' => $this->formClabitem['loinc_code'] ?: null,
            'loinc_display' => $this->formClabitem['loinc_display'] ?: null,
            'nilai_kritis' => ($this->formClabitem['nilai_kritis'] ?? 'N') === 'Y' ? 'Y' : 'N',
            'status' => $this->formClabitem['status'] ?: null,
        ];

        if ($jenisKategori) {
            // Jenis Kategori: rentang & kritis ada di tabel kategori. Teks rujukan diisi otomatis supaya
            // cetakan, aplikasi 6i, dan kode yang belum mengenal 'K' tetap menampilkan keterangan benar.
            $teksNormal = $this->teksRujukanUntukSimpan($kategoriSimpan);
            $payload = [
                ...$payload,
                'normal_m' => $teksNormal,
                'normal_f' => $teksNormal,
                'low_limit_m' => null, 'high_limit_m' => null,
                'low_limit_f' => null, 'high_limit_f' => null,
                'low_limit_k' => null, 'high_limit_k' => null,
                'critical_low_m' => null, 'critical_high_m' => null,
                'critical_low_f' => null, 'critical_high_f' => null,
                'critical_low_k' => null, 'critical_high_k' => null,
                'nilai_kritis' => collect($kategoriSimpan)->contains(fn($kategori) => $kategori['kritis']) ? 'Y' : 'N',
            ];
        }

        if ($this->formMode === 'create' && DB::table('lbmst_clabitems')->where('clabitem_id', $this->formClabitem['clabitem_id'])->exists()) {
            if (!$this->kodeOtomatis) {
                $this->addError('formClabitem.clabitem_id', 'Kode Pemeriksaan sudah dipakai.');
                $this->dispatch('master-lab-buka-tab', tab: 'pemeriksaan');
                return;
            }
            // Kode otomatis keburu dipakai pengguna lain sejak form diisi → buat yang baru.
            $this->formClabitem['clabitem_id'] = $this->buatKodePemeriksaan();
        }

        $clabitemId = $this->formMode === 'create' ? $this->formClabitem['clabitem_id'] : $this->oldClabitemId;

        try {
            DB::transaction(function () use ($payload, $jenisKategori, $kategoriSimpan, $clabitemId) {
                if ($this->formMode === 'create') {
                    DB::table('lbmst_clabitems')->insert([
                        'clabitem_id' => $this->formClabitem['clabitem_id'],
                        'clab_id' => $this->formClabitem['clab_id'],
                        ...$payload,
                    ]);
                } else {
                    DB::table('lbmst_clabitems')
                        ->where('clabitem_id', $this->oldClabitemId)
                        ->where('clab_id', $this->oldClabId)
                        ->where('product_id', $this->oldProductId)
                        ->update($payload);
                }

                // Baris kategori hanya disentuh bila item ini Kategori sekarang ATAU sebelumnya, supaya
                // item lama tetap bisa disimpan di environment yang belum menjalankan DDL tabel kategori.
                if ($jenisKategori || $this->jenisAwal === KategoriNilaiRujukanLab::JENIS) {
                    DB::table('lbmst_clabitem_kategoris')->where('clabitem_id', $clabitemId)->delete();
                    foreach (array_values($kategoriSimpan) as $urutan => $kategori) {
                        DB::table('lbmst_clabitem_kategoris')->insert([
                            'clabitem_id' => $clabitemId,
                            'urut' => $urutan + 1,
                            'nilai_teks' => $kategori['nilaiTeks'] !== '' ? $kategori['nilaiTeks'] : null,
                            'nilai_min' => $kategori['nilaiMin'],
                            'nilai_max' => $kategori['nilaiMax'],
                            'label' => $kategori['label'],
                            'status' => $kategori['status'],
                            'kritis' => $kategori['kritis'] ? 'Y' : 'N',
                        ]);
                    }
                }
            });
        } catch (QueryException $e) {
            if (str_contains($e->getMessage(), 'ORA-00942')) {
                $this->dispatch('toast', type: 'error', message: 'Tabel kategori nilai rujukan belum dibuat di database ini. Jalankan docs/ddl-lab-kategori-nilai-rujukan.sql dulu.');
                return;
            }
            throw $e;
        }

        KategoriNilaiRujukanLab::lupakan($clabitemId);
        $this->dispatch('toast', type: 'success', message: 'Data pemeriksaan berhasil disimpan.');
        $this->closeModal();
        CakupanLoincLab::lupakan();
        $this->dispatch('master.laborat.saved', entity: 'clabitem');
    }

    /* =========================================================
     * LOV — Grup Induk
     * ========================================================= */
    #[On('lov.selected.clabitemGroup')]
    public function onClabitemGroupSelected(string $target, ?array $payload): void
    {
        $this->formClabitem['clabitem_group'] = $payload['clabitem_id'] ?? '';
    }

    /* =========================================================
     * LOV — Produk/Reagen (opsional)
     * ========================================================= */
    #[On('lov.selected.clabitemProduct')]
    public function onClabitemProductSelected(string $target, ?array $payload): void
    {
        $this->formClabitem['product_id'] = $payload['product_id'] ?? '';
        $this->resetValidation('formClabitem.product_id');
    }

    // lov-product tidak mengirim event saat Ubah/kosongkan, jadi "tanpa produk" lewat tombol sendiri
    public function kosongkanProduk(): void
    {
        $this->formClabitem['product_id'] = '';
        $this->resetValidation('formClabitem.product_id');
        $this->incrementVersion('modal');
    }

    /* =========================================================
     * LOV — LOINC (Satu Sehat)
     * ========================================================= */
    #[On('lov.selected.loincLab')]
    public function onLoincLabSelected(string $target, ?array $payload): void
    {
        $this->formClabitem['loinc_code'] = $payload['loinc_code'] ?? '';
        $this->formClabitem['loinc_display'] = $payload['display'] ?? '';
        $this->hasilCekLoinc = null;
    }

    #[On('lov.cleared.loincLab')]
    public function onLoincLabCleared(string $target): void
    {
        $this->formClabitem['loinc_code'] = '';
        $this->formClabitem['loinc_display'] = '';
        $this->hasilCekLoinc = null;
    }

    /**
     * Cek kode ke LOINC (tx.fhir.org) dan tampilkan artinya untuk dicocokkan dengan pemeriksaan ini.
     *
     * SATUSEHAT tidak punya API terminologi, dan rsmst_loinc_codes bukan bukti untuk lab: isinya seed
     * manual ditambah hasil cari LOV. Jadi yang bisa dipastikan di sini hanya kodenya ada dan artinya apa.
     */
    public function cekLoincKeSatuSehat(): void
    {
        $kode = trim($this->formClabitem['loinc_code'] ?? '');

        if ($kode === '') {
            $this->dispatch('toast', type: 'info', message: 'Isi kode LOINC dulu sebelum dicek.');
            return;
        }

        $this->initializeLoincFhir();
        $this->hasilCekLoinc = ['kode' => $kode, ...$this->periksaKodeLoinc($kode)];
    }

    public function closeModal(): void
    {
        $this->resetAll();
        $this->dispatch('close-modal', name: 'master-laborat-clabitem');
        $this->resetVersion();
    }

    private function resetAll(): void
    {
        $this->formClabitem = [
            'clabitem_id' => '',
            'clabitem_desc' => '',
            'clab_id' => '',
            'product_id' => '',
            'is_group' => '',
            'clabitem_group' => '',
            'price' => '0',
            'dosage' => '0',
            'unit_desc' => '',
            'item_seq' => '',
            'item_code' => '',
            'normal_m' => '',
            'normal_f' => '',
            'lowhigh_status' => '',
            'hidden_status' => '',
            'low_limit_m' => '',
            'high_limit_m' => '',
            'low_limit_f' => '',
            'high_limit_f' => '',
            'low_limit_k' => '',
            'high_limit_k' => '',
            'critical_low_m' => '',
            'critical_high_m' => '',
            'critical_low_f' => '',
            'critical_high_f' => '',
            'critical_low_k' => '',
            'critical_high_k' => '',
            'unit_convert' => '',
            'loinc_code' => '',
            'loinc_display' => '',
            'nilai_kritis' => 'N',
            'status' => '',
        ];
        $this->oldClabitemId = '';
        $this->oldClabId = '';
        $this->oldProductId = '';
        $this->hasilCekLoinc = null;
        $this->kategoriList = [];
        $this->teksRujukanKategori = '';
        $this->jenisAwal = '';
        $this->kodeOtomatis = true;
        $this->bagianPanel = false;
        $this->resetValidation();
    }
};
?>

<div>
    <x-modal name="master-laborat-clabitem" size="full" height="full" focusable>
        <x-dirty-modal-content
            name="master-laborat-clabitem"
            event="master.laborat.saved"
            label="Item Pemeriksaan Lab"
            wrapperClass="flex flex-col min-h-full"
            :wireKey="$this->renderKey('modal', [$formMode])">

            {{-- HEADER --}}
            <x-modul-dokumen.header
                :judul="$formMode === 'edit' ? 'Ubah Pemeriksaan Lab' : 'Tambah Pemeriksaan Lab'"
                ikon="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
                tutupAlpine="tryClose()">
                Lengkapi data pemeriksaan laboratorium lalu klik Simpan.
                <x-slot:badge>
                    <x-badge :variant="$formMode === 'edit' ? 'warning' : 'success'">
                        {{ $formMode === 'edit' ? 'Mode: Edit' : 'Mode: Tambah' }}
                    </x-badge>
                </x-slot:badge>
            </x-modul-dokumen.header>

            {{-- BODY --}}
            {{-- BODY — bertab (pola Master Pasien: Alpine, instan tanpa reload). Simpan yang gagal validasi
                 membuka tab berisi error pertama lewat event master-lab-buka-tab. --}}
            <div class="flex-1 px-4 py-4 bg-surface-soft dark:bg-gray-950/20" x-enter-chain
                x-data="{ activeTab: 'pemeriksaan' }"
                x-on:master-lab-buka-tab.window="activeTab = $event.detail.tab"
                x-on:focus-clabitem-id.window="activeTab = 'pemeriksaan'; $nextTick(() => setTimeout(() => $refs.inputClabitemId?.focus(), 150))"
                x-on:focus-clabitem-desc.window="activeTab = 'pemeriksaan'; $nextTick(() => setTimeout(() => $refs.inputClabitemDesc?.focus(), 150))">

                <x-tabs variant="underline" class="mb-4">
                    <x-tab active-expr="activeTab === 'pemeriksaan'" x-on:click="activeTab = 'pemeriksaan'">Pemeriksaan</x-tab>
                    <x-tab active-expr="activeTab === 'nilai-rujukan'" x-on:click="activeTab = 'nilai-rujukan'">Nilai Rujukan</x-tab>
                    <x-tab active-expr="activeTab === 'tarif'" x-on:click="activeTab = 'tarif'">Tarif &amp; Reagen</x-tab>
                    <x-tab active-expr="activeTab === 'mapping'" x-on:click="activeTab = 'mapping'">Mapping Alat &amp; SATUSEHAT</x-tab>
                </x-tabs>

                {{-- ═══ SECTION 1: Pemeriksaan ═══ --}}
                <div x-show="activeTab === 'pemeriksaan'" x-cloak x-transition.opacity.duration.200ms>
                <x-border-form title="Pemeriksaan">
                    <div class="space-y-4">

                        {{-- Baris 1: Kode Pemeriksaan + Nama Pemeriksaan --}}
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-6 items-start">
                            <div class="sm:col-span-2">
                                <x-input-label value="Kode Pemeriksaan" />
                                <div class="flex gap-1 mt-1">
                                    <x-text-input wire:model.blur="formClabitem.clabitem_id" x-ref="inputClabitemId"
                                        :disabled="$formMode === 'edit'" maxlength="10" :error="$errors->has('formClabitem.clabitem_id')" class="w-full uppercase"
                                        x-on:keydown.enter.prevent="$refs.inputClabitemDesc?.focus()" />
                                    @if ($formMode === 'create')
                                        <x-outline-button type="button" wire:click="generateKodePemeriksaan" wire:loading.attr="disabled"
                                            wire:target="generateKodePemeriksaan" class="!px-3 shrink-0" title="Buat kode unik dari nama pemeriksaan">
                                            <x-ikon nama="proses" />
                                            Generate
                                        </x-outline-button>
                                    @endif
                                </div>
                                <x-input-error :messages="$errors->get('formClabitem.clabitem_id')" class="mt-1" />
                                <p class="mt-1 text-xs text-gray-600 dark:text-gray-400">
                                    @if ($formMode === 'create')
                                        {{ $kodeOtomatis ? 'Otomatis dari nama (2 huruf + nomor urut), boleh diubah.' : 'Diisi manual. Tekan Generate untuk kode otomatis.' }}
                                    @else
                                        Kode unik pemeriksaan, tidak bisa diubah.
                                    @endif
                                </p>
                            </div>
                            <div class="sm:col-span-4">
                                <x-input-label value="Nama Pemeriksaan (Parameter)" />
                                <x-text-input wire:model.live="formClabitem.clabitem_desc" x-ref="inputClabitemDesc"
                                    maxlength="100" :error="$errors->has('formClabitem.clabitem_desc')" class="w-full mt-1"
                                    x-on:keydown.enter.prevent="$refs.inputItemSeq?.focus()" />
                                <x-input-error :messages="$errors->get('formClabitem.clabitem_desc')" class="mt-1" />
                                <p class="mt-1 text-xs text-gray-600 dark:text-gray-400">Nama yang tampil di hasil lab
                                    pasien</p>
                            </div>
                        </div>

                        {{-- Baris 2: posisi di hasil lab — Panel/Paket, Urutan, Tampil --}}
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-6 items-start">
                            <div>
                                <x-input-label value="Bagian dari Panel / Paket" />
                                <div class="mt-2">
                                    <x-toggle wire:model.live="bagianPanel" :trueValue="true" :falseValue="false"
                                        :label="$bagianPanel ? 'Ya' : 'Tidak'" />
                                </div>
                                <p class="mt-1 text-xs text-gray-600 dark:text-gray-400">Jika Ya, pilih paket induk. Item
                                    otomatis ikut saat paket induk dipilih di transaksi</p>
                            </div>
                            @if ($bagianPanel)
                                <div class="sm:col-span-3">
                                    <livewire:lov.clabitem-group.lov-clabitem-group target="clabitemGroup"
                                        label="Panel / Paket Induk" placeholder="Ketik nama panel / paket pemeriksaan..."
                                        :clabId="$formClabitem['clab_id']" :initialClabitemId="$formClabitem['clabitem_group'] ?: null" :disabled="false"
                                        wire:key="lov-clabgrp-{{ $formClabitem['clab_id'] }}-{{ $renderVersions['modal'] ?? 0 }}" />
                                    <p class="mt-1 text-xs text-gray-600 dark:text-gray-400">Paket induk yang menaungi
                                        pemeriksaan ini</p>
                                </div>
                            @endif
                            <div>
                                <x-input-label value="Urutan di Hasil" />
                                <x-text-input-number wire:model="formClabitem.item_seq" x-ref="inputItemSeq"
                                    :error="$errors->has('formClabitem.item_seq')" class="mt-1"
                                    x-on:keydown.enter.prevent="$refs.inputLowHighStatus?.focus()" />
                                <x-input-error :messages="$errors->get('formClabitem.item_seq')" class="mt-1" />
                                <p class="mt-1 text-xs text-gray-600 dark:text-gray-400">Posisi baris di hasil lab (layar &amp; cetakan)</p>
                            </div>
                            <div>
                                @php $disembunyikan = in_array($formClabitem['hidden_status'] ?? '', ['Y', '1'], true); @endphp
                                <x-input-label value="Tampil di Hasil" />
                                <div class="mt-2">
                                    <x-toggle :current="$disembunyikan ? 'N' : 'Y'" trueValue="Y" falseValue="N"
                                        wireClick="$set('formClabitem.hidden_status', '{{ $disembunyikan ? 'N' : 'Y' }}')"
                                        :label="$disembunyikan ? 'Disembunyikan' : 'Tampil'" />
                                </div>
                                <p class="mt-1 text-xs text-gray-600 dark:text-gray-400">Mati = tidak tampil di hasil &amp; cetakan</p>
                            </div>
                        </div>

                    </div>
                </x-border-form>

                </div>

                {{-- ═══ SECTION 2: Nilai Rujukan ═══ --}}
                <div x-show="activeTab === 'nilai-rujukan'" x-cloak x-transition.opacity.duration.200ms>
                <x-border-form title="Satuan & Nilai Rujukan">
                    <div class="space-y-4">

                        {{-- Jenis Hasil | Nilai Kritis — 1 baris. Satuan Hasil ada di baris nilai rujukan tiap jenis (di bawah). --}}
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <x-input-label value="Jenis Hasil & Nilai Rujukan" />
                                <x-select-input wire:model.live="formClabitem.lowhigh_status" x-ref="inputLowHighStatus"
                                    class="w-full mt-1"
                                    x-on:keydown.enter.prevent="$refs.inputNormalM?.focus() || $refs.inputLowM?.focus()">
                                    <option value="">Kualitatif (teks, mis. NEGATIF / POSITIF)</option>
                                    <option value="Y">Kuantitatif (rentang angka bawah - atas)</option>
                                    <option value="K">Semikuantitatif (skor / kategori berlabel)</option>
                                </x-select-input>
                                <p class="mt-1 text-xs text-gray-600 dark:text-gray-400">Kuantitatif: hasil angka, sistem
                                    menandai Tinggi/Rendah. Kualitatif: hasil teks harus sama persis dengan nilai rujukan.
                                    Semikuantitatif: skor berlabel seperti TUBEX (0-2 NEGATIF, 3 BORDERLINE, &gt;= 4 POSITIF).</p>
                                @if ($jenisAwal === 'K' && $formClabitem['lowhigh_status'] !== 'K')
                                    <p class="mt-1 text-xs font-medium text-error-deep dark:text-red-300">Baris kategori item ini akan dihapus saat disimpan.</p>
                                @endif
                            </div>

                            {{-- Nilai Kritis — sebaris dengan Jenis Hasil. Semikuantitatif: kritis per baris kategori,
                                 toggle umum disembunyikan. --}}
                            <div @class(['hidden' => $formClabitem['lowhigh_status'] === 'K'])>
                                <x-input-label value="Nilai Kritis (Critical Value)" />
                                <div class="mt-2">
                                    <x-toggle wire:model.live="formClabitem.nilai_kritis" trueValue="Y" falseValue="N"
                                        label="Tandai hasil kritis (wajib segera dilaporkan ke dokter)" />
                                </div>
                                <p class="mt-1 text-xs text-gray-600 dark:text-gray-400">
                                    Saat aktif, hasil yang melewati ambang batas kritis ditandai
                                    <strong class="text-rose-600 dark:text-rose-400">NILAI KRITIS</strong>
                                    (contoh: Gula Darah Sewaktu &lt;40 mg/dL, Kalium &gt;6 mEq/L).
                                </p>
                            </div>
                        </div>

                        @if ($formClabitem['lowhigh_status'] === 'Y' || $formClabitem['lowhigh_status'] === '1')
                            {{-- Batas Nilai Rujukan — satu x-border-form per kelompok, pola bingkai EMR RJ (Tanda Vital) --}}
                            <div class="space-y-2">
                                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                                <x-border-form :title="__('Laki-laki')" :align="__('start')" :bgcolor="__('bg-surface-soft')" padding="p-3">
                                    <div class="grid grid-cols-2 gap-3">
                                        <div>
                                            <x-input-label value="Batas Bawah" />
                                            <x-text-input wire:model.live="formClabitem.low_limit_m" x-ref="inputLowM"
                                                type="number" step="0.01" class="w-full mt-1"
                                                x-on:keydown.enter.prevent="$refs.inputHighM?.focus()" />
                                        </div>
                                        <div>
                                            <x-input-label value="Batas Atas" />
                                            <x-text-input wire:model.live="formClabitem.high_limit_m" x-ref="inputHighM"
                                                type="number" step="0.01" class="w-full mt-1"
                                                x-on:keydown.enter.prevent="$refs.inputLowF?.focus()" />
                                        </div>
                                    </div>
                                </x-border-form>
                                <x-border-form :title="__('Perempuan')" :align="__('start')" :bgcolor="__('bg-surface-soft')" padding="p-3">
                                    <div class="grid grid-cols-2 gap-3">
                                        <div>
                                            <x-input-label value="Batas Bawah" />
                                            <x-text-input wire:model.live="formClabitem.low_limit_f" x-ref="inputLowF"
                                                type="number" step="0.01" class="w-full mt-1"
                                                x-on:keydown.enter.prevent="$refs.inputHighF?.focus()" />
                                        </div>
                                        <div>
                                            <x-input-label value="Batas Atas" />
                                            <x-text-input wire:model.live="formClabitem.high_limit_f" x-ref="inputHighF"
                                                type="number" step="0.01" class="w-full mt-1"
                                                x-on:keydown.enter.prevent="$refs.inputLowK?.focus()" />
                                        </div>
                                    </div>
                                </x-border-form>
                                <x-border-form :title="__('Anak')" :align="__('start')" :bgcolor="__('bg-surface-soft')" padding="p-3">
                                    <div class="grid grid-cols-2 gap-3">
                                        <div>
                                            <x-input-label value="Batas Bawah" />
                                            <x-text-input wire:model.live="formClabitem.low_limit_k" x-ref="inputLowK"
                                                type="number" step="0.01" class="w-full mt-1"
                                                x-on:keydown.enter.prevent="$refs.inputHighK?.focus()" />
                                        </div>
                                        <div>
                                            <x-input-label value="Batas Atas" />
                                            <x-text-input wire:model.live="formClabitem.high_limit_k" x-ref="inputHighK"
                                                type="number" step="0.01" class="w-full mt-1"
                                                x-on:keydown.enter.prevent="$refs.inputCritLowM?.focus() || $refs.inputUnitConvert?.focus()" />
                                        </div>
                                    </div>
                                </x-border-form>
                                </div>
                                <p class="text-xs text-gray-600 dark:text-gray-400">
                                    Batas nilai rujukan: hasil di luar rentang ditandai <strong>Tinggi</strong>/<strong>Rendah</strong>.
                                    Penilaian otomatis memakai batas Laki-laki/Perempuan sesuai jenis kelamin pasien; batas Anak
                                    tersimpan tetapi belum dipakai.
                                </p>
                            </div>

                            {{-- Ambang Nilai Kritis — hanya saat toggle Nilai Kritis aktif --}}
                            @if (($formClabitem['nilai_kritis'] ?? 'N') === 'Y')
                                <div class="p-4 border rounded-xl border-rose-200 bg-rose-50/60 dark:border-rose-900/40 dark:bg-rose-900/10">
                                    <div class="flex items-center gap-2 mb-3">
                                        <span class="w-2 h-2 rounded-full bg-rose-500 animate-pulse"></span>
                                        <span class="text-sm font-semibold text-rose-700 dark:text-rose-300">Ambang Nilai Kritis</span>
                                    </div>
                                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                                        <div>
                                            <x-input-label value="Laki-laki (Batas Kritis Bawah - Atas)" />
                                            <div class="flex items-center gap-2 mt-1">
                                                <x-text-input wire:model.live="formClabitem.critical_low_m" x-ref="inputCritLowM"
                                                    type="number" step="0.01" class="w-full" placeholder="Bawah"
                                                    x-on:keydown.enter.prevent="$refs.inputCritHighM?.focus()" />
                                                <span class="text-gray-400">-</span>
                                                <x-text-input wire:model.live="formClabitem.critical_high_m" x-ref="inputCritHighM"
                                                    type="number" step="0.01" class="w-full" placeholder="Atas"
                                                    x-on:keydown.enter.prevent="$refs.inputCritLowF?.focus()" />
                                            </div>
                                            <p class="mt-1 text-xs text-gray-600 dark:text-gray-400">Hasil di bawah/atas ambang ini ditandai
                                                <strong class="text-rose-600 dark:text-rose-400">NILAI KRITIS</strong></p>
                                        </div>
                                        <div>
                                            <x-input-label value="Perempuan (Batas Kritis Bawah - Atas)" />
                                            <div class="flex items-center gap-2 mt-1">
                                                <x-text-input wire:model.live="formClabitem.critical_low_f" x-ref="inputCritLowF"
                                                    type="number" step="0.01" class="w-full" placeholder="Bawah"
                                                    x-on:keydown.enter.prevent="$refs.inputCritHighF?.focus()" />
                                                <span class="text-gray-400">-</span>
                                                <x-text-input wire:model.live="formClabitem.critical_high_f" x-ref="inputCritHighF"
                                                    type="number" step="0.01" class="w-full" placeholder="Atas"
                                                    x-on:keydown.enter.prevent="$refs.inputCritLowK?.focus()" />
                                            </div>
                                        </div>
                                        <div>
                                            <x-input-label value="Anak (Batas Kritis Bawah - Atas)" />
                                            <div class="flex items-center gap-2 mt-1">
                                                <x-text-input wire:model.live="formClabitem.critical_low_k" x-ref="inputCritLowK"
                                                    type="number" step="0.01" class="w-full" placeholder="Bawah"
                                                    x-on:keydown.enter.prevent="$refs.inputCritHighK?.focus()" />
                                                <span class="text-gray-400">-</span>
                                                <x-text-input wire:model.live="formClabitem.critical_high_k" x-ref="inputCritHighK"
                                                    type="number" step="0.01" class="w-full" placeholder="Atas"
                                                    x-on:keydown.enter.prevent="$refs.inputUnitConvert?.focus()" />
                                            </div>
                                        </div>
                                    </div>
                                    <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                                        Kosongkan jika tidak berlaku untuk kelompok tertentu. Ambang kritis biasanya lebih ekstrem dari batas rujukan
                                        (contoh: Kalium rujukan 3.5&ndash;5.5, kritis &lt;2.5 atau &gt;6.5 mEq/L).
                                    </p>
                                </div>
                            @endif

                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-4">
                                <div>
                                    <x-input-label value="Satuan Hasil (UCUM)" />
                                    <x-text-input wire:model.live="formClabitem.unit_desc" x-ref="inputUnitDesc"
                                        maxlength="50" :error="$errors->has('formClabitem.unit_desc')" class="w-full mt-1" placeholder="g/dL, mg/dL, %, 10*3/uL"
                                        x-on:keydown.enter.prevent="$refs.inputUnitConvert?.focus()" />
                                    <x-input-error :messages="$errors->get('formClabitem.unit_desc')" class="mt-1" />
                                    <p class="mt-1 text-xs text-gray-600 dark:text-gray-400">Tampil di belakang hasil, misal: 12.5 <strong>g/dL</strong></p>
                                </div>
                                <div>
                                    <x-input-label value="Faktor Konversi Satuan Alat" />
                                    <x-text-input wire:model.live="formClabitem.unit_convert" x-ref="inputUnitConvert"
                                        type="number" step="0.01" class="w-full mt-1"
                                        x-on:keydown.enter.prevent="$refs.inputPrice?.focus()" />
                                    <p class="mt-1 text-xs text-gray-600 dark:text-gray-400">Hasil dari alat dibagi
                                        nilai ini saat simpan, dikali kembali saat tampil. Contoh: HGB dari Mindray /10
                                    </p>
                                </div>
                            </div>
                        @elseif ($formClabitem['lowhigh_status'] === 'K')
                            {{-- Kategori: baris rentang berlabel (LBMST_CLABITEM_KATEGORIS) --}}
                            <div class="space-y-3">
                                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                                    <div>
                                        <x-input-label value="Satuan Hasil (UCUM)" />
                                        <x-text-input wire:model.live="formClabitem.unit_desc" x-ref="inputUnitDesc"
                                            maxlength="50" :error="$errors->has('formClabitem.unit_desc')" class="w-full mt-1" placeholder="g/dL, mg/dL, %, 10*3/uL"
                                            x-on:keydown.enter.prevent="$refs.inputPrice?.focus()" />
                                        <x-input-error :messages="$errors->get('formClabitem.unit_desc')" class="mt-1" />
                                        <p class="mt-1 text-xs text-gray-600 dark:text-gray-400">Tampil di belakang hasil, misal: 12.5 <strong>g/dL</strong></p>
                                    </div>
                                </div>
                                <div class="overflow-x-auto bg-canvas border rounded-2xl border-hairline dark:border-gray-700">
                                    <table class="ds-table ds-table-entri">
                                        <thead>
                                            <tr>
                                                <th class="ds-c w-10">No</th>
                                                <th class="w-56">Teks Hasil</th>
                                                <th class="w-24">Min</th>
                                                <th class="w-24">Max</th>
                                                <th>Label</th>
                                                <th class="w-40">Status</th>
                                                <th class="ds-c w-20">Kritis</th>
                                                <th class="ds-c w-16">Aksi</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($kategoriList as $index => $kategori)
                                                <tr wire:key="kategori-nilai-rujukan-{{ $index }}" class="align-top">
                                                    <td class="ds-c ds-td-meta">{{ $index + 1 }}</td>
                                                    <td>
                                                        <x-text-input wire:model.live.debounce.500ms="kategoriList.{{ $index }}.nilaiTeks" maxlength="100"
                                                            :error="$errors->has('kategoriList.' . $index . '.nilaiTeks')" class="w-full px-2" placeholder="mis. NEGATIF|NEG atau *"
                                                            style="text-transform:uppercase" />
                                                        <x-input-error :messages="$errors->get('kategoriList.' . $index . '.nilaiTeks')" class="mt-1" />
                                                    </td>
                                                    <td>
                                                        <x-text-input type="number" step="0.01" wire:model.live.debounce.500ms="kategoriList.{{ $index }}.nilaiMin"
                                                            :error="$errors->has('kategoriList.' . $index . '.nilaiMin')" class="w-full px-2" placeholder="kosong = -" />
                                                        <x-input-error :messages="$errors->get('kategoriList.' . $index . '.nilaiMin')" class="mt-1" />
                                                    </td>
                                                    <td>
                                                        <x-text-input type="number" step="0.01" wire:model.live.debounce.500ms="kategoriList.{{ $index }}.nilaiMax"
                                                            :error="$errors->has('kategoriList.' . $index . '.nilaiMax')" class="w-full px-2" placeholder="kosong = tak terbatas" />
                                                        <x-input-error :messages="$errors->get('kategoriList.' . $index . '.nilaiMax')" class="mt-1" />
                                                    </td>
                                                    <td>
                                                        <x-text-input wire:model.live.debounce.500ms="kategoriList.{{ $index }}.label" maxlength="50"
                                                            :error="$errors->has('kategoriList.' . $index . '.label')" class="w-full px-2" placeholder="NEGATIF / BORDERLINE / POSITIF"
                                                            style="text-transform:uppercase" />
                                                        <x-input-error :messages="$errors->get('kategoriList.' . $index . '.label')" class="mt-1" />
                                                    </td>
                                                    <td>
                                                        <x-select-input wire:model.live="kategoriList.{{ $index }}.status" class="w-full px-2">
                                                            @foreach (\App\Support\KategoriNilaiRujukanLab::STATUS_KATEGORI as $kodeStatus => $namaStatus)
                                                                <option value="{{ $kodeStatus }}">{{ $namaStatus }}</option>
                                                            @endforeach
                                                        </x-select-input>
                                                    </td>
                                                    <td class="ds-c">
                                                        <div class="flex justify-center mt-2" title="Hasil di kategori ini ditandai NILAI KRITIS">
                                                            <x-toggle wire:model.live="kategoriList.{{ $index }}.kritis" :trueValue="true" :falseValue="false" onColor="bg-error" />
                                                        </div>
                                                    </td>
                                                    <td class="ds-c">
                                                        <x-hapus-button :action="'hapusKategori(' . $index . ')'" title="Hapus Kategori"
                                                            :message="'Hapus kategori ke-' . ($index + 1) . '?'" />
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                                <x-input-error :messages="$errors->get('kategoriList')" />
                                <div class="flex flex-wrap items-center gap-2">
                                    <x-secondary-button type="button" wire:click="tambahKategori"><x-ikon nama="tambah" /> Tambah Kategori</x-secondary-button>
                                </div>
                                <div>
                                    <x-input-label value="Nilai Rujukan di Cetakan" />
                                    <div class="flex gap-1 mt-1">
                                        <x-text-input wire:model.blur="teksRujukanKategori" maxlength="100" class="w-full"
                                            :error="$errors->has('teksRujukanKategori')" :placeholder="$this->teksNormalKategori() ?: 'mis. NEGATIF'"
                                            style="text-transform:uppercase" />
                                        <x-secondary-button type="button" wire:click="isiTeksRujukanDariKategori" class="!px-3 shrink-0 whitespace-nowrap"
                                            title="Isi dari gabungan kategori"><x-ikon nama="tambah" /> Isi dari Kategori</x-secondary-button>
                                    </div>
                                    <x-input-error :messages="$errors->get('teksRujukanKategori')" class="mt-1" />
                                    <p class="mt-1 text-xs text-gray-600 dark:text-gray-400">Tampil di kolom Nilai Rujukan hasil lab (maks. 100 karakter, tanpa simbol khusus).
                                        Kosong = otomatis dari kategori: <span class="font-mono">{{ $this->teksNormalKategori() ?: '-' }}</span></p>
                                </div>
                                <p class="text-xs text-gray-600 dark:text-gray-400">
                                    Tiap baris diisi <strong>Teks Hasil</strong> ATAU <strong>Min/Max</strong>; baris dicocokkan dari atas, yang pertama cocok dipakai.
                                    <strong>Teks Hasil</strong>: beberapa pilihan dipisah <span class="font-mono">|</span>, <span class="font-mono">*</span> = apa saja
                                    (mis. <span class="font-mono">NEGATIF|NEG</span>, <span class="font-mono">+1|+2|+3</span>, <span class="font-mono">*</span> untuk "selain di atas");
                                    huruf besar-kecil, spasi, dan tanda kurung diabaikan, jadi "(+1)" sama dengan "+1".
                                    <strong>Pilihan pertama</strong> tiap Teks Hasil = penulisan baku: muncul sebagai pilihan saat input hasil, dan
                                    hasil yang cocok disimpan dengan tulisan itu (mis. <span class="font-mono">NEGATIF|NEG|-</span>: "neg" &rarr; NEGATIF).
                                    <strong>Min/Max</strong>: angka diambil dari hasil ("SKOR : 2" &rarr; 2, "1/200" &rarr; 200, "1-2" &rarr; 2), batas termasuk.
                                    Label = yang tampil di hasil; Status menentukan tanda (Normal tanpa tanda, Borderline kuning, Abnormal oranye).
                                </p>
                            </div>
                        @else
                            {{-- Kualitatif: Nilai Rujukan Laki-laki | Perempuan | Satuan Hasil — 1 baris --}}
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                                <div>
                                    <x-input-label value="Nilai Rujukan Laki-laki" />
                                    <x-text-input wire:model.live="formClabitem.normal_m" x-ref="inputNormalM"
                                        maxlength="100" class="w-full mt-1"
                                        placeholder="Contoh: 13 - 18 g/dL atau NEGATIF"
                                        x-on:keydown.enter.prevent="$refs.inputNormalF?.focus()" />
                                    <p class="mt-1 text-xs text-gray-600 dark:text-gray-400">Tampil di kolom Nilai
                                        Rujukan pada hasil lab pasien laki-laki</p>
                                </div>
                                <div>
                                    <x-input-label value="Nilai Rujukan Perempuan" />
                                    <x-text-input wire:model.live="formClabitem.normal_f" x-ref="inputNormalF"
                                        maxlength="100" class="w-full mt-1"
                                        placeholder="Contoh: 12 - 16 g/dL atau NEGATIF"
                                        x-on:keydown.enter.prevent="$refs.inputUnitDesc?.focus()" />
                                    <p class="mt-1 text-xs text-gray-600 dark:text-gray-400">Tampil di kolom Nilai
                                        Rujukan pada hasil lab pasien perempuan</p>
                                </div>
                                <div>
                                    <x-input-label value="Satuan Hasil (UCUM)" />
                                    <x-text-input wire:model.live="formClabitem.unit_desc" x-ref="inputUnitDesc"
                                        maxlength="50" :error="$errors->has('formClabitem.unit_desc')" class="w-full mt-1" placeholder="g/dL, mg/dL, %, 10*3/uL"
                                        x-on:keydown.enter.prevent="$refs.inputPrice?.focus()" />
                                    <x-input-error :messages="$errors->get('formClabitem.unit_desc')" class="mt-1" />
                                    <p class="mt-1 text-xs text-gray-600 dark:text-gray-400">Tampil di belakang hasil, misal: 12.5 <strong>g/dL</strong></p>
                                </div>
                            </div>
                        @endif

                    </div>
                </x-border-form>

                </div>

                {{-- ═══ SECTION 3: Tarif & Konfigurasi ═══ --}}
                <div x-show="activeTab === 'tarif'" x-cloak x-transition.opacity.duration.200ms>
                <x-border-form title="Tarif & Konfigurasi">
                    @php
                        $produkTerpilih = trim($formClabitem['product_id'] ?? '');
                    @endphp
                    {{-- 1 baris di layar lebar: Tarif 2 · Status 2 · Reagen/BHP 5 · Kebutuhan Reagen 3 --}}
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-12 items-start">
                        <div class="lg:col-span-2">
                            <x-input-label value="Tarif Pemeriksaan" />
                            <x-text-input-number wire:model="formClabitem.price" x-ref="inputPrice" :error="$errors->has('formClabitem.price')"
                                class="mt-1" x-on:keydown.enter.prevent="$refs.inputStatus?.focus()" />
                            <x-input-error :messages="$errors->get('formClabitem.price')" class="mt-1" />
                            <p class="mt-1 text-xs text-gray-600 dark:text-gray-400">Biaya tagihan pasien</p>
                        </div>
                        <div class="lg:col-span-2">
                            <x-input-label value="Status Pemeriksaan" />
                            <x-select-input wire:model.live="formClabitem.status" x-ref="inputStatus"
                                class="w-full mt-1" x-on:keydown.enter.prevent="$refs.inputDosage?.focus()">
                                <option value="">Aktif</option>
                                <option value="0">Nonaktif</option>
                            </x-select-input>
                            <p class="mt-1 text-xs text-gray-600 dark:text-gray-400">Nonaktif = tidak bisa dipilih saat order</p>
                        </div>
                        <div class="lg:col-span-5">
                            <livewire:lov.product.lov-product target="clabitemProduct" label="Reagen / BHP (opsional)"
                                placeholder="Ketik nama/kode produk reagen..."
                                :initialProductId="$produkTerpilih !== '' && $produkTerpilih !== '0' ? $produkTerpilih : null"
                                wire:key="lov-clabitem-product-{{ $renderVersions['modal'] ?? 0 }}" />
                            <x-input-error :messages="$errors->get('formClabitem.product_id')" class="mt-1" />
                            <div class="flex flex-wrap items-center gap-2 mt-1 text-xs text-gray-600 dark:text-gray-400">
                                <span>Tersimpan:
                                    <span class="font-semibold text-ink dark:text-gray-200">{{ $produkTerpilih === '' || $produkTerpilih === '0' ? 'tanpa produk' : $produkTerpilih }}</span></span>
                                @if ($produkTerpilih !== '' && $produkTerpilih !== '0')
                                    <button type="button" wire:click="kosongkanProduk"
                                        class="font-medium text-brand-green hover:underline dark:text-brand-lime">Kosongkan</button>
                                @endif
                            </div>
                            <p class="mt-1 text-xs text-gray-600 dark:text-gray-400">Reagen/BHP dari master obat &amp;
                                barang. Boleh dikosongkan.</p>
                        </div>
                        <div class="lg:col-span-3">
                            <x-input-label value="Kebutuhan Reagen per Tes" />
                            <x-text-input-number wire:model="formClabitem.dosage" x-ref="inputDosage"
                                :error="$errors->has('formClabitem.dosage')" class="mt-1"
                                x-on:keydown.enter.prevent="$refs.inputItemCode?.focus()" />
                            <x-input-error :messages="$errors->get('formClabitem.dosage')" class="mt-1" />
                            <p class="mt-1 text-xs text-gray-600 dark:text-gray-400">Kebutuhan reagen per
                                pemeriksaan (sesuai satuan reagen di master obat &amp; barang), untuk perhitungan stok</p>
                        </div>
                    </div>
                </x-border-form>

                </div>

                {{-- ═══ SECTION 4: Mapping Alat & SATUSEHAT (kode pemetaan, seperti KFA di Master Obat) ═══ --}}
                <div x-show="activeTab === 'mapping'" x-cloak x-transition.opacity.duration.200ms>
                <x-border-form title="Mapping Alat & SATUSEHAT">
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                                <div>
                                    <x-input-label value="Kode Parameter Alat (Analyzer)" />
                                    <x-text-input wire:model.live="formClabitem.item_code" x-ref="inputItemCode"
                                        maxlength="25" :error="$errors->has('formClabitem.item_code')" class="w-full mt-1"
                                        placeholder="HGB, WBC, RBC..."
                                        x-on:keydown.enter.prevent="$refs.inputLoincCode?.focus()" />
                                    <x-input-error :messages="$errors->get('formClabitem.item_code')" class="mt-1" />
                                    <p class="mt-1 text-xs text-gray-600 dark:text-gray-400">Kode dari alat (Mindray dll)
                                        untuk import hasil otomatis</p>
                                </div>
                                <div class="sm:col-span-2">
                                    <livewire:lov.loinc.lov-loinc target="loincLab" label="Kode LOINC (SATUSEHAT)"
                                        placeholder="Ketik nama pemeriksaan / kode LOINC..." :initialLoincCode="$formClabitem['loinc_code'] ?: null"
                                        :disabled="false" wire:key="lov-loinc-{{ $renderVersions['modal'] ?? 0 }}" />

                                    <div class="flex flex-wrap items-center gap-2 mt-2">
                                        <x-outline-button type="button" wire:click="cekLoincKeSatuSehat"
                                            :disabled="trim($formClabitem['loinc_code'] ?? '') === ''" wire:loading.attr="disabled"
                                            wire:target="cekLoincKeSatuSehat">
                                            <span wire:loading.remove wire:target="cekLoincKeSatuSehat" class="inline-flex items-center gap-2"><x-ikon nama="cari" /> Cek Kode LOINC</span>
                                            <span wire:loading wire:target="cekLoincKeSatuSehat">Mengecek...</span>
                                        </x-outline-button>
                                    </div>

                                    @if ($hasilCekLoinc)
                                        <div class="mt-2 text-xs text-ink dark:text-gray-200">
                                            @if ($hasilCekLoinc['status'] === 'ada')
                                                <x-badge variant="success">Kode ada di LOINC</x-badge>
                                                <p class="mt-1">Arti kode {{ $hasilCekLoinc['kode'] }}: <span class="font-semibold">{{ $hasilCekLoinc['display'] ?? 'tidak tersedia' }}</span></p>
                                                @if (filled($formClabitem['loinc_display']) && filled($hasilCekLoinc['display']) && mb_strtolower(trim($formClabitem['loinc_display'])) !== mb_strtolower(trim($hasilCekLoinc['display'])))
                                                    <p class="mt-1">Nama tersimpan berbeda: {{ $formClabitem['loinc_display'] }}</p>
                                                @endif
                                                <p class="mt-1 text-muted dark:text-gray-400">Pastikan artinya sama dengan pemeriksaan ini. Kode yang artinya salah tetap bisa terkirim sebagai pemeriksaan lain.</p>
                                            @elseif ($hasilCekLoinc['status'] === 'tidak_ada')
                                                <x-badge variant="danger">Kode tidak ada di LOINC</x-badge>
                                                <p class="mt-1">Kode {{ $hasilCekLoinc['kode'] }} tidak dikenal, kemungkinan besar ditolak saat kirim. Ganti dengan kode yang benar.</p>
                                            @else
                                                <x-badge variant="warning">Belum bisa dicek</x-badge>
                                                <p class="mt-1">Server tx.fhir.org tidak merespons. Coba lagi beberapa saat.</p>
                                            @endif
                                        </div>
                                    @endif

                                    <p class="mt-1 text-xs text-gray-600 dark:text-gray-400">Cek ini memastikan kodenya ada dan
                                        menampilkan artinya. Satu Sehat tidak menyediakan cek kode, jadi diterima atau tidaknya
                                        baru pasti saat kirim.</p>
                                </div>
                            </div>
                </x-border-form>
                </div>
            </div>

            {{-- FOOTER --}}
            <div
                class="sticky bottom-0 z-10 px-6 py-4 mt-auto bg-surface-soft border-t border-hairline dark:bg-gray-900 dark:border-gray-700">
                <div class="flex items-center justify-between gap-3">
                    <div class="text-xs text-muted dark:text-gray-400">
                        <kbd
                            class="px-1.5 py-0.5 text-xs font-semibold bg-surface-card border border-hairline rounded dark:bg-gray-800 dark:border-gray-600">Enter</kbd>
                        <span class="mx-0.5">untuk berpindah field</span>
                    </div>
                    <div class="flex justify-end gap-2">
                        <x-secondary-button type="button" x-on:click="tryClose()"><x-ikon nama="batal" /> Batal</x-secondary-button>
                        <x-primary-button type="button" wire:click="save" wire:loading.attr="disabled">
                            <span wire:loading.remove wire:target="save" class="inline-flex items-center gap-2"><x-ikon nama="simpan" /> Simpan</span>
                            <span wire:loading wire:target="save">Saving...</span>
                        </x-primary-button>
                    </div>
                </div>
            </div>
        </x-dirty-modal-content>
    </x-modal>
</div>
