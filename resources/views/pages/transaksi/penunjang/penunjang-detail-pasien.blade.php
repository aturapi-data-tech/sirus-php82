{{--
    Detail pasien per-sumber (RI/RJ/UGD) untuk Tambah Pemeriksaan Lab & Radiologi.
    Dipakai di baris tabel ($pasien = $identity) & kartu terpilih ($pasien = $selectedPatient).
    Props: $pasien (array), $source ('RI'|'RJ'|'UGD').
--}}
@if ($source === 'RI')
    <div class="text-sm font-semibold text-blue-600 dark:text-blue-400">{{ $pasien['bangsal_name'] ?? '-' }}</div>
    <div class="text-sm text-body dark:text-gray-300">
        {{ $pasien['room_name'] ?? '-' }}@if (!empty($pasien['bed_no'])) · Bed {{ $pasien['bed_no'] }}@endif
    </div>
    @if (!empty($pasien['leveling_dokter_list']))
        <div class="mt-0.5">
            <span class="text-xs text-muted-soft">DPJP:</span>
            @foreach ($pasien['leveling_dokter_list'] as $dokterLeveling)
                <div class="text-sm text-body dark:text-gray-200">{{ $dokterLeveling['drName'] }}@if (!empty($dokterLeveling['levelDokter'])) <span class="text-xs text-muted">({{ $dokterLeveling['levelDokter'] === 'RawatGabung' ? 'Rawat Gabung' : $dokterLeveling['levelDokter'] }})</span>@endif</div>
            @endforeach
        </div>
    @endif
    <div class="text-xs italic text-muted dark:text-gray-400">Penerima: {{ $pasien['penerima_name'] ?? '-' }}</div>
@elseif ($source === 'RJ')
    <div class="text-sm font-semibold text-blue-600 dark:text-blue-400">{{ $pasien['poli_desc'] ?? '-' }}</div>
    <div class="text-sm text-body dark:text-gray-300">Dokter: {{ $pasien['dokter_name'] ?? '-' }}</div>
    @if (!empty($pasien['no_antrian']))
        <div class="text-xs text-muted dark:text-gray-400">Antrian: {{ $pasien['no_antrian'] }}</div>
    @endif
@else
    {{-- UGD --}}
    <div class="text-sm font-semibold text-blue-600 dark:text-blue-400">Dokter: {{ $pasien['dokter_name'] ?? '-' }}</div>
    <div class="text-sm text-body dark:text-gray-300">Cara Masuk: {{ $pasien['entry_desc'] ?? '-' }}</div>
    @if (!empty($pasien['no_antrian']))
        <div class="text-xs text-muted dark:text-gray-400">Antrian: {{ $pasien['no_antrian'] }}</div>
    @endif
@endif
<div class="flex flex-wrap items-center gap-2 mt-1">
    <x-badge variant="gray">{{ $pasien['klaim_desc'] ?? '-' }}</x-badge>
    <x-badge variant="info">{{ $source === 'RI' ? 'Dirawat' : 'Aktif' }}</x-badge>
    @if (!empty($pasien['masuk_date']))
        <span class="text-xs text-muted-soft">Masuk: {{ $pasien['masuk_date'] }}</span>
    @endif
</div>
