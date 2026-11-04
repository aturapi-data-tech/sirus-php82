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
        // Guard server — tombol di grid sudah @can, tapi event bisa dipicu manual dari browser.
        if (!auth()->user()?->can('rj.detailKunjungan')) {
            $this->dispatch('toast', type: 'error', message: 'Detail Kunjungan hanya untuk manager ke atas.');
            return;
        }

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
     | Field yang berhubungan digabung satu baris (mis. tanggal · shift · antrian) supaya terbaca seperti kalimat.
     =============================== */
    private function susunPendaftaran(array $dataDaftarRj): array
    {
        $isBpjs = ($dataDaftarRj['klaimStatus'] ?? '') === 'BPJS' || ($dataDaftarRj['klaimId'] ?? '') === 'JM';
        $tSep = $dataDaftarRj['sep']['reqSep']['request']['t_sep'] ?? [];

        $this->ringkasan['klaim'] = $dataDaftarRj['klaimStatus'] ?? null;
        $this->ringkasan['tanggal'] = $dataDaftarRj['rjDate'] ?? null;
        $this->ringkasan['poli'] = $this->gabung([$dataDaftarRj['poliDesc'] ?? null, $dataDaftarRj['drDesc'] ?? null]);

        $bagian = [];
        $bagian['Data Pendaftaran'] = [
            'Kunjungan' => $this->gabung([
                $dataDaftarRj['rjDate'] ?? null,
                filled($dataDaftarRj['shift'] ?? null) ? 'Shift ' . $dataDaftarRj['shift'] : null,
                filled($dataDaftarRj['noAntrian'] ?? null) ? 'Antrian ' . $dataDaftarRj['noAntrian'] : null,
            ]),
            'Poli' => $this->gabung([$dataDaftarRj['poliDesc'] ?? null, $dataDaftarRj['drDesc'] ?? null], ' — '),
            'Klaim' => $dataDaftarRj['klaimStatus'] ?? null,
        ];

        if (!$isBpjs) {
            return $bagian;
        }

        // Label sama dgn kunjunganOptions di daftar-rj-actions.
        $kunjunganId = (string) ($dataDaftarRj['kunjunganId'] ?? '');
        $jenisMasuk = match ($kunjunganId) {
            '1' => 'Rujukan FKTP',
            '2' => 'Rujukan Internal',
            '3' => 'Kontrol',
            '4' => 'Rujukan Antar RS',
            default => null,
        };
        $this->ringkasan['jenisMasuk'] = $jenisMasuk;

        $bagian['Masuk BPJS'] = [
            'Jenis Masuk' => $this->gabung([
                $jenisMasuk,
                match ($kunjunganId) {
                    '2' => $dataDaftarRj['internal12Desc'] ?? null,
                    '3' => $dataDaftarRj['kontrol12Desc'] ?? null,
                    default => null,
                },
                !empty($dataDaftarRj['postInap']) ? 'Post Inap' : null,
            ]),
            'No Booking' => $dataDaftarRj['noBooking'] ?? null,
            'No Referensi' => $dataDaftarRj['noReferensi'] ?? null,
        ];

        // Label sama dgn tujuanKunjOptions / assesmentPelOptions di vclaim-rj-actions.
        $tujuanKunjungan = match ((string) ($tSep['tujuanKunj'] ?? '')) {
            '0' => 'Normal',
            '1' => 'Prosedur',
            '2' => 'Konsul Dokter',
            default => null,
        };
        $assesmenPelayanan = match ((string) ($tSep['assesmentPel'] ?? '')) {
            '1' => 'Poli spesialis tidak tersedia pada hari sebelumnya',
            '2' => 'Jam Poli telah berakhir pada hari sebelumnya',
            '3' => 'Dokter Spesialis yang dimaksud tidak praktek pada hari sebelumnya',
            '4' => 'Atas Instruksi RS',
            '5' => 'Tujuan Kontrol',
            default => null,
        };

        $bagian['SEP'] = [
            'No SEP' => $this->gabung([
                $dataDaftarRj['sep']['noSep'] ?? ($dataDaftarRj['noSep'] ?? null),
                $this->formatTanggal($tSep['tglSep'] ?? null),
            ]),
            'Peserta' => $this->gabung([
                $tSep['noKartu'] ?? null,
                filled($tSep['klsRawat']['klsRawatHak'] ?? null) ? 'Hak Kelas ' . $tSep['klsRawat']['klsRawatHak'] : null,
            ]),
            'Kode BPJS' => $this->gabung([
                filled($tSep['poli']['tujuan'] ?? null) ? 'Poli ' . $tSep['poli']['tujuan'] : null,
                filled($tSep['dpjpLayan'] ?? null) ? 'DPJP ' . $tSep['dpjpLayan'] : null,
            ]),
            'Rujukan' => $this->gabung([
                $tSep['rujukan']['noRujukan'] ?? null,
                filled($tSep['rujukan']['ppkRujukan'] ?? null) ? 'dari ' . $tSep['rujukan']['ppkRujukan'] : null,
            ], ' '),
            'Masa Berlaku Rujukan' => $this->masaBerlakuRujukan($tSep['rujukan']['tglRujukan'] ?? null, $dataDaftarRj['rjDate'] ?? null),
            'No Surat Kontrol' => $tSep['skdp']['noSurat'] ?? null,
            'Diagnosa Awal' => $this->labelDiagnosaAwal($tSep['diagAwal'] ?? null),
            'Tujuan' => $this->gabung([$tujuanKunjungan, $assesmenPelayanan]),
            'Catatan' => $tSep['catatan'] ?? null,
        ];

        return $bagian;
    }

    /* ===============================
     | EMR — pengkajian + TTV, diagnosa, perencanaan, alur waktu pelayanan
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

        $this->ringkasan['emr'] = (int) ($this->calculateEmrPercentRJ($dataDaftarRj)['emr'] ?? 0);

        $tekananDarah = filled($tandaVital['sistolik'] ?? null) || filled($tandaVital['distolik'] ?? null)
            ? 'TD ' . ($tandaVital['sistolik'] ?? '-') . '/' . ($tandaVital['distolik'] ?? '-') . ' mmHg'
            : null;
        $beratTinggi = (float) ($nutrisi['bb'] ?? 0) > 0 || (float) ($nutrisi['tb'] ?? 0) > 0
            ? 'BB ' . ($nutrisi['bb'] ?? '-') . ' kg / TB ' . ($nutrisi['tb'] ?? '-') . ' cm'
            : null;

        $bagian = [];
        $bagian['Pengkajian'] = [
            'Perawat' => $this->gabung([
                $anamnesa['pengkajianPerawatan']['perawatPenerima'] ?? null,
                filled($anamnesa['pengkajianPerawatan']['jamDatang'] ?? null) ? 'pasien datang ' . $anamnesa['pengkajianPerawatan']['jamDatang'] : null,
            ]),
            'Dokter' => $perencanaan['pengkajianMedis']['drPemeriksa'] ?? null,
            'Keluhan Utama' => trim((string) ($anamnesa['keluhanUtama']['keluhanUtama'] ?? '')),
            'Alergi' => trim((string) ($anamnesa['alergi']['alergi'] ?? '')),
            'Tanda Vital' => $this->gabung([
                $tekananDarah,
                $this->denganSatuan($tandaVital['frekuensiNadi'] ?? null, 'x/mnt', 'Nadi'),
                $this->denganSatuan($tandaVital['frekuensiNafas'] ?? null, 'x/mnt', 'Napas'),
                $this->denganSatuan($tandaVital['suhu'] ?? null, '°C', 'Suhu'),
                $this->denganSatuan($tandaVital['spo2'] ?? null, '%', 'SpO2'),
                $this->denganSatuan($tandaVital['gda'] ?? null, 'mg/dL', 'GDA'),
                $beratTinggi,
            ]),
        ];

        // Kode ICD per baris; teks bebas dokter ditempel di bawahnya.
        $adaKodeDiagnosa = !empty($dataDaftarRj['diagnosis']);
        $adaKodeProsedur = !empty($dataDaftarRj['procedure']);
        $bagian['Diagnosa & Prosedur'] = [
            'Diagnosa' => $this->gabung([
                collect($dataDaftarRj['diagnosis'] ?? [])
                    ->map(fn($diagnosa) => $this->gabung([$diagnosa['icdX'] ?? null, $diagnosa['diagDesc'] ?? null], ' — ')
                        . (filled($diagnosa['kategoriDiagnosa'] ?? null) ? ' (' . $diagnosa['kategoriDiagnosa'] . ')' : ''))
                    ->filter()
                    ->implode("\n"),
                !$adaKodeDiagnosa ? 'Belum dikode ICD-10' : null,
                filled($dataDaftarRj['diagnosisFreeText'] ?? null) && $dataDaftarRj['diagnosisFreeText'] !== '-' ? 'Catatan dokter: ' . $dataDaftarRj['diagnosisFreeText'] : null,
            ], "\n"),
            'Prosedur' => $this->gabung([
                collect($dataDaftarRj['procedure'] ?? [])
                    ->map(fn($prosedur) => $this->gabung([$prosedur['procedureId'] ?? null, $prosedur['procedureDesc'] ?? null], ' — '))
                    ->filter()
                    ->implode("\n"),
                !$adaKodeProsedur && filled($dataDaftarRj['procedureFreeText'] ?? null) && $dataDaftarRj['procedureFreeText'] !== '-' ? 'Belum dikode ICD-9' : null,
                filled($dataDaftarRj['procedureFreeText'] ?? null) && $dataDaftarRj['procedureFreeText'] !== '-' ? 'Catatan dokter: ' . $dataDaftarRj['procedureFreeText'] : null,
            ], "\n"),
        ];

        $bagian['Perencanaan & Tindak Lanjut'] = [
            'Tindak Lanjut' => $this->gabung([$tindakLanjut['tindakLanjut'] ?? null, $tindakLanjut['keteranganTindakLanjut'] ?? null], ' — '),
            'Kontrol' => $isKontrol
                ? $this->gabung([
                    $kontrol['tglKontrol'] ?? null,
                    $kontrol['poliKontrolDesc'] ?? ($kontrol['poliKontrol'] ?? null),
                    $kontrol['drKontrolDesc'] ?? ($kontrol['drKontrol'] ?? null),
                ])
                : null,
            'No SKDP BPJS' => $kontrol['noSKDPBPJS'] ?? null,
            'Terapi' => trim((string) ($perencanaan['terapi']['terapi'] ?? '')),
        ];

        // Label taskId sama dgn grid Pelayanan RJ (skill bpjs-antrean-task-id).
        $bagian['Waktu Pelayanan'] = [
            'Alur' => $this->alurWaktuPelayanan($taskId),
            'Durasi' => $this->durasiPelayanan($taskId),
            'Batal' => $taskId['taskId99'] ?? null,
        ];

        return $bagian;
    }

    /* ===============================
     | ADMINISTRASI — status, resep, rincian biaya (query DB langsung, sama dgn kasir)
     =============================== */
    private function susunAdministrasi(int $rjNo, array $dataDaftarRj): array
    {
        $statusResep = $dataDaftarRj['statusResep'] ?? [];
        $jumlahNonRacikan = count($dataDaftarRj['eresep'] ?? []);
        $jumlahRacikan = collect($dataDaftarRj['eresepRacikan'] ?? [])->pluck('noRacikan')->filter()->unique()->count();

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
            'Administrasi' => isset($dataDaftarRj['AdministrasiRj'])
                ? $this->gabung([$dataDaftarRj['AdministrasiRj']['userLog'] ?? null, $dataDaftarRj['AdministrasiRj']['userLogDate'] ?? null])
                : 'Belum diselesaikan',
            'Kasir' => $this->ringkasan['kasir']['label'] ?? null,
            'E-Resep' => $jumlahNonRacikan || $jumlahRacikan ? $jumlahNonRacikan . ' obat non-racikan · ' . $jumlahRacikan . ' racikan' : null,
            'Status Resep' => $this->gabung([
                match ($statusResep['status'] ?? null) {
                    'DITUNGGU' => 'Ditunggu',
                    'DITINGGAL' => 'Ditinggal',
                    default => null,
                },
                $statusResep['keterangan'] ?? null,
                filled($statusResep['userLog'] ?? null) ? 'dicatat ' . $this->gabung([$statusResep['userLog'], $statusResep['userLogDate'] ?? null], ', ') : null,
            ]),
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
        $sisaTagihan = max(0, $tagihan - $sudahBayar);

        $rincianBiaya['Total'] = $this->rupiah($totalBiaya) . ($diskon > 0 ? ' − diskon ' . $this->rupiah($diskon) . ' = ' . $this->rupiah($tagihan) : '');
        $rincianBiaya['Pembayaran'] = 'Dibayar ' . $this->rupiah($sudahBayar) . ' · Sisa ' . $this->rupiah($sisaTagihan);
        $bagian['Rincian Biaya'] = $rincianBiaya;

        $this->ringkasan['sisaTagihan'] = $sisaTagihan;
        $this->ringkasan['totalTagihan'] = $tagihan;

        return $bagian;
    }

    /** "13:44:07 Daftar → 14:28:20 Masuk Poli → …" — tanggal ditulis sekali di depan bila semua hari yang sama. */
    private function alurWaktuPelayanan(array $taskId): ?string
    {
        $langkahList = collect([
            'taskId3' => 'Daftar',
            'taskId4' => 'Masuk Poli',
            'taskId5' => 'Keluar Poli',
            'taskId6' => 'Masuk Apotek',
            'taskId7' => 'Keluar Apotek',
        ])->filter(fn($label, $kunci) => filled($taskId[$kunci] ?? null));

        if ($langkahList->isEmpty()) {
            return null;
        }

        $tanggalList = $langkahList->keys()->map(fn($kunci) => substr($taskId[$kunci], 0, 10))->unique();
        $satuHari = $tanggalList->count() === 1;

        $alur = $langkahList
            ->map(fn($label, $kunci) => ($satuHari ? substr($taskId[$kunci], 11) : $taskId[$kunci]) . ' ' . $label)
            ->implode(' → ');

        return ($satuHari ? $tanggalList->first() . ': ' : '') . $alur;
    }

    /** Lama tunggu & layanan dalam menit dari pasangan taskId (hanya bila kedua waktu ada & urut). */
    private function durasiPelayanan(array $taskId): ?string
    {
        $menit = function (?string $dari, ?string $sampai): ?int {
            if (blank($dari) || blank($sampai)) {
                return null;
            }
            $selisih = (int) Carbon::createFromFormat('d/m/Y H:i:s', $dari)->diffInMinutes(Carbon::createFromFormat('d/m/Y H:i:s', $sampai), false);

            return $selisih >= 0 ? $selisih : null;
        };
        $teksMenit = fn(int $jumlahMenit) => $jumlahMenit < 1 ? '< 1 mnt' : $jumlahMenit . ' mnt';

        return $this->gabung([
            ($tunggu = $menit($taskId['taskId3'] ?? null, $taskId['taskId4'] ?? null)) !== null ? 'tunggu poli ' . $teksMenit($tunggu) : null,
            ($layanan = $menit($taskId['taskId4'] ?? null, $taskId['taskId5'] ?? null)) !== null ? 'di poli ' . $teksMenit($layanan) : null,
            ($apotek = $menit($taskId['taskId6'] ?? null, $taskId['taskId7'] ?? null)) !== null ? 'layanan apotek ' . $teksMenit($apotek) : null,
            ($total = $menit($taskId['taskId3'] ?? null, filled($taskId['taskId7'] ?? null) ? $taskId['taskId7'] : ($taskId['taskId5'] ?? null))) !== null ? 'total ' . $teksMenit($total) : null,
        ]);
    }

    /** Gabung potongan teks yang terisi dengan pemisah; kosong semua → null (baris tidak tampil). */
    private function gabung(array $potonganList, string $pemisah = ' · '): ?string
    {
        $terisi = array_filter(array_map(fn($potongan) => is_string($potongan) ? trim($potongan) : $potongan, $potonganList), fn($potongan) => filled($potongan) && $potongan !== '-');

        return $terisi ? implode($pemisah, $terisi) : null;
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

    private function denganSatuan($nilai, string $satuan, string $label = ''): ?string
    {
        return filled($nilai) && (string) $nilai !== '0' ? trim($label . ' ' . $nilai . ' ' . $satuan) : null;
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
    Pendaftaran (daftar, masuk BPJS, SEP) · EMR (pengkajian+TTV, diagnosa, perencanaan, alur waktu task)
    · Administrasi (status+resep, rincian biaya). Field yang berhubungan digabung satu baris
    (gabung()) supaya terbaca seperti kalimat. Hanya-baca.
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
                $labelMono = ['No Booking', 'No Referensi', 'No SEP', 'Peserta', 'Kode BPJS', 'Rujukan', 'No Surat Kontrol', 'No SKDP BPJS'];
                $labelTebal = ['Total', 'Pembayaran'];
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
                                                        'tabular-nums' => str_contains((string) $nilai, 'Rp '),
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
