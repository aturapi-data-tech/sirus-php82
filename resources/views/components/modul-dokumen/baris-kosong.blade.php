@props([
    // Jumlah kolom tabel (colspan).
    'kolom' => 1,
    'pesan' => 'Belum ada data tersimpan',
])

{{-- Baris "belum ada data" tabel daftar modul dokumen — dipakai di @empty. --}}
<tbody>
    <tr>
        <td colspan="{{ $kolom }}" class="px-6 py-12">
            <div class="flex flex-col items-center justify-center gap-3">
                <svg class="w-12 h-12 text-muted-soft" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                        d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                </svg>
                <p class="text-base font-medium text-muted dark:text-gray-400">{{ $pesan }}</p>
            </div>
        </td>
    </tr>
</tbody>
