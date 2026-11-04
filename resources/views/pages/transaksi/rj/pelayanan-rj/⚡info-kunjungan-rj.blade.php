<?php

use Carbon\Carbon;
use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Rj\EmrRJTrait;
use App\Http\Traits\Txn\Rj\EmrCompletenessRJTrait;

new class extends Component {
    use EmrRJTrait, EmrCompletenessRJTrait;

    public ?int $rjNo = null;

    /**
     * Ringkasan siap tampil, bukan JSON EMR utuh (payload kecil):
     * [kelompok => [judulBagian => [label => nilai, …], …], …]. Nilai kosong sudah dibuang.
     * Kelompok: Pendaftaran / EMR / Administrasi — untuk mengecek kelengkapan data kunjungan.
     */
    public array $detailKunjungan = [];

    public string $namaPasien = '';

    /** Kartu ringkasan di atas: klaim, jenis masuk, % EMR, status kasir/resep, sisa tagihan. */
    public array $ringkasan = [];

    #[On('open-info-kunjungan-rj')]
    public function open(int $rjNo): void
    {
        $this->rjNo = $rjNo;
        $dataDaftarRj = $this->findDataRJ($rjNo) ?? [];

        $this->namaPasien = trim(($dataDaftarRj['regNo'] ?? '') . ' · ' . ($dataDaftarRj['regName'] ?? ''), ' ·');
        $this->ringkasan = [];
        $this->detailKunjungan = $this->buangNilaiKosong([
            'Pendaftaran' => $this->susunPendaftaran($dataDaftarRj),
            'EMR' => $this->susunEmr($dataDaftarRj),
            'Administrasi' => $this->susunAdministrasi($rjNo, $dataDaftarRj),
        ]);
        $this->dispatch('open-modal', name: 'info-kunjungan-rj');
    }

    /* ===============================
     | PENDAFTARAN — data daftar, masuk BPJS, SEP
     =============================== */
    private function susunPendaftaran(array $dataDaftarRj): array
    {
        $isBpjs = ($dataDaftarRj['klaimStatus'] ?? '') === 'BPJS' || ($dataDaftarRj['klaimId'] ?? '') === 'JM';
        $tSep = $dataDaftarRj['sep']['reqSep']['request']['t_sep'] ?? [];

        $this->ringkasan['klaim'] = $dataDaftarRj['klaimStatus'] ?? null;
        $this->ringkasan['tanggal'] = $dataDaftarRj['rjDate'] ?? null;
        $this->ringkasan['poli'] = trim(($dataDaftarRj['poliDesc'] ?? '') . ' · ' . ($dataDaftarRj['drDesc'] ?? ''), ' ·');

        $bagian = [];
        $bagian['Data Pendaftaran'] = [
            'Tanggal Daftar' => $dataDaftarRj['rjDate'] ?? null,
            'Shift' => $dataDaftarRj['shift'] ?? null,
            'No Antrian' => $dataDaftarRj['noAntrian'] ?? null,
            'Poli' => $dataDaftarRj['poliDesc'] ?? null,
            'Dokter' => $dataDaftarRj['drDesc'] ?? null,
            'Klaim' => $dataDaftarRj['klaimStatus'] ?? null,
        ];

        if (!$isBpjs) {
            return $bagian;
        }

        // Label sama dgn kunjunganOptions di daftar-rj-actions.
        $kunjunganId = (string) ($dataDaftarRj['kunjunganId'] ?? '');
        $this->ringkasan['jenisMasuk'] = match ($kunjunganId) {
            '1' => 'Rujukan FKTP',
            '2' => 'Rujukan Internal',
            '3' => 'Kontrol',
            '4' => 'Rujukan Antar RS',
            default => null,
        };
        $bagian['Masuk BPJS'] = [
            'No Booking' => $dataDaftarRj['noBooking'] ?? null,
            'Jenis Masuk' => match ($kunjunganId) {
                '1' => 'Rujukan FKTP',
                '2' => 'Rujukan Internal',
                '3' => 'Kontrol',
                '4' => 'Rujukan Antar RS',
                default => null,
            },
            'Faskes' => match ($kunjunganId) {
                '2' => $dataDaftarRj['internal12Desc'] ?? null,
                '3' => $dataDaftarRj['kontrol12Desc'] ?? null,
                default => null,
            },
            'Post Inap' => !empty($dataDaftarRj['postInap']) ? 'Ya' : null,
            'No Referensi' => $dataDaftarRj['noReferensi'] ?? null,
        ];

        // Label sama dgn tujuanKunjOptions / assesmentPelOptions di vclaim-rj-actions.
        $bagian['SEP'] = [
            'No SEP' => $dataDaftarRj['sep']['noSep'] ?? ($dataDaftarRj['noSep'] ?? null),
            'Tanggal SEP' => $this->formatTanggal($tSep['tglSep'] ?? null),
            'No Kartu' => $tSep['noKartu'] ?? null,
            'Hak Kelas' => filled($tSep['klsRawat']['klsRawatHak'] ?? null) ? 'Kelas ' . $tSep['klsRawat']['klsRawatHak'] : null,
            'Poli Tujuan (BPJS)' => $tSep['poli']['tujuan'] ?? null,
            'DPJP Layan (BPJS)' => $tSep['dpjpLayan'] ?? null,
            'No Rujukan' => $tSep['rujukan']['noRujukan'] ?? null,
            'PPK Perujuk' => $tSep['rujukan']['ppkRujukan'] ?? null,
            'Masa Berlaku Rujukan' => $this->masaBerlakuRujukan($tSep['rujukan']['tglRujukan'] ?? null, $dataDaftarRj['rjDate'] ?? null),
            'No Surat Kontrol' => $tSep['skdp']['noSurat'] ?? null,
            'Diagnosa Awal' => $this->labelDiagnosaAwal($tSep['diagAwal'] ?? null),
            'Tujuan Kunjungan' => match ((string) ($tSep['tujuanKunj'] ?? '')) {
                '0' => 'Normal',
                '1' => 'Prosedur',
                '2' => 'Konsul Dokter',
                default => null,
            },
            'Assesmen Pelayanan' => match ((string) ($tSep['assesmentPel'] ?? '')) {
                '1' => 'Poli spesialis tidak tersedia pada hari sebelumnya',
                '2' => 'Jam Poli telah berakhir pada hari sebelumnya',
                '3' => 'Dokter Spesialis yang dimaksud tidak praktek pada hari sebelumnya',
                '4' => 'Atas Instruksi RS',
                '5' => 'Tujuan Kontrol',
                default => null,
            },
            'Catatan' => $tSep['catatan'] ?? null,
        ];

        return $bagian;
    }

    /* ===============================
     | EMR — pengkajian, TTV, diagnosa, perencanaan, waktu pelayanan
     =============================== */
    private function susunEmr(array $dataDaftarRj): array
    {
        $anamnesa = $dataDaftarRj['anamnesa'] ?? [];
        $tandaVital = $dataDaftarRj['pemeriksaan']['tandaVital'] ?? [];
        $nutrisi = $dataDaftarRj['pemeriksaan']['nutrisi'] ?? [];
        $perencanaan = $dataDaftarRj['perencanaan'] ?? [];
        $tindakLanjut = $perencanaan['tindakLanjut'] ?? [];
        $kontrol = $dataDaftarRj['kontrol'] ?? [];
        $isKontrol = ($tindakLanjut['tindakLanjut'] ?? null) === 'Kontrol';
        $taskId = $dataDaftarRj['taskIdPelayanan'] ?? [];

        $tekananDarah = filled($tandaVital['sistolik'] ?? null) || filled($tandaVital['distolik'] ?? null)
            ? ($tandaVital['sistolik'] ?? '-') . '/' . ($tandaVital['distolik'] ?? '-') . ' mmHg'
            : null;

        $this->ringkasan['emr'] = (int) ($this->calculateEmrPercentRJ($dataDaftarRj)['emr'] ?? 0);

        $bagian = [];
        $bagian['Pengkajian'] = [
            'Perawat Penerima' => $anamnesa['pengkajianPerawatan']['perawatPenerima'] ?? null,
            'Jam Datang' => $anamnesa['pengkajianPerawatan']['jamDatang'] ?? null,
            'Keluhan Utama' => trim((string) ($anamnesa['keluhanUtama']['keluhanUtama'] ?? '')),
            'Alergi' => trim((string) ($anamnesa['alergi']['alergi'] ?? '')),
            'Dokter Pemeriksa' => $perencanaan['pengkajianMedis']['drPemeriksa'] ?? null,
        ];

        $bagian['Tanda Vital'] = [
            'Tekanan Darah' => $tekananDarah,
            'Nadi' => $this->denganSatuan($tandaVital['frekuensiNadi'] ?? null, 'x/mnt'),
            'Napas' => $this->denganSatuan($tandaVital['frekuensiNafas'] ?? null, 'x/mnt'),
            'Suhu' => $this->denganSatuan($tandaVital['suhu'] ?? null, '°C'),
            'SpO2' => $this->denganSatuan($tandaVital['spo2'] ?? null, '%'),
            'GDA' => $this->denganSatuan($tandaVital['gda'] ?? null, 'mg/dL'),
            'BB / TB' => (float) ($nutrisi['bb'] ?? 0) > 0 || (float) ($nutrisi['tb'] ?? 0) > 0
                ? ($nutrisi['bb'] ?? '-') . ' kg / ' . ($nutrisi['tb'] ?? '-') . ' cm'
                : null,
        ];

        $bagian['Diagnosa & Prosedur'] = [
            'Diagnosa' => collect($dataDaftarRj['diagnosis'] ?? [])
                ->map(fn($diagnosa) => trim(($diagnosa['icdX'] ?? '') . ' — ' . ($diagnosa['diagDesc'] ?? '') . (filled($diagnosa['kategoriDiagnosa'] ?? null) ? ' (' . $diagnosa['kategoriDiagnosa'] . ')' : ''), ' —'))
                ->filter()
                ->implode("\n"),
            'Diagnosa (teks)' => $dataDaftarRj['diagnosisFreeText'] ?? null,
            'Prosedur' => collect($dataDaftarRj['procedure'] ?? [])
                ->map(fn($prosedur) => trim(($prosedur['procedureId'] ?? '') . ' — ' . ($prosedur['procedureDesc'] ?? ''), ' —'))
                ->filter()
                ->implode("\n"),
            'Prosedur (teks)' => $dataDaftarRj['procedureFreeText'] ?? null,
        ];

        $bagian['Perencanaan & Tindak Lanjut'] = [
            'Terapi' => trim((string) ($perencanaan['terapi']['terapi'] ?? '')),
            'Tindak Lanjut' => $tindakLanjut['tindakLanjut'] ?? null,
            'Keterangan' => $tindakLanjut['keteranganTindakLanjut'] ?? null,
            'Tanggal Kontrol' => $isKontrol ? $kontrol['tglKontrol'] ?? null : null,
            'No SKDP BPJS' => $kontrol['noSKDPBPJS'] ?? null,
            'Poli Kontrol' => $isKontrol ? $kontrol['poliKontrolDesc'] ?? ($kontrol['poliKontrol'] ?? null) : null,
            'Dokter Kontrol' => $isKontrol ? $kontrol['drKontrolDesc'] ?? ($kontrol['drKontrol'] ?? null) : null,
        ];

        // Label taskId sama dgn grid Pelayanan RJ (skill bpjs-antrean-task-id).
        $bagian['Waktu Pelayanan'] = [
            'Daftar (Task 3)' => $taskId['taskId3'] ?? null,
            'Masuk Poli (Task 4)' => $taskId['taskId4'] ?? null,
            'Keluar Poli (Task 5)' => $taskId['taskId5'] ?? null,
            'Masuk Apotek (Task 6)' => $taskId['taskId6'] ?? null,
            'Keluar Apotek (Task 7)' => $taskId['taskId7'] ?? null,
            'Batal (Task 99)' => $taskId['taskId99'] ?? null,
        ];

        return $bagian;
    }

    /* ===============================
     | ADMINISTRASI — status, resep, rincian biaya (query DB langsung, sama dgn kasir)
     =============================== */
    private function susunAdministrasi(int $rjNo, array $dataDaftarRj): array
    {
        $statusResep = $dataDaftarRj['statusResep'] ?? [];
        $eresep = $dataDaftarRj['eresep'] ?? [];
        $eresepRacikan = $dataDaftarRj['eresepRacikan'] ?? [];
        $jumlahRacikan = collect($eresepRacikan)->pluck('noRacikan')->filter()->unique()->count();

        $this->ringkasan['kasir'] = match ($dataDaftarRj['rjStatus'] ?? null) {
            'A' => ['label' => 'Menunggu Pembayaran', 'variant' => 'warning'],
            'L' => ['label' => 'Selesai Pembayaran', 'variant' => 'success'],
            'I' => ['label' => 'Transfer UGD', 'variant' => 'info'],
            'F' => ['label' => 'Batal', 'variant' => 'danger'],
            default => null,
        };
        $this->ringkasan['administrasi'] = isset($dataDaftarRj['AdministrasiRj']);

        $bagian = [];
        $bagian['Status'] = [
            'Administrasi' => $dataDaftarRj['AdministrasiRj']['userLog'] ?? 'Belum',
            'Waktu Administrasi' => $dataDaftarRj['AdministrasiRj']['userLogDate'] ?? null,
            'Kasir' => match ($dataDaftarRj['rjStatus'] ?? null) {
                'A' => 'Menunggu Pembayaran',
                'L' => 'Selesai Pembayaran',
                'I' => 'Transfer UGD',
                'F' => 'Batal',
                default => null,
            },
        ];

        $bagian['Resep'] = [
            'E-Resep' => count($eresep) || $jumlahRacikan
                ? count($eresep) . ' obat non-racikan, ' . $jumlahRacikan . ' racikan'
                : null,
            'Status Resep' => match ($statusResep['status'] ?? null) {
                'DITUNGGU' => 'Ditunggu',
                'DITINGGAL' => 'Ditinggal',
                default => null,
            },
            'Keterangan Resep' => $statusResep['keterangan'] ?? null,
            'Dicatat oleh' => filled($statusResep['userLog'] ?? null)
                ? $statusResep['userLog'] . (filled($statusResep['userLogDate'] ?? null) ? ' · ' . $statusResep['userLogDate'] : '')
                : null,
        ];

        // Komponen biaya = calculateRJCosts (dipakai kasir & transfer RJ→UGD), urutan & label ikut layar Administrasi.
        $biaya = $this->calculateRJCosts($rjNo);
        $labelBiaya = [
            'rsAdmin' => 'Admin RS',
            'rjAdmin' => 'Admin RJ',
            'poliPrice' => 'Poli',
            'actdPrice' => 'Jasa Dokter',
            'actpPrice' => 'Jasa Medis',
            'actePrice' => 'Jasa Karyawan',
            'obat' => 'Obat',
            'lab' => 'Laboratorium',
            'rad' => 'Radiologi',
            'kamarOperasi' => 'Kamar Operasi',
            'other' => 'Lain-lain',
        ];
        $rincianBiaya = [];
        foreach ($labelBiaya as $kunci => $label) {
            if (($biaya[$kunci] ?? 0) > 0) {
                $rincianBiaya[$label] = $this->rupiah($biaya[$kunci]);
            }
        }

        $totalBiaya = array_sum($biaya);
        $diskon = (int) DB::table('rstxn_rjhdrs')->where('rj_no', $rjNo)->value('rj_diskon');
        $sudahBayar = (int) DB::table('rstxn_rjcashins')->where('rj_no', $rjNo)->sum('rjc_nominal');
        $tagihan = max(0, $totalBiaya - $diskon);

        $rincianBiaya['Total'] = $this->rupiah($totalBiaya);
        if ($diskon > 0) {
            $rincianBiaya['Diskon'] = $this->rupiah($diskon);
        }
        $rincianBiaya['Sudah Dibayar'] = $this->rupiah($sudahBayar);
        $rincianBiaya['Sisa Tagihan'] = $this->rupiah(max(0, $tagihan - $sudahBayar));
        $this->ringkasan['sisaTagihan'] = max(0, $tagihan - $sudahBayar);
        $this->ringkasan['totalTagihan'] = $tagihan;
        $bagian['Rincian Biaya'] = $rincianBiaya;

        return $bagian;
    }

    /* ===============================
     | HELPERS
     =============================== */

    /** Masa berlaku rujukan 3 bulan; sisa hari dihitung dari TANGGAL KUNJUNGAN, bukan hari ini. */
    private function masaBerlakuRujukan(?string $tglRujukan, ?string $tglKunjungan): ?string
    {
        if (blank($tglRujukan)) {
            return null;
        }

        $mulai = Carbon::parse($tglRujukan);
        $batas = $mulai->copy()->addMonths(3);
        $acuan = filled($tglKunjungan) ? Carbon::createFromFormat('d/m/Y H:i:s', $tglKunjungan)->startOfDay() : now()->startOfDay();
        $sisaHari = (int) $acuan->diffInDays($batas, false);

        return $mulai->format('d/m/Y') . ' s/d ' . $batas->format('d/m/Y') . ' (sisa ' . $sisaHari . ' hari saat kunjungan)';
    }

    private function labelDiagnosaAwal(?string $icdX): ?string
    {
        if (blank($icdX)) {
            return null;
        }
        // icdx kembar di master (skill diagnosa-flow) — cukup ambil satu deskripsi untuk tampilan.
        $deskripsi = DB::table('rsmst_mstdiags')->where('icdx', $icdX)->value('diag_desc');

        return $icdX . ($deskripsi ? ' — ' . $deskripsi : '');
    }

    private function formatTanggal(?string $tanggal): ?string
    {
        return filled($tanggal) ? Carbon::parse($tanggal)->format('d/m/Y') : null;
    }

    private function denganSatuan($nilai, string $satuan): ?string
    {
        return filled($nilai) && (string) $nilai !== '0' ? $nilai . ' ' . $satuan : null;
    }

    private function rupiah(int $nilai): string
    {
        return 'Rp ' . number_format($nilai, 0, ',', '.');
    }

    /** Buang label bernilai kosong, lalu bagian & kelompok yang jadi kosong. */
    private function buangNilaiKosong(array $kelompokList): array
    {
        return collect($kelompokList)
            ->map(fn(array $bagianList) => collect($bagianList)
                ->map(fn(array $isiBagian) => array_filter($isiBagian, fn($nilai) => filled($nilai) && $nilai !== '-'))
                ->filter()
                ->all())
            ->filter()
            ->all();
    }
};

