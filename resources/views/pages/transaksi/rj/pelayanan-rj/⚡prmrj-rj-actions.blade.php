<?php

/**
 * PRMRJ — Profil Ringkas Medis Rawat Jalan (formulir RM.06).
 *
 * Dibuka dari titik tiga di Pelayanan RJ. Kunjungan yang sedang dipilih itulah
 * yang diringkas: kolom-kolom RM.06 terisi OTOMATIS dari EMR kunjungan tersebut,
 * petugas tinggal mencentang kriteria, mengisi Obat Khusus, lalu menandatangani.
 *
 * Satu baris per kunjungan; formulir RM.06 yang berisi banyak baris dirakit di
 * panel bawah dari semua baris milik pasien. Rancangan: docs/ddl-prmrj.sql.
 */

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Http\Traits\Txn\Rj\EmrRJTrait;
use App\Http\Traits\Txn\Prmrj\PrmrjTrait;
use App\Http\Traits\Master\MasterPasien\MasterPasienTrait;
use App\Http\Traits\Concerns\WithValidationToastTrait;
use App\Support\Options\PrmrjOptions;

new class extends Component {
    use EmrRJTrait, PrmrjTrait, MasterPasienTrait, WithValidationToastTrait;

    public ?int $rjNo = null;
    public ?string $regNo = null;
    public string $namaPasien = '';

    /** Tabel PRMRJ sudah dipasang di basis data? */
    public bool $siapDipakai = false;

    /** Snapshot EMR kunjungan ini — pratinjau, disalin apa adanya saat simpan. */
    public array $otomatis = [];

    /** Baris RM.06 milik pasien ini, kronologis. */
    public array $riwayat = [];
    public int $riwayatTotal = 0;

    /** true = sudah di-TTD, formulir jadi baca-saja. */
    public bool $isFormLocked = false;

    public array $form = [
        'diagnosisKompleks' => false,
        'asuhanTigaAtauLebih' => false,
        'alergiObatMdr' => false,
        // Rincian per kriteria — kunci dari PrmrjOptions, bukan teks bebas,
        // supaya redaksi label boleh diperbaiki tanpa merusak record lama.
        // PETA kunci => bool, bukan daftar kunci: x-toggle mengikat satu nilai,
        // tak bisa menunjuk elemen array seperti x-check-box. Bentuk TERSIMPAN
        // tetap daftar kunci — diubah bolak-balik di petaKunciPrmrj()/kunciTerpilihPrmrj().
        'detailDiagnosis' => [],
        'detailDiagnosisLain' => '',
        'detailAsuhan' => [],
        'detailAsuhanLain' => '',
        'detailAlergi' => '',
        'kriteriaCatatan' => '',
        'obatKhusus' => '',
        'ttdPrmrj' => '',
        'ttdPrmrjCode' => '',
        'ttdPrmrjDate' => '',
    ];

    #[On('pelayanan-rj.prmrj.open')]
    public function open(int $rjNo): void
    {
        $this->reset(['regNo', 'namaPasien', 'otomatis', 'riwayat', 'riwayatTotal']);
        $this->rjNo = $rjNo;

        $header = DB::table('rstxn_rjhdrs as h')
            ->leftJoin('rsmst_pasiens as p', 'p.reg_no', '=', 'h.reg_no')
            ->where('h.rj_no', $rjNo)
            ->select('h.reg_no', 'p.reg_name')
            ->first();

        $this->regNo = $header->reg_no ?? null;
        $this->namaPasien = (string) ($header->reg_name ?? '');

        $this->siapDipakai = $this->checkPrmrjTable();

        // Snapshot dibuat dari EMR kunjungan ini. Sekali muat, dipakai untuk
        // pratinjau DAN untuk disimpan — tak dibaca dua kali.
        $dataRJ = $this->findDataRJ($rjNo);
        $this->otomatis = blank($dataRJ) ? [] : $this->buildOtomatisPrmrj($dataRJ, $rjNo);

        $this->muatPrmrj($rjNo);

        $this->dispatch('open-modal', name: 'prmrj-rj');
    }

    /** Muat baris PRMRJ kunjungan ini + segarkan formulir RM.06 di panel bawah. */
    private function muatPrmrj(int $rjNo): void
    {
        $this->reset(['form']);
        $this->isFormLocked = false;

        [$this->riwayat, $this->riwayatTotal] = $this->findRiwayatPrmrj($this->regNo ?? '', 'RJ', $rjNo);

        [$baris, $isi] = $this->findDataPrmrj($this->regNo ?? '', 'RJ', $rjNo);

        if (! $baris) {
            // Peta tetap disiapkan walau belum ada record, supaya tiap toggle punya
            // kuncinya sendiri sejak awal — tanpa ini wire:model menunjuk kunci
            // yang belum ada dan toggle-nya tak bisa dinyalakan.
            $this->form['detailDiagnosis'] = $this->petaKunciPrmrj('diagnosis', []);
            $this->form['detailAsuhan'] = $this->petaKunciPrmrj('asuhan', []);

            return;
        }

        // SNAPSHOT TERSIMPAN MENANG atas bacaan segar dari EMR.
        //
        // open() selalu merakit ulang $otomatis dari EMR untuk kunjungan yang BELUM
        // punya PRMRJ. Kalau barisnya sudah ada, hasil rakitan itu HARUS ditimpa isi
        // tersimpan — kalau tidak, tiap kali modal dibuka ulang suntingan dokter di
        // bagian 2 hilang diam-diam dan tertimpa nilai EMR pada penyimpanan berikutnya.
        //
        // array_replace, bukan penugasan langsung: record lama bisa belum punya kunci
        // yang baru ditambahkan, dan kunci itu tetap terisi dari rakitan EMR.
        $this->otomatis = array_replace($this->otomatis, $isi['otomatis'] ?? []);

        $kriteria = $isi['kriteria'] ?? [];
        $this->form = array_replace($this->form, [
            'diagnosisKompleks' => (bool) ($kriteria['diagnosisKompleks'] ?? false),
            'asuhanTigaAtauLebih' => (bool) ($kriteria['asuhanTigaAtauLebih'] ?? false),
            'alergiObatMdr' => (bool) ($kriteria['alergiObatMdr'] ?? false),
            'detailDiagnosis' => $this->petaKunciPrmrj('diagnosis', (array) ($kriteria['detailDiagnosis'] ?? [])),
            'detailDiagnosisLain' => (string) ($kriteria['detailDiagnosisLain'] ?? ''),
            'detailAsuhan' => $this->petaKunciPrmrj('asuhan', (array) ($kriteria['detailAsuhan'] ?? [])),
            'detailAsuhanLain' => (string) ($kriteria['detailAsuhanLain'] ?? ''),
            'detailAlergi' => (string) ($kriteria['detailAlergi'] ?? ''),
            'kriteriaCatatan' => (string) ($kriteria['catatan'] ?? ''),
            'obatKhusus' => (string) ($isi['manual']['obatKhusus'] ?? ''),
            'ttdPrmrj' => (string) ($isi['ttd']['nama'] ?? ''),
            'ttdPrmrjCode' => (string) ($isi['ttd']['kode'] ?? ''),
            'ttdPrmrjDate' => (string) ($isi['ttd']['tanggal'] ?? ''),
        ]);

        $this->isFormLocked = (bool) ($isi['terkunci'] ?? false);
    }

    /**
     * Daftar kunci tersimpan → peta kunci=>bool untuk diikat x-toggle.
     * Kunci yang sudah tak ada di master diabaikan; kunci baru default mati.
     */
    private function petaKunciPrmrj(string $kelompok, array $kunciTersimpan): array
    {
        $peta = [];

        foreach (array_keys(PrmrjOptions::labels($kelompok)) as $kunci) {
            $peta[$kunci] = in_array($kunci, $kunciTersimpan, true);
        }

        return $peta;
    }

    /** Peta kunci=>bool → daftar kunci yang menyala, untuk disimpan. */
    private function kunciTerpilihPrmrj(array $peta): array
    {
        return array_values(array_keys(array_filter($peta)));
    }

    /** Minimal satu kriteria harus dicentang — itu yang membuat pasien layak PRMRJ. */
    public function rules(): array
    {
        return [
            'form.diagnosisKompleks' => ['boolean'],
            'form.asuhanTigaAtauLebih' => ['boolean'],
            'form.alergiObatMdr' => ['boolean'],
            'form.kriteriaCatatan' => ['nullable', 'string', 'max:500'],
            'form.obatKhusus' => ['nullable', 'string', 'max:500'],
            'form.ttdPrmrj' => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'form.ttdPrmrj.required' => 'Tanda tangan DPJP belum ada.',
        ];
    }

    public function validationAttributes(): array
    {
        return [
            'form.kriteriaCatatan' => 'Catatan kriteria',
            'form.obatKhusus' => 'Obat khusus',
            'form.ttdPrmrj' => 'Tanda tangan DPJP',
        ];
    }

    public function simpanDraft(): void
    {
        $this->simpan(false);
    }

    /**
     * TTD = aksi TERAKHIR yang sekaligus MENGUNCI (pola modul dokumen).
     * Stempelnya dipasang DI DALAM simpan(), sesudah formulir terbukti sah —
     * kalau dipasang di sini, validasi gagal meninggalkan TTD menempel di layar
     * padahal tak ada yang tersimpan.
     */
    public function ttdDokter(): void
    {
        $this->simpan(true);
    }

    private function simpan(bool $kunci): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'PRMRJ sudah terkunci.');

            return;
        }
        if (! $this->siapDipakai || blank($this->rjNo)) {
            return;
        }
        if (blank($this->regNo)) {
            $this->dispatch('toast', type: 'error', message: 'Nomor RM tidak ditemukan untuk kunjungan ini.');

            return;
        }

        $adaKriteria = $this->form['diagnosisKompleks']
            || $this->form['asuhanTigaAtauLebih']
            || $this->form['alergiObatMdr'];

        if (! $adaKriteria) {
            $this->dispatch('toast', type: 'error', message: 'Pilih minimal satu kriteria PRMRJ.');

            return;
        }

        // SPO menulis "3 atau lebih" untuk kriteria a & b. Ambang itu ditegakkan,
        // bukan sekadar tertulis di label — kalau tidak, toggle-nya bisa menyala
        // dengan satu butir tercentang dan pasien lolos kriteria tanpa alasan.
        // "Lainnya" ikut dihitung satu butir bila diisi.
        $jumlahDiagnosis = count($this->kunciTerpilihPrmrj($this->form['detailDiagnosis']))
            + (filled($this->form['detailDiagnosisLain']) ? 1 : 0);

        if ($this->form['diagnosisKompleks'] && $jumlahDiagnosis < PrmrjOptions::AMBANG_BUTIR) {
            $this->dispatch('toast', type: 'error', message: 'Diagnosis kompleks butuh minimal '
                . PrmrjOptions::AMBANG_BUTIR . ' diagnosis penyerta — baru ' . $jumlahDiagnosis . ' dipilih.');

            return;
        }

        $jumlahAsuhan = count($this->kunciTerpilihPrmrj($this->form['detailAsuhan']))
            + (filled($this->form['detailAsuhanLain']) ? 1 : 0);

        if ($this->form['asuhanTigaAtauLebih'] && $jumlahAsuhan < PrmrjOptions::AMBANG_BUTIR) {
            $this->dispatch('toast', type: 'error', message: 'Kriteria asuhan butuh minimal '
                . PrmrjOptions::AMBANG_BUTIR . ' asuhan — baru ' . $jumlahAsuhan . ' dipilih.');

            return;
        }

        if ($this->form['alergiObatMdr'] && blank($this->form['detailAlergi'])) {
            $this->dispatch('toast', type: 'error', message: 'Sebutkan alergi obat / multi drug resistance-nya.');

            return;
        }

        $sekarang = Carbon::now(config('app.timezone'));

        if ($kunci) {
            $this->form['ttdPrmrj'] = auth()->user()->myuser_name ?? auth()->user()->name ?? '';
            $this->form['ttdPrmrjCode'] = auth()->user()->myuser_code ?? '';
            $this->form['ttdPrmrjDate'] = $sekarang->format('d/m/Y H:i:s');

            $this->validateWithToast();
        }

        $isiJson = [
            'kriteria' => [
                'diagnosisKompleks' => (bool) $this->form['diagnosisKompleks'],
                'asuhanTigaAtauLebih' => (bool) $this->form['asuhanTigaAtauLebih'],
                'alergiObatMdr' => (bool) $this->form['alergiObatMdr'],
                'detailDiagnosis' => $this->kunciTerpilihPrmrj($this->form['detailDiagnosis']),
                'detailDiagnosisLain' => $this->form['detailDiagnosisLain'],
                'detailAsuhan' => $this->kunciTerpilihPrmrj($this->form['detailAsuhan']),
                'detailAsuhanLain' => $this->form['detailAsuhanLain'],
                'detailAlergi' => $this->form['detailAlergi'],
                'catatan' => $this->form['kriteriaCatatan'],
            ],
            'otomatis' => $this->otomatis,
            'manual' => ['obatKhusus' => $this->form['obatKhusus']],
            'ttd' => [
                'nama' => $this->form['ttdPrmrj'],
                'kode' => $this->form['ttdPrmrjCode'],
                'tanggal' => $this->form['ttdPrmrjDate'],
            ],
            'terkunci' => $kunci,
            'dibuat' => [
                'oleh' => auth()->user()->myuser_name ?? auth()->user()->name ?? '-',
                'waktu' => $sekarang->format('d/m/Y H:i:s'),
            ],
        ];

        try {
            DB::transaction(function () use ($isiJson, $kunci) {
                $this->updateJsonPrmrj($this->regNo ?? '', 'RJ', (int) $this->rjNo, $isiJson);

                $this->appendAdminLogRJ(
                    (int) $this->rjNo,
                    ($kunci ? 'Kunci (TTD DPJP)' : 'Simpan Draft') . ' PRMRJ — Profil Ringkas Medis Rawat Jalan',
                    'MR'
                );
            });
        } catch (\Throwable $exception) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menyimpan: ' . $exception->getMessage());

            return;
        }

        $this->muatPrmrj((int) $this->rjNo);
        $this->dispatch('toast', type: 'success', message: $kunci
            ? 'PRMRJ ditandatangani dan terkunci.'
            : 'Draft PRMRJ tersimpan.');
    }

    /**
     * Buka kunci satu baris RM.06. Barisnya DIPERTAHANKAN, hanya TTD-nya dicabut.
     * Audit ditulis ke EMR kunjungan baris itu, bukan kunjungan yang sedang dibuka.
     */
    public function bukaKunciPrmrj(int $prmrjNo): void
    {
        if (! auth()->user()?->can('dokumen.bukaKunci')) {
            $this->dispatch('toast', type: 'error', message: 'Anda tidak berhak membuka kunci.');

            return;
        }

        try {
            DB::transaction(function () use ($prmrjNo) {
                $this->lockPrmrjRow($prmrjNo);

                $baris = DB::table('rstxn_prmrjs')->where('prmrj_no', $prmrjNo)->first();
                $isi = $this->readJsonPrmrj($baris);

                $isi['terkunci'] = false;
                $isi['ttd'] = ['nama' => '', 'kode' => '', 'tanggal' => ''];

                DB::table('rstxn_prmrjs')
                    ->where('prmrj_no', $prmrjNo)
                    ->update(['prmrj_json' => json_encode($isi, self::JSON_FLAGS_PRMRJ)]);

                $rjBaris = (int) ($isi['kunjungan']['no'] ?? 0);

                if (($isi['kunjungan']['jenis'] ?? '') === 'RJ' && $rjBaris > 0) {
                    $this->appendAdminLogRJ(
                        $rjBaris,
                        'Buka kunci PRMRJ #' . $prmrjNo . ' — oleh '
                            . (auth()->user()->myuser_name ?? auth()->user()->name ?? '-'),
                        'MR'
                    );
                }
            });
        } catch (\Throwable $exception) {
            $this->dispatch('toast', type: 'error', message: 'Gagal membuka kunci: ' . $exception->getMessage());

            return;
        }

        $this->muatPrmrj((int) $this->rjNo);
        $this->dispatch('toast', type: 'info', message: 'Kunci PRMRJ dibuka.');
    }

    /** Hapus satu baris RM.06. Hanya boleh saat TIDAK terkunci. */
    public function hapusPrmrj(int $prmrjNo): void
    {
        if (! auth()->user()?->can('dokumen.hapus')) {
            $this->dispatch('toast', type: 'error', message: 'Anda tidak berwenang menghapus entri.');

            return;
        }

        try {
            DB::transaction(function () use ($prmrjNo) {
                $this->lockPrmrjRow($prmrjNo);

                $baris = DB::table('rstxn_prmrjs')->where('prmrj_no', $prmrjNo)->first();
                $isi = $this->readJsonPrmrj($baris);

                if (! empty($isi['terkunci'])) {
                    throw new \RuntimeException('PRMRJ masih terkunci — buka kuncinya dulu.');
                }

                $rjBaris = (int) ($isi['kunjungan']['no'] ?? 0);

                DB::table('rstxn_prmrjs')->where('prmrj_no', $prmrjNo)->delete();

                if (($isi['kunjungan']['jenis'] ?? '') === 'RJ' && $rjBaris > 0) {
                    $this->appendAdminLogRJ(
                        $rjBaris,
                        'Hapus PRMRJ #' . $prmrjNo . ' — oleh '
                            . (auth()->user()->myuser_name ?? auth()->user()->name ?? '-'),
                        'MR'
                    );
                }
            });
        } catch (\Throwable $exception) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menghapus: ' . $exception->getMessage());

            return;
        }

        $this->muatPrmrj((int) $this->rjNo);
        $this->dispatch('toast', type: 'success', message: 'PRMRJ dihapus.');
    }

    /** Buka rekam medis kunjungan satu baris RM.06 lewat viewer yang sudah ada. */
    public function lihatRekamMedis(int $rjNoBaris): void
    {
        $this->dispatch('cetak-rekam-medis.open', rjNo: $rjNoBaris);
    }

    /**
     * Cetak formulir RM.06 UTUH — seluruh baris pasien, bukan satu baris.
     * Itu memang bentuk formulirnya: selembar berisi banyak kunjungan.
     */
    public function cetak(?int $prmrjNo = null)
    {
        if (blank($this->regNo) || $this->riwayat === []) {
            $this->dispatch('toast', type: 'error', message: 'Belum ada baris PRMRJ untuk dicetak.');

            return null;
        }

        // Tanpa nomor = seluruh formulir (tombol footer). Dengan nomor = satu
        // kunjungan saja (tombol di kartu) — bentuk cetaknya sama, isinya sebaris.
        $barisDicetak = $prmrjNo === null
            ? $this->riwayat
            : array_values(array_filter($this->riwayat, fn (array $baris) => $baris['prmrjNo'] === $prmrjNo));

        if ($barisDicetak === []) {
            $this->dispatch('toast', type: 'error', message: 'Baris PRMRJ tidak ditemukan.');

            return null;
        }

        try {
            $identitasRs = DB::table('rsmst_identitases')
                ->select('int_name', 'int_phone1', 'int_phone2', 'int_fax', 'int_address', 'int_city')
                ->first();

            $pasienData = $this->findDataMasterPasien($this->regNo);
            $pasien = $pasienData['pasien'] ?? [];

            if (! empty($pasien['tglLahir'])) {
                try {
                    $pasien['thn'] = Carbon::createFromFormat('d/m/Y', $pasien['tglLahir'])
                        ->diff(Carbon::now(config('app.timezone')))
                        ->format('%y Thn, %m Bln %d Hr');
                } catch (\Throwable) {
                    $pasien['thn'] = '-';
                }
            }

            // TTD tiap baris diambil ulang dari master lewat kodenya — yang
            // tersimpan di JSON cuma nama + kode + waktu.
            $barisList = collect($barisDicetak)->map(function (array $baris) {
                $baris['ttdPath'] = null;

                [, $isi] = $this->findDataPrmrj($this->regNo ?? '', $baris['jenisKunjungan'], $baris['nomorKunjungan']);
                $kode = $isi['ttd']['kode'] ?? null;

                if (filled($kode)) {
                    $berkas = DB::table('users')->where('myuser_code', $kode)->value('myuser_ttd_image');

                    if (! empty($berkas) && file_exists(public_path('storage/' . $berkas))) {
                        $baris['ttdPath'] = public_path('storage/' . $berkas);
                    }
                }

                return $baris;
            })->all();

            $data = array_merge($pasien, [
                'barisList' => $barisList,
                'identitasRs' => $identitasRs,
                'tglCetak' => Carbon::now(config('app.timezone'))->translatedFormat('d F Y'),
            ]);

            set_time_limit(300);

            $pdf = Pdf::loadView(
                'pages.components.modul-dokumen.rj.prmrj.cetak-prmrj-rj-print',
                ['data' => $data]
            )->setPaper('A4', 'landscape');

            $this->dispatch('toast', type: 'success', message: 'Berhasil mencetak PRMRJ.');

            return response()->streamDownload(
                fn () => print $pdf->output(),
                'prmrj-' . $this->regNo . ($prmrjNo === null ? '' : '-' . $prmrjNo) . '.pdf'
            );
        } catch (\Throwable $exception) {
            $this->dispatch('toast', type: 'error', message: 'Gagal cetak: ' . $exception->getMessage());

            return null;
        }
    }
}; ?>

