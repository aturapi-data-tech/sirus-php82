<?php
// resources/views/pages/transaksi/rj/satu-sehat/kirim-telaah-resep.blade.php
// Kirim Telaah Resep sebagai QuestionnaireResponse Q0007.
//
// Sumber: datadaftarpolirj_json -> telaahResep (15 butir, diisi apoteker di
// Antrian Apotek RJ). Pemetaan ke linkId Q0007 ada di
// App\Support\Terminologi\TelaahResepQ0007 — termasuk bentuk bersarangnya yang
// mengikuti PERSIS contoh resmi Postman (grup 2, 3, dan butir 4 di DALAM grup 1).
//
// SATU KODE MASIH KOSONG: jawaban "Tidak Sesuai" belum punya kode clinical-term.
// Selama itu, telaah yang memuat jawaban "Tidak" pada butir ber-valueCoding
// DITOLAK KIRIM, disertai sebutan butir mana. Sengaja — melewati butir yang
// dijawab "Tidak" berarti telaah bermasalah terkirim TANPA masalahnya, tepat pada
// kasus yang paling penting. Lihat konstanta TIDAK_SESUAI di kelas pemetaan.

use Livewire\Component;
use Livewire\Attributes\On;
use Livewire\Attributes\Computed;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Rj\EmrRJTrait;
use App\Http\Traits\SATUSEHAT\QuestionnaireResponseTrait;
use App\Support\Terminologi\TelaahResepQ0007;

