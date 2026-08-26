<x-downtime.halaman kode="RJ-EMR-02" judul="Asesmen Medis, Diagnosa & Terapi Rawat Jalan"
    subjudul="Pengganti tab Pemeriksaan (dokter), Diagnosa & Perencanaan pada EMR Rawat Jalan"
    unit="Dokter poli rawat jalan" entriUlang="Pelayanan Rawat Jalan > EMR > tab Pemeriksaan, Diagnosa, Perencanaan"
    identitas="ringkas" :break="$dtBreak ?? false">

    <div class="dt-sec">A. Anamnesis</div>
    <table class="dt-tbl dt-tbl-kecil">
        <tr>
            <td class="dt-tbl-label" style="width:20%;">Riwayat penyakit sekarang</td>
            <td class="dt-isi">&nbsp;</td>
        </tr>
        <tr>
            <td class="dt-tbl-label">Riwayat penyakit dahulu / keluarga</td>
            <td class="dt-isi">&nbsp;</td>
        </tr>
        <tr>
            <td class="dt-tbl-label">Alergi</td>
            <td class="dt-isi">&nbsp;</td>
        </tr>
    </table>

    <div class="dt-sec">B. Pemeriksaan Fisik</div>
    <table class="dt-tbl dt-tbl-kecil">
        <tr>
            <th style="width:22%;">Regio / Sistem</th>
            <th style="width:14%;">Normal</th>
            <th>Kelainan / Deskripsi</th>
        </tr>
        @foreach (['Kepala, wajah, mata & THT', 'Leher', 'Thorax — jantung & paru', 'Abdomen', 'Ekstremitas'] as $regio)
            <tr>
                <td>{{ $regio }}</td>
                <td class="dt-tengah dt-isi"><span class="dt-box"></span></td>
                <td class="dt-isi">&nbsp;</td>
            </tr>
        @endforeach
        <tr>
            <td class="dt-tbl-label">Status lokalis / hasil penunjang</td>
            <td class="dt-isi-2" colspan="2">&nbsp;</td>
        </tr>
    </table>

    <div class="dt-sec">C. Diagnosa</div>
    <table class="dt-tbl dt-tbl-kecil">
        <tr>
            <th style="width:5%;">No</th>
            <th style="width:16%;">Jenis</th>
            <th style="width:14%;">Kode ICD-10</th>
            <th>Diagnosa</th>
        </tr>
        @foreach ([0, 1, 2] as $barisDiagnosa)
            <tr>
                <td class="dt-tengah">{{ $barisDiagnosa + 1 }}</td>
                <td class="dt-isi">
                    <span class="dt-opsi"><span class="dt-box"></span>Primer</span>
                    <span class="dt-opsi"><span class="dt-box"></span>Sekunder</span>
                </td>
                <td class="dt-isi">&nbsp;</td>
                <td class="dt-isi">&nbsp;</td>
            </tr>
        @endforeach
    </table>
    <div class="dt-note">Kode ICD-10 boleh dikosongkan; nama diagnosa wajib ditulis lengkap untuk koder.</div>

    <div class="dt-sec">D. Prosedur / Tindakan</div>
    <table class="dt-tbl dt-tbl-kecil">
        <tr>
            <th style="width:5%;">No</th>
            <th style="width:14%;">Kode ICD-9-CM</th>
            <th>Tindakan</th>
            <th style="width:12%;">Jam</th>
            <th style="width:18%;">Pelaksana</th>
        </tr>
        @foreach ([0] as $barisTindakan)
            <tr>
                <td class="dt-tengah">{{ $barisTindakan + 1 }}</td>
                <td class="dt-isi">&nbsp;</td>
                <td class="dt-isi">&nbsp;</td>
                <td class="dt-isi">&nbsp;</td>
                <td class="dt-isi">&nbsp;</td>
            </tr>
        @endforeach
    </table>

    <div class="dt-sec">E. Terapi & Edukasi</div>
    <table class="dt-tbl dt-tbl-kecil">
        <tr>
            <td class="dt-tbl-label" style="width:20%;">Terapi / instruksi</td>
            <td class="dt-isi-2">&nbsp;</td>
        </tr>
        <tr>
            <td class="dt-tbl-label">Edukasi kepada pasien / keluarga</td>
            <td class="dt-isi">&nbsp;</td>
        </tr>
    </table>

    <div class="dt-sec">F. Tindak Lanjut & Rencana Pemulangan</div>
    <table class="dt-tbl dt-tbl-kecil">
        <tr>
            <td class="dt-tbl-label" style="width:20%;">Tindak lanjut</td>
            <td class="dt-isi" colspan="3">
                <span class="dt-opsi"><span class="dt-box"></span>Selesai / sembuh</span>
                <span class="dt-opsi"><span class="dt-box"></span>Kontrol ulang</span>
                <span class="dt-opsi"><span class="dt-box"></span>Rawat inap</span>
                <span class="dt-opsi"><span class="dt-box"></span>Rujuk ke RS lain</span>
                <span class="dt-opsi"><span class="dt-box"></span>Pulang paksa</span>
                <span class="dt-opsi"><span class="dt-box"></span>Meninggal</span>
            </td>
        </tr>
        <tr>
            <td class="dt-tbl-label">Tanggal kontrol</td>
            <td class="dt-isi" style="width:30%;">&nbsp;</td>
            <td class="dt-tbl-label" style="width:20%;">Poli / dokter kontrol</td>
            <td class="dt-isi" style="width:30%;">&nbsp;</td>
        </tr>
        <tr>
            <td class="dt-tbl-label">Pelayanan berkelanjutan</td>
            <td class="dt-isi" colspan="3">
                <span class="dt-opsi"><span class="dt-box"></span>Tidak perlu</span>
                <span class="dt-opsi"><span class="dt-box"></span>Perawatan luka</span>
                <span class="dt-opsi"><span class="dt-box"></span>Fisioterapi</span>
                <span class="dt-opsi"><span class="dt-box"></span>Gizi</span>
                <span class="dt-opsi"><span class="dt-box"></span>Lainnya</span>
            </td>
        </tr>
        <tr>
            <td class="dt-tbl-label">Alasan rujukan / rawat inap</td>
            <td class="dt-isi" colspan="3">&nbsp;</td>
        </tr>
    </table>
    <div class="dt-note">Pasien BPJS yang kontrol: SKDP dibuat ulang di Jadwal Kontrol Pasien setelah sistem pulih.</div>

    <x-downtime.ttd tempat="Tulungagung" :kolom="['Perawat Pendamping' => 'Paraf', 'Dokter Pemeriksa (DPJP)' => 'Nama & SIP']" />

</x-downtime.halaman>
