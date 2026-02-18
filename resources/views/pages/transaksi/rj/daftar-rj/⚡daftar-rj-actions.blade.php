<?php

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;
use Carbon\Carbon;
use App\Http\Traits\Txn\Rj\EmrRJTrait;

new class extends Component {
    use EmrRJTrait;

    public string $formMode = 'create'; // create|edit
    public ?string $rjNo = null;
    public $disabledPropertyRjStatus = false;
    public ?string $kronisNotice = null;
    public array $dataDaftarPoliRJ = [];

    public string $klaimId = 'UM';
    public array $klaimOptions = [['klaimId' => 'UM', 'klaimDesc' => 'UMUM'], ['klaimId' => 'JM', 'klaimDesc' => 'BPJS'], ['klaimId' => 'JR', 'klaimDesc' => 'JASA RAHARJA'], ['klaimId' => 'JML', 'klaimDesc' => 'Asuransi Lain'], ['klaimId' => 'KR', 'klaimDesc' => 'Kronis']];

    public string $kunjunganId = '1';
    public array $kunjunganOptions = [['kunjunganId' => '1', 'kunjunganDesc' => 'Rujukan FKTP'], ['kunjunganId' => '2', 'kunjunganDesc' => 'Rujukan Internal'], ['kunjunganId' => '3', 'kunjunganDesc' => 'Kontrol'], ['kunjunganId' => '4', 'kunjunganDesc' => 'Rujukan Antar RS']];

    /* ===============================
     | OPEN CREATE
     =============================== */
    #[On('daftar-rj.openCreate')]
    public function openCreate(): void
    {
        $this->resetForm();
        $this->formMode = 'create';
        $this->resetValidation();

        $this->dataDaftarPoliRJ = $this->getDefaultRJTemplate();

        // ===============================
        // Set Tanggal RJ (hari ini)
        // ===============================
        $now = Carbon::now(config('app.timezone'));
        $this->dataDaftarPoliRJ['rjDate'] = $now->format('d/m/Y H:i:s');

        // ===============================
        // Set Shift berdasarkan jam sekarang
        // ===============================
        $nowTime = $now->format('H:i:s');

        $findShift = DB::table('rstxn_shiftctls')
            ->select('shift')
            ->whereRaw('? BETWEEN shift_start AND shift_end', [$nowTime])
            ->first();

        $this->dataDaftarPoliRJ['shift'] = $findShift->shift ?? 3;

        $this->dispatch('open-modal', name: 'rj-actions');
    }

    /* ===============================
     | OPEN EDIT
     =============================== */

    public function closeModal(): void
    {
        $this->resetValidation();
        $this->resetForm();
        $this->dispatch('close-modal', name: 'rj-actions');
    }

    protected function resetForm(): void
    {
        $this->reset(['rjNo', 'dataDaftarPoliRJ']);

        $this->formMode = 'create';
    }

    protected function rules(): array
    {
        return [
            'tindakLanjut' => ['nullable', 'string', 'max:255'],
            'tglKontrol' => ['nullable', 'date'],
            'keterangan' => ['nullable', 'string'],
        ];
    }

    /* ===============================
     | SAVE
     =============================== */

    /* ===============================
     | DELETE
     =============================== */

    #[On('lov.selected')]
    public function handleLovSelected(string $target, array $payload): void
    {
        if ($target === 'rjFormPasien') {
            $this->dataDaftarPoliRJ['regNo'] = $payload['regNo'] ?? '';
            $this->dataDaftarPoliRJ['regName'] = $payload['regName'] ?? '';
            $this->dispatch('focus-after-lov');
        }

        if ($target === 'rjFormDokter') {
            $this->dataDaftarPoliRJ['drId'] = $payload['dr_id'] ?? '';
            $this->dataDaftarPoliRJ['drDesc'] = $payload['dr_name'] ?? '';
            $this->dataDaftarPoliRJ['poliId'] = $payload['poli_id'] ?? '';
            $this->dataDaftarPoliRJ['poliDesc'] = $payload['poli_desc'] ?? '';
        }
    }

    public function updatedKlaimId($value)
    {
        $this->dataDaftarPoliRJ['klaimId'] = $value;
    }

    public function updatedKunjunganId($value)
    {
        $this->dataDaftarPoliRJ['kunjunganId'] = $value;
    }

    private function syncFromDataDaftarPoliRJ(): void
    {
        // mode edit: sync from dataDaftarPoliRJ
        if (!empty($this->dataDaftarPoliRJ['klaimId'])) {
            $this->klaimId = $this->dataDaftarPoliRJ['klaimId'];
        }

        if (!empty($this->dataDaftarPoliRJ['kunjunganId'])) {
            $this->kunjunganId = $this->dataDaftarPoliRJ['kunjunganId'];
        }
    }
};
?>


