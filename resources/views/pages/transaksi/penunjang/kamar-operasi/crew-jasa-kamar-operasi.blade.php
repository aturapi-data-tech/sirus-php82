<?php
// resources/views/pages/transaksi/penunjang/kamar-operasi/crew-jasa-kamar-operasi.blade.php

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Http\Traits\Txn\Penunjang\KamarOperasiTrait;
use App\Support\KamarOperasiTarif;

/**
 * Crew & Jasa Operasi — 6 posisi crew dipasangkan dengan pos jasanya masing-masing,
 * plus pos tarif yang tidak melekat pada orang, plus jasa on call.
 *
 * Dua kelompok visual: bingkai hijau = ditagihkan ke pasien, bingkai putus-putus =
 * tidak ditagihkan.
 *
 * `oprdoc_fee` & `equipment_fee` TIDAK bisa diketik — keduanya turunan dari tabel
 * Tindakan / Bahan-Alat dan ditimpa oleh KamarOperasiTarif::hitungUlang().
 */
new class extends Component {
    use KamarOperasiTrait;

    public string $okReg = '';
    public bool $isFormLocked = true;
    public int $riHdrNo = 0;

    /** Nilai pos; key = kolom rstxn_oks. Null = belum pernah diisi. */
    public array $tarif = [];
    public array $oncall = [];

    /** Baris siap-tampil — supaya template bebas logika & FQCN. */
    public array $crewRows = [];
    public array $posLainnyaRows = [];
    public array $oncallRows = [];

    /** Nilai crew terpilih; dipakai sebagai initial value LOV. */
    public ?string $drId = null;
    public ?string $drIdOk = null;
    public ?string $empIdAsistopr = null;
    public ?string $empIdAsistanes = null;
    public ?string $empIdInstrument = null;
    public ?string $empIdChangeanesdoc = null;

    /** Nilai kolom fee terakhir dari DB — pembanding "berubah/tidak". */
    private array $nilaiDb = [];

    /** Target LOV per kolom crew. */
    private const TARGET_LOV = [
        'dr_id' => 'kamar-operasi-operator',
        'dr_id_ok' => 'kamar-operasi-anestesi',
        'emp_id_changeanesdoc' => 'kamar-operasi-changeanesdoc',
        'emp_id_asistopr' => 'kamar-operasi-asistopr',
        'emp_id_asistanes' => 'kamar-operasi-asistanes',
        'emp_id_instrument' => 'kamar-operasi-instrument',
    ];

    public function mount(string $okReg = ''): void
    {
        $this->okReg = $okReg;
        $this->findData();
    }

    #[On('kamar-operasi.updated')]
    public function findData(): void
    {
        if ($this->okReg === '') {
            return;
        }

        $kolomFee = array_keys(KamarOperasiTarif::POS);
        $kolomOncall = array_keys(KamarOperasiTarif::POS_ONCALL);

        $row = DB::table('rstxn_oks as o')
            ->leftJoin('rsmst_doctors as dopr', 'dopr.dr_id', '=', 'o.dr_id')
            ->leftJoin('rsmst_doctors as danes', 'danes.dr_id', '=', 'o.dr_id_ok')
            ->leftJoin('rsmst_mstdiags as dg', 'dg.diag_id', '=', 'o.diag_id')
            ->select('o.ok_reg', 'o.rihdr_no', 'o.ok_status', 'o.dr_id', 'o.dr_id_ok', 'o.emp_id_asistopr', 'o.emp_id_asistanes', 'o.emp_id_instrument', 'o.emp_id_changeanesdoc', 'dg.diag_desc', 'dopr.dr_name as operator_name', 'danes.dr_name as anestesi_name', ...$kolomFee, ...$kolomOncall)
            ->where('o.ok_reg', $this->okReg)
            ->first();

        if (!$row) {
            return;
        }

        $data = (array) $row;
        $this->nilaiDb = $data;
        $this->riHdrNo = (int) $data['rihdr_no'];
        $this->isFormLocked = ($data['ok_status'] ?? 'A') !== 'A';

        // NULL dipertahankan — beda makna dengan 0 (belum pernah diisi = boleh
        // diisi otomatis oleh hitung ulang).
        $this->tarif = [];
        foreach ($kolomFee as $kolom) {
            $this->tarif[$kolom] = $data[$kolom] === null ? null : (int) $data[$kolom];
        }

        $this->oncall = [];
        foreach ($kolomOncall as $kolom) {
            $this->oncall[$kolom] = $data[$kolom] === null ? null : (int) $data[$kolom];
        }

        $this->drId = $data['dr_id'] ?? null;
        $this->drIdOk = $data['dr_id_ok'] ?? null;
        $this->empIdAsistopr = $data['emp_id_asistopr'] ?? null;
        $this->empIdAsistanes = $data['emp_id_asistanes'] ?? null;
        $this->empIdInstrument = $data['emp_id_instrument'] ?? null;
        $this->empIdChangeanesdoc = $data['emp_id_changeanesdoc'] ?? null;

        $this->susunBarisTampilan($data);
    }

    /** Rakit baris crew + pos lainnya supaya template bebas logika & FQCN. */
    private function susunBarisTampilan(array $data): void
    {
        // Nama karyawan diambil sekali untuk semua posisi — bukan satu query per baris.
        $empIds = array_values(array_filter([$this->empIdChangeanesdoc, $this->empIdAsistopr, $this->empIdAsistanes, $this->empIdInstrument]));
        $namaKaryawan = $empIds === [] ? [] : DB::table('hrmst_employees')->whereIn('emp_id', $empIds)->pluck('name', 'emp_id')->all();

        $idPerKolom = [
            'dr_id' => $this->drId,
            'dr_id_ok' => $this->drIdOk,
            'emp_id_changeanesdoc' => $this->empIdChangeanesdoc,
            'emp_id_asistopr' => $this->empIdAsistopr,
            'emp_id_asistanes' => $this->empIdAsistanes,
            'emp_id_instrument' => $this->empIdInstrument,
        ];

        $this->crewRows = [];
        foreach (KamarOperasiTarif::CREW as $kolomCrew => $crew) {
            $kolomFee = $crew['fee'];
            $idCrew = $idPerKolom[$kolomCrew] ?? null;

            $this->crewRows[] = [
                'kolomCrew' => $kolomCrew,
                'label' => $crew['label'],
                'jenis' => $crew['jenis'],
                'target' => self::TARGET_LOV[$kolomCrew] ?? '',
                'idCrew' => $idCrew,
                'namaCrew' => match ($kolomCrew) {
                    'dr_id' => $data['operator_name'] ?? null,
                    'dr_id_ok' => $data['anestesi_name'] ?? null,
                    default => $idCrew ? $namaKaryawan[$idCrew] ?? null : null,
                },
                'kolomFee' => $kolomFee,
                'labelFee' => KamarOperasiTarif::LABEL[$kolomFee] ?? $kolomFee,
                'persen' => KamarOperasiTarif::PERSEN_DARI_OPERATOR[$kolomFee] ?? null,
                'isTurunan' => in_array($kolomFee, KamarOperasiTarif::POS_TURUNAN_DETAIL, true),
                'isGajiDokter' => array_key_exists($kolomFee, KamarOperasiTarif::POS_GAJI_DOKTER),
                'kolomOncall' => $crew['oncall'],
            ];
        }

        $this->posLainnyaRows = [];
        foreach (KamarOperasiTarif::posTanpaCrew() as $kolom) {
            $this->posLainnyaRows[] = [
                'kolom' => $kolom,
                'label' => KamarOperasiTarif::LABEL[$kolom] ?? $kolom,
                'keterangan' => KamarOperasiTarif::POS[$kolom],
                'isTurunan' => in_array($kolom, KamarOperasiTarif::POS_TURUNAN_DETAIL, true),
            ];
        }

        $this->oncallRows = [];
        foreach (KamarOperasiTarif::POS_ONCALL as $kolom => $label) {
            $this->oncallRows[] = ['kolom' => $kolom, 'label' => $label];
        }

        $this->diagDesc = $data['diag_desc'] ?? null;
    }

    public ?string $diagDesc = null;

    private function bolehUbah(): bool
    {
        if (!$this->isAllowedRoleOk()) {
            $this->dispatch('toast', type: 'error', message: 'Anda tidak memiliki akses.');
            return false;
        }

        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Transaksi sudah selesai/dibatalkan — data tidak bisa diubah.');
            $this->findData();
            return false;
        }

        return true;
    }

    /* =======================
     | POS TARIF — hook wire:model dari x-text-input-number
     * ======================= */
    public function updatedTarif($value, $key): void
    {
        // oprdoc_fee & equipment_fee sengaja TIDAK boleh diketik: turunan tabel detail.
        $kolomBoleh = array_values(array_diff(array_keys(KamarOperasiTarif::POS), KamarOperasiTarif::POS_TURUNAN_DETAIL));

        $this->simpanKolomFee((string) $key, $value === null ? null : (string) $value, $kolomBoleh, KamarOperasiTarif::LABEL);
    }

    public function updatedOncall($value, $key): void
    {
        $this->simpanKolomFee((string) $key, $value === null ? null : (string) $value, array_keys(KamarOperasiTarif::POS_ONCALL), KamarOperasiTarif::POS_ONCALL);
    }

    /**
     * @param  list<string>          $kolomBoleh
     * @param  array<string,string>  $labelPeta
     */
    private function simpanKolomFee(string $kolom, ?string $nilai, array $kolomBoleh, array $labelPeta): void
    {
        if (!in_array($kolom, $kolomBoleh, true)) {
            return;
        }

        if (!$this->bolehUbah()) {
            return;
        }

        $bersih = str_replace(['.', ',', ' '], '', trim((string) $nilai));

        $validator = Validator::make(
            ['tarif' => $bersih === '' ? null : $bersih],
            ['tarif' => 'bail|nullable|integer|min:0|max:999999999'],
            ['tarif.integer' => 'Tarif harus berupa angka bulat.', 'tarif.min' => 'Tarif tidak boleh negatif.', 'tarif.max' => 'Tarif melebihi batas wajar.'],
        );

        if ($validator->fails()) {
            $this->dispatch('toast', type: 'error', message: $validator->errors()->first('tarif'));
            $this->findData();
            return;
        }

        $nilaiBaru = $bersih === '' ? null : (int) $bersih;

        // Nilai lama dari snapshot DB, BUKAN dari $this->tarif — array itu ter-bind
        // wire:model sehingga sudah berisi nilai BARU saat hook dipanggil.
        $nilaiLama = $this->nilaiDb[$kolom] ?? null;
        $nilaiLama = $nilaiLama === null ? null : (int) $nilaiLama;

        if ($nilaiLama === $nilaiBaru) {
            return;
        }

        $riHdrNo = $this->riHdrNo;
        $label = $labelPeta[$kolom] ?? $kolom;
        // NULL punya makna sendiri (belum diisi → boleh diisi otomatis), jadi ditulis
        // eksplisit di log supaya tidak tertukar dengan nol rupiah.
        $teksLama = $nilaiLama === null ? '(belum diisi)' : 'Rp ' . number_format($nilaiLama);
        $teksBaru = $nilaiBaru === null ? '(belum diisi)' : 'Rp ' . number_format($nilaiBaru);

        $berhasil = $this->jalankanDenganRetryOk(function () use ($kolom, $nilaiBaru, $riHdrNo, $label, $teksLama, $teksBaru) {
            $this->kunciBarisOk($this->okReg);

            DB::table('rstxn_oks')->where('ok_reg', $this->okReg)->update([$kolom => $nilaiBaru]);

            $this->catatLogOk($riHdrNo, "Ubah tarif OK No.{$this->okReg} — {$label}: {$teksLama} → {$teksBaru}");
        }, 'Gagal menyimpan tarif');

        $this->findData();

        if ($berhasil) {
            $this->dispatch('kamar-operasi.updated');
            $this->dispatch('toast', type: 'success', message: "{$label} disimpan.");
        }
    }

    /* =======================
     | CREW — listener LOV
     * ======================= */
    #[On('lov.selected.kamar-operasi-operator')]
    public function pilihOperator($target = null, $payload = null): void
    {
        $this->simpanCrew('dr_id', $payload['dr_id'] ?? null, 'Dr. Operator');
    }

    #[On('lov.selected.kamar-operasi-anestesi')]
    public function pilihAnestesi($target = null, $payload = null): void
    {
        $this->simpanCrew('dr_id_ok', $payload['dr_id'] ?? null, 'Dr. Anestesi');
    }

    #[On('lov.selected.kamar-operasi-changeanesdoc')]
    public function pilihChangeanesdoc($target = null, $payload = null): void
    {
        $this->simpanCrew('emp_id_changeanesdoc', $payload['emp_id'] ?? null, 'Pengganti Anestesi');
    }

    #[On('lov.selected.kamar-operasi-asistopr')]
    public function pilihAsistopr($target = null, $payload = null): void
    {
        $this->simpanCrew('emp_id_asistopr', $payload['emp_id'] ?? null, 'Asisten Operator');
    }

    #[On('lov.selected.kamar-operasi-asistanes')]
    public function pilihAsistanes($target = null, $payload = null): void
    {
        $this->simpanCrew('emp_id_asistanes', $payload['emp_id'] ?? null, 'Asisten Anestesi');
    }

    #[On('lov.selected.kamar-operasi-instrument')]
    public function pilihInstrument($target = null, $payload = null): void
    {
        $this->simpanCrew('emp_id_instrument', $payload['emp_id'] ?? null, 'Instrument');
    }

    /**
     * dr_id & dr_id_ok NOT NULL di rstxn_oks dan menentukan siapa yang menerima
     * pendapatan di Laporan Pendapatan Jasa Dokter, jadi tidak boleh dikosongkan.
     */
    private function simpanCrew(string $kolom, ?string $nilai, string $label): void
    {
        if (!array_key_exists($kolom, KamarOperasiTarif::CREW)) {
            return;
        }

        if (!$this->bolehUbah()) {
            return;
        }

        // Rantai Enter: kursor turun ke kolom Jasa milik crew ini. Dikirim lebih dulu
        // supaya tetap jalan walau nilainya ternyata tidak berubah.
        $kolomFee = KamarOperasiTarif::CREW[$kolom]['fee'];
        if (!in_array($kolomFee, KamarOperasiTarif::POS_TURUNAN_DETAIL, true)) {
            $this->dispatch('kamar-operasi-fokus', ke: 'ok-jasa-' . $kolomFee);
        }

        $nilaiBaru = $nilai === null || trim((string) $nilai) === '' ? null : trim((string) $nilai);
        $wajibIsi = in_array($kolom, ['dr_id', 'dr_id_ok'], true);

        if ($wajibIsi && $nilaiBaru === null) {
            $this->dispatch('toast', type: 'error', message: "{$label} wajib diisi — pilih dokter penggantinya, jangan dikosongkan.");
            $this->findData();
            return;
        }

        $nilaiLama = $this->nilaiDb[$kolom] ?? null;
        $nilaiLama = $nilaiLama === null || $nilaiLama === '' ? null : (string) $nilaiLama;

        if ($nilaiLama === $nilaiBaru) {
            return;
        }

        $riHdrNo = $this->riHdrNo;
        $namaLama = $this->namaCrew($kolom, $nilaiLama);
        $namaBaru = $this->namaCrew($kolom, $nilaiBaru);
        $catatanGaji = $wajibIsi ? ' (memindahkan pendapatan dokter di Laporan Pendapatan Jasa Dokter)' : '';

        $berhasil = $this->jalankanDenganRetryOk(function () use ($kolom, $nilaiBaru, $riHdrNo, $label, $namaLama, $namaBaru, $catatanGaji) {
            $this->kunciBarisOk($this->okReg);

            DB::table('rstxn_oks')->where('ok_reg', $this->okReg)->update([$kolom => $nilaiBaru]);

            $this->catatLogOk($riHdrNo, "Ubah crew OK No.{$this->okReg} — {$label}: {$namaLama} → {$namaBaru}{$catatanGaji}");
        }, 'Gagal menyimpan crew');

        $this->findData();

        if ($berhasil) {
            $this->dispatch('kamar-operasi.updated');
            $this->dispatch('toast', type: 'success', message: "{$label} disimpan.");
        }
    }

    private function namaCrew(string $kolom, ?string $idCrew): string
    {
        if ($idCrew === null) {
            return '(kosong)';
        }

        $nama = in_array($kolom, ['dr_id', 'dr_id_ok'], true) ? DB::table('rsmst_doctors')->where('dr_id', $idCrew)->value('dr_name') : DB::table('hrmst_employees')->where('emp_id', $idCrew)->value('name');

        return ($nama ?: '?') . " ({$idCrew})";
    }
};
?>

