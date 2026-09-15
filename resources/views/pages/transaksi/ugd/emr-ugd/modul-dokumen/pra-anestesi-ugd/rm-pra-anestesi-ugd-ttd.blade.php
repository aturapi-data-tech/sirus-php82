                            {{-- ══ TTD ══ --}}
                            <section class="pt-6 space-y-4 border-t border-hairline dark:border-gray-700">
                                {{-- Kiri = TTD gambar Pasien/Keluarga (field entri biasa); Kanan = TTD Petugas (kunci).
                                     Standar kolom TTD: judul di luar komponen → kotak TTD langsung di bawahnya. --}}
                                <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                                    {{-- Pasien / Keluarga (KIRI) — TTD gambar; pad hanya saat form aktif, hasil selalu tampil --}}
                                    <div class="flex flex-col">
                                        <div class="mb-2 text-sm font-semibold tracking-wide text-center uppercase text-muted dark:text-gray-400">Pasien / Keluarga</div>
                                        @if (!empty($signaturePasien))
                                            <x-signature.signature-result :signature="$signaturePasien" :disabled="$formReadOnly" wireMethod="clearSignaturePasien" />
                                        @elseif (!$formReadOnly)
                                            <x-signature.signature-pad wireMethod="setSignaturePasien" />
                                        @else
                                            <p class="py-8 text-base italic text-center text-muted-soft">Belum ditandatangani.</p>
                                        @endif
                                    </div>
                                    {{-- Petugas / Dokter Anestesi (KANAN) — stempel user login --}}
                                    <div class="flex flex-col">
                                        <div class="mb-2 text-sm font-semibold tracking-wide text-center uppercase text-muted dark:text-gray-400">Dokter Anestesi</div>
                                        <x-signature.ttd-petugas :framed="false" :ttd="$newForm['ttd']"
                                            :date="$newForm['ttdDate'] ?? ''" :code="$newForm['ttdCode'] ?? ''"
                                            :locked="$formReadOnly" sign="setTtd" clear="clearTtd"
                                            nameLabel="Dokter Anestesi" dateLabel="Waktu TTD"
                                            signLabel="TTD Dokter Anestesi" clearLabel="Batal TTD" />
                                    </div>
                                </div>