new class extends Component {
    use EmrRJTrait, QuestionnaireResponseTrait;

    public ?string $rjNo = null;
    public bool $hasEncounter = false;
    public bool $adaTelaah = false;
    public bool $sudahTtd = false;
    public array $penghalang = [];
    public string $questionnaireId = '';

    public bool $pratinjauTerbuka = false;

    public function togglePratinjau(): void
    {
        $this->pratinjauTerbuka = !$this->pratinjauTerbuka;
    }

    #[Computed]
    public function pratinjau(): array
    {
        if (empty($this->rjNo)) {
            return [];
        }
        $telaah = $this->findDataRJ($this->rjNo)['telaahResep'] ?? [];
        if ($telaah === []) {
            return [];
        }

        $baris = [];
        foreach ($telaah as $key => $butir) {
            if ($key === 'penanggungJawab' || !is_array($butir) || !isset($butir[$key])) {
                continue;
            }
            $baris[] = [
                'label' => $key,
                'nilai' => (string) $butir[$key],
                'ket'   => trim((string) ($butir['desc'] ?? '')),
            ];
        }

        return $baris;
    }

    public function mount(?string $rjNo = null): void
    {
        $this->rjNo = $rjNo;
        $this->reloadState();
    }

    #[On('rj-satu-sehat.refresh')]
    public function onRefresh(string $rjNo): void
    {
        if ((string) $this->rjNo !== $rjNo) {
            return;
        }
        $this->reloadState();
    }

    private function reloadState(): void
    {
        if (empty($this->rjNo)) {
            return;
        }
        $data = $this->findDataRJ($this->rjNo);
        if (empty($data)) {
            return;
        }

        $satuSehat = $data['satusehat'] ?? [];
        $telaah = $data['telaahResep'] ?? [];

        $this->hasEncounter = !empty($satuSehat['encounterId']);
        $this->questionnaireId = (string) ($satuSehat['telaahResepQuestionnaireId'] ?? '');
        $this->adaTelaah = collect($telaah)->keys()->contains(fn($k) => $k !== 'penanggungJawab');
        $this->sudahTtd = isset($telaah['penanggungJawab']);
        $this->penghalang = $telaah === [] ? [] : TelaahResepQ0007::butirTanpaKode($telaah);
    }

    public function kirimForCurrent(): void
    {
        if (empty($this->rjNo)) {
            return;
        }
        $this->kirim($this->rjNo);
        $this->reloadState();
    }

    /** Pembungkus rantai "Kirim Semua" — wajib melapor apa pun hasilnya. */
    #[On('ss-telaah-resep-rj.kirim')]
    public function kirim(string $rjNo): void
    {
        $this->kirimInti($rjNo);
        $this->dispatch('rj-satu-sehat.langkah-selesai', langkah: 'telaah-resep');
    }

    public function kirimInti(string $rjNo): void
    {
        try {
            $this->initializeSatuSehat();
            $dataRJ = $this->findDataRJ($rjNo);
            if (empty($dataRJ)) { $this->dispatch('toast', type: 'error', message: 'Data Rawat Jalan tidak ditemukan.'); return; }

            $satuSehat = $dataRJ['satusehat'] ?? [];
            if (empty($satuSehat['encounterId'])) { $this->dispatch('toast', type: 'error', message: 'Kirim Encounter terlebih dahulu.'); return; }
            if (!empty($satuSehat['telaahResepQuestionnaireId'])) { $this->dispatch('toast', type: 'info', message: 'Telaah resep sudah pernah dikirim.'); return; }

            $telaah = $dataRJ['telaahResep'] ?? [];
            if ($telaah === [] || !collect($telaah)->keys()->contains(fn($k) => $k !== 'penanggungJawab')) {
                $this->dispatch('toast', type: 'error', message: 'Telaah resep belum diisi.');
                return;
            }

            // Penjagaan yang paling penting di berkas ini — lihat catatan di kepala.
            $penghalang = TelaahResepQ0007::butirTanpaKode($telaah);
            if ($penghalang !== []) {
                $this->dispatch('toast', type: 'error', message: 'Belum bisa dikirim: butir "' . implode('", "', $penghalang) . '" dijawab Tidak, sedangkan kode "Tidak Sesuai" dari SATUSEHAT belum tersedia. Mengirimnya sekarang akan menghilangkan temuan itu dari rekam medis.');
                return;
            }

            $patientId = (string) (DB::table('rsmst_pasiens')->where('reg_no', $dataRJ['regNo'] ?? '')->value('patient_uuid') ?? '');
            if (empty($patientId)) { $this->dispatch('toast', type: 'error', message: 'Patient IHS Number kosong.'); return; }

            // Penulis = apoteker yang menandatangani telaah. Kalau IHS-nya tak ketemu,
            // kuesioner tetap dikirim tanpa author — lebih baik daripada tidak terkirim.
            $kodePetugas = (string) ($telaah['penanggungJawab']['userLogCode'] ?? '');
            $authorId = $kodePetugas !== ''
                ? (string) (DB::table('rsmst_doctors')->where('dr_id', $kodePetugas)->value('dr_uuid') ?? '')
                : '';

            // Butir 4 menunjuk resep yang dikaji; hanya disertakan bila MedicationRequest
            // memang sudah terbit di SATUSEHAT.
            $medicationRequestId = (string) (collect($satuSehat['medicationRequestIds'] ?? [])->first() ?? '');

            $respons = $this->createQuestionnaireResponse([
                'questionnaire' => TelaahResepQ0007::CANONICAL,
                'patientId'     => $patientId,
                'patientName'   => (string) ($dataRJ['regName'] ?? ''),
                'encounterId'   => $satuSehat['encounterId'],
                'authorId'      => $authorId,
                'authorName'    => (string) ($telaah['penanggungJawab']['userLog'] ?? ''),
                'authored'      => $this->waktuTelaah($telaah, $dataRJ)->toIso8601String(),
                'item'          => TelaahResepQ0007::item($telaah, $medicationRequestId ?: null),
            ]);

            if (empty($respons['id'])) { $this->dispatch('toast', type: 'error', message: 'SATUSEHAT tidak mengembalikan id QuestionnaireResponse.'); return; }

            $satuSehat['telaahResepQuestionnaireId'] = $respons['id'];
            $this->saveResult($rjNo, $satuSehat);

            $catatanResep = $medicationRequestId === '' ? ' Resep belum terkirim, jadi butir "resep yang dikaji" dilewati.' : '';
            $this->dispatch('toast', type: $catatanResep === '' ? 'success' : 'warning', message: 'Telaah resep terkirim.' . $catatanResep);
            $this->dispatch('rj-satu-sehat.refresh', rjNo: $rjNo);
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Telaah resep gagal: ' . $e->getMessage());
        }
    }

    /** Waktu telaah = saat apoteker TTD; belum TTD → jatuh ke waktu kunjungan. */
    private function waktuTelaah(array $telaah, array $dataRJ): Carbon
    {
        $teks = trim((string) ($telaah['penanggungJawab']['userLogDate'] ?? ''));
        if ($teks === '') {
            $teks = trim((string) ($dataRJ['rjDate'] ?? ''));
        }
        if ($teks === '') {
            return Carbon::now();
        }
        try { return Carbon::createFromFormat('d/m/Y H:i:s', $teks); } catch (\Throwable) {
            try { return Carbon::parse($teks); } catch (\Throwable) { return Carbon::now(); }
        }
    }

    private function saveResult(string $rjNo, array $satuSehat): void
    {
        DB::transaction(function () use ($rjNo, $satuSehat) {
            $this->lockRJRow($rjNo);
            $data = $this->findDataRJ($rjNo);
            $data['satusehat'] = $satuSehat;
            $this->updateJsonRJ($rjNo, $data);
        });
    }
};
?>

