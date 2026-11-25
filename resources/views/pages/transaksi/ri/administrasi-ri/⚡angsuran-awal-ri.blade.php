<?php

use Livewire\Component;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Ri\EmrRITrait;
use App\Http\Traits\Concerns\WithRenderVersioningTrait;
use App\Support\AksiRole;

/**
 * Angsuran Awal (titipan) pasien Rawat Inap — uang yang dibayar pasien SELAMA dirawat,
 * sebelum pulang. Port form 6i blok RSTXN_RIPAYMENTDTLS1.
 *
 * Tabel: rstxn_ripaymentdtls (ripay_no, rihdr_no, emp_id, ripay_date, ripay_bayar, shift, acc_id).
 * Pemakai hilir (sudah ada, jangan diubah rumusnya dari sini):
 *   - Kasir Pulang RI: dikurangkan dari total tagihan ("Dibayar (angsuran awal)").
 *   - Jurnal (JurnalCabang "ANGSURAN AWAL"): kas acc_id ← RIANGAWAL pada ripay_date;
 *     dibalik saat pasien pulang ("PENGEMBALIAN ANGSURAN AWAL").
 *   - Piutang Pasien: ikut dihitung sebagai pembayaran.
 * Hanya boleh selama ri_status='I'. Batal Inap sudah menolak RI yang punya angsuran awal.
 */
