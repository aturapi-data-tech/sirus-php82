<?php

/**
 * Master Identitas RS — RSMST_IDENTITASES.
 *
 * Tabel ini SATU BARIS dan bukan CRUD: tidak ada list, tidak ada modal, hanya
 * form isian + Simpan (varian "single-file" pada docs/standar-master-module.md).
 *
 * Isinya campur dua hal, dan itu sengaja dipisah di layar:
 *   1. Identitas & kop cetakan — dibaca 50+ blade cetak (consent, SEP, resume, dll.)
 *   2. Setelan transaksi — auto_ppn_status/ppn_value dipakai penerimaan medis &
 *      non-medis; rcvupdate_cost_price menentukan harga pokok master ikut terupdate.
 *
 * Kolom warisan Oracle Dev 6i (path berkas, serial, ambang ED/jatuh tempo) TIDAK
 * dibaca satu pun kode di aplikasi ini, dan path salah ketik bisa membuat unggah
 * atau cetak gagal diam-diam — karena itu ditampilkan TERKUNCI (keputusan user).
 */

use Livewire\Component;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;

new class extends Component {
    /** Kolom yang boleh diubah dari layar ini. */
    public array $form = [
        'int_name' => '',
        'int_city' => '',
        'int_address' => '',
        'int_phone1' => '',
        'int_phone2' => '',
        'int_fax' => '',
        'int_dir' => '',
        'app_desc' => '',
        'auto_ppn_status' => '0',
        'ppn_value' => 0,
        'rcvupdate_cost_price' => '0',
    ];

    /** Nilai mentah rcvupdate_cost_price sebelum dinormalkan — untuk peringatan di layar. */
    public string $nilaiLamaUpdateHpp = '';

    /** Kolom warisan — hanya dibaca, tidak pernah ditulis dari layar ini. */
    public array $terkunci = [];

    public function mount(): void
    {
        $this->muatData();
    }

    protected function muatData(): void
    {
        $baris = DB::table('rsmst_identitases')->first();

        if (!$baris) {
            $this->dispatch('toast', type: 'error', message: 'Data identitas belum ada di database. Hubungi administrator.');
            return;
        }

        $this->form = [
            'int_name' => (string) ($baris->int_name ?? ''),
            'int_city' => (string) ($baris->int_city ?? ''),
            'int_address' => (string) ($baris->int_address ?? ''),
            'int_phone1' => (string) ($baris->int_phone1 ?? ''),
            'int_phone2' => (string) ($baris->int_phone2 ?? ''),
            'int_fax' => (string) ($baris->int_fax ?? ''),
            'int_dir' => (string) ($baris->int_dir ?? ''),
            'app_desc' => (string) ($baris->app_desc ?? ''),
            'auto_ppn_status' => (string) ($baris->auto_ppn_status ?? '0'),
            'ppn_value' => (float) ($baris->ppn_value ?? 0),
            // Konvensi resmi aplikasi ini '1'/'0' (keputusan user). Nilai warisan
            // Oracle Dev 6i seperti 'Y' TIDAK diterjemahkan diam-diam jadi '1' —
            // itu akan menyalakan auto-update harga tanpa ada yang memutuskan.
            'rcvupdate_cost_price' => ((string) ($baris->rcvupdate_cost_price ?? '')) === '1' ? '1' : '0',
        ];

        $this->nilaiLamaUpdateHpp = (string) ($baris->rcvupdate_cost_price ?? '');

        $this->terkunci = [
            'int_logo' => (string) ($baris->int_logo ?? ''),
            'image_path' => (string) ($baris->image_path ?? ''),
            'rad_path' => (string) ($baris->rad_path ?? ''),
            'bpjs_path' => (string) ($baris->bpjs_path ?? ''),
            'bpjs_path_linux' => (string) ($baris->bpjs_path_linux ?? ''),
            'serial_machine' => (string) ($baris->serial_machine ?? ''),
            'print_desc' => (string) ($baris->print_desc ?? ''),
            'print_status' => (string) ($baris->print_status ?? ''),
            'ed_status' => (string) ($baris->ed_status ?? ''),
            'qty_warning' => (string) ($baris->qty_warning ?? ''),
            'due_date_warning' => (string) ($baris->due_date_warning ?? ''),
            'qty_due_date' => (string) ($baris->qty_due_date ?? ''),
            'plafont_sls' => (string) ($baris->plafont_sls ?? ''),
            'limit_stock' => (string) ($baris->limit_stock ?? ''),
            'auto_update_applicare' => (string) ($baris->auto_update_applicare ?? ''),
        ];
    }

    /** Toggle PPN: dimatikan → persen ikut dinolkan, sama seperti layar penerimaan. */
    public function updated(string $properti): void
    {
        if ($properti === 'form.auto_ppn_status' && $this->form['auto_ppn_status'] === '0') {
            $this->form['ppn_value'] = 0;
        }
    }

    public function simpan(): void
    {
        $this->validate(
            [
                'form.int_name' => 'required|string|max:100',
                'form.int_city' => 'nullable|string|max:100',
                'form.int_address' => 'nullable|string|max:300',
                'form.int_phone1' => 'nullable|string|max:25',
                'form.int_phone2' => 'nullable|string|max:25',
                'form.int_fax' => 'nullable|string|max:25',
                'form.int_dir' => 'nullable|string|max:100',
                'form.app_desc' => 'nullable|string|max:100',
                'form.auto_ppn_status' => 'required|in:0,1',
                'form.ppn_value' => 'nullable|numeric|min:0|max:100',
                'form.rcvupdate_cost_price' => 'required|in:0,1',
            ],
            [
                'form.int_name.required' => 'Nama rumah sakit wajib diisi.',
                'form.int_name.max' => 'Nama rumah sakit maksimal 100 karakter.',
                'form.int_address.max' => 'Alamat maksimal 300 karakter.',
                'form.int_phone1.max' => 'Telepon maksimal 25 karakter.',
                'form.int_phone2.max' => 'Telepon 2 maksimal 25 karakter.',
                'form.int_fax.max' => 'Fax maksimal 25 karakter.',
                'form.ppn_value.numeric' => 'PPN harus berupa angka.',
                'form.ppn_value.min' => 'PPN tidak boleh negatif.',
                'form.ppn_value.max' => 'PPN maksimal 100%.',
            ],
        );

        try {
            // Tabel setelan satu baris — tanpa PK, jadi update tanpa where.
            DB::table('rsmst_identitases')->update([
                'int_name' => trim($this->form['int_name']),
                'int_city' => trim($this->form['int_city']) ?: null,
                'int_address' => trim($this->form['int_address']) ?: null,
                'int_phone1' => trim($this->form['int_phone1']) ?: null,
                'int_phone2' => trim($this->form['int_phone2']) ?: null,
                'int_fax' => trim($this->form['int_fax']) ?: null,
                'int_dir' => trim($this->form['int_dir']) ?: null,
                'app_desc' => trim($this->form['app_desc']) ?: null,
                'auto_ppn_status' => $this->form['auto_ppn_status'],
                'ppn_value' => (float) ($this->form['ppn_value'] ?? 0),
                'rcvupdate_cost_price' => $this->form['rcvupdate_cost_price'],
            ]);

            $this->muatData();
            $this->dispatch('toast', type: 'success', message: 'Identitas & setelan berhasil disimpan.');
        } catch (QueryException $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menyimpan: ' . $e->getMessage());
        }
    }

    public function batal(): void
    {
        $this->resetValidation();
        $this->muatData();
        $this->dispatch('toast', type: 'info', message: 'Perubahan dibatalkan, data dimuat ulang.');
    }
};
?>

