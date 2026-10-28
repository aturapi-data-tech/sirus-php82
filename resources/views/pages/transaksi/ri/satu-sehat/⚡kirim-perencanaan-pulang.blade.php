<?php
// resources/views/pages/transaksi/ri/satu-sehat/kirim-perencanaan-pulang.blade.php
// Kirim Perencanaan Pemulangan Pasien (CarePlan, category 736372004 Discharge care plan) — RI, Playbook
// RI bab 22b, disamakan dgn alur Rawat Inap apikesehatan.com. Sumber: perencanaan.dischargePlanning
// (form multi-entri Discharge Planning RI): pelayanan berkelanjutan → activity ServiceRequest, alat bantu
// → activity DeviceRequest (snomedCode tersimpan per entri, lihat DischargePlanningOptions), description
// = ringkasan naratif. Hasil: satusehat.perencanaanPulangId. OPSIONAL — kedua bagian "Tidak Ada" / kosong
// = dilewati (tidak ada rencana yang perlu dilaporkan).

use Livewire\Component;
use Livewire\Attributes\On;
use Livewire\Attributes\Computed;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Ri\EmrRITrait;
use App\Http\Traits\SATUSEHAT\CarePlanTrait;

new class extends Component {
    use EmrRITrait, CarePlanTrait;

    public ?string $riHdrNo = null;

    /** Mode pratinjau saja (layar Coding SATUSEHAT RI): tombol Kirim disembunyikan, kirim ditolak. */
    public bool $hanyaPratinjau = false;
    public bool $hasEncounter = false;
    public int $count = 0;

    /** Pratinjau dihitung hanya saat dibuka — jangan bebani muat halaman belasan kartu. */
    public bool $pratinjauTerbuka = false;

    public function togglePratinjau(): void
    {
        $this->pratinjauTerbuka = !$this->pratinjauTerbuka;
    }

    /**
     * Rencana pemulangan dari form Discharge Planning — sumber tunggal pratinjau & kirim.
     *
     * @return array{description: string, aktivitas: array}|null null = tak ada rencana (kedua bagian Tidak Ada)
     */
    private function rencanaPulang(array $dataRI): ?array
    {
        $rencana = $dataRI['perencanaan']['dischargePlanning'] ?? [];
        $pelayanan = (array) ($rencana['pelayananBerkelanjutan'] ?? []);
        $alat = (array) ($rencana['penggunaanAlatBantu'] ?? []);
        $aktivitas = [];
        $kalimat = [];

        foreach ((array) ($pelayanan['pelayananBerkelanjutanData'] ?? []) as $entri) {
            $nama = trim(($entri['jenisPelayanan'] ?? '') . (filled($entri['ketJenis'] ?? null) ? ' (' . $entri['ketJenis'] . ')' : ''));
            $teks = trim($nama . (filled($entri['tempatFasyankes'] ?? null) ? ' di ' . $entri['tempatFasyankes'] : ''));
            $aktivitas[] = ['kind' => 'ServiceRequest', 'text' => $teks, 'tanggal' => trim((string) ($entri['tglRencana'] ?? '')) ?: null,
                'code' => filled($entri['snomedCode'] ?? null) ? ['system' => 'http://snomed.info/sct', 'code' => $entri['snomedCode'], 'display' => $entri['snomedDisplay'] ?? ''] : []];
            $kalimat[] = 'Pelayanan berkelanjutan: ' . $teks . (filled($entri['tglRencana'] ?? null) ? ', rencana ' . $entri['tglRencana'] : '') . '.';
        }
        foreach ((array) ($alat['penggunaanAlatBantuData'] ?? []) as $entri) {
            $teks = trim(($entri['jenisAlat'] ?? '') . (filled($entri['ketAlat'] ?? null) ? ' (' . $entri['ketAlat'] . ')' : ''));
            $aktivitas[] = ['kind' => 'DeviceRequest', 'text' => $teks, 'tanggal' => null,
                'code' => filled($entri['snomedCode'] ?? null) ? ['system' => 'http://snomed.info/sct', 'code' => $entri['snomedCode'], 'display' => $entri['snomedDisplay'] ?? ''] : []];
            $kalimat[] = 'Alat bantu: ' . $teks . (filled($entri['sumberAlat'] ?? null) ? ', ' . strtolower($entri['sumberAlat']) : '') . (filled($entri['durasi'] ?? null) ? ', ' . $entri['durasi'] : '') . '.';
        }
        foreach ([$pelayanan['ketPelayananBerkelanjutan'] ?? '', $alat['ketPenggunaanAlatBantu'] ?? ''] as $keterangan) {
            if (trim((string) $keterangan) !== '') {
                $kalimat[] = trim((string) $keterangan);
            }
        }

        return $kalimat === [] ? null : ['description' => implode(' ', $kalimat), 'aktivitas' => $aktivitas];
    }

    #[Computed]
    public function pratinjau(): array
    {
        if (empty($this->riHdrNo)) {
            return [];
        }

        $rencana = $this->rencanaPulang($this->findDataRI($this->riHdrNo));
        if ($rencana === null) {
            return [];
        }
        $baris = array_map(fn($aktivitas) => [
            'label' => $aktivitas['kind'] === 'ServiceRequest' ? 'Pelayanan berkelanjutan' : 'Alat bantu',
            'nilai' => $aktivitas['text'],
            'ket' => isset($aktivitas['code']['code']) ? 'SNOMED ' . $aktivitas['code']['code'] : 'teks (tanpa kode)',
        ], $rencana['aktivitas']);

        return $baris !== [] ? $baris : [['label' => 'Rencana', 'nilai' => $rencana['description'], 'ket' => 'naratif']];
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
        $this->count = !empty($satuSehat['perencanaanPulangId']) ? 1 : 0;
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
    #[On('ss-perencanaan-pulang-ri.kirim')]
    public function kirim(string $riHdrNo): void
    {
        if ($this->hanyaPratinjau) {
            return;
        }
        $this->kirimInti($riHdrNo);
        $this->dispatch('ri-satu-sehat.langkah-selesai', langkah: 'perencanaan-pulang');
    }

    public function kirimInti(string $riHdrNo): void
    {
        try {
            $this->initializeSatuSehat();
            $dataRI = $this->findDataRI($riHdrNo);
            if (empty($dataRI)) { $this->dispatch('toast', type: 'error', message: 'Data Rawat Inap tidak ditemukan.'); return; }

            $satuSehat = $dataRI['satusehat'] ?? [];
            if (empty($satuSehat['encounterId'])) { $this->dispatch('toast', type: 'error', message: 'Kirim Encounter terlebih dahulu.'); return; }
            if (!empty($satuSehat['perencanaanPulangId'])) { $this->dispatch('toast', type: 'info', message: 'Perencanaan pemulangan sudah pernah dikirim.'); return; }

            $rencana = $this->rencanaPulang($dataRI);
            if ($rencana === null) { $this->dispatch('toast', type: 'info', message: 'Discharge Planning belum berisi rencana (opsional) — dilewati.'); return; }

            $patientId = $this->getPatientIHS($dataRI['regNo'] ?? '');
            if (empty($patientId)) { $this->dispatch('toast', type: 'error', message: 'Patient IHS Number kosong.'); return; }

            $respons = $this->createCarePlanPemulangan([
                'patientId'   => $patientId,
                'encounterId' => $satuSehat['encounterId'],
                'authorId'    => (string) (DB::table('rsmst_doctors')->where('dr_id', $dataRI['drId'] ?? '')->value('dr_uuid') ?? ''),
                'created'     => $this->parseDate((string) ($dataRI['entryDate'] ?? ''))->toIso8601String(),
                'description' => $rencana['description'],
                'periodEnd'   => filled($dataRI['exitDate'] ?? null) ? $this->parseDate((string) $dataRI['exitDate'])->toIso8601String() : null,
                'aktivitas'   => $rencana['aktivitas'],
            ]);

            if (empty($respons['id'])) { $this->dispatch('toast', type: 'error', message: 'Perencanaan pemulangan gagal: respons tanpa id.'); return; }

            $satuSehat['perencanaanPulangId'] = $respons['id'];
            $this->saveResult($riHdrNo, $satuSehat);
            $this->dispatch('toast', type: 'success', message: 'Perencanaan pemulangan berhasil dikirim.');
            $this->dispatch('ri-satu-sehat.refresh', riHdrNo: $riHdrNo);
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Perencanaan pemulangan gagal: ' . $e->getMessage());
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
                <span class="text-sm font-bold">23</span>
            </div>
            @endunless
            <div>
                <div class="{{ $hanyaPratinjau ? 'text-xs font-semibold tracking-wide uppercase text-brand-green dark:text-brand-lime' : 'font-semibold text-ink dark:text-gray-100' }}">Perencanaan Pemulangan</div>
                <div class="text-xs text-muted dark:text-gray-400">Discharge Planning (CarePlan) — pelayanan berkelanjutan &amp; alat bantu, opsional.</div>
                @if ($count > 0)
                    <div class="mt-1 font-mono text-xs text-success dark:text-success">terkirim</div>
                @endif
            </div>
        </div>
        @unless ($hanyaPratinjau)
        <x-primary-button type="button" wire:click="kirimForCurrent" wire:loading.attr="disabled" :disabled="!$hasEncounter"
            class="!bg-teal-600 hover:!bg-teal-700 {{ $count > 0 ? '!bg-emerald-600' : '' }}">
            <span wire:loading.remove wire:target="kirimForCurrent,kirim">
                <span class="inline-flex items-center gap-1.5">
                    <x-satu-sehat.ikon-tombol :selesai="$count > 0" jenis="kirim" />
                    {{ $count > 0 ? 'Terkirim' : 'Kirim' }}
                </span>
            </span>
            <span wire:loading wire:target="kirimForCurrent,kirim"><x-loading />...</span>
        </x-primary-button>
        @endunless
    </div>

    <x-satu-sehat.pratinjau :terbuka="$pratinjauTerbuka" :ringkas="$hanyaPratinjau"
        :baris="$pratinjauTerbuka ? $this->pratinjau : []"
        kosong="Discharge Planning belum berisi pelayanan berkelanjutan / alat bantu (opsional)." />
</div>
