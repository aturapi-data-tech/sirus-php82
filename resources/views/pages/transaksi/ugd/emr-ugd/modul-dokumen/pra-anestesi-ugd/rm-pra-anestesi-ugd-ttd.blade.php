                            {{-- ══ TTD ══ --}}
                            <section class="pt-6 space-y-4 border-t border-hairline dark:border-gray-700">
                                {{-- Kiri = TTD gambar Pasien/Keluarga (field entri biasa); Kanan = TTD Petugas (kunci) --}}
                                <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                                    {{-- Pasien / Keluarga (KIRI) — TTD gambar; pad hanya saat form aktif, hasil selalu tampil --}}
                                    <div class="border shadow-sm border-hairline rounded-2xl bg-canvas dark:border-gray-700 dark:bg-gray-900">
                                        <div class="p-4">
                                            <div class="mb-4 text-sm font-semibold tracking-wide text-center uppercase ds-caption-up dark:text-gray-400">Pasien / Keluarga</div>
                                            <div class="max-w-xl mx-auto">
                                                @if (!empty($signaturePasien))
                                                    <x-signature.signature-result :signature="$signaturePasien" :date="''" :disabled="$formReadOnly" wireMethod="clearSignaturePasien" />
                                                @elseif (!$formReadOnly)
                                                    <x-signature.signature-pad wireMethod="setSignaturePasien" />
                                                @else
                                                    <p class="py-8 text-base italic text-center text-muted-soft">Belum ditandatangani.</p>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                    {{-- Petugas / Dokter Anestesi (KANAN) — stempel user login --}}
                                    <x-signature.ttd-petugas :framed="true" :ttd="$newForm['ttd']"
                                        :date="$newForm['ttdDate'] ?? ''" :code="$newForm['ttdCode'] ?? ''"
                                        :locked="$formReadOnly" sign="setTtd" clear="clearTtd"
                                        title="Dokter Anestesi" label="Dokter Anestesi"
                                        nameLabel="Dokter Anestesi" dateLabel="Waktu TTD"
                                        signLabel="TTD Dokter Anestesi" clearLabel="Batal TTD" />
                                </div>