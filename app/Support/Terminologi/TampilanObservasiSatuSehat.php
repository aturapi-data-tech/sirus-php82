<?php

namespace App\Support\Terminologi;

use Illuminate\Support\Facades\DB;

/**
 * Baris tampilan (hanya-baca) layar Coding SATUSEHAT RI & UGD untuk data observasi yang dikirim OTOMATIS
 * oleh kartu SATUSEHAT — tanda vital Observasi Lanjutan, pemberian obat & cairan, oksigen, pengeluaran urin.
 * Kode diambil dari peta yang SAMA dengan sendernya (TandaVitalObservationMap, ObservasiLanjutanMap, KFA
 * Master Obat), jadi yang tampil = yang terkirim; yang dilewati sender ditandai beserta sebabnya.
 */
final class TampilanObservasiSatuSehat
{
    /**
     * Entri Observasi Lanjutan ber-kunci stabil — kunci sama dengan kartu Observation RI/UGD:
     * jam pemeriksaan; jam kembar / kosong diberi akhiran urutan.
     *
     * @return array<string, array>
     */
    public static function entriTandaVital(array $daftar): array
    {
        $hasil = [];
        foreach (array_values($daftar) as $urutan => $entri) {
            $kunci = trim((string) ($entri['waktuPemeriksaan'] ?? '')) ?: 'urutan-' . $urutan;
            if (isset($hasil[$kunci])) {
                $kunci .= '#' . $urutan;
            }
            $hasil[$kunci] = (array) $entri;
        }

        return $hasil;
    }

    /**
     * @param  array $terkirim kunci entri yang sudah terkirim (peta [kunci => ids]); true = semua terkirim (record lama)
     * @return array<int, array{waktu: string, isi: string, kode: string, status: string}>
     */
    public static function barisTandaVital(array $daftar, array|bool $terkirim): array
    {
        $baris = [];
        foreach (self::entriTandaVital($daftar) as $kunci => $entri) {
            $isi = [];
            $kode = [];
            if (!empty($entri['sistolik']) && !empty($entri['distolik'])) {
                $isi[] = 'TD ' . $entri['sistolik'] . '/' . $entri['distolik'];
                $kode[] = TandaVitalObservationMap::TEKANAN_DARAH['panel']['code'];
            }
            foreach (TandaVitalObservationMap::VITAL_TUNGGAL as $kunciVital => $peta) {
                $nilai = $entri[$kunciVital] ?? null;
                if (!empty($nilai) && is_numeric($nilai)) {
                    $isi[] = $peta['label'] . ' ' . $nilai . ' ' . $peta['satuanTampil'];
                    $kode[] = $peta['loinc'];
                }
            }
            $baris[] = [
                'waktu' => trim((string) ($entri['waktuPemeriksaan'] ?? '')) ?: '-',
                'isi' => $isi !== [] ? implode(' · ', $isi) : '(tanpa nilai tanda vital)',
                'kode' => $kode !== [] ? 'LOINC ' . implode(', ', $kode) : 'tidak dikirim — tak ada nilai valid',
                'status' => $kode === [] ? '' : (($terkirim === true || isset($terkirim[$kunci])) ? 'terkirim' : 'akan dikirim'),
            ];
        }

        return $baris;
    }