<div class="p-4 bg-canvas border border-hairline rounded-2xl dark:border-gray-700 dark:bg-gray-900"
    x-data="{
        /* Rantai Enter antar input tarif dalam kartu ini. Urutannya mengikuti
           urutan DOM, jadi tidak perlu x-ref bernomor yang gampang basi saat
           susunan pos berubah. Selektornya SEMUA input yang bisa diketik —
           kalau disaring inputmode=numeric, kotak pencarian LOV crew terlewati.
           blur() dulu karena x-text-input-number menyinkron lewat $wire.set saat blur. */
        enterBerikutnya(input) {
            input.blur();
            const daftar = [...$el.querySelectorAll('input:not([disabled]):not([type=hidden])')]
                .filter(el => el.offsetParent !== null);
            daftar[daftar.indexOf(input) + 1]?.focus();
        }
    }">

    <div class="flex flex-wrap items-center gap-2 mb-1">
        <h3 class="text-sm font-semibold text-body dark:text-gray-300">Crew &amp; Jasa Operasi</h3>
        @if ($isFormLocked)
            <x-badge variant="danger" class="text-xs whitespace-nowrap shrink-0">Read Only</x-badge>
        @else
            <span class="ml-auto text-xs italic text-muted">Tersimpan saat kursor berpindah</span>
        @endif
    </div>

    {{-- Penjelasan semua penanda ditaruh di satu panel info (gaya biru-info standar,
         default tertutup) — bukan disingkat di badge tiap kartu yang justru bikin rancu. --}}
    <div x-data="{ buka: false }"
        class="mb-3 border rounded-lg border-blue-200 bg-blue-50 dark:bg-blue-900/20 dark:border-blue-800">
        <button type="button" x-on:click="buka = !buka"
            class="flex items-center justify-between w-full px-4 py-2 text-sm font-semibold text-blue-900 dark:text-blue-100">
            <span class="flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                Arti penanda pada tarif
            </span>
            <svg class="w-4 h-4 transition-transform" x-bind:class="buka &amp;&amp; 'rotate-180'"
                fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
        </button>

        <div x-show="buka" x-cloak class="px-4 pb-3 space-y-2 text-sm text-blue-900 dark:text-blue-100">
            <div class="flex items-start gap-2">
                <x-badge variant="warning" class="mt-0.5 shrink-0">Dokter</x-badge>
                <span>
                    Nilainya <span class="font-semibold">ditagihkan ke pasien</span> seperti pos lain,
                    <span class="font-semibold">dan sekaligus</span> tercatat sebagai
                    <span class="font-semibold">pendapatan dokter</span> di Laporan Pendapatan Jasa Dokter.
                    Jadi mengubah angka ini menggeser dua hal: tagihan pasien dan penghasilan dokter
                    yang tercatat. Penandanya menyebut siapa penerimanya, bukan nama posnya
                    (nama pos tetap JD Operator / JD Anestesi).
                </span>
            </div>
            <div class="flex items-start gap-2">
                <span class="mt-0.5 text-xs italic shrink-0 text-blue-700 dark:text-blue-300">otomatis</span>
                <span>Tidak bisa diketik — dijumlah sendiri dari tabel Tindakan Operasi atau Bahan dan Alat.</span>
            </div>
            <div class="flex items-start gap-2">
                <span class="mt-0.5 text-xs italic shrink-0 text-blue-700 dark:text-blue-300">50% / 10%</span>
                <span>
                    Angka usulan, dihitung dari pos <span class="font-semibold">JD Operator</span>.
                    Disegarkan tiap kali tombol <span class="font-semibold">Hitung Tarif OK</span> ditekan.
                    Boleh Anda ubah manual — total tetap mengikuti nilai yang tersimpan, bukan persentasenya.
                </span>
            </div>
            <div class="flex items-start gap-2">
                <span class="mt-0.5 text-xs italic shrink-0 text-blue-700 dark:text-blue-300">On Call</span>
                <span>
                    Tambahan jasa karena petugas dipanggil di luar jadwal.
                    <span class="font-semibold">Tidak ditagihkan ke pasien</span> dan tidak ikut ditransfer
                    ke biaya rawat inap — hanya catatan jasa petugas.
                </span>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 pb-2 mb-2 text-sm border-b gap-x-6 gap-y-1 border-hairline dark:border-gray-700 sm:grid-cols-2 lg:grid-cols-1">
        <div class="flex items-start gap-2">
            <span class="shrink-0 text-muted">Diagnosa Pra-Op:</span>
            @if (!empty($diagDesc))
                <span class="font-medium text-warning-deep dark:text-amber-300">{{ $diagDesc }}</span>
            @else
                <span class="italic text-muted-soft dark:text-gray-500">-</span>
            @endif
        </div>
    </div>

    {{-- KELOMPOK 1 — semua yang masuk tagihan pasien, dibingkai sekali di tingkat
         kelompok (bukan per sel). --}}
    <div class="p-2 border rounded-lg border-brand-green/30 bg-brand-green/5 dark:border-brand-lime/30 dark:bg-brand-lime/5">
        <p class="px-1 mb-2 text-sm font-semibold tracking-wide uppercase text-brand-green dark:text-brand-lime">
            Ditagihkan ke pasien
        </p>

        {{-- 6 crew disusun grid — 2 ke kanan di layar lebar. --}}
        <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
            @foreach ($crewRows as $crew)
                @php
                    $kolomFee = $crew['kolomFee'];
                    $kolomOncall = $crew['kolomOncall'];
                    $isTurunan = $crew['isTurunan'];
                    $isGajiDokter = $crew['isGajiDokter'];
                    $persen = $crew['persen'];
                @endphp

                <div wire:key="crew-{{ $crew['kolomCrew'] }}"
                    class="px-3 py-2 border rounded-xl bg-surface-soft dark:bg-gray-800/40 {{ $isGajiDokter ? 'border-warning/30 dark:border-amber-700' : 'border-hairline dark:border-gray-700' }}">

                    <div class="flex flex-wrap items-center gap-x-2 gap-y-1 mb-1">
                        <span class="text-sm font-semibold text-body dark:text-gray-300">{{ $crew['label'] }}</span>
                        @if ($isGajiDokter)
                            {{-- Label sengaja "Dokter", bukan "jasa dokter": nama posnya sendiri
                                 sudah JD (Jasa Dokter), dua-duanya berdampingan bikin salah tangkap. --}}
                            <x-badge variant="warning"
                                title="Angka ini masuk tagihan pasien DAN tercatat sebagai pendapatan dokter di Laporan Pendapatan Jasa Dokter. Lihat panel 'Arti penanda pada tarif'.">Dokter</x-badge>
                        @endif
                        <span class="ml-auto text-xs text-muted-soft">
                            {{ $crew['labelFee'] }}
                            @if ($isTurunan)
                                <span class="italic" title="Dijumlah dari tabel tindakan">&middot; otomatis</span>
                            @elseif ($persen !== null)
                                <span class="italic"
                                    title="Usulan {{ $persen }}% dari pos JD Operator; disegarkan tiap kali tarif dihitung ulang. Boleh diedit — total tetap mengikuti nilai yang Anda isi.">&middot; {{ $persen }}%</span>
                            @endif
                        </span>
                    </div>

                    {{-- Nama petugas --}}
                    @if ($isFormLocked)
                        <p class="mb-1 text-sm font-medium text-ink dark:text-gray-200">{{ $crew['namaCrew'] ?: '-' }}</p>
                    @elseif ($crew['jenis'] === 'dokter')
                        <div class="mb-1">
                            <livewire:lov.dokter.lov-dokter :target="$crew['target']" label=""
                                :initialDrId="$crew['idCrew']" wire:key="lov-{{ $crew['kolomCrew'] }}-{{ $okReg }}-{{ $crew['idCrew'] }}" />
                        </div>
                    @else
                        <div class="mb-1">
                            <livewire:lov.karyawan-oncall.lov-karyawan-oncall :target="$crew['target']" label=""
                                :initialEmpId="$crew['idCrew']" wire:key="lov-{{ $crew['kolomCrew'] }}-{{ $okReg }}-{{ $crew['idCrew'] }}" />
                        </div>
                    @endif

                    {{-- Jasa; on call dikelompokkan sendiri di bawah. --}}
                    @if ($isFormLocked || $isTurunan)
                        <p class="text-sm font-semibold text-ink dark:text-gray-200 tabular-nums">
                            {{ ($tarif[$kolomFee] ?? null) === null ? '—' : 'Rp ' . number_format($tarif[$kolomFee]) }}
                        </p>
                    @else
                        {{-- Simpan dipicu hook updatedTarif() saat komponen sync di blur. --}}
                        <x-text-input-number id="ok-jasa-{{ $kolomFee }}" wire:model="tarif.{{ $kolomFee }}"
                            placeholder="belum diisi"
                            x-on:keydown.enter.prevent="enterBerikutnya($el)" />
                    @endif
                </div>
            @endforeach
        </div>

        {{-- POS TARIF LAINNYA — fasilitas, bahan, dan jasa kelompok (tidak melekat
             pada satu orang). Masih di dalam kelompok "Ditagihkan ke pasien". --}}
        <div class="pt-2 mt-2 border-t border-brand-green/20 dark:border-brand-lime/20">
            <div class="flex flex-wrap items-baseline px-1 mb-2 gap-x-2">
                <h4 class="text-sm font-semibold text-body dark:text-gray-300">Pos Tarif Lainnya</h4>
                <span class="text-sm text-muted dark:text-gray-400">fasilitas, bahan, dan jasa kelompok</span>
            </div>

            <div class="grid grid-cols-2 gap-1.5 sm:grid-cols-3">
                @foreach ($posLainnyaRows as $pos)
                    @php $kolom = $pos['kolom']; @endphp
                    <div wire:key="pos-tarif-{{ $kolom }}"
                        class="px-2.5 py-1.5 border rounded-xl border-hairline dark:border-gray-700 bg-surface-soft dark:bg-gray-800/40">
                        <div class="flex items-center justify-between gap-1">
                            <p class="text-sm truncate text-muted dark:text-gray-400" title="{{ $pos['keterangan'] }}">{{ $pos['label'] }}</p>
                            @if ($pos['isTurunan'])
                                <span class="text-xs italic shrink-0 text-muted-soft" title="Dijumlah dari tabel bahan dan alat">otomatis</span>
                            @endif
                        </div>

                        @if ($isFormLocked || $pos['isTurunan'])
                            <p class="text-sm font-semibold text-ink dark:text-gray-200 tabular-nums">
                                {{ ($tarif[$kolom] ?? null) === null ? '—' : 'Rp ' . number_format($tarif[$kolom]) }}
                            </p>
                        @else
                            <x-text-input-number wire:model="tarif.{{ $kolom }}" placeholder="belum diisi"
                                x-on:keydown.enter.prevent="enterBerikutnya($el)" />
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- KELOMPOK 2 — jasa petugas yang TIDAK masuk tagihan pasien. --}}
    <div class="p-2 mt-3 border border-dashed rounded-lg border-hairline bg-surface-soft dark:border-gray-600 dark:bg-gray-800/40">
        <div class="flex flex-wrap items-baseline px-1 mb-2 gap-x-2">
            <p class="text-sm font-semibold tracking-wide uppercase text-muted dark:text-gray-400">
                Tidak ditagihkan ke pasien
            </p>
            <span class="text-sm text-muted dark:text-gray-400">
                jasa on call petugas &mdash; tidak ikut ditransfer ke biaya rawat inap
            </span>
            <span class="ml-auto text-sm font-semibold text-ink dark:text-gray-200 tabular-nums">
                Rp {{ number_format(array_sum(array_map(fn($nilai) => (int) $nilai, $oncall))) }}
            </span>
        </div>

        <div class="grid grid-cols-1 gap-1.5 sm:grid-cols-3">
            @foreach ($oncallRows as $baris)
                @php $kolom = $baris['kolom']; @endphp
                <div wire:key="pos-oncall-{{ $kolom }}" class="px-2 py-1">
                    <p class="text-sm truncate text-muted dark:text-gray-400 mb-0.5">{{ $baris['label'] }}</p>
                    @if ($isFormLocked)
                        <p class="text-sm font-semibold text-ink dark:text-gray-200 tabular-nums">
                            {{ ($oncall[$kolom] ?? null) === null ? '—' : 'Rp ' . number_format($oncall[$kolom]) }}
                        </p>
                    @else
                        <x-text-input-number wire:model="oncall.{{ $kolom }}" placeholder="belum diisi"
                            x-on:keydown.enter.prevent="enterBerikutnya($el)" />
                    @endif
                </div>
            @endforeach
        </div>
    </div>
</div>
