<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Jenis Nilai Rujukan "Kategori" untuk item lab (lbmst_clabitems.lowhigh_status = 'K').
 *
 * Dibuat untuk pemeriksaan berskor seperti TUBEX TF: 0-2 NEGATIF, 3 BORDERLINE, >= 4 POSITIF.
 * Dua jenis lama tidak berubah: 'Y' = batas angka Low/High, 'N'/kosong = teks yang harus sama persis.
 * Kode yang belum mengenal 'K' (termasuk aplikasi 6i) membacanya sebagai teks dan menampilkan
 * normal_m/normal_f, yang diisi otomatis dari kategori (teksNormal) saat master disimpan.
 *
 * Tabel LBMST_CLABITEM_KATEGORIS (docs/ddl-lab-kategori-nilai-rujukan.sql), satu baris per kategori,
 * berupa SALAH SATU dari:
 *   - rentang angka: NILAI_MIN / NILAI_MAX inklusif, kosong = tak terbatas (angka diambil dari teks hasil);
 *   - teks hasil: NILAI_TEKS, alternatif dipisah "|", "*" = wildcard (mis. "NEGATIF|NEG", "+1|+2|+3", "*").
 *     Dibandingkan setelah dinormalkan: huruf besar, tanpa spasi & tanda kurung ("(+1)" = "+1").
 *   STATUS N (normal) / B (borderline) / A (abnormal); KRITIS Y/N.
 * Baris dicocokkan menurut URUT; yang pertama cocok dipakai (teks & angka boleh dicampur, mis. Widal).
 * Status hasil yang disimpan ke lbtxn_checkupdtls.lab_result_status:
 *   N → null (normal, sama seperti sistem lama), B → 'B' (baru), A → 'R' (Abnormal, sudah dikenal).
 *
 * Selama tabel belum dibuat di suatu environment, semua pembacaan mengembalikan daftar kosong
 * (tidak melempar ORA-00942), sehingga halaman lab tetap jalan.
 */
class KategoriNilaiRujukanLab
{
    public const JENIS = 'K';
    public const STATUS_BORDERLINE = 'B';

    public const STATUS_KATEGORI = [
        'N' => 'Normal',
        'B' => 'Borderline',
        'A' => 'Abnormal',
    ];

    /** @var array<string, list<array>> cache per request */
    private static array $cache = [];

    /** Kategori satu item, urut. Kosong bila tidak ada atau tabel belum dibuat. */
    public static function untukItem(string $clabitemId): array
    {
        $clabitemId = trim($clabitemId);
        if ($clabitemId === '') {
            return [];
        }

        if (!array_key_exists($clabitemId, self::$cache)) {
            try {
                self::$cache[$clabitemId] = DB::table('lbmst_clabitem_kategoris')
                    ->where('clabitem_id', $clabitemId)
                    ->orderBy('urut')
                    ->get()
                    ->map(fn($row) => [
                        'urut' => (int) $row->urut,
                        'nilaiMin' => $row->nilai_min === null ? null : (float) $row->nilai_min,
                        'nilaiMax' => $row->nilai_max === null ? null : (float) $row->nilai_max,
                        'nilaiTeks' => trim((string) ($row->nilai_teks ?? '')),
                        'label' => (string) $row->label,
                        'status' => (string) $row->status,
                        'kritis' => (string) ($row->kritis ?? 'N') === 'Y',
                    ])
                    ->all();
            } catch (\Throwable) {
                self::$cache[$clabitemId] = [];
            }
        }

        return self::$cache[$clabitemId];
    }

    public static function lupakan(?string $clabitemId = null): void
    {
        if ($clabitemId === null) {
            self::$cache = [];
        } else {
            unset(self::$cache[trim($clabitemId)]);
        }
    }

    /**
     * Angka dari teks hasil: "SKOR : 2", "SKOR 2", "2" → 2. Rentang "1-2" → 2 (batas atas).
     * Koma desimal dibaca sebagai titik. Null bila tidak ada angka.
     */
    public static function angkaDariHasil(string $hasil): ?float
    {
        if (!preg_match_all('/\d+(?:[.,]\d+)?/', $hasil, $temuan) || $temuan[0] === []) {
            return null;
        }

        return max(array_map(fn(string $angka) => (float) str_replace(',', '.', $angka), $temuan[0]));
    }

    /**
     * Bentuk baku teks untuk dibandingkan: huruf besar, tanpa spasi dan tanda kurung.
     * "(+1)" → "+1", " negatif " → "NEGATIF", "Reactive (Remang)" → "REACTIVEREMANG".
     */
    public static function normalisasi(string $teks): string
    {
        return preg_replace('/[\s()\[\]]+/u', '', mb_strtoupper($teks));
    }

    /** Apakah hasil cocok dengan pola NILAI_TEKS ("A|B|C", "*" wildcard). */
    public static function cocokTeks(string $hasil, string $pola): bool
    {
        return self::alternatifCocok($hasil, $pola) !== null;
    }

    /** Alternatif pola (sebagaimana ditulis) yang cocok dengan hasil, atau null. */
    private static function alternatifCocok(string $hasil, string $pola): ?string
    {
        $hasilBaku = self::normalisasi($hasil);
        if ($hasilBaku === '') {
            return null;
        }

        foreach (explode('|', $pola) as $alternatif) {
            $alternatifBaku = self::normalisasi($alternatif);
            if ($alternatifBaku === '') {
                continue;
            }
            $regex = '/^' . str_replace('\\*', '.*', preg_quote($alternatifBaku, '/')) . '$/u';
            if (preg_match($regex, $hasilBaku)) {
                return trim($alternatif);
            }
        }

        return null;
    }

