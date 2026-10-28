<?php
// resources/views/pages/transaksi/ri/satu-sehat/kirim-edukasi.blade.php
// Kirim Edukasi Pasien (Procedure, category SNOMED 409073007 Education) — RI, Playbook RI bab 21,
// disamakan dgn alur Rawat Inap apikesehatan.com: code = jenis edukasi SNOMED + KPTL 10913 "Edukasi
// Kesehatan Individu", note = materi. Sumber: edukasiPasienTerintegrasi[] (Formulir Edukasi Terintegrasi):
// jenis dari tujuan pertama (form.tujuan.opsi, App\Support\Options\EdukasiTerintegrasiOptions), materi dari
// tujuan + kebutuhan + metode, waktu = form.tglEdukasi. Hasil: satusehat.edukasiIds = [id entri => id];
// entri terkirim tak dikirim ulang. OPSIONAL.
// Performer = DPJP (pola kartu RI lain): kode pemberi informasi di formulir adalah kode USER, bukan dr_id —
// mencocokkannya ke rsmst_doctors bisa salah orang. Nama pemberi edukasi ditulis di note.

use Livewire\Component;
use Livewire\Attributes\On;
use Livewire\Attributes\Computed;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Ri\EmrRITrait;
use App\Http\Traits\SATUSEHAT\ProcedureTrait;
use App\Support\Options\EdukasiTerintegrasiOptions;

