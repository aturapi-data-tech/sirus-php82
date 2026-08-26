<?php

/**
 * Pemantauan Suhu Ruang Server — FORM (Akreditasi MRMIK 2.2).
 *
 * Satu modal = SATU PENGUKURAN. Bentuknya modul master biasa (lihat
 * docs/standar-master-module.md §4): buka → isi → simpan → tutup. Tak ada
 * lembar, tak ada TTD tersimpan, tak ada terkunci/buka kunci — tanda tangan
 * dibubuhkan di kertas pada cetakan bulanan.
 *
 * Kondisi N/TN tidak dipilih petugas: ia DIHITUNG dari suhu terhadap ambang di
 * SuhuRuangServerOptions, lalu disimpan sebagai snapshot supaya penilaian yang
 * sudah tercetak tak berubah kalau ambangnya kelak direvisi.
 */

use App\Http\Traits\Concerns\WithRenderVersioningTrait;
use App\Http\Traits\Concerns\WithValidationToastTrait;
use App\Http\Traits\Sistem\PemantauanRuangServer\PemantauanSuhuTrait;
use App\Support\Options\SuhuRuangServerOptions;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component {
    use PemantauanSuhuTrait, WithRenderVersioningTrait, WithValidationToastTrait;

    public array $renderVersions = [];

    protected array $renderAreas = ['modal'];

    public string $formMode = 'create';

    /** 0 = pengukuran belum pernah disimpan. */
    public int $suhuNo = 0;

    public bool $siapDipakai = false;

    public array $form = [];

    /** Paraf pencatat pertama — dipertahankan apa adanya saat baris dikoreksi. */
    public array $paraf = ['nama' => '', 'kode' => '', 'tanggal' => ''];

    public function mount(): void
    {
        $this->registerAreas(['modal']);
        $this->form = $this->formKosong();
    }

    /* ══════════════════════ BUKA / TUTUP ══════════════════════ */

    #[On('pemantauan-suhu-ruang-server.openCreate')]
    public function openCreate(): void
    {
        $this->resetForm();
        $this->siapDipakai = $this->checkTabelSuhu();
        $this->formMode = 'create';

        // Pengukuran dicatat saat itu juga; waktunya diisi sebagai titik awal
        // yang masih bisa diubah, bukan dikosongkan.
        $this->form['waktu'] = Carbon::now(config('app.timezone'))
            ->format(SuhuRuangServerOptions::FORMAT_WAKTU);

        $this->incrementVersion('modal');
        $this->dispatch('open-modal', name: 'pemantauan-suhu-ruang-server-actions');
        $this->dispatch('focus-suhu');
    }

    #[On('pemantauan-suhu-ruang-server.openEdit')]
    public function openEdit(int $suhuNo): void
    {
        $this->resetForm();
        $this->siapDipakai = $this->checkTabelSuhu();

        [$baris, $isi] = $this->findSuhu($suhuNo);

        if ($baris === null) {
            $this->dispatch('toast', type: 'error', message: 'Pengukuran tidak ditemukan.');

            return;
        }

        $this->formMode = 'edit';
        $this->suhuNo = (int) $baris->suhuserver_no;

        // array_replace, bukan penugasan langsung: record lama bisa belum punya
        // kunci yang baru ditambahkan, dan kunci itu tetap terisi nilai bawaan.
        $this->form = array_replace($this->formKosong(), [
            'waktu' => (string) ($isi['waktu'] ?? ''),
            'suhu' => (string) ($isi['suhu'] ?? ''),
            'statusAc' => (string) ($isi['statusAc'] ?? 'normal'),
            'tindakLanjut' => (string) ($isi['tindakLanjut'] ?? ''),
        ]);

        $this->paraf = [
            'nama' => (string) ($isi['paraf']['nama'] ?? ''),
            'kode' => (string) ($isi['paraf']['kode'] ?? ''),
            'tanggal' => (string) ($isi['paraf']['tanggal'] ?? ''),
        ];

        $this->incrementVersion('modal');
        $this->dispatch('open-modal', name: 'pemantauan-suhu-ruang-server-actions');
        $this->dispatch('focus-suhu');
    }

    public function closeModal(): void
    {
        $this->resetForm();
        $this->dispatch('close-modal', name: 'pemantauan-suhu-ruang-server-actions');
        $this->resetVersion();
    }

    private function resetForm(): void
    {
        $this->formMode = 'create';
        $this->suhuNo = 0;
        $this->form = $this->formKosong();
        $this->paraf = ['nama' => '', 'kode' => '', 'tanggal' => ''];
        $this->resetValidation();
    }

    private function formKosong(): array
    {
        return [
            'waktu' => '',
            'suhu' => '',
            'statusAc' => 'normal',
            'tindakLanjut' => '',
        ];
    }

    /* ══════════════════════ AKSI ══════════════════════ */

    public function setWaktuSekarang(): void
    {
        $this->form['waktu'] = Carbon::now(config('app.timezone'))
            ->format(SuhuRuangServerOptions::FORMAT_WAKTU);
    }

    /** Kondisi N/TN untuk pratinjau di layar — sumbernya sama dengan saat simpan. */
    public function kondisiSekarang(): string
    {
        return SuhuRuangServerOptions::hitungKondisi($this->form, []);
    }

    public function save(): void
    {
        if (! $this->siapDipakai) {
            $this->dispatch('toast', type: 'error', message: 'Tabel pemantauan suhu belum dipasang.');

            return;
        }

        // validate() DULUAN — guard sebelum validasi menyembunyikan field merah.
        $this->validateWithToast([
            'form.waktu' => ['required', 'date_format:' . SuhuRuangServerOptions::FORMAT_WAKTU],
            'form.suhu' => ['required', 'numeric', 'min:-50', 'max:100'],
            'form.statusAc' => ['required', 'in:' . implode(',', array_keys(SuhuRuangServerOptions::STATUS_AC))],
            'form.tindakLanjut' => ['nullable', 'string', 'max:255'],
        ], [
            'form.waktu.required' => 'Waktu pemantauan wajib diisi.',
            'form.waktu.date_format' => 'Waktu harus berformat dd/mm/yyyy HH:MM:SS.',
            'form.suhu.required' => 'Suhu wajib diisi.',
            'form.statusAc.in' => 'Status AC tidak dikenal.',
        ], [
            'form.waktu' => 'Waktu pemantauan',
            'form.suhu' => 'Suhu',
            'form.statusAc' => 'Status AC',
            'form.tindakLanjut' => 'Tindak lanjut',
        ]);

        $kondisi = SuhuRuangServerOptions::hitungKondisi($this->form, []);

        // Formulir menulis "di luar rentang → wajib tindak lanjut". Ditegakkan di
        // sini, bukan sekadar jadi tulisan di label.
        if ($kondisi === 'TN' && blank($this->form['tindakLanjut'])) {
            $this->dispatch('toast', type: 'error', message: 'Kondisi Tidak Normal — tindak lanjut wajib diisi.');

            return;
        }

        // Paraf milik petugas yang MENGUKUR, jadi ia tak berpindah tangan saat
        // barisnya dikoreksi belakangan.
        $paraf = $this->formMode === 'edit' && filled($this->paraf['nama'])
            ? $this->paraf
            : [
                'nama' => auth()->user()->myuser_name ?? auth()->user()->name ?? '',
                'kode' => auth()->user()->myuser_code ?? '',
                'tanggal' => Carbon::now(config('app.timezone'))->format(SuhuRuangServerOptions::FORMAT_WAKTU),
            ];

        try {
            $this->simpanSuhu($this->suhuNo === 0 ? null : $this->suhuNo, [
                'waktu' => $this->form['waktu'],
                'suhu' => (string) $this->form['suhu'],
                'statusAc' => $this->form['statusAc'],
                // Snapshot, bukan hitungan ulang saat cetak.
                'kondisi' => $kondisi,
                'tindakLanjut' => $this->form['tindakLanjut'],
                'paraf' => $paraf,
            ]);
        } catch (\Throwable $exception) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menyimpan: ' . $exception->getMessage());

            return;
        }

        $this->dispatch('toast', type: 'success', message: 'Pengukuran suhu tersimpan.');
        $this->closeModal();
        $this->dispatch('pemantauan-suhu-ruang-server.saved');
    }

    #[On('pemantauan-suhu-ruang-server.requestDelete')]
    public function hapusSuhu(int $suhuNo): void
    {
        // Dua lapis: @can di blade menyembunyikan tombolnya, guard ini menutup
        // wire:click yang dipanggil langsung.
        if (! auth()->user()?->can('sistem.pemantauanRuangServer.hapus')) {
            $this->dispatch('toast', type: 'error', message: 'Anda tidak berwenang menghapus pengukuran.');

            return;
        }

        $terhapus = DB::table('rstxn_suhuservers')->where('suhuserver_no', $suhuNo)->delete();

        if ($terhapus === 0) {
            $this->dispatch('toast', type: 'error', message: 'Pengukuran tidak ditemukan.');

            return;
        }

        if ($this->suhuNo === $suhuNo) {
            $this->closeModal();
        }

        $this->dispatch('toast', type: 'success', message: 'Pengukuran dihapus.');
        $this->dispatch('pemantauan-suhu-ruang-server.saved');
    }
};
?>

