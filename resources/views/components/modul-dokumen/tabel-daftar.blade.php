@props([
    // Judul kolom, urut kiri→kanan. Nilai berindeks angka = label; berkunci teks = label => kelas tambahan
    // (mis. 'Status' => 'text-center', 'Aksi' => 'text-center w-64'). Label '' = kolom panah rincian (w-8).
    'kolom' => [],
])

{{-- Tabel layar daftar modul dokumen (docs/modul-dokumen-ri-pattern.md §2a "Tabel daftar"):
     kartu x-border-form p-0 → tabel min-w-full text-sm → thead sticky. Slot bawaan = isi tabel
     (<tbody> per entri + @empty baris-kosong). Kelas tambahan kartu (mis. mx-4 mt-4) lewat class. --}}
<x-border-form padding="p-0" {{ $attributes }}>
    @isset($atas)
        {{-- Slot "atas": baris tombol di atas tabel, di dalam kartu (Catatan Terapi Neonatal: Cetak Catatan). --}}
        <div class="flex items-center justify-between gap-2 px-4 pt-3">{{ $atas }}</div>
    @endisset
    <div class="overflow-x-auto rounded-2xl">
        <table class="min-w-full text-sm">
            <thead class="sticky top-0 z-10 bg-surface-card dark:bg-gray-800">
                <tr class="text-xs font-semibold tracking-wide text-left uppercase text-muted dark:text-gray-300">
                    @foreach ($kolom as $kunci => $nilai)
                        @php
                            [$label, $tambahan] = is_int($kunci) ? [$nilai, ''] : [$kunci, $nilai];
                            $jarak = $label === '' ? 'w-8 px-2' : 'px-4';
                        @endphp
                        <th class="whitespace-nowrap {{ $jarak }} py-3 border-b border-hairline dark:border-gray-700 bg-surface-card dark:bg-gray-800 {{ $tambahan }}">{{ $label }}</th>
                    @endforeach
                </tr>
            </thead>
            {{ $slot }}
        </table>
    </div>
</x-border-form>
