<?php
// resources/views/pages/components/rekam-medis/ri/ringkasan-pulang-ri/ringkasan-pulang-ri-actions.blade.php
//
// Ringkasan Pemulangan Pasien — DIISI PERAWAT/BIDAN (beda dgn Resume Medis dokter).
// Pola sama persis Resume Medis: editor TinyMCE (HTML), template auto pre-fill dari
// data EMR, disimpan sebagai HTML string di datadaftarri_json.ringkasanPulang,
// cetak PDF via ringkasan-pulang-ri-print (raw HTML + footer 3 TTD).
//
// TTD (sejak 2026-09-28, pola modul dokumen varian multi-TTD): tiga penanda tangan di
// `datadaftarri_json.ringkasanPulangTtd` — diserahkan (stempel perawat/bidan login),
// penerima (signature-pad pasien/keluarga, gambar ke RSTXN_TTDS via TtdPasien), disetujui
// (stempel Ka.Ru/PJ Shift/Ka.Tim login). Terkunci OTOMATIS begitu ketiganya lengkap.
// Buka Kunci (Gate dokumen.bukaKunci) mencabut TTD PETUGAS saja; TTD penerima dipertahankan.

use Livewire\Component;
use Livewire\Attributes\On;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use App\Support\TtdPasien;
use Carbon\Carbon;
use App\Http\Traits\Txn\Ri\EmrRITrait;
use App\Support\Terminologi\DischargeDisposition;
use App\Http\Traits\Master\MasterPasien\MasterPasienTrait;