new class extends Component {
    use EmrRITrait, ProcedureTrait;

    /**
     * Tujuan edukasi (key EdukasiTerintegrasiOptions::tujuan) → jenis edukasi SNOMED, pilihan alur
     * apikesehatan.com, diverifikasi tx.fhir.org 2026-10-28. Tujuan lain → 311401005 Patient education.
     */
    private const JENIS_EDUKASI = [
        'penyakit' => ['code' => '84635008', 'display' => 'Disease process or condition education'],
        'nutrisi' => ['code' => '410177006', 'display' => 'Special diet education'],
    ];
    private const JENIS_UMUM = ['code' => '311401005', 'display' => 'Patient education'];
    private const KPTL_EDUKASI = ['system' => 'http://terminology.kemkes.go.id/CodeSystem/kptl', 'code' => '10913', 'display' => 'Edukasi Kesehatan Individu'];

    public ?string $riHdrNo = null;

    /** Mode pratinjau saja (layar Coding SATUSEHAT RI): tombol Kirim disembunyikan, kirim ditolak. */
    public bool $hanyaPratinjau = false;
    public bool $hasEncounter = false;
    public int $count = 0;
    public int $jumlahEntri = 0;

    /** Pratinjau dihitung hanya saat dibuka — jangan bebani muat halaman belasan kartu. */
    public bool $pratinjauTerbuka = false;

    public function togglePratinjau(): void
    {
        $this->pratinjauTerbuka = !$this->pratinjauTerbuka;
    }

    /** @return array<string, array> id entri => ['jenis','materi','waktu','pemberi'] */
    private function entriEdukasi(array $dataRI): array
    {
        $labelTujuan = EdukasiTerintegrasiOptions::tujuan();
        $hasil = [];
        foreach (array_values((array) ($dataRI['edukasiPasienTerintegrasi'] ?? [])) as $urutan => $entri) {
            $form = (array) ($entri['form'] ?? []);
            $tujuanList = array_values((array) ($form['tujuan']['opsi'] ?? []));
            $label = fn(array $bagian, array $kamus) => collect((array) ($bagian['opsi'] ?? []))->map(fn($opsi) => $kamus[$opsi] ?? (string) $opsi)
                ->push(trim((string) ($bagian['lainnya'] ?? '')))->filter()->implode(', ');
            $materi = array_filter([
                'Tujuan: ' . collect($tujuanList)->map(fn($opsi) => $labelTujuan[$opsi] ?? $opsi)->push(trim((string) ($form['tujuan']['lainnya'] ?? '')))->filter()->implode(', '),
                ($kebutuhan = $label((array) ($form['kebutuhan'] ?? []), EdukasiTerintegrasiOptions::kebutuhan())) !== '' ? 'Kebutuhan: ' . $kebutuhan : '',
                ($metode = $label((array) ($form['metodeMedia'] ?? []), EdukasiTerintegrasiOptions::metode())) !== '' ? 'Metode: ' . $metode : '',
            ], fn($baris) => $baris !== '' && $baris !== 'Tujuan: ');
            $pemberi = trim((string) ($form['pemberiInformasi']['petugasName'] ?? ($entri['created_by']['name'] ?? '')));
            $hasil[(string) (($entri['id'] ?? '') ?: 'urutan-' . $urutan)] = [
                'jenis' => self::JENIS_EDUKASI[$tujuanList[0] ?? ''] ?? self::JENIS_UMUM,
                'materi' => implode('. ', $materi) . ($pemberi !== '' ? '. Pemberi edukasi: ' . $pemberi : ''),
                'waktu' => trim((string) ($form['tglEdukasi'] ?? ($entri['created_at'] ?? ''))),
            ];
        }

        return $hasil;
    }

    #[Computed]
    public function pratinjau(): array
    {
        if (empty($this->riHdrNo)) {
            return [];
        }

        $data = $this->findDataRI($this->riHdrNo);
        $terkirim = $data['satusehat']['edukasiIds'] ?? [];

        return collect($this->entriEdukasi($data))->map(fn($entri, $id) => [
            'label' => $entri['waktu'] ?: 'Edukasi',
            'nilai' => 'SNOMED ' . $entri['jenis']['code'] . ' ' . $entri['jenis']['display'],
            'ket' => $entri['materi'] . (isset($terkirim[$id]) ? ' — sudah terkirim' : ' — akan dikirim'),
        ])->values()->all();
    }

    public function mount(?string $riHdrNo = null, bool $hanyaPratinjau = false): void
    {
        $this->hanyaPratinjau = $hanyaPratinjau;
        $this->pratinjauTerbuka = $hanyaPratinjau;
        $this->riHdrNo = $riHdrNo;
        $this->reloadState();
    }

    #[On('ri-satu-sehat.refresh')]
    public function onRefresh(string $riHdrNo): void
    {
        if ((string) $this->riHdrNo !== $riHdrNo) {
            return;
        }
        $this->reloadState();
    }

    private function reloadState(): void
    {
        if (empty($this->riHdrNo)) {
            return;
        }
        $data = $this->findDataRI($this->riHdrNo);
        if (empty($data)) {
            return;
        }
        $satuSehat = $data['satusehat'] ?? [];
        $this->hasEncounter = !empty($satuSehat['encounterId']);
        $this->count = count($satuSehat['edukasiIds'] ?? []);
        $this->jumlahEntri = count($this->entriEdukasi($data));
    }

    public function kirimForCurrent(): void
    {
        if ($this->hanyaPratinjau) {
            return;
        }
        if (empty($this->riHdrNo)) {
            return;
        }
        $this->kirim($this->riHdrNo);
        $this->reloadState();
    }

    /**
     * Pembungkus untuk rantai "Kirim Semua": apa pun hasilnya langkah ini WAJIB memberi
     * kabar, supaya orkestrator bisa melanjutkan ke langkah berikutnya.
     */
    #[On('ss-edukasi-ri.kirim')]
    public function kirim(string $riHdrNo): void
    {
        if ($this->hanyaPratinjau) {
            return;
        }
        $this->kirimInti($riHdrNo);
        $this->dispatch('ri-satu-sehat.langkah-selesai', langkah: 'edukasi');
    }

    public function kirimInti(string $riHdrNo): void
    {
        try {
            $this->initializeSatuSehat();
            $dataRI = $this->findDataRI($riHdrNo);
            if (empty($dataRI)) { $this->dispatch('toast', type: 'error', message: 'Data Rawat Inap tidak ditemukan.'); return; }

            $satuSehat = $dataRI['satusehat'] ?? [];
            if (empty($satuSehat['encounterId'])) { $this->dispatch('toast', type: 'error', message: 'Kirim Encounter terlebih dahulu.'); return; }

            $terkirim = $satuSehat['edukasiIds'] ?? [];
            $antrean = array_diff_key($this->entriEdukasi($dataRI), $terkirim);
            if (empty($antrean)) {
                $this->dispatch('toast', type: 'info', message: $terkirim !== []
                    ? 'Semua edukasi sudah pernah dikirim.'
                    : 'Belum ada Formulir Edukasi Terintegrasi (opsional) — dilewati.');
                return;
            }

            $patientId = $this->getPatientIHS($dataRI['regNo'] ?? '');
            if (empty($patientId)) { $this->dispatch('toast', type: 'error', message: 'Patient IHS Number kosong.'); return; }
            $practitionerId = (string) (DB::table('rsmst_doctors')->where('dr_id', $dataRI['drId'] ?? '')->value('dr_uuid') ?? '');

            $gagalList = [];
            foreach ($antrean as $id => $entri) {
                try {
                    $respons = $this->createProcedure([
                        'patientId'         => $patientId,
                        'encounterId'       => $satuSehat['encounterId'],
                        'code'              => $entri['jenis']['code'],
                        'display'           => $entri['jenis']['display'],
                        'codingTambahan'    => [self::KPTL_EDUKASI],
                        'kategori'          => ['system' => 'http://snomed.info/sct', 'code' => '409073007', 'display' => 'Education'],
                        'performedDateTime' => $this->parseDate($entri['waktu'])->toIso8601String(),
                        'performerId'       => $practitionerId,
                        'note'              => $entri['materi'],
                    ]);
                    if (empty($respons['id'])) {
                        $gagalList[] = ($entri['waktu'] ?: $id) . ' (respons tanpa id)';
                        continue;
                    }
                    $terkirim[$id] = $respons['id'];
                } catch (\Throwable $e) {
                    $gagalList[] = ($entri['waktu'] ?: $id) . ' (' . $e->getMessage() . ')';
                }
            }

            // Yang berhasil tetap disimpan walau sebagian gagal — kirim ulang tak menggandakan.
            $satuSehat['edukasiIds'] = $terkirim;
            $this->saveResult($riHdrNo, $satuSehat);

            empty($gagalList)
                ? $this->dispatch('toast', type: 'success', message: 'Edukasi berhasil dikirim (' . count($antrean) . ' entri).')
                : $this->dispatch('toast', type: 'error', message: 'Sebagian edukasi gagal: ' . implode('; ', $gagalList));
            $this->dispatch('ri-satu-sehat.refresh', riHdrNo: $riHdrNo);
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Edukasi gagal: ' . $e->getMessage());
        }
    }

    private function getPatientIHS(string $regNo): string
    {
        if (empty($regNo)) return '';
        return (string) (DB::table('rsmst_pasiens')->where('reg_no', $regNo)->value('patient_uuid') ?? '');
    }

    private function saveResult(string $riHdrNo, array $satuSehat): void
    {
        DB::transaction(function () use ($riHdrNo, $satuSehat) {
            $this->lockRIRow($riHdrNo);
            $data = $this->findDataRI($riHdrNo);
            $data['satusehat'] = $satuSehat;
            $this->updateJsonRI((int) $riHdrNo, $data);
        });
    }

    private function parseDate(string $teksTanggal): Carbon
    {
        if (empty($teksTanggal)) throw new \RuntimeException('tanggal sumber kosong — lengkapi datanya di EMR dulu (tidak diisi jam sekarang).');
        try { return Carbon::createFromFormat('d/m/Y H:i:s', $teksTanggal); } catch (\Throwable) {
            try { return Carbon::parse($teksTanggal); } catch (\Throwable) { throw new \RuntimeException("tanggal '{$teksTanggal}' tidak terbaca — betulkan datanya di EMR dulu (tidak diisi jam sekarang)."); }
        }
    }
};
?>

