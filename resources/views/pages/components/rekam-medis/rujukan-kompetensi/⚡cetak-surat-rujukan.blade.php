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
    use EmrRJTrait, EmrUGDTrait, EmrRITrait, MasterPasienTrait;

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

        return [
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
                'alamat' => '',
            ],

            'pasien' => [
                'nama' => (string) ($pasien['pasien']['regName'] ?? ''),
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
                'diagnosa' => $this->daftarDiagnosa($kunjungan),
                'kriteria' => $this->daftarKriteria($rujukan),
                'tindakan' => $this->daftarTindakan($kunjungan),
                'terapi' => $this->daftarTerapi($kunjungan),
                'alasan' => trim((string) ($rujukan['catatan'] ?? '')),
            ],
        ];
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

    /** Diagnosa EMR: icdX bila ada, jatuh ke diagId (lihat skill diagnosa-flow). */
    private function daftarDiagnosa(array $kunjungan): array
    {
        return collect($kunjungan['diagnosis'] ?? [])
            ->map(function ($baris) {
                $kode = trim((string) ($baris['icdX'] ?? ($baris['diagId'] ?? '')));
                $desc = trim((string) ($baris['diagDesc'] ?? ''));
                return trim($kode . ' ' . $desc);
            })
            ->filter()->values()->all();
    }

    private function daftarTindakan(array $kunjungan): array
    {
        return collect($kunjungan['procedure'] ?? [])
            ->map(function ($baris) {
                $kode = trim((string) ($baris['procedureId'] ?? ''));
                $desc = trim((string) ($baris['procedureDesc'] ?? ''));
                return trim($kode . ' ' . $desc);
            })
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
        $terpilih = collect($rujukan['kriteriaList'] ?? [])
            ->firstWhere('linkId', $rujukan['kriteriaPilih'] ?? null);

        if (empty($terpilih)) {
            return [];
        }

        $teks = trim((string) ($terpilih['text'] ?? ''));
        $icd9 = trim((string) ($rujukan['kriteriaIcd9'] ?? ''));
        $icd9Desc = trim((string) ($rujukan['kriteriaIcd9Desc'] ?? ''));

        if ($icd9 !== '') {
            $teks = trim($teks . ' — ' . trim($icd9 . ' ' . $icd9Desc));
        }

        return $teks === '' ? [] : [$teks];
    }

    private function terapiTeks(array $kunjungan): string
    {
        return implode("\n", $this->daftarTerapi($kunjungan));
    }
};
?>

<div></div>