new class extends Component {
    use EmrRITrait, WithRenderVersioningTrait;

    /** Yang boleh menerima titipan = petugas kasir (sama dengan Proses Pulang) + atasannya. */
    private const ROLE_ENTRI = ['Admin', 'Tu', 'Supervisor Tu'];

    /** Hapus = eskalasi ke atasan (sama dengan Batal Transaksi Pulang): uang sudah diterima. */
    private const ROLE_HAPUS = AksiRole::KASIR_RI_BATAL;

    public array $renderVersions = [];
    protected array $renderAreas = ['angsuran-awal-ri'];

    public bool $isFormLocked = false;
    #[Locked] // kunci record — tak boleh diubah dari klien (celah ID)
    public ?int $riHdrNo = null;

    /** Baris rstxn_ripaymentdtls kunjungan ini (hasil query, bukan dokumen EMR). */
    public array $daftarAngsuran = [];

    public array $formEntry = [
        'tanggal' => '',
        'shift'   => '',
        'accId'   => '',
        'accName' => '',
        'nominal' => null,
    ];

    private function nowFormatted(): string
    {
        return Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
    }

    /**
     * Shift dari jam pada tanggal terima (rstxn_shiftctls) — pola Ubah Data Kunjungan RJ/UGD.
     * Null bila format belum lengkap / jam di luar master shift (kasir memilih manual).
     */
    private function shiftDariTanggal(?string $tanggal): ?string
    {
        try {
            $jam = Carbon::createFromFormat('d/m/Y H:i:s', (string) $tanggal)->format('H:i:s');
        } catch (\Throwable $e) {
            return null;
        }

        $shift = DB::table('rstxn_shiftctls')
            ->whereNotNull('shift_start')
            ->whereNotNull('shift_end')
            ->whereRaw('? BETWEEN shift_start AND shift_end', [$jam])
            ->value('shift');

        return $shift !== null ? (string) $shift : null;
    }

    /** Tanggal = sekarang, shift ikut jamnya. */
    private function isiTanggalSekarang(): void
    {
        $this->formEntry['tanggal'] = $this->nowFormatted();
        $this->formEntry['shift']   = $this->shiftDariTanggal($this->formEntry['tanggal']) ?? '';
    }

    /** Tanggal diketik ulang → shift menyesuaikan; tetap bisa dipilih manual sesudahnya. */
    public function updatedFormEntry($value, $key): void
    {
        if ($key !== 'tanggal') {
            return;
        }

        $shift = $this->shiftDariTanggal($value);
        if ($shift !== null) {
            $this->formEntry['shift'] = $shift;
            $this->resetErrorBag('formEntry.shift');
        }
    }

    public function bolehEntri(): bool
    {
        return (bool) auth()->user()?->hasAnyRole(self::ROLE_ENTRI);
    }

    public function bolehHapus(): bool
    {
        return (bool) auth()->user()?->hasAnyRole(self::ROLE_HAPUS);
    }

    /* ===============================
     | MOUNT
     =============================== */
    public function mount(): void
    {
        $this->registerAreas($this->renderAreas);
        $this->isiTanggalSekarang();

        if ($this->riHdrNo) {
            $this->isFormLocked = $this->checkRIStatus($this->riHdrNo);
            $this->findData($this->riHdrNo);
        }
    }

    /* ===============================
     | LISTENER — sync lock saat parent broadcast (post/batal transaksi pulang)
     =============================== */
    #[On('ri.administrasi-selesai')]
    public function onAdministrasiSelesai(?int $riHdrNo = null): void
    {
        if ($riHdrNo && (int) ($this->riHdrNo ?? 0) === $riHdrNo) {
            $this->isFormLocked = $this->checkRIStatus($this->riHdrNo);
        }
    }

    #[On('administrasi-ri.updated')]
    public function onAdministrasiRiUpdated(): void
    {
        if ($this->riHdrNo) {
            $this->isFormLocked = $this->checkRIStatus($this->riHdrNo);
            $this->findData($this->riHdrNo);
        }
    }

    private function findData(int $riHdrNo): void
    {
        $this->daftarAngsuran = DB::table('rstxn_ripaymentdtls as p')
            ->leftJoin('acmst_accounts as a', 'a.acc_id', '=', 'p.acc_id')
            ->leftJoin('immst_employers as e', 'e.emp_id', '=', 'p.emp_id')
            ->where('p.rihdr_no', $riHdrNo)
            ->orderByDesc('p.ripay_date')
            ->orderByDesc('p.ripay_no')
            ->get([
                'p.ripay_no',
                DB::raw("to_char(p.ripay_date, 'dd/mm/yyyy hh24:mi:ss') as ripay_date"),
                'p.ripay_bayar',
                'p.shift',
                'p.acc_id',
                'a.acc_name',
                'p.emp_id',
                'e.emp_name',
            ])
            ->map(fn($row) => (array) $row)
            ->toArray();
    }

    /* ===============================
     | LOV KAS
     =============================== */
    #[On('lov.selected.kas-angsuran-awal-ri')]
    public function onKasSelected(string $target, ?array $payload): void
    {
        $this->formEntry['accId']   = $payload['acc_id']   ?? '';
        $this->formEntry['accName'] = $payload['acc_name'] ?? '';
        $this->resetErrorBag('formEntry.accId');

        if ($this->formEntry['accId'] !== '') {
            $this->dispatch('focus-input-nominal-angsuran-ri');
        }
    }

    /* ===============================
     | VALIDASI
     =============================== */
    protected function rules(): array
    {
        return [
            'formEntry.tanggal' => ['required', 'date_format:d/m/Y H:i:s'],
            'formEntry.shift'   => ['required', 'in:1,2,3'],
            'formEntry.accId'   => ['required', 'string'],
            'formEntry.nominal' => ['required', 'integer', 'min:1'],
        ];
    }

    protected function messages(): array
    {
        return [
            'formEntry.tanggal.required'    => 'Tanggal harus diisi.',
            'formEntry.tanggal.date_format' => 'Format tanggal: dd/mm/yyyy hh:mm:ss.',
            'formEntry.shift.required'      => 'Shift belum dipilih.',
            'formEntry.shift.in'            => 'Shift tidak valid.',
            'formEntry.accId.required'      => 'Akun kas belum dipilih.',
            'formEntry.nominal.required'    => 'Nominal titipan masih kosong.',
            'formEntry.nominal.integer'     => 'Nominal harus berupa angka.',
            'formEntry.nominal.min'         => 'Nominal titipan harus lebih dari 0.',
        ];
    }

    /* ===============================
     | SIMPAN
     =============================== */
    public function simpanAngsuran(): void
    {
        if (!$this->bolehEntri()) {
            $this->dispatch('toast', type: 'error', message: 'Hanya petugas kasir (Tu) yang dapat menerima angsuran awal.');
            return;
        }

        if ($this->checkRIStatus($this->riHdrNo)) {
            $this->isFormLocked = true;
            $this->dispatch('toast', type: 'error', message: 'Pasien sudah pulang, angsuran awal tidak bisa ditambah.');
            return;
        }

        // Sama dengan 6i & Kasir Pulang: kasir tanpa akun kas tak boleh menerima uang.
        if (!DB::table('user_kas')->where('user_id', auth()->id())->exists()) {
            $this->dispatch('toast', type: 'error', message: 'Akun kas anda belum terkonfigurasi. Hubungi administrator.');
            return;
        }

        $this->validate();

        $empId = auth()->user()->emp_id ?? null;
        if (!$empId) {
            $this->dispatch('toast', type: 'error', message: 'EMP ID belum diisi di profil user. Hubungi administrator.');
            return;
        }

        $nominal = (int) $this->formEntry['nominal'];
        $ripayNo = null;

        // ripay_no = MAX+1 (konvensi 6i). PK global lintas kunjungan, jadi bentrok antar
        // kasir ditangani ulang-coba atas ORA-00001 — FOR UPDATE pada agregat ditolak Oracle.
        for ($percobaan = 1; ; $percobaan++) {
            try {
                DB::transaction(function () use ($nominal, $empId, &$ripayNo) {
                    $this->lockRIRow($this->riHdrNo);

                    if ($this->checkRIStatus($this->riHdrNo)) {
                        throw new \RuntimeException('Pasien sudah pulang, angsuran awal tidak bisa ditambah.');
                    }

                    $ripayNo = (int) DB::table('rstxn_ripaymentdtls')
                        ->selectRaw('nvl(max(ripay_no),0)+1 as nomor')
                        ->value('nomor');

                    DB::table('rstxn_ripaymentdtls')->insert([
                        'ripay_no'    => $ripayNo,
                        'rihdr_no'    => $this->riHdrNo,
                        'emp_id'      => $empId,
                        'ripay_date'  => DB::raw("TO_DATE('" . $this->formEntry['tanggal'] . "','dd/mm/yyyy hh24:mi:ss')"),
                        'ripay_bayar' => $nominal,
                        'shift'       => $this->formEntry['shift'],
                        'acc_id'      => $this->formEntry['accId'],
                    ]);

                    $this->appendAdminLogRI(
                        $this->riHdrNo,
                        'Terima Angsuran Awal #' . $ripayNo . ': Rp ' . number_format($nominal) . ' (' . $this->formEntry['accId'] . ', shift ' . $this->formEntry['shift'] . ')'
                    );
                });
                break;
            } catch (\RuntimeException $e) {
                $this->dispatch('toast', type: 'error', message: $e->getMessage());
                return;
            } catch (\Illuminate\Database\QueryException $e) {
                if ($percobaan < 3 && str_contains($e->getMessage(), 'ORA-00001')) {
                    continue;
                }
                $this->dispatch('toast', type: 'error', message: 'Gagal menyimpan angsuran awal: ' . $e->getMessage());
                return;
            } catch (\Exception $e) {
                $this->dispatch('toast', type: 'error', message: 'Gagal menyimpan angsuran awal: ' . $e->getMessage());
                return;
            }
        }

        // Akun kas dipertahankan — titipan berikutnya cukup ketik nominal + Enter.
        $this->resetNominal();
        $this->findData($this->riHdrNo);
        $this->dispatch('administrasi-ri.updated');
        $this->dispatch('focus-input-nominal-angsuran-ri');
        $this->dispatch('toast', type: 'success', message: 'Angsuran awal Rp ' . number_format($nominal) . ' diterima.');

        // 6i langsung mencetak kwitansi setelah simpan.
        $this->dispatch('cetak-kwitansi-ri-angsuran-awal.open', ripayNo: $ripayNo);
    }

    /* ===============================
     | CETAK ULANG KWITANSI
     =============================== */
    public function cetakKwitansi(int $ripayNo): void
    {
        $this->dispatch('cetak-kwitansi-ri-angsuran-awal.open', ripayNo: $ripayNo);
    }

    /* ===============================
     | HAPUS
     =============================== */
    public function hapusAngsuran(int $ripayNo): void
    {
        if (!$this->bolehHapus()) {
            $this->dispatch('toast', type: 'error', message: 'Hapus angsuran awal hanya oleh Supervisor Tu / Admin.');
            return;
        }

        try {
            DB::transaction(function () use ($ripayNo) {
                $this->lockRIRow($this->riHdrNo);

                if ($this->checkRIStatus($this->riHdrNo)) {
                    throw new \RuntimeException('Pasien sudah pulang, angsuran awal tidak bisa dihapus.');
                }

                $row = DB::table('rstxn_ripaymentdtls')
                    ->where('ripay_no', $ripayNo)
                    ->where('rihdr_no', $this->riHdrNo)
                    ->first(['ripay_bayar', 'acc_id', DB::raw("to_char(ripay_date, 'dd/mm/yyyy hh24:mi:ss') as ripay_date")]);

                if (!$row) {
                    throw new \RuntimeException('Data angsuran awal tidak ditemukan.');
                }

                DB::table('rstxn_ripaymentdtls')
                    ->where('ripay_no', $ripayNo)
                    ->where('rihdr_no', $this->riHdrNo)
                    ->delete();

                // Nilai lama dicatat utuh: baris ini sudah masuk jurnal kas pada tanggalnya.
                $this->appendAdminLogRI(
                    $this->riHdrNo,
                    'Hapus Angsuran Awal #' . $ripayNo . ': Rp ' . number_format((int) $row->ripay_bayar)
                        . ' (' . $row->acc_id . ', ' . $row->ripay_date . ')'
                );
            });

            $this->findData($this->riHdrNo);
            $this->dispatch('administrasi-ri.updated');
            $this->dispatch('toast', type: 'success', message: 'Angsuran awal dihapus.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menghapus: ' . $e->getMessage());
        }
    }

    public function refreshTanggal(): void
    {
        $this->isiTanggalSekarang();
        $this->resetErrorBag(['formEntry.tanggal', 'formEntry.shift']);
    }

    public function resetFormEntry(): void
    {
        $this->reset(['formEntry']);
        $this->isiTanggalSekarang();
        $this->resetValidation();
        $this->incrementVersion('angsuran-awal-ri');
        $this->dispatch('focus-awal-angsuran-ri');
    }

    /** Sesudah simpan: kosongkan nominal & tanggal kembali ke sekarang, akun kas tetap. */
    private function resetNominal(): void
    {
        $this->formEntry['nominal'] = null;
        $this->isiTanggalSekarang();
        $this->resetValidation();
        $this->incrementVersion('angsuran-awal-ri');
    }
};
?>

