<?php

/**
 * Rekam Medis Nasional — pintu masuk SATUSEHAT RME (SSRME) v2 dari footer EMR
 * RJ / UGD / RI. Satu komponen untuk tiga jalur; dipasang sekali di luar modal
 * EMR dan dibuka lewat event `rekam-medis-nasional.open` { jalur, nomor }.
 *
 * Alur (Petunjuk Teknis SSRME v2.0, 1 Sep 2026):
 *   1. "Buka Rekam Medis Nasional" → POST ssrme/v2/ntl/shl.
 *      Sukses → shlinkUrl dibuka di tab baru.
 *      403 CONSENT_REQUIRED → langkah persetujuan ditampilkan.
 *   2. Persetujuan pasien → POST ssrme/v2/ntl/chl → verificationUrl: halaman
 *      tempat petugas memasukkan KODE AKSES dari SATUSEHAT Mobile pasien.
 *      Akses darurat (type_medical_summary EMERGENCY) → form alasan + pengantar/wali
 *      di halaman yang sama, tanpa persetujuan pasien.
 *   3. Setelah persetujuan tercatat, langkah 1 diulang.
 *
 * Persetujuan melekat ke pasien + DOKTER; dokter yang dipakai = DPJP kunjungan.
 * Ini hanya MELIHAT riwayat faskes lain — rekam medis RS sendiri tetap di siRUS.
 * Tautan tidak disimpan di DB (URL sensitif menurut juknis).
 */

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\SATUSEHAT\SatuSehatTrait;
use App\Http\Traits\SATUSEHAT\RekamMedisNasionalTrait;

