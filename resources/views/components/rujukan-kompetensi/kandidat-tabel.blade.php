{{-- resources/views/components/rujukan-kompetensi/kandidat-tabel.blade.php

    Tabel kandidat faskes tujuan — SATU bentuk untuk keenam panel Rujukan
    Berbasis Kompetensi (RJ/UGD/RI, jalur BPJS-SISRUTE maupun FHIR-SATUSEHAT).

    Dulu ada dua tabel berbeda: jalur BPJS menempelkan alamat, kelas, dan beban
    ke sel nama, sedangkan jalur FHIR memberi jarak kolomnya sendiri dan tak
    menampilkan alamat sama sekali. Angka yang sama pun bernama lain di tiap
    layar ("PPK/SATUSEHAT" vs "Org ID"). Sekarang keduanya memakai bentuk ini;
    keterangan yang tidak dipunyai suatu sumber sekadar tidak tampil.

    Semua meta dilebur ke sel Faskes (bukan kolom sendiri) karena panel ini
    hanya selebar sepertiga layar — kolom Aksi harus tetap terlihat tanpa
    menggulung ke samping.

    Prop:
      :rows          kandidatList apa adanya dari panel (bentuk SISRUTE atau FHIR)
      :selectedIndex kandidatIdx — indeks baris terpilih, null bila belum memilih
      :disabled      form terkunci
      :requireBpjs   true  = kandidat tanpa kode BPJS tak boleh dipilih (jalur
                             SISRUTE: rujukan BPJS butuh kode PPK tujuan)
                     false = jalur FHIR, kode BPJS boleh kosong
      action         nama method Livewire yang dipanggil, menerima INDEKS
--}}

@props([
    'rows' => [],
    'selectedIndex' => null,
    'disabled' => false,
    'requireBpjs' => false,
    'action' => 'pilihKandidat',
])

@use('App\Support\RujukanTampil')

@if (!empty($rows))
    <div class="mt-2 overflow-x-auto border bg-canvas rounded-2xl border-hairline dark:border-gray-700">
        <table class="ds-table ds-table-rapat">
            <thead>
                <tr>
                    <th class="ds-c w-8">No</th>
                    <th>Faskes Tujuan</th>
                    <th class="ds-c w-20">Pilih</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $indexKandidat => $kandidatMentah)
                    @php
                        $kandidat = RujukanTampil::kandidatBaris($kandidatMentah);
                        $terpilih = $selectedIndex === $indexKandidat;
                        $tanpaBpjs = $requireBpjs && $kandidat['bpjs'] === '';
                    @endphp
                    <tr class="{{ $terpilih ? 'bg-brand-green/5 dark:bg-brand-lime/5' : '' }}">
                        <td class="ds-c ds-td-meta">{{ $indexKandidat + 1 }}</td>
                        <td class="break-words">
                            <span class="ds-td-strong">{{ $kandidat['nama'] }}</span>
                            @if (filled($kandidat['alamat']))
                                <span class="block text-xs text-muted-soft">{{ $kandidat['alamat'] }}</span>
                            @endif
                            {{-- Dua kode dari DUA SISTEM, dikirim bersama dan WAJIB milik RS
                                 yang sama — tertukar berarti rujukan nyasar. Masing-masing
                                 disebut namanya, memakai sebutan yang sama dengan layar
                                 /rujukan/masuk & /rujukan/keluar.

                                 Strata SENGAJA tidak ditampilkan: SATUSEHAT mengirim kunci
                                 'strata' tanpa nilai untuk semua kandidat. --}}
                            <span class="flex flex-wrap items-center mt-1 gap-x-2 gap-y-1 text-xs text-muted dark:text-gray-400">
                                @if ($kandidat['bpjs'] === '')
                                    <x-badge variant="gray">non-BPJS</x-badge>
                                @else
                                    <span>Kode BPJS
                                        <span class="font-mono text-ink dark:text-gray-200">{{ $kandidat['bpjs'] }}</span>
                                    </span>
                                @endif
                                <span title="Kode faskes di SATUSEHAT (Organization ID) — dipakai memasangkan faskes BPJS dengan SATUSEHAT">· Org ID
                                    <span class="font-mono text-ink dark:text-gray-200">{{ $kandidat['orgId'] ?: '—' }}</span>
                                </span>
                                @if (filled($kandidat['kelas']))
                                    <span>· Kelas {{ $kandidat['kelas'] }}</span>
                                @endif
                                <span class="tabular-nums">· {{ RujukanTampil::jarak($kandidat['jarak']) }}</span>
                                @if (RujukanTampil::waktu($kandidat['estimasi']) !== '—')
                                    <span class="tabular-nums">· {{ RujukanTampil::waktu($kandidat['estimasi']) }}</span>
                                @endif
                                @if (filled($kandidat['beban']))
                                    <span class="tabular-nums" title="Rujukan masuk / kapasitas">· beban {{ $kandidat['beban'] }}</span>
                                @endif
                                @if (filled($kandidat['bed']))
                                    <span class="tabular-nums" title="Tempat tidur tersedia di faskes tujuan">· bed {{ $kandidat['bed'] }}</span>
                                @endif
                            </span>
                        </td>
                        {{-- wireClick dikirim INDEKS angka: nama faskes ber-& akan
                             ter-escape ganda dan aksinya gagal diam-diam. --}}
                        <td class="ds-c ds-toggle-tumpuk">
                            <x-toggle :current="$terpilih ? 'Ya' : 'Tidak'" trueValue="Ya" falseValue="Tidak"
                                :disabled="$disabled || $tanpaBpjs"
                                wireClick="{{ $action }}({{ $indexKandidat }})"
                                :label="$terpilih ? 'Dipilih' : ($tanpaBpjs ? 'Tak bisa' : 'Pilih')" />
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
