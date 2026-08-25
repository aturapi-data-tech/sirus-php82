{{-- resources/views/pages/components/modul-dokumen/rj/prmrj/cetak-prmrj-rj-print.blade.php --}}

<x-pdf.layout-a4-with-out-background title="FORMULIR PROFIL RINGKAS MEDIS RAWAT JALAN (PRMRJ)">

    {{-- Identitas pasien lewat slot patientData: layout menaruhnya di kiri,
         sejajar logo & identitas RS di kanan. --}}
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
        <x-pdf.identitas-pasien :rm="$data['regNo'] ?? null" :nama="$data['regName'] ?? null"
            :jenisKelamin="$data['jenisKelamin']['jenisKelaminDesc'] ?? null" :tempatLahir="$data['tempatLahir'] ?? null"
            :tglLahir="$data['tglLahir'] ?? null" :umur="$data['thn'] ?? null" :alamat="$alamatPasien" />
    </x-slot>

    @php
        $barisList = $data['barisList'] ?? [];
    @endphp

    {{-- Tabel RM.06 — satu baris per kunjungan, kronologis.
         Lebar kolom dipatok persen supaya dompdf tak melebar tak karuan.
         Hindari whitespace-nowrap: dompdf mengabaikan lebar bila ada nowrap. --}}
    <table class="w-full border border-black border-collapse" style="font-size: 8px;">
        <thead>
            <tr>
                <th class="px-1 py-1 border border-black" style="width: 3%;">NO</th>
                <th class="px-1 py-1 border border-black" style="width: 8%;">Tanggal Kunjungan</th>
                <th class="px-1 py-1 border border-black" style="width: 8%;">Poliklinik</th>
                <th class="px-1 py-1 border border-black" style="width: 10%;">DPJP dan PPA</th>
                <th class="px-1 py-1 border border-black" style="width: 17%;">Diagnosa &amp; Kode Diagnosa</th>
                <th class="px-1 py-1 border border-black" style="width: 6%;">Riwayat Alergi</th>
                <th class="px-1 py-1 border border-black" style="width: 12%;">Terapi (Obat-obatan)</th>
                <th class="px-1 py-1 border border-black" style="width: 8%;">Tindakan</th>
                <th class="px-1 py-1 border border-black" style="width: 7%;">Operasi</th>
                <th class="px-1 py-1 border border-black" style="width: 8%;">Obat Khusus</th>
                <th class="px-1 py-1 border border-black" style="width: 8%;">Rencana Tindakan Pengobatan</th>
                <th class="px-1 py-1 border border-black" style="width: 5%;">TTD Dan Nama</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($barisList as $urut => $baris)
                <tr>
                    <td class="px-1 py-1 align-top border border-black">{{ $urut + 1 }}</td>
                    <td class="px-1 py-1 align-top border border-black">{{ $baris['tglKunjungan'] ?: '-' }}</td>
                    <td class="px-1 py-1 align-top border border-black">{{ $baris['poliklinik'] ?: '-' }}</td>
                    <td class="px-1 py-1 align-top border border-black">{{ $baris['dpjp'] ?: '-' }}</td>
                    <td class="px-1 py-1 align-top border border-black" style="white-space: pre-line;">{{ $baris['diagnosa'] ?: '-' }}</td>
                    <td class="px-1 py-1 align-top border border-black">{{ $baris['riwayatAlergi'] ?: 'tidak ada' }}</td>
                    <td class="px-1 py-1 align-top border border-black">{{ $baris['terapi'] ?: '-' }}</td>
                    <td class="px-1 py-1 align-top border border-black" style="white-space: pre-line;">{{ $baris['tindakan'] ?: '-' }}</td>
                    <td class="px-1 py-1 align-top border border-black" style="white-space: pre-line;">{{ $baris['operasi'] ?: '-' }}</td>
                    <td class="px-1 py-1 align-top border border-black">{{ $baris['obatKhusus'] ?: '-' }}</td>
                    <td class="px-1 py-1 align-top border border-black">{{ $baris['rencanaTindakLanjut'] ?: '-' }}</td>
                    <td class="px-1 py-1 align-top text-center border border-black">
                        @if (!empty($baris['ttdPath']))
                            <img src="{{ $baris['ttdPath'] }}" style="height: 28px;" alt="Tanda Tangan DPJP" />
                        @else
                            <div style="height: 28px;">&nbsp;</div>
                        @endif
                        <div>{{ $baris['ttdNama'] ?: '-' }}</div>
                    </td>
                </tr>
                {{-- Kriteria menempel di bawah barisnya sendiri, bukan jadi catatan
                     kaki bernomor di bawah tabel — pembaca tak perlu bolak-balik
                     mencocokkan nomor. Kolom resmi formulir tetap 12. --}}
                <tr>
                    <td colspan="12" class="px-1 py-1 align-top border border-black">
                        <span class="font-bold">Kriteria:</span>
                        {{ !empty($baris['kriteria']) ? implode(' | ', $baris['kriteria']) : 'tidak dicatat' }}
                        @if (!empty($baris['kriteriaCatatan']))
                            &mdash; {{ $baris['kriteriaCatatan'] }}
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="12" class="px-1 py-4 text-center border border-black">Belum ada baris PRMRJ.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <p class="mt-2 italic" style="font-size: 8px;">
        *PRMRJ di Identifikasi dan di isi lengkap oleh DPJP Utama*
    </p>

</x-pdf.layout-a4-with-out-background>
