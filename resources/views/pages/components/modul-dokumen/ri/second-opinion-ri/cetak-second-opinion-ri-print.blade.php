{{-- resources/views/pages/components/modul-dokumen/ri/second-opinion-ri/cetak-second-opinion-ri-print.blade.php --}}

<x-pdf.layout-a4-with-out-background title="FORMULIR PERMINTAAN SECOND OPINION">

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
        $form = $data['form'] ?? [];
        $identitasRs = $data['identitasRs'] ?? null;
        $rsName = $identitasRs->int_name ?? 'RSI MADINAH';

        $kategoriDipilih = $form['kategori'] ?? '';

        $hubunganMap = [
            'pasien' => 'Pasien Sendiri',
            'suami' => 'Suami',
            'istri' => 'Istri',
            'ayah' => 'Ayah',
            'ibu' => 'Ibu',
            'anak' => 'Anak',
            'saudara' => 'Saudara',
            'wali_hukum' => 'Wali Hukum',
            'lainnya' => 'Lainnya',
        ];
        $hubunganText = $hubunganMap[$form['hubunganPasien'] ?? ''] ?? '-';
    @endphp

    <table class="w-full text-[10px] border-collapse">

        {{-- ── TANGGAL/JAM PERMINTAAN ── --}}
        <tr>
            <td class="border border-black px-2 py-1.5 w-1/3"><strong>Tanggal / Jam Permintaan</strong></td>
            <td class="border border-black px-2 py-1.5">{{ $form['tglPermintaan'] ?? '-' }}</td>
        </tr>

        {{-- ── KATEGORI ── --}}
        <tr>
            <td class="border border-black px-2 py-1.5 w-1/3"><strong>Kategori Permintaan</strong></td>
            <td class="border border-black px-2 py-1.5">{{ $kategoriDipilih ?: '-' }}</td>
        </tr>

        {{-- ── URAIAN PERMINTAAN ── --}}
        <tr>
            <td colspan="2" class="border border-black px-2 py-1.5">
                <p class="font-bold mb-1">Uraian Permintaan Second Opinion</p>
                <p class="leading-relaxed">{{ ($form['uraian'] ?? '') ?: '-' }}</p>
            </td>
        </tr>

        {{-- ── ALASAN ── --}}
        <tr>
            <td colspan="2" class="border border-black px-2 py-1.5">
                <p class="font-bold mb-1">Alasan Permintaan Second Opinion</p>
                <p class="leading-relaxed">{{ $form['alasan'] ?? '-' }}</p>
            </td>
        </tr>

        {{-- ── TANDA TANGAN ── --}}
        <tr>
            <td colspan="2" class="border border-black px-1.5 py-1">
                <table class="w-full text-[10px]" cellpadding="0" cellspacing="0">
                    <tr>
                        {{-- Pasien / Keluarga --}}
                        <td class="w-1/2 align-top text-center px-3 py-2">
                            <p class="font-bold mb-1">Yang Meminta Second Opinion</p>
                            <p class="mb-1 text-[9px]">{{ $hubunganText }}</p>
                            <div style="min-height:60px;" class="flex items-center justify-center">
                                @if (!empty($form['signature']))
                                    <img src="{{ $form['signature'] }}" style="max-height:55px;max-width:140px;" />
                                @endif
                            </div>
                            <p class="mt-1 border-t border-black pt-1">
                                <strong>{{ $form['namaPenanda'] ?? '-' }}</strong>
                            </p>
                            <p class="text-[9px] text-gray-600">{{ $form['signatureDate'] ?? '-' }}</p>
                        </td>

                        {{-- Petugas (DPJP/PPA) --}}
                        <td class="w-1/2 align-top text-center px-3 py-2 border-l border-black">
                            <p class="font-bold mb-1">Petugas (DPJP / PPA)</p>
                            <div style="min-height:60px;" class="flex items-center justify-center">
                                @if (!empty($data['ttdPemberiPath']))
                                    <img src="{{ $data['ttdPemberiPath'] }}" style="max-height:55px;max-width:140px;" />
                                @endif
                            </div>
                            <p class="mt-1 border-t border-black pt-1">
                                <strong>{{ $form['pemberiInfo'] ?? '-' }}</strong>
                            </p>
                            <p class="text-[9px] text-gray-600">{{ $form['pemberiInfoDate'] ?? '-' }}</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>

    </table>

</x-pdf.layout-a4-with-out-background>
