@use('Illuminate\Support\Facades\Cache')
@use('Illuminate\Support\Facades\DB')

@props([
    'wireModelNama',                   // path NAMA ruangan — inilah nilainya, contoh: 'newPindah.keRoomDesc'
    'wireModel' => null,               // opsional: path id, ikut terisi saat nama cocok dengan master
    'wireModelJenis' => null,          // opsional: path jenis ('ruangan'|'poli') — WAJIB dipakai bila sumber='semua'
    'sumber' => 'ruangan',             // 'ruangan' (rsmst_rooms) | 'poli' (rsmst_polis) | 'semua'
    'hanya' => null,                   // daftar id yang BOLEH tampil (array/CSV) — mis. unit penunjang saja
    'kecuali' => null,                 // id yang disembunyikan (mis. ruangan pasien saat ini)
    'disabled' => false,
    'placeholder' => 'Ketik nama ruangan…',
    'inputId' => null,
    'enterAction' => null,             // ekspresi Alpine saat Enter & tak ada baris tersorot
    'error' => false,
])

{{--
    Combobox RUANGAN — ketik-saring, pilih dari master.

    Perilaku & markupnya milik <x-combobox>: YANG DIKETIK ADALAH NILAINYA. Master di sini
    bantuan ketik, bukan pagar — ruangan yang belum terdaftar boleh diketik apa adanya, dan
    ketikan petugas tak pernah dikembalikan atau dihapus komponen ini.

    `wireModel` (id) ikut terisi HANYA selama nama di kotak cocok persis dengan master, dan
    dikosongkan begitu teksnya menyimpang — jadi tak pernah ada id basi yang menunjuk ruangan
    lain dari yang tertulis. Karena id itu bonus, induk WAJIB mewajibkan NAMA-nya, bukan
    id-nya, di rules(). Kalau suatu saat butuh id yang dijamin ada (mis. memesan bed),
    itu pekerjaan lov-room, bukan komponen ini.

    Beda dari <livewire:lov.room.lov-room>: LOV itu memilih sampai tingkat BED dan hanya
    menampilkan bed KOSONG (dipakai admisi & pindah kamar — di sana bed memang harus
    dipesan). Komponen ini memilih RUANGAN saja, tanpa peduli terisi atau tidak, karena
    serah terima itu soal unit tujuan, bukan pemesanan tempat tidur. Untuk memesan bed,
    tetap pakai lov-room — jangan pakai komponen ini.

    Daftarnya kecil (±43 ruangan, 25 poli) sehingga aman dibekukan ke x-data; tetap di-cache
    supaya tidak di-query ulang tiap render Livewire.

    SUMBER TUJUAN bisa diganti lewat prop `sumber`, karena tujuan serah terima tidak selalu
    ruangan rawat: pasien UGD bisa diantar ke radiologi, lab, atau OK. Master unit penunjang
    ada di rsmst_polis, dan di sana TIDAK ADA kolom yang menandai mana yang penunjang —
    komponen ini sengaja tidak menebaknya. Pemanggil yang menentukan lewat `hanya`, mis.
    hanya='15,22,23,10' untuk Radiologi/Lab/Rontgen/OK.

    AWAS TABRAKAN ID: room_id dan poli_id hidup di master berbeda dan bisa sama persis
    (ICU room_id '1' vs POLI UMUM poli_id '1'). Karena itu saat sumber='semua', induk WAJIB
    ikut menyimpan `wireModelJenis` — tanpa itu id tersimpan jadi ambigu.
--}}
@php
    $daftarRuangan = fn() => Cache::remember(
        'lov.ruangan.combobox.ruangan',
        now()->addMinutes(30),
        fn() => DB::table('rsmst_rooms as r')
            ->leftJoin('rsmst_class as c', 'c.class_id', '=', 'r.class_id')
            ->where('r.active_status', '1')
            ->whereRaw('LENGTH(TRIM(r.room_name)) > 0')
            ->orderBy('r.room_name')
            ->get(['r.room_id', 'r.room_name', 'c.class_desc'])
            ->map(fn($row) => [
                'id' => (string) $row->room_id,
                'nama' => trim((string) $row->room_name),
                'kelas' => trim((string) ($row->class_desc ?? '')),
                'jenis' => 'ruangan',
            ])
            ->values()
            ->all(),
    );

    $daftarPoli = fn() => Cache::remember(
        'lov.ruangan.combobox.poli',
        now()->addMinutes(30),
        fn() => DB::table('rsmst_polis')
            ->whereRaw('LENGTH(TRIM(poli_desc)) > 0')
            ->orderBy('poli_desc')
            ->get(['poli_id', 'poli_desc'])
            ->map(fn($row) => [
                'id' => (string) $row->poli_id,
                'nama' => trim((string) $row->poli_desc),
                'kelas' => 'Unit',
                'jenis' => 'poli',
            ])
            ->values()
            ->all(),
    );

    $ruanganOptions = match ($sumber) {
        'poli' => $daftarPoli(),
        'semua' => array_merge($daftarRuangan(), $daftarPoli()),
        default => $daftarRuangan(),
    };

    $keSenarai = fn($nilai) => is_array($nilai)
        ? array_map('strval', $nilai)
        : (filled($nilai) ? array_map('trim', explode(',', (string) $nilai)) : []);

    $idHanya = $keSenarai($hanya);
    if ($idHanya !== []) {
        $ruanganOptions = array_values(array_filter($ruanganOptions, fn($opt) => in_array($opt['id'], $idHanya, true)));
    }

    $idKecuali = $keSenarai($kecuali);
    if ($idKecuali !== []) {
        $ruanganOptions = array_values(array_filter($ruanganOptions, fn($opt) => !in_array($opt['id'], $idKecuali, true)));
    }
@endphp

<x-combobox :wire-model="$wireModelNama" :wire-model-id="$wireModel" :wire-model-jenis="$wireModelJenis"
    :options="$ruanganOptions" :disabled="$disabled" :placeholder="$placeholder" :input-id="$inputId"
    :enter-action="$enterAction" :error="$error" :maxlength="200" judul-daftar="daftar ruangan"
    {{ $attributes }} />
