{{-- Bagian A & B formulir DT-01. Partial markup murni; state ada di
     ⚡pelaporan-downtime-actions.blade.php. Import TIDAK diwarisi dari induk,
     jadi @use di bawah wajib ada. --}}
@use('App\Support\Options\PelaporanDowntimeOptions')

<x-border-form :title="__('A. Data Kejadian')" :align="__('start')" :bgcolor="__('bg-canvas')">
    <div class="space-y-4">

        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
            <div>
                <x-input-label value="Jenis Waktu Henti" class="mb-1" />
                <x-select-input wire:model.live="kejadian.jenis" class="w-full">
                    @foreach (PelaporanDowntimeOptions::JENIS as $kunci => $label)
                        <option value="{{ $kunci }}">{{ $label }}</option>
                    @endforeach
                </x-select-input>
                <x-input-error :messages="$errors->get('kejadian.jenis')" class="mt-1" />
            </div>
            <div>
                <x-input-label value="No. Log (opsional)" class="mb-1" />
                <x-text-input wire:model.live="kejadian.noLog"
                    :error="$errors->has('kejadian.noLog')"
                    placeholder="cth: DT-2026-014" class="w-full" />
                <x-input-error :messages="$errors->get('kejadian.noLog')" class="mt-1" />
                <p class="mt-1 text-xs text-muted dark:text-gray-400">
                    Dikosongkan pun tetap terlacak — nomor laporan dipakai sebagai gantinya.
                </p>
            </div>
            <div>
                <x-input-label value="Lingkup Gangguan" class="mb-1" />
                <x-select-input wire:model.live="kejadian.lingkup" class="w-full">
                    @foreach (PelaporanDowntimeOptions::LINGKUP as $kunci => $label)
                        <option value="{{ $kunci }}">{{ $label }}</option>
                    @endforeach
                </x-select-input>
                <x-input-error :messages="$errors->get('kejadian.lingkup')" class="mt-1" />
            </div>
        </div>

        {{-- Satu field per ujung, bertanggal PENUH — bukan tanggal & jam terpisah.
             Itu yang membuat gangguan lewat tengah malam terhitung durasinya. --}}
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <div>
                <x-input-label value="Mulai Down Time" class="mb-1" />
                <div class="flex items-center gap-2">
                    <x-text-input wire:model.live="kejadian.waktuMulai"
                        :error="$errors->has('kejadian.waktuMulai')"
                        placeholder="dd/mm/yyyy HH:MM:SS" class="w-full" />
                    <x-now-button wire:click="setMulaiSekarang" title="Set ke tanggal & jam sekarang" />
                </div>
                <x-input-error :messages="$errors->get('kejadian.waktuMulai')" class="mt-1" />
            </div>
            <div>
                <x-input-label value="Pulih (boleh kosong)" class="mb-1" />
                <div class="flex items-center gap-2">
                    <x-text-input wire:model.live="kejadian.waktuPulih"
                        :error="$errors->has('kejadian.waktuPulih')"
                        placeholder="dd/mm/yyyy HH:MM:SS" class="w-full" />
                    <x-now-button wire:click="setPulihSekarang" title="Set ke tanggal & jam sekarang" />
                </div>
                <x-input-error :messages="$errors->get('kejadian.waktuPulih')" class="mt-1" />
                <p class="mt-1 text-xs text-muted dark:text-gray-400">
                    Dikosongkan = layanan belum dinyatakan pulih. Barisnya disorot di daftar.
                </p>
            </div>
        </div>

        <div>
            {{-- Dihitung, tak bisa diketik: angka di laporan yang ditandatangani
                 harus selalu cocok dengan waktu mulai & pulihnya.

                 "Modul / Layanan Terdampak" TIDAK ada di sini — isinya persis
                 daftar unit yang dicentang di Bagian D, dan mengetiknya dua kali
                 cuma membuka peluang keduanya berbeda. Cetakan menurunkannya
                 sendiri lewat PelaporanDowntimeOptions::modulTerdampakDari(). --}}
            <x-input-label value="Durasi (dihitung)" class="mb-1" />
            <div class="flex items-center h-10">
                @if (PelaporanDowntimeOptions::belumPulih($kejadian))
                    <x-badge variant="warning">Belum dinyatakan pulih</x-badge>
                @elseif (filled($kejadian['durasi']))
                    <x-badge variant="info">{{ $kejadian['durasi'] }}</x-badge>
                @else
                    <x-badge variant="danger">Waktu pulih mendahului waktu mulai</x-badge>
                @endif
            </div>
        </div>
    </div>
</x-border-form>

<x-border-form :title="__('B. Pelaporan Awal')" :align="__('start')" :bgcolor="__('bg-canvas')">
    <div class="space-y-4">
        {{-- Satu field, bukan "Dilaporkan oleh" + "Unit Pelapor" terpisah:
             nama dan unitnya satu keterangan, dan pemilih PPA sudah menampilkan
             unitnya sendiri. --}}
        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
            <div>
                <x-input-label value="Dilaporkan oleh (nama / unit)" class="mb-1" />
                <x-ppa-combobox wireModel="pelaporan.dilaporkanOleh"
                    placeholder="Pilih dari daftar atau ketik nama & unitnya" />
                <x-input-error :messages="$errors->get('pelaporan.dilaporkanOleh')" class="mt-1" />
            </div>
            <div>
                <x-input-label value="Jam Laporan Diterima" class="mb-1" />
                <div class="flex items-center gap-2">
                    <x-text-input wire:model.live="pelaporan.jamLaporanDiterima"
                        :error="$errors->has('pelaporan.jamLaporanDiterima')" placeholder="HH:MM" class="w-full" />
                    <x-now-button wire:click="setJamLaporanSekarang"
                        title="Set ke jam sekarang" />
                </div>
                <x-input-error :messages="$errors->get('pelaporan.jamLaporanDiterima')" class="mt-1" />
            </div>
            <div>
                <x-input-label value="Media Laporan" class="mb-1" />
                <x-select-input wire:model.live="pelaporan.mediaLaporan" class="w-full">
                    @foreach (PelaporanDowntimeOptions::MEDIA_LAPORAN as $kunci => $label)
                        <option value="{{ $kunci }}">{{ $label }}</option>
                    @endforeach
                </x-select-input>
                <x-input-error :messages="$errors->get('pelaporan.mediaLaporan')" class="mt-1" />
            </div>
        </div>

        <div>
            <x-input-label value="Gejala / Keluhan Awal" class="mb-1" />
            <x-textarea wire:model.live="pelaporan.gejalaAwal" rows="2"
                :error="$errors->has('pelaporan.gejalaAwal')"
                placeholder="cth: aplikasi tidak bisa dibuka, muncul pesan koneksi database gagal" class="w-full" />
            <x-input-error :messages="$errors->get('pelaporan.gejalaAwal')" class="mt-1" />
        </div>
    </div>
</x-border-form>
