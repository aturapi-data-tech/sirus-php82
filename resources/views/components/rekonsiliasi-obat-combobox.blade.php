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
    Combobox Nama Obat untuk REKONSILIASI OBAT (EMR UGD & RI) — pilih dari master
    ATAU ketik bebas.

    BERDIRI SENDIRI, bukan pembungkus x-catatan-signa-combobox. Perilakunya memang
    disalin dari sana (filter, navigasi keyboard, tombol clear/chevron, wire:ignore
    di dropdown), tapi salinannya dipisah supaya dua hal ini tidak saling mengunci:
    combobox e-resep dan combobox rekonsiliasi bebas berkembang sendiri-sendiri —
    mengubah salah satu tidak berisiko merembet ke modul satunya.

    Beda dari <livewire:lov.product.lov-product>: LOV itu MEWAJIBKAN obat ada di
    master (mengunci pilihan + mengembalikan product_id). Di sini justru sebaliknya —
    yang didata adalah obat BAWAAN PASIEN, sering dari luar RS dan memang tidak ada
    di immst_products. Master di sini bantuan ketik, bukan pagar. Untuk pemilihan
    obat yang HARUS ada di master (e-resep, administrasi obat, gudang) tetap pakai
    lov-product — jangan pakai komponen ini.

    KONSEKUENSI YANG DISENGAJA: nilai tersimpan TEKS saja, tanpa product_id — termasuk
    saat dipilih dari master. Kalau kelak butuh tautan ke master/KFA (mis.
    MedicationStatement SATUSEHAT), itu perlu komponen sendiri.

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

    // Border DIPISAH dari baseClass: kalau border-gray ikut baseClass lalu
    // border-red ditempel lewat merge, yang menang ditentukan urutan CSS —
    // penanda error bisa tidak kelihatan sama sekali.
    $borderClass = $error
        ? 'border-red-500 dark:border-red-500 focus:border-red-500 focus:ring-red-500'
        : 'border-gray-300 dark:border-gray-700 focus:border-brand-lime focus:ring-brand-lime';
@endphp

<div class="relative w-full"
    x-data="{
        allOptions: @js($obatOptions),
        filtered: [],
        open: false,
        highlighted: -1,
        wireModelName: @js($wireModel),

        init() { this.filter(this.$refs.cbInput ? this.$refs.cbInput.value : ''); },

        filter(q) {
            const lq = (q ?? '').toString().toLowerCase().trim();
            this.filtered = lq.length === 0
                ? this.allOptions.slice(0, 50)
                : this.allOptions.filter(o => (o ?? '').toString().toLowerCase().includes(lq)).slice(0, 50);
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
            // tidak auto-highlight; user explicit via arrow / hover
            this.highlighted = -1;
        },

        clearValue() {
            this.$wire.set(this.wireModelName, '', false);
            if (this.$refs.cbInput) {
                this.$refs.cbInput.value = '';
                this.$refs.cbInput.dispatchEvent(new Event('input', { bubbles: true }));
            }
            this.open = false;
            this.highlighted = -1;
            this.$nextTick(() => this.$refs.cbInput && this.$refs.cbInput.focus());
        },

        pick(text) {
            // defer (live=false): set value tanpa AJAX immediate, supaya tidak interrupt user yg lagi ngetik.
            this.$wire.set(this.wireModelName, text, false);
            if (this.$refs.cbInput) {
                this.$refs.cbInput.value = text;
                this.$refs.cbInput.dispatchEvent(new Event('input', { bubbles: true }));
            }
            this.open = false;
            this.highlighted = -1;
            this.$nextTick(() => this.$refs.cbInput && this.$refs.cbInput.focus());
        },

        moveDown() {
            if (!this.open) {
                this.openDropdown();
                if (!this.open) return;
                this.highlighted = 0;
                return;
            }
            if (this.filtered.length === 0) return;
            this.highlighted = this.highlighted < 0
                ? 0
                : (this.highlighted + 1) % this.filtered.length;
        },

        moveUp() {
            if (!this.open) {
                this.openDropdown();
                if (!this.open) return;
                this.highlighted = this.filtered.length - 1;
                return;
            }
            if (this.filtered.length === 0) return;
            this.highlighted = this.highlighted <= 0
                ? this.filtered.length - 1
                : this.highlighted - 1;
        },
    }"
    x-on:click.outside="open = false">

    <div class="relative">
        <input
            type="text"
            autocomplete="off"
            @disabled($disabled)
            wire:model="{{ $wireModel }}"
            placeholder="{{ $placeholder }}"
            maxlength="{{ $maxlength }}"
            @if($inputId) id="{{ $inputId }}" @endif
            x-ref="cbInput"
            x-on:input="onInput()"
            x-on:keydown.escape="open = false"
            x-on:keydown.arrow-down.prevent="moveDown()"
            x-on:keydown.arrow-up.prevent="moveUp()"
            @if($enterAction)
                x-on:keydown.enter.prevent="
                    if (open && highlighted >= 0 && filtered[highlighted]) {
                        pick(filtered[highlighted]);
                    } else {
                        open = false;
                        {{ $enterAction }};
                    }
                "
            @else
                x-on:keydown.enter.prevent="
                    if (open && highlighted >= 0 && filtered[highlighted]) {
                        pick(filtered[highlighted]);
                    } else {
                        open = false;
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
                :title="open ? 'Tutup daftar' : 'Lihat daftar obat'"
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
        yang sama (mis. x-bind:class tab lain di halaman) ikut batal dijalankan.
    --}}
    <div wire:ignore
         x-show="open && filtered.length > 0"
         x-transition.opacity.duration.100ms
         class="absolute z-50 w-full mt-2 overflow-hidden bg-white border border-gray-200 shadow-lg rounded-xl dark:bg-gray-900 dark:border-gray-700">
        <ul class="overflow-y-auto divide-y divide-gray-100 max-h-72 dark:divide-gray-800">
            <template x-for="(opt, idx) in filtered" :key="idx + '-' + opt">
                <li :class="idx === highlighted
                        ? 'bg-brand-lime/15 dark:bg-brand-lime/25 ring-1 ring-brand-lime/30'
                        : 'hover:bg-brand-lime/10 dark:hover:bg-brand-lime/20'"
                    class="w-full px-4 py-3 text-left text-gray-800 dark:text-gray-100 rounded-lg transition-colors duration-150 cursor-pointer"
                    x-on:mousedown.prevent="pick(opt)"
                    x-on:mouseenter="highlighted = idx"
                    x-text="opt"></li>
            </template>
        </ul>
    </div>
</div>
