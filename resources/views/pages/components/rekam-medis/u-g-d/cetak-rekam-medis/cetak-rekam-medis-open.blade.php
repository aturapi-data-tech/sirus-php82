<?php
// resources/views/pages/components/rekam-medis/u-g-d/cetak-rekam-medis/cetak-rekam-medis-open.blade.php

use Livewire\Component;
use Livewire\Attributes\On;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Ugd\EmrUGDTrait;
use App\Http\Traits\Master\MasterPasien\MasterPasienTrait;
use App\Support\NyeriOptions;

new class extends Component {
    use EmrUGDTrait, MasterPasienTrait;

    public ?int $rjNo = null;
    public array $dataDaftarUGD = [];
    public bool $isLoading = false;

    // Navigasi antar-kunjungan (Prev/Next) — diisi rekam-medis-display saat open.
    public int $navPos = 0;
    public int $navTotal = 0;

    /* ═══════════════════════════════════════
     | OPEN → load ke property, buka modal preview
    ═══════════════════════════════════════ */
    #[On('cetak-rekam-medis-ugd.open')]
    public function open(int $rjNo, int $navPos = 0, int $navTotal = 0): void
    {
        $this->rjNo = $rjNo;
        $this->navPos = $navPos;
        $this->navTotal = $navTotal;
        $this->isLoading = true;
        $this->dataDaftarUGD = [];

        $dataUGD = $this->findDataUGD($rjNo);
        if (empty($dataUGD)) {
            $this->isLoading = false;
            $this->dispatch('toast', type: 'error', message: 'Data UGD tidak ditemukan.');
            return;
        }

        $pasienData = $this->findDataMasterPasien($dataUGD['regNo'] ?? '');
        if (empty($pasienData)) {
            $this->isLoading = false;
            $this->dispatch('toast', type: 'error', message: 'Data pasien tidak ditemukan.');
            return;
        }

        $pasien = $pasienData['pasien'];

        if (!empty($pasien['tglLahir'])) {
            $pasien['thn'] = Carbon::createFromFormat('d/m/Y', $pasien['tglLahir'])
                ->diff(Carbon::now(env('APP_TIMEZONE')))
                ->format('%y Thn, %m Bln %d Hr');
        }

        $dokter = DB::table('rsmst_doctors')
            ->where('dr_id', $dataUGD['drId'] ?? '')
            ->select('dr_name')
            ->first();

        $this->dataDaftarUGD = array_merge($pasien, [
            'dataDaftarTxn' => $dataUGD,
            'namaDokter' => $dokter->dr_name ?? null,
            'strDokter' => $dokter->dr_str ?? null,
            'tglCetak' => $dataUGD['rjDate'] ?? Carbon::now()->format('d/m/Y'),
        ]);

        $this->isLoading = false;

        $this->dispatch('open-modal', name: 'preview-rekam-medis-ugd');
    }

    /* ═══════════════════════════════════════
     | CETAK → generate PDF dari data yang sudah ada
    ═══════════════════════════════════════ */
    public function cetakPdf(): mixed
    {
        if (empty($this->dataDaftarUGD)) {
            $this->dispatch('toast', type: 'error', message: 'Tidak ada data untuk dicetak.');
            return null;
        }

        $data = $this->dataDaftarUGD;
        $regNo = $data['regNo'] ?? $this->rjNo;

        $pdf = Pdf::loadView('pages.components.rekam-medis.u-g-d.cetak-rekam-medis.cetak-rekam-medis-print', ['data' => $data])->setPaper('A4');

        return response()->streamDownload(fn() => print $pdf->output(), 'rekam-medis-ugd-' . $regNo . '.pdf');
    }

    /* ═══════════════════════════════════════
     | CLOSE
    ═══════════════════════════════════════ */
    public function closeModal(): void
    {
        $this->dataDaftarUGD = [];
        $this->rjNo = null;
        $this->dispatch('close-modal', name: 'preview-rekam-medis-ugd');
    }

    /** Pindah ke kunjungan sebelum/berikutnya (dihandle rekam-medis-display). */
    public function navPrev(): void
    {
        $this->dispatch('rm-display-nav', dir: 'prev');
    }

    public function navNext(): void
    {
        $this->dispatch('rm-display-nav', dir: 'next');
    }

    /* Ringkasan entri nyeri (metode/skor/interpretasi) — dipakai daftar Skala Nyeri. */
    public function ringkasNyeri(array $entri): array
    {
        return NyeriOptions::ringkasEntri($entri);
    }
};
?>