new class extends Component {
    use SatuSehatTrait, RekamMedisNasionalTrait;

    public string $jalur = '';
    public string $nomorKunjungan = '';

    public string $namaPasien = '';
    public string $regNo = '';
    public string $pasienIhs = '';
    public string $namaDokter = '';
    public string $dokterIhs = '';

    /** Diisi setelah SHLink/CHLink berhasil — tidak disimpan di mana pun. */
    public string $tautanRekamMedis = '';
    public string $tautanPersetujuan = '';
    public bool $persetujuanDarurat = false;
    public bool $perluPersetujuan = false;
    public string $pesan = '';

    #[On('rekam-medis-nasional.open')]
    public function open(string $jalur, $nomor): void
    {
        $this->reset();

        // Whitelist: jalur di luar dugaan tidak boleh jatuh ke tabel mana pun.
        $sumberKunjungan = [
            'RJ' => ['rstxn_rjhdrs', 'rj_no'],
            'UGD' => ['rstxn_ugdhdrs', 'rj_no'],
            'RI' => ['rstxn_rihdrs', 'rihdr_no'],
        ];
        if (!isset($sumberKunjungan[$jalur])) {
            $this->dispatch('toast', type: 'error', message: 'Jalur kunjungan tidak dikenal.');
            return;
        }
        [$tabel, $kolomNomor] = $sumberKunjungan[$jalur];

        $kunjungan = DB::table($tabel)->select('reg_no', 'dr_id')->where($kolomNomor, $nomor)->first();
        if (!$kunjungan) {
            $this->dispatch('toast', type: 'error', message: 'Data kunjungan tidak ditemukan.');
            return;
        }

        $pasien = DB::table('rsmst_pasiens')->select('reg_name', 'patient_uuid')->where('reg_no', $kunjungan->reg_no)->first();
        $dokter = DB::table('rsmst_doctors')->select('dr_name', 'dr_uuid')->where('dr_id', $kunjungan->dr_id)->first();

        $this->jalur = $jalur;
        $this->nomorKunjungan = (string) $nomor;
        $this->regNo = (string) $kunjungan->reg_no;
        $this->namaPasien = (string) ($pasien->reg_name ?? '');
        $this->pasienIhs = trim((string) ($pasien->patient_uuid ?? ''));
        $this->namaDokter = (string) ($dokter->dr_name ?? '');
        $this->dokterIhs = trim((string) ($dokter->dr_uuid ?? ''));

        $this->dispatch('open-modal', name: 'rekam-medis-nasional');
    }

    public function prasyaratKurang(): array
    {
        $kurang = [];
        if ($this->pasienIhs === '') {
            $kurang[] = 'IHS pasien belum ada (kirim/cari Patient SATUSEHAT dulu di Master Pasien)';
        }
        if ($this->dokterIhs === '') {
            $kurang[] = 'IHS dokter DPJP belum ada (isi dr_uuid di Master Dokter)';
        }

        return $kurang;
    }

    private function identitas(): array
    {
        return [
            'patient_id' => $this->pasienIhs,
            'patient_name' => $this->namaPasien,
            'practitioner_id' => $this->dokterIhs,
            'practitioner_name' => $this->namaDokter,
        ];
    }

    public function bukaRekamMedis(): void
    {
        $this->tautanRekamMedis = '';
        if (!empty($this->prasyaratKurang())) {
            $this->dispatch('toast', type: 'error', message: 'Data belum siap: ' . implode('; ', $this->prasyaratKurang()) . '.');
            return;
        }

        $hasil = $this->rekamMedisNasionalBukaTautan($this->identitas());

        if ($hasil['ok'] && !empty($hasil['data']['shlinkUrl'])) {
            $this->tautanRekamMedis = (string) $hasil['data']['shlinkUrl'];
            $this->perluPersetujuan = false;
            $this->pesan = !empty($hasil['data']['partial'])
                ? 'Sebagian data tidak berhasil dihimpun SATUSEHAT — riwayat mungkin belum lengkap.'
                : '';
            // Dibuka langsung; bila diblokir popup blocker, tombol tautan tetap tersedia.
            $this->js('window.open(' . json_encode($this->tautanRekamMedis) . ', "_blank", "noopener")');
            return;
        }

        if ($hasil['kodeError'] === 'CONSENT_REQUIRED' || $hasil['code'] === 403) {
            $this->perluPersetujuan = true;
            $this->pesan = 'Pasien belum memberi persetujuan untuk dokter ini. Minta persetujuan dulu.';
            return;
        }

        $this->pesan = '';
        $this->dispatch('toast', type: 'error', message: 'Gagal membuka rekam medis nasional [' . $hasil['code'] . '] ' . $hasil['pesan']);
    }

    public function mintaPersetujuan(bool $darurat = false): void
    {
        $this->tautanPersetujuan = '';
        if (!empty($this->prasyaratKurang())) {
            $this->dispatch('toast', type: 'error', message: 'Data belum siap: ' . implode('; ', $this->prasyaratKurang()) . '.');
            return;
        }

        $hasil = $this->rekamMedisNasionalMintaPersetujuan($this->identitas(), $darurat);
        if (!$hasil['ok'] || empty($hasil['data']['verificationUrl'])) {
            $this->dispatch('toast', type: 'error', message: 'Gagal membuat halaman persetujuan [' . $hasil['code'] . '] ' . $hasil['pesan']);
            return;
        }

        $this->tautanPersetujuan = (string) $hasil['data']['verificationUrl'];
        $this->persetujuanDarurat = $darurat;
        $this->perluPersetujuan = true;
        $this->js('window.open(' . json_encode($this->tautanPersetujuan) . ', "_blank", "noopener")');
    }

    public function closeModal(): void
    {
        $this->reset();
        $this->dispatch('close-modal', name: 'rekam-medis-nasional');
    }
};
?>

