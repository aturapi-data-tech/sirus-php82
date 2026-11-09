<?php

use App\Support\CakupanIhsDokter;
use App\Support\CakupanIhsPasien;
use App\Support\CakupanKfa;
use App\Support\CakupanLocationKamar;
use App\Support\CakupanLocationPoli;
use App\Support\CakupanLoincLab;
use App\Support\CakupanLoincRadiologi;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    /**
     * Satu baris = satu jenis mapping master ke SATUSEHAT. Rumus angkanya ada di App\Support\Cakupan*,
     * halaman ini hanya menampilkan. 'roles' = siapa yang boleh melihat baris itu.
     */
    private const DAFTAR_MAPPING = [
        ['kelas' => CakupanKfa::class, 'nama' => 'Obat', 'kode' => 'KFA', 'satuanMaster' => 'obat aktif', 'satuanPakai' => 'baris resep', 'route' => 'master.obat', 'filter' => ['kfa' => 'belum'], 'roles' => ['Admin', 'Apoteker']],
        ['kelas' => CakupanLoincLab::class, 'nama' => 'Item Laboratorium', 'kode' => 'LOINC', 'satuanMaster' => 'item lab', 'satuanPakai' => 'hasil lab', 'route' => 'master.laborat', 'filter' => ['loinc' => 'belum'], 'roles' => ['Admin', 'Laboratorium']],
        ['kelas' => CakupanLoincRadiologi::class, 'nama' => 'Tindakan Radiologi', 'kode' => 'LOINC', 'satuanMaster' => 'tindakan', 'satuanPakai' => 'permintaan', 'route' => 'master.radiologis', 'filter' => ['loinc' => 'belum'], 'roles' => ['Admin', 'Radiologi']],
        ['kelas' => CakupanIhsDokter::class, 'nama' => 'Dokter', 'kode' => 'IHS', 'satuanMaster' => 'dokter aktif', 'satuanPakai' => 'kunjungan', 'route' => 'master.dokter', 'filter' => ['ihs' => 'belum'], 'roles' => ['Admin']],
        ['kelas' => CakupanLocationPoli::class, 'nama' => 'Poli', 'kode' => 'Location', 'satuanMaster' => 'poli', 'satuanPakai' => 'kunjungan RJ', 'route' => 'master.poli', 'filter' => ['location' => 'belum'], 'roles' => ['Admin']],
        ['kelas' => CakupanLocationKamar::class, 'nama' => 'Kamar Rawat Inap', 'kode' => 'Location', 'satuanMaster' => 'kamar aktif', 'satuanPakai' => 'masuk rawat', 'route' => 'master.kamar', 'filter' => ['location' => 'belum'], 'roles' => ['Admin']],
        ['kelas' => CakupanIhsPasien::class, 'nama' => 'Pasien Berkunjung', 'kode' => 'IHS', 'satuanMaster' => 'pasien', 'satuanPakai' => 'kunjungan', 'route' => 'master.pasien', 'filter' => ['ihs' => 'belum'], 'roles' => ['Admin', 'Mr']],
    ];

    #[Computed]
    public function rows(): array
    {
        $user = auth()->user();
        $rows = [];

        foreach (self::DAFTAR_MAPPING as $mapping) {
            if (!$user->hasAnyRole($mapping['roles'])) {
                continue;
            }

            $ringkasan = $mapping['kelas']::ringkasan();

            $rows[] = [
                ...$mapping,
                'ringkasan' => $ringkasan,
                'hariPemakaian' => $mapping['kelas']::HARI_PEMAKAIAN,
                'warnaMaster' => $this->warnaBatang($ringkasan['persen'] ?? 0),
                'warnaPakai' => $this->warnaBatang($ringkasan['persenPakai'] ?? 0),
                'jumlahBelum' => $ringkasan ? $ringkasan['total'] - $ringkasan['berkode'] : 0,
            ];
        }

        return $rows;
    }

    private function warnaBatang(float $persen): string
    {
        return match (true) {
            $persen >= 90 => 'bg-green-500',
            $persen >= 60 => 'bg-amber-500',
            default => 'bg-red-500',
        };
    }
};
?>