<div>
    <x-modal name="preview-rekam-medis-ugd" size="full" height="full" focusable>

        @php
            $dataRekamMedis = $this->dataDaftarUGD;
            $dataDaftarTxn = $dataRekamMedis['dataDaftarTxn'] ?? [];

            // entriTerakhir(): record lama menyimpan penilaian.nyeri sebagai SATU entri (assoc).
            $nyeriTerakhir = NyeriOptions::entriTerakhir($dataDaftarTxn['penilaian']['nyeri'] ?? null);
            $resikoJatuhTerakhir = !empty($dataDaftarTxn['penilaian']['resikoJatuh']) ? end($dataDaftarTxn['penilaian']['resikoJatuh']) : null;
            $dekubitusTerakhir = !empty($dataDaftarTxn['penilaian']['dekubitus']) ? end($dataDaftarTxn['penilaian']['dekubitus']) : null;
            $giziTerakhir = !empty($dataDaftarTxn['penilaian']['gizi']) ? end($dataDaftarTxn['penilaian']['gizi']) : null;
        @endphp

        <div class="flex flex-col min-h-[calc(100vh-4rem)]" wire:key="preview-rekam-medis-ugd-{{ $rjNo }}"
            x-data="{ tab: 'resume' }">

            {{-- ── HEADER ── --}}
            <div class="relative px-6 py-5 border-b border-hairline dark:border-gray-700">
                <div class="absolute inset-0 opacity-[0.06] dark:opacity-[0.10]"
                    style="background-image:radial-gradient(currentColor 1px,transparent 1px);background-size:14px 14px">
                </div>
                <div class="relative flex items-start justify-between gap-4">
                    {{-- Identitas pasien jadi header (pola EMR) --}}
                    <div class="flex-1 min-w-0">
                        <livewire:pages::transaksi.ugd.display-pasien-ugd.display-pasien-ugd :rjNo="$rjNo"
                            wire:key="preview-rm-ugd-display-pasien-{{ $rjNo }}" />
                    </div>
                    <x-secondary-button type="button" wire:click="closeModal" class="!p-2 shrink-0">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd"
                                d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                clip-rule="evenodd" />
                        </svg>
                    </x-secondary-button>
                </div>
            </div>

            {{-- ── TAB NAV ── --}}
            <div class="px-6 border-b bg-canvas border-hairline shrink-0 dark:bg-gray-900 dark:border-gray-700">
                <nav class="flex gap-1 -mb-px">
                    <button type="button" x-on:click="tab = 'resume'"
                        :class="tab === 'resume' ? 'border-brand-green text-brand-green' :
                            'border-transparent text-muted hover:text-ink'"
                        class="px-4 py-3 text-base font-semibold transition-colors border-b-2">Assesment Awal UGD</button>
                    <button type="button" x-on:click="tab = 'dokumen'"
                        :class="tab === 'dokumen' ? 'border-brand-green text-brand-green' :
                            'border-transparent text-muted hover:text-ink'"
                        class="px-4 py-3 text-base font-semibold transition-colors border-b-2">Modul Dokumen</button>
                    <button type="button" x-on:click="tab = 'penunjang'"
                        :class="tab === 'penunjang' ? 'border-brand-green text-brand-green' :
                            'border-transparent text-muted hover:text-ink'"
                        class="px-4 py-3 text-base font-semibold transition-colors border-b-2">Hasil Penunjang</button>
                </nav>
            </div>

            {{-- ── BODY (scroll) ── --}}
            <div class="flex-1 overflow-y-auto bg-surface-soft/70 dark:bg-gray-950/20">

                {{-- ════ TAB: RESUME ════ --}}
                <div x-show="tab === 'resume'" class="px-6 py-5">

                {{-- TRIASE --}}
                @php
                    $kajian = $dataDaftarTxn['anamnesa']['pengkajianPerawatan'] ?? [];
                    $tingkatKegawatan = $kajian['tingkatKegawatan'] ?? '-';
                    $tkLabel = match ($tingkatKegawatan) {
                        'P1' => 'P1 — Kritis',
                        'P2' => 'P2 — Urgent',
                        'P3' => 'P3 — Minor',
                        'P0' => 'P0 — Death',
                        default => '-',
                    };
                    $tingkatKegawatanBg = match ($tingkatKegawatan) {
                        'P1' => 'bg-red-100 border-red-500 dark:bg-red-900/30 dark:border-red-500',
                        'P2' => 'bg-yellow-100 border-yellow-500 dark:bg-yellow-900/30 dark:border-yellow-500',
                        'P3' => 'bg-green-100 border-green-500 dark:bg-green-900/30 dark:border-green-500',
                        'P0' => 'bg-gray-200 border-gray-700 dark:bg-gray-800 dark:border-gray-500',
                        default => 'bg-canvas border-hairline dark:bg-gray-900 dark:border-gray-700',
                    };
                    $tkBadge = match ($tingkatKegawatan) {
                        'P1' => 'bg-red-600 text-white',
                        'P2' => 'bg-yellow-400 text-ink',
                        'P3' => 'bg-green-600 text-white',
                        'P0' => 'bg-gray-800 text-white',
                        default => 'bg-gray-300 text-body',
                    };
                @endphp
                <div class="mb-4 p-3 border-l-4 rounded-lg shadow-sm {{ $tingkatKegawatanBg }}">
                    <div class="flex items-start gap-3">
                        <span
                            class="inline-flex items-center px-3 py-1 text-sm font-bold rounded-full shrink-0 {{ $tkBadge }}">
                            {{ $tkLabel }}
                        </span>
                        <div class="flex-1 grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-1 text-sm">
                            <p><span class="text-muted">Cara Masuk IGD : </span><span
                                    class="font-medium text-ink dark:text-gray-200">{{ $kajian['caraMasukIgd'] ?? '-' }}</span>
                            </p>
                            <p><span class="text-muted">Jam Datang : </span><span
                                    class="font-medium text-ink dark:text-gray-200">{{ $kajian['jamDatang'] ?? '-' }}</span>
                            </p>
                            <p class="sm:col-span-2"><span class="text-muted">Perawat Penerima : </span><span
                                    class="font-medium text-ink dark:text-gray-200">
                                    {{ !empty($kajian['perawatPenerima']) ? strtoupper($kajian['perawatPenerima']) : '-' }}
                                    @if (!empty($kajian['perawatPenerimaCode']))
                                        <span class="text-xs text-muted">({{ $kajian['perawatPenerimaCode'] }})</span>
                                    @endif
                                </span></p>
                        </div>
                    </div>
                </div>

                {{-- SCREENING UGD --}}
                @php
                    $screening = $dataDaftarTxn['screening'] ?? [];
                    $scrNyeriDada = $screening['nyeriDada'] ?? '';
                    if ($scrNyeriDada === 'Ada' && !empty($screening['nyeriDadaTingkat'])) {
                        $scrNyeriDada .= ' — ' . $screening['nyeriDadaTingkat'];
                    }
                    $scrTriase = $screening['triaseSaran'] ?? '';
                    $scrTriaseLabel = match ($scrTriase) {
                        'P0' => 'P0 — Meninggal',
                        'P1' => 'P1 — Kritis',
                        'P2' => 'P2 — Urgent',
                        'P3' => 'P3 — Minor',
                        default => '-',
                    };
                    // Gerbang blok P0 — sama persis dgn aturan di form Screening UGD.
                    $scrHentiJantungNafas = ($screening['pernafasan'] ?? '') === 'Henti Nafas' && ($screening['nadi'] ?? '') === 'Tidak Teraba';
                @endphp
                <x-border-form title="Screening UGD" class="mb-4">
                    <div class="grid grid-cols-2 gap-x-6">
                        <div class="space-y-2.5">
                            <p class="flex gap-3 text-base leading-relaxed pb-1.5 border-b border-hairline-soft dark:border-gray-800 last:border-0"><span class="w-56 shrink-0 text-right text-muted">Keluhan Utama :</span><span
                                    class="text-body dark:text-gray-300">{{ $screening['keluhanUtama'] ?? '-' }}</span>
                            </p>
                            <p class="flex gap-3 text-base leading-relaxed pb-1.5 border-b border-hairline-soft dark:border-gray-800 last:border-0"><span class="w-56 shrink-0 text-right text-muted">Pernafasan :</span><span
                                    class="text-body dark:text-gray-300">{{ ($screening['pernafasan'] ?? '') ?: '-' }}</span>
                            </p>
                            <p class="flex gap-3 text-base leading-relaxed pb-1.5 border-b border-hairline-soft dark:border-gray-800 last:border-0"><span class="w-56 shrink-0 text-right text-muted">Kesadaran :</span><span
                                    class="text-body dark:text-gray-300">{{ ($screening['kesadaran'] ?? '') ?: '-' }}</span>
                            </p>
                            <p class="flex gap-3 text-base leading-relaxed pb-1.5 border-b border-hairline-soft dark:border-gray-800 last:border-0"><span class="w-56 shrink-0 text-right text-muted">Nadi :</span><span
                                    class="text-body dark:text-gray-300">{{ ($screening['nadi'] ?? '') ?: '-' }}</span>
                            </p>
                        </div>
                        <div class="space-y-2.5">
                            <p class="flex gap-3 text-base leading-relaxed pb-1.5 border-b border-hairline-soft dark:border-gray-800 last:border-0"><span class="w-56 shrink-0 text-right text-muted">Nyeri Dada :</span><span
                                    class="text-body dark:text-gray-300">{{ $scrNyeriDada ?: '-' }}</span>
                            </p>
                            <p class="flex gap-3 text-base leading-relaxed pb-1.5 border-b border-hairline-soft dark:border-gray-800 last:border-0"><span class="w-56 shrink-0 text-right text-muted">Prioritas Pelayanan :</span><span
                                    class="text-body dark:text-gray-300">{{ ($screening['prioritasPelayanan'] ?? '') ?: '-' }}</span>
                            </p>
                            <p class="flex gap-3 text-base leading-relaxed pb-1.5 border-b border-hairline-soft dark:border-gray-800 last:border-0"><span class="w-56 shrink-0 text-right text-muted">Saran Triase :</span><span
                                    class="text-body dark:text-gray-300">{{ $scrTriaseLabel }}</span>
                            </p>
                            <p class="flex gap-3 text-base leading-relaxed pb-1.5 border-b border-hairline-soft dark:border-gray-800 last:border-0"><span class="w-56 shrink-0 text-right text-muted">Petugas :</span><span
                                    class="text-body dark:text-gray-300">{{ ($screening['petugasPelayanan'] ?? '') ?: '-' }}{{ !empty($screening['tanggalPelayanan']) ? ' — ' . $screening['tanggalPelayanan'] : '' }}</span>
                            </p>
                        </div>
                    </div>

                    {{-- Pernyataan meninggal — hanya tampil bila ada tanda henti jantung-nafas --}}
                    @if ($scrHentiJantungNafas)
                        <div class="p-3 mt-4 border rounded-lg border-gray-300 bg-gray-50 dark:border-gray-600 dark:bg-gray-800/40">
                            <div class="mb-2 text-base font-bold text-ink dark:text-gray-100">Penilaian Henti Jantung-Nafas (P0)</div>
                            <div class="grid grid-cols-2 gap-x-6">
                                <div class="space-y-2.5">
                                    <p class="flex gap-3 text-base leading-relaxed pb-1.5 border-b border-hairline-soft dark:border-gray-800 last:border-0"><span class="w-56 shrink-0 text-right text-muted">Tanda Kematian Pasti :</span><span
                                            class="text-body dark:text-gray-300">{{ ($screening['tandaKematianPasti'] ?? '') ?: '-' }}{{ !empty($screening['tandaKematianKeterangan']) ? ' — ' . $screening['tandaKematianKeterangan'] : '' }}</span>
                                    </p>
                                    <p class="flex gap-3 text-base leading-relaxed pb-1.5 border-b border-hairline-soft dark:border-gray-800 last:border-0"><span class="w-56 shrink-0 text-right text-muted">Tindakan Resusitasi :</span><span
                                            class="text-body dark:text-gray-300">{{ ($screening['tindakanResusitasi'] ?? '') ?: '-' }}</span>
                                    </p>
                                </div>
                                <div class="space-y-2.5">
                                    <p class="flex gap-3 text-base leading-relaxed pb-1.5 border-b border-hairline-soft dark:border-gray-800 last:border-0"><span class="w-56 shrink-0 text-right text-muted">Dinyatakan Meninggal :</span><span
                                            class="text-body dark:text-gray-300">{{ ($screening['dinyatakanMeninggal'] ?? '') ?: '-' }}{{ !empty($screening['waktuMeninggal']) ? ' — ' . $screening['waktuMeninggal'] : '' }}</span>
                                    </p>
                                    <p class="flex gap-3 text-base leading-relaxed pb-1.5 border-b border-hairline-soft dark:border-gray-800 last:border-0"><span class="w-56 shrink-0 text-right text-muted">Dokter yang Menyatakan :</span><span
                                            class="text-body dark:text-gray-300">{{ ($screening['dokterPenyataMeninggal'] ?? '') ?: '-' }}</span>
                                    </p>
                                </div>
                            </div>
                        </div>
                    @endif
                </x-border-form>

                {{-- PERAWAT --}}
                <x-border-form title="Perawat" class="mb-4">
                    @php
                        $statusPsikologis = $dataDaftarTxn['anamnesa']['statusPsikologis'] ?? [];
                        $statPsiko = collect([
                            $statusPsikologis['tidakAdaKelainan'] ?? false ? 'Tidak Ada Kelainan' : null,
                            $statusPsikologis['marah'] ?? false ? 'Marah' : null,
                            $statusPsikologis['ccemas'] ?? false ? 'Cemas' : null,
                            $statusPsikologis['takut'] ?? false ? 'Takut' : null,
                            $statusPsikologis['sedih'] ?? false ? 'Sedih' : null,
                            $statusPsikologis['cenderungBunuhDiri'] ?? false ? 'Resiko Bunuh Diri' : null,
                        ])
                            ->filter()
                            ->implode(' / ');
                        $statusMental = $dataDaftarTxn['anamnesa']['statusMental'] ?? [];
                    @endphp
                    <div class="space-y-2.5">
                        <p class="flex gap-3 text-base leading-relaxed pb-1.5 border-b border-hairline-soft dark:border-gray-800 last:border-0"><span class="w-56 shrink-0 text-right text-muted">Status Psikologis :</span><span
                                class="text-body dark:text-gray-300">{{ $statPsiko . (!empty($statusPsikologis['sebutstatusPsikologis']) ? ' — ' . $statusPsikologis['sebutstatusPsikologis'] : '') ?: '-' }}</span>
                        </p>
                        <p class="flex gap-3 text-base leading-relaxed pb-1.5 border-b border-hairline-soft dark:border-gray-800 last:border-0"><span class="w-56 shrink-0 text-right text-muted">Status Mental :</span><span
                                class="text-body dark:text-gray-300">{{ ($statusMental['statusMental'] ?? '-') . (!empty($statusMental['keteranganStatusMental']) ? ' — ' . $statusMental['keteranganStatusMental'] : '') }}</span>
                        </p>
                    </div>
                </x-border-form>

                {{-- ANAMNESA + TANDA VITAL + NUTRISI --}}
                <div class="grid grid-cols-3 gap-4 mb-4">
                    <x-border-form title="Anamnesa" class="col-span-2">
                        <div class="space-y-2.5">
                            <p class="flex gap-3 text-base leading-relaxed pb-1.5 border-b border-hairline-soft dark:border-gray-800 last:border-0"><span class="w-56 shrink-0 text-right text-muted">Keluhan Utama :</span><span
                                    class="text-body dark:text-gray-300">{{ $dataDaftarTxn['anamnesa']['keluhanUtama']['keluhanUtama'] ?? '-' }}</span>
                            </p>
                            <p class="flex gap-3 text-base leading-relaxed pb-1.5 border-b border-hairline-soft dark:border-gray-800 last:border-0"><span class="w-56 shrink-0 text-right text-muted">Screening Batuk :</span><span
                                    class="text-body dark:text-gray-300">{{ $dataDaftarTxn['anamnesa']['screeningBatuk'] ?? '-' }}</span>
                            </p>
                            {{-- PENILAIAN — tampilkan SEMUA record asesmen (array) + waktu --}}
                            @php $statusMedikVal = $dataDaftarTxn['anamnesa']['pengkajianPerawatan']['statusMedik']['statusMedik'] ?? ''; @endphp
                            <p class="flex gap-3 text-base leading-relaxed pb-1.5 border-b border-hairline-soft dark:border-gray-800 last:border-0"><span class="w-56 shrink-0 text-right text-muted">Status Medik :</span><span
                                    class="text-body dark:text-gray-300">{{ $statusMedikVal !== '' ? $statusMedikVal : '-' }}</span>
                            </p>
                            @php $nyeriList = collect(NyeriOptions::daftarEntri($dataDaftarTxn['penilaian']['nyeri'] ?? null))->filter(fn($entri) => filled(data_get($entri, 'nyeri.nyeriMetode.nyeriMetode'))); @endphp
                                                            <div class="flex gap-3 pb-1.5 border-b border-hairline-soft dark:border-gray-800">
                                    <span class="w-56 shrink-0 text-right text-muted">Skala Nyeri :</span>
                                    <div class="space-y-1 text-body dark:text-gray-300">
                                        @forelse ($nyeriList as $entriNyeri)
                                            @php $ringkasNyeri = $this->ringkasNyeri($entriNyeri); @endphp
                                            <div><span class="text-sm font-medium text-muted-soft">{{ $entriNyeri['tglPenilaian'] ?? '-' }}</span> — Metode: {{ $ringkasNyeri['metode'] }} / Skor: {{ $ringkasNyeri['skor'] }} / {{ $ringkasNyeri['label'] }}@if ($ringkasNyeri['catatan']) ({{ $ringkasNyeri['catatan'] }})@endif / Pencetus: {{ $entriNyeri['nyeri']['pencetus'] ?? '-' }} / Durasi: {{ $entriNyeri['nyeri']['durasi'] ?? '-' }} / Lokasi: {{ $entriNyeri['nyeri']['lokasi'] ?? '-' }} / Sensasi: {{ $entriNyeri['nyeri']['sensasi'] ?? '-' }}</div>
                                        @empty
                                            <span class="italic text-muted-soft">Belum dinilai</span>
                                        @endforelse
                                    </div>
                                </div>
                            @php $resikoJatuhList = collect($dataDaftarTxn['penilaian']['resikoJatuh'] ?? [])->filter(fn($entri) => filled(data_get($entri, 'resikoJatuh.resikoJatuhMetode.resikoJatuhMetode'))); @endphp
                                                            <div class="flex gap-3 pb-1.5 border-b border-hairline-soft dark:border-gray-800">
                                    <span class="w-56 shrink-0 text-right text-muted">Resiko Jatuh :</span>
                                    <div class="space-y-1 text-body dark:text-gray-300">
                                        @forelse ($resikoJatuhList as $entriResikoJatuh)
                                            <div><span class="text-sm font-medium text-muted-soft">{{ $entriResikoJatuh['tglPenilaian'] ?? '-' }}</span> — Metode: {{ $entriResikoJatuh['resikoJatuh']['resikoJatuhMetode']['resikoJatuhMetode'] ?? '-' }} / Skor: {{ $entriResikoJatuh['resikoJatuh']['resikoJatuhMetode']['resikoJatuhMetodeScore'] ?? '-' }} / {{ $entriResikoJatuh['resikoJatuh']['kategoriResiko'] ?? '-' }}</div>
                                        @empty
                                            <span class="italic text-muted-soft">Belum dinilai</span>
                                        @endforelse
                                    </div>
                                </div>
                            @php $resikoBunuhDiriList = collect($dataDaftarTxn['penilaian']['resikoBunuhDiri'] ?? [])->filter(fn($entri) => filled(data_get($entri, 'tglPenilaian'))); @endphp
                            @if ($resikoBunuhDiriList->isNotEmpty())
                                <div class="flex gap-3 pb-1.5 border-b border-hairline-soft dark:border-gray-800">
                                    <span class="w-56 shrink-0 text-right text-muted">Risiko Bunuh Diri (C-SSRS) :</span>
                                    <div class="space-y-1 text-body dark:text-gray-300">
                                        @foreach ($resikoBunuhDiriList as $entriBunuhDiri)
                                            <div><span class="text-sm font-medium text-muted-soft">{{ $entriBunuhDiri['tglPenilaian'] ?? '-' }}</span> — Skor keparahan: {{ $entriBunuhDiri['skorKeparahan'] ?? '-' }} / {{ $entriBunuhDiri['kategoriResiko'] ?? '-' }}{{ !empty($entriBunuhDiri['tindakLanjut']) ? ' / Tindak lanjut: ' . implode(', ', $entriBunuhDiri['tindakLanjut']) : '' }}</div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                            @php $dekubitusList = collect($dataDaftarTxn['penilaian']['dekubitus'] ?? [])->filter(fn($entri) => filled(data_get($entri, 'dekubitus.dekubitus'))); @endphp
                                                            <div class="flex gap-3 pb-1.5 border-b border-hairline-soft dark:border-gray-800">
                                    <span class="w-56 shrink-0 text-right text-muted">Dekubitus :</span>
                                    <div class="space-y-1 text-body dark:text-gray-300">
                                        @forelse ($dekubitusList as $entriDekubitus)
                                            <div><span class="text-sm font-medium text-muted-soft">{{ $entriDekubitus['tglPenilaian'] ?? '-' }}</span> — {{ $entriDekubitus['dekubitus']['dekubitus'] ?? '-' }} / Skor Braden: {{ $entriDekubitus['dekubitus']['bradenScore'] ?? '-' }} / {{ $entriDekubitus['dekubitus']['kategoriResiko'] ?? '-' }}{{ !empty($entriDekubitus['dekubitus']['rekomendasi']) ? ' / ' . $entriDekubitus['dekubitus']['rekomendasi'] : '' }}</div>
                                        @empty
                                            <span class="italic text-muted-soft">Belum dinilai</span>
                                        @endforelse
                                    </div>
                                </div>
                            @php $giziList = collect($dataDaftarTxn['penilaian']['gizi'] ?? [])->filter(fn($entri) => filled(data_get($entri, 'gizi.imt')) || filled(data_get($entri, 'gizi.beratBadan'))); @endphp
                                                            <div class="flex gap-3 pb-1.5 border-b border-hairline-soft dark:border-gray-800">
                                    <span class="w-56 shrink-0 text-right text-muted">Gizi :</span>
                                    <div class="space-y-1 text-body dark:text-gray-300">
                                        @forelse ($giziList as $entriGizi)
                                            <div><span class="text-sm font-medium text-muted-soft">{{ $entriGizi['tglPenilaian'] ?? '-' }}</span> — BB: {{ $entriGizi['gizi']['beratBadan'] ?? '-' }} kg / TB: {{ $entriGizi['gizi']['tinggiBadan'] ?? '-' }} cm / IMT: {{ $entriGizi['gizi']['imt'] ?? '-' }} / Skor: {{ $entriGizi['gizi']['skorSkrining'] ?? '-' }} / {{ $entriGizi['gizi']['kategoriGizi'] ?? '-' }}{{ !empty($entriGizi['gizi']['catatan']) ? ' / ' . $entriGizi['gizi']['catatan'] : '' }}</div>
                                        @empty
                                            <span class="italic text-muted-soft">Belum dinilai</span>
                                        @endforelse
                                    </div>
                                </div>
                            <p class="flex gap-3 text-base leading-relaxed pb-1.5 border-b border-hairline-soft dark:border-gray-800 last:border-0"><span class="w-56 shrink-0 text-right text-muted">Riwayat Penyakit Sekarang :</span><span
                                    class="text-body dark:text-gray-300">{{ $dataDaftarTxn['anamnesa']['riwayatPenyakitSekarangUmum']['riwayatPenyakitSekarangUmum'] ?? '-' }}</span>
                            </p>
                            <p class="flex gap-3 text-base leading-relaxed pb-1.5 border-b border-hairline-soft dark:border-gray-800 last:border-0"><span class="w-56 shrink-0 text-right text-muted">Riwayat Penyakit Dahulu :</span><span
                                    class="text-body dark:text-gray-300">{{ $dataDaftarTxn['anamnesa']['riwayatPenyakitDahulu']['riwayatPenyakitDahulu'] ?? '-' }}</span>
                            </p>
                            <p class="flex gap-3 text-base leading-relaxed pb-1.5 border-b border-hairline-soft dark:border-gray-800 last:border-0"><span class="w-56 shrink-0 text-right text-muted">Alergi :</span><span
                                    class="text-body dark:text-gray-300">{{ \App\Support\AlergiSnomed::untukCetak($dataDaftarTxn['anamnesa']['alergi'] ?? []) }}</span>
                            </p>
                            <div>
                                <p class="mb-1.5 text-base text-muted">Rekonsiliasi Obat :</p>
                                <table class="w-full text-sm border-collapse">
                                    <thead>
                                        <tr class="bg-surface-soft dark:bg-gray-800">
                                            <th
                                                class="px-2.5 py-1.5 text-left text-sm font-medium text-muted border border-hairline dark:border-gray-700">
                                                Nama Obat</th>
                                            <th
                                                class="px-2.5 py-1.5 text-left text-sm font-medium text-muted border border-hairline dark:border-gray-700">
                                                Dosis</th>
                                            <th
                                                class="px-2.5 py-1.5 text-left text-sm font-medium text-muted border border-hairline dark:border-gray-700">
                                                Rute</th>
                                            {{-- Digabung satu kolom (label atas-bawah), seperti pada cetak --}}
                                            <th
                                                class="px-2.5 py-1.5 text-left text-sm font-medium text-muted border border-hairline dark:border-gray-700">
                                                Keterangan</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($dataDaftarTxn['anamnesa']['rekonsiliasiObat'] ?? [] as $obat)
                                            <tr>
                                                <td
                                                    class="px-2.5 py-1.5 text-body dark:text-gray-300 border border-hairline dark:border-gray-700">
                                                    {{ $obat['namaObat'] ?? '-' }}</td>
                                                <td
                                                    class="px-2.5 py-1.5 text-body dark:text-gray-300 border border-hairline dark:border-gray-700">
                                                    {{ $obat['dosis'] ?? '-' }}</td>
                                                <td
                                                    class="px-2.5 py-1.5 text-body dark:text-gray-300 border border-hairline dark:border-gray-700">
                                                    {{ $obat['rute'] ?? '-' }}</td>
                                                <td style="white-space:nowrap"
                                                    class="px-2.5 py-1.5 text-body dark:text-gray-300 border border-hairline dark:border-gray-700">
                                                    Dibawa saat ranap :
                                                    {{ filled($obat['dibawaRanap'] ?? null) ? $obat['dibawaRanap'] : '-' }}<br>
                                                    Lanjut saat pulang :
                                                    {{ filled($obat['lanjutPulang'] ?? null) ? $obat['lanjutPulang'] : '-' }}
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="4"
                                                    class="px-2.5 py-1.5 text-center text-muted-soft border border-hairline dark:border-gray-700">
                                                    Tidak ada data</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </x-border-form>

                    <div class="space-y-4">
                        <x-border-form title="Tanda Vital">
                            @php $tandaVital = $dataDaftarTxn['pemeriksaan']['tandaVital'] ?? []; @endphp
                            <div class="space-y-2.5">
                                @foreach ([['Tekanan Darah', ($tandaVital['sistolik'] ?? '-') . ' / ' . ($tandaVital['distolik'] ?? '-'), 'mmHg'], ['Nadi', $tandaVital['frekuensiNadi'] ?? '-', 'x/mnt'], ['Suhu', $tandaVital['suhu'] ?? '-', '°C'], ['Pernafasan', $tandaVital['frekuensiNafas'] ?? '-', 'x/mnt'], ['SPO2', $tandaVital['spo2'] ?? '-', '%'], ['GDA', $tandaVital['gda'] ?? '-', 'mg/dL'], ['GCS', 'E' . ($tandaVital['e'] ?? '-') . ' V' . ($tandaVital['v'] ?? '-') . ' M' . ($tandaVital['m'] ?? '-') . ' (' . ($tandaVital['gcs'] ?? '-') . ')', '']] as [$label, $nilai, $unit])
                                    <p
                                        class="flex gap-3 text-base leading-relaxed pb-1.5 border-b border-hairline-soft dark:border-gray-800 last:border-0">
                                        <span
                                            class="w-56 shrink-0 text-right text-muted">{{ $label }} :</span>
                                        <span
                                            class="text-base font-semibold text-ink dark:text-gray-200">{{ $nilai }}
                                            <span class="font-normal text-muted-soft">{{ $unit }}</span></span>
                                    </p>
                                @endforeach
                            </div>
                        </x-border-form>

                        <x-border-form title="Nutrisi">
                            @php $nutrisi = $dataDaftarTxn['pemeriksaan']['nutrisi'] ?? []; @endphp
                            <div class="space-y-2.5">
                                @foreach ([['Berat Badan', $nutrisi['bb'] ?? '-', 'Kg'], ['Tinggi Badan', $nutrisi['tb'] ?? '-', 'cm'], ['Index Masa Tubuh', $nutrisi['imt'] ?? '-', 'Kg/M²'], ['Lingkar Kepala', $nutrisi['lk'] ?? '-', 'cm'], ['Lingkar Lengan Atas', $nutrisi['lila'] ?? '-', 'cm']] as [$label, $nilai, $unit])
                                    <p
                                        class="flex gap-3 text-base leading-relaxed pb-1.5 border-b border-hairline-soft dark:border-gray-800 last:border-0">
                                        <span
                                            class="w-56 shrink-0 text-right text-muted">{{ $label }} :</span>
                                        <span
                                            class="text-base font-semibold text-ink dark:text-gray-200">{{ $nilai }}
                                            <span class="font-normal text-muted-soft">{{ $unit }}</span></span>
                                    </p>
                                @endforeach
                            </div>
                        </x-border-form>
                    </div>
                </div>

                {{-- PENGKAJIAN PRIMER (ABCD) — UGD --}}
                @php $tandaVitalPrimer = $dataDaftarTxn['pemeriksaan']['tandaVital'] ?? []; @endphp
                <div class="mb-4">
                    <x-border-form title="Pengkajian Primer (ABCD)">
                        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
                            @foreach ([['A — Jalan Nafas', $tandaVitalPrimer['jalanNafas']['jalanNafas'] ?? '-'], ['B — Pernafasan', $tandaVitalPrimer['pernafasan']['pernafasan'] ?? '-'], ['Gerak Dada', $tandaVitalPrimer['gerakDada']['gerakDada'] ?? '-'], ['C — Sirkulasi', $tandaVitalPrimer['sirkulasi']['sirkulasi'] ?? '-'], ['D — Disability', $tandaVitalPrimer['disability']['disability'] ?? '-']] as [$label, $nilai])
                                <div class="px-3 py-2 rounded-lg bg-surface-soft/60 dark:bg-gray-800/40">
                                    <div class="text-sm font-semibold text-muted">{{ $label }}</div>
                                    <div class="text-base text-ink dark:text-gray-200">{{ $nilai }}</div>
                                </div>
                            @endforeach
                        </div>
                    </x-border-form>
                </div>

                {{-- KEADAAN UMUM + FUNGSIONAL + PEMERIKSAAN FISIK + ANATOMI — 1 baris --}}
                <div class="grid grid-cols-1 gap-4 mb-4 sm:grid-cols-2 lg:grid-cols-4">
                    <x-border-form title="Keadaan Umum">
                        <p class="text-base text-ink dark:text-gray-200">
                            {{ $dataDaftarTxn['pemeriksaan']['tandaVital']['keadaanUmum'] ?? 'BAIK' }} &nbsp;/&nbsp; <span
                                class="font-medium">{{ $dataDaftarTxn['pemeriksaan']['tandaVital']['tingkatKesadaran'] ?? '-' }}</span>
                        </p>
                    </x-border-form>
                    <x-border-form title="Fungsional">
                        @php $fungsional = $dataDaftarTxn['pemeriksaan']['fungsional'] ?? []; @endphp
                        <div class="space-y-2.5">
                            <p class="flex gap-3 text-base leading-relaxed pb-1.5 border-b border-hairline-soft dark:border-gray-800 last:border-0"><span class="w-56 shrink-0 text-right text-muted">Alat Bantu :</span><span
                                    class="text-body dark:text-gray-300">{{ $fungsional['alatBantu'] ?? '-' }}</span></p>
                            <p class="flex gap-3 text-base leading-relaxed pb-1.5 border-b border-hairline-soft dark:border-gray-800 last:border-0"><span class="w-56 shrink-0 text-right text-muted">Prothesa :</span><span
                                    class="text-body dark:text-gray-300">{{ $fungsional['prothesa'] ?? '-' }}</span></p>
                            <p class="flex gap-3 text-base leading-relaxed pb-1.5 border-b border-hairline-soft dark:border-gray-800 last:border-0"><span class="w-56 shrink-0 text-right text-muted">Cacat Tubuh :</span><span
                                    class="text-body dark:text-gray-300">{{ $fungsional['cacatTubuh'] ?? '-' }}</span></p>
                            @php
                                $suspekAK = $dataDaftarTxn['pemeriksaan']['suspekAkibatKerja']['suspekAkibatKerja'] ?? '-';
                                $ketAK = trim($dataDaftarTxn['pemeriksaan']['suspekAkibatKerja']['keteranganSuspekAkibatKerja'] ?? '');
                            @endphp
                            <p class="flex gap-3 text-base leading-relaxed pb-1.5 border-b border-hairline-soft dark:border-gray-800 last:border-0"><span class="w-56 shrink-0 text-right text-muted">Suspek Kecelakaan Kerja :</span><span
                                    class="text-body dark:text-gray-300">{{ $suspekAK }}@if ($suspekAK === 'Ya' && $ketAK !== '') &nbsp;({{ $ketAK }})@endif</span>
                            </p>
                        </div>
                    </x-border-form>
                    <x-border-form title="Pemeriksaan Fisik & Uji Fungsi">
                        <p class="text-base text-body whitespace-pre-line dark:text-gray-300">
                            {{ $dataDaftarTxn['pemeriksaan']['fisik'] ?? '-' }}
                            {{ $dataDaftarTxn['pemeriksaan']['FisikujiFungsi']['FisikujiFungsi'] ?? '' }}</p>
                    </x-border-form>
                    <x-border-form title="Anatomi">
                        @if (!empty($dataDaftarTxn['pemeriksaan']['anatomi']))
                            @foreach ($dataDaftarTxn['pemeriksaan']['anatomi'] as $key => $pAnatomi)
                                @if (!empty($pAnatomi['kelainan']) && $pAnatomi['kelainan'] !== 'Tidak Diperiksa')
                                    <p class="text-base text-body dark:text-gray-300"><span
                                            class="font-semibold">{{ strtoupper($key) }}</span>:
                                        {{ $pAnatomi['kelainan'] }} — {{ $pAnatomi['desc'] ?? '-' }}</p>
                                @endif
                            @endforeach
                        @else
                            <p class="text-base text-muted">-</p>
                        @endif
                    </x-border-form>
                </div>

                {{-- PENUNJANG + DIAGNOSIS + PROSEDUR --}}
                @php
                    // Prioritas freetext dari dokter; fallback ke keterangan ICD-10 (kode disembunyikan).
                    $diagnosisDisplay = trim((string) ($dataDaftarTxn['diagnosisFreeText'] ?? ''));
                    if ($diagnosisDisplay === '') {
                        $diagnosisDescriptions = collect($dataDaftarTxn['diagnosis'] ?? [])
                            ->pluck('diagDesc')
                            ->map(fn($desc) => trim((string) $desc))
                            ->filter()
                            ->values()
                            ->all();
                        $diagnosisDisplay = $diagnosisDescriptions ? implode("\n", $diagnosisDescriptions) : '-';
                    }

                    // Prioritas freetext dari dokter; fallback ke keterangan ICD-9-CM (kode disembunyikan).
                    $prosedurDisplay = trim((string) ($dataDaftarTxn['procedureFreeText'] ?? ''));
                    if ($prosedurDisplay === '') {
                        $procedureDescriptions = collect($dataDaftarTxn['procedure'] ?? [])
                            ->pluck('procedureDesc')
                            ->map(fn($desc) => trim((string) $desc))
                            ->filter()
                            ->values()
                            ->all();
                        $prosedurDisplay = $procedureDescriptions ? implode("\n", $procedureDescriptions) : '-';
                    }
                @endphp
                <x-border-form class="mb-4">
                    <div class="space-y-2.5">
                        <p class="flex gap-3 text-base leading-relaxed pb-1.5 border-b border-hairline-soft dark:border-gray-800 last:border-0"><span class="w-56 shrink-0 text-right text-muted">Penunjang :</span><span
                                class="text-body dark:text-gray-300">{{ $dataDaftarTxn['pemeriksaan']['penunjang'] ?? '-' }}</span>
                        </p>
                        <p class="flex gap-3 text-base leading-relaxed pb-1.5 border-b border-hairline-soft dark:border-gray-800 last:border-0"><span class="w-56 shrink-0 text-right text-muted">Diagnosis :</span><span
                                class="font-semibold text-ink dark:text-gray-100">{!! nl2br(e($diagnosisDisplay)) !!}</span>
                        </p>
                        <p class="flex gap-3 text-base leading-relaxed pb-1.5 border-b border-hairline-soft dark:border-gray-800 last:border-0"><span class="w-56 shrink-0 text-right text-muted">Prosedur :</span><span
                                class="text-body dark:text-gray-300">{!! nl2br(e($prosedurDisplay)) !!}</span>
                        </p>
                    </div>
                </x-border-form>

                {{-- TINDAK LANJUT + TERAPI --}}
                <div class="grid grid-cols-2 gap-4 mb-4">
                    <x-border-form title="Tindak Lanjut">
                        <p class="text-base text-ink dark:text-gray-200">
                            {{ $dataDaftarTxn['perencanaan']['tindakLanjut']['tindakLanjut'] ?? '-' }}@if (!empty($dataDaftarTxn['perencanaan']['tindakLanjut']['keteranganTindakLanjut']))
                                / {{ $dataDaftarTxn['perencanaan']['tindakLanjut']['keteranganTindakLanjut'] }}
                            @endif
                        </p>
                    </x-border-form>
                    <x-border-form title="Terapi">
                        <p class="text-base text-ink whitespace-pre-line dark:text-gray-200">
                            {{ $dataDaftarTxn['perencanaan']['terapi']['terapi'] ?? '-' }}</p>
                    </x-border-form>
                </div>

                {{-- RUJUKAN ANTAR RS (jika ada) --}}
                @php $rujukanAntarRS = $dataDaftarTxn['rujukanAntarRS'] ?? []; @endphp
                @if (array_filter($rujukanAntarRS))
                    <x-border-form title="Rujukan Antar RS" class="mb-4">
                        <div class="grid grid-cols-1 text-sm gap-y-1 gap-x-4 sm:grid-cols-2">
                            @foreach ([['Faskes Dirujuk', $rujukanAntarRS['ppkDirujukNama'] ?? '-'], ['Tgl Rujukan', $rujukanAntarRS['tglRujukan'] ?? '-'], ['Diagnosa Rujukan', $rujukanAntarRS['diagRujukanNama'] ?? '-'], ['Poli Tujuan', $rujukanAntarRS['poliRujukanNama'] ?? '-'], ['Tipe Rujukan', ['0' => 'Penuh', '1' => 'Partial', '2' => 'Balik (PRB)'][$rujukanAntarRS['tipeRujukan'] ?? ''] ?? '-'], ['No. Rujukan BPJS', $rujukanAntarRS['noRujukan'] ?? '-']] as [$label, $nilai])
                                <p><span class="text-muted dark:text-gray-400">{{ $label }} : </span><span class="font-medium text-ink dark:text-gray-200">{{ $nilai }}</span></p>
                            @endforeach
                        </div>
                        @if (!empty($rujukanAntarRS['catatan']))
                            <p class="mt-1 text-sm"><span class="text-muted dark:text-gray-400">Catatan : </span><span class="text-ink dark:text-gray-200">{{ $rujukanAntarRS['catatan'] }}</span></p>
                        @endif
                    </x-border-form>
                @endif

                {{-- OBSERVASI LANJUTAN (jika ada) --}}
                @php $obsList = $dataDaftarTxn['observasi']['observasiLanjutan']['tandaVital'] ?? []; @endphp
                <x-border-form title="Observasi Lanjutan" class="mb-4">
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm whitespace-nowrap">
                                <thead>
                                    <tr class="text-left border-b text-muted-soft border-hairline">
                                        <th class="py-1 pr-2 font-semibold">Waktu</th>
                                        <th class="px-2 font-semibold">TD</th>
                                        <th class="px-2 font-semibold">Nadi</th>
                                        <th class="px-2 font-semibold">Nafas</th>
                                        <th class="px-2 font-semibold">Suhu</th>
                                        <th class="px-2 font-semibold">SPO2</th>
                                        <th class="px-2 font-semibold">GDA</th>
                                        <th class="px-2 font-semibold">GCS</th>
                                        <th class="px-2 font-semibold">Cairan</th>
                                        <th class="px-2 font-semibold">Tetes</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($obsList as $entriObservasi)
                                        <tr class="border-b border-hairline-soft last:border-0 text-ink dark:text-gray-200">
                                            <td class="py-1 pr-2">{{ $entriObservasi['waktuPemeriksaan'] ?? '-' }}</td>
                                            <td class="px-2">{{ $entriObservasi['sistolik'] ?? '-' }}/{{ $entriObservasi['distolik'] ?? '-' }}</td>
                                            <td class="px-2">{{ $entriObservasi['frekuensiNadi'] ?? '-' }}</td>
                                            <td class="px-2">{{ $entriObservasi['frekuensiNafas'] ?? '-' }}</td>
                                            <td class="px-2">{{ $entriObservasi['suhu'] ?? '-' }}</td>
                                            <td class="px-2">{{ $entriObservasi['spo2'] ?? '-' }}</td>
                                            <td class="px-2">{{ $entriObservasi['gda'] ?? '-' }}</td>
                                            <td class="px-2">{{ $entriObservasi['gcs'] ?? '-' }}</td>
                                            <td class="px-2">{{ $entriObservasi['cairan'] ?? '-' }}</td>
                                            <td class="px-2">{{ $entriObservasi['tetesan'] ?? '-' }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="10" class="py-3 text-base text-center text-muted-soft">Belum ada observasi lanjutan.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </x-border-form>

                {{-- PEMBERIAN OBAT & CAIRAN (jika ada) --}}
                @php $ocList = $dataDaftarTxn['observasi']['obatDanCairan']['pemberianObatDanCairan'] ?? []; @endphp
                <x-border-form title="Pemberian Obat & Cairan" class="mb-4">
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="text-left border-b text-muted-soft border-hairline">
                                        <th class="py-1 pr-2 font-semibold whitespace-nowrap">Waktu</th>
                                        <th class="px-2 font-semibold">Obat / Cairan</th>
                                        <th class="px-2 font-semibold">Jumlah</th>
                                        <th class="px-2 font-semibold">Dosis</th>
                                        <th class="px-2 font-semibold">Rute</th>
                                        <th class="px-2 font-semibold">Keterangan</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($ocList as $entriObatDanCairan)
                                        <tr class="border-b border-hairline-soft last:border-0 text-ink dark:text-gray-200">
                                            <td class="py-1 pr-2 whitespace-nowrap">{{ $entriObatDanCairan['waktuPemberian'] ?? '-' }}</td>
                                            <td class="px-2">{{ $entriObatDanCairan['namaObatAtauJenisCairan'] ?? '-' }}</td>
                                            <td class="px-2">{{ $entriObatDanCairan['jumlah'] ?? '-' }}</td>
                                            <td class="px-2">{{ $entriObatDanCairan['dosis'] ?? '-' }}</td>
                                            <td class="px-2">{{ $entriObatDanCairan['rute'] ?? '-' }}</td>
                                            <td class="px-2">{{ $entriObatDanCairan['keterangan'] ?? '-' }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="6" class="py-3 text-base text-center text-muted-soft">Belum ada pemberian obat & cairan.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </x-border-form>

                {{-- TTD --}}
                <x-border-form>
                    <div class="flex items-end justify-between">
                        <div class="text-center min-w-[160px]">
                            <p class="mb-2 text-base text-muted">Perawat / Terapis</p>
                            <div class="flex items-center justify-center h-20">
                                @isset($dataDaftarTxn['anamnesa']['pengkajianPerawatan']['perawatPenerimaCode'])
                                    @if ($dataDaftarTxn['anamnesa']['pengkajianPerawatan']['perawatPenerimaCode'])
                                        @php $ttdPerawat = App\Models\User::where('myuser_code', $dataDaftarTxn['anamnesa']['pengkajianPerawatan']['perawatPenerimaCode'])->value('myuser_ttd_image'); @endphp
                                        @if (!empty($ttdPerawat))
                                            <img class="object-contain h-16 mx-auto"
                                                src="{{ asset('storage/' . $ttdPerawat) }}" alt="TTD Perawat">
                                        @endif
                                    @endif
                                @endisset
                            </div>
                            <div class="pt-1 border-t border-hairline dark:border-gray-700">
                                <p class="text-base font-semibold text-ink dark:text-gray-200">
                                    {{ isset($dataDaftarTxn['anamnesa']['pengkajianPerawatan']['perawatPenerima']) ? strtoupper($dataDaftarTxn['anamnesa']['pengkajianPerawatan']['perawatPenerima']) : '.................................' }}
                                </p>
                            </div>
                        </div>
                        <div class="text-center min-w-[160px]">
                            <p class="mb-2 text-base text-muted">Tulungagung, {{ $dataRekamMedis['tglCetak'] ?? '-' }}</p>
                            <div class="flex items-center justify-center h-20">
                                @isset($dataDaftarTxn['perencanaan']['pengkajianMedis']['drPemeriksa'])
                                    @if ($dataDaftarTxn['perencanaan']['pengkajianMedis']['drPemeriksa'])
                                        @php $ttdDokter = App\Models\User::where('myuser_code', $dataDaftarTxn['drId'] ?? '')->value('myuser_ttd_image'); @endphp
                                        @if (!empty($ttdDokter))
                                            <img class="object-contain h-16 mx-auto"
                                                src="{{ asset('storage/' . $ttdDokter) }}" alt="TTD Dokter">
                                        @endif
                                    @endif
                                @endisset
                            </div>
                            <div class="pt-1 border-t border-hairline dark:border-gray-700">
                                <p class="text-base font-semibold text-ink dark:text-gray-200">
                                    {{ $dataRekamMedis['namaDokter'] ?? 'dr. .................' }}</p>
                                @if (!empty($dataRekamMedis['strDokter']))
                                    <p class="text-base text-muted">STR: {{ $dataRekamMedis['strDokter'] }}</p>
                                @endif
                            </div>
                        </div>
                    </div>
                </x-border-form>

                </div>{{-- /tab resume --}}

                {{-- ════ TAB: MODUL DOKUMEN (view-only — data + cetak) ════ --}}
                @php
                    // Chip tanggal (hijau brand) — kosong → tidak tampil
                    $dateChip = fn($tanggal) => filled($tanggal)
                        ? '<span class="inline-flex items-center px-2 py-0.5 text-xs font-semibold rounded-full bg-brand-green/10 text-brand-green dark:bg-brand-green/20 dark:text-brand-lime">' . e($tanggal) . '</span>'
                        : '';
                    // Pill empty-state netral + ikon tanya
                    $emptyPill = fn($teks) => '<div class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm rounded-lg text-muted-soft bg-surface-soft dark:bg-gray-800/60"><svg class="w-4 h-4 opacity-60" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9 5.25h.008v.008H12v-.008z" /></svg>' . e($teks) . '</div>';
                @endphp
                <div x-show="tab === 'dokumen'" x-cloak class="px-6 py-5 space-y-5">

                    {{-- ── General Consent — viewer (Lihat + Cetak dalam modal) ── --}}
                    <livewire:pages::components.rekam-medis.u-g-d.dokumen-view.general-consent-view-ugd :rjNo="$rjNo"
                        :consent="$dataDaftarTxn['generalConsentPasienUGD'] ?? []" wire:key="rm-view-gc-ugd-{{ $rjNo }}" />

                    {{-- ── Inform Consent — viewer (Lihat + Cetak dalam modal) ── --}}
                    <livewire:pages::components.rekam-medis.u-g-d.dokumen-view.inform-consent-view-ugd :rjNo="$rjNo"
                        :entries="$dataDaftarTxn['informConsentPasienUGD'] ?? []" wire:key="rm-view-ic-ugd-{{ $rjNo }}" />

                    {{-- ── Form Transfer UGD → RI — viewer (Lihat + Cetak dalam modal) ── --}}
                    <livewire:pages::components.rekam-medis.u-g-d.dokumen-view.form-trf-ugd-ri-view-ugd :rjNo="$rjNo"
                        :trf="$dataDaftarTxn['trfUgd'] ?? []" wire:key="rm-view-trf-ugd-{{ $rjNo }}" />

                    {{-- ── Form Penjaminan & Orientasi Kamar — viewer (Lihat + Cetak dalam modal) ── --}}
                    <livewire:pages::components.rekam-medis.u-g-d.dokumen-view.form-penjaminan-view-ugd :rjNo="$rjNo"
                        :entries="$dataDaftarTxn['formPenjaminanOrientasiKamar'] ?? []" wire:key="rm-view-penjaminan-ugd-{{ $rjNo }}" />

                    {{-- ── Surat Keterangan Kematian — viewer; hanya untuk pasien P0, supaya tak
                         muncul kartu "belum ada" di rekam medis tiap pasien yang pulang sehat ── --}}
                    @if (($dataDaftarTxn['screening']['triaseSaran'] ?? '') === 'P0')
                        <livewire:pages::components.rekam-medis.u-g-d.dokumen-view.surat-kematian-view-ugd :rjNo="$rjNo"
                            :surat="$dataDaftarTxn['suratKematianUGD'] ?? []" wire:key="rm-view-sk-ugd-{{ $rjNo }}" />
                    @endif

                    {{-- ── Pengkajian Akhir Hayat — viewer (Lihat + Cetak dalam modal) ── --}}
                    <livewire:pages::components.rekam-medis.u-g-d.dokumen-view.akhir-hayat-view-ugd :rjNo="$rjNo"
                        :entries="$dataDaftarTxn['pengkajianAkhirHayatUGD'] ?? []" wire:key="rm-view-akhir-hayat-ugd-{{ $rjNo }}" />

                    {{-- ── Penolakan Pengobatan / Obat Tertentu — viewer (Lihat + Cetak dalam modal) ── --}}
                    <livewire:pages::components.rekam-medis.u-g-d.dokumen-view.penolakan-obat-view-ugd :rjNo="$rjNo"
                        :entries="$dataDaftarTxn['penolakanObatUGD'] ?? []" wire:key="rm-view-penolakan-obat-ugd-{{ $rjNo }}" />

                    {{-- ── Pengkajian Pre Operasi (Site Marking) — viewer (Lihat + Cetak dalam modal) ── --}}
                    <livewire:pages::components.rekam-medis.u-g-d.dokumen-view.pengkajian-pre-op-view-ugd :rjNo="$rjNo"
                        :entries="$dataDaftarTxn['pengkajianPreOpUGD'] ?? []" wire:key="rm-view-pengkajian-pre-op-ugd-{{ $rjNo }}" />
                </div>

                {{-- ════ TAB: HASIL PENUNJANG (lab / radiologi / upload — view-only) ════ --}}
                @php $regNoPenunjang = (string) ($dataDaftarTxn['regNo'] ?? ''); @endphp
                <div x-show="tab === 'penunjang'" x-cloak class="px-6 py-5" x-data="{ sub: 'laboratorium' }">
                    <div class="flex flex-wrap gap-1 mb-4 border-b border-hairline dark:border-gray-700">
                        <button type="button" x-on:click="sub = 'laboratorium'"
                            :class="sub === 'laboratorium' ? 'border-brand-green text-brand-green' : 'border-transparent text-muted hover:text-ink'"
                            class="px-4 py-2 -mb-px text-sm font-semibold transition-colors border-b-2">Laboratorium</button>
                        <button type="button" x-on:click="sub = 'radiologi'"
                            :class="sub === 'radiologi' ? 'border-brand-green text-brand-green' : 'border-transparent text-muted hover:text-ink'"
                            class="px-4 py-2 -mb-px text-sm font-semibold transition-colors border-b-2">Radiologi</button>
                        <button type="button" x-on:click="sub = 'upload'"
                            :class="sub === 'upload' ? 'border-brand-green text-brand-green' : 'border-transparent text-muted hover:text-ink'"
                            class="px-4 py-2 -mb-px text-sm font-semibold transition-colors border-b-2">Upload Penunjang</button>
                    </div>

                    <div x-show="sub === 'laboratorium'" x-cloak class="space-y-4">
                        <livewire:pages::components.rekam-medis.penunjang.laboratorium-display.laboratorium-display
                            :regNo="$regNoPenunjang" wire:key="rm-ugd-penunjang-lab-{{ $regNoPenunjang }}" />
                        <livewire:pages::components.rekam-medis.penunjang.lab-luar-display.lab-luar-display
                            :regNo="$regNoPenunjang" wire:key="rm-ugd-penunjang-lab-luar-{{ $regNoPenunjang }}" />
                    </div>

                    <div x-show="sub === 'radiologi'" x-cloak>
                        <livewire:pages::components.rekam-medis.penunjang.radiologi-display.radiologi-display
                            :regNo="$regNoPenunjang" wire:key="rm-ugd-penunjang-rad-{{ $regNoPenunjang }}" />
                    </div>

                    <div x-show="sub === 'upload'" x-cloak>
                        <livewire:pages::components.rekam-medis.penunjang.upload-penunjang-display.upload-penunjang-display
                            :regNo="$regNoPenunjang" wire:key="rm-ugd-penunjang-upload-{{ $regNoPenunjang }}" />
                    </div>
                </div>

            </div>

            {{-- ── FOOTER ── --}}
            <div
                class="sticky bottom-0 z-10 px-6 py-4 bg-canvas border-t border-hairline dark:bg-gray-900 dark:border-gray-700">
                <div class="flex items-center justify-between gap-3">
                    <x-rm.record-nav :pos="$navPos" :total="$navTotal" />
                    <div class="flex gap-2">
                        <x-secondary-button type="button" wire:click="closeModal">Tutup</x-secondary-button>
                        <x-primary-button type="button" wire:click="cetakPdf" wire:loading.attr="disabled">
                            <svg wire:loading.remove class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            <svg wire:loading class="w-4 h-4 mr-1.5 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10"
                                    stroke="currentColor" stroke-width="4" />
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z" />
                            </svg>
                            <span wire:loading.remove>Cetak PDF</span>
                            <span wire:loading>Menyiapkan PDF...</span>
                        </x-primary-button>
                    </div>
                </div>
            </div>

        </div>
    </x-modal>
</div>
