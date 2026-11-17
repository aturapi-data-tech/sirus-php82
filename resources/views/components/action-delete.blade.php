{{--
    Tombol Hapus standar untuk baris tabel (master/list).
    confirm-button (danger, modal konfirmasi) + ikon sampah. Label default "Hapus".

    Pemakaian:
        <x-action-delete :action="'requestDelete(\'' . $row->id . '\')'"
                         title="Hapus Poli" message="Yakin hapus data poli {{ $row->desc }}?" />

    Props diteruskan ke <x-confirm-button>:
      - action (wajib)  : ekspresi method Livewire, mis. "delete('10')"
      - title           : judul modal konfirmasi
      - message         : pesan konfirmasi
      - confirmText / cancelText
--}}
@props([
    'action',
    'title' => 'Hapus Data',
    'message' => 'Apakah Anda yakin ingin menghapus data ini?',
    'confirmText' => 'Ya, hapus',
    'cancelText' => 'Batal',
])

<x-confirm-button variant="danger" :action="$action" :title="$title" :message="$message"
    :confirmText="$confirmText" :cancelText="$cancelText"
    {{ $attributes->merge(['class' => 'px-2.5 py-1.5 text-sm']) }}>
    <x-ikon nama="hapus" />
    {{ $slot->isEmpty() ? 'Hapus' : $slot }}
</x-confirm-button>
