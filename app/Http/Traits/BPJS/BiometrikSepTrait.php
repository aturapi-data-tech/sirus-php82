<?php

namespace App\Http\Traits\BPJS;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Validasi biometrik BPJS sebelum SEP dibuat — dipakai bersama panel VClaim
 * RJ/UGD/RI (Volt). Butuh host yang memakai VclaimTrait dan punya $SEPForm
 * dengan kunci noKartu, tglSep (d/m/Y), jnsPelayanan, ppkPelayanan.
 *
 * Alur BPJS (katalog VClaim grup SEP/FingerPrint):
 *   1. Status finger peserta per tanggal pelayanan: '1' sudah, '0' belum.
 *   2. Peserta yang gagal/tidak bisa finger (kondisi klinis) boleh lewat
 *      PERTANYAAN ACAK: BPJS memberi daftar faskes, peserta menunjuk FKTP
 *      tempatnya terdaftar + tanggal lahir; jawaban benar = 'True' dan SEP
 *      boleh terbit tanpa finger. Ini jalur resmi untuk pengecualian, bukan
 *      untuk semua pasien — hasilnya dicatat di state supaya bisa diaudit.
 *   3. Tidak semua poli/jenis layanan mewajibkan finger, jadi petugas tetap
 *      boleh "lanjut tanpa validasi"; BPJS sendiri yang menolak bila wajib.
 *
 * Spesifikasi endpoint diambil dari sumber tiruan katalog (katalog resmi
 * balas 500 saat dibaca, 2026-09-11) — bentuk balasan persis baru terbukti
 * di uji dev; parser di sini menerima beberapa varian kunci.
 */
trait BiometrikSepTrait
{
    /**
     * status     : null belum dicek | '1' sudah validasi | '0' belum
     * lolos      : jawaban pertanyaan acak diterima BPJS ('True')
     * lewati     : petugas memilih lanjut tanpa validasi
     * tampil     : blok pertanyaan acak sedang dibuka
     * dicekUntuk : "noKartu|tglSep" saat status diambil — ganti kartu/tanggal = cek ulang
     */
    public array $biometrik = [
        'status' => null,
        'keterangan' => '',
        'faskesList' => [],
        'tglLahir' => '',
        'lolos' => false,
        'lewati' => false,
        'tampil' => false,
        'dicekUntuk' => '',
    ];

    public function resetBiometrik(): void
    {
        $this->biometrik = [
            'status' => null,
            'keterangan' => '',
            'faskesList' => [],
            'tglLahir' => '',
            'lolos' => false,
            'lewati' => false,
            'tampil' => false,
            'dicekUntuk' => '',
        ];
    }

    protected function biometrikNoKartu(): string
    {
        return trim((string) ($this->SEPForm['noKartu'] ?? ''));
    }

    /** tglSep form d/m/Y → Y-m-d; kosong = hari ini. */
    protected function biometrikTglSep(): string
    {
        $tgl = trim((string) ($this->SEPForm['tglSep'] ?? ''));
        try {
            return $tgl !== '' ? Carbon::createFromFormat('d/m/Y', $tgl)->format('Y-m-d') : Carbon::now()->format('Y-m-d');
        } catch (\Throwable) {
            return Carbon::now()->format('Y-m-d');
        }
    }

    protected function biometrikKunci(): string
    {
        return $this->biometrikNoKartu() . '|' . $this->biometrikTglSep();
    }

