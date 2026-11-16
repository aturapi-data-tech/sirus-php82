<?php

use Livewire\Component;
use Livewire\Attributes\On;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Rj\EmrRJTrait;
use App\Http\Traits\Txn\Ugd\EmrUGDTrait;
use App\Http\Traits\Txn\Ri\EmrRITrait;
use App\Http\Traits\Master\MasterPasien\MasterPasienTrait;
use App\Support\Options\RujukanKompetensiOptions;
use App\Http\Traits\SATUSEHAT\SatuSehatRujukanTrait;

/**
 * Cetak Surat Pengantar Rujukan + Resume Klinis Pasien Rujukan.
 *
 * Satu komponen untuk KEENAM panel rujukan (RJ/UGD/RI × SISRUTE/FHIR): formatnya
 * ditetapkan Kemkes untuk SEMUA rujukan, jadi tidak ada gunanya menggandakan
 * cetakan per jalur. Yang berbeda antar jalur hanya SUMBER datanya — itu
 * diselesaikan di normalkan*() lalu diserahkan ke blade dalam bentuk seragam.
 *
 * Dipanggil: $this->dispatch('cetak-surat-rujukan.open',
 *              jalur: 'rj'|'ugd'|'ri', noKunjungan: $rjNo, node: 'rujukanKompetensi');
 */