new class extends Component {
    use EmrRITrait, MasterPasienTrait;

    public ?int $riHdrNo = null;
    public string $ringkasanPulang = '';

    /** true = ketiga TTD lengkap → editor & Simpan ditutup. */
    public bool $isFormLocked = false;

    /** ['diserahkan' => [nama,kode,waktu], 'penerima' => [nama,hubungan,ttd,waktu], 'disetujui' => [nama,kode,waktu]] */
    public array $ttd = [];

    /** Isian penerima sebelum pad ditandatangani. */
    public string $penerimaNama = '';
    public string $penerimaHubungan = 'Pasien Sendiri';
    public ?string $regNo = null;

    public array $hubunganOptions = ['Pasien Sendiri', 'Suami', 'Istri', 'Ayah', 'Ibu', 'Anak', 'Saudara', 'Wali Hukum', 'Lainnya'];

    /* ═══════════════ OPEN ═══════════════ */
    #[On('ringkasan-pulang-ri.open')]
    public function open(int $riHdrNo): void
    {
        $this->riHdrNo = $riHdrNo;
        $this->resetValidation();

        $dataRI = $this->findDataRI($riHdrNo);
        if (empty($dataRI)) {
            $this->dispatch('toast', type: 'error', message: 'Data Rawat Inap tidak ditemukan.');
            return;
        }

        $this->regNo = (string) ($dataRI['regNo'] ?? '');
        $this->muatTtd($dataRI);

        $existing = (string) data_get($dataRI, 'ringkasanPulang', '');
        $this->ringkasanPulang = $existing !== '' ? $existing : $this->buildPreFilledTemplate($dataRI);

        $this->dispatch('open-modal', name: 'ringkasan-pulang-ri');
    }

    /* ═══════════════ RESET ═══════════════ */
    public function resetToDefault(): void
    {
        if (empty($this->riHdrNo)) {
            $this->dispatch('toast', type: 'error', message: 'Sesi expired, buka ulang dari EMR RI.');
            return;
        }
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Ringkasan sudah lengkap ditandatangani — terkunci.');
            return;
        }
        $dataRI = $this->findDataRI($this->riHdrNo);
        if (empty($dataRI)) {
            $this->dispatch('toast', type: 'error', message: 'Data RI tidak ditemukan.');
            return;
        }
        $this->ringkasanPulang = $this->buildPreFilledTemplate($dataRI);
        $this->dispatch('ringkasan-pulang-ri.reload');
        $this->dispatch('toast', type: 'success', message: 'Template di-reset dari data EMR terbaru.');
    }

    /**
     * Build template HTML "Ringkasan Pemulangan Pasien" dgn value pre-filled
     * best-effort dari JSON RI. Perawat tinggal lengkapi sisanya di editor.
     */
    private function buildPreFilledTemplate(array $dataRI): string
    {
        $esc = fn($v) => e(trim((string) $v));

        // Diagnosa utama / komorbid
        $dxList = collect(data_get($dataRI, 'diagnosis', []));
        $byKat = fn(array $kw) => $dxList->first(function ($d) use ($kw) {
            $k = strtolower((string) data_get($d, 'kategoriDiagnosa', ''));
            foreach ($kw as $w) {
                if (str_contains($k, $w)) return true;
            }
            return false;
        });
        $dxUtama = $byKat(['utama', 'primer', 'primary']);
        $dxKomor = $byKat(['komorbid', 'sekunder', 'secondary']);
        $diagMasuk = $esc(data_get($dataRI, 'pengkajianAwalPasienRawatInap.bagian1DataUmum.diagnosaMasuk', ''));
        $diagnosa = $esc(data_get($dxUtama, 'descDiagnosa', '')) ?: $diagMasuk;
        $komorbid = $esc(data_get($dxKomor, 'descDiagnosa', ''));

        // Tanda Vital ← entri Observasi Lanjutan TERBARU (kondisi terkini saat pulang),
        // bukan TTV saat masuk. Pilih waktuPemeriksaan paling akhir.
        $obsList = collect(data_get($dataRI, 'observasi.observasiLanjutan.tandaVital', []));
        $lastObs = $obsList->sortBy(function ($o) {
            try {
                return Carbon::createFromFormat('d/m/Y H:i:s', (string) data_get($o, 'waktuPemeriksaan', '01/01/2000 00:00:00'))->timestamp;
            } catch (\Throwable) {
                return 0;
            }
        })->last() ?? [];
        $sis = trim((string) data_get($lastObs, 'sistolik', ''));
        $dis = trim((string) data_get($lastObs, 'distolik', ''));
        $td = $sis !== '' || $dis !== '' ? trim($sis . '/' . $dis) : '';
        $nadi = $esc(data_get($lastObs, 'frekuensiNadi', ''));
        $suhu = $esc(data_get($lastObs, 'suhu', ''));
        $nafas = $esc(data_get($lastObs, 'frekuensiNafas', ''));
        // Keadaan umum tidak ada di observasi lanjutan → ambil dari pengkajian awal (jika ada).
        $keadaanUmum = $esc(data_get($dataRI, 'pengkajianAwalPasienRawatInap.bagian4PemeriksaanFisik.tandaVital.keadaanUmum', ''));

        // Tindakan / prosedur
        $tindakan = collect(data_get($dataRI, 'procedureICDList', []))
            ->map(fn($p) => trim((string) data_get($p, 'descProcedure', '')))
            ->filter()
            ->map(fn($d) => e($d))
            ->implode('<br>');

        // Obat saat pulang ← E-RESEP TERAKHIR (resep pulang), bukan gabungan semua resep
        // selama dirawat. Pilih header dgn resepDate paling akhir.
        $eresepHdrs = (array) data_get($dataRI, 'eresepHdr', []);
        $lastHdr = collect($eresepHdrs)->sortBy(function ($h) {
            try {
                return Carbon::createFromFormat('d/m/Y H:i:s', (string) data_get($h, 'resepDate', '01/01/2000 00:00:00'))->timestamp;
            } catch (\Throwable) {
                return 0;
            }
        })->last() ?? [];

        $obatRows = '';
        foreach ((array) data_get($lastHdr, 'eresep', []) as $eo) {
            $nama = trim((string) data_get($eo, 'productName', ''));
            if ($nama === '') continue;
            $signaX = trim((string) data_get($eo, 'signaX', ''));
            $signaHari = trim((string) data_get($eo, 'signaHari', ''));
            $frek = $signaX !== '' || $signaHari !== '' ? trim($signaX . ' x ' . $signaHari) : '';
            $obatRows .=
                '<tr>' .
                '<td>' . e($nama) . '</td>' .
                '<td>' . e((string) data_get($eo, 'qty', '')) . '</td>' .
                '<td></td>' .
                '<td></td>' .
                '<td>' . ($frek === 'x' ? '' : e($frek)) . '</td>' .
                '<td></td>' .
                '<td>' . e((string) data_get($eo, 'catatanKhusus', '')) . '</td>' .
                '</tr>';
        }
        if ($obatRows === '') {
            $obatRows = '<tr><td>&nbsp;</td><td></td><td></td><td></td><td></td><td></td><td></td></tr>'
                . '<tr><td>&nbsp;</td><td></td><td></td><td></td><td></td><td></td><td></td></tr>';
        }

        // Kondisi pulang ← kunci internal status pulang. Label diambil dari SUMBER TUNGGAL
        // (App\Support\Terminologi\DischargeDisposition) — dulu file ini punya peta sendiri yang
        // BERTENTANGAN dgn form ('371828006' ditulis "Membaik", form: "Pulang Tanpa Perbaikan").
        $kondisiPulang = e(DischargeDisposition::label((string) data_get($dataRI, 'perencanaan.tindakLanjut.tindakLanjut', '')));

        // Kontrol ← surat kontrol (SKDP) di datadaftarri_json.kontrol
        $kontrol = (array) data_get($dataRI, 'kontrol', []);
        $tglKontrolRaw = trim((string) data_get($kontrol, 'tglKontrol', ''));
        $kontrolHariTgl = '';
        if ($tglKontrolRaw !== '') {
            try {
                $kontrolHariTgl = Carbon::createFromFormat('d/m/Y', $tglKontrolRaw)->locale('id')->translatedFormat('l, d/m/Y');
            } catch (\Throwable) {
                $kontrolHariTgl = $tglKontrolRaw;
            }
        }
        $kontrolHariTgl = e($kontrolHariTgl);
        $poliKontrol = trim((string) data_get($kontrol, 'poliKontrolDesc', ''));
        $drKontrol = trim((string) data_get($kontrol, 'drKontrolDesc', ''));
        $tempatKontrol = e(trim($poliKontrol . ($drKontrol !== '' ? ' — ' . $drKontrol : '')));

        // Baris tabel 2-kolom (Label | Value) ber-garis — pola sama Resume Medis.
        $row = fn(string $label, string $value) =>
            "<tr><td class=\"text-muted\" style=\"width: 210px; border: 1px solid #cbd5e1; padding: 3px 6px; vertical-align: top;\">{$label}</td>" .
            "<td style=\"border: 1px solid #cbd5e1; padding: 3px 6px; vertical-align: top;\">{$value}</td></tr>";

        $ttv = 'TD: ' . ($td !== '' ? $td : '___') . ' mmHg &nbsp;&nbsp; Nadi: ' . ($nadi !== '' ? $nadi : '___')
            . ' x/mnt &nbsp;&nbsp; Suhu: ' . ($suhu !== '' ? $suhu : '___') . ' °C &nbsp;&nbsp; Pernafasan: '
            . ($nafas !== '' ? $nafas : '___') . ' x/mnt';

        $obatTable = '<table style="border-collapse:collapse;width:100%;"><thead><tr>'
            . '<th>Nama Obat</th><th>Jumlah</th><th>Dosis</th><th>Cara Pemberian</th>'
            . '<th>Frekuensi</th><th>Jam</th><th>Petunjuk Khusus</th>'
            . '</tr></thead><tbody>' . $obatRows . '</tbody></table>';

        // Discharge Planning ← entri terstruktur perencanaan.dischargePlanning.
        // Record lama tak punya entri (array-nya dulu tak pernah bisa diisi form) → jatuh ke
        // teks keterangan lama supaya isinya tidak hilang dari cetakan.
        $dp = (array) data_get($dataRI, 'perencanaan.dischargePlanning', []);

        $dpBlok = function (string $node, string $flagKey, string $ketKey, string $dataKey, callable $baris) use ($dp) {
            $n = (array) data_get($dp, $node, []);
            $flag = trim((string) data_get($n, $flagKey, ''));
            if ($flag !== '' && strcasecmp($flag, 'Ada') !== 0) {
                return e($flag); // "Tidak Ada"
            }
            $items = collect((array) data_get($n, $dataKey, []))->map($baris)->filter()->values();
            if ($items->isNotEmpty()) {
                return $items->map(fn($t, $i) => ($i + 1) . ') ' . $t)->implode('<br>');
            }
            return e(trim((string) data_get($n, $ketKey, ''))); // fallback teks lama
        };

        $pelayananCell = $dpBlok(
            'pelayananBerkelanjutan', 'pelayananBerkelanjutan', 'ketPelayananBerkelanjutan', 'pelayananBerkelanjutanData',
            function ($r) {
                $t = trim((string) data_get($r, 'jenisPelayanan', ''));
                if ($t === '') return null;
                $tempat = trim((string) data_get($r, 'tempatFasyankes', ''));
                $tgl = trim((string) data_get($r, 'tglRencana', ''));
                $ket = trim((string) data_get($r, 'ketJenis', ''));
                if ($tempat !== '') $t .= ' — ' . $tempat;
                if ($tgl !== '') $t .= ' (' . $tgl . ')';
                if ($ket !== '') $t .= ': ' . $ket;
                return e($t);
            },
        );

        $alatBantuCell = $dpBlok(
            'penggunaanAlatBantu', 'penggunaanAlatBantu', 'ketPenggunaanAlatBantu', 'penggunaanAlatBantuData',
            function ($r) {
                $t = trim((string) data_get($r, 'jenisAlat', ''));
                if ($t === '') return null;
                $sumber = trim((string) data_get($r, 'sumberAlat', ''));
                $durasi = trim((string) data_get($r, 'durasi', ''));
                $ket = trim((string) data_get($r, 'ketAlat', ''));
                $extra = array_filter([$sumber, $durasi]);
                if ($extra) $t .= ' — ' . implode(', ', $extra);
                if ($ket !== '') $t .= ': ' . $ket;
                return e($t);
            },
        );

        $dokumenCell = '1) Hasil Lab: ___ Lbr<br>2) Foto Rontgen / CT Scan / MRI: ___ Lbr<br>3) USG / ECG: ___ Lbr<br>'
            . '4) Surat Asuransi: Ya / Tidak<br>5) Surat Ket. Sakit/Opname/Istirahat: Ya / Tidak<br>'
            . '6) Surat Kematian: Ya / Tidak<br>7) Surat Ket. Kelahiran: Ya / Tidak<br>8) Lain-lain: ';

        $rows = implode("\n", [
            '<tr><th colspan="2" style="border: 1px solid #cbd5e1; padding: 4px 6px; background:#f3f4f6; text-align:center;">OLEH PERAWAT / BIDAN</th></tr>',
            $row('Keadaan Waktu Masuk', ''),
            $row('Diagnosa', $diagnosa),
            $row('Komorbid', $komorbid),
            $row('Keadaan Umum', $keadaanUmum),
            $row('Tanda Vital', $ttv),
            $row('Tindakan Diagnostik &amp; Prosedur Terapi', $tindakan ?: ''),
            $row('Obat Saat Dirawat Inap', ''),
            $row('Efek Terapi Yang Diberikan', ''),
            $row('Obat Yang Diberikan Saat Pulang', $obatTable),
            $row('Dokumen Yang Diserahkan', $dokumenCell),
            $row('Keadaan Pasien Saat Pulang', $kondisiPulang),
            $row('Kontrol Ulang — Hari, Tanggal', $kontrolHariTgl),
            $row('Tempat Kontrol', $tempatKontrol),
            $row('Pelayanan Berkelanjutan', $pelayananCell),
            $row('Alat Bantu Yang Digunakan', $alatBantuCell),
            $row('Pendidikan Kesehatan', ''),
            $row('Diit', ''),
            $row('Catatan Khusus Untuk Pasien', ''),
        ]);

        return '<table class="w-full" style="border-collapse: collapse;">' . "\n" . $rows . "\n" . '</table>';
    }

    /* ═══════════════ SAVE ═══════════════ */
    public function save(): void
    {
        if (empty($this->riHdrNo)) {
            $this->dispatch('toast', type: 'error', message: 'Sesi expired, buka ulang dari EMR RI.');
            return;
        }
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Ringkasan sudah lengkap ditandatangani — terkunci.');
            return;
        }

        $plain = trim(strip_tags((string) $this->ringkasanPulang));
        if (mb_strlen($plain) < 5) {
            $this->addError('ringkasanPulang', 'Ringkasan harus diisi (minimal 5 karakter teks).');
            return;
        }

        $this->validate(
            ['ringkasanPulang' => 'required|string|max:65000'],
            ['ringkasanPulang.required' => 'Ringkasan harus diisi.'],
        );

        $dataRI = $this->findDataRI($this->riHdrNo);
        if (empty($dataRI)) {
            $this->dispatch('toast', type: 'error', message: 'Data RI tidak ditemukan.');
            return;
        }

        try {
            DB::transaction(function () use (&$dataRI) {
                $this->lockRIRow($this->riHdrNo);
                $dataRI['ringkasanPulang'] = $this->ringkasanPulang;
                $dataRI['ringkasanPulangSavedBy'] = auth()->user()->myuser_name ?? '';
                $dataRI['ringkasanPulangSavedAt'] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
                $this->updateJsonRI($this->riHdrNo, $dataRI);
                $this->appendAdminLogRI((int) $this->riHdrNo, 'Simpan Ringkasan Pemulangan Pasien (perawat)', 'MR');
            });
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal simpan: ' . $e->getMessage());
            return;
        }

        $this->dispatch('toast', type: 'success', message: 'Draft ringkasan pemulangan tersimpan — belum lengkap ditandatangani.');
    }

    /* ═══════════════ TANDA TANGAN ═══════════════ */

    private function muatTtd(array $dataRI): void
    {
        $this->ttd = (array) data_get($dataRI, 'ringkasanPulangTtd', []);
        $this->penerimaNama = (string) data_get($this->ttd, 'penerima.nama', '') ?: (string) ($dataRI['regName'] ?? '');
        $this->penerimaHubungan = (string) data_get($this->ttd, 'penerima.hubungan', '') ?: 'Pasien Sendiri';
        $this->isFormLocked = $this->ttdLengkap($this->ttd);
    }

    private function ttdLengkap(array $ttd): bool
    {
        return !empty(data_get($ttd, 'diserahkan.nama'))
            && !empty(data_get($ttd, 'penerima.ttd'))
            && !empty(data_get($ttd, 'disetujui.nama'));
    }

    private function stempelSaya(): array
    {
        return [
            'nama' => (string) (auth()->user()->myuser_name ?? ''),
            'kode' => (string) (auth()->user()->myuser_code ?? ''),
            'waktu' => Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s'),
        ];
    }

    /**
     * Tulis satu perubahan TTD + isi editor saat ini dalam satu transaksi.
     * $ubah menerima array TTD terbaru dari DB (bukan state komponen) lalu mengembalikannya.
     */
    private function simpanTtd(callable $ubah, string $log): bool
    {
        if (empty($this->riHdrNo)) {
            $this->dispatch('toast', type: 'error', message: 'Sesi expired, buka ulang dari EMR RI.');
            return false;
        }
        $plain = trim(strip_tags((string) $this->ringkasanPulang));
        if (mb_strlen($plain) < 5) {
            $this->addError('ringkasanPulang', 'Ringkasan harus diisi sebelum ditandatangani.');
            $this->dispatch('toast', type: 'error', message: 'Ringkasan masih kosong.');
            return false;
        }

        try {
            DB::transaction(function () use ($ubah, $log) {
                $this->lockRIRow($this->riHdrNo);
                $dataRI = $this->findDataRI($this->riHdrNo);
                if (empty($dataRI)) {
                    throw new \RuntimeException('Data RI tidak ditemukan.');
                }
                $ttdDb = (array) data_get($dataRI, 'ringkasanPulangTtd', []);
                if ($this->ttdLengkap($ttdDb)) {
                    throw new \RuntimeException('Ringkasan sudah lengkap ditandatangani — terkunci.');
                }
                $dataRI['ringkasanPulangTtd'] = $ubah($ttdDb);
                $dataRI['ringkasanPulang'] = $this->ringkasanPulang;
                $dataRI['ringkasanPulangSavedBy'] = auth()->user()->myuser_name ?? '';
                $dataRI['ringkasanPulangSavedAt'] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
                $this->updateJsonRI($this->riHdrNo, $dataRI);
                $this->appendAdminLogRI((int) $this->riHdrNo, $log, 'MR');
                $this->muatTtd($dataRI);
            });
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal: ' . $e->getMessage());
            return false;
        }

        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'success', message: 'Ketiga TTD lengkap — ringkasan pemulangan terkunci.');
        }

        return true;
    }

    /** Dokumen perawat: TTD petugas (Diserahkan & Disetujui) hanya role Perawat — sejajar Resume Medis = Dokter. */
    public function bolehTtdPetugas(): bool
    {
        return (bool) auth()->user()?->hasRole('Perawat');
    }

    private function tolakBukanPerawat(): bool
    {
        if ($this->bolehTtdPetugas()) {
            return false;
        }
        $this->dispatch('toast', type: 'error', message: 'TTD Ringkasan Pemulangan hanya untuk perawat / bidan.');

        return true;
    }

    public function ttdDiserahkan(): void
    {
        if ($this->tolakBukanPerawat()) {
            return;
        }
        $stempel = $this->stempelSaya();
        if ($this->simpanTtd(fn(array $ttd) => array_merge($ttd, ['diserahkan' => $stempel]), 'TTD Diserahkan Ringkasan Pemulangan — ' . $stempel['nama']) && !$this->isFormLocked) {
            $this->dispatch('toast', type: 'success', message: 'TTD yang menyerahkan tersimpan.');
        }
    }

    public function ttdDisetujui(): void
    {
        if ($this->tolakBukanPerawat()) {
            return;
        }
        $stempel = $this->stempelSaya();
        if ($this->simpanTtd(fn(array $ttd) => array_merge($ttd, ['disetujui' => $stempel]), 'TTD Disetujui Ringkasan Pemulangan — ' . $stempel['nama']) && !$this->isFormLocked) {
            $this->dispatch('toast', type: 'success', message: 'TTD yang menyetujui tersimpan.');
        }
    }

    /** Hapus stempel sebelum terkunci — hanya pemilik stempel atau pemegang Gate bukaKunci. */
    private function hapusStempel(string $peran, string $label): void
    {
        $kodeSaya = (string) (auth()->user()->myuser_code ?? '');
        $pemilik = (string) data_get($this->ttd, "{$peran}.kode", '');
        if (!($kodeSaya !== '' && $kodeSaya === $pemilik) && !Gate::allows('dokumen.bukaKunci')) {
            $this->dispatch('toast', type: 'error', message: "Hanya penanda tangan sendiri yang bisa menghapus TTD {$label}.");
            return;
        }
        if ($this->simpanTtd(function (array $ttd) use ($peran) {
            unset($ttd[$peran]);
            return $ttd;
        }, "Hapus TTD {$label} Ringkasan Pemulangan")) {
            $this->dispatch('toast', type: 'success', message: "TTD {$label} dihapus.");
        }
    }

    public function hapusTtdDiserahkan(): void
    {
        $this->hapusStempel('diserahkan', 'yang menyerahkan');
    }

    public function hapusTtdDisetujui(): void
    {
        $this->hapusStempel('disetujui', 'yang menyetujui');
    }

    public function setSignaturePenerima(string $dataUrl): void
    {
        if ($this->isFormLocked) {
            return;
        }
        if (trim($this->penerimaNama) === '') {
            $this->addError('penerimaNama', 'Isi nama penerima dulu sebelum tanda tangan.');
            $this->dispatch('toast', type: 'error', message: 'Nama penerima wajib diisi.');
            return;
        }
        $this->resetErrorBag('penerimaNama');

        $penerima = [
            'nama' => trim($this->penerimaNama),
            'hubungan' => in_array($this->penerimaHubungan, $this->hubunganOptions, true) ? $this->penerimaHubungan : 'Lainnya',
            'ttd' => TtdPasien::simpan($dataUrl, $this->regNo),   // gambar ke RSTXN_TTDS, JSON cukup referensi
            'waktu' => Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s'),
        ];
        if ($this->simpanTtd(fn(array $ttd) => array_merge($ttd, ['penerima' => $penerima]), 'TTD Penerima Ringkasan Pemulangan — ' . $penerima['nama']) && !$this->isFormLocked) {
            $this->dispatch('toast', type: 'success', message: 'TTD penerima tersimpan.');
        }
    }

    public function clearSignaturePenerima(): void
    {
        if ($this->isFormLocked) {
            return;
        }
        $this->simpanTtd(function (array $ttd) {
            unset($ttd['penerima']);
            return $ttd;
        }, 'Hapus TTD Penerima Ringkasan Pemulangan');
    }

    /** Buka kunci: cabut TTD PETUGAS (diserahkan & disetujui); TTD penerima tetap. */
    public function bukaKunci(): void
    {
        if (empty($this->riHdrNo) || !$this->isFormLocked) {
            return;
        }
        if (!Gate::allows('dokumen.bukaKunci')) {
            $this->dispatch('toast', type: 'error', message: 'Anda tidak berwenang membuka kunci.');
            return;
        }
        $pelaku = (string) (auth()->user()->myuser_name ?? auth()->user()->name ?? '-');

        try {
            DB::transaction(function () use ($pelaku) {
                $this->lockRIRow($this->riHdrNo);
                $dataRI = $this->findDataRI($this->riHdrNo);
                if (empty($dataRI)) {
                    throw new \RuntimeException('Data RI tidak ditemukan.');
                }
                $ttd = (array) data_get($dataRI, 'ringkasanPulangTtd', []);
                unset($ttd['diserahkan'], $ttd['disetujui']);
                $dataRI['ringkasanPulangTtd'] = $ttd;
                $this->updateJsonRI($this->riHdrNo, $dataRI);
                $this->appendAdminLogRI((int) $this->riHdrNo, "Buka kunci Ringkasan Pemulangan — TTD petugas dicabut (oleh {$pelaku})", 'MR');
                $this->muatTtd($dataRI);
            });
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal buka kunci: ' . $e->getMessage());
            return;
        }

        $this->dispatch('toast', type: 'success', message: 'Kunci dibuka — TTD petugas dicabut, TTD penerima tetap. Koreksi lalu tandatangani ulang.');
    }

    /* ═══════════════ CETAK PDF ═══════════════ */
    public function cetakPdf(): mixed
    {
        if (empty($this->riHdrNo)) {
            $this->dispatch('toast', type: 'error', message: 'Sesi expired, buka ulang dari EMR RI.');
            return null;
        }

        $plain = trim(strip_tags((string) $this->ringkasanPulang));
        if (mb_strlen($plain) < 5) {
            $this->dispatch('toast', type: 'error', message: 'Ringkasan kosong — isi dulu sebelum dicetak.');
            return null;
        }

        $dataRI = $this->findDataRI($this->riHdrNo);
        if (empty($dataRI)) {
            $this->dispatch('toast', type: 'error', message: 'Data RI tidak ditemukan.');
            return null;
        }

        $regNo = (string) ($dataRI['regNo'] ?? '');
        $pasienData = $regNo ? $this->findDataMasterPasien($regNo) : null;
        if (empty($pasienData)) {
            $this->dispatch('toast', type: 'error', message: 'Data pasien tidak ditemukan.');
            return null;
        }

        $pdf = Pdf::loadView(
            'pages.components.rekam-medis.ri.ringkasan-pulang-ri.ringkasan-pulang-ri-print',
            [
                'dataDaftarRi' => $dataRI,
                'dataPasien' => $pasienData,
                'ringkasanPulang' => $this->ringkasanPulang,
            ],
        )->setPaper('A4', 'portrait');

        $filename = 'ringkasan-pulang-ri-' . ($regNo ?: $this->riHdrNo) . '.pdf';
        return response()->streamDownload(fn() => print $pdf->output(), $filename);
    }

    public function closeEditor(): void
    {
        $this->reset(['riHdrNo', 'ringkasanPulang', 'isFormLocked', 'ttd', 'penerimaNama', 'penerimaHubungan', 'regNo']);
        $this->resetErrorBag();
        $this->dispatch('close-modal', name: 'ringkasan-pulang-ri');
    }
};
?>

