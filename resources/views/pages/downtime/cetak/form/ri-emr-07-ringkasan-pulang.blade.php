<x-downtime.halaman kode="RI-EMR-07" judul="Ringkasan Pulang (Resume Medis) Rawat Inap"
    subjudul="Dokumen wajib rekam medis & syarat berkas klaim BPJS — diisi DPJP saat pasien pulang" unit="DPJP"
    entriUlang="Daftar Rawat Inap > EMR > Ringkasan Pulang; berkas klaim menyusul setelah lengkap"
    :break="$dtBreak ?? false">

    <x-downtime.identitas variant="lengkap" />

    <div class="dt-sec">A. Data Perawatan</div>
    <table class="dt-tbl dt-tbl-kecil">
        <tr>
            <td class="dt-tbl-label" style="width:20%;">Tanggal masuk</td>
            <td class="dt-isi" style="width:30%;">&nbsp;</td>
            <td class="dt-tbl-label" style="width:20%;">Tanggal keluar</td>
            <td class="dt-isi" style="width:30%;">&nbsp;</td>
        </tr>
        <tr>
            <td class="dt-tbl-label">Lama rawat (hari)</td>
            <td class="dt-isi">&nbsp;</td>
            <td class="dt-tbl-label">Ruangan / kelas terakhir</td>
            <td class="dt-isi">&nbsp;</td>
        </tr>
        <tr>
            <td class="dt-tbl-label">Indikasi rawat inap</td>
            <td class="dt-isi" colspan="3">&nbsp;</td>
        </tr>
        <tr>
            <td class="dt-tbl-label">Riwayat penyakit &amp; pemeriksaan fisik singkat</td>
            <td class="dt-isi-2" colspan="3">&nbsp;</td>
        </tr>
        <tr>
            <td class="dt-tbl-label">Hasil penunjang penting</td>
            <td class="dt-isi" colspan="3">&nbsp;</td>
        </tr>
    </table>

    <div class="dt-sec">B. Diagnosa & Tindakan</div>
    <table class="dt-tbl dt-tbl-kecil">
        <tr>
            <th style="width:5%;">No</th>
            <th style="width:18%;">Jenis</th>
            <th style="width:14%;">Kode</th>
            <th>Diagnosa / Tindakan</th>
        </tr>
        <tr>
            <td class="dt-tengah">1</td>
            <td>Diagnosa utama (ICD-10)</td>
            <td class="dt-isi">&nbsp;</td>
            <td class="dt-isi">&nbsp;</td>
        </tr>
        @foreach ([2, 3] as $nomor)
            <tr>
                <td class="dt-tengah">{{ $nomor }}</td>
                <td>Diagnosa sekunder (ICD-10)</td>
                <td class="dt-isi">&nbsp;</td>
                <td class="dt-isi">&nbsp;</td>
            </tr>
        @endforeach
        @foreach ([4, 5] as $nomor)
            <tr>
                <td class="dt-tengah">{{ $nomor }}</td>
                <td>Tindakan / prosedur (ICD-9-CM)</td>
                <td class="dt-isi">&nbsp;</td>
                <td class="dt-isi">&nbsp;</td>
            </tr>
        @endforeach
    </table>
    <div class="dt-note">
        Diagnosa &amp; tindakan pada resume harus sama dengan yang dientri ke SIMRS dan berkas klaim &mdash;
        perbedaan menyebabkan klaim BPJS dikembalikan (pending).
    </div>

    <div class="dt-sec">C. Terapi Selama Dirawat & Obat Pulang</div>
    <table class="dt-tbl dt-tbl-kecil">
        <tr>
            <td class="dt-tbl-label" style="width:20%;">Terapi selama dirawat</td>
            <td class="dt-isi-2" colspan="3">&nbsp;</td>
        </tr>
        <tr>
            <td class="dt-tbl-label">Obat pulang (nama, dosis, aturan pakai)</td>
            <td class="dt-isi-2" colspan="3">&nbsp;</td>
        </tr>
    </table>

    <div class="dt-sec">D. Kondisi Pulang & Tindak Lanjut</div>
    <table class="dt-tbl dt-tbl-kecil">
        <tr>
            <td class="dt-tbl-label" style="width:20%;">Cara keluar</td>
            <td class="dt-isi" colspan="3">
                <span class="dt-opsi"><span class="dt-box"></span>Sembuh</span>
                <span class="dt-opsi"><span class="dt-box"></span>Membaik</span>
                <span class="dt-opsi"><span class="dt-box"></span>Pulang paksa</span>
                <span class="dt-opsi"><span class="dt-box"></span>Rujuk</span>
                <span class="dt-opsi"><span class="dt-box"></span>Meninggal &lt; 48 jam</span>
                <span class="dt-opsi"><span class="dt-box"></span>Meninggal &gt;= 48 jam</span>
            </td>
        </tr>
        <tr>
            <td class="dt-tbl-label">Kontrol (tanggal / poli / dokter)</td>
            <td class="dt-isi" style="width:30%;">&nbsp;</td>
            <td class="dt-tbl-label" style="width:20%;">Edukasi / diet di rumah</td>
            <td class="dt-isi" style="width:30%;">&nbsp;</td>
        </tr>
    </table>
    <div class="dt-note">
        Pasien BPJS yang direncanakan kontrol: SKDP dibuat ulang di Jadwal Kontrol Pasien setelah sistem pulih.
    </div>

    <x-downtime.ttd tempat="Tulungagung" :kolom="['Pasien / Keluarga' => 'Menerima salinan', 'DPJP' => 'Nama & SIP']" />

</x-downtime.halaman>
