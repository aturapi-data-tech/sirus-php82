{{-- resources/views/pages/components/modul-dokumen/ugd/permintaan-rawat-inap/cetak-permintaan-rawat-inap-print.blade.php
     Surat Permintaan Rawat Inap UGD. Payload: form/identitasRs/ttdDokterPath + data pasien. --}}

<x-pdf.layout-a4-with-out-background kode="RM-08.03 · Rev.0" title="SURAT PERMINTAAN RAWAT INAP">

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
        $kotaRs = ucwords(strtolower($identitasRs->int_city ?? 'Tulungagung'));

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

        $tglSurat = $form['tglPermintaan'] ?? '';
        try {
            $tglSurat = \Carbon\Carbon::createFromFormat('d/m/Y H:i:s', $tglSurat)->translatedFormat('d F Y');
        } catch (\Throwable) {
            // biarkan apa adanya
        }
    @endphp

    <p class="text-[11px] mb-1">Dengan hormat,</p>
    <p class="text-[11px] mb-2">Mohon untuk dilakukan rawat inap terhadap pasien tersebut di atas dengan keterangan sebagai berikut:</p>

    <table class="w-full text-[10px] border-collapse">
        <tr>
            <td class="border border-black px-2 py-1.5 w-1/3"><strong>Tanggal / Jam Permintaan</strong></td>
            <td class="border border-black px-2 py-1.5">{{ ($form['tglPermintaan'] ?? '') ?: '-' }}</td>
        </tr>
        <tr>
            <td class="border border-black px-2 py-1.5 w-1/3 align-top"><strong>Diagnosis</strong></td>
            <td class="border border-black px-2 py-1.5 leading-relaxed">{!! nl2br(e(($form['diagnosis'] ?? '') ?: '-')) !!}</td>
        </tr>
        <tr>
            <td class="border border-black px-2 py-1.5 w-1/3"><strong>DPJP</strong></td>
            <td class="border border-black px-2 py-1.5">{{ ($form['dpjpName'] ?? '') ?: '-' }}</td>
        </tr>
        <tr>
            <td class="border border-black px-2 py-1.5 w-1/3 align-top"><strong>Rencana Tindakan</strong></td>
            <td class="border border-black px-2 py-1.5 leading-relaxed">{!! nl2br(e(($form['rencanaTindakan'] ?? '') ?: '-')) !!}</td>
        </tr>
        <tr>
            <td class="border border-black px-2 py-1.5 w-1/3 align-top"><strong>Alasan / Indikasi Rawat Inap</strong></td>
            <td class="border border-black px-2 py-1.5 leading-relaxed">{!! nl2br(e(($form['indikasi'] ?? '') ?: '-')) !!}</td>
        </tr>

        {{-- ── TANDA TANGAN ── --}}
        <tr>
            <td colspan="2" class="border border-black px-1.5 py-1">
                <table class="w-full text-[10px]" cellpadding="0" cellspacing="0">
                    <tr>
                        {{-- Pasien / Keluarga --}}
                        <td class="w-1/2 align-top text-center px-3 py-2">
                            <p class="mb-1">&nbsp;</p>
                            <p class="font-bold mb-1">Mengetahui, Pasien / Keluarga</p>
                            <p class="mb-1 text-[9px]">{{ $hubunganText }}</p>
                            <div style="min-height:60px;" class="flex items-center justify-center">
                                @if (!empty($form['signature']))
                                    <img src="{{ \App\Support\TtdPasien::sumberGambar($form['signature']) }}" style="max-height:55px;max-width:140px;" />
                                @endif
                            </div>
                            <p class="mt-1 border-t border-black pt-1">
                                <strong>{{ ($form['namaPenanda'] ?? '') ?: '-' }}</strong>
                            </p>
                            <p class="text-[9px] text-gray-600">{{ ($form['signatureDate'] ?? '') ?: '-' }}</p>
                        </td>

                        {{-- Dokter IGD --}}
                        <td class="w-1/2 align-top text-center px-3 py-2 border-l border-black">
                            <p class="mb-1">{{ $kotaRs }}, {{ $tglSurat ?: '-' }}</p>
                            <p class="font-bold mb-1">Dokter IGD</p>
                            <p class="mb-1 text-[9px]">&nbsp;</p>
                            <div style="min-height:60px;" class="flex items-center justify-center">
                                @if (!empty($data['ttdDokterPath']))
                                    <img src="{{ $data['ttdDokterPath'] }}" style="max-height:55px;max-width:140px;" />
                                @endif
                            </div>
                            <p class="mt-1 border-t border-black pt-1">
                                <strong>{{ ($form['dokterIgd'] ?? '') ?: '-' }}</strong>
                            </p>
                            <p class="text-[9px] text-gray-600">{{ ($form['dokterIgdDate'] ?? '') ?: '-' }}</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

</x-pdf.layout-a4-with-out-background>