<div>
    <x-modal name="rekam-medis-nasional" size="2xl" focusable>
        <div class="p-6 space-y-4">
            <div>
                <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Rekam Medis Nasional (SATUSEHAT)</h2>
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    Riwayat pasien dari faskes lain yang tercatat di SATUSEHAT. Rekam medis RS sendiri tetap dibuka di siRUS.
                </p>
            </div>

            <dl class="grid grid-cols-1 gap-2 p-3 text-sm border rounded-lg sm:grid-cols-2 border-gray-200 dark:border-gray-700">
                <div>
                    <dt class="text-gray-500 dark:text-gray-400">Pasien</dt>
                    <dd class="font-medium text-gray-900 dark:text-gray-100">{{ $namaPasien ?: '-' }} <span class="text-gray-500 dark:text-gray-400">({{ $regNo }})</span></dd>
                    <dd class="text-xs {{ $pasienIhs ? 'text-gray-600 dark:text-gray-400' : 'text-red-700 dark:text-red-400' }}">IHS: {{ $pasienIhs ?: 'belum ada' }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500 dark:text-gray-400">Dokter (DPJP)</dt>
                    <dd class="font-medium text-gray-900 dark:text-gray-100">{{ $namaDokter ?: '-' }}</dd>
                    <dd class="text-xs {{ $dokterIhs ? 'text-gray-600 dark:text-gray-400' : 'text-red-700 dark:text-red-400' }}">IHS: {{ $dokterIhs ?: 'belum ada' }}</dd>
                </div>
            </dl>

            @if (!empty($this->prasyaratKurang()))
                <div class="p-3 text-sm text-red-800 border border-red-200 rounded-lg bg-red-50 dark:bg-red-900/20 dark:text-red-300 dark:border-red-800">
                    <p class="font-medium">Belum bisa dibuka:</p>
                    <ul class="ml-5 list-disc">
                        @foreach ($this->prasyaratKurang() as $kekurangan)
                            <li>{{ $kekurangan }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if ($pesan !== '')
                <div class="p-3 text-sm text-amber-900 border border-amber-200 rounded-lg bg-amber-50 dark:bg-amber-900/20 dark:text-amber-200 dark:border-amber-800">
                    {{ $pesan }}
                </div>
            @endif

            {{-- Langkah 1 — buka --}}
            <div class="flex flex-wrap items-center gap-2">
                <x-primary-button type="button" wire:click="bukaRekamMedis" wire:loading.attr="disabled" wire:target="bukaRekamMedis"
                    :disabled="!empty($this->prasyaratKurang())">
                    <span wire:loading.remove wire:target="bukaRekamMedis">Buka Rekam Medis Nasional</span>
                    <span wire:loading wire:target="bukaRekamMedis" class="flex items-center gap-1"><x-loading /> Memuat...</span>
                </x-primary-button>
                @if ($tautanRekamMedis !== '')
                    <a href="{{ $tautanRekamMedis }}" target="_blank" rel="noopener noreferrer"
                        class="text-sm font-medium text-blue-700 underline dark:text-blue-400">Tab tidak terbuka? Klik di sini</a>
                @endif
            </div>

            {{-- Langkah 2 — persetujuan (muncul bila SATUSEHAT meminta) --}}
            @if ($perluPersetujuan)
                <div class="p-4 space-y-3 border rounded-lg border-gray-200 dark:border-gray-700">
                    <p class="text-sm font-medium text-gray-900 dark:text-gray-100">Persetujuan akses</p>
                    <ol class="ml-5 text-sm text-gray-700 list-decimal dark:text-gray-300">
                        <li><b>Biasa:</b> minta pasien membuat kode akses di aplikasi SATUSEHAT Mobile, lalu masukkan kode itu di halaman persetujuan.</li>
                        <li><b>Darurat:</b> hanya bila kondisi pasien darurat — isi alasan dan data pengantar/wali di halaman persetujuan, tanpa kode akses pasien.</li>
                        <li>Setelah persetujuan tercatat, tekan lagi <b>Buka Rekam Medis Nasional</b>.</li>
                    </ol>
                    <div class="flex flex-wrap items-center gap-2">
                        <x-secondary-button type="button" wire:click="mintaPersetujuan(false)" wire:loading.attr="disabled" wire:target="mintaPersetujuan">
                            Persetujuan Pasien (Kode Akses)
                        </x-secondary-button>
                        <x-confirm-button variant="danger-soft" action="mintaPersetujuan(true)" title="Akses Darurat"
                            message="Akses darurat membuka rekam medis nasional TANPA persetujuan pasien dan tercatat sebagai akses darurat. Lanjutkan hanya bila kondisi pasien benar-benar darurat."
                            confirmText="Ya, darurat">
                            Akses Darurat
                        </x-confirm-button>
                    </div>
                    @if ($tautanPersetujuan !== '')
                        <a href="{{ $tautanPersetujuan }}" target="_blank" rel="noopener noreferrer"
                            class="text-sm font-medium text-blue-700 underline dark:text-blue-400">
                            Buka halaman {{ $persetujuanDarurat ? 'akses darurat' : 'persetujuan pasien' }}
                        </a>
                    @endif
                </div>
            @endif

            <div class="flex justify-end">
                <x-secondary-button type="button" wire:click="closeModal">Tutup</x-secondary-button>
            </div>
        </div>
    </x-modal>
</div>