<div class="{{ $hanyaPratinjau
    ? 'p-3 border border-brand-green/30 rounded-xl bg-brand-green/5 dark:bg-brand-green/10 dark:border-brand-green/50'
    : 'p-4 bg-canvas border border-hairline shadow-sm rounded-xl dark:bg-gray-900 dark:border-gray-700' }}">
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
            @unless ($hanyaPratinjau)
            <div
                class="flex items-center justify-center w-8 h-8 rounded-full {{ $count > 0 ? 'bg-emerald-100 text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-400' : 'bg-surface-soft text-muted-soft dark:bg-gray-800 dark:text-gray-500' }}">
                <span class="text-sm font-bold">22</span>
            </div>
            @endunless
            <div>
                <div class="{{ $hanyaPratinjau ? 'text-xs font-semibold tracking-wide uppercase text-brand-green dark:text-brand-lime' : 'font-semibold text-ink dark:text-gray-100' }}">Edukasi Pasien</div>
                <div class="text-xs text-muted dark:text-gray-400">Formulir Edukasi Terintegrasi (Procedure), opsional.</div>
                @if ($count > 0)
                    <div class="mt-1 font-mono text-xs text-success dark:text-success">
                        {{ $count }}/{{ $jumlahEntri }} entri terkirim
                    </div>
                @endif
            </div>
        </div>
        @unless ($hanyaPratinjau)
        <x-primary-button type="button" wire:click="kirimForCurrent" wire:loading.attr="disabled" :disabled="!$hasEncounter"
            class="!bg-teal-600 hover:!bg-teal-700 {{ $count > 0 && $count >= $jumlahEntri ? '!bg-emerald-600' : '' }}">
            <span wire:loading.remove wire:target="kirimForCurrent,kirim">
                <span class="inline-flex items-center gap-1.5">
                    <x-satu-sehat.ikon-tombol :selesai="$count > 0 && $count >= $jumlahEntri" jenis="kirim" />
                    {{ $count > 0 && $count >= $jumlahEntri ? 'Terkirim' : 'Kirim' }}
                </span>
            </span>
            <span wire:loading wire:target="kirimForCurrent,kirim"><x-loading />...</span>
        </x-primary-button>
        @endunless
    </div>

    <x-satu-sehat.pratinjau :terbuka="$pratinjauTerbuka" :ringkas="$hanyaPratinjau"
        :baris="$pratinjauTerbuka ? $this->pratinjau : []"
        kosong="Belum ada Formulir Edukasi Terintegrasi (opsional)." />
</div>