    /**
     * Ambil status finger dari BPJS. Mengembalikan '1' / '0' / null (gagal).
     */
    public function cekBiometrik(bool $senyap = false): ?string
    {
        $noKartu = $this->biometrikNoKartu();
        if ($noKartu === '') {
            if (!$senyap) {
                $this->dispatch('toast', type: 'error', message: 'Nomor kartu BPJS kosong — pilih rujukan/peserta dulu.');
            }
            return null;
        }

        $respon = $this->sep_fingerprint_status($noKartu, $this->biometrikTglSep())->getOriginalContent();
        $code = (int) ($respon['metadata']['code'] ?? 0);
        $data = $respon['response'] ?? null;

        if ($code !== 200 || !is_array($data)) {
            $this->biometrik['status'] = null;
            $this->biometrik['keterangan'] = (string) ($respon['metadata']['message'] ?? 'Gagal membaca status finger print.');
            if (!$senyap) {
                $this->dispatch('toast', type: 'error', message: "Cek biometrik gagal ({$code}): " . $this->biometrik['keterangan']);
            }
            return null;
        }

        // Dua varian bentuk yang mungkin: {kode:'1', status:'Sudah ...'} atau {status:{kode:'1', keterangan:'...'}}.
        $statusMentah = $data['status'] ?? null;
        $kode = (string) ($data['kode'] ?? (is_array($statusMentah) ? ($statusMentah['kode'] ?? '') : ''));
        $keterangan = is_array($statusMentah) ? ($statusMentah['keterangan'] ?? '') : ($statusMentah ?? ($data['keterangan'] ?? ''));
        $this->biometrik['status'] = $kode === '1' ? '1' : '0';
        $this->biometrik['keterangan'] = trim((string) $keterangan);
        $this->biometrik['dicekUntuk'] = $this->biometrikKunci();
        $this->biometrik['lolos'] = false;
        $this->biometrik['lewati'] = false;
        $this->biometrik['tampil'] = $this->biometrik['status'] === '0';

        if (!$senyap) {
            $this->dispatch('toast', type: $this->biometrik['status'] === '1' ? 'success' : 'warning', message: $this->biometrik['status'] === '1' ? 'Peserta SUDAH validasi biometrik.' : 'Peserta BELUM validasi biometrik — jawab pertanyaan acak atau lanjut bila layanan tidak mewajibkan.');
        }

        return $this->biometrik['status'];
    }

    /** Daftar faskes untuk pertanyaan acak "FKTP tempat terdaftar". */
    public function muatPertanyaanBiometrik(): void
    {
        $noKartu = $this->biometrikNoKartu();
        if ($noKartu === '') {
            $this->dispatch('toast', type: 'error', message: 'Nomor kartu BPJS kosong.');
            return;
        }

        $respon = $this->sep_fingerprint_randomquestion($noKartu, $this->biometrikTglSep())->getOriginalContent();
        $code = (int) ($respon['metadata']['code'] ?? 0);
        $data = $respon['response'] ?? null;

        if ($code !== 200 || !is_array($data)) {
            $this->dispatch('toast', type: 'error', message: "Pertanyaan acak gagal dimuat ({$code}): " . ($respon['metadata']['message'] ?? '-'));
            return;
        }

        $mentah = $data['faskes'] ?? ($data['list'] ?? (array_is_list($data) ? $data : []));
        $this->biometrik['faskesList'] = collect($mentah)
            ->filter(fn($f) => is_array($f))
            ->map(fn($f) => [
                'kode' => (string) ($f['kode'] ?? ($f['kdProvider'] ?? ($f['kdppk'] ?? ''))),
                'nama' => (string) ($f['nama'] ?? ($f['nmProvider'] ?? ($f['nmppk'] ?? ''))),
            ])
            ->filter(fn($f) => $f['kode'] !== '')
            ->values()
            ->all();
        $this->biometrik['tampil'] = true;

        if ($this->biometrik['tglLahir'] === '') {
            $this->biometrik['tglLahir'] = $this->biometrikTglLahirAwal();
        }

        if (empty($this->biometrik['faskesList'])) {
            $this->dispatch('toast', type: 'warning', message: 'BPJS tidak memberi pilihan faskes untuk pertanyaan acak.');
        }
    }

    /**
     * Tanggal lahir awal dari MASTER PASIEN RS (rsmst_pasiens.birth_date lewat
     * noMR = reg_no) — bukan dari balasan BPJS, supaya jawaban tetap berasal
     * dari rekam kita/pasien. Kosong bila tidak ketemu; petugas mengisi manual.
     */
    protected function biometrikTglLahirAwal(): string
    {
        $regNo = trim((string) ($this->SEPForm['noMR'] ?? ''));
        if ($regNo === '') {
            return '';
        }
        try {
            $nilai = DB::table('rsmst_pasiens')->where('reg_no', $regNo)->value('birth_date');
            return filled($nilai) ? Carbon::parse($nilai)->format('Y-m-d') : '';
        } catch (\Throwable) {
            return '';
        }
    }

