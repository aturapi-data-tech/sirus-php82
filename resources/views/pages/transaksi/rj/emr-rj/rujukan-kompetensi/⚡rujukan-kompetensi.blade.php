<?php
// resources/views/pages/transaksi/rj/rujukan-kompetensi/rujukan-kompetensi.blade.php
// Rujukan Berbasis Kompetensi (Sisrute + Satu Sehat) — Tahap 1 MVP

use Livewire\Component;
use Livewire\Attributes\On;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Rj\EmrRJTrait;
use App\Http\Traits\BPJS\SisruteTrait;

new class extends Component {
    use EmrRJTrait, SisruteTrait;

    // ── Target & context
    public ?string $rjNo = null;
    public array $dataRJ = [];

    // ── Form: data rujukan
    public string $tglRencanaKunjungan = '';   // YYYY-MM-DD (format HTML date input)
    public string $kodeDiagnosa = '';          // ICD-10
    public string $kodeSpesialis = '';
    public string $kodePropinsi = '';
    public string $namaPropinsi = '';
    public string $kodeKabupaten = '';
    public string $namaKabupaten = '';
    public string $catatan = '';
    public string $patientInstruction = '';
    public string $keteranganRujukan = '';
    public string $jnsPelayanan = '2';        // 1=RI, 2=RJ (default)
    public string $tipeRujukan = '0';         // 0=Penuh, 1=Partial, 2=Rujuk Balik
    public string $poliRujukan = '';       // kd_poli_bpjs yang dikirim ke Sisrute (input manual 3-digit)
    public string $kodeSarana = '';        // Sarana khusus yang dibutuhkan di faskes tujuan (opsional, parameter GetFaskesRujukan)

    // ── Kriteria rujukan — default 3 item dari dokumentasi, akan di-overwrite
    //    oleh response GetKriteriaRujukan (dinamis per diagnosa + faskes).
    public array $kriteriaItems = [
        ['linkId' => '3216', 'text' => 'Terapy/Pengobatan', 'type' => 'boolean', 'answer' => false],
        ['linkId' => '3215', 'text' => 'Tindakan Medis',    'type' => 'string',  'answer' => ''],
        ['linkId' => '3214', 'text' => 'Upaya Diagnosis',   'type' => 'boolean', 'answer' => false],
    ];

    // Status sumber kriteria: 'default' (hardcode) / 'server' (dari GetKriteriaRujukan)
    public string $kriteriaSource = 'default';
    public bool $loadingKriteria = false;

    // ── Auto-filled dari context (readonly)
    public string $noSep = '';
    public string $kodeFaskesSatuSehat = '';
    public string $idPasienSatuSehat = '';
    public string $kdDokterSatuSehat = '';
    public string $encounterReference = '';

    // ── Result
    public array $faskesList = [];
    public ?array $faskesTerpilih = null;
    public ?array $rujukanResult = null;

    // ── Missing data warnings
    public array $missingFields = [];

    public function mount(): void
    {
        if (!$this->rjNo) {
            return;
        }
        $this->loadContext();
    }

    /**
     * Trigger tampilkan modal — reload fresh data + state rujukan sebelum render.
     * Kriteria TIDAK di-fetch otomatis — user klik tombol "Ambil Kriteria" di
     * Section C saat siap, supaya flow lebih deliberate (hindari call API saat
     * user cuma buka modal untuk lihat ringkasan).
     */
    public function openModal(): void
    {
        $this->loadContext();
        $this->restoreState();
        $this->faskesList = [];   // hasil search sesi sebelumnya tidak di-persist
        $this->dispatch('open-modal', name: 'rujukan-kompetensi-rj');
    }

    /**
     * GET /Rujukan/GetKriteriaRujukan — ambil daftar pertanyaan kriteria + jejaring wilayah.
     * Trigger manual via tombol "Ambil Kriteria" / "Muat Ulang" di Section C.
     */
    public function muatKriteria(bool $silent = false): void
    {
        if (!$this->kodeDiagnosa || !$this->kodeFaskesSatuSehat) {
            if (!$silent) {
                $this->dispatch('toast', type: 'error',
                    message: 'Diagnosa & Kode Faskes Satu Sehat harus terisi dulu.');
            }
            return;
        }

        $this->loadingKriteria = true;
        try {
            $res = self::sisrute_get_kriteria_rujukan($this->kodeDiagnosa, $this->kodeFaskesSatuSehat)
                ->getOriginalContent();

            if (($res['metadata']['code'] ?? 0) != 200) {
                if (!$silent) {
                    $this->dispatch('toast', type: 'error',
                        message: 'Gagal muat kriteria: ' . ($res['metadata']['message'] ?? '-'));
                }
                return;
            }

            $payload = $res['response'] ?? [];

            // Struktur fleksibel — cek beberapa lokasi kemungkinan
            $items = $payload['kriteriaRujukan']['item']
                ?? $payload['item']
                ?? $payload['kriteria']
                ?? $payload['list']
                ?? [];

            if (empty($items)) {
                if (!$silent) {
                    $this->dispatch('toast', type: 'warning',
                        message: 'Server tidak kasih kriteria — pakai default.');
                }
                return;
            }

            // Normalize struktur kriteria
            $this->kriteriaItems = array_values(array_map(function ($k) {
                $linkId = (string) ($k['linkId'] ?? '');
                $text   = (string) ($k['text'] ?? '');
                // Detect type: ada field "type" explicit, atau infer dari nama (Tindakan Medis = string ICD9)
                $type = strtolower((string) ($k['type'] ?? ''));
                if ($type === '') {
                    $type = stripos($text, 'tindakan') !== false ? 'string' : 'boolean';
                }
                return [
                    'linkId' => $linkId,
                    'text'   => $text,
                    'type'   => in_array($type, ['string', 'boolean']) ? $type : 'boolean',
                    'answer' => $type === 'string' ? '' : false,
                ];
            }, $items));

            // Auto-fill jejaring wilayah dari response (kalau belum di-set user)
            $wilayah = $payload['jejaringWilayah'] ?? $payload['codeJejaringWilayah'] ?? [];
            if (!empty($wilayah)) {
                if (!$this->kodePropinsi)  $this->kodePropinsi  = (string) ($wilayah['kodePropinsi']  ?? '');
                if (!$this->namaPropinsi)  $this->namaPropinsi  = (string) ($wilayah['namaPropinsi']  ?? '');
                if (!$this->kodeKabupaten) $this->kodeKabupaten = (string) ($wilayah['kodeKabupaten'] ?? '');
                if (!$this->namaKabupaten) $this->namaKabupaten = (string) ($wilayah['namaKabupaten'] ?? '');
            }

            $this->kriteriaSource = 'server';
            if (!$silent) {
                $this->dispatch('toast', type: 'success',
                    message: count($this->kriteriaItems) . ' kriteria dimuat dari Satu Sehat Rujukan.');
            }
        } catch (\Throwable $e) {
            if (!$silent) {
                $this->dispatch('toast', type: 'error', message: 'Error muat kriteria: ' . $e->getMessage());
            }
        } finally {
            $this->loadingKriteria = false;
        }
    }

    private function loadContext(): void
    {
        $data = $this->findDataRJ($this->rjNo);
        if (!$data) {
            $this->dispatch('toast', type: 'error', message: 'Data RJ tidak ditemukan.');
            return;
        }
        $this->dataRJ = $data;

        // Auto-fill context fields
        $this->noSep               = (string) ($data['sep']['noSep'] ?? '');
        $this->kodeFaskesSatuSehat = (string) env('SATUSEHAT_ORGANIZATION_ID', '');

        $regNo = $data['regNo'] ?? '';
        $this->idPasienSatuSehat = $regNo
            ? (string) (DB::table('rsmst_pasiens')->where('reg_no', $regNo)->value('patient_uuid') ?? '')
            : '';

        $drId = $data['drId'] ?? '';
        $this->kdDokterSatuSehat = $drId
            ? (string) (DB::table('rsmst_doctors')->where('dr_id', $drId)->value('dr_uuid') ?? '')
            : '';

        $encId = $data['satuSehat']['encounterId'] ?? '';
        $this->encounterReference = $encId ? 'Encounter/' . $encId : '';

        // Defaults untuk tgl rencana kunjungan kalau belum di-set
        if (!$this->tglRencanaKunjungan) {
            $this->tglRencanaKunjungan = Carbon::tomorrow()->format('Y-m-d');
        }

        // Diagnosa default dari EMR (kalau belum di-set & ada)
        if (!$this->kodeDiagnosa) {
            $firstDiag = collect($data['diagnosis'] ?? [])->first() ?: [];
            $this->kodeDiagnosa = (string) ($firstDiag['icdX'] ?? $firstDiag['diagId'] ?? '');
        }

        // Kode Spesialis default = kode poli BPJS pasien yang sedang dilayani.
        // Rujukan biasanya ke spesialis yang sama di RS tujuan. User bisa override.
        if (!$this->kodeSpesialis) {
            $this->kodeSpesialis = (string) ($data['kdpolibpjs'] ?? '');
        }

        // Warning kalau ada field Satu Sehat yang kosong
        $this->missingFields = [];
        if (!$this->noSep)               $this->missingFields[] = 'No. SEP — pasang SEP VClaim dulu.';
        if (!$this->kodeFaskesSatuSehat) $this->missingFields[] = 'Kode Faskes Satu Sehat — set env SATUSEHAT_ORGANIZATION_ID.';
        if (!$this->idPasienSatuSehat)   $this->missingFields[] = 'ID Pasien Satu Sehat — patient_uuid kosong di rsmst_pasiens (sync dulu via Satu Sehat Patient API).';
        if (!$this->kdDokterSatuSehat)   $this->missingFields[] = 'Kd Dokter Satu Sehat — dr_uuid kosong di rsmst_doctors untuk dr_id: ' . $drId . '.';
        if (!$this->encounterReference)  $this->missingFields[] = 'Encounter Reference — kirim Encounter ke Satu Sehat dulu (modul kirim-encounter).';
    }

    public function cariFaskes(): void
    {
        if (!empty($this->missingFields)) {
            $this->dispatch('toast', type: 'error', message: 'Masih ada data wajib yang kosong (lihat panel atas).');
            return;
        }

        $payload = [
            'kodeFaskesSatuSehat' => $this->kodeFaskesSatuSehat,
            'kodeDiagnosa'        => $this->kodeDiagnosa,
            'kodeSpesialis'       => $this->kodeSpesialis,
            'kodeSarana'          => $this->kodeSarana, // opsional
            'tglRencanaKunjungan' => Carbon::parse($this->tglRencanaKunjungan)->format('d-m-Y'),
            'kriteriaRujukan'     => ['item' => $this->buildKriteriaPayload()],
            'codeJejaringWilayah' => [
                'kodePropinsi'  => $this->kodePropinsi,
                'namaPropinsi'  => $this->namaPropinsi,
                'kodeKabupaten' => $this->kodeKabupaten,
                'namaKabupaten' => $this->namaKabupaten,
            ],
            'encounter' => ['reference' => $this->encounterReference],
        ];

        $res = self::sisrute_get_faskes_rujukan($payload)->getOriginalContent();
        if (($res['metadata']['code'] ?? 0) != 200) {
            $this->dispatch('toast', type: 'error', message: 'Gagal cari faskes: ' . ($res['metadata']['message'] ?? '-'));
            return;
        }

        $this->faskesList = $res['response']['list'] ?? [];
        $this->faskesTerpilih = null;
        $count = count($this->faskesList);
        $this->dispatch('toast', type: $count > 0 ? 'success' : 'warning',
            message: $count > 0 ? "Ditemukan {$count} faskes tujuan." : 'Tidak ada faskes yang cocok.');
    }

    public function pilihFaskes(int $idx): void
    {
        $this->faskesTerpilih = $this->faskesList[$idx] ?? null;
    }

    public function kirimRujukan(): void
    {
        if (!$this->faskesTerpilih) {
            $this->dispatch('toast', type: 'error', message: 'Pilih faskes tujuan dulu.');
            return;
        }

        $user = auth()->user()->name ?? auth()->user()->myuser_name ?? 'Sirus';

        $payload = [
            'noSep'               => $this->noSep,
            'tglRujukan'          => Carbon::today()->format('Y-m-d'),
            'tglRencanaKunjungan' => $this->tglRencanaKunjungan,
            'ppkDirujuk'          => (string) ($this->faskesTerpilih['kdppk'] ?? ''),
            'jnsPelayanan'        => $this->jnsPelayanan,
            'catatan'             => $this->catatan ?: '-',
            'diagRujukan'         => $this->kodeDiagnosa,
            'tipeRujukan'         => $this->tipeRujukan,
            'poliRujukan'         => $this->poliRujukan ?: $this->kodeSpesialis,
            'user'                => (string) $user,
            'satuSehatRujukan' => [
                'kodeFaskesSatuSehat'         => $this->kodeFaskesSatuSehat,
                'idPasienSatuSehat'           => $this->idPasienSatuSehat,
                'kdppkSatuSehatTujuanRujukan' => (string) ($this->faskesTerpilih['kodeFaskesSatuSehat'] ?? ''),
                'kdDokterSatuSehat'           => $this->kdDokterSatuSehat,
                'encounter'                   => ['reference' => $this->encounterReference],
                'patientInstruction'          => $this->patientInstruction,
                'kriteriaRujukan'             => ['item' => $this->buildKriteriaPayload()],
                'keteranganRujukan'           => $this->keteranganRujukan,
                'codeJejaringWilayah' => [
                    'kodePropinsi'  => $this->kodePropinsi,
                    'namaPropinsi'  => $this->namaPropinsi,
                    'kodeKabupaten' => $this->kodeKabupaten,
                    'namaKabupaten' => $this->namaKabupaten,
                ],
            ],
        ];

        $res = self::sisrute_post_kunjungan($payload)->getOriginalContent();
        if (($res['metadata']['code'] ?? 0) != 200) {
            $this->dispatch('toast', type: 'error', message: 'Gagal kirim rujukan: ' . ($res['metadata']['message'] ?? '-'));
            return;
        }

        $this->rujukanResult = $res['response']['rujukan'] ?? $res['response'] ?? [];

        // Persist ke JSON dataRJ.rujukanKompetensi — audit trail & survives modal close.
        try {
            $this->persistState();
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'warning',
                message: 'Rujukan dibuat tapi gagal simpan state lokal: ' . $e->getMessage());
            return;
        }

        $this->dispatch('toast', type: 'success',
            message: 'Rujukan berhasil dibuat: ' . ($this->rujukanResult['noRujukan'] ?? '-'));
    }

    /**
     * Simpan state rujukan ke datadaftarpolirj_json.rujukanKompetensi.
     * Pola sama dengan iDRG / SKDP / PRB — pakai lockRJRow + updateJsonRJ.
     */
    private function persistState(): void
    {
        DB::transaction(function () {
            $this->lockRJRow($this->rjNo);
            $data = $this->findDataRJ($this->rjNo);

            $data['rujukanKompetensi'] = [
                'form' => [
                    'tglRencanaKunjungan' => $this->tglRencanaKunjungan,
                    'kodeDiagnosa'        => $this->kodeDiagnosa,
                    'kodeSpesialis'       => $this->kodeSpesialis,
                    'kodePropinsi'        => $this->kodePropinsi,
                    'namaPropinsi'        => $this->namaPropinsi,
                    'kodeKabupaten'       => $this->kodeKabupaten,
                    'namaKabupaten'       => $this->namaKabupaten,
                    'catatan'             => $this->catatan,
                    'patientInstruction'  => $this->patientInstruction,
                    'keteranganRujukan'   => $this->keteranganRujukan,
                    'jnsPelayanan'        => $this->jnsPelayanan,
                    'tipeRujukan'         => $this->tipeRujukan,
                    'poliRujukan'         => $this->poliRujukan,
                    'kodeSarana'          => $this->kodeSarana,
                    'kriteriaItems'       => $this->kriteriaItems,
                    'kriteriaSource'      => $this->kriteriaSource,
                ],
                'faskesTerpilih' => $this->faskesTerpilih,
                'rujukanResult'  => $this->rujukanResult,
                'sentAt'         => $this->rujukanResult ? Carbon::now()->toIso8601String() : null,
            ];

            $this->updateJsonRJ((int) $this->rjNo, $data);
            $this->dataRJ = $data;
        });
    }

    /**
     * Restore state form + hasil rujukan dari JSON (kalau sudah pernah disimpan).
     * Dipanggil saat modal dibuka supaya user bisa lihat/lanjutkan sesi sebelumnya.
     */
    private function restoreState(): void
    {
        $saved = $this->dataRJ['rujukanKompetensi'] ?? null;
        if (!$saved) return;

        $form = $saved['form'] ?? [];
        $this->tglRencanaKunjungan = $form['tglRencanaKunjungan'] ?? $this->tglRencanaKunjungan;
        $this->kodeDiagnosa        = $form['kodeDiagnosa']        ?? $this->kodeDiagnosa;
        $this->kodeSpesialis       = $form['kodeSpesialis']       ?? '';
        $this->kodePropinsi        = $form['kodePropinsi']        ?? '';
        $this->namaPropinsi        = $form['namaPropinsi']        ?? '';
        $this->kodeKabupaten       = $form['kodeKabupaten']       ?? '';
        $this->namaKabupaten       = $form['namaKabupaten']       ?? '';
        $this->catatan             = $form['catatan']             ?? '';
        $this->patientInstruction  = $form['patientInstruction']  ?? '';
        $this->keteranganRujukan   = $form['keteranganRujukan']   ?? '';
        $this->jnsPelayanan        = $form['jnsPelayanan']        ?? '2';
        $this->tipeRujukan         = $form['tipeRujukan']         ?? '0';
        $this->poliRujukan         = $form['poliRujukan']         ?? '';
        $this->kodeSarana          = $form['kodeSarana']           ?? '';
        if (!empty($form['kriteriaItems'])) {
            $this->kriteriaItems = $form['kriteriaItems'];
        }
        $this->kriteriaSource = (string) ($form['kriteriaSource'] ?? 'default');

        $this->faskesTerpilih = $saved['faskesTerpilih'] ?? null;
        $this->rujukanResult  = $saved['rujukanResult']  ?? null;
    }

    private function buildKriteriaPayload(): array
    {
        return array_map(function ($it) {
            $answer = $it['type'] === 'boolean'
                ? ['valueBoolean' => (bool) $it['answer']]
                : ['valueString' => (string) $it['answer']];
            return [
                'linkId' => (string) $it['linkId'],
                'text'   => (string) $it['text'],
                'answer' => [$answer],
            ];
        }, $this->kriteriaItems);
    }
};
?>

