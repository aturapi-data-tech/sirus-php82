<?php

/**
 * Pemantauan Akses Ruang Server — FORM (Akreditasi MRMIK 2.2).
 *
 * Satu modal = SATU KUNJUNGAN. Bentuknya modul master biasa (lihat
 * docs/standar-master-module.md §4): buka → isi → simpan → tutup. Tak ada
 * lembar, tak ada TTD tersimpan, tak ada terkunci/buka kunci — tanda tangan
 * dibubuhkan di kertas pada cetakan bulanan.
 */

use App\Http\Traits\Concerns\WithRenderVersioningTrait;
use App\Http\Traits\Concerns\WithValidationToastTrait;
use App\Http\Traits\Sistem\PemantauanRuangServer\PemantauanAksesTrait;
use App\Support\Options\AksesRuangServerOptions;
use App\Support\Options\SuhuRuangServerOptions;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component {
    use PemantauanAksesTrait, WithRenderVersioningTrait, WithValidationToastTrait;

    public array $renderVersions = [];

    protected array $renderAreas = ['modal'];

    public string $formMode = 'create';

    /** 0 = kunjungan belum pernah disimpan. */
    public int $aksesNo = 0;

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

    #[On('pemantauan-akses-ruang-server.openCreate')]
    public function openCreate(): void
    {
        $this->resetForm();
        $this->siapDipakai = $this->checkTabelAkses();
        $this->formMode = 'create';

        // Kunjungan dicatat saat tamunya masuk; waktunya diisi sebagai titik awal
        // yang masih bisa diubah, bukan dikosongkan.
        $this->form['waktu'] = Carbon::now(config('app.timezone'))
            ->format(SuhuRuangServerOptions::FORMAT_WAKTU);

        $this->incrementVersion('modal');
        $this->dispatch('open-modal', name: 'pemantauan-akses-ruang-server-actions');
        $this->dispatch('focus-nama');
    }

    #[On('pemantauan-akses-ruang-server.openEdit')]
    public function openEdit(int $aksesNo): void
    {
        $this->resetForm();
        $this->siapDipakai = $this->checkTabelAkses();

        [$baris, $isi] = $this->findAkses($aksesNo);

        if ($baris === null) {
            $this->dispatch('toast', type: 'error', message: 'Kunjungan tidak ditemukan.');

            return;
        }

        $this->formMode = 'edit';
        $this->aksesNo = (int) $baris->aksesserver_no;

        // array_replace, bukan penugasan langsung: record lama bisa belum punya
        // kunci yang baru ditambahkan, dan kunci itu tetap terisi nilai bawaan.
        $this->form = array_replace($this->formKosong(), [
            'waktu' => (string) ($isi['waktu'] ?? ''),
            'waktuKeluar' => (string) ($isi['waktuKeluar'] ?? ''),
            'nama' => (string) ($isi['nama'] ?? ''),
            'unitInstansi' => (string) ($isi['unitInstansi'] ?? ''),
            'jenisPengunjung' => (string) ($isi['jenisPengunjung'] ?? 'internal'),
            'keperluan' => (string) ($isi['keperluan'] ?? 'perawatanRutin'),
            'keperluanLain' => (string) ($isi['keperluanLain'] ?? ''),
            'membawaPerangkat' => (string) ($isi['membawaPerangkat'] ?? ''),
            'didampingi' => (string) ($isi['didampingi'] ?? ''),
            'catatan' => (string) ($isi['catatan'] ?? ''),
        ]);

        $this->paraf = [
            'nama' => (string) ($isi['paraf']['nama'] ?? ''),
            'kode' => (string) ($isi['paraf']['kode'] ?? ''),
            'tanggal' => (string) ($isi['paraf']['tanggal'] ?? ''),
        ];

        $this->incrementVersion('modal');
        $this->dispatch('open-modal', name: 'pemantauan-akses-ruang-server-actions');
        $this->dispatch('focus-nama');
    }

    public function closeModal(): void
    {
        $this->resetForm();
        $this->dispatch('close-modal', name: 'pemantauan-akses-ruang-server-actions');
        $this->resetVersion();
    }

    private function resetForm(): void
    {
        $this->formMode = 'create';
        $this->aksesNo = 0;
        $this->form = $this->formKosong();
        $this->paraf = ['nama' => '', 'kode' => '', 'tanggal' => ''];
        $this->resetValidation();
    }

    private function formKosong(): array
    {
        return [
            'waktu' => '',
            'waktuKeluar' => '',
            'nama' => '',
            'unitInstansi' => '',
            'jenisPengunjung' => 'internal',
            'keperluan' => 'perawatanRutin',
            'keperluanLain' => '',
            'membawaPerangkat' => '',
            'didampingi' => '',
            'catatan' => '',
        ];
    }

    /* ══════════════════════ AKSI ══════════════════════ */

    public function setWaktuMasukSekarang(): void
    {
        $this->form['waktu'] = Carbon::now(config('app.timezone'))
            ->format(SuhuRuangServerOptions::FORMAT_WAKTU);
    }

    public function setWaktuKeluarSekarang(): void
    {
        $this->form['waktuKeluar'] = Carbon::now(config('app.timezone'))
            ->format(SuhuRuangServerOptions::FORMAT_WAKTU);
    }

    /**
     * Tutup kunjungan langsung dari tabel — waktu keluar diisi jam sekarang.
     *
     * Ada karena inilah aksi yang paling sering dilakukan: petugas mencatat saat
     * tamu datang, lalu kembali ke layar ini saat tamunya pulang.
     */
    #[On('pemantauan-akses-ruang-server.tutup')]
    public function tutupKunjungan(int $aksesNo): void
    {
        if (! $this->checkTabelAkses()) {
            return;
        }

        [$baris, $isi] = $this->findAkses($aksesNo);

        if ($baris === null) {
            $this->dispatch('toast', type: 'error', message: 'Kunjungan tidak ditemukan.');

            return;
        }

        if (! AksesRuangServerOptions::masihDiDalam($isi)) {
            $this->dispatch('toast', type: 'error', message: 'Kunjungan ini sudah punya waktu keluar.');

            return;
        }

        $sekarang = Carbon::now(config('app.timezone'))->format(SuhuRuangServerOptions::FORMAT_WAKTU);
        $isi['waktuKeluar'] = $sekarang;

        // Jam sekarang bisa saja MENDAHULUI waktu masuk kalau barisnya salah ketik
        // (mis. tanggal masuk kelewat maju). Dibiarkan lewat, baris itu tersimpan
        // dengan lama kunjungan kosong dan tak pernah bisa dijelaskan ke auditor.
        if (AksesRuangServerOptions::keluarSebelumMasuk($isi)) {
            $this->dispatch('toast', type: 'error', message: 'Waktu masuk baris ini ada di masa depan — perbaiki lewat tombol Ubah.');

            return;
        }

        try {
            $this->simpanAkses($aksesNo, $isi);
        } catch (\Throwable $exception) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menyimpan: ' . $exception->getMessage());

            return;
        }

        $this->dispatch('toast', type: 'success', message: 'Waktu keluar dicatat ' . $sekarang . '.');
        $this->dispatch('pemantauan-akses-ruang-server.saved');
    }

    public function save(): void
    {
        if (! $this->siapDipakai) {
            $this->dispatch('toast', type: 'error', message: 'Tabel akses ruang server belum dipasang.');

            return;
        }

        // validate() DULUAN — guard sebelum validasi menyembunyikan field merah.
        $this->validateWithToast([
            'form.waktu' => ['required', 'date_format:' . SuhuRuangServerOptions::FORMAT_WAKTU],
            'form.waktuKeluar' => ['nullable', 'date_format:' . SuhuRuangServerOptions::FORMAT_WAKTU],
            'form.nama' => ['required', 'string', 'max:100'],
            'form.unitInstansi' => ['nullable', 'string', 'max:100'],
            'form.jenisPengunjung' => ['required', 'in:' . implode(',', array_keys(AksesRuangServerOptions::JENIS_PENGUNJUNG))],
            'form.keperluan' => ['required', 'in:' . implode(',', array_keys(AksesRuangServerOptions::KEPERLUAN))],
            'form.keperluanLain' => ['nullable', 'string', 'max:255'],
            'form.membawaPerangkat' => ['nullable', 'string', 'max:255'],
            'form.didampingi' => ['nullable', 'string', 'max:100'],
            'form.catatan' => ['nullable', 'string', 'max:255'],
        ], [
            'form.waktu.required' => 'Waktu masuk wajib diisi.',
            'form.waktu.date_format' => 'Waktu masuk harus berformat dd/mm/yyyy HH:MM:SS.',
            'form.waktuKeluar.date_format' => 'Waktu keluar harus berformat dd/mm/yyyy HH:MM:SS.',
            'form.nama.required' => 'Nama yang masuk wajib diisi.',
            'form.jenisPengunjung.in' => 'Jenis pengunjung tidak dikenal.',
            'form.keperluan.in' => 'Keperluan tidak dikenal.',
        ], [
            'form.waktu' => 'Waktu masuk',
            'form.waktuKeluar' => 'Waktu keluar',
            'form.nama' => 'Nama',
            'form.unitInstansi' => 'Unit / instansi',
            'form.jenisPengunjung' => 'Jenis pengunjung',
            'form.keperluan' => 'Keperluan',
            'form.keperluanLain' => 'Keperluan lain',
            'form.membawaPerangkat' => 'Perangkat yang dibawa',
            'form.didampingi' => 'Didampingi',
            'form.catatan' => 'Catatan',
        ]);

        // Keduanya bertanggal penuh, jadi kunjungan yang melewati tengah malam
        // tetap sah — yang ditolak hanya keluar SEBELUM masuk, yang pasti salah ketik.
        if (AksesRuangServerOptions::keluarSebelumMasuk($this->form)) {
            $this->dispatch('toast', type: 'error', message: 'Waktu keluar mendahului waktu masuk — periksa tanggal & jamnya.');

            return;
        }

        // Aturan ruang server: tamu dari luar tak boleh berada di dalam sendirian.
        // Ditegakkan di sini, bukan sekadar tulisan di label.
        if (AksesRuangServerOptions::wajibDidampingi($this->form['jenisPengunjung'])
            && blank($this->form['didampingi'])) {
            $this->dispatch('toast', type: 'error', message: 'Pengunjung dari luar wajib didampingi — isi nama petugas IT yang mendampingi.');

            return;
        }

        if ($this->form['keperluan'] === AksesRuangServerOptions::KEPERLUAN_LAIN
            && blank($this->form['keperluanLain'])) {
            $this->dispatch('toast', type: 'error', message: 'Keperluan "Lainnya" harus dijelaskan.');

            return;
        }

        // Paraf milik petugas yang MENCATAT kunjungan, jadi ia tak berpindah tangan
        // saat barisnya dikoreksi belakangan.
        $paraf = $this->formMode === 'edit' && filled($this->paraf['nama'])
            ? $this->paraf
            : [
                'nama' => auth()->user()->myuser_name ?? auth()->user()->name ?? '',
                'kode' => auth()->user()->myuser_code ?? '',
                'tanggal' => Carbon::now(config('app.timezone'))->format(SuhuRuangServerOptions::FORMAT_WAKTU),
            ];

        try {
            $this->simpanAkses($this->aksesNo === 0 ? null : $this->aksesNo, [
                ...$this->form,
                'paraf' => $paraf,
            ]);
        } catch (\Throwable $exception) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menyimpan: ' . $exception->getMessage());

            return;
        }

        $this->dispatch('toast', type: 'success', message: 'Catatan kunjungan tersimpan.');
        $this->closeModal();
        $this->dispatch('pemantauan-akses-ruang-server.saved');
    }

    #[On('pemantauan-akses-ruang-server.requestDelete')]
    public function hapusAkses(int $aksesNo): void
    {
        // Dua lapis: @can di blade menyembunyikan tombolnya, guard ini menutup
        // wire:click yang dipanggil langsung.
        if (! auth()->user()?->can('sistem.pemantauanRuangServer.hapus')) {
            $this->dispatch('toast', type: 'error', message: 'Anda tidak berwenang menghapus kunjungan.');

            return;
        }

        $terhapus = DB::table('rstxn_aksesservers')->where('aksesserver_no', $aksesNo)->delete();

        if ($terhapus === 0) {
            $this->dispatch('toast', type: 'error', message: 'Kunjungan tidak ditemukan.');

            return;
        }

        if ($this->aksesNo === $aksesNo) {
            $this->closeModal();
        }

        $this->dispatch('toast', type: 'success', message: 'Kunjungan dihapus.');
        $this->dispatch('pemantauan-akses-ruang-server.saved');
    }
};
?>

