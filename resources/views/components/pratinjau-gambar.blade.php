{{--
    Pratinjau gambar (JPG/PNG) dengan zoom & rotasi — pasangan iframe PDF di modal
    hasil penunjang (EMR RJ/UGD/RI + Riwayat Upload). PDF tetap pakai viewer bawaan
    browser (zoom/rotasi sudah ada di sana).

    Ukuran dihitung di Alpine dan dipasang lewat inline style: tidak bergantung kelas
    Tailwind baru, dan gambar yang diputar 90°/270° tetap bisa di-scroll penuh.

    Pakai: <x-pratinjau-gambar :src="$viewFilePDF" />
--}}
@props(['src'])

<div x-data="{
    zoom: 1,
    rotasi: 0,
    skalaPas: 1,
    lebarAsli: 0,
    tinggiAsli: 0,
    get diputar() { return this.rotasi % 180 !== 0 },
    get lebar() { return this.lebarAsli * this.skalaPas * this.zoom },
    get tinggi() { return this.tinggiAsli * this.skalaPas * this.zoom },
    muat() {
        const gambar = this.$refs.gambar;
        if (!gambar.naturalWidth) return;
        this.lebarAsli = gambar.naturalWidth;
        this.tinggiAsli = gambar.naturalHeight;
        this.hitungPas();
    },
    hitungPas() {
        if (!this.lebarAsli) return;
        const area = this.$refs.area;
        const ruangLebar = Math.max(area.clientWidth - 32, 50);
        const ruangTinggi = Math.max(area.clientHeight - 32, 50);
        const lebarTampil = this.diputar ? this.tinggiAsli : this.lebarAsli;
        const tinggiTampil = this.diputar ? this.lebarAsli : this.tinggiAsli;
        this.skalaPas = Math.min(ruangLebar / lebarTampil, ruangTinggi / tinggiTampil, 1);
    },
    perbesar() { this.zoom = Math.min(+(this.zoom + 0.25).toFixed(2), 5) },
    perkecil() { this.zoom = Math.max(+(this.zoom - 0.25).toFixed(2), 0.25) },
    putar(derajat) {
        this.rotasi = (this.rotasi + derajat + 360) % 360;
        this.zoom = 1;
        this.hitungPas();
    },
    pasLayar() {
        this.zoom = 1;
        this.hitungPas();
    },
}" x-init="$nextTick(() => { if ($refs.gambar.complete) muat() })" x-on:resize.window.debounce.200ms="hitungPas()"
    class="flex flex-col w-full h-full">

    {{-- Toolbar --}}
    <div
        class="flex flex-wrap items-center justify-center gap-2 px-4 py-2 border-b shrink-0 bg-canvas border-hairline dark:bg-gray-900 dark:border-gray-700">
        <x-icon-button color="gray" x-on:click="perkecil()" title="Perkecil">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4" />
            </svg>
        </x-icon-button>
        <span class="w-14 text-sm text-center tabular-nums text-muted" x-text="Math.round(zoom * 100) + '%'"></span>
        <x-icon-button color="gray" x-on:click="perbesar()" title="Perbesar">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            </svg>
        </x-icon-button>

        <span class="w-px h-6 mx-1 bg-hairline dark:bg-gray-700"></span>

        <x-icon-button color="gray" x-on:click="putar(-90)" title="Putar ke kiri">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6" />
            </svg>
        </x-icon-button>
        <x-icon-button color="gray" x-on:click="putar(90)" title="Putar ke kanan">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M21 10H11a8 8 0 00-8 8v2m18-10l-6 6m6-6l-6-6" />
            </svg>
        </x-icon-button>

        <span class="w-px h-6 mx-1 bg-hairline dark:bg-gray-700"></span>

        <x-secondary-button type="button" class="!px-3 !py-1.5" x-on:click="pasLayar()">
            Pas Layar
        </x-secondary-button>
    </div>

    {{-- Area gambar — Ctrl + scroll untuk zoom --}}
    <div x-ref="area" class="flex-1 min-h-0 overflow-auto" style="overscroll-behavior: contain"
        x-on:wheel.ctrl.prevent="$event.deltaY < 0 ? perbesar() : perkecil()">
        <div style="display:flex;align-items:center;justify-content:center;min-width:100%;min-height:100%;padding:16px;box-sizing:border-box">
            <div :style="{
                position: 'relative',
                flex: 'none',
                width: (diputar ? tinggi : lebar) + 'px',
                height: (diputar ? lebar : tinggi) + 'px',
            }">
                <img x-ref="gambar" src="{{ $src }}" alt="Hasil penunjang" x-on:load="muat()"
                    class="rounded shadow select-none" draggable="false"
                    :style="{
                        position: 'absolute',
                        left: '50%',
                        top: '50%',
                        maxWidth: 'none',
                        width: lebar + 'px',
                        height: tinggi + 'px',
                        transform: 'translate(-50%, -50%) rotate(' + rotasi + 'deg)',
                    }" />
            </div>
        </div>
    </div>
</div>
