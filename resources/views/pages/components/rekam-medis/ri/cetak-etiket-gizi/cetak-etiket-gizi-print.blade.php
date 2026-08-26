<x-pdf.layout-etiket>

    {{-- HEADER --}}
    <table class="w-full border-b border-gray-400" cellpadding="0" cellspacing="0" style="margin-bottom:0.8mm;">
        <tr>
            <td class="pr-1 align-middle" style="width:auto;">
                <img src="{{ public_path('images/Logo Persegi.png') }}" alt="Logo RS" class="object-contain"
                    style="height:4.5mm; width:auto;">
            </td>
            <td class="text-left align-middle">
                <div class="font-bold text-gray-900" style="font-size:6.5pt;">ETIKET DIET — INSTALASI GIZI</div>
            </td>
        </tr>
    </table>

    {{-- IDENTITAS RINGKAS --}}
    @php $lp = $data['sex'] === 'L' ? 'L' : ($data['sex'] === 'P' ? 'P' : '-'); @endphp

    {{-- font-size pakai inline style — kelas arbitrary (text-[..px]) belum tentu ada di CSS build PDF --}}
    <div class="font-bold text-black" style="font-size:9px; line-height:1.15;">
        No. RM : {{ $data['regNo'] ?? '-' }}
    </div>
    <div class="font-bold text-black" style="font-size:11px; line-height:1.15;">
        {{ strtoupper($data['regName'] ?? '-') }} / {{ $lp }}
    </div>
    <div class="text-black" style="font-size:8px; line-height:1.2;">
        {{ $data['birthDate'] ?? '-' }} / {{ isset($data['umurTahun']) && $data['umurTahun'] !== null ? $data['umurTahun'] . ' tahun' : '-' }}
    </div>
    <div class="text-black" style="font-size:8px; line-height:1.2;">
        {{ strtoupper($data['bangsal'] ?? '-') }} / {{ strtoupper($data['room'] ?? '-') }}
    </div>

    {{-- PROGRAM DIET — paling menonjol, rata tengah --}}
    <div class="font-bold text-black text-center" style="font-size:12px; line-height:1.15; margin-top:0.8mm;">
        {{ strtoupper($data['programDiet'] ?? '-') }}
    </div>
    @if (filled($data['programDietKet'] ?? ''))
        <div class="text-black text-center" style="font-size:8px; line-height:1.2;">
            {{ \Illuminate\Support\Str::limit($data['programDietKet'], 60) }}
        </div>
    @endif

    {{-- ALERGI — hanya bila ADA (krusial untuk dapur) --}}
    @if (filled($data['alergi'] ?? ''))
        <div class="font-bold text-black" style="font-size:9px; line-height:1.2; margin-top:0.5mm;">
            ALERGI: {{ strtoupper(\Illuminate\Support\Str::limit($data['alergi'], 45)) }}
        </div>
    @endif

    <div class="text-black" style="font-size:7px; line-height:1.2; margin-top:0.5mm;">
        Dicetak: {{ $data['tglCetak'] ?? '-' }}
    </div>

</x-pdf.layout-etiket>