<div>
    <x-page-title
        title="Master Identitas RS"
        subtitle="Kop cetakan & setelan transaksi yang dipakai lintas modul" />

    <div class="w-full min-h-[calc(100vh-5rem)] bg-surface-soft dark:bg-gray-800">
        <div class="px-6 pt-4 pb-6 space-y-4 max-w-5xl">

            {{-- IDENTITAS & KOP CETAKAN --}}
            <x-border-form title="Identitas & Kop Cetakan">
                <p class="mb-3 text-xs text-muted dark:text-gray-400">
                    Dipakai sebagai kop di lebih dari 50 cetakan (consent, SEP, resume medis, surat keterangan).
                    Mengubahnya langsung terasa di semua cetakan berikutnya.
                </p>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-12">
                    <div class="sm:col-span-7">
                        <x-input-label value="Nama Rumah Sakit" :required="true" />
                        <x-text-input wire:model="form.int_name" maxlength="100" class="w-full mt-1"
                            :error="$errors->has('form.int_name')" />
                        <x-input-error :messages="$errors->get('form.int_name')" class="mt-1" />
                    </div>

                    <div class="sm:col-span-5">
                        <x-input-label value="Kota" />
                        <x-text-input wire:model="form.int_city" maxlength="100" class="w-full mt-1"
                            :error="$errors->has('form.int_city')" />
                        <x-input-error :messages="$errors->get('form.int_city')" class="mt-1" />
                    </div>

                    <div class="sm:col-span-12">
                        <x-input-label value="Alamat" />
                        <x-text-input wire:model="form.int_address" maxlength="300" class="w-full mt-1"
                            :error="$errors->has('form.int_address')" />
                        <x-input-error :messages="$errors->get('form.int_address')" class="mt-1" />
                    </div>

                    <div class="sm:col-span-4">
                        <x-input-label value="Telepon 1" />
                        <x-text-input wire:model="form.int_phone1" maxlength="25" class="w-full mt-1"
                            :error="$errors->has('form.int_phone1')" />
                        <x-input-error :messages="$errors->get('form.int_phone1')" class="mt-1" />
                    </div>

                    <div class="sm:col-span-4">
                        <x-input-label value="Telepon 2" />
                        <x-text-input wire:model="form.int_phone2" maxlength="25" class="w-full mt-1"
                            :error="$errors->has('form.int_phone2')" />
                        <x-input-error :messages="$errors->get('form.int_phone2')" class="mt-1" />
                    </div>

                    <div class="sm:col-span-4">
                        <x-input-label value="Fax" />
                        <x-text-input wire:model="form.int_fax" maxlength="25" class="w-full mt-1"
                            :error="$errors->has('form.int_fax')" />
                        <x-input-error :messages="$errors->get('form.int_fax')" class="mt-1" />
                    </div>

                    <div class="sm:col-span-6">
                        <x-input-label value="Nama Direktur" />
                        <x-text-input wire:model="form.int_dir" maxlength="100" class="w-full mt-1"
                            :error="$errors->has('form.int_dir')" />
                        <p class="mt-1 text-xs text-muted">Belum dipakai cetakan mana pun di aplikasi ini.</p>
                    </div>

                    <div class="sm:col-span-6">
                        <x-input-label value="Judul Aplikasi" />
                        <x-text-input wire:model="form.app_desc" maxlength="100" class="w-full mt-1"
                            :error="$errors->has('form.app_desc')" />
                        <p class="mt-1 text-xs text-muted">Warisan Oracle Dev 6i, belum dipakai di aplikasi ini.</p>
                    </div>
                </div>
            </x-border-form>

            {{-- SETELAN TRANSAKSI --}}
            <x-border-form title="Setelan Transaksi">
                <p class="mb-3 text-xs text-muted dark:text-gray-400">
                    Setelan di bawah ini mengubah perhitungan di modul lain — baca keterangannya sebelum mengganti.
                </p>

                <div class="space-y-4">
                    <div class="flex flex-wrap items-center justify-between gap-3 px-3 py-3 border rounded-xl border-hairline bg-surface-soft dark:bg-gray-800/40 dark:border-gray-700">
                        <div class="flex-1 min-w-[16rem]">
                            <div class="text-sm font-semibold text-ink dark:text-gray-100">Default PPN penerimaan</div>
                            <p class="text-xs text-muted dark:text-gray-400">
                                Menentukan posisi awal saklar "Faktur kena PPN" di penerimaan medis &amp; non-medis.
                                Dimatikan → persen ikut dinolkan.
                            </p>
                        </div>
                        <div class="flex items-center gap-3">
                            <x-toggle wire:model.live="form.auto_ppn_status" trueValue="1" falseValue="0"
                                :label="$form['auto_ppn_status'] === '1' ? 'Kena PPN' : 'Bebas PPN'" />
                            <div class="w-28">
                                <x-input-label value="PPN (%)" />
                                <x-text-input type="text" inputmode="decimal" wire:model.blur="form.ppn_value"
                                    class="w-full mt-1 text-right" :error="$errors->has('form.ppn_value')"
                                    :disabled="$form['auto_ppn_status'] === '0'"
                                    x-on:input="$el.value = $el.value.replace(/[^0-9.,]/g, '').replace(',', '.')" />
                            </div>
                        </div>
                    </div>
                    <x-input-error :messages="$errors->get('form.ppn_value')" class="-mt-2" />

                    <div class="flex flex-wrap items-center justify-between gap-3 px-3 py-3 border rounded-xl border-hairline bg-surface-soft dark:bg-gray-800/40 dark:border-gray-700">
                        <div class="flex-1 min-w-[16rem]">
                            <div class="text-sm font-semibold text-ink dark:text-gray-100">Harga pokok master ikut diperbarui</div>
                            <p class="text-xs text-muted dark:text-gray-400">
                                Nyala → harga beli baru dari faktur langsung menimpa <span class="font-mono">cost_price</span>
                                master obat. Mati → perubahan harga ditawarkan dulu ke petugas lewat kotak konfirmasi
                                di layar penerimaan.
                            </p>
                        </div>
                        <x-toggle wire:model.live="form.rcvupdate_cost_price" trueValue="1" falseValue="0"
                            :label="$form['rcvupdate_cost_price'] === '1' ? 'Otomatis' : 'Konfirmasi dulu'" />
                    </div>

                    @if (filled($nilaiLamaUpdateHpp) && !in_array($nilaiLamaUpdateHpp, ['0', '1'], true))
                        <div class="px-3 py-2 text-xs border rounded-lg text-amber-800 bg-amber-50 border-amber-200 dark:bg-amber-900/20 dark:border-amber-700 dark:text-amber-300">
                            Nilai tersimpan saat ini <span class="font-mono">{{ $nilaiLamaUpdateHpp }}</span> —
                            warisan Oracle Dev 6i. Aplikasi ini memakai konvensi <span class="font-mono">1</span>/<span class="font-mono">0</span>,
                            jadi nilai itu dibaca sebagai <strong>mati</strong> dan saklar di atas ditampilkan mati.
                            Begitu Anda menyimpan, kolomnya dinormalkan ke <span class="font-mono">1</span>/<span class="font-mono">0</span>
                            sesuai posisi saklar. Pastikan Oracle Dev 6i tidak lagi bergantung pada
                            <span class="font-mono">{{ $nilaiLamaUpdateHpp }}</span> sebelum menyimpan.
                        </div>
                    @endif
                </div>
            </x-border-form>

            {{-- WARISAN — TERKUNCI --}}
            <x-border-form title="Setelan Warisan Oracle Dev 6i (terkunci)">
                <p class="mb-3 text-xs text-muted dark:text-gray-400">
                    Kolom berikut <strong>tidak dibaca satu pun kode di aplikasi ini</strong> dan sengaja dikunci:
                    path berkas yang salah ketik bisa membuat unggah/cetak gagal diam-diam. Perubahan tetap lewat
                    Oracle Dev 6i atau permintaan ke administrator database.
                </p>

                <div class="overflow-x-auto border rounded-xl border-hairline dark:border-gray-700">
                    <table class="w-full text-sm">
                        <thead class="text-left text-muted bg-surface-soft dark:bg-gray-800 dark:text-gray-300">
                            <tr>
                                <th class="px-3 py-2 font-semibold">Kolom</th>
                                <th class="px-3 py-2 font-semibold">Nilai sekarang</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-hairline dark:divide-gray-700">
                            @foreach ($terkunci as $kolom => $nilai)
                                <tr>
                                    <td class="px-3 py-2 font-mono text-xs text-muted whitespace-nowrap">{{ $kolom }}</td>
                                    <td class="px-3 py-2 break-all text-ink dark:text-gray-200">
                                        {{ filled($nilai) ? $nilai : '—' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-border-form>

            {{-- AKSI --}}
            <div class="sticky bottom-0 z-10 flex items-center justify-between gap-3 px-4 py-3 border rounded-2xl bg-canvas border-hairline dark:bg-gray-900 dark:border-gray-700">
                <div class="text-xs text-muted dark:text-gray-400">
                    Tabel ini hanya berisi satu baris — menyimpan berarti mengubah setelan untuk seluruh aplikasi.
                </div>
                <div class="flex justify-end gap-2">
                    <x-secondary-button type="button" wire:click="batal">Batal</x-secondary-button>
                    <x-primary-button type="button" wire:click="simpan" wire:loading.attr="disabled" wire:target="simpan">
                        <span wire:loading.remove wire:target="simpan">Simpan</span>
                        <span wire:loading wire:target="simpan"><x-loading /> Menyimpan...</span>
                    </x-primary-button>
                </div>
            </div>
        </div>
    </div>
</div>