    /** Kirim jawaban: faskes terpilih (indeks) + tanggal lahir yang diisi. */
    public function jawabBiometrik(int $index): void
    {
        $faskes = $this->biometrik['faskesList'][$index] ?? null;
        if (!$faskes) {
            $this->dispatch('toast', type: 'error', message: 'Pilihan faskes tidak dikenal.');
            return;
        }

        $tglLahir = trim((string) $this->biometrik['tglLahir']);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tglLahir)) {
            $this->dispatch('toast', type: 'error', message: 'Isi tanggal lahir peserta (yyyy-mm-dd) untuk jawaban pertanyaan acak.');
            return;
        }

        $respon = $this->sep_fingerprint_randomanswer([
            'noKartu' => $this->biometrikNoKartu(),
            'tglSep' => $this->biometrikTglSep(),
            'jenPel' => (string) ($this->SEPForm['jnsPelayanan'] ?? '2'),
            'ppkPelSep' => (string) ($this->SEPForm['ppkPelayanan'] ?? ''),
            'tglLahir' => $tglLahir,
            'ppkPst' => $faskes['kode'],
            'user' => (string) (auth()->user()->name ?? 'sirus App'),
        ])->getOriginalContent();

        $code = (int) ($respon['metadata']['code'] ?? 0);
        $data = $respon['response'] ?? null;
        $benar = is_string($data) ? strtolower(trim($data, " \"")) === 'true' : (bool) ($data['status'] ?? ($data['result'] ?? false));

        if ($code !== 200) {
            $this->dispatch('toast', type: 'error', message: "Jawaban ditolak BPJS ({$code}): " . ($respon['metadata']['message'] ?? '-'));
            return;
        }

        if ($benar) {
            $this->biometrik['lolos'] = true;
            $this->biometrik['tampil'] = false;
            $this->biometrik['keterangan'] = 'Lolos pertanyaan acak: ' . $faskes['nama'];
            $this->dispatch('toast', type: 'success', message: 'Jawaban BENAR — peserta lolos validasi lewat pertanyaan acak. Lanjut buat SEP.');
            return;
        }

        $this->dispatch('toast', type: 'error', message: 'Jawaban SALAH menurut BPJS. Coba faskes lain, atau minta peserta validasi sidik jari / surat petugas BPJS.');
    }

    /** Petugas memilih lanjut tanpa validasi — BPJS yang menolak bila layanan mewajibkan. */
    public function lewatiBiometrik(): void
    {
        $this->biometrik['lewati'] = true;
        $this->biometrik['tampil'] = false;
        $this->dispatch('toast', type: 'info', message: 'Lanjut tanpa validasi biometrik — BPJS akan menolak SEP bila layanan ini mewajibkan finger print.');
    }

    public function tutupBiometrik(): void
    {
        $this->biometrik['tampil'] = false;
    }

    public function bukaBiometrik(): void
    {
        $this->biometrik['tampil'] = true;
        if (empty($this->biometrik['faskesList'])) {
            $this->muatPertanyaanBiometrik();
        }
    }

    /**
     * Gerbang sebelum insert SEP. true = boleh lanjut. false = status '0'
     * dan belum lolos/dilewati — blok pertanyaan acak dibuka, host harus return.
     */
    protected function biometrikSiap(): bool
    {
        $kunciBerubah = $this->biometrik['dicekUntuk'] !== '' && $this->biometrik['dicekUntuk'] !== $this->biometrikKunci();
        if ($kunciBerubah) {
            $this->resetBiometrik();
        }

        if ($this->biometrik['lolos'] || $this->biometrik['lewati']) {
            return true;
        }

        $status = $this->biometrik['status'] ?? $this->cekBiometrik(senyap: true);

        // Gagal membaca status (BPJS down / timeout) tidak boleh mengunci pendaftaran.
        if ($status === null || $status === '1') {
            return true;
        }

        $this->biometrik['tampil'] = true;
        $this->dispatch('toast', type: 'warning', message: 'Peserta BELUM validasi biometrik. Jawab pertanyaan acak, atau pilih "Lanjut tanpa validasi" bila layanan tidak mewajibkan finger print.');
        return false;
    }

    /** Ringkasan untuk audit log / keterangan SEP. */
    protected function biometrikRingkas(): string
    {
        return match (true) {
            (bool) $this->biometrik['lolos'] => 'biometrik: lolos pertanyaan acak',
            (bool) $this->biometrik['lewati'] => 'biometrik: dilewati petugas',
            $this->biometrik['status'] === '1' => 'biometrik: tervalidasi',
            $this->biometrik['status'] === '0' => 'biometrik: belum validasi',
            default => 'biometrik: tidak dicek',
        };
    }
}