<div>
    <x-modal name="ringkasan-pulang-ri" size="full" height="full" focusable>
        <div class="flex flex-col h-full">
            <div class="px-6 py-4 border-b border-hairline dark:border-gray-700">
                @if (!empty($riHdrNo))
                    <div class="flex items-start gap-3">
                        <div class="flex-1 min-w-0">
                            <livewire:pages::transaksi.ri.display-pasien-ri.display-pasien-ri :riHdrNo="$riHdrNo"
                                wire:key="ringkasan-pulang-ri-display-pasien-header-{{ $riHdrNo }}" />
                        </div>
                        <x-icon-button color="gray" type="button" wire:click="closeEditor" class="shrink-0">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                            </svg>
                        </x-icon-button>
                    </div>
                @else
                    <div class="flex items-center justify-end">
                        <x-icon-button color="gray" type="button" wire:click="closeEditor" class="shrink-0">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                            </svg>
                        </x-icon-button>
                    </div>
                @endif
            </div>

            {{-- Body BOLEH scroll sendiri (scrollbar kanan). Editor mengisi sisa tinggi modal di
                 layar besar, tapi punya tinggi minimum (rp-editor-wrap / rp-kunci-box) supaya di
                 layar pendek / zoom browser tidak terjepit jadi satu baris — kelebihannya
                 digulir lewat body. --}}
            <style>
                .rp-editor-wrap .tox-tinymce { height: 100% !important; }
                .rp-editor-wrap, .rp-kunci-box { min-height: 480px; }
                .rp-kunci-content { font-size: 12px; line-height: 1.45; color: #1f2937; }
                .rp-kunci-content table { border-collapse: collapse; width: 100%; }
                .rp-kunci-content td, .rp-kunci-content th { border: 1px solid #cbd5e1; padding: 3px 6px; vertical-align: top; }
                .rp-kunci-content .text-muted { color: #6b7280; }
                .dark .rp-kunci-content { color: #d1d5db; }
            </style>
            <div class="flex flex-col flex-1 min-h-0 px-6 py-4 overflow-y-auto">
                {{-- Banner status baku modul dokumen: terkunci = ketiga TTD lengkap. --}}
                @if ($isFormLocked)
                    <x-modul-dokumen.banner jenis="terkunci" class="mb-3 shrink-0">
                        Ringkasan sudah lengkap ditandatangani — diserahkan {{ $ttd['diserahkan']['nama'] ?? '-' }},
                        diterima {{ $ttd['penerima']['nama'] ?? '-' }}, disetujui {{ $ttd['disetujui']['nama'] ?? '-' }}.
                        Terkunci, tidak dapat diubah.
                    </x-modul-dokumen.banner>
                @endif

                <div class="flex flex-wrap items-center justify-between mb-1 gap-x-2 shrink-0">
                    <x-input-label value="Ringkasan Pemulangan Pasien (oleh Perawat / Bidan)" required class="!mb-0" />
                    <span class="text-xs text-muted dark:text-gray-400">Identitas pasien terisi otomatis saat dicetak. Sebagian field di-isi dari data EMR. Editor mendukung teks ala Word + tabel.</span>
                </div>

                {{-- Terkunci: isi tersimpan read-only. Editor tetap di DOM (disembunyikan) supaya
                     langsung bisa dipakai lagi sesudah Buka Kunci. --}}
                @if ($isFormLocked)
                    <div class="flex-1 min-h-0 p-3 mt-1 overflow-auto border rounded-md rp-kunci-box border-hairline dark:border-gray-700 bg-surface-soft dark:bg-gray-800/40">
                        <div class="rp-kunci-content">{!! $ringkasanPulang !!}</div>
                    </div>
                @endif
                <div @class(['flex-1 min-h-0 mt-1 rp-editor-wrap', 'hidden' => $isFormLocked])>
                    <x-tinymce-editor
                        name="ringkasanPulang"
                        placeholder="Ketik isi ringkasan pemulangan pasien..."
                        height="600"
                        modal-event="ringkasan-pulang-ri"
                        flush-event="ringkasan-pulang-ri.flush"
                        reload-event="ringkasan-pulang-ri.reload"
                        :content-style="'body{font-family:sans-serif;font-size:11px;line-height:1.4;color:#1f2937;} table{border-collapse:collapse;width:100%;} table td,table th{border:1px solid #cbd5e1;padding:3px 6px;vertical-align:top;} .text-muted{color:#6b7280;}'"
                        class="h-full" />
                </div>
                @error('ringkasanPulang')
                    <p class="mt-1 text-xs text-red-500 shrink-0">{{ $message }}</p>
                @enderror

                {{-- ══ TANDA TANGAN ══ — tiga kolom; terkunci otomatis bila ketiganya lengkap.
                     Bisa dilipat supaya ruang editor lega saat mengetik. --}}
                @php
                    $jumlahTtd = (int) !empty($ttd['diserahkan']['nama']) + (int) !empty($ttd['penerima']['ttd']) + (int) !empty($ttd['disetujui']['nama']);
                @endphp
                <div x-data="{ bukaTtd: true }" class="pt-3 mt-3 border-t shrink-0 border-hairline dark:border-gray-700">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <button type="button" x-on:click="bukaTtd = !bukaTtd" class="flex items-center gap-2 text-left">
                            <svg class="w-4 h-4 transition-transform text-muted" :class="bukaTtd && 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                            <span class="text-sm font-semibold text-ink dark:text-gray-200">Tanda Tangan</span>
                            <x-badge :variant="$jumlahTtd === 3 ? 'success' : 'warning'" class="text-[10px] px-1.5 py-0">{{ $jumlahTtd }}/3</x-badge>
                            @if ($isFormLocked)
                                <x-badge variant="success" class="text-[10px] px-1.5 py-0">Terkunci</x-badge>
                            @endif
                        </button>
                    </div>

                    <div x-show="bukaTtd" x-collapse class="overflow-y-auto max-h-96">
                        <div class="grid grid-cols-1 gap-6 pt-3 md:grid-cols-3">
                            {{-- Diserahkan: perawat/bidan --}}
                            <div class="flex flex-col">
                                <x-signature.ttd-petugas :framed="false" label="Diserahkan"
                                    :ttd="$ttd['diserahkan']['nama'] ?? ''" :code="$ttd['diserahkan']['kode'] ?? ''" :date="$ttd['diserahkan']['waktu'] ?? ''"
                                    :locked="$isFormLocked" :canSign="$this->bolehTtdPetugas()" sign="ttdDiserahkan" clear="hapusTtdDiserahkan"
                                    signLabel="TTD Saya (Perawat / Bidan)" nameLabel="Perawat / Bidan" emptyText="Belum ditandatangani." />
                                @if (!$isFormLocked && empty($ttd['diserahkan']['nama']) && !$this->bolehTtdPetugas())
                                    <div class="pt-2">
                                        <x-primary-button type="button" disabled class="justify-center w-full gap-1.5 opacity-60 cursor-not-allowed"
                                            title="TTD ini hanya dapat dilakukan akun Perawat / Bidan.">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                            </svg>
                                            TTD Saya (Perawat / Bidan)
                                        </x-primary-button>
                                        <p class="mt-1 text-xs text-amber-700 dark:text-amber-400">TTD ini hanya dapat dilakukan akun Perawat / Bidan.</p>
                                    </div>
                                @endif
                            </div>

                            {{-- Diterima: pasien / penanggung jawab --}}
                            <div class="flex flex-col">
                                <div class="mb-2 text-sm font-semibold tracking-wide text-center uppercase text-muted dark:text-gray-400">Diterima</div>
                                @if (!empty($ttd['penerima']['ttd']))
                                    <x-signature.signature-result :signature="$ttd['penerima']['ttd']" :date="$ttd['penerima']['waktu'] ?? ''"
                                        :disabled="$isFormLocked" wireMethod="clearSignaturePenerima" />
                                    <div class="mt-3">
                                        <x-input-label value="Nama Penerima" />
                                        <x-text-input value="{{ $ttd['penerima']['nama'] ?? '' }}" class="w-full mt-1" :disabled="true" readonly />
                                    </div>
                                    <p class="mt-1 text-sm"><span class="text-muted">Hubungan:</span>
                                        <span class="font-semibold text-ink dark:text-gray-200">{{ $ttd['penerima']['hubungan'] ?? '-' }}</span></p>
                                @elseif (!$isFormLocked)
                                    <x-signature.signature-pad wireMethod="setSignaturePenerima" />
                                    <div class="mt-3">
                                        <x-input-label value="Nama Penerima (Pasien / Penanggung Jawab) *" />
                                        <x-text-input wire:model.live.debounce.400ms="penerimaNama" :error="$errors->has('penerimaNama')" class="w-full mt-1" />
                                        <x-input-error :messages="$errors->get('penerimaNama')" class="mt-1" />
                                    </div>
                                    <div class="mt-2">
                                        <x-input-label value="Hubungan dengan Pasien" />
                                        <x-select-input wire:model.live="penerimaHubungan" class="mt-1">
                                            @foreach ($hubunganOptions as $opsi)
                                                <option value="{{ $opsi }}">{{ $opsi }}</option>
                                            @endforeach
                                        </x-select-input>
                                    </div>
                                @else
                                    <p class="py-8 text-sm italic text-center text-muted-soft">Belum ditandatangani.</p>
                                @endif
                            </div>

                            {{-- Disetujui: Ka.Ru / PJ Shift / Ka.Tim --}}
                            <div class="flex flex-col">
                                <x-signature.ttd-petugas :framed="false" label="Disetujui"
                                    :ttd="$ttd['disetujui']['nama'] ?? ''" :code="$ttd['disetujui']['kode'] ?? ''" :date="$ttd['disetujui']['waktu'] ?? ''"
                                    :locked="$isFormLocked" :canSign="$this->bolehTtdPetugas()" sign="ttdDisetujui" clear="hapusTtdDisetujui"
                                    signLabel="TTD Saya (Ka.Ru / PJ Shift)" nameLabel="Ka.Ru / PJ Shift / Ka.Tim" emptyText="Belum ditandatangani." />
                                @if (!$isFormLocked && empty($ttd['disetujui']['nama']) && !$this->bolehTtdPetugas())
                                    <div class="pt-2">
                                        <x-primary-button type="button" disabled class="justify-center w-full gap-1.5 opacity-60 cursor-not-allowed"
                                            title="TTD ini hanya dapat dilakukan akun Perawat / Bidan.">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                            </svg>
                                            TTD Saya (Ka.Ru / PJ Shift)
                                        </x-primary-button>
                                        <p class="mt-1 text-xs text-amber-700 dark:text-amber-400">TTD ini hanya dapat dilakukan akun Perawat / Bidan.</p>
                                    </div>
                                @endif
                            </div>
                        </div>
                        @unless ($isFormLocked)
                        <p class="mt-3 text-xs text-muted dark:text-gray-400">
                            <strong>Simpan Draft</strong> boleh berkali-kali selama ringkasan disusun. Tiap TTD ikut menyimpan
                            isi editor saat ini; begitu ketiganya lengkap, ringkasan terkunci. TTD Diserahkan &amp; Disetujui
                            hanya untuk akun perawat / bidan.
                        </p>
                        @endunless
                    </div>
                </div>
            </div>

            <div class="sticky bottom-0 z-10 flex items-center justify-between gap-2 px-6 py-3 border-t border-hairline bg-canvas dark:bg-gray-900 dark:border-gray-700 shrink-0">
                {{-- Pojok kiri: Reset ke Default (draft) atau Buka Kunci (terkunci) — pola modul dokumen. --}}
                @if ($isFormLocked)
                    @can('dokumen.bukaKunci')
                        <x-confirm-button variant="warning-soft" action="bukaKunci()" title="Buka Kunci Ringkasan Pemulangan"
                            message="TTD petugas (menyerahkan & menyetujui) akan dicabut; TTD penerima tetap. Lanjutkan?"
                            confirmText="Ya, Buka Kunci" wire:key="buka-kunci-ringkasan-{{ $riHdrNo }}">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M8 11V7a4 4 0 118 0m-4 8v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2z" />
                            </svg>
                            Buka Kunci
                        </x-confirm-button>
                    @else
                        <span></span>
                    @endcan
                @else
                    <x-secondary-button type="button"
                        wire:click="resetToDefault"
                        wire:confirm="Reset isi ke template default dari data EMR terbaru? Perubahan yang belum disimpan akan hilang."
                        wire:loading.attr="disabled" wire:target="resetToDefault"
                        class="text-xs">
                        <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                        <span wire:loading.remove wire:target="resetToDefault">Reset ke Default</span>
                        <span wire:loading wire:target="resetToDefault"><x-loading /> Reset...</span>
                    </x-secondary-button>
                @endif

                <div class="flex items-center gap-2">
                    <x-secondary-button type="button" wire:click="closeEditor">Batal</x-secondary-button>

                    <x-secondary-button type="button"
                        x-on:click="window.dispatchEvent(new Event('ringkasan-pulang-ri.flush')); $nextTick(() => $wire.cetakPdf())"
                        wire:loading.attr="disabled" wire:target="cetakPdf,save">
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0110.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0l.229 2.523a1.125 1.125 0 01-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0021 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 00-1.913-.247M6.34 18H5.25A2.25 2.25 0 013 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 011.913-.247m10.5 0a48.536 48.536 0 00-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659M18 10.5h.008v.008H18V10.5z"/></svg>
                        <span wire:loading.remove wire:target="cetakPdf">Cetak PDF</span>
                        <span wire:loading wire:target="cetakPdf"><x-loading /> Cetak...</span>
                    </x-secondary-button>

                    {{-- Simpan Draft — bisa dicicil, TIDAK mengunci. Terkunci → disembunyikan (pola modul dokumen). --}}
                    @unless ($isFormLocked)
                        <x-primary-button type="button"
                            x-on:click="window.dispatchEvent(new Event('ringkasan-pulang-ri.flush')); $nextTick(() => $wire.save())"
                            wire:loading.attr="disabled" wire:target="save,cetakPdf"
                            class="gap-2 min-w-[160px] justify-center">
                            <span wire:loading.remove wire:target="save" class="flex items-center gap-1.5">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 21v-8H7v8M7 3v5h8M5 3h11l4 4v12a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2z" />
                                </svg>
                                Simpan Draft
                            </span>
                            <span wire:loading wire:target="save"><x-loading /> Menyimpan...</span>
                        </x-primary-button>
                    @endunless
                </div>
            </div>
        </div>
    </x-modal>
</div>
