                            {{-- ══ TTD 3 PIHAK = KUNCI ══ --}}
                            <section class="pt-6 space-y-4 border-t border-hairline dark:border-gray-700">
                                <h3 class="text-base font-semibold text-ink dark:text-gray-200">Tanda Tangan (3 Pihak)</h3>
                                <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
                                    <x-signature.ttd-petugas :ttd="$newForm['ttdPerawatRuangan']" :date="$newForm['ttdPerawatRuanganDate'] ?? ''"
                                        :code="$newForm['ttdPerawatRuanganCode'] ?? ''" :locked="$formReadOnly"
                                        sign="setTtdRole('perawatRuangan')" clear="clearTtdRole('perawatRuangan')"
                                        title="Perawat Ruangan" nameLabel="Perawat Ruangan" dateLabel="Waktu TTD"
                                        signLabel="TTD Perawat Ruangan" clearLabel="Batal TTD" />
                                    <x-signature.ttd-petugas :ttd="$newForm['ttdPerawatKamarBedah']" :date="$newForm['ttdPerawatKamarBedahDate'] ?? ''"
                                        :code="$newForm['ttdPerawatKamarBedahCode'] ?? ''" :locked="$formReadOnly"
                                        sign="setTtdRole('perawatKamarBedah')" clear="clearTtdRole('perawatKamarBedah')"
                                        title="Perawat Kamar Bedah" nameLabel="Perawat Kamar Bedah" dateLabel="Waktu TTD"
                                        signLabel="TTD Perawat Kamar Bedah" clearLabel="Batal TTD" />
                                    <x-signature.ttd-petugas :ttd="$newForm['ttdDokterOperator']" :date="$newForm['ttdDokterOperatorDate'] ?? ''"
                                        :code="$newForm['ttdDokterOperatorCode'] ?? ''" :locked="$formReadOnly"
                                        sign="setTtdRole('dokterOperator')" clear="clearTtdRole('dokterOperator')"
                                        title="Dokter Operator" nameLabel="Dokter Operator" dateLabel="Waktu TTD"
                                        signLabel="TTD Dokter Operator" clearLabel="Batal TTD" />
                                </div>
                                @if (!$formReadOnly)
                                    <p class="-mt-2 text-xs text-center text-muted">
                                        Tiap TTD langsung tersimpan (bisa menyusul oleh user berbeda). Entri otomatis <strong>terkunci</strong> saat ketiga TTD lengkap.
                                    </p>
                                @endif
                            </section>
                        </fieldset>
