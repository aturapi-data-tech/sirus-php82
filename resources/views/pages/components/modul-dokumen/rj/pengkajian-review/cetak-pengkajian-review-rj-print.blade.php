{{-- resources/views/pages/components/modul-dokumen/rj/pengkajian-review/cetak-pengkajian-review-rj-print.blade.php --}}

<x-pdf.layout-a4-with-out-background title="REVIEW PENGKAJIAN MEDIS RAWAT JALAN">

    {{-- Identitas pasien lewat slot patientData: layout menaruhnya di kiri, sejajar
         dengan logo & identitas RS di kanan. --}}
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
        $review = $data['review'] ?? [];
        $form = $review['form'] ?? [];
        $ulang = ($review['keputusan'] ?? '') === 'ULANG';

        $sumberText = match ($review['sumber']['jenis'] ?? '') {
            'LUAR' => 'Luar RS' . (filled($review['sumber']['deskripsi'] ?? '') ? ' — ' . $review['sumber']['deskripsi'] : ''),
            'RJ', 'RI' => trim(
                ($review['sumber']['jenis'] ?? '') .
                    (filled($review['sumber']['no'] ?? null) ? ' ' . $review['sumber']['no'] : '') .
                    (filled($review['sumber']['deskripsi'] ?? '') ? ' — ' . $review['sumber']['deskripsi'] : ''),
            ),
            default => '-',
        };

        $tindakanList = array_values(
            array_filter([
                !empty($form['tindakanTinjau']) ? 'Meninjau hasil pengkajian sebelumnya' : null,
                !empty($form['tindakanVerifikasi']) ? 'Verifikasi & update sesuai kondisi terkini' : null,
                !empty($form['tindakanUlang']) ? 'Pengkajian medis ulang' : null,
            ]),
        );
    @endphp

    {{-- ── DASAR & KEPUTUSAN ── --}}
    <table class="w-full text-[11px] border border-black border-collapse mb-3">
        <tr>
            <td class="border border-black px-2 py-1 w-[38%]">Tanggal peninjauan</td>
            <td class="border border-black px-2 py-1">{{ $review['reviewDate'] ?? '-' }}</td>
        </tr>
        <tr>
            <td class="border border-black px-2 py-1">Sumber pengkajian yang ditinjau</td>
            <td class="border border-black px-2 py-1">{{ $sumberText }}</td>
        </tr>
        <tr>
            <td class="border border-black px-2 py-1">Tanggal pengkajian yang ditinjau</td>
            <td class="border border-black px-2 py-1">
                {{ $review['tglPengkajian'] ?? '-' }}
                @if (!is_null($review['usiaHariSaatReview'] ?? null))
                    (usia {{ $review['usiaHariSaatReview'] }} hari)
                @endif
            </td>
        </tr>
        <tr>
            <td class="border border-black px-2 py-1">Dipakai pada kunjungan</td>
            <td class="border border-black px-2 py-1">
                {{ ($review['pemakai']['jenis'] ?? '') . ' ' . ($review['pemakai']['no'] ?? '') }}
            </td>
        </tr>
        <tr>
            <td class="border border-black px-2 py-1 font-bold">Keputusan</td>
            <td class="border border-black px-2 py-1 font-bold">
                {{ $ulang ? 'PENGKAJIAN MEDIS ULANG (lebih dari 30 hari)' : 'REVIEW / VERIFIKASI (30 hari atau kurang)' }}
            </td>
        </tr>
    </table>

    {{-- ── KONDISI PASIEN ── --}}
    <div class="text-[11px] mb-3">
        <p class="font-bold mb-1">Kondisi Pasien Saat Ini</p>
        <p>
            <span class="font-bold">
                @if (($form['adaPerubahan'] ?? '') === 'Y')
                    <span style="font-family: 'DejaVu Sans', sans-serif;">&#10003;</span>
                    Ada perubahan kondisi klinis yang bermakna
                @else
                    Tidak ada perubahan kondisi klinis yang bermakna
                @endif
            </span>
        </p>
        @if (($form['adaPerubahan'] ?? '') === 'Y' && filled($form['perubahanDesc'] ?? ''))
            <p class="mt-1">{{ $form['perubahanDesc'] }}</p>
        @endif
    </div>

    {{-- ── TINDAKAN ── --}}
    <div class="text-[11px] mb-3">
        <p class="font-bold mb-1">Tindakan yang Dilakukan</p>
        @forelse ($tindakanList as $tindakan)
            <p>
                <span style="font-family: 'DejaVu Sans', sans-serif;">&#10003;</span>
                {{ $tindakan }}
            </p>
        @empty
            <p>-</p>
        @endforelse
    </div>

    {{-- ── CATATAN ── --}}
    <div class="text-[11px] mb-4">
        <p class="font-bold mb-1">Catatan Review / Update</p>
        <p>{{ filled($form['reviewCatatan'] ?? '') ? $form['reviewCatatan'] : '-' }}</p>
    </div>

    {{-- ── TANDA TANGAN ── --}}
    <table class="w-full text-[11px] mt-6">
        <tr>
            <td class="w-1/2">&nbsp;</td>
            <td class="w-1/2 align-top text-center px-3 py-2">
                <p class="font-bold mb-1">Dokter Penanggung Jawab</p>
                <p class="text-[9px] text-gray-500 mb-2">{{ $data['tglCetak'] ?? '-' }}</p>

                <div class="text-center my-1">
                    @if (!empty($data['ttdPetugasPath']))
                        <img src="{{ $data['ttdPetugasPath'] }}" class="h-16" alt="Tanda Tangan Dokter" />
                    @else
                        <div class="h-16">&nbsp;</div>
                    @endif
                </div>

                <div class="border-t border-black pt-[3px] mt-1 min-w-[140px] inline-block">
                    <p class="font-bold">{{ strtoupper($form['ttdPengkajianReview'] ?? '-') }}</p>
                    @if (!empty($form['ttdPengkajianReviewDate']))
                        <p class="text-[9px] text-gray-500">{{ $form['ttdPengkajianReviewDate'] }}</p>
                    @endif
                </div>
            </td>
        </tr>
    </table>

    <p class="text-[8px] text-gray-500 mt-4">
        Pengkajian medis yang dibuat 30 hari atau kurang sebelum tindakan/prosedur rawat jalan boleh
        dipakai ulang bila ditinjau/diverifikasi dan diperbarui sesuai kondisi terkini; lebih dari 30
        hari wajib pengkajian ulang.
    </p>

</x-pdf.layout-a4-with-out-background>