?>

{{--
    Modal detail kunjungan RJ untuk mengecek kelengkapan data dalam satu layar:
    Pendaftaran (daftar, masuk BPJS, SEP) · EMR (pengkajian, TTV, diagnosa, perencanaan, waktu task)
    · Administrasi (status, resep, rincian biaya). Hanya-baca.
    Dibuka dari tombol "Detail Kunjungan" di grid Pelayanan RJ via event `open-info-kunjungan-rj` (rjNo).
--}}

<div>
    <x-modal name="info-kunjungan-rj" size="full" height="full" focusable>
        <div class="flex flex-col min-h-full">

            <x-modul-dokumen.header :judul="'Detail Kunjungan — No. RJ ' . ($rjNo ?? '-')"
                ikon="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"
                jalur="RJ" namaModal="info-kunjungan-rj">
                {{ $namaPasien }}
            </x-modul-dokumen.header>

            @php
                // Pita kelompok — gaya sama dgn pita SOAP di x-kelengkapan-emr.isi.
                $gayaKelompok = [
                    'Pendaftaran' => ['huruf' => 'D', 'pita' => 'bg-blue-100 dark:border-blue-700', 'bulat' => 'text-blue-700 bg-blue-100 dark:bg-blue-900/40 dark:text-blue-300'],
                    'EMR' => ['huruf' => 'E', 'pita' => 'bg-emerald-100 dark:border-emerald-700', 'bulat' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300'],
                    'Administrasi' => ['huruf' => 'A', 'pita' => 'bg-amber-100 dark:border-amber-700', 'bulat' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300'],
                ];
                // Nomor/kode → huruf mono supaya mudah dicocokkan karakter per karakter.
                $labelMono = ['No Booking', 'No Referensi', 'No SEP', 'No Kartu', 'No Rujukan', 'PPK Perujuk', 'No Surat Kontrol', 'No SKDP BPJS', 'Poli Tujuan (BPJS)', 'DPJP Layan (BPJS)'];
                $labelTebal = ['Total', 'Sisa Tagihan'];
                $emr = (int) ($ringkasan['emr'] ?? 0);
                $warnaEmr = $emr >= 80 ? 'text-success dark:text-success' : ($emr >= 50 ? 'text-amber-600 dark:text-amber-400' : 'text-error dark:text-rose-400');
            @endphp

            <div class="flex-1 px-6 py-4 space-y-5 bg-surface-soft/70 dark:bg-gray-950/20">
                @if ($detailKunjungan)
                    {{-- RINGKASAN --}}
                    <div class="p-4 border shadow-sm bg-canvas border-hairline rounded-2xl dark:bg-gray-900 dark:border-gray-700">
                        <div class="flex flex-wrap items-center justify-between gap-4">
                            <div class="min-w-0">
                                <p class="ds-form-title">{{ $ringkasan['poli'] ?? '-' }}</p>
                                <p class="text-xs text-muted dark:text-gray-400">{{ $ringkasan['tanggal'] ?? '-' }}</p>
                                <div class="flex flex-wrap items-center gap-2 mt-2">
                                    @if (filled($ringkasan['klaim'] ?? null))
                                        <x-badge :variant="$ringkasan['klaim'] === 'BPJS' ? 'success' : 'gray'">{{ $ringkasan['klaim'] }}</x-badge>
                                    @endif
                                    @if (filled($ringkasan['jenisMasuk'] ?? null))
                                        <x-badge variant="info">{{ $ringkasan['jenisMasuk'] }}</x-badge>
                                    @endif
                                    <x-badge :variant="($ringkasan['administrasi'] ?? false) ? 'success' : 'gray'">
                                        {{ ($ringkasan['administrasi'] ?? false) ? 'Administrasi ✓' : 'Administrasi belum' }}
                                    </x-badge>
                                    @if ($ringkasan['kasir'] ?? null)
                                        <x-badge :variant="$ringkasan['kasir']['variant']">Kasir: {{ $ringkasan['kasir']['label'] }}</x-badge>
                                    @endif
                                </div>
                            </div>
                            <div class="flex items-end gap-6">
                                <div class="text-right">
                                    <p class="text-xs text-muted dark:text-gray-400">Kelengkapan EMR</p>
                                    <p class="text-2xl font-bold {{ $warnaEmr }}">{{ $emr }}%</p>
                                </div>
                                @isset($ringkasan['sisaTagihan'])
                                    <div class="text-right">
                                        <p class="text-xs text-muted dark:text-gray-400">Sisa Tagihan</p>
                                        <p class="text-2xl font-bold tabular-nums {{ $ringkasan['sisaTagihan'] > 0 ? 'text-error dark:text-rose-400' : 'text-success dark:text-success' }}">
                                            Rp {{ number_format($ringkasan['sisaTagihan'], 0, ',', '.') }}
                                        </p>
                                    </div>
                                @endisset
                            </div>
                        </div>
                    </div>
                @endif

                @forelse ($detailKunjungan as $kelompok => $bagianList)
                    <section>
                        <div class="flex items-center gap-2 p-2 mb-3 border-2 rounded-t-lg {{ $gayaKelompok[$kelompok]['pita'] ?? 'bg-surface-soft dark:border-gray-600' }}">
                            <span class="inline-flex items-center justify-center w-8 h-8 text-base font-bold rounded-full {{ $gayaKelompok[$kelompok]['bulat'] ?? '' }}">{{ $gayaKelompok[$kelompok]['huruf'] ?? '•' }}</span>
                            <span class="text-base font-semibold text-body dark:text-gray-300">{{ $kelompok }}</span>
                        </div>
                        <div class="grid items-start grid-cols-1 gap-4 xl:grid-cols-2">
                            @foreach ($bagianList as $judulBagian => $isiBagian)
                                <div class="overflow-hidden border shadow-sm bg-canvas border-hairline rounded-2xl dark:bg-gray-900 dark:border-gray-700">
                                    <p class="px-4 pt-3 pb-2 ds-form-title">{{ $judulBagian }}</p>
                                    <ul class="border-t divide-y border-hairline-soft divide-hairline-soft dark:border-gray-700 dark:divide-gray-700">
                                        @foreach ($isiBagian as $label => $nilai)
                                            @php
                                                $isPanjang = str_contains((string) $nilai, "\n") || mb_strlen((string) $nilai) > 60;
                                                $isTebal = in_array($label, $labelTebal, true);
                                            @endphp
                                            @if ($isPanjang)
                                                <li class="px-4 py-2 text-sm">
                                                    <p class="text-xs text-muted dark:text-gray-400">{{ $label }}</p>
                                                    <p class="mt-0.5 break-words whitespace-pre-line text-body dark:text-gray-200">{{ $nilai }}</p>
                                                </li>
                                            @else
                                                <li @class(['flex items-start justify-between gap-4 px-4 py-2 text-sm', 'bg-surface-soft dark:bg-gray-800' => $isTebal])>
                                                    <span class="text-muted shrink-0 dark:text-gray-400">{{ $label }}</span>
                                                    <span @class([
                                                        'text-right break-words min-w-0 text-ink dark:text-gray-100',
                                                        'font-mono text-xs' => in_array($label, $labelMono, true),
                                                        'tabular-nums' => str_starts_with((string) $nilai, 'Rp '),
                                                        'font-semibold' => $isTebal,
                                                        'font-medium' => !$isTebal,
                                                    ])>{{ $nilai }}</span>
                                                </li>
                                            @endif
                                        @endforeach
                                    </ul>
                                </div>
                            @endforeach
                        </div>
                    </section>
                @empty
                    <p class="text-sm text-center text-muted dark:text-gray-400">Data kunjungan tidak ditemukan.</p>
                @endforelse
            </div>

            <div class="sticky bottom-0 z-10 flex justify-end px-6 py-2 border-t bg-canvas border-hairline dark:bg-gray-900 dark:border-gray-700">
                <x-secondary-button type="button"
                    x-on:click="$dispatch('close-modal', { name: 'info-kunjungan-rj' })">
                    Tutup
                </x-secondary-button>
            </div>

        </div>
    </x-modal>
</div>
