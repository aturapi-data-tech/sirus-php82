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

@use('App\Support\RujukanKompetensiTampil')

@if (!empty($rows))
    <div class="mt-2 overflow-x-auto border bg-canvas rounded-2xl border-hairline dark:border-gray-700">
        <table class="ds-table ds-table-rapat">
            <thead>
                <tr>
                    {{-- Kolom pertama hanya toggle (tanpa label "Pilih" — toggle sudah
                         mewakili), nomor urut menempel di depan nama faskes, supaya
                         kolom Faskes mendapat hampir seluruh lebar panel. --}}
                    <th class="ds-c w-14">Pilih</th>
                    <th>Faskes Tujuan</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $indexKandidat => $kandidatMentah)
                    @php
                        $kandidat = RujukanKompetensiTampil::kandidatBaris($kandidatMentah);
                        $terpilih = $selectedIndex === $indexKandidat;
                        $tanpaBpjs = $requireBpjs && $kandidat['bpjs'] === '';
                    @endphp
                    <tr class="{{ $terpilih ? 'bg-brand-green/5 dark:bg-brand-lime/5' : '' }}">
                        {{-- wireClick dikirim INDEKS angka: nama faskes ber-& akan
                             ter-escape ganda dan aksinya gagal diam-diam. --}}
                        {{-- Tanpa label: alasan "tak bisa dipilih" sudah terbaca dari badge
                             non-BPJS di sel Faskes; title tetap memberi keterangan saat hover. --}}
                        <td class="ds-c align-top">
                            <div class="inline-flex"
                                title="{{ $terpilih ? 'Dipilih' : ($tanpaBpjs ? 'Tak bisa dipilih: faskes non-BPJS' : 'Pilih faskes ini') }}">
                                <x-toggle :current="$terpilih ? 'Ya' : 'Tidak'" trueValue="Ya" falseValue="Tidak"
                                    :disabled="$disabled || $tanpaBpjs"
                                    wireClick="{{ $action }}({{ $indexKandidat }})" />
                            </div>
                        </td>
                        <td class="break-words">
                            <span class="ds-td-meta">{{ $indexKandidat + 1 }}.</span>
                            <span class="ds-td-strong">{{ $kandidat['nama'] }}</span>
                            @if (filled($kandidat['alamat']))
                                <span class="block text-xs text-muted-soft">{{ $kandidat['alamat'] }}</span>
                            @endif
                            {{-- Dua kode dari DUA SISTEM, dikirim bersama dan WAJIB milik RS
                                 yang sama — tertukar berarti rujukan nyasar. Masing-masing
                                 disebut namanya, memakai sebutan yang sama dengan layar
                                 /rujukan/masuk & /rujukan/keluar.

                                 Strata SATUSEHAT (Dasar/Madya/Utama/Paripurna) tampil sebagai
                                 badge HANYA bila server mengisinya — di sampel grup ~40%
                                 kandidat FHIR kosong, SISRUTE hampir selalu terisi. --}}
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
                                @if (filled($kandidat['strata']))
                                    <x-badge variant="info" title="Strata kompetensi faskes menurut SATUSEHAT">Strata {{ $kandidat['strata'] }}</x-badge>
                                @endif
                                @if (filled($kandidat['kelas']))
                                    <span>· Kelas {{ $kandidat['kelas'] }}</span>
                                @endif
                                <span class="tabular-nums">· {{ RujukanKompetensiTampil::jarak($kandidat['jarak']) }}</span>
                                @if (RujukanKompetensiTampil::waktu($kandidat['estimasi']) !== '—')
                                    <span class="tabular-nums">· {{ RujukanKompetensiTampil::waktu($kandidat['estimasi']) }}</span>
                                @endif
                            </span>
                            {{-- Jadwal praktik poli tujuan (jalur BPJS-SISRUTE), disusul
                                 kapasitas di baris yang sama — kapasitas adalah kuota jadwal itu.
                                 Jadwal kosong = poli tak praktik pada tanggal rencana, padahal
                                 RS-nya tetap jadi kandidat — ditandai supaya tak terpilih tanpa
                                 sadar. Jadwal null = sumbernya tak membawa jadwal (draft lama):
                                 kapasitas tetap tampil sendiri bila ada. --}}
                            @if ($kandidat['jadwal'] !== null || filled($kandidat['kapasitas']))
                                <span class="flex flex-wrap items-center mt-1 gap-x-2 gap-y-1 text-xs">
                                    @if ($kandidat['jadwal'] === '')
                                        <x-badge variant="warning" title="BPJS tidak mengirim jadwal praktik poli tujuan untuk tanggal rencana kunjungan">Jadwal kosong{{ filled($kandidat['tglJadwal']) ? ' ' . $kandidat['tglJadwal'] : '' }}</x-badge>
                                    @elseif ($kandidat['jadwal'] !== null)
                                        <span><span class="text-muted dark:text-gray-400">Jadwal</span>
                                            <span class="text-ink dark:text-gray-200 tabular-nums">{{ $kandidat['jadwal'] }}</span></span>
                                    @endif
                                    @if (filled($kandidat['kapasitas']))
                                        <span class="tabular-nums text-muted dark:text-gray-400" title="Kuota rujukan poli tujuan pada tanggal rencana (semua sesi) dan yang sudah terpakai, menurut BPJS">{{ $kandidat['jadwal'] !== null ? '· ' : '' }}kapasitas {{ $kandidat['kapasitas'] }}@if (filled($kandidat['terpakai'])), terpakai {{ $kandidat['terpakai'] }}@endif</span>
                                    @endif
                                </span>
                            @endif
                            {{-- Ketersediaan tempat tidur per ruang (jalur FHIR, bed-availability):
                                 tersedia/total. Hanya sebagian kandidat membawanya. --}}
                            @if (!empty($kandidat['tempatTidur']))
                                <span class="flex flex-wrap items-center mt-1 gap-x-2 gap-y-1 text-xs text-muted dark:text-gray-400"
                                    title="Tempat tidur tersedia / total per ruang, menurut SATUSEHAT">
                                    <span>Tempat tidur kosong:</span>
                                    @foreach ($kandidat['tempatTidur'] as $tempatTidur)
                                        <span class="tabular-nums" title="{{ $tempatTidur['kategori'] }}">{{ $tempatTidur['nama'] }}
                                            <span class="{{ $tempatTidur['tersedia'] === 0 ? 'text-error-deep dark:text-red-300' : 'text-ink dark:text-gray-200' }}">{{ RujukanKompetensiTampil::angkaTempatTidur($tempatTidur) }}</span>
                                        </span>
                                    @endforeach
                                </span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
