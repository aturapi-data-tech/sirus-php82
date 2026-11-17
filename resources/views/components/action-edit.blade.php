{{--
    Tombol Edit standar untuk baris tabel (master/list).
    secondary-button + ikon pensil. Label default "Edit" (bisa di-override via slot).

    Pemakaian:
        <x-action-edit wire:click="openEdit('{{ $row->id }}')" />
        <x-action-edit wire:click="openEdit('{{ $row->id }}')">Ubah</x-action-edit>
--}}
<x-secondary-button type="button" {{ $attributes->merge(['class' => 'px-2.5 py-1.5 text-sm']) }}>
    <x-ikon nama="ubah" />
    {{ $slot->isEmpty() ? 'Edit' : $slot }}
</x-secondary-button>
