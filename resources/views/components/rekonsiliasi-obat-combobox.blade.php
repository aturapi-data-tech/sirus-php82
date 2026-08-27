@use('Illuminate\Support\Facades\Cache')
@use('Illuminate\Support\Facades\DB')

@props([
    'wireModel',                       // contoh: 'formEntryRekonsiliasi.namaObat'
    'disabled' => false,
    'placeholder' => 'Nama obat — pilih dari master atau ketik bebas',
    'inputId' => null,                 // id input — dipakai parent untuk focus via document.getElementById
    'enterAction' => null,             // ekspresi Alpine yg dijalankan saat Enter & dropdown tertutup
    'maxlength' => 200,
    'error' => false,
])

{{--
    Combobox NAMA OBAT untuk REKONSILIASI OBAT (EMR UGD & RI) — pilih dari master
    ATAU ketik bebas. Perilaku & markupnya milik <x-combobox> (mode teks bebas).

    Beda dari <livewire:lov.product.lov-product>: LOV itu MEWAJIBKAN obat ada di
    master (mengunci pilihan + mengembalikan product_id). Di sini justru sebaliknya —
    yang didata adalah obat BAWAAN PASIEN, sering dari luar RS dan memang tidak ada
    di immst_products. Master di sini bantuan ketik, bukan pagar. Untuk pemilihan
    obat yang HARUS ada di master (e-resep, administrasi obat, gudang) tetap pakai
    lov-product — jangan pakai komponen ini.

    KONSEKUENSI YANG DISENGAJA: nilai tersimpan TEKS saja, tanpa product_id — termasuk
    saat dipilih dari master. Kalau kelak butuh tautan ke master/KFA (mis.
    MedicationStatement SATUSEHAT), pasang `wire-model-id` seperti x-ruangan-combobox —
    <x-combobox> sudah menyediakannya.

    Daftar di-cache: ~1.500 nama obat ini kalau tidak di-cache akan di-query ulang
    SETIAP render Livewire (tiap tambah/hapus baris), padahal master jarang berubah.
--}}
@php
    $obatOptions = Cache::remember(
        'lov.rekonsiliasi-obat.combobox.nama',
        now()->addMinutes(30),
        fn() => DB::table('immst_products')
            ->where('active_status', '1')
            ->whereRaw('LENGTH(TRIM(product_name)) > 0')
            ->orderBy('product_name')
            ->pluck('product_name')
            ->unique()
            ->values()
            ->all(),
    );
@endphp

<x-combobox :wire-model="$wireModel" :options="$obatOptions" :disabled="$disabled" :placeholder="$placeholder"
    :input-id="$inputId" :enter-action="$enterAction" :maxlength="$maxlength" :error="$error"
    judul-daftar="daftar obat" {{ $attributes }} />