new class extends Component {
    use EmrRJTrait, EmrUGDTrait, EmrRITrait, MasterPasienTrait, SatuSehatRujukanTrait;

    #[On('cetak-surat-rujukan.open')]
    public function open(string $jalur, string $noKunjungan, string $node = 'rujukanKompetensi'): mixed
    {
        $dataKunjungan = match ($jalur) {
            'rj' => $this->findDataRJ($noKunjungan),
            'ugd' => $this->findDataUGD($noKunjungan),
            'ri' => $this->findDataRI($noKunjungan),
            default => [],
        };

        if (empty($dataKunjungan)) {
            $this->dispatch('toast', type: 'error', message: 'Data kunjungan tidak ditemukan.');
            return null;
        }

        $rujukan = $dataKunjungan[$node] ?? [];
        $hasil = $rujukan['hasil'] ?? [];

        // Surat pengantar hanya sah kalau rujukannya sudah benar-benar terbit —
        // nomor SATUSEHAT adalah penanda sukses sejati (lihat aturan payload no. 8).
        if (empty($hasil['noRujukanSatuSehat'])) {
            $this->dispatch('toast', type: 'error', message: 'Rujukan belum terkirim — surat pengantar baru bisa dicetak setelah nomor Rujukan SATUSEHAT terbit.');
            return null;
        }

        $pasien = $this->findDataMasterPasien($dataKunjungan['regNo'] ?? '');
        if (empty($pasien)) {
            $this->dispatch('toast', type: 'error', message: 'Data pasien tidak ditemukan.');
            return null;
        }

        $data = $this->normalkan($dataKunjungan, $pasien, $rujukan, $hasil);

        $pdf = Pdf::loadView('pages.components.rekam-medis.rujukan-kompetensi.cetak-surat-rujukan-print', [
            'data' => $data,
        ])->setPaper('A4');

        $namaBerkas = 'surat-rujukan-' . ($dataKunjungan['regNo'] ?? '') . '-' . $hasil['noRujukanSatuSehat'] . '.pdf';

        return response()->streamDownload(fn() => print $pdf->output(), $namaBerkas);
    }

    /* ═══════════════════════════════════════
     | Normalisasi — sumber beda, bentuk sama
    ═══════════════════════════════════════ */
    private function normalkan(array $kunjungan, array $pasien, array $rujukan, array $hasil): array
    {
        $identitas = $pasien['pasien']['identitas'] ?? [];
        $tandaVital = $kunjungan['pemeriksaan']['tandaVital'] ?? [];

        $klaim = DB::table('rsmst_klaimtypes')
            ->where('klaim_id', $kunjungan['klaimId'] ?? '')
            ->select('klaim_status', 'klaim_desc')
            ->first();

        $sistolik = trim((string) ($tandaVital['sistolik'] ?? ''));
        $distolik = trim((string) ($tandaVital['distolik'] ?? ''));

        $noRujukanBpjs = trim((string) ($hasil['noRujukan'] ?? ''));

        return [
            // Rujukan Rawat Jalan BPJS (SISRUTE, punya nomor BPJS) → halaman 1 memakai tata letak
            // contoh BPJS 16/11/26 (rujukan-vclaim-*.pdf) dengan logo RS; jalur FHIR tetap Kepmenkes.
            'format' => $noRujukanBpjs !== '' ? 'bpjs' : 'kepmenkes',
            'qrNoRujukan' => $noRujukanBpjs !== '' ? $this->qrPng($noRujukanBpjs) : '',
            'tglRencana' => $this->tanggalPanjang((string) ($rujukan['tglRencanaKunjungan'] ?? ''), 'd/m/Y'),
            'tglRujukanPanjang' => $this->tanggalPanjang((string) ($hasil['tglRujukan'] ?? ''), 'Y-m-d'),
            'poliTujuan' => $this->namaPoliBpjs((string) (($rujukan['poliRujukan'] ?? '') ?: ($rujukan['kodeSpesialis'] ?? ''))),
            'diagnosaKode' => trim((string) ($rujukan['kodeDiagnosa'] ?? '')),
            'diagnosaDesc' => trim((string) ($rujukan['diagnosaDesc'] ?? '')),
            'catatan' => trim((string) ($rujukan['catatan'] ?? '')),
            'dicetakPada' => Carbon::now(config('app.timezone'))->format('d-m-Y H:i') . ' WIB',

            // Jalur FHIR tidak menerbitkan nomor BPJS — kosong, bukan '-' palsu.
            'noRujukan' => (string) ($hasil['noRujukanSatuSehat'] ?? ''),
            'noRujukanBpjs' => (string) ($hasil['noRujukan'] ?? ''),
            'tanggal' => $this->tanggalSurat($hasil),

            'perujuk' => [
                'nama' => 'Rumah Sakit Islam Madinah',
                'kode' => $this->kodeRegisterPerujuk(),
                'alamat' => 'Jl. Jati Wayang, Lk. 2, Ngunut, Kec. Ngunut, Kabupaten Tulungagung, Jawa Timur 66292',
                'kota' => 'Tulungagung',
            ],
            'tujuan' => [
                'nama' => (string) ($hasil['tujuanNama'] ?? ''),
                'kode' => $this->kodeRegisterTujuan($hasil),
                'alamat' => $this->alamatTujuan($hasil),
            ],

            'pasien' => [
                'nama' => (string) ($pasien['pasien']['regName'] ?? ''),
                'noRm' => (string) ($kunjungan['regNo'] ?? ''),
                'nik' => (string) ($identitas['nik'] ?? ''),
                'umur' => $this->umur($pasien),
                'jenisKelamin' => (string) ($pasien['pasien']['jenisKelamin']['jenisKelaminDesc'] ?? ''),
                'alamat' => (string) ($identitas['alamat'] ?? ''),
                'jenisJaminan' => (string) ($klaim->klaim_desc ?? ''),
                'nomorJaminan' => (string) ($identitas['idbpjs'] ?? ''),
                'noBpjs' => (string) ($identitas['idbpjs'] ?? ''),
            ],

            'diagnosaSementara' => trim(
                trim((string) ($rujukan['kodeDiagnosa'] ?? '')) . ' ' . trim((string) ($rujukan['diagnosaDesc'] ?? ''))
            ),
            'terapiDiberikan' => $this->terapiTeks($kunjungan),
            'dpjp' => (string) ($kunjungan['drDesc'] ?? ''),
            'respon' => [
                'faskes' => (string) ($hasil['tujuanNama'] ?? ''),
                'noRujukan' => (string) ($hasil['noRujukanSatuSehat'] ?? ''),
            ],

            'resume' => [
                'keluhanUtama' => (string) ($kunjungan['anamnesa']['keluhanUtama']['keluhanUtama'] ?? ''),
                'keadaanUmum' => (string) ($tandaVital['keadaanUmum'] ?? ''),
                // RJ/UGD mencatat tingkat kesadaran AVPU, bukan angka GCS — dicetak
                // apa adanya supaya tidak ada nilai karangan di kolom GCS.
                'gcs' => (string) ($tandaVital['gcs'] ?? ($tandaVital['tingkatKesadaran'] ?? '')),
                'ttv' => [
                    'tensi' => $sistolik !== '' || $distolik !== '' ? trim($sistolik . '/' . $distolik) . ' mmHg' : '',
                    'nadi' => $this->satuan($tandaVital['frekuensiNadi'] ?? '', 'x/mnt'),
                    'suhu' => $this->satuan($tandaVital['suhu'] ?? '', '°C'),
                    'nafas' => $this->satuan($tandaVital['frekuensiNafas'] ?? '', 'x/mnt'),
                    'spo2' => $this->satuan($tandaVital['spo2'] ?? '', '%'),
                ],
                'kelainan' => trim((string) ($kunjungan['pemeriksaan']['fisik'] ?? '')),
                'alergi' => $this->alergiTeks($kunjungan),
                // Teks bebas tab Penunjang EMR (Laborat / Foto / EKG / lain-lain).
                'penunjang' => trim((string) ($kunjungan['pemeriksaan']['penunjang'] ?? '')),
                'diagnosa' => $this->daftarDiagnosa($kunjungan, $rujukan),
                'kriteria' => $this->daftarKriteria($rujukan),
                'tindakan' => $this->daftarTindakan($kunjungan),
                'terapi' => $this->daftarTerapi($kunjungan),
                // SISRUTE menyimpan 'catatan', jalur FHIR menyimpan 'deskripsi' (CarePlan.description).
                'alasan' => trim((string) (($rujukan['catatan'] ?? '') ?: ($rujukan['deskripsi'] ?? ''))),
            ],
        ];
    }

    /** QR nomor rujukan BPJS (PNG base64) — kosong bila pustaka/GD gagal, surat tetap tercetak. */
    private function qrPng(string $teks): string
    {
        try {
            $qr = new \Milon\Barcode\DNS2D();
            $qr->setStorPath(storage_path('framework/cache/'));

            return (string) $qr->getBarcodePNG($teks, 'QRCODE', 4, 4);
        } catch (\Throwable) {
            return '';
        }
    }

    /** "2026-05-05" / "05/05/2026" → "5 Mei 2026" (gaya contoh BPJS); gagal → apa adanya. */
    private function tanggalPanjang(string $tanggal, string $format): string
    {
        $tanggal = trim($tanggal);
        if ($tanggal === '') {
            return '';
        }
        try {
            return Carbon::createFromFormat($format, $tanggal)->locale('id')->isoFormat('D MMMM YYYY');
        } catch (\Throwable) {
            return $tanggal;
        }
    }

    /** Kode poli BPJS 3 huruf → nama poli lokal (rsmst_polis.kd_poli_bpjs); tak ketemu → kodenya. */
    private function namaPoliBpjs(string $kode): string
    {
        $kode = trim($kode);
        if ($kode === '') {
            return '';
        }
        $nama = DB::table('rsmst_polis')->where('kd_poli_bpjs', $kode)->value('poli_desc');

        return $nama ? trim((string) $nama) . " ({$kode})" : $kode;
    }

    private function tanggalSurat(array $hasil): string
    {
        $tanggal = trim((string) ($hasil['tglRujukan'] ?? ''));
        if ($tanggal !== '') {
            // Jalur SISRUTE menyimpan yyyy-mm-dd; surat memakai dd/mm/yyyy.
            try {
                return Carbon::createFromFormat('Y-m-d', $tanggal)->format('d/m/Y');
            } catch (\Throwable) {
                return $tanggal;
            }
        }

        // Jalur FHIR tidak punya tglRujukan — pakai waktu kirim (sudah dd/mm/yyyy H:i:s).
        return trim(explode(' ', (string) ($hasil['dikirimPada'] ?? ''))[0]);
    }

    private function kodeRegisterPerujuk(): string
    {
        return collect([env('SISRUTE_KDPPK'), env('SATUSEHAT_ORGANIZATION_ID')])
            ->map(fn($kode) => trim((string) $kode))
            ->filter()
            ->implode(' / ');
    }

    private function kodeRegisterTujuan(array $hasil): string
    {
        return collect([$hasil['tujuanPpk'] ?? null, $hasil['tujuanSatuSehat'] ?? ($hasil['tujuanOrgId'] ?? null)])
            ->map(fn($kode) => trim((string) $kode))
            ->filter()
            ->implode(' / ');
    }

    /**
     * Alamat tujuan disimpan ke hasil saat kirim (SISRUTE: alamatPpk kandidat; FHIR:
     * Organization.address). Rujukan yang terbit sebelum kolom itu ada dilengkapi
     * dari Organization SATUSEHAT bila org id-nya tersimpan.
     */
    private function alamatTujuan(array $hasil): string
    {
        $alamat = trim((string) ($hasil['tujuanAlamat'] ?? ''));
        if ($alamat !== '') {
            return $alamat;
        }

        return $this->rujukanAlamatOrganisasi(trim((string) ($hasil['tujuanOrgId'] ?? '')));
    }

    private function umur(array $pasien): string
    {
        $tglLahir = trim((string) ($pasien['pasien']['tglLahir'] ?? ''));
        if ($tglLahir === '') {
            return '';
        }

        try {
            return Carbon::createFromFormat('d/m/Y', $tglLahir)
                ->diff(Carbon::now(config('app.timezone')))
                ->format('%y Th %m Bl %d Hr');
        } catch (\Throwable) {
            return '';
        }
    }

    private function satuan(mixed $nilai, string $satuan): string
    {
        $nilai = trim((string) $nilai);
        return $nilai === '' ? '' : $nilai . ' ' . $satuan;
    }

    /**
     * IV Diagnosa — konsep sama dengan Rekam Medis Open: teks bebas dokter
     * (diagnosisFreeText) diutamakan; bila kosong, keterangan ICD-10 tanpa kode.
     * Bila EMR belum mencatat apa pun, pakai diagnosa yang dikirim bersama rujukan
     * agar halaman 1 (Diagnosis Rujukan) dan halaman 2 tetap sejalan.
     */
    private function daftarDiagnosa(array $kunjungan, array $rujukan): array
    {
        $daftar = $this->freeTextAtauKeterangan($kunjungan, 'diagnosisFreeText', 'diagnosis', 'diagDesc');
        if ($daftar !== []) {
            return $daftar;
        }

        $diagnosaRujukan = trim(trim((string) ($rujukan['kodeDiagnosa'] ?? '')) . ' ' . trim((string) ($rujukan['diagnosaDesc'] ?? '')));

        return $diagnosaRujukan === '' ? [] : [$diagnosaRujukan];
    }

    /**
     * VIII Tindakan Medis — konsep sama dengan Rekam Medis Open: Free Text Procedure
     * diutamakan (dokter sering menulis tindakan tanpa memilih kode ICD-9-CM);
     * bila kosong, keterangan ICD-9-CM tanpa kode.
     */
    private function daftarTindakan(array $kunjungan): array
    {
        return $this->freeTextAtauKeterangan($kunjungan, 'procedureFreeText', 'procedure', 'procedureDesc');
    }

    /** Teks bebas dipecah per baris; bila kosong, kolom keterangan dari daftar terkode. */
    private function freeTextAtauKeterangan(array $kunjungan, string $kunciFreeText, string $kunciDaftar, string $kolomKeterangan): array
    {
        $freeText = collect(preg_split('/\r\n|\r|\n/', (string) ($kunjungan[$kunciFreeText] ?? '')))
            ->map(fn($baris) => trim($baris))
            ->filter()->values()->all();

        if ($freeText !== []) {
            return $freeText;
        }

        return collect($kunjungan[$kunciDaftar] ?? [])
            ->map(fn($baris) => trim((string) ($baris[$kolomKeterangan] ?? '')))
            ->filter()->values()->all();
    }

    /**
     * Terapi diambil dari e-resep (terstruktur). Kalau kosong, teks bebas
     * perencanaan.terapi dipecah per baris — di situ resep ditulis "R/ ..." per baris.
     */
    private function daftarTerapi(array $kunjungan): array
    {
        $eresep = collect($kunjungan['eresep'] ?? [])
            ->map(function ($obat) {
                $nama = trim((string) ($obat['productName'] ?? ''));
                if ($nama === '') {
                    return '';
                }
                // Bentuk signa mengikuti cetak e-resep: "S 1dd1".
                $signaX = trim((string) ($obat['signaX'] ?? ''));
                $signaHari = trim((string) ($obat['signaHari'] ?? ''));
                $signa = $signaX !== '' || $signaHari !== '' ? 'S ' . ($signaX ?: '-') . 'dd' . ($signaHari ?: '-') : '';
                $qty = trim((string) ($obat['qty'] ?? ''));

                return trim($nama . ($qty !== '' ? ' | No. ' . $qty : '') . ($signa !== '' ? ' | ' . $signa : ''));
            })
            ->filter()->values()->all();

        if ($eresep !== []) {
            return $eresep;
        }

        return collect(preg_split('/\r\n|\r|\n/', (string) ($kunjungan['perencanaan']['terapi']['terapi'] ?? '')))
            ->map(fn($baris) => trim($baris))
            ->filter()->values()->all();
    }

    /**
     * Kriteria rujukan yang BENAR-BENAR dikirim: satu item terpilih dari
     * kriteriaList (linkId dinamis per ICD-10). Untuk "Tindakan Medis" jawabannya
     * berupa ICD-9-CM, jadi kodenya ikut dicetak.
     */
    private function daftarKriteria(array $rujukan): array
    {
        $icd9 = trim((string) ($rujukan['kriteriaIcd9'] ?? ''));
        $icd9Desc = trim((string) ($rujukan['kriteriaIcd9Desc'] ?? ''));
        $keteranganIcd9 = $icd9 !== '' ? ' — ' . trim($icd9 . ' ' . $icd9Desc) : '';

        // Jalur FHIR ke IGD: lima pertanyaan gawat darurat, tercetak yang dicentang.
        if (($rujukan['jalur'] ?? '') === 'igd') {
            return collect($rujukan['kriteriaIgd'] ?? [])
                ->filter(fn ($dicentang) => $dicentang === true)
                ->keys()
                ->map(fn ($linkId) => RujukanKompetensiOptions::PERTANYAAN_IGD[$linkId] ?? (string) $linkId)
                ->values()->all();
        }

        // Jalur FHIR ke ranap: satu pilihan terapi / tindakan / upaya diagnosis.
        if (($rujukan['jalur'] ?? '') === 'ranap') {
            $teks = RujukanKompetensiOptions::KRITERIA_RANAP[$rujukan['kriteriaPilih'] ?? ''] ?? '';
            return $teks === '' ? [] : [$teks . $keteranganIcd9];
        }

        // Jalur SISRUTE: satu item terpilih dari kriteriaList (linkId dinamis per ICD-10).
        $terpilih = collect($rujukan['kriteriaList'] ?? [])
            ->firstWhere('linkId', $rujukan['kriteriaPilih'] ?? null);

        $teks = trim((string) ($terpilih['text'] ?? ''));

        return $teks === '' ? [] : [$teks . $keteranganIcd9];
    }

    /**
     * Butir "Alergi (Ada/Tidak) Sebutkan". Ada/Tidak ditentukan HANYA oleh pilihan
     * adaAlergi (Ya/Tidak) — teks bebas alergi sering berisi "Tidak ada alergi",
     * jadi isi teks tidak boleh dianggap tanda ada alergi. Teks dicetak apa adanya.
     */
    private function alergiTeks(array $kunjungan): string
    {
        $alergi = $kunjungan['anamnesa']['alergi'] ?? [];
        $teks = trim((string) ($alergi['alergi'] ?? ''));

        return match (trim((string) ($alergi['adaAlergi'] ?? ''))) {
            'Ya' => $teks !== '' ? 'Ada — ' . $teks : 'Ada',
            'Tidak' => 'Tidak ada',
            // Pilihan belum diisi (data lama): cetak teks apa adanya tanpa menebak.
            default => $teks,
        };
    }

    private function terapiTeks(array $kunjungan): string
    {
        return implode("\n", $this->daftarTerapi($kunjungan));
    }
};
?>

<div></div>
