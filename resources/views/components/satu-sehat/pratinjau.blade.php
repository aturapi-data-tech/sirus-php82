{{--
    Pratinjau data yang AKAN dikirim ke SATUSEHAT untuk satu langkah.

    Dipakai bersama seluruh kartu kirim (RJ/UGD/RI) supaya bentuknya seragam dan
    petugas tahu persis apa yang berangkat sebelum menekan tombol — sebelumnya
    kartu hanya menyebut jumlah, sehingga isi yang salah baru ketahuan setelah
    ditolak SATUSEHAT (atau tidak ketahuan sama sekali).

    @param array $baris  [ ['label' => 'Diagnosa 1', 'nilai' => 'J06.9', 'ket' => '...'], ... ]
    @param string $kosong  kalimat bila tak ada data yang akan dikirim
--}}
@props(['baris' => [], 'kosong' => 'Tidak ada data yang akan dikirim untuk langkah ini.'])

<div class="mt-3 border rounded-lg bg-surface-soft border-hairline dark:bg-gray-800 dark:border-gray-700">
    @if (empty($baris))
        <p class="px-3 py-2 text-xs text-muted dark:text-gray-400">{{ $kosong }}</p>
    @else
        <table class="w-full text-xs">
            <tbody>
                @foreach ($baris as $satu)
                    <tr class="border-b border-hairline last:border-0 dark:border-gray-700">
                        <td class="px-3 py-1.5 align-top text-muted dark:text-gray-400 w-2/5">
                            {{ $satu['label'] ?? '-' }}
                        </td>
                        <td class="px-3 py-1.5 align-top font-medium text-ink dark:text-gray-100">
                            {{ $satu['nilai'] ?? '-' }}
                            @if (!empty($satu['ket']))
                                <span class="block font-normal text-muted-soft">{{ $satu['ket'] }}</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>