{{-- Masuk dari Enter-kosong tab Obat Pinjam: fokus ke form; bila form tak tampil (pasien sudah
     pulang / bukan kasir) teruskan ke Kasir Pulang supaya rantai Enter antartab tidak putus. --}}
<div class="space-y-4" wire:key="{{ $this->renderKey('angsuran-awal-ri', [$riHdrNo ?? 'new']) }}"
    x-data
    x-on:focus-angsuran-awal-ri.window="$nextTick(() => {
        if (!$el.querySelector('[data-form-angsuran-awal]')) {
            $dispatch('administrasi-ri-goto-tab', { tab: 'RiKasir', focus: 'focus-input-bayar-ri' });
            return;
        }
        window.dispatchEvent(new CustomEvent('focus-awal-angsuran-ri'));
    })">

    @if ($isFormLocked)
        <div class="flex items-center gap-2 px-4 py-2.5 text-sm font-medium text-amber-700 bg-amber-50 border border-amber-200 rounded-xl dark:bg-amber-900/20 dark:border-amber-600 dark:text-amber-300">
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
            </svg>
            Pasien sudah pulang — angsuran awal sudah diperhitungkan di Kasir Pulang dan terkunci.
        </div>
    @endif

    <div class="grid items-start grid-cols-1 gap-4 lg:grid-cols-2">

        {{-- KIRI: form terima angsuran awal --}}
        @if (!$isFormLocked)
            <div class="p-4 border border-hairline rounded-2xl dark:border-gray-700 bg-surface-soft dark:bg-gray-800/40"
                x-data="{
                    // Input LOV kas nonaktif bila kas sudah terpilih → langsung ke Nominal.
                    fokusAwal() {
                        const lov = this.$refs.lovKasAngsuran?.querySelector('input:not([disabled])');
                        lov ? lov.focus() : this.fokusNominal();
                    },
                    fokusNominal() {
                        const el = this.$refs.inputNominalAngsuran;
                        el?.focus();
                        el?.select?.();
                    },
                }"
                x-on:focus-awal-angsuran-ri.window="$nextTick(() => { fokusAwal(); setTimeout(() => fokusAwal(), 150); })"
                x-on:focus-input-nominal-angsuran-ri.window="$nextTick(() => { fokusNominal(); setTimeout(() => fokusNominal(), 150); })">

                <h4 class="mb-1 text-sm font-semibold text-body dark:text-gray-300">Terima Angsuran Awal (Titipan)</h4>
                <p class="mb-3 text-xs text-muted dark:text-gray-400">
                    Uang yang dibayar pasien selama dirawat. Totalnya otomatis mengurangi tagihan di tab
                    <strong>Kasir Pulang</strong>. Kwitansi langsung tercetak setelah disimpan.
                    Shift otomatis mengikuti jam Tanggal Terima, tetap bisa dipilih manual.
                </p>

                @if ($this->bolehEntri())
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-12 items-start" data-form-angsuran-awal>
                        {{-- Tanggal --}}
                        <div class="sm:col-span-8">
                            <x-input-label value="Tanggal Terima" class="mb-1" />
                            <div class="flex gap-2">
                                <x-text-input wire:model.live.debounce.500ms="formEntry.tanggal" placeholder="dd/mm/yyyy hh:mm:ss"
                                    :error="$errors->has('formEntry.tanggal')" class="w-56 text-sm font-mono"
                                    x-on:keydown.enter.prevent="fokusAwal()" />
                                <x-now-button wire:click="refreshTanggal" title="Set ke tanggal & jam sekarang" />
                            </div>
                            <x-input-error :messages="$errors->get('formEntry.tanggal')" class="mt-1" />
                        </div>

                        {{-- Shift — otomatis dari jam Tanggal Terima, tetap bisa dipilih manual --}}
                        <div class="sm:col-span-4">
                            <x-input-label value="Shift" class="mb-1" />
                            <x-select-input wire:model="formEntry.shift" class="w-full" :error="$errors->has('formEntry.shift')">
                                <option value="">-- Shift --</option>
                                <option value="1">Shift 1</option>
                                <option value="2">Shift 2</option>
                                <option value="3">Shift 3</option>
                            </x-select-input>
                            <x-input-error :messages="$errors->get('formEntry.shift')" class="mt-1" />
                        </div>

                        {{-- Akun Kas --}}
                        {{-- Enter saat kolom cari kosong = selesai di tab ini → Kasir Pulang. --}}
                        <div class="sm:col-span-6" x-ref="lovKasAngsuran"
                            x-on:keydown.enter="if (!$event.target.value?.trim()) $dispatch('administrasi-ri-goto-tab', { tab: 'RiKasir', focus: 'focus-input-bayar-ri' })">
                            <livewire:lov.kas.lov-kas target="kas-angsuran-awal-ri" tipe="ri" label="Akun Kas"
                                :initialAccId="$formEntry['accId'] ?: null"
                                wire:key="lov-kas-angsuran-awal-ri-{{ $riHdrNo }}-{{ $renderVersions['angsuran-awal-ri'] ?? 0 }}" />
                            <x-input-error :messages="$errors->get('formEntry.accId')" class="mt-1" />
                        </div>

                        {{-- Nominal — Enter = simpan --}}
                        <div class="sm:col-span-4">
                            <x-input-label value="Nominal (Rp)" class="mb-1" />
                            <x-text-input-number wire:model="formEntry.nominal" placeholder="0"
                                :error="$errors->has('formEntry.nominal')"
                                x-ref="inputNominalAngsuran"
                                wire:key="{{ $this->renderKey('angsuran-awal-ri', 'nominal') }}"
                                x-on:keydown.enter.prevent="
                                    const kosong = $el.value.replace(/\D/g, '') === '';
                                    $el.blur();
                                    kosong
                                        ? $dispatch('administrasi-ri-goto-tab', { tab: 'RiKasir', focus: 'focus-input-bayar-ri' })
                                        : $wire.simpanAngsuran()
                                " />
                            <x-input-error :messages="$errors->get('formEntry.nominal')" class="mt-1" />
                        </div>

                        {{-- Batal (ikon) --}}
                        <div class="flex sm:col-span-2 sm:pt-6">
                            <x-icon-button color="gray" type="button" wire:click.prevent="resetFormEntry"
                                title="Batal — kosongkan form entri">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </x-icon-button>
                        </div>

                        <p class="text-xs text-muted dark:text-gray-400 sm:col-span-12">
                            Tekan <span class="px-1.5 py-0.5 font-semibold rounded border border-hairline bg-canvas text-body dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200">Enter</span>
                            di kolom Nominal untuk menyimpan · Enter saat Nominal kosong = lanjut ke Kasir Pulang.
                        </p>
                    </div>
                @else
                    <p class="text-sm italic text-muted dark:text-gray-400">
                        Penerimaan angsuran awal hanya oleh petugas kasir (Tu / Supervisor Tu / Admin).
                    </p>
                @endif
            </div>
        @endif

        {{-- KANAN: daftar angsuran awal --}}
        <div class="overflow-hidden bg-canvas border border-hairline rounded-2xl dark:border-gray-700 dark:bg-gray-900">
            <div class="flex items-center justify-between px-4 py-3 border-b border-hairline dark:border-gray-700">
                <h3 class="text-sm font-semibold text-body dark:text-gray-300">Riwayat Angsuran Awal</h3>
                <x-badge variant="gray">{{ count($daftarAngsuran) }} transaksi</x-badge>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead class="text-sm font-semibold tracking-wide text-left text-gray-600 uppercase dark:text-gray-300 bg-surface-soft dark:bg-gray-800/50">
                        <tr>
                            <th class="px-4 py-3">Tanggal</th>
                            <th class="px-4 py-3">Akun Kas</th>
                            <th class="px-4 py-3">Kasir</th>
                            <th class="px-4 py-3 text-right">Nominal</th>
                            <th class="w-24 px-4 py-3 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-hairline-soft dark:divide-gray-800">
                        @forelse ($daftarAngsuran as $row)
                            <tr wire:key="angsuran-awal-ri-{{ $row['ripay_no'] }}" class="transition hover:bg-surface-soft dark:hover:bg-gray-800/40">
                                <td class="px-4 py-1.5 whitespace-nowrap">
                                    <div class="font-mono text-sm text-muted dark:text-gray-400">{{ $row['ripay_date'] ?? '-' }}</div>
                                    <div class="text-xs text-muted dark:text-gray-400">No. {{ $row['ripay_no'] }} · Shift {{ $row['shift'] ?? '-' }}</div>
                                </td>
                                <td class="px-4 py-1.5">
                                    <div class="text-ink dark:text-gray-200">{{ $row['acc_name'] ?: '-' }}</div>
                                    <div class="font-mono text-xs text-muted dark:text-gray-400">{{ $row['acc_id'] ?? '-' }}</div>
                                </td>
                                <td class="px-4 py-1.5 text-muted dark:text-gray-400">
                                    {{ $row['emp_name'] ?: ($row['emp_id'] ?? '-') }}
                                </td>
                                <td class="px-4 py-1.5 font-semibold text-right text-ink dark:text-gray-200 whitespace-nowrap">
                                    Rp {{ number_format($row['ripay_bayar'] ?? 0) }}
                                </td>
                                <td class="px-4 py-1.5">
                                    <div class="flex items-center justify-center gap-1">
                                        <x-cetak-button wire:click="cetakKwitansi({{ $row['ripay_no'] }})" title="Cetak ulang kwitansi" />
                                        @if (!$isFormLocked && $this->bolehHapus())
                                            <x-hapus-button wire:click.prevent="hapusAngsuran({{ $row['ripay_no'] }})"
                                                confirm="Hapus angsuran awal Rp {{ number_format($row['ripay_bayar'] ?? 0) }}? Pastikan uang sudah dikembalikan / salah entri." />
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-10 text-sm text-center text-muted-soft dark:text-gray-600">
                                    Belum ada angsuran awal
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    @if (!empty($daftarAngsuran))
                        <tfoot class="border-t border-hairline bg-surface-soft dark:bg-gray-800/50 dark:border-gray-700">
                            <tr>
                                <td colspan="3" class="px-4 py-3 text-sm font-semibold text-muted dark:text-gray-400">Total Angsuran Awal</td>
                                <td class="px-4 py-3 text-sm font-bold text-right text-brand-green dark:text-brand-lime">
                                    Rp {{ number_format(collect($daftarAngsuran)->sum('ripay_bayar')) }}
                                </td>
                                <td></td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        </div>
    </div>

</div>