    /**
     * Pemberian obat & cairan (observasi.obatDanCairan.pemberianObatDanCairan[]) — MedicationAdministration.
     *
     * @param  array|bool $terkirim peta [id entri => id] (UGD); true = kartu sudah mengirim (RI, sekali kirim)
     * @return array<int, array{waktu: string, isi: string, kode: string, status: string}>
     */
    public static function barisPemberianObat(array $daftar, array|bool $terkirim): array
    {
        $productIdList = collect($daftar)->pluck('productId')->map(fn($id) => trim((string) $id))->filter()->unique()->values()->all();
        $kfa = $productIdList === [] ? collect() : DB::table('immst_products')
            ->whereIn('product_id', $productIdList)
            ->whereRaw('product_id_satusehat IS NOT NULL AND LENGTH(TRIM(product_id_satusehat)) > 0')
            ->pluck('product_id_satusehat', 'product_id');

        $baris = [];
        foreach (array_values($daftar) as $urutan => $entri) {
            $kunci = (string) (($entri['id'] ?? '') ?: 'urutan-' . $urutan);
            $productId = trim((string) ($entri['productId'] ?? ''));
            $kodeKfa = $productId !== '' ? (string) ($kfa[$productId] ?? '') : '';
            $dosis = ObservasiLanjutanMap::dosis((string) ($entri['dosis'] ?? ''));
            $rute = ObservasiLanjutanMap::rute((string) ($entri['rute'] ?? ''));

            $kode = match (true) {
                $productId === '' => 'tidak dikirim — obat/cairan diketik bebas, bukan dari Master Obat',
                $kodeKfa === '' => 'tidak dikirim — obat belum punya kode KFA (lengkapi di Master Obat)',
                default => 'KFA ' . $kodeKfa
                    . ($dosis !== null ? ' · dosis ' . $dosis['value'] . ($dosis['code'] ? ' ' . $dosis['code'] . ' (UCUM)' : '') : ' · dosis tak terurai (tidak dikirim)')
                    . ($dosis !== null && $rute !== null ? ' · rute SNOMED ' . $rute['code'] : ''),
            };
            $baris[] = [
                'waktu' => trim((string) ($entri['waktuPemberian'] ?? '')) ?: '-',
                'isi' => trim(implode(' · ', array_filter([
                    trim((string) ($entri['namaObatAtauJenisCairan'] ?? '')),
                    trim((string) ($entri['dosis'] ?? '')),
                    trim((string) ($entri['rute'] ?? '')),
                ]))),
                'kode' => $kode,
                'status' => $kodeKfa === '' ? '' : (($terkirim === true || (is_array($terkirim) && isset($terkirim[$kunci]))) ? 'terkirim' : 'akan dikirim'),
            ];
        }

        return $baris;
    }

    /**
     * Oksigen & pengeluaran urin (RI) — Observation; kode dari ObservasiLanjutanMap.
     *
     * @return array<int, array{waktu: string, isi: string, kode: string, status: string}>
     */
    public static function barisOksigenDanUrin(array $oksigen, array $pengeluaran, bool $terkirim): array
    {
        $baris = [];
        foreach ($oksigen as $entri) {
            $observasi = ObservasiLanjutanMap::oksigen((array) $entri);
            $baris[] = [
                'waktu' => trim((string) ($entri['tanggalWaktuMulai'] ?? '')) ?: '-',
                'isi' => 'Oksigen · ' . trim(trim((string) ($entri['jenisAlatOksigen'] ?? '')) . ' ' . trim((string) ($entri['dosisOksigen'] ?? ''))),
                'kode' => $observasi === [] ? 'tidak dikirim — alat & laju tak terbaca' : 'LOINC ' . collect($observasi)->pluck('code.code')->implode(', '),
                'status' => $observasi === [] ? '' : ($terkirim ? 'terkirim' : 'akan dikirim'),
            ];
        }
        foreach ($pengeluaran as $entri) {
            $observasi = ObservasiLanjutanMap::pengeluaran((array) $entri);
            $baris[] = [
                'waktu' => trim((string) ($entri['waktuPengeluaran'] ?? '')) ?: '-',
                'isi' => 'Pengeluaran · ' . trim(trim((string) ($entri['jenisOutput'] ?? '')) . ' ' . trim((string) ($entri['volume'] ?? '')) . ' mL'),
                'kode' => $observasi === [] ? 'tidak dikirim — jenis selain urin / volume kosong' : 'LOINC ' . collect($observasi)->pluck('code.code')->implode(', '),
                'status' => $observasi === [] ? '' : ($terkirim ? 'terkirim' : 'akan dikirim'),
            ];
        }

        return $baris;
    }
}