<div>

    <x-page-title
        title="Mapping SATUSEHAT"
        subtitle="Cakupan kode dan identitas master yang dibutuhkan pengiriman SATUSEHAT" />

    <div class="w-full h-[calc(100vh-5rem)] flex flex-col bg-surface-soft dark:bg-gray-900">
        <div class="flex flex-col flex-1 min-h-0 px-6 pt-2 pb-6">

            <div class="mt-4 flex flex-col flex-1 min-h-0 bg-canvas border border-hairline shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">

                <div class="flex-1 min-h-0 overflow-x-auto overflow-y-auto rounded-t-2xl">
                    <table class="ds-table">
                        <thead class="sticky top-0 z-10">
                            <tr>
                                <th>Mapping</th>
                                <th>Master</th>
                                <th>Pemakaian</th>
                                <th class="ds-c">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($this->rows as $row)
                                <tr wire:key="mapping-{{ $row['route'] }}">
                                    <td>
                                        <div class="ds-td-strong">{{ $row['nama'] }}</div>
                                        <div class="text-xs text-muted dark:text-gray-400">{{ $row['kode'] }}</div>
                                    </td>

                                    @if ($row['ringkasan'])
                                        <td class="min-w-56">
                                            <div class="text-sm font-semibold text-ink dark:text-gray-100">{{ $row['ringkasan']['persen'] }}%</div>
                                            <div class="w-full h-1.5 mt-1 bg-gray-200 rounded-full dark:bg-gray-700">
                                                <div class="h-1.5 rounded-full {{ $row['warnaMaster'] }}" style="width: {{ $row['ringkasan']['persen'] }}%"></div>
                                            </div>
                                            <div class="mt-1 text-xs text-muted dark:text-gray-400">
                                                {{ number_format($row['ringkasan']['berkode'], 0, ',', '.') }} dari {{ number_format($row['ringkasan']['total'], 0, ',', '.') }} {{ $row['satuanMaster'] }}
                                            </div>
                                        </td>

                                        <td class="min-w-56">
                                            @if ($row['ringkasan']['pakaiTotal'] > 0)
                                                <div class="text-sm font-semibold text-ink dark:text-gray-100">{{ $row['ringkasan']['persenPakai'] }}%</div>
                                                <div class="w-full h-1.5 mt-1 bg-gray-200 rounded-full dark:bg-gray-700">
                                                    <div class="h-1.5 rounded-full {{ $row['warnaPakai'] }}" style="width: {{ $row['ringkasan']['persenPakai'] }}%"></div>
                                                </div>
                                                <div class="mt-1 text-xs text-muted dark:text-gray-400">
                                                    {{ number_format($row['ringkasan']['pakaiBerkode'], 0, ',', '.') }} dari {{ number_format($row['ringkasan']['pakaiTotal'], 0, ',', '.') }} {{ $row['satuanPakai'] }}, {{ $row['hariPemakaian'] }} hari terakhir
                                                </div>
                                            @else
                                                <span class="text-xs text-muted dark:text-gray-400">Tidak ada {{ $row['satuanPakai'] }} dalam {{ $row['hariPemakaian'] }} hari terakhir</span>
                                            @endif
                                        </td>

                                        <td class="ds-c whitespace-nowrap">
                                            @if ($row['jumlahBelum'] > 0)
                                                <a href="{{ route($row['route'], $row['filter']) }}" wire:navigate
                                                    class="inline-flex items-center justify-center px-3 py-2 text-sm font-medium transition-colors duration-150 border rounded-lg text-brand-green bg-brand-green/10 border-brand-green/30 hover:bg-brand-green hover:text-white hover:border-brand-green dark:text-brand-lime dark:bg-brand-lime/10 dark:border-brand-lime/30">
                                                    Lengkapi {{ number_format($row['jumlahBelum'], 0, ',', '.') }}
                                                </a>
                                            @else
                                                <x-badge variant="success">Lengkap</x-badge>
                                            @endif
                                        </td>
                                    @else
                                        <td colspan="3">
                                            <x-badge variant="danger">Angka gagal dihitung, lihat log aplikasi</x-badge>
                                        </td>
                                    @endif
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-6 py-10 text-center text-muted dark:text-gray-400">
                                        Tidak ada mapping yang dapat Anda lihat dengan peran ini.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="ds-table-foot">
                    Angka disimpan paling lama satu jam dan diperbarui saat master disimpan. Lengkapi membuka master tersaring pada yang belum terisi, urut dari yang paling sering dipakai.
                </div>
            </div>
        </div>
    </div>
</div>