<div>
    <x-modal name="pemantauan-suhu-ruang-server-actions" size="full" height="full" focusable>
        <div class="flex flex-col h-full" wire:key="{{ $this->renderKey('modal', [$formMode, $suhuNo]) }}">

            {{-- HEADER --}}
            <div class="px-6 py-5 bg-surface-soft">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 class="ds-display-sm dark:text-gray-100">
                            {{ $formMode === 'edit' ? 'Ubah Pengukuran Suhu' : 'Catat Pengukuran Suhu' }}
                        </h2>
                        <p class="mt-0.5 text-sm text-muted dark:text-gray-400">
                            Ruang server &middot; standar {{ \App\Support\Options\SuhuRuangServerOptions::SUHU_MIN_DEFAULT }}&ndash;{{ \App\Support\Options\SuhuRuangServerOptions::SUHU_MAX_DEFAULT }} °C
                        </p>
                        <div class="mt-3">
                            <x-badge :variant="$formMode === 'edit' ? 'warning' : 'success'">
                                {{ $formMode === 'edit' ? 'Mode: Edit' : 'Mode: Tambah' }}
                            </x-badge>
                        </div>
                    </div>
                    <x-icon-button color="gray" type="button" wire:click="closeModal">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                        </svg>
                    </x-icon-button>
                </div>
            </div>

            {{-- BODY --}}
            <div class="flex-1 min-h-0 px-4 py-4 overflow-y-auto bg-surface-soft dark:bg-gray-950/20" x-enter-chain x-data
                 x-on:focus-suhu.window="$nextTick(() => setTimeout(() => $refs.inputSuhu?.focus(), 150))">

                @if (! $siapDipakai)
                    <div class="px-4 py-3 text-sm border rounded-2xl bg-amber-50 border-amber-200 text-amber-900 dark:bg-amber-900/20 dark:border-amber-800 dark:text-amber-200">
                        Tabel <span class="font-mono">RSTXN_SUHUSERVERS</span> belum dipasang di basis data.
                    </div>
                @else
                    @php $kondisi = $this->kondisiSekarang(); @endphp

                    <x-border-form title="Pengukuran">
                        <div class="space-y-4">
                            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div>
                                    <x-input-label value="Waktu Pemantauan" class="mb-1" />
                                    <div class="flex items-center gap-2">
                                        <x-text-input wire:model.live="form.waktu"
                                            :error="$errors->has('form.waktu')"
                                            placeholder="dd/mm/yyyy HH:MM:SS" class="w-full" />
                                        <x-now-button wire:click="setWaktuSekarang" title="Set ke tanggal & jam sekarang" />
                                    </div>
                                    <x-input-error :messages="$errors->get('form.waktu')" class="mt-1" />
                                </div>
                                <div>
                                    <x-input-label value="Suhu (°C)" class="mb-1" />
                                    <x-text-input wire:model.live.debounce.400ms="form.suhu" x-ref="inputSuhu"
                                        :error="$errors->has('form.suhu')"
                                        inputmode="decimal" placeholder="cth: 22.5" class="w-full" />
                                    <x-input-error :messages="$errors->get('form.suhu')" class="mt-1" />
                                </div>
                            </div>

                            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div>
                                    <x-input-label value="Status AC" class="mb-1" />
                                    <x-select-input wire:model.live="form.statusAc" class="w-full">
                                        @foreach (\App\Support\Options\SuhuRuangServerOptions::STATUS_AC as $kunci => $label)
                                            <option value="{{ $kunci }}">{{ $label }}</option>
                                        @endforeach
                                    </x-select-input>
                                    <x-input-error :messages="$errors->get('form.statusAc')" class="mt-1" />
                                </div>
                                <div>
                                    {{-- Pratinjau kondisi: petugas tahu wajib-tidaknya tindak lanjut
                                         SEBELUM menekan simpan, bukan sesudah ditolak toast. --}}
                                    <x-input-label value="Kondisi (dihitung)" class="mb-1" />
                                    <div class="flex items-center h-10">
                                        @if ($kondisi === 'TN')
                                            <x-badge variant="danger">TN — Tidak Normal</x-badge>
                                        @else
                                            <x-badge variant="success">N — Normal</x-badge>
                                        @endif
                                    </div>
                                </div>
                            </div>

                            <div>
                                <x-input-label
                                    :value="'Tindak Lanjut' . ($kondisi === 'TN' ? ' (wajib — kondisi Tidak Normal)' : ' (opsional)')"
                                    class="mb-1" />
                                <x-textarea wire:model.live="form.tindakLanjut" rows="2"
                                    :error="$errors->has('form.tindakLanjut')"
                                    placeholder="cth: AC dinyalakan penuh, lapor teknisi pukul 10.00" class="w-full" />
                                <x-input-error :messages="$errors->get('form.tindakLanjut')" class="mt-1" />
                            </div>

                            @if ($formMode === 'edit' && filled($paraf['nama']))
                                <p class="text-xs text-muted dark:text-gray-400">
                                    Diparaf <span class="font-semibold">{{ $paraf['nama'] }}</span>
                                    @if (filled($paraf['tanggal']))
                                        &middot; <span class="font-mono">{{ $paraf['tanggal'] }}</span>
                                    @endif
                                    &mdash; paraf tetap milik pencatat pertama walau baris ini dikoreksi.
                                </p>
                            @endif
                        </div>
                    </x-border-form>
                @endif
            </div>

            {{-- FOOTER --}}
            <div class="sticky bottom-0 z-10 px-6 py-4 mt-auto bg-surface-soft border-t border-hairline dark:bg-gray-900 dark:border-gray-700">
                <div class="flex justify-end gap-2">
                    <x-secondary-button type="button" wire:click="closeModal">Batal</x-secondary-button>
                    @if ($siapDipakai)
                        <x-primary-button type="button" wire:click="save" wire:loading.attr="disabled">
                            <span wire:loading.remove>Simpan</span>
                            <span wire:loading>Menyimpan...</span>
                        </x-primary-button>
                    @endif
                </div>
            </div>
        </div>
    </x-modal>
</div>