    /**
     * Pilihan baku untuk isian hasil: PILIHAN PERTAMA tiap baris Teks Hasil (baris yang hanya "*" dilewati).
     * Mis. baris "NEGATIF|NEG|-" → "NEGATIF".
     *
     * @return list<string>
     */
    public static function pilihanBaku(array $kategoriList): array
    {
        $pilihan = [];
        foreach ($kategoriList as $kategori) {
            $pertama = trim(explode('|', (string) ($kategori['nilaiTeks'] ?? ''))[0]);
            if ($pertama !== '' && !str_contains($pertama, '*')) {
                $pilihan[] = mb_strtoupper($pertama);
            }
        }

        return array_values(array_unique($pilihan));
    }

    /**
     * Penulisan baku hasil yang diketik petugas:
     *   - cocok baris teks lewat alternatif PERSIS (tanpa "*") → pilihan pertama baris itu
     *     ("negatif", "-", "NEAGTIF" → "NEGATIF");
     *   - cocok lewat "*" / baris angka / tak cocok → apa adanya, huruf besar ("(+2)", "1-2", "AMORF PHOSPAT").
     */
    public static function bakukanHasil(string $hasil, array $kategoriList): string
    {
        $hasil = trim($hasil);
        if ($hasil === '') {
            return '';
        }

        foreach ($kategoriList as $kategori) {
            $pola = (string) ($kategori['nilaiTeks'] ?? '');
            if ($pola === '') {
                continue;
            }
            $alternatif = self::alternatifCocok($hasil, $pola);
            if ($alternatif === null) {
                continue;
            }
            $pertama = trim(explode('|', $pola)[0]);

            return !str_contains($alternatif, '*') && !str_contains($pertama, '*') ? mb_strtoupper($pertama) : mb_strtoupper($hasil);
        }

        return mb_strtoupper($hasil);
    }

    /** Kategori pertama (menurut urutan) yang cocok untuk hasil, atau null. */
    public static function cocokkan(string $hasil, array $kategoriList): ?array
    {
        $angka = self::angkaDariHasil($hasil);

        foreach ($kategoriList as $kategori) {
            if (($kategori['nilaiTeks'] ?? '') !== '') {
                if (self::cocokTeks($hasil, $kategori['nilaiTeks'])) {
                    return $kategori;
                }
                continue;
            }
            if ($angka === null) {
                continue;
            }
            $diAtasMin = $kategori['nilaiMin'] === null || $angka >= $kategori['nilaiMin'];
            $diBawahMax = $kategori['nilaiMax'] === null || $angka <= $kategori['nilaiMax'];
            if ($diAtasMin && $diBawahMax) {
                return $kategori;
            }
        }

        return null;
    }

    /**
     * Nilai lab_result_status untuk hasil: null (normal), 'B', atau 'R'.
     * Hasil kosong → null. Tidak cocok kategori mana pun:
     *   - hasil berangka → 'R' (di luar semua rentang; lebih aman ditandai);
     *   - hasil tanpa angka & item punya kategori teks → 'R' (teks di luar daftar, sama seperti Kualitatif);
     *   - hasil tanpa angka & item hanya kategori angka → null (tak bisa dinilai, mis. "SKOR : -").
     */
    public static function statusHasil(string $hasil, array $kategoriList): ?string
    {
        if (trim($hasil) === '') {
            return null;
        }

        $kategori = self::cocokkan($hasil, $kategoriList);
        if ($kategori === null) {
            $adaKategoriTeks = collect($kategoriList)->contains(fn($kategori) => ($kategori['nilaiTeks'] ?? '') !== '');

            return self::angkaDariHasil($hasil) !== null || $adaKategoriTeks ? 'R' : null;
        }

        return match ($kategori['status']) {
            'B' => self::STATUS_BORDERLINE,
            'A' => 'R',
            default => null,
        };
    }

    public static function kritis(string $clabitemId, string $hasil): bool
    {
        return (bool) (self::cocokkan($hasil, self::untukItem($clabitemId))['kritis'] ?? false);
    }

    /** Label kategori hasil (mis. "NEGATIF"), atau '' bila tak cocok. */
    public static function labelHasil(string $clabitemId, string $hasil): string
    {
        return (string) (self::cocokkan($hasil, self::untukItem($clabitemId))['label'] ?? '');
    }

    /**
     * Teks nilai normal untuk normal_m/normal_f (VARCHAR2(100)), ASCII saja: charset Oracle Latin-1
     * mengubah tanda seperti >= atau en-dash menjadi "¿". Contoh: "0-2 NEGATIF ; 3 BORDERLINE ; >= 4 POSITIF".
     */
    public static function teksNormal(array $kategoriList): string
    {
        $bagian = [];
        foreach ($kategoriList as $kategori) {
            // Baris teks: cukup labelnya (pola seperti "+1|+2|*" tidak perlu tampil di cetakan).
            if (($kategori['nilaiTeks'] ?? '') !== '') {
                $bagian[] = $kategori['label'];
                continue;
            }
            $min = $kategori['nilaiMin'];
            $max = $kategori['nilaiMax'];
            $rentang = match (true) {
                $min !== null && $max !== null && $min == $max => self::angkaRingkas($min),
                $min !== null && $max !== null => self::angkaRingkas($min) . '-' . self::angkaRingkas($max),
                $min !== null => '>= ' . self::angkaRingkas($min),
                $max !== null => '<= ' . self::angkaRingkas($max),
                default => 'semua',
            };
            $bagian[] = trim($rentang . ' ' . $kategori['label']);
        }

        return implode(' ; ', array_values(array_unique($bagian)));
    }

    private static function angkaRingkas(float $angka): string
    {
        return rtrim(rtrim(number_format($angka, 2, '.', ''), '0'), '.');
    }
}
