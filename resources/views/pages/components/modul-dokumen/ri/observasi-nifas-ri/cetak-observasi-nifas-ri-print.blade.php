{{-- resources/views/pages/components/modul-dokumen/ri/observasi-nifas-ri/cetak-observasi-nifas-ri-print.blade.php --}}

<x-pdf.layout-a4-with-out-background title="LEMBAR OBSERVASI NIFAS">

    {{-- ── IDENTITAS PASIEN ── --}}
    <x-slot name="patientData">
        @php
            $identitas = $data['identitas'] ?? [];
            $alamatPasien = trim(
                ($identitas['alamat'] ?? '-') .
                    (!empty($identitas['rt']) ? ' RT ' . $identitas['rt'] : '') .
                    (!empty($identitas['rw']) ? '/RW ' . $identitas['rw'] : '') .
                    (!empty($identitas['desaName']) ? ', ' . $identitas['desaName'] : '') .
                    (!empty($identitas['kecamatanName']) ? ', ' . $identitas['kecamatanName'] : ''),
            );
        @endphp
        <x-pdf.identitas-pasien
            :rm="$data['regNo'] ?? null"
            :nama="$data['regName'] ?? null"
            :jenisKelamin="$data['jenisKelamin']['jenisKelaminDesc'] ?? null"
            :tempatLahir="$data['tempatLahir'] ?? null"
            :tglLahir="$data['tglLahir'] ?? null"
            :umur="$data['thn'] ?? null"
            :alamat="$alamatPasien" />
    </x-slot>

    @php
        $rows = $data['rows'] ?? [];
        $nilaiSel = fn($nilai) => filled($nilai) ? e($nilai) : '-';
        $nilaiLochia = function (array $baris) {
            $lochia = trim(($baris['lochiaJenis'] ?? '') . ' ' . ($baris['lochiaJumlah'] ?? ''));
            return $lochia !== '' ? e($lochia) : '-';
        };
    @endphp

    <table class="w-full border-collapse text-[8px]">
        <thead>
            <tr>
                <th class="w-[9%] bg-[#eef2ee] font-bold border border-[#999] px-[3px] py-[2px] align-top text-center">Tgl / Jam</th>
                <th class="w-[6%] bg-[#eef2ee] font-bold border border-[#999] px-[3px] py-[2px] align-top text-center">TD</th>
                <th class="w-[4%] bg-[#eef2ee] font-bold border border-[#999] px-[3px] py-[2px] align-top text-center">N</th>
                <th class="w-[4%] bg-[#eef2ee] font-bold border border-[#999] px-[3px] py-[2px] align-top text-center">RR</th>
                <th class="w-[4%] bg-[#eef2ee] font-bold border border-[#999] px-[3px] py-[2px] align-top text-center">S</th>
                <th class="w-[4%] bg-[#eef2ee] font-bold border border-[#999] px-[3px] py-[2px] align-top text-center">EWS</th>
                <th class="w-[8%] bg-[#eef2ee] font-bold border border-[#999] px-[3px] py-[2px] align-top text-center">TFU</th>
                <th class="w-[6%] bg-[#eef2ee] font-bold border border-[#999] px-[3px] py-[2px] align-top text-center">Kontraksi</th>
                <th class="w-[8%] bg-[#eef2ee] font-bold border border-[#999] px-[3px] py-[2px] align-top text-center">Lochia</th>
                <th class="w-[4%] bg-[#eef2ee] font-bold border border-[#999] px-[3px] py-[2px] align-top text-center">Drh (cc)</th>
                <th class="w-[6%] bg-[#eef2ee] font-bold border border-[#999] px-[3px] py-[2px] align-top text-center">Luka</th>
                <th class="w-[5%] bg-[#eef2ee] font-bold border border-[#999] px-[3px] py-[2px] align-top text-center">Laktasi</th>
                <th class="w-[4%] bg-[#eef2ee] font-bold border border-[#999] px-[3px] py-[2px] align-top text-center">ASI</th>
                <th class="w-[18%] bg-[#eef2ee] font-bold border border-[#999] px-[3px] py-[2px] align-top text-center">Keterangan</th>
                <th class="w-[10%] bg-[#eef2ee] font-bold border border-[#999] px-[3px] py-[2px] align-top text-center">Petugas</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $baris)
                @php
                    $keterangan = trim(
                        (filled($baris['keluhan'] ?? null) ? 'Keluhan: ' . $baris['keluhan'] . '. ' : '') .
                        (filled($baris['asuhanTindakan'] ?? null) ? $baris['asuhanTindakan'] : '')
                    );
                @endphp
                <tr>
                    <td class="border border-[#999] px-[3px] py-[2px] align-top text-center">{{ $nilaiSel($baris['tglJam'] ?? null) }}</td>
                    <td class="border border-[#999] px-[3px] py-[2px] align-top text-center">{{ $nilaiSel(filled($baris['sistolik'] ?? null) || filled($baris['diastolik'] ?? null) ? ($baris['sistolik'] ?? '-') . '/' . ($baris['diastolik'] ?? '-') : ($baris['td'] ?? null)) }}</td>
                    <td class="border border-[#999] px-[3px] py-[2px] align-top text-center">{{ $nilaiSel($baris['nadi'] ?? null) }}</td>
                    <td class="border border-[#999] px-[3px] py-[2px] align-top text-center">{{ $nilaiSel($baris['rr'] ?? null) }}</td>
                    <td class="border border-[#999] px-[3px] py-[2px] align-top text-center">{{ $nilaiSel($baris['suhu'] ?? null) }}</td>
                    <td class="border border-[#999] px-[3px] py-[2px] align-top text-center">{{ $nilaiSel($baris['ewsScore'] ?? null) }}</td>
                    <td class="border border-[#999] px-[3px] py-[2px] align-top text-center">{{ $nilaiSel($baris['tfu'] ?? null) }}</td>
                    <td class="border border-[#999] px-[3px] py-[2px] align-top text-center">{{ $nilaiSel($baris['kontraksiUterus'] ?? null) }}</td>
                    <td class="border border-[#999] px-[3px] py-[2px] align-top text-center">{{ $nilaiLochia($baris) }}</td>
                    <td class="border border-[#999] px-[3px] py-[2px] align-top text-center">{{ $nilaiSel($baris['perdarahanCc'] ?? null) }}</td>
                    <td class="border border-[#999] px-[3px] py-[2px] align-top text-center">{{ $nilaiSel($baris['lukaJalanLahir'] ?? null) }}</td>
                    <td class="border border-[#999] px-[3px] py-[2px] align-top text-center">{{ $nilaiSel($baris['laktasi'] ?? null) }}</td>
                    <td class="border border-[#999] px-[3px] py-[2px] align-top text-center">{{ $nilaiSel($baris['asiEksklusif'] ?? null) }}</td>
                    <td class="border border-[#999] px-[3px] py-[2px] align-top text-left">{{ $keterangan !== '' ? e($keterangan) : '-' }}</td>
                    <td class="border border-[#999] px-[3px] py-[2px] align-top text-center">{{ $nilaiSel($baris['petugas'] ?? null) }}</td>
                </tr>
            @empty
                <tr><td colspan="15" class="border border-[#999] px-[3px] py-[2px] align-top text-center">Belum ada baris observasi nifas.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div style="font-size:8px; margin-top:4px; color:#555;">
        Keterangan: TD = Tekanan Darah, N = Nadi, RR = Respirasi, S = Suhu, EWS = Maternal Early Warning Score,
        TFU = Tinggi Fundus Uteri, Drh = Perdarahan, ASI = ASI Eksklusif. Lochia: Rubra / Sanguinolenta / Serosa / Alba.
    </div>

    {{-- Penutup / TTD --}}
    <table style="width:100%; margin-top:16px; font-size:10px;">
        <tr>
            <td style="width:60%;">&nbsp;</td>
            <td style="width:40%; text-align:center;">
                {{ $data['identitasRs']->int_city ?? 'Tulungagung' }}, {{ $data['ttdDate'] ?? ($data['tglCetak'] ?? '') }}<br>
                {{ $data['ttd'] ?? 'Bidan / Perawat' }}<br>
                @if (!empty($data['ttdPath']))
                    <img src="{{ $data['ttdPath'] }}" style="height:44px; margin:4px 0;" alt="Tanda Tangan"><br>
                @else
                    <br><br><br>
                @endif
                <span style="border-top:1px solid #000; padding:0 30px;">Tanda Tangan &amp; Nama Terang</span>
            </td>
        </tr>
    </table>

</x-pdf.layout-a4-with-out-background>
