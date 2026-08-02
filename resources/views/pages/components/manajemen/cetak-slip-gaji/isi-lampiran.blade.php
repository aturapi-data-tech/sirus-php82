{{-- resources/views/pages/components/manajemen/cetak-slip-gaji/isi-lampiran.blade.php --}}
{{-- Badan lampiran rincian pasien — dipakai bersama oleh cetak satuan
     (cetak-lampiran-pasien-print) dan cetak massal
     (cetak-lampiran-pasien-massal-print). Dipisah supaya keduanya tidak pernah
     berbeda isi; kop & kerangka halaman diurus pemanggilnya. Pola yang sama
     dengan isi-slip untuk slipnya.

     Ukuran cetak dipasang lewat style sebaris, bukan kelas arbitrary Tailwind —
     kelas seperti text-[9px] tidak ikut ter-build sehingga hilang di PDF.
     Kelas Tailwind biasa (border, text-gray-600, dst) tetap dipakai.

     Identitas dokter & periode TIDAK di sini — ada di partial
     identitas-lampiran yang dipasang di kop, sebelah kiri logo.

     Butuh: $header (RSTXN_GAJIDOCTORHDRS + dr_name), $detail (koleksi
     RSTXN_GAJIDOCTORDTLS milik slip), $lampiran (keluaran
     GajiDokterLampiran::baris()), $grupKapita (array GROUP_DOC),
     $tanggalCetak. --}}

@php
    use App\Support\GajiDokterLampiran;

    $rupiah = fn($nilai) => number_format((float) $nilai, 0, ',', '.');

    $perKomponen = collect($lampiran)->groupBy('desc_doc');

    // Agregasinya dikerjakan di GajiDokterLampiran, bukan di sini — lihat
    // catatan pada rekonsiliasi(). Yang tersisa di template hanya pemetaan
    // tampilan.
    $adalahKapita = fn($groupDoc) => in_array($groupDoc, $grupKapita, true);
    $angkaRekonsiliasi = GajiDokterLampiran::rekonsiliasi($lampiran, $detail, $grupKapita);
@endphp

{{-- Catatan tanggal WAJIB ada di lampiran, bukan sekadar keterangan sopan:
     tanpa itu baris rawat inap yang bertanggal bulan lain terbaca sebagai
     salah periode, padahal justru begitu cara jasanya dihitung. --}}
<div class="px-2 py-1 border border-gray-300 text-gray-700" style="font-size: 8.5px;">
    Tanggal pada lampiran ini adalah <strong>tanggal layanan</strong> (tanggal visite, konsultasi,
    tindakan, operasi, atau kunjungan). Jasa rawat inap masuk periode gaji saat pasien
    <strong>pulang</strong>, sehingga baris rawat inap dapat bertanggal bulan sebelum periode jasa di atas.
</div>

@forelse ($perKomponen as $descDoc => $barisKomponen)
    @php
        $kapita = $adalahKapita($barisKomponen->first()['group_doc']);
        $subtotal = $barisKomponen->sum('nominal');
        $pasien = $barisKomponen->sum('pasien');
    @endphp

    <div class="mt-3">
        <table class="w-full border-collapse" style="font-size: 9px;">
            <thead>
                <tr>
                    <td colspan="6" class="pt-1 pb-0.5 font-bold uppercase tracking-wide text-gray-900 border-b border-gray-800"
                        style="font-size: 10px;">
                        {{ $barisKomponen->first()['label'] }}
                        <span class="font-normal normal-case text-gray-600">
                            &mdash; {{ $pasien }} pasien, {{ $rupiah($subtotal) }}
                            @if ($kapita)
                                (dasar hitung per kapita)
                            @endif
                        </span>
                    </td>
                </tr>
                @if ($kapita)
                    <tr>
                        <td colspan="6" class="py-0.5 text-gray-700 border-b border-gray-300" style="font-size: 8.5px;">
                            Dokter ini dibayar per kepala. Nominal di bawah adalah tarif komponen dan
                            <strong>tidak masuk slip</strong>; yang dipakai hanya jumlah pasiennya.
                        </td>
                    </tr>
                @endif
                <tr class="border-b border-gray-400 text-gray-600">
                    <th class="py-1 font-normal text-left" style="width: 5%;">No</th>
                    <th class="py-1 font-normal text-left" style="width: 13%;">Tanggal</th>
                    <th class="py-1 font-normal text-left" style="width: 11%;">No. RM</th>
                    <th class="py-1 font-normal text-left" style="width: 40%;">Nama Pasien</th>
                    <th class="py-1 font-normal text-left" style="width: 13%;">No. Transaksi</th>
                    <th class="py-1 font-normal text-right" style="width: 18%;">Nominal (Rp)</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($barisKomponen as $baris)
                    <tr class="border-b border-gray-200" style="page-break-inside: avoid;">
                        <td class="py-0.5 text-gray-700">{{ $loop->iteration }}</td>
                        <td class="py-0.5 text-gray-800">{{ $baris['tgl'] }}</td>
                        <td class="py-0.5 text-gray-800">{{ $baris['reg_no'] !== '' ? $baris['reg_no'] : '-' }}</td>
                        <td class="py-0.5 text-gray-900">{{ $baris['nama'] !== '' ? $baris['nama'] : '-' }}</td>
                        <td class="py-0.5 text-gray-600">{{ $baris['txn_no'] }}</td>
                        <td class="py-0.5 text-right text-gray-900">{{ $rupiah($baris['nominal']) }}</td>
                    </tr>
                @endforeach
                <tr class="border-t border-gray-800">
                    <td colspan="5" class="py-1 text-right font-bold text-gray-900">
                        Subtotal {{ $barisKomponen->first()['label'] }}
                    </td>
                    <td class="py-1 text-right font-bold text-gray-900">{{ $rupiah($subtotal) }}</td>
                </tr>
            </tbody>
        </table>
    </div>
