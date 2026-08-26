{{-- Bagian E formulir DT-01 + tiga tanda tangan berantai. Partial markup murni. --}}

<x-border-form :title="__('E. Evaluasi & Rencana Tindak Lanjut')" :align="__('start')" :bgcolor="__('bg-canvas')">
    <div class="space-y-4">

        <div>
            <x-input-label value="Analisis Akar Masalah" class="mb-1" />
            <x-textarea wire:model.live="evaluasi.akarMasalah" rows="3"
                :error="$errors->has('evaluasi.akarMasalah')"
                placeholder="cth: kapasitas UPS tidak cukup menutup durasi padam listrik PLN" class="w-full" />
            <x-input-error :messages="$errors->get('evaluasi.akarMasalah')" class="mt-1" />
        </div>

        <div>
            <x-input-label value="Rencana Tindak Lanjut" class="mb-1" />
            <x-textarea wire:model.live="evaluasi.rencanaTindakLanjut" rows="3"
                :error="$errors->has('evaluasi.rencanaTindakLanjut')"
                placeholder="Satu rencana per baris" class="w-full" />
            <x-input-error :messages="$errors->get('evaluasi.rencanaTindakLanjut')" class="mt-1" />
        </div>

        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <div>
                <x-input-label value="Penanggung Jawab" class="mb-1" />
                <x-ppa-combobox wireModel="evaluasi.penanggungJawab"
                    placeholder="Pilih dari daftar atau ketik nama" />
                <x-input-error :messages="$errors->get('evaluasi.penanggungJawab')" class="mt-1" />
            </div>
            <div>
                <x-input-label value="Target Selesai" class="mb-1" />
                <x-text-input wire:model.live="evaluasi.targetSelesai"
                    :error="$errors->has('evaluasi.targetSelesai')"
                    placeholder="dd/mm/yyyy" class="w-full" />
                <x-input-error :messages="$errors->get('evaluasi.targetSelesai')" class="mt-1" />
            </div>
        </div>

        <div>
            {{-- Pertanyaan pertama auditor setelah down time: datanya aman atau tidak. --}}
            <x-input-label value="Status Pencadangan (Backup) Terakhir" class="mb-1" />
            <x-text-input wire:model.live="evaluasi.statusBackup"
                :error="$errors->has('evaluasi.statusBackup')"
                placeholder="cth: backup harian 11/08/2026 23:00 berhasil, terverifikasi" class="w-full" />
            <x-input-error :messages="$errors->get('evaluasi.statusBackup')" class="mt-1" />
        </div>
    </div>
</x-border-form>
