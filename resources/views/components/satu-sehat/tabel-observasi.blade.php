@props([
    // Judul tabel.
    'judul' => '',
    // Baris dari App\Support\Terminologi\TampilanObservasiSatuSehat: [['waktu', 'isi', 'kode', 'status'], ...].
    'baris' => [],
    // Pesan bila kosong.
    'kosong' => 'Belum ada data',
])

{{-- Tabel hanya-baca layar Coding SATUSEHAT: data observasi yang dikirim OTOMATIS oleh kartu SATUSEHAT,
     dengan kode yang dikirim. Baris yang dilewati sender ditandai sebabnya (status kosong). --}}
<div>
    <p class="mb-1 text-sm font-semibold text-ink dark:text-gray-100">{{ $judul }}</p>
    <x-modul-dokumen.tabel-daftar :kolom="['Waktu' => 'w-40', 'Isi', 'Kode SATUSEHAT', 'Status' => 'w-28']">
        @forelse ($baris as $item)
            <tbody class="border-b border-hairline dark:border-gray-700">
                <tr class="align-top">
                    <td class="px-4 py-2 font-mono text-xs whitespace-nowrap text-muted dark:text-gray-400">{{ $item['waktu'] }}</td>
                    <td class="px-4 py-2 text-body dark:text-gray-300">{{ $item['isi'] }}</td>
                    <td class="px-4 py-2 font-mono text-xs {{ $item['status'] === '' ? 'text-warning-deep dark:text-amber-200' : 'text-brand-green dark:text-brand-lime' }}">{{ $item['kode'] }}</td>
                    <td class="px-4 py-2">
                        @if ($item['status'] === 'terkirim')
                            <x-badge variant="success">terkirim</x-badge>
                        @elseif ($item['status'] === 'akan dikirim')
                            <x-badge variant="brand">akan dikirim</x-badge>
                        @else
                            <x-badge variant="warning">dilewati</x-badge>
                        @endif
                    </td>
                </tr>
            </tbody>
        @empty
            <x-modul-dokumen.baris-kosong :kolom="4" :pesan="$kosong" />
        @endforelse
    </x-modul-dokumen.tabel-daftar>
</div>
