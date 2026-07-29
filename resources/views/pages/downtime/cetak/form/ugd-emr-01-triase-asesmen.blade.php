<x-downtime.halaman kode="UGD-EMR-01" judul="Triase & Asesmen Awal Keperawatan UGD"
    subjudul="Pengganti triase + tab Anamnesa & Pemeriksaan (perawat) pada EMR UGD" unit="Perawat UGD"
    entriUlang="Pelayanan UGD > EMR > triase, tab Anamnesa & Pemeriksaan" :break="$dtBreak ?? false">

    <x-downtime.identitas variant="ringkas" />

    <div class="dt-sec">A. Triase</div>
    <table class="dt-tbl">
        <tr>
            <td class="dt-tbl-label" style="width:20%;">Jam datang</td>
            <td class="dt-isi" style="width:30%;">&nbsp;</td>
            <td class="dt-tbl-label" style="width:20%;">Jam triase</td>
            <td class="dt-isi" style="width:30%;">&nbsp;</td>
        </tr>
        <tr>
            <td class="dt-tbl-label">Kategori triase</td>
            <td class="dt-isi" colspan="3">
                <span class="dt-opsi"><span class="dt-box"></span>P1 &mdash; Kritis (merah)</span>
                <span class="dt-opsi"><span class="dt-box"></span>P2 &mdash; Urgent (kuning)</span>
                <span class="dt-opsi"><span class="dt-box"></span>P3 &mdash; Minor (hijau)</span>
                <span class="dt-opsi"><span class="dt-box"></span>P0 &mdash; Death (hitam)</span>
            </td>
        </tr>
        <tr>
            <td class="dt-tbl-label">Cara datang</td>
            <td class="dt-isi">
                <span class="dt-opsi"><span class="dt-box"></span>Jalan</span>
                <span class="dt-opsi"><span class="dt-box"></span>Kursi roda</span>
                <span class="dt-opsi"><span class="dt-box"></span>Brankar</span>
                <span class="dt-opsi"><span class="dt-box"></span>Ambulans</span>
            </td>
            <td class="dt-tbl-label">Pengantar &amp; no. telepon</td>
            <td class="dt-isi">&nbsp;</td>
        </tr>
        <tr>
            <td class="dt-tbl-label">Perawat triase</td>
            <td class="dt-isi">&nbsp;</td>
            <td class="dt-tbl-label">Kasus</td>
            <td class="dt-isi">
                <span class="dt-opsi"><span class="dt-box"></span>Non trauma</span>
                <span class="dt-opsi"><span class="dt-box"></span>Trauma</span>
                <span class="dt-opsi"><span class="dt-box"></span>Laka lantas</span>
            </td>
        </tr>
        <tr>
            <td class="dt-tbl-label">Keluhan utama</td>
            <td class="dt-isi-2" colspan="3">&nbsp;</td>
        </tr>
    </table>

    <div class="dt-sec">B. Survei Primer</div>
    <table class="dt-tbl dt-tbl-kecil">
        <tr>
            <th style="width:16%;">Komponen</th>
            <th style="width:44%;">Temuan</th>
            <th>Tindakan segera</th>
        </tr>
        @foreach (['A — Airway (jalan nafas)', 'B — Breathing (pernafasan)', 'C — Circulation (sirkulasi)', 'D — Disability (kesadaran)', 'E — Exposure'] as $komponen)
            <tr>
                <td>{{ $komponen }}</td>
                <td class="dt-isi">&nbsp;</td>
                <td class="dt-isi">&nbsp;</td>
            </tr>
        @endforeach
    </table>

    <div class="dt-sec">C. Tanda Vital Awal</div>
    <table class="dt-tbl dt-tbl-kecil">
        <tr>
            <th>Sistolik</th>
            <th>Diastolik</th>
            <th>Nadi (x/mnt)</th>
            <th>Nafas (x/mnt)</th>
            <th>Suhu (&deg;C)</th>
            <th>SpO2 (%)</th>
            <th>GDA</th>
            <th>GCS (E / V / M)</th>
        </tr>
        <tr>
            <td class="dt-isi-2">&nbsp;</td>
            <td class="dt-isi-2">&nbsp;</td>
            <td class="dt-isi-2">&nbsp;</td>
            <td class="dt-isi-2">&nbsp;</td>
            <td class="dt-isi-2">&nbsp;</td>
            <td class="dt-isi-2">&nbsp;</td>
            <td class="dt-isi-2">&nbsp;</td>
            <td class="dt-isi-2">&nbsp;</td>
        </tr>
    </table>
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
        <tr>
            <td class="dt-tbl-label">BB (kg) / TB (cm)</td>
            <td class="dt-isi">&nbsp;</td>
            <td class="dt-tbl-label">Ada alergi?</td>
            <td class="dt-isi">
                <span class="dt-opsi"><span class="dt-box"></span>Tidak</span>
                <span class="dt-opsi"><span class="dt-box"></span>Ya, sebutkan</span>
            </td>
        </tr>
        <tr>
            <td class="dt-tbl-label">Riwayat penyakit / obat rutin</td>
            <td class="dt-isi" colspan="3">&nbsp;</td>
        </tr>
    </table>

    <div class="dt-sec">D. Penilaian Nyeri & Risiko Jatuh</div>
    <table class="dt-tbl dt-tbl-kecil">
        <tr>
            <td class="dt-tbl-label" style="width:20%;">Skor nyeri (0&ndash;10)</td>
            <td class="dt-isi" colspan="3">
                @foreach (range(0, 10) as $skorNyeri)
                    <span class="dt-opsi"><span class="dt-box"></span>{{ $skorNyeri }}</span>
                @endforeach
            </td>
        </tr>
        <tr>
            <td class="dt-tbl-label">Lokasi / pencetus nyeri</td>
            <td class="dt-isi" style="width:30%;">&nbsp;</td>
            <td class="dt-tbl-label" style="width:20%;">Intervensi nyeri</td>
            <td class="dt-isi" style="width:30%;">&nbsp;</td>
        </tr>
        <tr>
            <td class="dt-tbl-label">Risiko jatuh</td>
            <td class="dt-isi">
                <span class="dt-opsi"><span class="dt-box"></span>Rendah</span>
                <span class="dt-opsi"><span class="dt-box"></span>Sedang</span>
                <span class="dt-opsi"><span class="dt-box"></span>Tinggi</span>
            </td>
            <td class="dt-tbl-label">Penanda risiko dipasang</td>
            <td class="dt-isi">
                <span class="dt-opsi"><span class="dt-box"></span>Ya</span>
                <span class="dt-opsi"><span class="dt-box"></span>Tidak</span>
            </td>
        </tr>
    </table>
    <div class="dt-note">
        Skoring rinci risiko jatuh (Morse / Humpty Dumpty) dan skrining gizi memakai formulir RJ-EMR-03 bila
        pasien direncanakan rawat inap.
    </div>

    <x-downtime.ttd :kolom="['Perawat Triase' => 'Nama & paraf', 'Perawat Penanggung Jawab' => 'Nama & paraf']" />

</x-downtime.halaman>