<div>
    <x-modal name="pemantauan-akses-ruang-server-actions" size="full" height="full" focusable>
        <div class="flex flex-col h-full" wire:key="{{ $this->renderKey('modal', [$formMode, $aksesNo]) }}">

            {{-- HEADER --}}
            <div class="px-6 py-5 bg-surface-soft">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 class="ds-display-sm dark:text-gray-100">
                            {{ $formMode === 'edit' ? 'Ubah Catatan Kunjungan' : 'Catat Kunjungan Ruang Server' }}
                        </h2>
                        <p class="mt-0.5 text-sm text-muted dark:text-gray-400">
                            {{ \App\Support\Options\RuangServerOptions::NAMA_RUANG }} &middot;
                            {{ \App\Support\Options\RuangServerOptions::GEDUNG_LANTAI }}
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
                 x-on:focus-nama.window="$nextTick(() => setTimeout(() => $refs.inputNama?.focus(), 150))">

                @if (! $siapDipakai)
                    <div class="px-4 py-3 text-sm border rounded-2xl bg-amber-50 border-amber-200 text-amber-900 dark:bg-amber-900/20 dark:border-amber-800 dark:text-amber-200">
                        Tabel <span class="font-mono">RSTXN_AKSESSERVERS</span> belum dipasang di basis data.
                    </div>
                @else
                    @php
                        $wajibDidampingi = \App\Support\Options\AksesRuangServerOptions::wajibDidampingi($form['jenisPengunjung']);
                        $keperluanLain = $form['keperluan'] === \App\Support\Options\AksesRuangServerOptions::KEPERLUAN_LAIN;
                    @endphp

                    <x-border-form title="Kunjungan">
                        <div class="space-y-4">
                            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div>
                                    <x-input-label value="Waktu Masuk" class="mb-1" />
                                    <div class="flex items-center gap-2">
                                        <x-text-input wire:model.live="form.waktu"
                                            :error="$errors->has('form.waktu')"
                                            placeholder="dd/mm/yyyy HH:MM:SS" class="w-full" />
                                        <x-now-button wire:click="setWaktuMasukSekarang" title="Set ke tanggal & jam sekarang" />
                                    </div>
                                    <x-input-error :messages="$errors->get('form.waktu')" class="mt-1" />
                                </div>
                                <div>
                                    <x-input-label value="Waktu Keluar (boleh kosong)" class="mb-1" />
                                    <div class="flex items-center gap-2">
                                        <x-text-input wire:model.live="form.waktuKeluar"
                                            :error="$errors->has('form.waktuKeluar')"
                                            placeholder="dd/mm/yyyy HH:MM:SS" class="w-full" />
                                        <x-now-button wire:click="setWaktuKeluarSekarang" title="Set ke tanggal & jam sekarang" />
                                    </div>
                                    <x-input-error :messages="$errors->get('form.waktuKeluar')" class="mt-1" />
                                    <p class="mt-1 text-xs text-muted dark:text-gray-400">
                                        Dikosongkan = tamu masih di dalam. Barisnya disorot di daftar.
                                    </p>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                                <div>
                                    <x-input-label value="Nama" class="mb-1" />
                                    <x-text-input wire:model.live="form.nama" x-ref="inputNama"
                                        :error="$errors->has('form.nama')"
                                        placeholder="Nama lengkap yang masuk" class="w-full" />
                                    <x-input-error :messages="$errors->get('form.nama')" class="mt-1" />
                                </div>
                                <div>
                                    <x-input-label value="Unit / Instansi" class="mb-1" />
                                    <x-text-input wire:model.live="form.unitInstansi"
                                        :error="$errors->has('form.unitInstansi')"
                                        placeholder="cth: PT Vendor Jaya" class="w-full" />
                                    <x-input-error :messages="$errors->get('form.unitInstansi')" class="mt-1" />
                                </div>
                                <div>
                                    <x-input-label value="Jenis Pengunjung" class="mb-1" />
                                    <x-select-input wire:model.live="form.jenisPengunjung" class="w-full">
                                        @foreach (\App\Support\Options\AksesRuangServerOptions::JENIS_PENGUNJUNG as $kunci => $label)
                                            <option value="{{ $kunci }}">{{ $label }}</option>
                                        @endforeach
                                    </x-select-input>
                                    <x-input-error :messages="$errors->get('form.jenisPengunjung')" class="mt-1" />
                                </div>
                            </div>

                            {{-- Keperluan + pendamping + perangkat jadi SATU baris.
                                 Lebarnya menyesuaikan: tanpa "Keperluan Lain" bertiga
                                 dapat 4 kolom, begitu field itu muncul berempat dapat 3 —
                                 total tetap 12, jadi barisnya tak pernah pecah. --}}
                            @php $spanKeperluan = $keperluanLain ? 'lg:col-span-3' : 'lg:col-span-4'; @endphp
                            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-12">
                                <div class="{{ $spanKeperluan }}">
                                    <x-input-label value="Keperluan" class="mb-1" />
                                    <x-select-input wire:model.live="form.keperluan" class="w-full">
                                        @foreach (\App\Support\Options\AksesRuangServerOptions::KEPERLUAN as $kunci => $label)
                                            <option value="{{ $kunci }}">{{ $label }}</option>
                                        @endforeach
                                    </x-select-input>
                                    <x-input-error :messages="$errors->get('form.keperluan')" class="mt-1" />
                                </div>

                                {{-- Uraian bebas hanya muncul saat memang dibutuhkan — field mati
                                     yang selalu terlihat cuma jadi kebisingan. --}}
                                @if ($keperluanLain)
                                    <div class="{{ $spanKeperluan }}">
                                        <x-input-label value="Keperluan Lain (wajib)" class="mb-1" />
                                        <x-text-input wire:model.live="form.keperluanLain"
                                            :error="$errors->has('form.keperluanLain')"
                                            placeholder="Jelaskan keperluannya" class="w-full" />
                                        <x-input-error :messages="$errors->get('form.keperluanLain')" class="mt-1" />
                                    </div>
                                @endif

                                <div class="{{ $spanKeperluan }}">
                                    <x-input-label
                                        :value="'Didampingi Petugas IT' . ($wajibDidampingi ? ' (wajib)' : ' (opsional)')"
                                        class="mb-1" />
                                    <x-ppa-combobox wireModel="form.didampingi"
                                        placeholder="Pilih atau ketik nama" />
                                    <x-input-error :messages="$errors->get('form.didampingi')" class="mt-1" />
                                </div>

                                <div class="{{ $spanKeperluan }}">
                                    <x-input-label value="Perangkat Dibawa Masuk (opsional)" class="mb-1" />
                                    <x-text-input wire:model.live="form.membawaPerangkat"
                                        :error="$errors->has('form.membawaPerangkat')"
                                        placeholder="cth: laptop, HDD eksternal" class="w-full" />
                                    <x-input-error :messages="$errors->get('form.membawaPerangkat')" class="mt-1" />
                                </div>
                            </div>

                            <div>
                                <x-input-label value="Catatan (opsional)" class="mb-1" />
                                <x-textarea wire:model.live="form.catatan" rows="2"
                                    :error="$errors->has('form.catatan')"
                                    placeholder="cth: mengganti kipas rack 2, tidak menyentuh perangkat lain" class="w-full" />
                                <x-input-error :messages="$errors->get('form.catatan')" class="mt-1" />
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