<div class="p-4 bg-canvas border border-hairline shadow-sm rounded-xl dark:bg-gray-900 dark:border-gray-700">
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
            <div
                class="flex items-center justify-center w-8 h-8 rounded-full {{ $questionnaireId !== '' ? 'bg-emerald-100 text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-400' : 'bg-surface-soft text-muted-soft dark:bg-gray-800 dark:text-gray-500' }}">
                <span class="text-sm font-bold">14</span>
            </div>
            <div>
                <div class="font-semibold text-ink dark:text-gray-100">Telaah Resep</div>
                <div class="text-xs text-muted dark:text-gray-400">
                    Pengkajian resep apoteker &mdash; kuesioner baku SATUSEHAT Q0007.
                    @if ($adaTelaah && !$sudahTtd)
                        <span class="text-muted-soft">Belum ditandatangani apoteker.</span>
                    @endif
                </div>
                @if (!empty($penghalang))
                    {{-- Penghalang disebut di kartu, bukan hanya saat tombol ditekan --}}
                    <div class="mt-1 text-xs text-warning-deep dark:text-amber-400">
                        Butir "{{ implode('", "', $penghalang) }}" dijawab Tidak &mdash; kode "Tidak Sesuai"
                        dari SATUSEHAT belum tersedia, jadi belum bisa dikirim.
                    </div>
                @endif
                @if ($questionnaireId !== '')
                    <div class="mt-1 font-mono text-xs text-success dark:text-success">terkirim</div>
                @endif
            </div>
        </div>
        <x-primary-button type="button" wire:click="kirimForCurrent" wire:loading.attr="disabled"
            :disabled="!$hasEncounter || !empty($penghalang)"
            class="!bg-teal-600 hover:!bg-teal-700 {{ $questionnaireId !== '' ? '!bg-emerald-600' : '' }}">
            <span wire:loading.remove wire:target="kirimForCurrent,kirim">
                <span class="inline-flex items-center gap-1.5">
                    <x-satu-sehat.ikon-tombol :selesai="$questionnaireId !== ''" jenis="kirim" />
                    {{ $questionnaireId !== '' ? 'Terkirim' : 'Kirim' }}
                </span>
            </span>
            <span wire:loading wire:target="kirimForCurrent,kirim"><x-loading />...</span>
        </x-primary-button>
    </div>

    <x-satu-sehat.pratinjau :terbuka="$pratinjauTerbuka"
        :baris="$pratinjauTerbuka ? $this->pratinjau : []"
        kosong="Telaah resep belum diisi di Antrian Apotek RJ — Kirim akan ditolak." />
</div>
