@use('Illuminate\Support\Facades\Cache')
@use('Illuminate\Support\Facades\DB')

@props([
    'wireModel',                       // path id ruangan, contoh: 'formHandover.ruanganTujuanId'
    'wireModelNama' => null,           // opsional: path nama tujuan, biar induk tak perlu lookup ulang
    'wireModelJenis' => null,           // opsional: path jenis ('ruangan'|'poli') — WAJIB dipakai bila sumber='semua'
    'nilai' => null,                   // id yang sedang tersimpan — untuk isi awal kotak
    'sumber' => 'ruangan',             // 'ruangan' (rsmst_rooms) | 'poli' (rsmst_polis) | 'semua'
    'hanya' => null,                   // daftar id yang BOLEH tampil (array/CSV) — mis. unit penunjang saja
    'kecuali' => null,                 // id yang disembunyikan (mis. ruangan pasien saat ini)
    'disabled' => false,
    'placeholder' => 'Ketik nama ruangan…',
    'inputId' => null,
    'enterAction' => null,             // ekspresi Alpine saat Enter & dropdown tertutup
    'error' => false,
])

{{--
    Combobox RUANGAN — ketik-saring, pilih dari master.

    Perilakunya disalin dari x-rekonsiliasi-obat-combobox (filter, navigasi keyboard,
    tombol clear/chevron, wire:ignore di dropdown), TAPI ada satu perbedaan pokok yang
    disengaja: di sini pilihan DIKUNCI ke master dan yang disimpan adalah room_id.

    Kenapa beda: combobox obat mendata obat BAWAAN PASIEN yang sering memang tak ada di
    master, jadi teks bebas itu fiturnya. Ruangan tujuan sebaliknya — menyerahkan pasien
    ke ruangan yang tak ada di master itu tak punya arti. Ketikan yang tak cocok dengan
    satu pun ruangan dikembalikan saat blur, dan id dikosongkan begitu teksnya diubah,
    supaya tak pernah ada id basi yang menunjuk ruangan lain dari yang tertulis.

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

    // Isi awal kotak: nama ruangan yang id-nya sedang tersimpan.
    $idTerpilih = filled($nilai) ? (string) $nilai : '';
    $namaAwal = '';
    foreach ($ruanganOptions as $opt) {
        if ($opt['id'] === $idTerpilih) {
            $namaAwal = $opt['nama'];
            break;
        }
    }

    // Border DIPISAH dari baseClass: kalau border-gray ikut baseClass lalu border-red
    // ditempel lewat merge, yang menang ditentukan urutan CSS — penanda error bisa
    // tidak kelihatan sama sekali.
    $borderClass = $error
        ? 'border-red-500 dark:border-red-500 focus:border-red-500 focus:ring-red-500'
        : 'border-gray-300 dark:border-gray-700 focus:border-brand-lime focus:ring-brand-lime';
@endphp

<div class="relative w-full"
    x-data="{
        allOptions: @js($ruanganOptions),
        filtered: [],
        open: false,
        highlighted: -1,
        idModel: @js($wireModel),
        namaModel: @js($wireModelNama),
        jenisModel: @js($wireModelJenis),
        namaTerpilih: @js($namaAwal),

        init() { this.filter(this.$refs.cbInput ? this.$refs.cbInput.value : ''); },

        teks(opt) { return opt.kelas ? opt.nama + ' — ' + opt.kelas : opt.nama; },

        filter(q) {
            const lq = (q ?? '').toString().toLowerCase().trim();
            this.filtered = lq.length === 0
                ? this.allOptions.slice(0, 50)
                : this.allOptions.filter(o =>
                    (o.nama ?? '').toLowerCase().includes(lq)
                    || (o.id ?? '').toLowerCase().includes(lq)
                    || (o.kelas ?? '').toLowerCase().includes(lq)
                  ).slice(0, 50);
        },

        openDropdown() {
            this.filter(this.$refs.cbInput ? this.$refs.cbInput.value : '');
            this.open = this.filtered.length > 0;
            this.highlighted = -1;
        },

        onInput() {
            const cur = this.$refs.cbInput ? this.$refs.cbInput.value : '';
            this.filter(cur);
            this.open = this.filtered.length > 0;
            this.highlighted = -1;
            // Teks diubah tangan → pilihan sebelumnya batal. Tanpa ini, id lama tetap
            // tersimpan sementara kotaknya sudah menampilkan ruangan lain.
            if (cur !== this.namaTerpilih) { this.lepasPilihan(); }
        },

        lepasPilihan() {
            this.namaTerpilih = '';
            this.$wire.set(this.idModel, '', false);
            if (this.namaModel) { this.$wire.set(this.namaModel, '', false); }
            if (this.jenisModel) { this.$wire.set(this.jenisModel, '', false); }
        },

        clearValue() {
            this.lepasPilihan();
            if (this.$refs.cbInput) { this.$refs.cbInput.value = ''; }
            this.filter('');
            this.open = false;
            this.highlighted = -1;
            this.$nextTick(() => this.$refs.cbInput && this.$refs.cbInput.focus());
        },

        pick(opt) {
            // defer (live=false): tak perlu AJAX seketika, cukup ikut permintaan berikutnya.
            this.namaTerpilih = opt.nama;
            this.$wire.set(this.idModel, opt.id, false);
            if (this.namaModel) { this.$wire.set(this.namaModel, opt.nama, false); }
            if (this.jenisModel) { this.$wire.set(this.jenisModel, opt.jenis, false); }
            if (this.$refs.cbInput) { this.$refs.cbInput.value = opt.nama; }
            this.open = false;
            this.highlighted = -1;
            this.$nextTick(() => this.$refs.cbInput && this.$refs.cbInput.focus());
        },

        // Dikunci ke master: ketikan yang tak cocok persis dengan satu ruangan pun
        // dikembalikan ke pilihan terakhir yang sah (atau dikosongkan).
        rapikan() {
            if (!this.$refs.cbInput) return;
            const cur = this.$refs.cbInput.value.trim().toLowerCase();
            if (cur === '') { this.lepasPilihan(); return; }
            const cocok = this.allOptions.find(o => (o.nama ?? '').toLowerCase() === cur);
            if (cocok) { this.pick(cocok); return; }
            this.$refs.cbInput.value = this.namaTerpilih;
            if (this.namaTerpilih === '') { this.lepasPilihan(); }
        },

        moveDown() {
            if (!this.open) {
                this.openDropdown();
                if (!this.open) return;
                this.highlighted = 0;
                return;
            }
            if (this.filtered.length === 0) return;
            this.highlighted = this.highlighted < 0 ? 0 : (this.highlighted + 1) % this.filtered.length;
        },

        moveUp() {
            if (!this.open) {
                this.openDropdown();
                if (!this.open) return;
                this.highlighted = this.filtered.length - 1;
                return;
            }
            if (this.filtered.length === 0) return;
            this.highlighted = this.highlighted <= 0 ? this.filtered.length - 1 : this.highlighted - 1;
        },
    }"
    x-on:click.outside="open = false; rapikan()">

    <div class="relative">
        <input
            type="text"
            autocomplete="off"
            @disabled($disabled)
            value="{{ $namaAwal }}"
            placeholder="{{ $placeholder }}"
            @if($inputId) id="{{ $inputId }}" @endif
            x-ref="cbInput"
            x-on:input="onInput()"
            x-on:blur="rapikan()"
            x-on:keydown.escape="open = false; rapikan()"
            x-on:keydown.arrow-down.prevent="moveDown()"
            x-on:keydown.arrow-up.prevent="moveUp()"
            @if($enterAction)
                x-on:keydown.enter.prevent="
                    if (open && highlighted >= 0 && filtered[highlighted]) {
                        pick(filtered[highlighted]);
                    } else {
                        open = false;
                        rapikan();
                        {{ $enterAction }};
                    }
                "
            @else
                x-on:keydown.enter.prevent="
                    if (open && highlighted >= 0 && filtered[highlighted]) {
                        pick(filtered[highlighted]);
                    } else {
                        open = false;
                        rapikan();
                    }
                "
            @endif
            {{ $attributes->merge([
                'class' => trim($borderClass . ' dark:bg-gray-900 dark:text-gray-100 rounded-md shadow-sm disabled:opacity-90 disabled:bg-gray-100 disabled:cursor-not-allowed w-full ' . ($disabled ? '' : 'pr-16')),
            ]) }} />

        {{-- Tombol Clear (×) — hanya muncul kalau ada nilai --}}
        @unless($disabled)
            <button type="button"
                x-show="$refs.cbInput && $refs.cbInput.value !== ''"
                x-on:click.prevent="clearValue()"
                title="Kosongkan"
                class="absolute top-1/2 right-8 -translate-y-1/2 inline-flex items-center justify-center w-6 h-6 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 rounded transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>

            {{-- Chevron untuk buka/tutup dropdown manual --}}
            <button type="button"
                x-on:click.prevent="open ? (open = false) : openDropdown()"
                :title="open ? 'Tutup daftar' : 'Lihat daftar ruangan'"
                class="absolute top-1/2 right-2 -translate-y-1/2 inline-flex items-center justify-center w-6 h-6 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 rounded transition">
                <svg class="w-4 h-4 transition-transform" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
            </button>
        @endunless
    </div>

    {{--
        wire:ignore WAJIB di sini. Isi dropdown 100% dibangun Alpine dari `allOptions`
        (sudah dibekukan ke x-data saat render) — server tidak pernah perlu memperbaruinya.
        Tanpa ini, morph Livewire ikut mengaduk <template x-for>; kalau <li>-nya sampai
        tercabut ke DOM hidup, `opt` kehilangan scope x-for dan Alpine melempar
        "opt is not defined" DI TENGAH flushHandlers — sisa directive pada batch initTree
        yang sama ikut batal dijalankan.
    --}}
    <div wire:ignore
         x-show="open && filtered.length > 0"
         x-transition.opacity.duration.100ms
         class="absolute z-50 w-full mt-2 overflow-hidden bg-white border border-gray-200 shadow-lg rounded-xl dark:bg-gray-900 dark:border-gray-700">
        <ul class="overflow-y-auto divide-y divide-gray-100 max-h-72 dark:divide-gray-800">
            <template x-for="(opt, idx) in filtered" :key="opt.jenis + '-' + opt.id">
                <li :class="idx === highlighted
                        ? 'bg-brand-lime/15 dark:bg-brand-lime/25 ring-1 ring-brand-lime/30'
                        : 'hover:bg-brand-lime/10 dark:hover:bg-brand-lime/20'"
                    class="w-full px-4 py-3 text-left text-gray-800 dark:text-gray-100 rounded-lg transition-colors duration-150 cursor-pointer"
                    x-on:mousedown.prevent="pick(opt)"
                    x-on:mouseenter="highlighted = idx"
                    x-text="teks(opt)"></li>
            </template>
        </ul>
    </div>
</div>
