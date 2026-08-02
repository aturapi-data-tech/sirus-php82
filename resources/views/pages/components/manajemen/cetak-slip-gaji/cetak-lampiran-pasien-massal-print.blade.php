{{-- resources/views/pages/components/manajemen/cetak-slip-gaji/cetak-lampiran-pasien-massal-print.blade.php --}}
{{-- Template print: Lampiran Rincian Pasien — CETAK MASSAL satu periode.

     Satu berkas berisi lampiran banyak dokter; tiap dokter MULAI di halaman
     baru, tapi lampirannya sendiri boleh memakai berapa pun halaman yang
     dibutuhkan. Berbeda dari cetak massal slip yang tiap dokter pas satu
     halaman — daftar pasien tidak bisa dipaksa muat.

     KOMPONEN LAYOUT DIPAKAI SEKALI UNTUK SELURUH BERKAS, BUKAN PER DOKTER.
     Komponen layout-a4-with-out-background memancarkan satu dokumen html utuh
     lengkap dengan blok <style> berisi build Tailwind ~188 KB. Memanggilnya per
     dokter berarti dokumen itu terulang sebanyak dokternya — hasilnya memang
     masih tercetak benar, tapi biayanya melonjak: diukur 2026-08-02 untuk 3
     dokter / 360 baris, HTML-nya 781 KB dengan 3 blok <style> dan dompdf butuh
     72,6 detik. Untuk 36 dokter angkanya tidak masuk akal. Dipanggil sekali,
     CSS-nya cuma disisipkan sekali dan sisanya tinggal isi.

     Konsekuensinya kop dokter PERTAMA datang dari slot patientData milik
     komponen, sedangkan dokter kedua dan seterusnya menyusun kopnya sendiri di
     dalam perulangan. Bentuk cetaknya sama; yang berbeda hanya dari mana
     markupnya berasal.

     Butuh: $lampiranList (koleksi objek {header, detail, lampiran, grupKapita}),
     $judulPeriode, $tanggalCetak. --}}

@php
    // Dokter pertama disiapkan di luar komponen: slot patientData dirender
    // sebelum perulangan di bawah menimpa variabel-variabel ini.
    $lampiranPertama = $lampiranList->first();

    $header = $lampiranPertama['header'];
    $lampiran = $lampiranPertama['lampiran'];
@endphp

<x-pdf.layout-a4-with-out-background title="Lampiran Rincian Pasien — Slip Gaji Dokter" :showGaris="false">
    <x-slot name="patientData">
        @include('pages.components.manajemen.cetak-slip-gaji.identitas-lampiran')
    </x-slot>

    @foreach ($lampiranList as $satuLampiran)
        @php
            $header = $satuLampiran['header'];
            $detail = $satuLampiran['detail'];
            $lampiran = $satuLampiran['lampiran'];
            $grupKapita = $satuLampiran['grupKapita'];
        @endphp

        @if ($loop->first)
            {{-- Kop & judulnya sudah dipasang komponen di atas. --}}
            @include('pages.components.manajemen.cetak-slip-gaji.isi-lampiran')
        @else
            <div style="page-break-before: always;">
                {{-- Susunan kop disalin dari cabang patientData milik komponen
                     layout A4: identitas dokter di kiri, logo identitas RS di
                     kanan, tanpa garis pemisah. --}}
                <table width="100%" cellpadding="0" cellspacing="0">
                    <tr>
                        <td width="50%" style="vertical-align: bottom;">
                            @include('pages.components.manajemen.cetak-slip-gaji.identitas-lampiran')
                        </td>
                        <td width="50%" style="vertical-align: bottom; padding-left: 8px;">
                            <x-logo.identitas :showGaris="false" />
                        </td>
                    </tr>
                </table>

                <div
                    style="margin-top:16px; margin-bottom:12px; font-size:13px; font-weight:bold; text-align:center; text-decoration:underline;">
                    Lampiran Rincian Pasien &mdash; Slip Gaji Dokter
                </div>

                @include('pages.components.manajemen.cetak-slip-gaji.isi-lampiran')
            </div>
        @endif
    @endforeach
</x-pdf.layout-a4-with-out-background>