@empty
    <div class="mt-4 p-3 border border-dashed border-gray-400 text-center text-gray-600" style="font-size: 10px;">
        Tidak ada transaksi pasien pada periode ini.
    </div>
@endforelse

{{-- REKONSILIASI — bagian terpenting halaman ini.
     Lampiran ditarik LIVE dari transaksi, sedangkan slip adalah snapshot.
     Keduanya bisa berbeda kalau transaksi dikoreksi setelah slip dibuat,
     atau kalau bagian keuangan menambah baris jasa manual yang memang tidak
     punya pasien. Selisihnya ditampilkan apa adanya, bukan disembunyikan —
     angka yang tidak cocok itu justru informasi. --}}
<div class="mt-4" style="page-break-inside: avoid;">
    <table class="w-full border-collapse" style="font-size: 9.5px;">
        <tr class="border-t border-b border-gray-800">
            <td colspan="2" class="py-1 font-bold uppercase tracking-wide text-gray-900">
                Rekonsiliasi dengan Slip
            </td>
        </tr>
        <tr>
            <td class="py-0.5 text-gray-700">Total nominal transaksi pada lampiran</td>
            <td class="py-0.5 text-right text-gray-900" style="width: 30%;">
                {{ $rupiah($angkaRekonsiliasi['totalTransaksi']) }}
            </td>
        </tr>
        @if ($angkaRekonsiliasi['kapitaSlip'] != 0.0 || $angkaRekonsiliasi['totalDasarKapita'] != 0.0)
            <tr>
                <td class="py-0.5 text-gray-700">
                    Jasa per kapita pada slip
                    <span class="text-gray-500">
                        (dasar hitung {{ $rupiah($angkaRekonsiliasi['totalDasarKapita']) }} tidak dijumlahkan)
                    </span>
                </td>
                <td class="py-0.5 text-right text-gray-900">{{ $rupiah($angkaRekonsiliasi['kapitaSlip']) }}</td>
            </tr>
        @endif
        <tr class="border-t border-gray-400">
            <td class="py-1 font-bold text-gray-900">Jasa pada Slip Gaji</td>
            <td class="py-1 text-right font-bold text-gray-900">{{ $rupiah($angkaRekonsiliasi['jasaSlip']) }}</td>
        </tr>
        @if (round($angkaRekonsiliasi['selisih'], 0) != 0.0)
            <tr>
                <td class="py-0.5 text-gray-700">
                    Selisih
                    <span class="text-gray-500">
                        &mdash; berasal dari baris jasa yang ditambahkan manual pada slip, atau dari
                        transaksi yang berubah setelah slip dibuat.
                    </span>
                </td>
                <td class="py-0.5 text-right font-bold text-gray-900">{{ $rupiah($angkaRekonsiliasi['selisih']) }}</td>
            </tr>
        @endif
    </table>
</div>

<div class="mt-3 text-right text-gray-600" style="font-size: 8.5px;">
    {{ $tanggalCetak }}
</div>
