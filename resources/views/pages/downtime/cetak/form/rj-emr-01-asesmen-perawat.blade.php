<x-downtime.halaman kode="RJ-EMR-01" judul="Asesmen Awal Keperawatan Rawat Jalan"
    subjudul="Pengganti tab Anamnesa & Pemeriksaan (perawat) pada EMR Rawat Jalan" unit="Perawat poli rawat jalan"
    entriUlang="Pelayanan Rawat Jalan > EMR > tab Anamnesa & Pemeriksaan" :break="$dtBreak ?? false">

    <x-downtime.identitas variant="ringkas" />

    <div class="dt-sec">A. Pengkajian Awal</div>
    <table class="dt-tbl">
        <tr>
            <td class="dt-tbl-label" style="width:20%;">Perawat penerima</td>
            <td class="dt-isi" style="width:30%;">&nbsp;</td>
            <td class="dt-tbl-label" style="width:20%;">Jam datang</td>
            <td class="dt-isi" style="width:30%;">&nbsp;</td>
        </tr>
        <tr>
            <td class="dt-tbl-label">Keluhan utama</td>
            <td class="dt-isi-2" colspan="3">&nbsp;</td>
        </tr>
    </table>

    <div class="dt-sec">B. Tanda Vital</div>
    <table class="dt-tbl">
        <tr>
            <td class="dt-tbl-label" style="width:20%;">Keadaan umum</td>
            <td class="dt-isi" style="width:30%;">&nbsp;</td>
            <td class="dt-tbl-label" style="width:20%;">Tingkat kesadaran</td>
            <td class="dt-isi" style="width:30%;">
                <span class="dt-opsi"><span class="dt-box"></span>Composmentis</span>
                <span class="dt-opsi"><span class="dt-box"></span>Apatis</span>
                <span class="dt-opsi"><span class="dt-box"></span>Somnolen</span>
                <span class="dt-opsi"><span class="dt-box"></span>Stupor</span>
                <span class="dt-opsi"><span class="dt-box"></span>Koma</span>
            </td>
        </tr>
    </table>
    <table class="dt-tbl">
        <tr>
            <th style="width:14.28%;">Sistolik (mmHg)</th>
            <th style="width:14.28%;">Diastolik (mmHg)</th>
            <th style="width:14.28%;">Nadi (x/mnt)</th>
            <th style="width:14.28%;">Nafas (x/mnt)</th>
            <th style="width:14.28%;">Suhu (&deg;C)</th>
            <th style="width:14.28%;">SPO2 (%)</th>
            <th>GDA (g/dl)</th>
        </tr>
        <tr>
            <td class="dt-isi-2">&nbsp;</td>
            <td class="dt-isi-2">&nbsp;</td>
            <td class="dt-isi-2">&nbsp;</td>
            <td class="dt-isi-2">&nbsp;</td>
            <td class="dt-isi-2">&nbsp;</td>
            <td class="dt-isi-2">&nbsp;</td>
            <td class="dt-isi-2">&nbsp;</td>
        </tr>
    </table>

    <div class="dt-sec">C. Nutrisi</div>
    <table class="dt-tbl">
        <tr>
            <th style="width:20%;">Berat Badan (kg)</th>
            <th style="width:20%;">Tinggi Badan (cm)</th>
            <th style="width:20%;">IMT (kg/m&sup2;)</th>
            <th style="width:20%;">Lingkar Kepala (cm)</th>
            <th>Lingkar Lengan Atas (cm)</th>
        </tr>
        <tr>
            <td class="dt-isi-2">&nbsp;</td>
            <td class="dt-isi-2">&nbsp;</td>
            <td class="dt-isi-2">&nbsp;</td>
            <td class="dt-isi-2">&nbsp;</td>
            <td class="dt-isi-2">&nbsp;</td>
        </tr>
    </table>
    <div class="dt-note">IMT dihitung otomatis oleh SIMRS saat entri ulang — pengisian manual bersifat perkiraan.</div>

    <div class="dt-sec">D. Riwayat & Alergi</div>
    <table class="dt-tbl">
        <tr>
            <td class="dt-tbl-label" style="width:20%;">Riwayat penyakit dahulu</td>
            <td class="dt-isi-2" colspan="3">&nbsp;</td>
        </tr>
        <tr>
            <td class="dt-tbl-label">Ada alergi?</td>
            <td class="dt-isi" style="width:30%;">
                <span class="dt-opsi"><span class="dt-box"></span>Tidak</span>
                <span class="dt-opsi"><span class="dt-box"></span>Ya</span>
            </td>
            <td class="dt-tbl-label" style="width:20%;">Jenis alergi (obat / makanan / lain)</td>
            <td class="dt-isi" style="width:30%;">&nbsp;</td>
        </tr>
    </table>

    <div class="dt-sec">E. Status Psikologis & Fungsional</div>
    <table class="dt-tbl">
        <tr>
            <td class="dt-tbl-label" style="width:20%;">Status mental</td>
            <td class="dt-isi" colspan="3">
                <span class="dt-opsi"><span class="dt-box"></span>Tenang / kooperatif</span>
                <span class="dt-opsi"><span class="dt-box"></span>Cemas</span>
                <span class="dt-opsi"><span class="dt-box"></span>Takut</span>
                <span class="dt-opsi"><span class="dt-box"></span>Marah</span>
                <span class="dt-opsi"><span class="dt-box"></span>Sedih</span>
                <span class="dt-opsi"><span class="dt-box"></span>Lainnya</span>
            </td>
        </tr>
        <tr>
            <td class="dt-tbl-label">Fungsional (centang bila ada)</td>
            <td class="dt-isi" style="width:30%;">
                <span class="dt-opsi"><span class="dt-box"></span>Alat bantu</span>
                <span class="dt-opsi"><span class="dt-box"></span>Cacat tubuh</span>
                <span class="dt-opsi"><span class="dt-box"></span>Prothesa</span>
            </td>
            <td class="dt-tbl-label" style="width:20%;">Keterangan fungsional</td>
            <td class="dt-isi" style="width:30%;">&nbsp;</td>
        </tr>
    </table>

    <div class="dt-sec">F. Skrining Batuk (TB)</div>
    <table class="dt-tbl">
        <tr>
            <th>Gejala / Riwayat</th>
            <th style="width:8%;">Ya</th>
            <th style="width:8%;">Tidak</th>
            <th style="width:34%;">Keterangan</th>
        </tr>
        @foreach ([
            'Riwayat demam',
            'Berkeringat malam tanpa aktivitas',
            'Riwayat bepergian ke daerah wabah',
            'Berat badan turun tanpa sebab',
            'Pembesaran kelenjar getah bening',
            'Pemakaian obat jangka panjang',
        ] as $gejala)
            <tr>
                <td>{{ $gejala }}</td>
                <td class="dt-tengah dt-isi"><span class="dt-box"></span></td>
                <td class="dt-tengah dt-isi"><span class="dt-box"></span></td>
                <td class="dt-isi">&nbsp;</td>
            </tr>
        @endforeach
    </table>

    <x-downtime.ttd :kolom="['Perawat Poli' => 'Pengkaji']" />

</x-downtime.halaman>
