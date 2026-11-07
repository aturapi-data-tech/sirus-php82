{{-- resources/views/pages/components/manajemen/monitoring-jasa-dokter/isi-rincian-layar.blade.php --}}
{{-- Badan rincian transaksi jasa dokter untuk TAMPILAN LAYAR (modal), bukan PDF.

     Dipisah dari komponen modalnya supaya berkas komponen tetap terbaca —
     yang di sana urusan data & aksi, yang di sini murni penyajian.

     Butuh: $dataLampiran, koleksi entri per dokter berisi
     {header, lampiran, perKomponen, angka} dari GajiDokterLampiran::susunPerDokter(). --}}

@php
    $rupiah = fn($nilai) => number_format((float) $nilai, 0, ',', '.');
    $bulanNama = \App\Support\GajiDokter\GajiDokterLampiran::NAMA_BULAN;
@endphp

@forelse ($dataLampiran as $satuDokter)
    @php
        $header = $satuDokter['header'];
        $angka = $satuDokter['angka'];
        $periodeJasa = ($bulanNama[$header->bulan_jasa] ?? $header->bulan_jasa) . ' ' . $header->tahun_jasa;
    @endphp

    <div class="mb-4 overflow-hidden bg-canvas border border-hairline rounded-xl dark:bg-gray-900 dark:border-gray-700">

        {{-- IDENTITAS DOKTER --}}
        <div class="px-4 py-3 border-b border-hairline bg-surface-card dark:bg-gray-800 dark:border-gray-700">
            <div class="flex flex-wrap items-baseline justify-between gap-x-6 gap-y-1">
                <h3 class="text-base font-bold text-ink dark:text-gray-100">{{ $header->dr_name }}</h3>
                <div class="flex flex-wrap gap-x-5 gap-y-1 text-xs text-muted dark:text-gray-400">
                    <span>Bulan Jasa: <span class="font-medium text-body dark:text-gray-200">{{ $periodeJasa }}</span></span>
                    <span>Jumlah Baris: <span class="font-medium text-body dark:text-gray-200">{{ $satuDokter['lampiran']->count() }} transaksi</span></span>
                    <span>Total: <span class="font-semibold tabular-nums text-body dark:text-gray-200">{{ $rupiah($angka['totalTransaksi']) }}</span></span>
                </div>
            </div>
        </div>

        {{-- Catatan tanggal WAJIB ada: tanpa itu baris rawat inap yang bertanggal
             bulan lain terbaca sebagai salah periode, padahal justru begitu cara
             bulan jasanya ditentukan. --}}
        <div class="px-4 py-2 text-xs border-b border-hairline bg-blue-50 text-blue-900 dark:bg-blue-900/20 dark:text-blue-200 dark:border-gray-700">
            Tanggal di bawah adalah <strong>tanggal layanan</strong> (visite, konsultasi, tindakan, operasi,
            atau kunjungan). Jasa rawat inap masuk bulan jasa saat pasien <strong>pulang</strong>, sehingga
            baris rawat inap dapat bertanggal bulan sebelum bulan jasa di atas. Baris yang sama inilah yang
            ditarik SIAP.
        </div>

        {{-- TABEL PER KOMPONEN --}}
        @foreach ($satuDokter['perKomponen'] as $descDoc => $barisKomponen)
            @php
                $subtotal = $barisKomponen->sum('nominal');
                $pasien = $barisKomponen->sum('pasien');
                // Komponen besar dilipat: ribuan baris tidak ikut HTML sampai diminta.
                $kunciKomponen = $header->dr_id . '|' . $descDoc;
                $dilipat = $barisKomponen->count() > ($batasLipat ?? 300) && !in_array($kunciKomponen, $komponenTerbuka ?? [], true);
            @endphp

            <div class="border-b border-hairline dark:border-gray-700">
                <div class="flex flex-wrap items-baseline justify-between gap-x-4 px-4 py-2 bg-surface-soft dark:bg-gray-800/60">
                    <div class="text-sm font-semibold text-ink dark:text-gray-100">
                        {{ $barisKomponen->first()['label'] }}
                    </div>
                    <div class="flex items-center gap-3 text-xs text-muted dark:text-gray-400">
                        <span>
                            {{ $pasien }} pasien &middot;
                            <span class="font-semibold tabular-nums text-body dark:text-gray-200">{{ $rupiah($subtotal) }}</span>
                        </span>
                        @if ($barisKomponen->count() > ($batasLipat ?? 300))
                            <x-secondary-button type="button" wire:click="toggleKomponen('{{ $kunciKomponen }}')"
                                wire:loading.attr="disabled" wire:target="toggleKomponen('{{ $kunciKomponen }}')" class="gap-1.5 whitespace-nowrap">
                                <span wire:loading.remove wire:target="toggleKomponen('{{ $kunciKomponen }}')">
                                    {{ $dilipat ? 'Tampilkan ' . $barisKomponen->count() . ' baris' : 'Sembunyikan baris' }}
                                </span>
                                <span wire:loading wire:target="toggleKomponen('{{ $kunciKomponen }}')" class="inline-flex items-center gap-1.5">
                                    <x-loading class="w-4 h-4" /> Memuat...
                                </span>
                            </x-secondary-button>
                        @endif
                    </div>
                </div>
                @if ($dilipat)
                    <div class="px-4 py-2 text-xs italic text-muted dark:text-gray-400">
                        {{ $barisKomponen->count() }} baris dilipat supaya rincian cepat dibuka &mdash; subtotal di atas sudah menghitung semuanya. Unduh Excel tetap memuat seluruh baris.
                    </div>
                @else
                {{-- table-fixed WAJIB: dengan table-layout otomatis, sel ber-whitespace-nowrap
                     melebarkan kolomnya sendiri sehingga satu No. SEP panjang menggusur kolom
                     Nama Pasien. Lebar diukur dari data Juli 2026 (4.688 baris): tgl 10 huruf,
                     No. RM 8, klaim 12, no. transaksi 6, nominal 9, nama p95 21, SEP p95 19. --}}
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left table-fixed text-body dark:text-gray-300">
                        <thead class="text-xs uppercase text-muted bg-canvas dark:bg-gray-900 dark:text-gray-400">
                            <tr>
                                <th class="px-2 py-2 w-12 text-right">No</th>
                                <th class="px-2 py-2 w-24">Tanggal</th>
                                <th class="px-2 py-2 w-24">No. RM</th>
                                <th class="px-3 py-2">Nama Pasien</th>
                                <th class="px-2 py-2 w-40">Klaim</th>
                                <th class="px-2 py-2 w-52">No. SEP</th>
                                <th class="px-2 py-2 w-24">No. Transaksi</th>
                                <th class="px-3 py-2 w-32 text-right">Nominal (Rp)</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($barisKomponen as $row)
                                <tr class="border-t border-hairline hover:bg-surface-soft dark:border-gray-700 dark:hover:bg-gray-800/50">
                                    <td class="px-2 py-1.5 text-right tabular-nums text-muted dark:text-gray-400">{{ $loop->iteration }}</td>
                                    <td class="px-2 py-1.5 whitespace-nowrap tabular-nums">{{ $row['tgl'] }}</td>
                                    <td class="px-2 py-1.5 whitespace-nowrap tabular-nums">{{ $row['reg_no'] !== '' ? $row['reg_no'] : '—' }}</td>
                                    {{-- Keterangan = nama jasa/tindakan/pemeriksaan/kasus operasi (kosong untuk
                                         uang periksa, visite, konsul). Ditumpuk di bawah nama supaya kolom nama
                                         tidak menyempit di semua baris. --}}
                                    <td class="px-3 py-1.5 text-ink dark:text-gray-100" title="{{ $row['nama'] }}{{ $row['keterangan'] !== '' ? ' — ' . $row['keterangan'] : '' }}">
                                        <span class="block truncate">{{ $row['nama'] !== '' ? $row['nama'] : '—' }}</span>
                                        @if ($row['keterangan'] !== '')
                                            <span class="block text-xs truncate text-muted dark:text-gray-400">{{ $row['keterangan'] }}</span>
                                        @endif
                                    </td>
                                    <td class="px-2 py-1.5 truncate" title="{{ $row['klaim'] }}">
                                        {{ $row['klaim'] !== '' ? $row['klaim'] : '—' }}
                                        @if ($row['klaim_status'] === 'BPJS')
                                            <span class="ml-1 px-1 py-0.5 text-xs font-medium rounded bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200">BPJS</span>
                                        @endif
                                    </td>
                                    {{-- SEP kosong itu WAJAR: pasien umum tidak punya, klinik tidak menyimpan kolomnya. --}}
                                    <td class="px-2 py-1.5 truncate tabular-nums text-muted dark:text-gray-400" title="{{ $row['no_sep'] }}">{{ $row['no_sep'] !== '' ? $row['no_sep'] : '—' }}</td>
                                    <td class="px-2 py-1.5 whitespace-nowrap tabular-nums text-muted dark:text-gray-400">{{ $row['txn_no'] }}</td>
                                    <td class="px-3 py-1.5 text-right tabular-nums whitespace-nowrap">{{ $rupiah($row['nominal']) }}</td>
                                </tr>
                            @endforeach
                            <tr class="border-t-2 border-gray-400 font-semibold bg-surface-soft dark:border-gray-600 dark:bg-gray-800/60">
                                <td colspan="7" class="px-3 py-2 text-right">Subtotal {{ $barisKomponen->first()['label'] }}</td>
                                <td class="px-3 py-2 text-right tabular-nums whitespace-nowrap">{{ $rupiah($subtotal) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                @endif
            </div>
        @endforeach

        {{-- TOTAL DOKTER — angka yang akan muncul sebagai jasa di SIAP sebelum
             gaji pokok, tunjangan, dan potongan. --}}
        <div class="px-4 py-3 bg-surface-card dark:bg-gray-800">
            <table class="w-full text-sm text-body dark:text-gray-300">
                <tr class="font-bold text-ink dark:text-gray-100">
                    <td class="py-1.5">Total transaksi jasa {{ $header->dr_name }} &mdash; {{ $periodeJasa }}</td>
                    <td class="py-1.5 w-40 text-right tabular-nums">{{ $rupiah($angka['totalTransaksi']) }}</td>
                </tr>
            </table>
        </div>
    </div>
@empty
    <div class="p-6 text-sm text-center border border-dashed rounded-xl text-muted border-gray-300 dark:text-gray-400 dark:border-gray-700">
        Tidak ada transaksi pasien pada periode ini.
    </div>
@endforelse
