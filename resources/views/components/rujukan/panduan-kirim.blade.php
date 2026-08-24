{{-- resources/views/components/rujukan/panduan-kirim.blade.php

    Panduan pemakaian Rujukan Berbasis Kompetensi jalur FHIR (Ranap & IGD),
    gaya biru-info standar, default TERTUTUP.
    Lihat memory project_panduan_panel_blue_info_standard.

    Dipakai bersama panel UGD, RI, dan RJ-FHIR — jangan menyalin isinya ke
    salah satu panel, nanti tiga versi berbeda saat aturannya berubah.

    Prop:
      :jalurGanda  true  = panel bisa memilih tujuan IGD atau Ranap (UGD, RJ-FHIR)
                   false = hanya Ranap (RI)
      :jalur       'fhir'    = langsung ke SATUSEHAT (Ranap/IGD), ada tahap
                               persetujuan faskes tujuan
                   'sisrute' = Rawat Jalan lewat BPJS (SISRUTE), TANPA tahap
                               persetujuan — konfirmasi resmi 22/08/26: accept/
                               reject hanya berlaku untuk IGD & Ranap
--}}

@props(['jalurGanda' => true, 'jalur' => 'fhir'])

@php $lewatBpjs = $jalur === 'sisrute'; @endphp

<div x-data="{ buka: false }"
    class="overflow-hidden border rounded-2xl bg-blue-50 border-blue-200 dark:bg-blue-900/20 dark:border-blue-700">
    <button type="button" x-on:click="buka = !buka"
        class="flex items-center justify-between w-full px-4 py-2.5 text-sm font-semibold text-blue-900 transition-colors hover:bg-blue-100 dark:text-blue-200 dark:hover:bg-blue-900/30">
        <span class="flex items-center min-w-0 gap-2">
            <svg class="w-4 h-4 shrink-0 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <span class="truncate">Panduan: cara mengirim rujukan sampai terkirim</span>
        </span>
        <svg class="w-4 h-4 ml-2 text-blue-600 transition-transform shrink-0" x-bind:class="buka && 'rotate-180'"
            fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
        </svg>
    </button>

    <div x-show="buka" x-cloak class="px-4 pb-4 space-y-4 text-sm text-blue-900 dark:text-blue-100">

        {{-- 1. SEBELUM MULAI --}}
        <div>
            <div class="font-semibold">Sebelum mulai</div>
            @if ($lewatBpjs)
                <p class="mt-1">
                    Rujukan Rawat Jalan dikirim lewat <span class="font-semibold">BPJS</span>, yang meneruskannya
                    ke SATUSEHAT. Kalau ada prasyarat yang kurang, daftarnya muncul di kotak peringatan atas.
                </p>
                <ul class="mt-1 ml-4 space-y-0.5 list-disc">
                    <li><span class="font-semibold">Pasien punya SEP / nomor kartu BPJS</span> &mdash; rujukan ini
                        khusus peserta JKN.</li>
                    <li><span class="font-semibold">Diagnosa rujukan sudah dipilih</span> (ICD-10 kode rinci).</li>
                    <li><span class="font-semibold">IHS pasien &amp; dokter</span> terisi di master.</li>
                </ul>
            @else
                <p class="mt-1">
                    Rujukan ini berjalan di atas kunjungan yang sudah terdaftar di SATUSEHAT. Kalau salah satu
                    di bawah belum ada, tombol <span class="font-semibold">Cari Kandidat</span> akan menolak dan
                    daftarnya muncul di kotak peringatan atas.
                </p>
                <ul class="mt-1 ml-4 space-y-0.5 list-disc">
                    <li><span class="font-semibold">Encounter SATUSEHAT sudah dikirim</span> &mdash; lewat menu
                        Satu Sehat &rarr; Encounter pada pasien yang sama.</li>
                    <li><span class="font-semibold">IHS pasien</span> terisi di Master Pasien.</li>
                    <li><span class="font-semibold">IHS dokter</span> terisi di Master Dokter.</li>
                </ul>
            @endif
        </div>

        {{-- 2. URUTAN LANGKAH --}}
        <div class="pt-3 border-t border-blue-200 dark:border-blue-800">
            <div class="font-semibold">Urutan langkah</div>
            <p class="mt-1">Nomor di bawah sama dengan nomor pada penanda langkah di atas form.</p>
            @if ($lewatBpjs)
                <ol class="mt-1 ml-4 space-y-1 list-decimal">
                    <li>
                        <span class="font-semibold">Diagnosa &amp; Kriteria.</span> Pilih diagnosa (ICD-10),
                        tekan <span class="font-semibold">Ambil Kriteria</span> untuk menarik daftar pertanyaan
                        dari server, lalu centang <span class="font-semibold">tepat satu</span> kriteria.
                        Kriteria "Tindakan Medis" wajib disertai kode ICD-9-CM.
                    </li>
                    <li>
                        <span class="font-semibold">Cari &amp; Pilih Kandidat.</span> Tekan
                        <span class="font-semibold">Cari Faskes</span>, lalu pilih satu RS dari daftar.
                        RS bertanda <span class="font-semibold">non-BPJS</span> tidak bisa dipilih untuk
                        rujukan JKN.
                    </li>
                    <li>
                        <span class="font-semibold">Kirim Rujukan.</span> Isi poli tujuan (boleh dikosongkan
                        = ikut kode spesialis) dan catatan, lalu kirim. Yang terbit
                        <span class="font-semibold">dua nomor</span>: No. Rujukan BPJS dan No. Rujukan SATUSEHAT.
                    </li>
                </ol>
                <p class="mt-2">
                    <span class="font-semibold">Tidak ada tahap persetujuan.</span> Rawat Jalan langsung jadi
                    begitu terkirim &mdash; accept/reject hanya berlaku untuk rujukan IGD &amp; Rawat Inap.
                </p>
            @else
            <ol class="mt-1 ml-4 space-y-1 list-decimal">
                <li>
                    <span class="font-semibold">Diagnosa &amp; Kriteria.</span>
                    @if ($jalurGanda)
                        Pilih tujuan <span class="font-semibold">IGD</span> atau <span class="font-semibold">Rawat Inap</span> dulu &mdash;
                        pilihan ini menentukan pertanyaan kriteria yang muncul.
                    @endif
                    Pilih diagnosa (ICD-10), isi kriteria, lalu tentukan wilayah tujuan.
                    Kriteria inilah yang dipakai SATUSEHAT menilai RS mana yang mampu menangani.
                </li>
                <li>
                    <span class="font-semibold">Cari &amp; Pilih Kandidat.</span> Tekan
                    <span class="font-semibold">Cari Kandidat Faskes</span>, lalu
                    <span class="font-semibold">Cek Hasil Kandidat</span> bila daftarnya belum muncul
                    (jawabannya tidak selalu seketika). Pilih satu RS dari daftar itu.
                </li>
                <li>
                    <span class="font-semibold">Kirim Tugas Rujukan.</span> Ini
                    <span class="font-semibold">menanyakan kesediaan</span> RS tujuan, belum merujuk.
                    Isi Kode/Nama Layanan dan deskripsi kebutuhan, lalu kirim.
                </li>
                <li>
                    <span class="font-semibold">Persetujuan Faskes.</span> Tekan
                    <span class="font-semibold">Cek Status</span> untuk melihat jawaban RS tujuan:
                    Diterima, Ditolak, atau belum dijawab.
                </li>
                <li>
                    <span class="font-semibold">Kirim Rujukan.</span> Baru di sini rujukan resmi terbit dan
                    <span class="font-semibold">Nomor Rujukan Nasional</span> keluar. Nomor itu tersimpan
                    otomatis dan tampil di panel hasil.
                </li>
            </ol>
            @endif
        </div>

        {{-- 3. YANG DIISI PETUGAS --}}
        <div class="pt-3 border-t border-blue-200 dark:border-blue-800">
            <div class="font-semibold">Yang perlu diketik petugas</div>
            @if ($lewatBpjs)
                <p class="mt-1">
                    Hanya <span class="font-semibold">diagnosa, kriteria, wilayah, poli tujuan, dan catatan</span>.
                    Nomor kartu, SEP, dokter, dan kode faskes kita diambil sendiri dari data kunjungan.
                </p>
                <div class="mt-1 overflow-x-auto">
                    <table class="w-full text-sm text-left">
                        <tbody class="align-top">
                            <tr>
                                <td class="py-0.5 pr-3 font-semibold whitespace-nowrap">Kriteria</td>
                                <td class="py-0.5">Harus <span class="font-semibold">tepat satu</span> yang terisi &mdash;
                                    lebih dari satu ditolak server.</td>
                            </tr>
                            <tr>
                                <td class="py-0.5 pr-3 font-semibold whitespace-nowrap">ICD-9-CM</td>
                                <td class="py-0.5">Wajib bila kriterianya Tindakan Medis. Kodenya ikut menentukan
                                    kandidat, jadi salah kode = daftar RS keliru tanpa pesan error.</td>
                            </tr>
                            <tr>
                                <td class="py-0.5 pr-3 font-semibold whitespace-nowrap">Poli Rujukan</td>
                                <td class="py-0.5">Boleh dikosongkan &mdash; otomatis memakai kode spesialis dari Langkah 1.</td>
                            </tr>
                            <tr>
                                <td class="py-0.5 pr-3 font-semibold whitespace-nowrap">Wilayah</td>
                                <td class="py-0.5">Menentukan jejaring RS yang dicari.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            @else

            <p class="mt-1">
                Hanya <span class="font-semibold">diagnosa, kriteria, wilayah, kode layanan, dan deskripsi</span>.
                Identitas pasien, dokter, dan kunjungan diambil sendiri dari master &mdash; tidak perlu diketik ulang.
            </p>
            <div class="mt-1 overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <tbody class="align-top">
                        <tr>
                            <td class="py-0.5 pr-3 font-semibold whitespace-nowrap">Kelompok Layanan</td>
                            <td class="py-0.5">Opsional. Mempersempit kandidat ke RS yang melayani kelompok itu.
                                Biarkan kosong kalau ragu &mdash; salah pilih justru menyaring kandidat secara keliru.</td>
                        </tr>
                        <tr>
                            <td class="py-0.5 pr-3 font-semibold whitespace-nowrap">Kode Layanan</td>
                            <td class="py-0.5">Ada pilihan cepat untuk kode yang sudah lazim dipakai; kode lain
                                tetap boleh diketik manual karena katalog resminya belum lengkap dibagikan.</td>
                        </tr>
                        <tr>
                            <td class="py-0.5 pr-3 font-semibold whitespace-nowrap">Tgl. Rencana Kunjungan</td>
                            <td class="py-0.5">Kapan pasien direncanakan dilayani di RS tujuan &mdash;
                                bukan jam pengiriman. Terisi hari ini secara bawaan.</td>
                        </tr>
                        <tr>
                            <td class="py-0.5 pr-3 font-semibold whitespace-nowrap">Wilayah</td>
                            <td class="py-0.5">Dipilih sekali lewat pencarian kabupaten/kota; provinsinya ikut terisi sendiri.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            @endif
        </div>

        {{-- 4. KALAU TERSENDAT --}}
        <div class="pt-3 border-t border-blue-200 dark:border-blue-800">
            <div class="font-semibold">Kalau tersendat</div>
            <div class="mt-1 overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <tbody class="align-top">
                        <tr>
                            <td class="py-0.5 pr-3 font-semibold">&ldquo;Data belum siap&hellip;&rdquo;</td>
                            <td class="py-0.5">Prasyarat di bagian pertama belum lengkap &mdash; paling sering
                                Encounter SATUSEHAT belum dikirim.</td>
                        </tr>
                        <tr>
                            <td class="py-0.5 pr-3 font-semibold">Kandidat kosong</td>
                            <td class="py-0.5">Bukan error. SATUSEHAT menilai tidak ada RS yang perlu/ bisa dituju
                                untuk diagnosa &amp; wilayah itu. Periksa lagi diagnosa, kriteria, dan wilayahnya.</td>
                        </tr>
                        <tr>
                            <td class="py-0.5 pr-3 font-semibold">&ldquo;Pilih kandidat faskes tujuan dulu&rdquo;</td>
                            <td class="py-0.5">Belum ada RS yang dipilih dari daftar kandidat. Kalau daftarnya hilang
                                sesudah wilayah/diagnosa diubah, itu memang sengaja &mdash; cari kandidat ulang.</td>
                        </tr>
                        <tr>
                            <td class="py-0.5 pr-3 font-semibold">&ldquo;Kirim Tugas Rujukan dulu&rdquo;</td>
                            <td class="py-0.5">Langkah 3 belum dijalankan. Rujukan resmi selalu berdiri di atas
                                rencana rujukan yang dibuat di langkah itu.</td>
                        </tr>
                        <tr>
                            <td class="py-0.5 pr-3 font-semibold">Kotak kuning &ldquo;id-nya belum terbaca&rdquo;</td>
                            <td class="py-0.5"><span class="font-semibold">Jangan kirim ulang.</span> Tugasnya sudah
                                sampai di RS tujuan; mengulang hanya menumpuk permintaan ganda di sana. Tekan
                                <span class="font-semibold">Pulihkan ID Tugas Rujukan</span>.</td>
                        </tr>
                        <tr>
                            <td class="py-0.5 pr-3 font-semibold">Ditolak RS tujuan</td>
                            <td class="py-0.5">Rujukan tidak bisa diteruskan ke RS itu. Pilih kandidat lain, lalu
                                kirim tugas rujukan lagi ke RS yang baru.</td>
                        </tr>
                        <tr>
                            <td class="py-0.5 pr-3 font-semibold">Belum dijawab</td>
                            <td class="py-0.5">Rujukan tetap boleh diterbitkan &mdash; akan muncul peringatan, bukan
                                penolakan. Di pelayanan nyata sebaiknya tunggu jawaban dulu.</td>
                        </tr>
                        <tr>
                            <td class="py-0.5 pr-3 font-semibold">Gangguan koneksi / kuota</td>
                            <td class="py-0.5">Isian tidak hilang. Tunggu sebentar lalu ulangi tombol yang sama;
                                jangan mengulang dari langkah awal.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        {{-- 5. ISTILAH --}}
        <div class="pt-3 border-t border-blue-200 dark:border-blue-800">
            <div class="font-semibold">Istilah</div>
            <div class="mt-1 overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <tbody class="align-top">
                        <tr><td class="py-0.5 pr-3 font-mono whitespace-nowrap">Kandidat</td>
                            <td class="py-0.5">Daftar RS yang menurut SATUSEHAT mampu menangani, dihitung dari
                                diagnosa, kriteria, dan wilayah yang dikirim.</td></tr>
                        <tr><td class="py-0.5 pr-3 font-mono whitespace-nowrap">Tugas Rujukan</td>
                            <td class="py-0.5">Permintaan kesediaan ke RS tujuan. Belum merujuk, belum berarti
                                pasien diterima.</td></tr>
                        <tr><td class="py-0.5 pr-3 font-mono whitespace-nowrap">Rencana Rujukan</td>
                            <td class="py-0.5">Isi permintaan: layanan apa yang diminta, untuk pasien siapa,
                                dari dokter siapa. Dibuat otomatis saat tugas rujukan dikirim.</td></tr>
                        <tr><td class="py-0.5 pr-3 font-mono whitespace-nowrap">Permintaan Rujukan</td>
                            <td class="py-0.5">Rujukan resminya. Inilah yang menerbitkan Nomor Rujukan Nasional.</td></tr>
                        <tr><td class="py-0.5 pr-3 font-mono whitespace-nowrap">Kunjungan</td>
                            <td class="py-0.5">Data kunjungan pasien di SATUSEHAT. Semua langkah rujukan menempel padanya.</td></tr>
                        <tr><td class="py-0.5 pr-3 font-mono whitespace-nowrap">IHS</td>
                            <td class="py-0.5">Nomor identitas pasien/dokter di SATUSEHAT, tersimpan di master.</td></tr>
                        <tr><td class="py-0.5 pr-3 font-mono whitespace-nowrap">ICD-10</td>
                            <td class="py-0.5">Kode diagnosa baku. Harus kode rinci, bukan kode induk.</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
