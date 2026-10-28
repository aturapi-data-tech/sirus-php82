{{-- pages/transaksi/ugd/emr-ugd/anamnesa/tabs/riwayat-penyakit-dahulu-tab.blade.php --}}
<x-border-form :title="__('Riwayat & Alergi')" :align="__('start')" :bgcolor="__('bg-surface-soft')">
    <div class="space-y-4">

        {{-- Riwayat Penyakit Dahulu --}}
        <div>
            <x-input-label value="Riwayat Penyakit Dahulu" :required="true" />
            <x-textarea wire:model.live="anamnesa.riwayatPenyakitDahulu.riwayatPenyakitDahulu"
                placeholder="Riwayat Perjalanan Penyakit" :error="$errors->has('anamnesa.riwayatPenyakitDahulu.riwayatPenyakitDahulu')" :disabled="$isFormLocked" :rows="3"
                class="w-full mt-1" />
            <x-input-error :messages="$errors->get('anamnesa.riwayatPenyakitDahulu.riwayatPenyakitDahulu')" class="mt-1" />
        </div>

        {{-- Riwayat Penyakit Keluarga (teks klinis) — kodenya diisi di Coding SATUSEHAT. Buka-tutup
             seperti Status Psikiatri: tertutup, terbuka sendiri bila sudah berisi. --}}
        <x-border-form :title="__('Riwayat Penyakit Keluarga')" :collapsible="true" padding="p-3"
            :open="filled($anamnesa['riwayatPenyakitKeluarga']['riwayatPenyakitKeluarga'] ?? null)">
            <x-textarea wire:model.live="anamnesa.riwayatPenyakitKeluarga.riwayatPenyakitKeluarga"
                placeholder="Mis. Ibu menderita diabetes, ayah hipertensi" :disabled="$isFormLocked" :rows="2" class="w-full" />
            @if (in_array('riwayatPenyakitKeluarga', $dariKunjunganSebelumnya ?? [], true))
                <p class="mt-1 text-xs text-warning-deep dark:text-amber-200">Terisi dari kunjungan sebelumnya — periksa dan ubah bila sudah berbeda.</p>
            @endif
        </x-border-form>

        {{-- Ada alergi? — default "Tidak" (SNOMED 716186003 diisi di server, bukan lewat LOV
             zat: 716186003 itu konsep *situation*, bukan zat, jadi ditolak substance-code). --}}
        <div>
            <x-input-label value="Ada Alergi?" :required="false" />
            <div class="flex gap-4 mt-2">
                @foreach (['Ya', 'Tidak'] as $opt)
                    <x-radio-button :label="$opt" :value="$opt" name="adaAlergiUgd"
                        wire:model.live="anamnesa.alergi.adaAlergi" :disabled="$isFormLocked" />
                @endforeach
            </div>
        </div>

        @php $adaAlergi = ($anamnesa['alergi']['adaAlergi'] ?? 'Tidak') === 'Ya'; @endphp

        @if ($adaAlergi)
            {{-- Alergi (teks) — hanya saat "Ya" --}}
            <div>
                <x-input-label value="Alergi" :required="false" />
                <x-textarea wire:model.live="anamnesa.alergi.alergi"
                    placeholder="Jenis Alergi — Makanan / Obat / Udara" :error="$errors->has('anamnesa.alergi.alergi')" :disabled="$isFormLocked"
                    :rows="3" class="w-full mt-1" />
                <x-input-error :messages="$errors->get('anamnesa.alergi.alergi')" class="mt-1" />
            </div>

            <p class="text-xs text-muted dark:text-gray-400">
                Kode zat penyebab alergi (KFA / SNOMED) diisi sesudah pelayanan lewat menu
                <span class="font-semibold">Coding SATUSEHAT</span>.
            </p>
        @else
            <div class="text-xs text-muted dark:text-gray-400">
                Terekam sebagai <span class="font-semibold text-ink dark:text-gray-100">Tidak ada alergi</span>
                <span class="font-mono text-[10px] text-muted-soft">716186003</span> untuk Satu Sehat.
            </div>
        @endif

    </div>
</x-border-form>
