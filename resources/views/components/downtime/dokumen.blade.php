{{-- Shell dokumen cetak Formulir Manual Down Time.
     Memakai layout cetak standar repo (x-pdf.layout-a4-with-out-background):
     kop RS + A4 tanpa ornamen latar. Kop halaman pertama disediakan layout;
     formulir berikutnya dalam bundel mencetak kop sendiri (lihat x-downtime.halaman).

     Kelas dt-* di bawah ini khusus formulir manual — ditulis eksplisit (bukan
     kelas Tailwind arbitrary) supaya hasil dompdf tidak bergantung isi build CSS.
     Judul dokumen sengaja tidak dititipkan ke layout: tiap formulir mencetak
     judulnya sendiri (lihat x-downtime.halaman) supaya seragam di mode bundel. --}}
@props([
    // Varian blok identitas pasien formulir PERTAMA ('lengkap'/'ringkas'/null).
    // Formulir berikutnya dalam bundel mencetak kopnya sendiri di x-downtime.halaman.
    'identitasAwal' => null,
])

<x-pdf.layout-a4-with-out-background :title="null" :showGaris="true">

    @if (filled($identitasAwal))
        <x-slot name="patientData">
            <x-downtime.identitas :variant="$identitasAwal" />
        </x-slot>
    @endif

    <style>
        /* Formulir isian butuh ruang lebih banyak daripada dokumen naratif:
           margin halaman & padding konten layout dipersempit khusus dokumen ini
           supaya satu formulir tetap muat 1 lembar A4. */
        @page {
            margin: 16px 0 14px 0;
        }

        .pdf-content {
            padding: 14px 22px 14px 22px;
        }

        .dt-halaman {
            position: relative;
        }

        .dt-break {
            page-break-before: always;
        }

        /* ── Judul formulir (mengikuti gaya judul layout cetak standar) ── */
        .dt-judul-wrap {
            margin-top: 8px;
            margin-bottom: 6px;
        }

        .dt-judul {
            font-size: 13px;
            font-weight: bold;
            text-align: center;
            text-decoration: underline;
            text-transform: uppercase;
        }

        .dt-subjudul {
            font-size: 9px;
            text-align: center;
            color: #555;
            margin-top: 2px;
        }

        .dt-pita {
            margin-top: 6px;
            border: 1px solid #b91c1c;
            background-color: #fef2f2;
            color: #7f1d1d;
            font-size: 8.5px;
            font-weight: bold;
            text-align: center;
            padding: 3px 6px;
            text-transform: uppercase;
        }

        /* ── Blok identitas pasien di kop (kiri, sejajar logo) ── */
        .dt-identitas {
            width: 100%;
            border-collapse: collapse;
        }

        .dt-identitas td {
            font-size: 8.5px;
            padding: 0 2px 1px 0;
            vertical-align: bottom;
        }

        .dt-identitas-label {
            width: 34%;
            white-space: nowrap;
            color: #444;
        }

        .dt-identitas-titik {
            width: 8px;
        }

        /* ── Kotak kode formulir ── */
        .dt-kode {
            width: 190px;
            border-collapse: collapse;
        }

        .dt-kode td {
            border: 1px solid #9aa89e;
            padding: 2px 5px;
            font-size: 8.5px;
        }

        /* ── Judul bagian ── */
        .dt-sec {
            background-color: #eef2f0;
            border: 1px solid #9aa89e;
            border-left: 3px solid #157547;
            font-size: 9.5px;
            font-weight: bold;
            text-transform: uppercase;
            padding: 2px 6px;
            margin-top: 6px;
            margin-bottom: 3px;
        }

        /* ── Tabel isian ── */
        .dt-tbl {
            width: 100%;
            border-collapse: collapse;
        }

        .dt-tbl th,
        .dt-tbl td {
            border: 1px solid #9aa89e;
            padding: 2px 4px;
            font-size: 9px;
            vertical-align: top;
        }

        .dt-tbl th {
            background-color: #f3f5f4;
            font-weight: bold;
            text-align: center;
        }

        .dt-tbl-label {
            background-color: #fafbfa;
            width: 26%;
        }

        /* Formulir padat (isi banyak) — padding sel dipangkas agar tetap 1 lembar.
           Ukuran huruf tidak diubah supaya tetap terbaca saat difotokopi. */
        .dt-padat .dt-tbl th,
        .dt-padat .dt-tbl td {
            padding: 0 4px;
        }

        /* Tabel register (banyak kolom) — huruf lebih kecil agar muat A4 portrait */
        .dt-tbl-kecil th,
        .dt-tbl-kecil td {
            font-size: 7.5px;
            padding: 2px 3px;
        }

        .dt-isi {
            height: 14px;
        }

        .dt-isi-2 {
            height: 28px;
        }

        .dt-isi-3 {
            height: 42px;
        }

        .dt-isi-4 {
            height: 56px;
        }

        .dt-isi-6 {
            height: 84px;
        }

        /* ── Tabel polos (tanpa garis) untuk baris titik-titik ── */
        .dt-plain {
            width: 100%;
            border-collapse: collapse;
        }

        .dt-plain td {
            font-size: 9px;
            padding: 1px 2px;
            vertical-align: bottom;
        }

        .dt-garis {
            border-bottom: 1px dotted #6b7280;
            height: 11px;
        }

        /* ── Kotak centang ── */
        .dt-box {
            display: inline-block;
            width: 8px;
            height: 8px;
            border: 1px solid #374151;
            margin-right: 3px;
            vertical-align: middle;
        }

        .dt-opsi {
            white-space: nowrap;
            margin-right: 10px;
            display: inline-block;
        }

        /* ── Tanda tangan ── */
        .dt-ttd {
            width: 100%;
            border-collapse: collapse;
            margin-top: 6px;
        }

        .dt-ttd td {
            font-size: 9px;
            text-align: center;
            vertical-align: top;
            padding: 0 6px;
        }

        .dt-ttd-ruang {
            height: 30px;
        }

        .dt-ttd-nama {
            border-top: 1px solid #6b7280;
            padding-top: 2px;
        }

        /* ── Garis potong (kwitansi rangkap / etiket) ── */
        .dt-potong {
            border-top: 1px dashed #6b7280;
            text-align: center;
            font-size: 7px;
            color: #6b7280;
            margin: 10px 0 8px 0;
            padding-top: 2px;
        }

        /* ── Catatan kecil ── */
        .dt-note {
            font-size: 8px;
            color: #555;
            font-style: italic;
            margin-top: 4px;
        }

        .dt-kecil {
            font-size: 8px;
            color: #555;
        }

        .dt-tengah {
            text-align: center;
        }

        .dt-kanan {
            text-align: right;
        }

        .dt-tebal {
            font-weight: bold;
        }

        /* ── Blok entri ulang (wajib ada di tiap formulir) ── */
        .dt-entri {
            margin-top: 6px;
            border: 1px dashed #b45309;
            background-color: #fffbeb;
            padding: 4px 6px;
        }

        .dt-entri-judul {
            font-size: 8.5px;
            font-weight: bold;
            color: #78350f;
            text-transform: uppercase;
        }

        /* ── Footer formulir (inline, bukan fixed: dalam bundel tiap formulir
              punya kode sendiri sehingga footer tidak boleh document-level) ── */
        .dt-footer {
            margin-top: 4px;
            border-top: 1px solid #9aa89e;
            padding-top: 3px;
            width: 100%;
            border-collapse: collapse;
        }

        .dt-footer td {
            font-size: 7.5px;
            color: #555;
            padding-top: 3px;
        }
    </style>

    {{ $slot }}

</x-pdf.layout-a4-with-out-background>
