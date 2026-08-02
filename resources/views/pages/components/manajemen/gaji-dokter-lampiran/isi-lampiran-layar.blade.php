{{-- resources/views/pages/components/manajemen/gaji-dokter-lampiran/isi-lampiran-layar.blade.php --}}
{{-- Badan lampiran rincian pasien untuk TAMPILAN LAYAR (modal), bukan PDF.

     Dipisah dari komponen modalnya supaya berkas komponen tetap terbaca —
     yang di sana urusan data & aksi, yang di sini murni penyajian.

     Butuh: $dataLampiran, koleksi entri per dokter berisi
     {header, lampiran, grupKapita, perKomponen, angka}. --}}

@php
    $rupiah = fn($nilai) => number_format((float) $nilai, 0, ',', '.');

    $bulanNama = [
        '01' => 'Januari', '02' => 'Februari', '03' => 'Maret', '04' => 'April',
        '05' => 'Mei', '06' => 'Juni', '07' => 'Juli', '08' => 'Agustus',
        '09' => 'September', '10' => 'Oktober', '11' => 'November', '12' => 'Desember',
    ];
@endphp

@forelse ($dataLampiran as $satuDokter)
    @php
        $header = $satuDokter['header'];
        $angka = $satuDokter['angka'];
        $grupKapita = $satuDokter['grupKapita'];

        $periodeJasa = ($bulanNama[$header->bulan_jasa] ?? $header->bulan_jasa) . ' ' . $header->tahun_jasa;
        $periodeGaji = ($bulanNama[$header->bulan_gaji] ?? $header->bulan_gaji) . ' ' . $header->tahun_gaji;
    @endphp

    <div class="mb-4 overflow-hidden bg-canvas border border-hairline rounded-xl dark:bg-gray-900 dark:border-gray-700">

        {{-- IDENTITAS DOKTER --}}
        <div class="px-4 py-3 border-b border-hairline bg-surface-card dark:bg-gray-800 dark:border-gray-700">
            <div class="flex flex-wrap items-baseline justify-between gap-x-6 gap-y-1">
                <h3 class="text-base font-bold text-ink dark:text-gray-100">{{ $header->dr_name }}</h3>
                <div class="flex flex-wrap gap-x-5 gap-y-1 text-xs text-muted dark:text-gray-400">
                    <span>Periode Jasa: <span class="font-medium text-body dark:text-gray-200">{{ $periodeJasa }}</span></span>
                    <span>Dibayarkan: <span class="font-medium text-body dark:text-gray-200">{{ $periodeGaji }}</span></span>
                    <span>Jumlah Baris: <span class="font-medium text-body dark:text-gray-200">{{ $satuDokter['lampiran']->count() }} transaksi</span></span>
                </div>
            </div>
        </div>

        {{-- Catatan tanggal WAJIB ada, bukan sekadar keterangan sopan: tanpa itu
             baris rawat inap yang bertanggal bulan lain terbaca sebagai salah
             periode, padahal justru begitu cara jasanya dihitung. --}}
        <div class="px-4 py-2 text-xs border-b border-hairline bg-blue-50 text-blue-900 dark:bg-blue-900/20 dark:text-blue-200 dark:border-gray-700">
            Tanggal di bawah adalah <strong>tanggal layanan</strong> (visite, konsultasi, tindakan, operasi,
            atau kunjungan). Jasa rawat inap masuk periode gaji saat pasien <strong>pulang</strong>, sehingga
            baris rawat inap dapat bertanggal bulan sebelum periode jasa di atas.
        </div>

        {{-- TABEL PER KOMPONEN --}}
        @foreach ($satuDokter['perKomponen'] as $descDoc => $barisKomponen)
            @php
                $kapita = in_array($barisKomponen->first()['group_doc'], $grupKapita, true);
                $subtotal = $barisKomponen->sum('nominal');
                $pasien = $barisKomponen->sum('pasien');
            @endphp

            <div class="border-b border-hairline dark:border-gray-700">
                <div class="flex flex-wrap items-baseline justify-between gap-x-4 px-4 py-2 bg-surface-soft dark:bg-gray-800/60">
                    <div class="text-sm font-semibold text-ink dark:text-gray-100">
                        {{ $barisKomponen->first()['label'] }}
                        @if ($kapita)
                            <span class="ml-1 px-1.5 py-0.5 text-xs font-medium rounded bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200">
                                dasar hitung per kapita
                            </span>
                        @endif
                    </div>
                    <div class="text-xs text-muted dark:text-gray-400">
                        {{ $pasien }} pasien &middot;
                        <span class="font-semibold tabular-nums text-body dark:text-gray-200">{{ $rupiah($subtotal) }}</span>
                    </div>
                </div>

                @if ($kapita)
                    <div class="px-4 py-1.5 text-xs border-b border-hairline bg-amber-50 text-amber-900 dark:bg-amber-900/20 dark:text-amber-200 dark:border-gray-700">
                        Dokter ini dibayar per kepala. Nominal di bawah adalah tarif komponen dan
                        <strong>tidak masuk slip</strong>; yang dipakai hanya jumlah pasiennya.
                    </div>
                @endif

                {{-- table-fixed WAJIB di sini. Dengan table-layout otomatis, lebar
                     w-* cuma usulan: sel ber-whitespace-nowrap tetap melebarkan
                     kolomnya sendiri, sehingga satu No. SEP panjang menggusur
                     kolom Nama Pasien. Dengan fixed, lebarnya benar-benar dipatuhi
                     dan kelebihan isi dipotong (truncate) — bukan menggeser
                     tetangganya.

                     Lebar diukur dari data asli Juli 2026 (4.688 baris), bukan
                     dikira-kira: tgl 10 huruf, No. RM 8, klaim 12 ("JASA RAHARJA"),
                     no. transaksi 6, nominal 9 ("3.150.000"), nama 30 (p95 21).
                     No. SEP p95 19 tapi maksimalnya 33 — sebagian berisi teks bebas
                     seperti "TTD DR.RADIKA/0184R...". Kolomnya dipatok cukup untuk
                     yang 19 dan sisanya dipotong; melebarkannya demi segelintir
                     baris berarti mengorbankan kolom nama di SEMUA baris. Yang
                     terpotong tetap terbaca lewat tooltip. --}}
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
                            @foreach ($barisKomponen as $baris)
                                <tr class="border-t border-hairline hover:bg-surface-soft dark:border-gray-700 dark:hover:bg-gray-800/50">
                                    <td class="px-2 py-1.5 text-right tabular-nums text-muted dark:text-gray-400">{{ $loop->iteration }}</td>
                                    <td class="px-2 py-1.5 whitespace-nowrap tabular-nums">{{ $baris['tgl'] }}</td>
                                    <td class="px-2 py-1.5 whitespace-nowrap tabular-nums">{{ $baris['reg_no'] !== '' ? $baris['reg_no'] : '—' }}</td>
                                    <td class="px-3 py-1.5 truncate text-ink dark:text-gray-100" title="{{ $baris['nama'] }}">{{ $baris['nama'] !== '' ? $baris['nama'] : '—' }}</td>
                                    <td class="px-2 py-1.5 truncate" title="{{ $baris['klaim'] }}">
                                        {{ $baris['klaim'] !== '' ? $baris['klaim'] : '—' }}
                                        @if ($baris['klaim_status'] === 'BPJS')
                                            <span class="ml-1 px-1 py-0.5 text-xs font-medium rounded bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200">BPJS</span>
                                        @endif
                                    </td>
                                    {{-- SEP kosong itu WAJAR, bukan data hilang: pasien umum
                                         memang tidak punya, dan klinik tidak menyimpan kolomnya. --}}
                                    <td class="px-2 py-1.5 truncate tabular-nums text-muted dark:text-gray-400" title="{{ $baris['no_sep'] }}">{{ $baris['no_sep'] !== '' ? $baris['no_sep'] : '—' }}</td>
                                    <td class="px-2 py-1.5 whitespace-nowrap tabular-nums text-muted dark:text-gray-400">{{ $baris['txn_no'] }}</td>
                                    <td class="px-3 py-1.5 text-right tabular-nums whitespace-nowrap">{{ $rupiah($baris['nominal']) }}</td>
                                </tr>
                            @endforeach
                            <tr class="border-t-2 border-gray-400 font-semibold bg-surface-soft dark:border-gray-600 dark:bg-gray-800/60">
                                <td colspan="7" class="px-3 py-2 text-right">Subtotal {{ $barisKomponen->first()['label'] }}</td>
                                <td class="px-3 py-2 text-right tabular-nums whitespace-nowrap">{{ $rupiah($subtotal) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        @endforeach

        {{-- REKONSILIASI — bagian terpenting.
             Lampiran ditarik LIVE dari transaksi, sedangkan slip adalah snapshot.
             Keduanya bisa berbeda kalau transaksi dikoreksi setelah slip dibuat,
             atau kalau bagian keuangan menambah baris jasa manual yang memang
             tidak punya pasien. Selisihnya ditampilkan apa adanya — angka yang
             tidak cocok itu justru informasi. --}}
        <div class="px-4 py-3 bg-surface-card dark:bg-gray-800">
            <div class="mb-1.5 text-xs font-semibold tracking-wider uppercase text-muted dark:text-gray-400">
                Rekonsiliasi dengan Slip
            </div>
            <table class="w-full text-sm text-body dark:text-gray-300">
                <tr>
                    <td class="py-1">Total nominal transaksi pada lampiran</td>
                    <td class="py-1 w-40 text-right tabular-nums">{{ $rupiah($angka['totalTransaksi']) }}</td>
                </tr>
                @if ($angka['kapitaSlip'] != 0.0 || $angka['totalDasarKapita'] != 0.0)
                    <tr>
                        <td class="py-1">
                            Jasa per kapita pada slip
                            <span class="text-xs text-muted dark:text-gray-400">
                                (dasar hitung {{ $rupiah($angka['totalDasarKapita']) }} tidak dijumlahkan)
                            </span>
                        </td>
                        <td class="py-1 text-right tabular-nums">{{ $rupiah($angka['kapitaSlip']) }}</td>
                    </tr>
                @endif
                <tr class="font-bold border-t border-gray-400 text-ink dark:border-gray-600 dark:text-gray-100">
                    <td class="py-1.5">Jasa pada Slip Gaji</td>
                    <td class="py-1.5 text-right tabular-nums">{{ $rupiah($angka['jasaSlip']) }}</td>
                </tr>
                @if (round($angka['selisih'], 0) != 0.0)
                    <tr class="text-amber-800 dark:text-amber-300">
                        <td class="py-1">
                            Selisih
                            <span class="text-xs">
                                &mdash; dari baris jasa yang ditambahkan manual pada slip, atau dari
                                transaksi yang berubah setelah slip dibuat.
                            </span>
                        </td>
                        <td class="py-1 font-bold text-right tabular-nums">{{ $rupiah($angka['selisih']) }}</td>
                    </tr>
                @endif
            </table>
        </div>
    </div>
@empty
    <div class="p-6 text-sm text-center border border-dashed rounded-xl text-muted border-gray-300 dark:text-gray-400 dark:border-gray-700">
        Tidak ada transaksi pasien pada periode ini.
    </div>
@endforelse