<div>
    {{-- Inline panel (tampil di dalam Tindak Lanjut = Rujuk) --}}
    <div class="p-4 rounded-xl border border-indigo-200 dark:border-indigo-700 bg-indigo-50/40 dark:bg-indigo-900/10">
        <div class="flex items-start justify-between gap-4">
            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2">
                    <svg class="w-5 h-5 text-indigo-600 dark:text-indigo-400 shrink-0" fill="none" stroke="currentColor"
                        viewBox="0 0 24 24" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                    </svg>
                    <span class="font-semibold text-indigo-800 dark:text-indigo-200">Rujukan Berbasis Kompetensi</span>
                    <span class="text-[10px] px-1.5 py-0.5 rounded bg-indigo-200/60 dark:bg-indigo-800/40 text-indigo-700 dark:text-indigo-300 uppercase tracking-wider">Sisrute · Satu Sehat</span>
                </div>
                <p class="mt-1 text-xs text-indigo-700/80 dark:text-indigo-300/70">
                    Cari faskes tujuan berdasarkan diagnosa + kompetensi, lalu kirim rujukan lengkap (Sisrute + Satu Sehat).
                </p>
                @if ($rujukanResult)
                    <div class="mt-2 inline-flex items-center gap-1.5 px-2 py-1 rounded-md bg-emerald-100 dark:bg-emerald-900/30 text-xs text-emerald-700 dark:text-emerald-300">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                        </svg>
                        Rujukan sudah dibuat: <span class="font-mono font-bold">{{ $rujukanResult['noRujukan'] ?? '-' }}</span>
                    </div>
                @elseif (!empty($missingFields))
                    <div class="mt-2 inline-flex items-center gap-1.5 px-2 py-1 rounded-md bg-amber-100 dark:bg-amber-900/30 text-xs text-amber-700 dark:text-amber-300">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
                        </svg>
                        {{ count($missingFields) }} data belum lengkap
                    </div>
                @endif
            </div>
            <x-primary-button type="button" wire:click="openModal" wire:loading.attr="disabled" wire:target="openModal"
                class="!bg-indigo-600 hover:!bg-indigo-700 shrink-0">
                <span wire:loading.remove wire:target="openModal">
                    {{ $rujukanResult ? 'Lihat / Ubah Rujukan' : 'Buat Rujukan' }}
                </span>
                <span wire:loading wire:target="openModal"><x-loading /> Memuat…</span>
            </x-primary-button>
        </div>
    </div>

    <x-modal name="rujukan-kompetensi-rj" size="full" height="full" focusable>
        <div class="flex flex-col min-h-0">

            {{-- HEADER --}}
            <div class="px-6 py-5 border-b border-gray-200 dark:border-gray-700">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">Rujukan Berbasis Kompetensi
                            (Sisrute)</h2>
                        <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                            RJ: <span class="font-mono">{{ $rjNo ?? '-' }}</span>
                            &mdash; SEP: <span class="font-mono">{{ $noSep ?: '-' }}</span>
                            &mdash; Pasien: <span class="font-semibold">{{ $dataRJ['regName'] ?? '-' }}</span>
                        </p>
                    </div>
                    <x-icon-button color="gray" type="button"
                        x-on:click="$dispatch('close-modal', { name: 'rujukan-kompetensi-rj' })">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd"
                                d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                clip-rule="evenodd" />
                        </svg>
                    </x-icon-button>
                </div>
            </div>

            <div class="flex-1 overflow-y-auto px-6 py-5 bg-gray-50/70 dark:bg-gray-950/20 space-y-5">

                @php
                    $guide = [
                        ['key' => 'A', 'title' => 'A. Data Rujukan (langkah 1)', 'items' => [
                            ['n' => '1', 'head' => 'Tgl Rencana Kunjungan', 'body' => 'Default = besok. Tanggal pasien datang ke RS tujuan. Ubah kalau perlu.'],
                            ['n' => '2', 'head' => 'Diagnosa (ICD-10)', 'body' => 'Otomatis terisi dari diagnosis pertama di EMR. Memicu auto fetch Kriteria + Jejaring Wilayah saat diubah.'],
                            ['n' => '3', 'head' => 'Kode Spesialis', 'body' => 'Otomatis dari kdpolibpjs pasien saat ini. User bisa override kalau rujuk ke spesialis berbeda. Dipakai untuk field "Spesialis/Sub Spesialis" di GetFaskesRujukan.'],
                            ['n' => '4', 'head' => 'Poli Rujukan', 'body' => 'Input manual kode poli BPJS 3-digit di faskes tujuan (mis. 005). Kalau kosong, fallback pakai Kode Spesialis.'],
                            ['n' => '5', 'head' => 'Kode Sarana (opsional)', 'body' => 'Parameter GetFaskesRujukan untuk filter faskes berdasarkan sarana khusus (mis. alat CT scan, MRI). Kosongkan kalau tidak perlu.'],
                            ['n' => '6', 'head' => 'Jenis & Tipe Rujukan', 'body' => 'Jenis: 2=RJ (default), 1=RI. Tipe: 0=Penuh, 1=Partial, 2=Rujuk Balik.'],
                        ]],
                        ['key' => 'B', 'title' => 'B. Data Satu Sehat (auto)', 'items' => [
                            ['n' => '1', 'head' => 'Kode Faskes Satu Sehat', 'body' => 'Dari env SATUSEHAT_ORGANIZATION_ID — kode Satu Sehat RS sendiri. Tetap per instance RS.'],
                            ['n' => '2', 'head' => 'ID Pasien Satu Sehat', 'body' => 'Dari rsmst_pasiens.patient_uuid. Kalau kosong — sync pasien lewat modul Satu Sehat Patient dulu.'],
                            ['n' => '3', 'head' => 'Kd Dokter Satu Sehat', 'body' => 'Dari rsmst_doctors.dr_uuid untuk dokter DPJP. Kalau kosong — isi dulu di master dokter.'],
                            ['n' => '4', 'head' => 'Encounter Reference', 'body' => 'Dari dataRJ.satuSehat.encounterId. Kalau kosong — kirim Encounter Satu Sehat dulu (modul kirim-encounter di Satu Sehat RJ).'],
                        ]],
                        ['key' => 'C', 'title' => 'C. Kriteria Rujukan + Wilayah (langkah 2)', 'items' => [
                            ['n' => '1', 'head' => 'Klik tombol "⬇ Ambil Kriteria"', 'body' => 'Trigger manual — sistem POST ke GET /Rujukan/GetKriteriaRujukan dengan diagnosa + kode faskes RS kita. Server balas: daftar pertanyaan kriteria (dinamis per diagnosa) + rekomendasi jejaring wilayah.'],
                            ['n' => '2', 'head' => 'Fallback kalau belum dimuat', 'body' => 'Sebelum user klik tombol, Section C tampil 3 kriteria default (3216 Terapy/Pengobatan boolean, 3215 Tindakan Medis ICD-9 string, 3214 Upaya Diagnosis boolean) sebagai fallback. Badge "⚠ Belum dimuat" di header.'],
                            ['n' => '3', 'head' => 'Muat Ulang saat ganti diagnosa', 'body' => 'Setelah kriteria pernah dimuat, ubah diagnosa di Section A → klik "🔄 Muat Ulang" supaya kriteria refetch sesuai diagnosa baru (auto-refresh tidak aktif — biar user kontrol timing).'],
                            ['n' => '4', 'head' => 'Isi jawaban kriteria', 'body' => 'Boolean = toggle Ya/Tidak. String = input kode (biasanya ICD-9 untuk Tindakan Medis). Field boleh kosong — tidak semua pertanyaan wajib.'],
                            ['n' => '5', 'head' => 'Jejaring Wilayah (auto-fill)', 'body' => 'Kode & nama Provinsi/Kabupaten auto-fill dari response server saat Ambil Kriteria. Boleh override kalau mau rujuk ke wilayah lain.'],
                        ]],
                        ['key' => 'D', 'title' => 'D. Cari & Pilih Faskes (langkah 3)', 'items' => [
                            ['n' => '1', 'head' => 'Klik "Cari Faskes"', 'body' => 'POST /Rujukan/GetFaskesRujukan ke VClaim-Sisrute dengan diagnosa + kompetensi + sarana + kriteria + wilayah + encounter.'],
                            ['n' => '2', 'head' => 'Tabel hasil', 'body' => 'Per baris tampilkan: Nama PPK, Kode PPK, Kelas, Kota, Jadwal Praktek Spesialis, Jarak (meter), Kapasitas %.'],
                            ['n' => '3', 'head' => 'Pilih 1 Faskes', 'body' => 'Klik tombol "Pilih" di baris. kdppk + kodeFaskesSatuSehat tujuan tersimpan. Card konfirmasi + tombol Kirim muncul.'],
                            ['n' => '4', 'head' => 'Klik "Kirim Rujukan"', 'body' => 'POST /Rujukan/Insert (langkah 4). VClaim-Sisrute teruskan ke Satu Sehat. Output: noRujukan BPJS + noRujukanSatuSehat + serviceRequestId FHIR.'],
                        ]],
                        ['key' => 'E', 'title' => 'E. Hasil & Persistensi (langkah 4)', 'items' => [
                            ['n' => '1', 'head' => 'noRujukan BPJS', 'body' => 'Nomor rujukan resmi BPJS, dipakai pasien saat datang ke RS tujuan (dicetak bersama SEP).'],
                            ['n' => '2', 'head' => 'noRujukanSatuSehat', 'body' => 'Nomor rujukan versi Satu Sehat Kemenkes.'],
                            ['n' => '3', 'head' => 'serviceRequestId', 'body' => 'FHIR ServiceRequest ID di Satu Sehat — reference ke resource rujukan di platform Kemenkes.'],
                            ['n' => '4', 'head' => 'Detail lengkap', 'body' => 'Panel hasil tampilkan: asal rujukan (RS kita), tujuan rujukan, poli tujuan, diagnosa, data peserta BPJS, tgl rujukan.'],
                            ['n' => '5', 'head' => 'Persistensi', 'body' => 'Hasil otomatis disimpan di rstxn_rjhdrs.datadaftarpolirj_json pada key rujukanKompetensi. Buka modal lagi → state form + hasil otomatis ter-restore.'],
                        ]],
                        ['key' => '!', 'title' => '⚠ Penting', 'items' => [
                            ['n' => '—', 'head' => 'Scope RJ (Rawat Jalan)', 'body' => 'Flow ini khusus RJ antar-faskes lewat gateway VClaim-Sisrute BPJS. UGD & Inap langsung ke Satu Sehat FHIR (trait lain, belum dibuat).'],
                            ['n' => '—', 'head' => 'Alur 1 kali POST', 'body' => 'VClaim-Sisrute = orkestrator gabungan. Kita POST ke BPJS, BPJS teruskan ke Satu Sehat Rujukan. Respons balik dapat kedua nomor rujukan (BPJS + Satu Sehat).'],
                            ['n' => '—', 'head' => 'Prasyarat', 'body' => 'SEP, Encounter Satu Sehat, patient_uuid, dr_uuid. Kalau salah satu belum ada, tombol akan gagal (tampil di kotak merah atas).'],
                            ['n' => '—', 'head' => 'Env base URL', 'body' => 'Set SISRUTE_URL = "https://apijkn-dev.bpjs-kesehatan.go.id/vclaim-sisrute-rest" di .env. Credential VClaim existing dipakai sebagai fallback.'],
                            ['n' => '—', 'head' => 'Delete belum ada', 'body' => 'Fitur hapus rujukan belum diimplementasi (stub 501). Pastikan faskes tujuan & data benar sebelum kirim.'],
                        ]],
                    ];
                @endphp

                {{-- MISSING FIELDS WARNING --}}
                @if (!empty($missingFields))
                    <div class="p-4 rounded-xl border-2 border-red-300 dark:border-red-700 bg-red-50 dark:bg-red-900/20">
                        <div class="font-semibold text-red-700 dark:text-red-300 mb-2">Data berikut belum siap:</div>
                        <ul class="list-disc pl-5 text-sm text-red-700 dark:text-red-300 space-y-1">
                            @foreach ($missingFields as $m)
                                <li>{{ $m }}</li>
                            @endforeach
                        </ul>
                        <p class="mt-2 text-xs text-red-600/80 dark:text-red-400/80">
                            Tombol Cari Faskes & Kirim Rujukan akan gagal sampai semua data ini lengkap.
                        </p>
                    </div>
                @endif

                {{-- 2-col grid: LEFT = Form (A/B/C), RIGHT = Panduan accordion --}}
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
                    <div class="space-y-5 min-w-0">

                {{-- A. DATA RUJUKAN --}}
                <div class="bg-white border border-gray-200 rounded-xl shadow-sm dark:bg-gray-900 dark:border-gray-700">
                    <div class="px-5 py-3 border-b border-gray-100 dark:border-gray-700">
                        <h3 class="font-semibold text-gray-800 dark:text-gray-100">A. Data Rujukan</h3>
                    </div>
                    <div class="p-5 grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <x-input-label value="Tgl Rencana Kunjungan *" />
                            <x-text-input type="date" wire:model="tglRencanaKunjungan" class="mt-1 w-full" />
                        </div>
                        <div>
                            <x-input-label value="Diagnosa (ICD-10) *" />
                            <x-select-input wire:model="kodeDiagnosa" class="mt-1 w-full">
                                <option value="">— Pilih diagnosa —</option>
                                @foreach ($dataRJ['diagnosis'] ?? [] as $d)
                                    <option value="{{ $d['icdX'] ?? ($d['diagId'] ?? '') }}">
                                        {{ $d['icdX'] ?? '-' }} — {{ $d['diagDesc'] ?? '-' }}
                                    </option>
                                @endforeach
                            </x-select-input>
                            @if (empty($dataRJ['diagnosis'] ?? []))
                                <p class="mt-1 text-[11px] text-amber-600 dark:text-amber-400">
                                    Belum ada diagnosa di EMR. Isi diagnosa dulu sebelum buat rujukan.
                                </p>
                            @endif
                        </div>
                        <div>
                            <x-input-label value="Kode Spesialis *" />
                            <x-text-input wire:model="kodeSpesialis" placeholder="mis. 095" class="mt-1 w-full" />
                            <p class="mt-1 text-[11px] text-gray-500">Ambil dari referensi VClaim (API bridging).</p>
                        </div>
                        <div>
                            <x-input-label value="Jenis Pelayanan" />
                            <x-select-input wire:model="jnsPelayanan" class="mt-1 w-full">
                                <option value="1">1 — Rawat Inap</option>
                                <option value="2">2 — Rawat Jalan</option>
                            </x-select-input>
                        </div>
                        <div>
                            <x-input-label value="Tipe Rujukan" />
                            <x-select-input wire:model="tipeRujukan" class="mt-1 w-full">
                                <option value="0">0 — Penuh</option>
                                <option value="1">1 — Partial</option>
                                <option value="2">2 — Rujuk Balik</option>
                            </x-select-input>
                        </div>
                        <div>
                            <x-input-label value="Poli Rujukan (kode BPJS 3-digit)" />
                            <x-text-input wire:model="poliRujukan" placeholder="mis. 005"
                                maxlength="10" class="mt-1 w-full font-mono" />
                            <p class="mt-1 text-[11px] text-gray-500">
                                Kode poli BPJS standar nasional (3 digit) di faskes tujuan. Kalau kosong, fallback pakai Kode Spesialis.
                            </p>
                        </div>

                        <div>
                            <x-input-label value="Kode Sarana (opsional)" />
                            <x-text-input wire:model="kodeSarana" placeholder="kode sarana khusus"
                                class="mt-1 w-full font-mono" />
                            <p class="mt-1 text-[11px] text-gray-500">
                                Sarana khusus yang dibutuhkan di faskes tujuan (mis. alat CT scan,
                                MRI). Kosongkan kalau tidak perlu.
                            </p>
                        </div>

                        {{-- Wilayah auto-fill setelah GetKriteriaRujukan — ditampilkan di Section C --}}

                        <div class="md:col-span-2">
                            <x-input-label value="Catatan" />
                            <x-textarea wire:model="catatan" rows="2" class="mt-1 w-full"></x-textarea>
                        </div>
                        <div>
                            <x-input-label value="Patient Instruction" />
                            <x-textarea wire:model="patientInstruction" rows="2" class="mt-1 w-full"></x-textarea>
                        </div>
                        <div>
                            <x-input-label value="Keterangan Rujukan" />
                            <x-textarea wire:model="keteranganRujukan" rows="2" class="mt-1 w-full"></x-textarea>
                        </div>
                    </div>
                </div>

                {{-- B. SATU SEHAT (auto) --}}
                <div class="bg-white border border-gray-200 rounded-xl shadow-sm dark:bg-gray-900 dark:border-gray-700">
                    <div class="px-5 py-3 border-b border-gray-100 dark:border-gray-700">
                        <h3 class="font-semibold text-gray-800 dark:text-gray-100">B. Data Satu Sehat (auto dari
                            master)</h3>
                    </div>
                    <div class="p-5 grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <x-input-label value="Kode Faskes Satu Sehat (RS asal)" />
                            <x-text-input :value="$kodeFaskesSatuSehat" readonly
                                class="mt-1 w-full font-mono bg-gray-50 dark:bg-gray-800" />
                            <p class="mt-1 text-[11px] text-gray-500">Dari env SATUSEHAT_ORGANIZATION_ID.</p>
                        </div>
                        <div>
                            <x-input-label value="ID Pasien Satu Sehat" />
                            <x-text-input :value="$idPasienSatuSehat" readonly
                                class="mt-1 w-full font-mono bg-gray-50 dark:bg-gray-800"
                                placeholder="— patient_uuid kosong —" />
                            <p class="mt-1 text-[11px] text-gray-500">Dari rsmst_pasiens.patient_uuid.</p>
                        </div>
                        <div>
                            <x-input-label value="Kd Dokter Satu Sehat" />
                            <x-text-input :value="$kdDokterSatuSehat" readonly
                                class="mt-1 w-full font-mono bg-gray-50 dark:bg-gray-800"
                                placeholder="— dr_uuid kosong —" />
                            <p class="mt-1 text-[11px] text-gray-500">Dari rsmst_doctors.dr_uuid.</p>
                        </div>
                        <div>
                            <x-input-label value="Encounter Reference" />
                            <x-text-input :value="$encounterReference" readonly
                                class="mt-1 w-full font-mono bg-gray-50 dark:bg-gray-800"
                                placeholder="— encounterId kosong —" />
                            <p class="mt-1 text-[11px] text-gray-500">Dari dataRJ.satuSehat.encounterId.</p>
                        </div>
                    </div>
                </div>

                {{-- C. KRITERIA RUJUKAN --}}
                <div class="bg-white border border-gray-200 rounded-xl shadow-sm dark:bg-gray-900 dark:border-gray-700">
                    <div class="px-5 py-3 border-b border-gray-100 dark:border-gray-700 flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <h3 class="font-semibold text-gray-800 dark:text-gray-100">C. Kriteria Rujukan</h3>
                            <p class="text-xs text-gray-500 mt-1">
                                @if ($kriteriaSource === 'server')
                                    <span class="text-emerald-600 dark:text-emerald-400 font-semibold">✓ Dari Server</span>
                                    — kriteria &amp; jejaring wilayah sudah dimuat dari Satu Sehat Rujukan.
                                    Klik "Muat Ulang" kalau diagnosa diubah.
                                @else
                                    <span class="text-amber-600 dark:text-amber-400 font-semibold">⚠ Belum dimuat</span>
                                    — klik tombol <strong>Ambil Kriteria</strong> untuk fetch dari server sesuai
                                    diagnosa. Sementara tampil 3 kriteria default sebagai fallback.
                                @endif
                            </p>
                        </div>
                        <x-primary-button type="button" wire:click="muatKriteria" wire:loading.attr="disabled"
                            wire:target="muatKriteria"
                            class="shrink-0 {{ $kriteriaSource === 'server' ? '!bg-gray-500 hover:!bg-gray-600' : '!bg-indigo-600 hover:!bg-indigo-700' }}">
                            <span wire:loading.remove wire:target="muatKriteria">
                                {{ $kriteriaSource === 'server' ? '🔄 Muat Ulang' : '⬇ Ambil Kriteria' }}
                            </span>
                            <span wire:loading wire:target="muatKriteria"><x-loading /> Memuat…</span>
                        </x-primary-button>
                    </div>
                    <div class="p-5 space-y-3">
                        @foreach ($kriteriaItems as $idx => $item)
                            <div class="flex items-start gap-4 p-3 rounded-lg bg-gray-50 dark:bg-gray-800/50">
                                <div class="flex-1 min-w-0">
                                    <div class="text-xs text-gray-500 font-mono">linkId: {{ $item['linkId'] }}</div>
                                    <div class="font-semibold text-gray-800 dark:text-gray-100">{{ $item['text'] }}
                                    </div>
                                </div>
                                <div class="shrink-0 w-64">
                                    @if ($item['type'] === 'boolean')
                                        <x-toggle wire:model.live="kriteriaItems.{{ $idx }}.answer"
                                            :trueValue="true" :falseValue="false" label="Ya" />
                                    @else
                                        <x-text-input wire:model="kriteriaItems.{{ $idx }}.answer"
                                            placeholder="kode ICD-9, mis. 01.24" class="w-full" />
                                    @endif
                                </div>
                            </div>
                        @endforeach

                        {{-- Jejaring Wilayah — datang dari response GetKriteriaRujukan (auto-fill), user bisa override --}}
                        <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                            <h4 class="text-sm font-semibold text-gray-800 dark:text-gray-100 mb-2">Jejaring Wilayah</h4>
                            <p class="text-xs text-gray-500 mb-3">
                                Otomatis dari response Satu Sehat (berdasarkan jejaring faskes kita). Boleh override
                                kalau mau rujuk ke wilayah lain.
                            </p>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                <div>
                                    <x-input-label value="Kode Provinsi *" />
                                    <x-text-input wire:model="kodePropinsi" placeholder="mis. 31"
                                        class="mt-1 w-full font-mono" />
                                </div>
                                <div>
                                    <x-input-label value="Nama Provinsi *" />
                                    <x-text-input wire:model="namaPropinsi" placeholder="mis. DKI Jakarta"
                                        class="mt-1 w-full" />
                                </div>
                                <div>
                                    <x-input-label value="Kode Kabupaten (opsional)" />
                                    <x-text-input wire:model="kodeKabupaten" class="mt-1 w-full font-mono" />
                                </div>
                                <div>
                                    <x-input-label value="Nama Kabupaten (opsional)" />
                                    <x-text-input wire:model="namaKabupaten" class="mt-1 w-full" />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                    </div> {{-- end LEFT col --}}

                    {{-- RIGHT col: Panduan Cara Pakai (accordion) --}}
                    <div class="lg:sticky lg:top-0 lg:self-start">
                        <div class="bg-white border border-indigo-200 shadow-sm rounded-xl dark:bg-gray-900 dark:border-indigo-700/40">
                            <div class="px-5 py-3 border-b border-indigo-100 dark:border-indigo-900/40">
                                <div class="flex items-center gap-3">
                                    <div class="flex items-center justify-center w-8 h-8 rounded-full bg-indigo-100 dark:bg-indigo-900/30">
                                        <svg class="w-4 h-4 text-indigo-600 dark:text-indigo-400" fill="none"
                                            stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                    </div>
                                    <div>
                                        <div class="font-semibold text-gray-800 dark:text-gray-100">Cara Pakai — Alur Rujukan Kompetensi</div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">Sisrute = orkestrator gabungan BPJS + Kemenkes + Satu Sehat.</div>
                                    </div>
                                </div>
                            </div>

                            <div x-data="{ activeSec: {{ $rujukanResult ? 'null' : "'A'" }} }" class="px-5 py-4">
                                @foreach ($guide as $g)
                                    <div x-data="{ sec: '{{ $g['key'] }}' }">
                                        <button type="button"
                                            x-on:click="activeSec = (activeSec === sec) ? null : sec"
                                            class="flex items-center w-full gap-3 py-2 mt-1 text-left group/sec">
                                            <h4 class="text-xs font-bold tracking-wider uppercase whitespace-nowrap transition-colors text-gray-400 dark:text-gray-500 group-hover/sec:text-gray-600 dark:group-hover/sec:text-gray-300"
                                                x-bind:class="activeSec === sec ? 'text-indigo-600 dark:text-indigo-400' : ''">
                                                {{ $g['title'] }}</h4>
                                            <div class="flex-1 h-px bg-gray-200 dark:bg-gray-700"></div>
                                            <svg class="w-3.5 h-3.5 text-gray-400 shrink-0 transition-transform duration-200"
                                                x-bind:class="activeSec === sec ? 'rotate-0' : '-rotate-90'"
                                                fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M19 9l-7 7-7-7" />
                                            </svg>
                                        </button>
                                        <div x-show="activeSec === sec"
                                            x-transition:enter="transition ease-out duration-200"
                                            x-transition:enter-start="opacity-0 -translate-y-2"
                                            x-transition:enter-end="opacity-100 translate-y-0"
                                            x-transition:leave="transition ease-in duration-150"
                                            x-transition:leave-start="opacity-100 translate-y-0"
                                            x-transition:leave-end="opacity-0 -translate-y-2"
                                            class="pb-3 space-y-2" style="display: none;">
                                            @foreach ($g['items'] as $item)
                                                <div class="flex items-start gap-3 p-3 border border-gray-100 rounded-lg bg-gray-50 dark:bg-gray-800/50 dark:border-gray-700">
                                                    <div class="flex items-center justify-center min-w-[28px] h-7 px-1.5 rounded-full bg-indigo-100 text-indigo-700 text-xs font-bold shrink-0 dark:bg-indigo-900/30 dark:text-indigo-300">
                                                        {{ $item['n'] }}
                                                    </div>
                                                    <div class="text-sm text-gray-700 dark:text-gray-300 min-w-0">
                                                        <div class="font-semibold text-gray-800 dark:text-gray-100">{{ $item['head'] }}</div>
                                                        <div class="text-xs text-gray-500 dark:text-gray-400">{{ $item['body'] }}</div>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>

                </div> {{-- end 2-col grid --}}

                {{-- D. AKSI + HASIL FASKES (full-width) --}}
                <div class="bg-white border border-gray-200 rounded-xl shadow-sm dark:bg-gray-900 dark:border-gray-700">
                    <div class="px-5 py-3 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between gap-3">
                        <h3 class="font-semibold text-gray-800 dark:text-gray-100">D. Cari Faskes Tujuan</h3>
                        <x-primary-button type="button" wire:click="cariFaskes" wire:loading.attr="disabled"
                            wire:target="cariFaskes">
                            <span wire:loading.remove wire:target="cariFaskes">🔍 Cari Faskes</span>
                            <span wire:loading wire:target="cariFaskes"><x-loading /> Mencari…</span>
                        </x-primary-button>
                    </div>

                    <div class="p-5">
                        @if (empty($faskesList))
                            <p class="text-sm text-gray-500 italic">Belum ada data. Klik Cari Faskes.</p>
                        @else
                            <div class="overflow-x-auto">
                                <table class="min-w-full text-sm">
                                    <thead class="bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300">
                                        <tr>
                                            <th class="px-3 py-2 text-left">Nama PPK</th>
                                            <th class="px-3 py-2 text-left">Kode PPK</th>
                                            <th class="px-3 py-2 text-left">Kelas</th>
                                            <th class="px-3 py-2 text-left">Kota</th>
                                            <th class="px-3 py-2 text-left">Jadwal Praktek</th>
                                            <th class="px-3 py-2 text-right">Jarak (m)</th>
                                            <th class="px-3 py-2 text-right">Kap %</th>
                                            <th class="px-3 py-2 text-center">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                        @foreach ($faskesList as $idx => $fk)
                                            @php $isSelected = $faskesTerpilih && ($faskesTerpilih['kdppk'] ?? '') === ($fk['kdppk'] ?? ''); @endphp
                                            <tr class="{{ $isSelected ? 'bg-emerald-50 dark:bg-emerald-900/20' : '' }}">
                                                <td class="px-3 py-2 font-semibold">{{ $fk['nmppk'] ?? '-' }}</td>
                                                <td class="px-3 py-2 font-mono text-xs">{{ $fk['kdppk'] ?? '-' }}</td>
                                                <td class="px-3 py-2">{{ $fk['kelas'] ?? '-' }}</td>
                                                <td class="px-3 py-2">{{ $fk['nmkc'] ?? '-' }}</td>
                                                <td class="px-3 py-2 text-xs max-w-[240px] text-gray-600 dark:text-gray-400">
                                                    {{ $fk['jadwal'] ?? '-' }}
                                                </td>
                                                <td class="px-3 py-2 text-right font-mono">
                                                    {{ number_format($fk['distance'] ?? 0, 0) }}</td>
                                                <td class="px-3 py-2 text-right">{{ $fk['persentase'] ?? 0 }}%</td>
                                                <td class="px-3 py-2 text-center">
                                                    <x-secondary-button type="button"
                                                        wire:click="pilihFaskes({{ $idx }})"
                                                        class="px-2 py-1 text-xs">
                                                        {{ $isSelected ? '✓ Dipilih' : 'Pilih' }}
                                                    </x-secondary-button>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>

                            @if ($faskesTerpilih)
                                <div
                                    class="mt-4 p-4 rounded-lg border-2 border-emerald-300 dark:border-emerald-700 bg-emerald-50/60 dark:bg-emerald-900/10">
                                    <div class="flex items-center justify-between gap-3">
                                        <div class="text-sm">
                                            <div class="font-semibold text-emerald-700 dark:text-emerald-300">Faskes
                                                tujuan dipilih:</div>
                                            <div>{{ $faskesTerpilih['nmppk'] ?? '-' }}
                                                ({{ $faskesTerpilih['kdppk'] ?? '-' }})</div>
                                            <div class="text-xs text-gray-500 mt-0.5">
                                                {{ $faskesTerpilih['alamatPpk'] ?? '-' }} ·
                                                {{ $faskesTerpilih['telpPpk'] ?? '-' }}</div>
                                        </div>
                                        <x-primary-button type="button" wire:click="kirimRujukan"
                                            wire:loading.attr="disabled" wire:target="kirimRujukan"
                                            class="!bg-emerald-600 hover:!bg-emerald-700">
                                            <span wire:loading.remove wire:target="kirimRujukan">📤 Kirim
                                                Rujukan</span>
                                            <span wire:loading wire:target="kirimRujukan"><x-loading />
                                                Mengirim…</span>
                                        </x-primary-button>
                                    </div>
                                </div>
                            @endif
                        @endif
                    </div>
                </div>

                {{-- E. HASIL RUJUKAN --}}
                @if ($rujukanResult)
                    <div
                        class="bg-white border-2 border-brand/40 dark:border-brand-lime/40 rounded-xl shadow-sm dark:bg-gray-900">
                        <div class="px-5 py-3 border-b border-brand/20 dark:border-brand-lime/20 flex items-center gap-2">
                            <svg class="w-5 h-5 text-brand dark:text-brand-lime" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                            </svg>
                            <h3 class="font-semibold text-brand dark:text-brand-lime">E. Hasil Rujukan</h3>
                        </div>
                        <div class="p-5 space-y-4">
                            {{-- Nomor-nomor rujukan --}}
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                                <div class="p-3 rounded-lg bg-brand/10 dark:bg-brand-lime/10">
                                    <div class="text-[10px] uppercase tracking-wider text-brand dark:text-brand-lime font-semibold">
                                        No Rujukan BPJS</div>
                                    <div class="mt-1 font-mono font-bold text-gray-900 dark:text-gray-100 break-all">
                                        {{ $rujukanResult['noRujukan'] ?? '-' }}
                                    </div>
                                </div>
                                <div class="p-3 rounded-lg bg-indigo-50 dark:bg-indigo-900/20">
                                    <div class="text-[10px] uppercase tracking-wider text-indigo-600 dark:text-indigo-400 font-semibold">
                                        No Rujukan Satu Sehat</div>
                                    <div class="mt-1 font-mono font-bold text-gray-900 dark:text-gray-100 break-all">
                                        {{ $rujukanResult['noRujukanSatuSehat'] ?? '-' }}
                                    </div>
                                </div>
                                <div class="p-3 rounded-lg bg-purple-50 dark:bg-purple-900/20">
                                    <div class="text-[10px] uppercase tracking-wider text-purple-600 dark:text-purple-400 font-semibold">
                                        Service Request ID (FHIR)</div>
                                    <div class="mt-1 font-mono text-xs text-gray-900 dark:text-gray-100 break-all">
                                        {{ $rujukanResult['serviceRequestId'] ?? '-' }}
                                    </div>
                                </div>
                            </div>

                            {{-- Detail --}}
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                                @if (!empty($rujukanResult['asalRujukan']) || !empty($rujukanResult['AsalRujukan']))
                                    @php $asal = $rujukanResult['asalRujukan'] ?? ($rujukanResult['AsalRujukan'] ?? []); @endphp
                                    <div>
                                        <div class="text-xs text-gray-500 mb-1">Asal Rujukan (RS Kita)</div>
                                        <div class="text-gray-800 dark:text-gray-200">{{ $asal['nama'] ?? '-' }}</div>
                                        <div class="text-xs text-gray-500 font-mono">{{ $asal['kode'] ?? '-' }}</div>
                                    </div>
                                @endif
                                @if (!empty($rujukanResult['tujuanRujukan']))
                                    <div>
                                        <div class="text-xs text-gray-500 mb-1">Tujuan Rujukan</div>
                                        <div class="text-gray-800 dark:text-gray-200 font-semibold">
                                            {{ $rujukanResult['tujuanRujukan']['nama'] ?? '-' }}</div>
                                        <div class="text-xs text-gray-500 font-mono">
                                            {{ $rujukanResult['tujuanRujukan']['kode'] ?? '-' }}</div>
                                    </div>
                                @endif
                                @if (!empty($rujukanResult['poliTujuan']))
                                    <div>
                                        <div class="text-xs text-gray-500 mb-1">Poli Tujuan</div>
                                        <div class="text-gray-800 dark:text-gray-200">
                                            {{ $rujukanResult['poliTujuan']['nama'] ?? '-' }}</div>
                                        <div class="text-xs text-gray-500 font-mono">
                                            {{ $rujukanResult['poliTujuan']['kode'] ?? '-' }}</div>
                                    </div>
                                @endif
                                @if (!empty($rujukanResult['diagnosa']))
                                    <div>
                                        <div class="text-xs text-gray-500 mb-1">Diagnosa</div>
                                        <div class="text-gray-800 dark:text-gray-200">
                                            {{ $rujukanResult['diagnosa']['nama'] ?? '-' }}</div>
                                        <div class="text-xs text-gray-500 font-mono">
                                            {{ $rujukanResult['diagnosa']['kode'] ?? '-' }}</div>
                                    </div>
                                @endif
                                @if (!empty($rujukanResult['peserta']))
                                    <div class="md:col-span-2 p-3 rounded-lg bg-gray-50 dark:bg-gray-800/50">
                                        <div class="text-xs text-gray-500 mb-1">Data Peserta (BPJS)</div>
                                        <div class="grid grid-cols-2 md:grid-cols-4 gap-2 text-xs">
                                            <div>
                                                <div class="text-gray-500">Nama</div>
                                                <div class="font-semibold">
                                                    {{ $rujukanResult['peserta']['nama'] ?? '-' }}</div>
                                            </div>
                                            <div>
                                                <div class="text-gray-500">No Kartu</div>
                                                <div class="font-mono">
                                                    {{ $rujukanResult['peserta']['noKartu'] ?? '-' }}</div>
                                            </div>
                                            <div>
                                                <div class="text-gray-500">Jenis Peserta</div>
                                                <div>{{ $rujukanResult['peserta']['jnsPeserta'] ?? '-' }}</div>
                                            </div>
                                            <div>
                                                <div class="text-gray-500">Tgl Lahir</div>
                                                <div>{{ $rujukanResult['peserta']['tglLahir'] ?? '-' }}</div>
                                            </div>
                                        </div>
                                    </div>
                                @endif
                                @if (!empty($rujukanResult['tglRujukan']))
                                    <div>
                                        <div class="text-xs text-gray-500 mb-1">Tgl Rujukan</div>
                                        <div class="text-gray-800 dark:text-gray-200">
                                            {{ $rujukanResult['tglRujukan'] }}</div>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                @endif

            </div>
        </div>
    </x-modal>
</div>