<div>
    <x-modal name="rj-actions" size="full" height="full" focusable>
        <div class="flex flex-col min-h-[calc(100vh-8rem)]"
            wire:key="rj-actions-{{ $formMode }}-{{ $rjNo ?? 'new' }}">

            {{-- HEADER --}}
            <div class="relative px-6 py-5 border-b border-gray-200 dark:border-gray-700">
                {{-- Background pattern --}}
                <div class="absolute inset-0 opacity-[0.06] dark:opacity-[0.10]"
                    style="background-image: radial-gradient(currentColor 1px, transparent 1px); background-size: 14px 14px;">
                </div>

                <div class="relative flex items-start justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-3">
                            {{-- Icon --}}
                            <div
                                class="flex items-center justify-center w-10 h-10 rounded-xl bg-brand-green/10 dark:bg-brand-lime/15">
                                <img src="{{ asset('images/Logogram black solid.png') }}" alt="RSI Madinah"
                                    class="block w-6 h-6 dark:hidden" />
                                <img src="{{ asset('images/Logogram white solid.png') }}" alt="RSI Madinah"
                                    class="hidden w-6 h-6 dark:block" />
                            </div>

                            {{-- Title & subtitle --}}
                            <div>
                                <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">
                                    {{ $formMode === 'edit' ? 'Ubah Data Rawat Jalan' : 'Tambah Data Rawat Jalan' }}
                                </h2>
                                <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                                    Kelola data pendaftaran dan pelayanan pasien rawat jalan.
                                </p>
                            </div>
                        </div>

                        {{-- Badge mode --}}
                        <div class="mt-3">
                            <x-badge :variant="$formMode === 'edit' ? 'warning' : 'success'">
                                {{ $formMode === 'edit' ? 'Mode: Edit' : 'Mode: Tambah' }}
                            </x-badge>
                        </div>
                    </div>

                    {{-- Close button --}}
                    <x-secondary-button type="button" wire:click="closeModal" class="!p-2">
                        <span class="sr-only">Close</span>
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd"
                                d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                clip-rule="evenodd" />
                        </svg>
                    </x-secondary-button>
                </div>
            </div>

            {{-- BODY --}}
            <div class="flex-1 px-4 py-4 bg-gray-50/70 dark:bg-gray-950/20">

                <div class="max-w-full mx-auto">
                    <div class="p-1 space-y-1" x-data
                        @keydown.enter.prevent="
                            let f = [...$el.querySelectorAll('input,select,textarea')]
                                .filter(e => !e.disabled && e.type !== 'hidden');

                            let i = f.indexOf($event.target);

                            if(i > -1 && i < f.length - 1){
                                f[i+1].focus();
                            }
                        ">
                        <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">

                            {{-- ========================= --}}
                            {{-- KOLOM KIRI --}}
                            {{-- ========================= --}}
                            <div
                                class="p-6 space-y-6 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-900 dark:border-gray-700">

                                <div class="flex gap-4">
                                    {{-- Tanggal RJ --}}
                                    <div class="flex-1">
                                        <x-input-label value="Tanggal RJ" />
                                        <x-text-input wire:model.live="dataDaftarPoliRJ.rjDate"
                                            wire:key="rjDate-{{ $dataDaftarPoliRJ['rjDate'] ?? 'new' }}"
                                            class="block w-full" />
                                    </div>
                                    {{-- Shift --}}
                                    <div class="w-36">
                                        <x-input-label value="Shift" />
                                        <x-select-input wire:model.live="dataDaftarPoliRJ.shift"
                                            class="w-full mt-1 sm:w-36"
                                            wire:key="shift-{{ $dataDaftarPoliRJ['shift'] ?? 'new' }}">
                                            <option value="">-- Pilih Shift --</option>
                                            <option value="1">Shift 1</option>
                                            <option value="2">Shift 2</option>
                                            <option value="3">Shift 3</option>
                                        </x-select-input>
                                    </div>
                                </div>

                                {{-- LOV Pasien --}}
                                <livewire:lov.pasien.lov-pasien target="rjFormPasien" :initialRegNo="$dataDaftarPoliRJ['regNo'] ?? ''"
                                    wire:key="'lov-pasien-' . ($dataDaftarPoliRJ['regNo'] ?? 'new')" />

                                {{-- Jenis Klaim --}}
                                <div>
                                    <x-input-label value="Jenis Klaim" />
                                    <div class="grid grid-cols-5 gap-2 mt-2">
                                        @foreach ($klaimOptions ?? [] as $index => $klaim)
                                            <x-radio-button :label="$klaim['klaimDesc']" :value="(string) $klaim['klaimId']"
                                                name="dataDaftarPoliRJ.klaimId" wire:model.live="klaimId"
                                                wire:key="klaim-{{ $klaim['klaimId'] }}-{{ $index }}" />
                                        @endforeach
                                    </div>
                                </div>



                            </div>

                            {{-- ========================= --}}
                            {{-- KOLOM KANAN --}}
                            {{-- ========================= --}}
                            <div
                                class="p-6 space-y-6 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-900 dark:border-gray-700">

                                {{-- Jenis Kunjungan --}}
                                <div>
                                    <x-input-label value="Jenis Kunjungan" />
                                    <div class="grid grid-cols-4 gap-2 mt-2">
                                        @foreach ($kunjunganOptions ?? [] as $index => $kunjungan)
                                            <x-radio-button :label="$kunjungan['kunjunganDesc']" :value="$kunjungan['kunjunganId']"
                                                name="dataDaftarPoliRJ.kunjunganId" wire:model.live="kunjunganId"
                                                wire:key="kunjungan-{{ $kunjungan['kunjunganId'] }}-{{ $index }}" />
                                        @endforeach
                                    </div>

                                    {{-- LOGIC POST INAP & KONTROL 1/2 --}}
                                    <div class="mt-4">
                                        @if (($dataDaftarPoliRJ['kunjunganId'] ?? '') === '3')
                                            <x-check-box value="1" :label="__('Post Inap')"
                                                wire:model="dataDaftarPoliRJ.postInap" />
                                        @endif

                                        <div class="grid grid-cols-2 gap-2 mt-2">
                                            @if (in_array($dataDaftarPoliRJ['kunjunganId'] ?? '', ['2', '3']))
                                                @foreach ($dataDaftarPoliRJ['kontrol12Options'] ?? [] as $kontrol12)
                                                    <x-radio-button :label="__($kontrol12['kontrol12Desc'])"
                                                        value="{{ $kontrol12['kontrol12'] }}"
                                                        wire:model="dataDaftarPoliRJ.kontrol12" />
                                                @endforeach
                                            @endif
                                        </div>
                                    </div>
                                </div>


                                {{-- No Referensi --}}
                                <div class="pt-4 space-y-3 border-t">
                                    <div class="grid">
                                        <x-input-label value="No Referensi" />
                                        <x-text-input wire:model.live="dataDaftarPoliRJ.noReferensi" />
                                    </div>
                                    <div class="flex justify-between gap-2">
                                        <x-primary-button wire:click.prevent="clickrujukanPeserta()">
                                            Cek Referensi
                                        </x-primary-button>
                                        <x-primary-button wire:click.prevent="callRJskdp()">
                                            Buat SKDP
                                        </x-primary-button>
                                    </div>
                                </div>

                                {{-- Dokter & Poli --}}
                                <div class="pt-4 space-y-4 border-t">
                                    <x-input-label value="Dokter & Poli" />

                                    {{-- Display Selected --}}
                                    {{-- <x-text-input class="w-full mt-1" :disabled="true"
                                        value="{{ ($dataDaftarPoliRJ['drId'] ?? '') .
                                            (isset($dataDaftarPoliRJ['drDesc']) ? ' - ' . $dataDaftarPoliRJ['drDesc'] : '') .
                                            (isset($dataDaftarPoliRJ['poliDesc']) ? ' | Poli: ' . $dataDaftarPoliRJ['poliDesc'] : '') }}" /> --}}

                                    {{-- LOV Dokter --}}
                                    <div class="mt-2">
                                        <livewire:lov.dokter.lov-dokter target="rjFormDokter" :initialDrId="$dataDaftarPoliRJ['drId'] ?? null"
                                            :key="'lov-dokter-rj-' . ($dataDaftarPoliRJ['drId'] ?? 'new')" />
                                    </div>
                                </div>

                            </div>

                        </div>
                    </div>
                </div>

            </div>

            {{-- FOOTER --}}
            <div
                class="sticky bottom-0 z-10 px-6 py-4 bg-white border-t border-gray-200 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex justify-between gap-3">
                    <x-primary-button wire:click.prevent="callFormPasien()">
                        Master Pasien
                    </x-primary-button>
                    <div class="flex justify-between gap-3">
                        <x-secondary-button wire:click="closeModal">
                            Batal
                        </x-secondary-button>
                        <x-primary-button wire:click.prevent="store()" class="min-w-[120px]">
                            Simpan
                        </x-primary-button>
                    </div>
                </div>
            </div>

        </div>
    </x-modal>
</div>