<div>
    <x-modal name="prmrj-rj" size="full" height="full" focusable>
        @php $terkunci = $isFormLocked; @endphp

        <div class="flex flex-col h-full">
            {{-- ══ HEADER ══ --}}
            <div class="px-6 py-4 border-b border-hairline dark:border-gray-700">
                <div class="flex items-start justify-between gap-3">
                    {{-- Urutan baku: DISPLAY PASIEN dulu, judul dokumen di bawahnya.
                         Yang pertama dicari petugas saat modal terbuka adalah "ini pasien
                         siapa", bukan nama formulirnya. --}}
                    <div class="min-w-0">
                        @if (filled($rjNo))
                            {{-- Display pasien RJ — komponen yang sama dipakai EMR RJ, log
                                 aktivitas, dan viewer rekam medis. Ia memuat sendiri dari rjNo,
                                 jadi komponen ini tak perlu merakit identitas apa pun. --}}
                            <livewire:pages::transaksi.rj.display-pasien-rj.display-pasien-rj :rjNo="(string) $rjNo"
                                wire:key="prmrj-display-pasien-rj-{{ $rjNo }}" />
                        @endif

                        <h2 class="mt-3 text-base font-semibold text-ink dark:text-gray-100">
                            Profil Ringkas Medis Rawat Jalan (PRMRJ)
                        </h2>
                        <p class="mt-0.5 text-sm text-muted dark:text-gray-400">
                            Diidentifikasi dan dilengkapi DPJP Utama.
                        </p>
                    </div>
                    <x-icon-button color="gray" type="button"
                        x-on:click="$dispatch('close-modal', { name: 'prmrj-rj' })" class="shrink-0">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </x-icon-button>
                </div>
            </div>

            {{-- ══ ISI ══ --}}
            <div class="flex-1 min-h-0 px-6 py-4 space-y-4 overflow-y-auto">
                @if (! $siapDipakai)
                    <div class="px-4 py-3 text-sm border rounded-2xl bg-amber-50 border-amber-200 text-amber-900 dark:bg-amber-900/20 dark:border-amber-800 dark:text-amber-200">
                        Tabel <span class="font-mono">RSTXN_PRMRJS</span> belum dipasang di basis data.
                        Jalankan <span class="font-mono">docs/ddl-prmrj.sql</span> lebih dulu.
                    </div>
                @else
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">

                        {{-- ── 1. KRITERIA ── --}}
                        <x-border-form :title="__('1. Kriteria PRMRJ')" :align="__('start')" :bgcolor="__('bg-surface-soft')">
                            <div class="px-4 py-3 space-y-3">
                                <p class="text-sm text-muted dark:text-gray-400">
                                    Pasien layak PRMRJ bila <strong>salah satu</strong> terpenuhi.
                                </p>
                                {{-- Tiap toggle membuka rinciannya sendiri. Rinciannya baru muncul
                                     saat toggle-nya menyala — daftar mati yang selalu terlihat cuma
                                     jadi kebisingan di layar yang sudah padat. --}}
                                <div class="flex flex-col gap-4">

                                    {{-- (a) DIAGNOSIS KOMPLEKS --}}
                                    <div>
                                        <x-toggle wire:model.live="form.diagnosisKompleks" :trueValue="true" :falseValue="false"
                                            :disabled="$terkunci"
                                            label="Diagnosis kompleks — 3 atau lebih diagnosis penyerta" />
                                        @if ($form['diagnosisKompleks'])
                                            <div class="pt-2 pl-4 mt-2 space-y-2 border-l-2 border-hairline dark:border-gray-700">
                                                @foreach (PrmrjOptions::DIAGNOSIS_PENYERTA as $kunci => $label)
                                                    <x-toggle wire:model.live="form.detailDiagnosis.{{ $kunci }}"
                                                        :trueValue="true" :falseValue="false" :disabled="$terkunci"
                                                        :label="$label" />
                                                @endforeach
                                                <div class="pt-1">
                                                    <x-text-input wire:model.live="form.detailDiagnosisLain" :disabled="$terkunci"
                                                        placeholder="Diagnosis penyerta lain (opsional)" class="w-full" />
                                                </div>
                                                @php
                                                    $jumlahDiagnosis = count(array_filter($form['detailDiagnosis']))
                                                        + (filled($form['detailDiagnosisLain']) ? 1 : 0);
                                                @endphp
                                                <p class="pt-1 text-xs {{ $jumlahDiagnosis >= PrmrjOptions::AMBANG_BUTIR ? 'text-muted dark:text-gray-400' : 'text-red-600 dark:text-red-400' }}">
                                                    Dipilih {{ $jumlahDiagnosis }} dari minimal {{ PrmrjOptions::AMBANG_BUTIR }}.
                                                </p>
                                            </div>
                                        @endif
                                    </div>

                                    {{-- (b) ASUHAN --}}
                                    <div>
                                        <x-toggle wire:model.live="form.asuhanTigaAtauLebih" :trueValue="true" :falseValue="false"
                                            :disabled="$terkunci"
                                            label="Mendapat 3 atau lebih asuhan" />
                                        @if ($form['asuhanTigaAtauLebih'])
                                            <div class="pt-2 pl-4 mt-2 space-y-2 border-l-2 border-hairline dark:border-gray-700">
                                                @foreach (PrmrjOptions::ASUHAN as $kunci => $label)
                                                    <x-toggle wire:model.live="form.detailAsuhan.{{ $kunci }}"
                                                        :trueValue="true" :falseValue="false" :disabled="$terkunci"
                                                        :label="$label" />
                                                @endforeach
                                                <div class="pt-1">
                                                    <x-text-input wire:model.live="form.detailAsuhanLain" :disabled="$terkunci"
                                                        placeholder="Asuhan lain (opsional)" class="w-full" />
                                                </div>
                                                @php
                                                    $jumlahAsuhan = count(array_filter($form['detailAsuhan']))
                                                        + (filled($form['detailAsuhanLain']) ? 1 : 0);
                                                @endphp
                                                <p class="pt-1 text-xs {{ $jumlahAsuhan >= PrmrjOptions::AMBANG_BUTIR ? 'text-muted dark:text-gray-400' : 'text-red-600 dark:text-red-400' }}">
                                                    Dipilih {{ $jumlahAsuhan }} dari minimal {{ PrmrjOptions::AMBANG_BUTIR }}.
                                                </p>
                                            </div>
                                        @endif
                                    </div>

                                    {{-- (c) ALERGI / MDR --}}
                                    <div>
                                        <x-toggle wire:model.live="form.alergiObatMdr" :trueValue="true" :falseValue="false"
                                            :disabled="$terkunci"
                                            label="Alergi obat atau multi drug resistance" />
                                        @if ($form['alergiObatMdr'])
                                            <div class="pt-2 pl-4 mt-2 border-l-2 border-hairline dark:border-gray-700">
                                                <x-text-input wire:model.live="form.detailAlergi" :disabled="$terkunci"
                                                    placeholder="cth: alergi amoxicillin; MRSA" class="w-full" />
                                            </div>
                                        @endif
                                    </div>
                                </div>
                                <div>
                                    <x-input-label value="Catatan Kriteria (opsional)" class="mb-1" />
                                    <x-textarea wire:model.live="form.kriteriaCatatan" rows="2" :disabled="$terkunci"
                                        :error="$errors->has('form.kriteriaCatatan')"
                                        placeholder="cth: DM tipe 2, hipertensi grade II, gagal ginjal kronik" class="w-full" />
                                    <x-input-error :messages="$errors->get('form.kriteriaCatatan')" class="mt-1" />
                                </div>

                                {{-- Panduan kriteria — gaya biru-info standar, default TERTUTUP. --}}
                                <div x-data="{ buka: false }"
                                    class="overflow-hidden border rounded-2xl bg-blue-50 border-blue-200 dark:bg-blue-900/20 dark:border-blue-700">
                                    <button type="button" x-on:click="buka = !buka"
                                        class="flex items-center justify-between w-full px-4 py-2.5 text-sm font-semibold text-blue-900 transition-colors hover:bg-blue-100 dark:text-blue-200 dark:hover:bg-blue-900/30">
                                        <span class="flex items-center min-w-0 gap-2">
                                            <svg class="w-4 h-4 text-blue-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                            <span class="truncate">Panduan: rincian tiap kriteria</span>
                                        </span>
                                        <svg class="w-4 h-4 ml-2 text-blue-600 transition-transform shrink-0" x-bind:class="buka && 'rotate-180'"
                                            fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                        </svg>
                                    </button>
                                    <div x-show="buka" x-cloak class="px-4 pb-4 space-y-2 text-sm text-blue-900 dark:text-blue-100">
                                        <p>
                                            <strong>Diagnosis penyerta</strong>: diabetes melitus, hipertensi grade II,
                                            gagal ginjal kronik, congestive heart failure, tuberculosis paru dalam
                                            pengobatan atau dinyatakan sembuh, post tindakan operasi besar.
                                        </p>
                                        <p>
                                            <strong>Asuhan</strong>: gizi, radiologi, laboratorium, rehabilitasi medis,
                                            kemoterapi, EKG, tindakan operasi.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </x-border-form>

                        {{-- ── 2. DATA KUNJUNGAN INI ── --}}
                        <x-border-form :title="__('2. Data Kunjungan Ini')" :align="__('start')" :bgcolor="__('bg-surface-soft')">
                            <div class="px-4 py-3 space-y-3">
                                <p class="text-sm text-muted dark:text-gray-400">
                                    Terisi sendiri dari EMR kunjungan ini, tapi <strong>tetap bisa diedit</strong> &mdash;
                                    yang tersimpan adalah yang terlihat di sini, disalin apa adanya saat disimpan
                                    supaya cetakan tetap sama dengan yang ditandatangani.
                                </p>

                                <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                                    <div>
                                        <x-input-label value="Tanggal Kunjungan" class="mb-1" />
                                        <x-text-input wire:model.live="otomatis.tglKunjungan" :disabled="$terkunci" class="w-full" />
                                    </div>
                                    <div>
                                        <x-input-label value="Poliklinik" class="mb-1" />
                                        <x-text-input wire:model.live="otomatis.poliklinik" :disabled="$terkunci" class="w-full" />
                                    </div>
                                </div>

                                <div>
                                    <x-input-label value="DPJP dan PPA" class="mb-1" />
                                    {{-- Combobox PPA: boleh dipilih dari daftar users (dokter, perawat, bidan,
                                         apoteker, gizi jadi satu) ATAU diketik bebas — formulir ini menyebut
                                         "DPJP dan PPA lainnya", jadi isinya tak selalu satu nama dari master. --}}
                                    <x-ppa-combobox wireModel="otomatis.dpjp" :disabled="$terkunci"
                                        placeholder="DPJP / PPA — pilih dari daftar atau ketik" />
                                </div>

                                <div>
                                    <x-input-label value="Riwayat Alergi" class="mb-1" />
                                    <x-text-input wire:model.live="otomatis.riwayatAlergi" :disabled="$terkunci"
                                        placeholder="cth: tidak ada" class="w-full" />
                                </div>

                                {{-- Diagnosa, Tindakan, dan Operasi: teks bebas, satu butir per baris.
                                     Prasetelnya dari EMR sudah berbentuk teks (kode digabung di depan
                                     uraiannya), jadi dokter tinggal menyunting apa adanya. --}}
                                <div>
                                    <x-input-label value="Diagnosa & Kode Diagnosa" class="mb-1" />
                                    <x-textarea wire:model.live="otomatis.diagnosa" rows="3" :disabled="$terkunci"
                                        placeholder="cth: K04.1 Necrosis of pulp" class="w-full" />
                                </div>

                                <div>
                                    <x-input-label value="Tindakan" class="mb-1" />
                                    <x-textarea wire:model.live="otomatis.tindakan" rows="2" :disabled="$terkunci"
                                        placeholder="cth: 93.39 Perawatan saluran akar" class="w-full" />
                                </div>

                                <div>
                                    <x-input-label value="Operasi" class="mb-1" />
                                    <x-textarea wire:model.live="otomatis.operasi" rows="2" :disabled="$terkunci"
                                        placeholder="cth: 12/08/2026 Ekstraksi gigi impaksi" class="w-full" />
                                </div>

                                <div>
                                    <x-input-label value="Terapi (Obat-obatan)" class="mb-1" />
                                    <x-textarea wire:model.live="otomatis.terapi" rows="3" :disabled="$terkunci" class="w-full" />
                                </div>

                                <div>
                                    <x-input-label value="Rencana Tindakan Pengobatan" class="mb-1" />
                                    <x-textarea wire:model.live="otomatis.rencanaTindakLanjut" rows="2" :disabled="$terkunci" class="w-full" />
                                </div>
                            </div>
                        </x-border-form>
                    </div>

                    {{-- ── 3. OBAT KHUSUS + TTD ── --}}
                    <x-border-form :title="__('3. Obat Khusus & Tanda Tangan DPJP')" :align="__('start')" :bgcolor="__('bg-surface-soft')">
                        <div class="px-4 py-3 space-y-3">
                            @if ($terkunci)
                                <div><x-badge variant="success">Terkunci</x-badge></div>
                            @elseif ($riwayatTotal > 0)
                                <div><x-badge variant="warning">Draft</x-badge></div>
                            @endif
                            <div>
                                <x-input-label value="Obat Khusus (opsional)" class="mb-1" />
                                <x-textarea wire:model.live="form.obatKhusus" rows="2" :disabled="$terkunci"
                                    :error="$errors->has('form.obatKhusus')"
                                    placeholder="cth: Sansulin log 50-0-0 | Cansulin rapid 3x6iu" class="w-full" />
                                <x-input-error :messages="$errors->get('form.obatKhusus')" class="mt-1" />
                            </div>

                            <div class="pt-2 border-t border-hairline dark:border-gray-700">
                                <x-signature.ttd-petugas :framed="false" :allowClear="false"
                                    :ttd="$form['ttdPrmrj']" :code="$form['ttdPrmrjCode']" :date="$form['ttdPrmrjDate']"
                                    :locked="$terkunci" sign="ttdDokter"
                                    nameLabel="DPJP Utama" dateLabel="Tanggal / Jam TTD"
                                    signLabel="TTD & Kunci"
                                    emptyText="Belum ditandatangani — terisi dari pengguna yang sedang login." />
                            </div>

                            <div class="flex flex-wrap items-center justify-end gap-2 pt-2 border-t border-hairline dark:border-gray-700">
                                @if (! $terkunci)
                                    <x-secondary-button type="button" wire:click="simpanDraft">Simpan Draft</x-secondary-button>
                                @endif
                            </div>
                        </div>
                    </x-border-form>

                    {{-- ── FORMULIR RM.06 ── --}}
                    <x-border-form :title="__('Seluruh Kunjungan Pasien')" :align="__('start')" :bgcolor="__('bg-surface-soft')">
                        <div class="px-3 py-3">
                            {{-- Bukan tabel lebar lagi: 10 kolom memaksa layar menggulung ke samping
                                 dan tiap sel jadi sempit. Tiap kunjungan kini satu KARTU dua kolom —
                                 keterangan ditumpuk ke bawah, dibagi kiri (identitas & diagnosa) dan
                                 kanan (terapi, tindakan, rencana). Kartunya tetap memakai bahasa rupa
                                 baku Pelayanan RJ: ring-1, rounded-2xl, aksen kiri untuk baris aktif.
                                 Cetakannya TETAP tabel 12 kolom — itu bentuk resmi formulirnya. --}}
                            <div class="space-y-3">
                                @forelse ($riwayat as $urut => $baris)
                                    @php
                                        $isiKiri = [
                                            'Poliklinik / DPJP' => trim(($baris['poliklinik'] ?: '-') . "\n" . ($baris['dpjp'] ?: '-')),
                                            'Diagnosa & Kode' => $baris['diagnosa'] ?: '-',
                                            'Riwayat Alergi' => $baris['riwayatAlergi'] ?: '-',
                                        ];
                                        $tindakanOperasi = trim(
                                            ($baris['tindakan'] ?: '')
                                            . (filled($baris['operasi']) ? "\nOperasi: " . $baris['operasi'] : '')
                                        );
                                        $isiKanan = [
                                            'Terapi' => $baris['terapi'] ?: '-',
                                            'Obat Khusus' => $baris['obatKhusus'] ?: '-',
                                            'Tindakan & Operasi' => $tindakanOperasi ?: '-',
                                            'Rencana Tindakan Pengobatan' => $baris['rencanaTindakLanjut'] ?: '-',
                                        ];
                                    @endphp

                                    <div wire:key="prmrj-baris-{{ $baris['prmrjNo'] }}"
                                        class="p-4 transition rounded-2xl shadow-sm ring-1 ring-hairline dark:ring-gray-700
                                        {{ $baris['iniKunjunganIni']
                                            ? 'bg-blue-50 dark:bg-blue-900/20 border-l-4 border-blue-500'
                                            : 'bg-canvas dark:bg-gray-900 hover:shadow-md hover:bg-surface-soft dark:hover:bg-gray-800' }}">

                                        {{-- KEPALA KARTU --}}
                                        <div class="flex flex-wrap items-center justify-between gap-2 pb-2 mb-3 border-b border-hairline dark:border-gray-700">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <span class="text-sm font-semibold text-muted dark:text-gray-400">{{ $urut + 1 }}.</span>
                                                <span class="font-mono text-sm font-semibold text-ink dark:text-gray-200">{{ $baris['tglKunjungan'] ?: '-' }}</span>
                                                @if ($baris['iniKunjunganIni'])
                                                    <x-badge variant="info">Kunjungan ini</x-badge>
                                                @endif
                                                @if ($baris['terkunci'])
                                                    <x-badge variant="success">Terkunci</x-badge>
                                                @else
                                                    <x-badge variant="warning">Draft</x-badge>
                                                @endif
                                            </div>

                                            {{-- Empat tombol baku, seragam dengan kartu Riwayat Pengkajian:
                                                 biru Lihat · amber Cetak · abu Buka Kunci · merah Hapus.
                                                 Hapus didorong ke ujung kanan supaya tak mudah terklik. --}}
                                            <div class="flex flex-wrap items-center w-full gap-1.5 sm:w-auto">
                                                @if ($baris['jenisKunjungan'] === 'RJ' && $baris['nomorKunjungan'] > 0)
                                                    <x-outline-button type="button"
                                                        wire:click="lihatRekamMedis({{ (int) $baris['nomorKunjungan'] }})"
                                                        wire:loading.attr="disabled"
                                                        class="!text-blue-600 !bg-blue-50 !border-blue-200 hover:!bg-blue-100 hover:!text-blue-700 hover:!border-blue-300 dark:!text-blue-400 dark:!bg-blue-900/20 dark:!border-blue-800/30 dark:hover:!bg-blue-900/30 dark:hover:!text-blue-300"
                                                        title="Lihat rekam medis kunjungan ini">
                                                        <span class="inline-flex items-center gap-1">
                                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                            </svg>
                                                            <span class="text-sm font-semibold">Lihat</span>
                                                        </span>
                                                    </x-outline-button>
                                                @endif

                                                {{-- Cetak SATU kunjungan; tombol di footer mencetak seluruh formulir. --}}
                                                <x-outline-button type="button"
                                                    wire:click="cetak({{ (int) $baris['prmrjNo'] }})"
                                                    wire:loading.attr="disabled" wire:target="cetak({{ (int) $baris['prmrjNo'] }})"
                                                    class="!text-amber-600 !bg-amber-50 !border-amber-200 hover:!bg-amber-100 hover:!text-amber-700 hover:!border-amber-300 dark:!text-amber-400 dark:!bg-amber-900/20 dark:!border-amber-800/30 dark:hover:!bg-amber-900/30 dark:hover:!text-amber-300"
                                                    title="Cetak kunjungan ini saja">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                            d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                                                    </svg>
                                                </x-outline-button>

                                                @if ($baris['terkunci'])
                                                    @can('dokumen.bukaKunci')
                                                        <x-outline-button type="button"
                                                            wire:click="bukaKunciPrmrj({{ (int) $baris['prmrjNo'] }})"
                                                            wire:confirm="Buka kunci baris PRMRJ ini? TTD DPJP akan dicabut & baris kembali menjadi draft."
                                                            wire:loading.attr="disabled"
                                                            class="!text-muted !bg-surface-soft !border-hairline hover:!bg-surface-soft hover:!text-body hover:!border-gray-300 dark:!text-muted-soft dark:!bg-gray-800/40 dark:!border-gray-700 dark:hover:!bg-gray-800/60"
                                                            title="Buka kunci baris ini">
                                                            <span class="inline-flex items-center gap-1">
                                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 11V7a4 4 0 118 0m-8 4h10a2 2 0 012 2v5a2 2 0 01-2 2H8a2 2 0 01-2-2v-5a2 2 0 012-2z" />
                                                                </svg>
                                                                <span class="text-sm font-semibold">Buka Kunci</span>
                                                            </span>
                                                        </x-outline-button>
                                                    @endcan
                                                @else
                                                    @can('dokumen.hapus')
                                                        <x-outline-button type="button"
                                                            wire:click="hapusPrmrj({{ (int) $baris['prmrjNo'] }})"
                                                            wire:confirm="Yakin hapus baris PRMRJ ini?"
                                                            wire:loading.attr="disabled"
                                                            class="ml-auto !text-red-600 !bg-red-50 !border-red-200 hover:!bg-red-100 hover:!text-red-700 hover:!border-red-300 dark:!text-red-400 dark:!bg-red-900/20 dark:!border-red-800/30 dark:hover:!bg-red-900/30 dark:hover:!text-red-300"
                                                            title="Hapus baris ini">
                                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                            </svg>
                                                        </x-outline-button>
                                                    @endcan
                                                @endif
                                            </div>
                                        </div>

                                        {{-- ISI: kiri & kanan, tiap keterangan ditumpuk ke bawah --}}
                                        <div class="grid grid-cols-1 gap-x-6 gap-y-3 md:grid-cols-2">
                                            <dl class="space-y-3 text-sm">
                                                @foreach ($isiKiri as $label => $nilai)
                                                    <div>
                                                        <dt class="text-xs tracking-wide uppercase text-muted dark:text-gray-400">{{ $label }}</dt>
                                                        <dd class="text-ink dark:text-gray-200 whitespace-pre-line">{{ $nilai }}</dd>
                                                    </div>
                                                @endforeach
                                            </dl>

                                            <dl class="space-y-3 text-sm">
                                                @foreach ($isiKanan as $label => $nilai)
                                                    <div>
                                                        <dt class="text-xs tracking-wide uppercase text-muted dark:text-gray-400">{{ $label }}</dt>
                                                        <dd class="text-ink dark:text-gray-200 whitespace-pre-line">{{ $nilai }}</dd>
                                                    </div>
                                                @endforeach
                                            </dl>
                                        </div>

                                        {{-- KRITERIA + TTD, sepenuh lebar di bawah --}}
                                        <div class="pt-3 mt-3 space-y-2 text-sm border-t border-hairline dark:border-gray-700">
                                            @if ($baris['kriteria'] !== [])
                                                <div>
                                                    <dt class="text-xs tracking-wide uppercase text-muted dark:text-gray-400">Kriteria</dt>
                                                    <dd class="text-ink dark:text-gray-200">
                                                        <ul class="list-disc list-inside">
                                                            @foreach ($baris['kriteria'] as $kriteria)
                                                                <li>{{ $kriteria }}</li>
                                                            @endforeach
                                                        </ul>
                                                    </dd>
                                                </div>
                                            @endif
                                            <div>
                                                <dt class="text-xs tracking-wide uppercase text-muted dark:text-gray-400">Tanda Tangan DPJP</dt>
                                                <dd class="text-ink dark:text-gray-200">
                                                    {{ $baris['ttdNama'] ?: 'Belum ditandatangani' }}
                                                    @if (filled($baris['ttdTanggal']))
                                                        <span class="font-mono text-muted dark:text-gray-400">&middot; {{ $baris['ttdTanggal'] }}</span>
                                                    @endif
                                                </dd>
                                            </div>
                                        </div>
                                    </div>
                                @empty
                                    <p class="py-16 text-sm text-center text-muted dark:text-gray-400">
                                        Belum ada baris PRMRJ untuk pasien ini.
                                    </p>
                                @endforelse
                            </div>

                            @if ($riwayatTotal > count($riwayat))
                                <p class="py-1 text-xs text-muted dark:text-gray-400">
                                    Menampilkan {{ count($riwayat) }} terlama dari {{ $riwayatTotal }} baris.
                                </p>
                            @endif

                            <p class="pt-2 text-xs italic text-muted dark:text-gray-400">
                                PRMRJ diidentifikasi dan diisi lengkap oleh DPJP Utama.
                            </p>
                        </div>
                    </x-border-form>
                @endif
            </div>

            {{-- ══ FOOTER ══ --}}
            <div class="sticky bottom-0 z-10 flex items-center justify-end gap-2 px-6 py-3 border-t border-hairline bg-canvas dark:bg-gray-900 dark:border-gray-700">
                @if ($siapDipakai && $riwayat !== [])
                    <x-outline-button type="button" wire:click="cetak" wire:loading.attr="disabled" wire:target="cetak"
                        class="!text-amber-600 !bg-amber-50 !border-amber-200 hover:!bg-amber-100 hover:!text-amber-700 hover:!border-amber-300 dark:!text-amber-400 dark:!bg-amber-900/20 dark:!border-amber-800/30 dark:hover:!bg-amber-900/30 dark:hover:!text-amber-300"
                        title="Cetak seluruh kunjungan pasien">
                        <span wire:loading.remove wire:target="cetak">Cetak Semua</span>
                        <span wire:loading wire:target="cetak" class="flex items-center gap-1"><x-loading class="w-4 h-4" /> Mencetak...</span>
                    </x-outline-button>
                @endif
                <x-secondary-button type="button"
                    x-on:click="$dispatch('close-modal', { name: 'prmrj-rj' })">Tutup</x-secondary-button>
            </div>
        </div>
    </x-modal>
</div>
