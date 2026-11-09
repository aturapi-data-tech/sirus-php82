<?php

namespace App\Support;

use App\Http\Traits\Master\MasterPasien\MasterPasienTrait;
use App\Http\Traits\SATUSEHAT\PatientTrait;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Mencari atau mendaftarkan IHS pasien sesuai alur Master Patient Index SATUSEHAT. Satu sumber aturan
 * untuk tombol di form Master Pasien, Kirim Encounter RJ/UGD/RI, dan pengambilan massal.
 *
 * Perilaku API yang menjadi dasar (diuji di staging dengan pasien dummy resmi, 2026-11-09):
 *  - NIK saja: nama disamarkan, tanpa tanggal lahir, dan bisa memuat resource yang BUKAN IHS. Jadi
 *    hasilnya hanya membuktikan NIK sudah terdaftar, bukan bahwa orangnya sama.
 *  - NIK + nama + tanggal lahir: dicocokkan di server. Nama harus persis (huruf besar-kecil bebas);
 *    nama sebagian atau bergelar menghasilkan 0.
 *
 * Bayi tidak diproses: form Master Pasien mengisi NIK bayi dengan NIK ibu, sehingga NIK itu bisa
 * mengembalikan IHS ibunya. Pasien bayi punya alur sendiri lewat identifier nik-ibu.
 */
class IhsPasienSatuSehat
{
    use PatientTrait, MasterPasienTrait;

    public const SUDAH_ADA = 'sudah_ada';
    public const DITEMUKAN = 'ditemukan';
    public const DIDAFTARKAN = 'didaftarkan';
    public const NIK_TIDAK_VALID = 'nik_tidak_valid';
    public const BAYI = 'bayi';
    public const DATA_KURANG = 'data_kurang';
    public const TIDAK_COCOK = 'tidak_cocok';
    public const GANDA = 'ganda';
    public const NIK_KEMBAR = 'nik_kembar';
    public const WILAYAH_LAMA = 'wilayah_lama';
    public const DITOLAK = 'ditolak';
    public const GAGAL = 'gagal';

    /** Status yang berarti pasien kini punya IHS. */
    public const BERHASIL = [self::SUDAH_ADA, self::DITEMUKAN, self::DIDAFTARKAN];

    /** Label ringkas untuk laporan. */
    public const LABEL = [
        self::SUDAH_ADA => 'Sudah ber-IHS',
        self::DITEMUKAN => 'Ditemukan',
        self::DIDAFTARKAN => 'Didaftarkan baru',
        self::NIK_TIDAK_VALID => 'NIK tidak valid',
        self::BAYI => 'Bayi (alur NIK ibu)',
        self::DATA_KURANG => 'Data kurang',
        self::TIDAK_COCOK => 'Nama/tanggal lahir beda',
        self::GANDA => 'Lebih dari satu IHS',
        self::NIK_KEMBAR => 'NIK dipakai pasien lain',
        self::WILAYAH_LAMA => 'Kode wilayah lama',
        self::DITOLAK => 'Ditolak SATUSEHAT',
        self::GAGAL => 'Gagal, coba lagi',
    ];

    private bool $terinisialisasi = false;

    /** Pasien di master: pakai IHS yang ada, atau cari/daftarkan lalu simpan ke kolom + JSON. */
    public function pastikanDariMaster(string $regNo): array
    {
        $pasien = DB::table('rsmst_pasiens')
            ->where('reg_no', $regNo)
            ->selectRaw("reg_no, reg_name, sex, TO_CHAR(birth_date, 'YYYY-MM-DD') AS tanggal_lahir, nik_bpjs, nokartu_bpjs, patient_uuid, phone, address, rt, rw, des_id, marital_status")
            ->first();

        if (!$pasien) {
            return $this->hasil(self::DATA_KURANG, 'Data pasien tidak ditemukan.');
        }

        if (filled(trim((string) $pasien->patient_uuid))) {
            return $this->hasil(self::SUDAH_ADA, '', trim((string) $pasien->patient_uuid));
        }

        $hasil = $this->cari([
            'regNo' => $regNo,
            'nama' => $pasien->reg_name,
            'tanggalLahir' => $pasien->tanggal_lahir,
            'nik' => $pasien->nik_bpjs,
            'jenisKelamin' => $pasien->sex,
            'telepon' => $pasien->phone,
            'bpjs' => $pasien->nokartu_bpjs,
            'alamat' => $pasien->address,
            'rt' => $pasien->rt,
            'rw' => $pasien->rw,
            'desaId' => $pasien->des_id,
            'statusNikah' => $pasien->marital_status,
        ]);

        if (filled($hasil['ihs'])) {
            try {
                $this->simpan($regNo, $hasil['ihs'], $hasil['status']);
            } catch (\Throwable $e) {
                // IHS yang gagal tersimpan tidak boleh dipakai: pemanggil bisa lanjut dengan data yang
                // tak ada di SIRUS. Percobaan berikutnya menemukannya lagi lewat pencarian.
                Log::warning("IHS pasien {$regNo} gagal disimpan: " . $e->getMessage());

                return $this->hasil(self::GAGAL, 'IHS ditemukan tetapi gagal disimpan di SIRUS: ' . mb_substr($e->getMessage(), 0, 150));
            }
        }

        return $hasil;
    }

    /**
     * Mencari lalu mendaftarkan dari identitas yang diberikan, tanpa menyimpan apa pun di SIRUS.
     *
     * @param array $identitas regNo, nama, tanggalLahir (Y-m-d), nik, jenisKelamin (L/P), telepon, bpjs,
     *                         alamat, rt, rw, desaId, statusNikah
     * @return array{status: string, pesan: string, ihs: ?string}
     */
    public function cari(array $identitas): array
    {
        $nik = trim((string) ($identitas['nik'] ?? ''));
        $nama = trim((string) ($identitas['nama'] ?? ''));
        $tanggalLahir = trim((string) ($identitas['tanggalLahir'] ?? ''));

        if (!preg_match('/^\d{16}$/', $nik)) {
            return $this->hasil(self::NIK_TIDAK_VALID, 'NIK harus 16 digit.');
        }
        if (self::namaBayi($nama)) {
            return $this->hasil(self::BAYI, 'Pasien bayi tidak bisa memakai NIK biasa, karena NIK ibu akan mengembalikan IHS ibunya.');
        }
        if ($nama === '' || $tanggalLahir === '') {
            return $this->hasil(self::DATA_KURANG, 'Nama dan tanggal lahir wajib diisi.');
        }

        try {
            $this->siapkan();
            $sistemNik = 'https://fhir.kemkes.go.id/id/nik|' . $nik;

            $cocok = $this->daftarIhs($this->panggilCari(['identifier' => $sistemNik, 'name' => $nama, 'birthdate' => $tanggalLahir]));
            if (count($cocok) === 1) {
                return $this->hasil(self::DITEMUKAN, 'IHS ditemukan: NIK, nama, dan tanggal lahir cocok.', $cocok[0]);
            }
            if (count($cocok) > 1) {
                return $this->hasil(self::GANDA, 'Ada lebih dari satu IHS untuk identitas ini: ' . implode(', ', $cocok) . '.');
            }

            $hasilNik = $this->panggilCari(['identifier' => $sistemNik]);
            if ($this->daftarIhs($hasilNik) !== []) {
                $namaTerdaftar = $this->namaTerdaftar($hasilNik);

                return $this->hasil(self::TIDAK_COCOK, 'NIK sudah terdaftar' . ($namaTerdaftar !== '' ? " atas nama {$namaTerdaftar}" : '') . ', tetapi nama atau tanggal lahir di SIRUS tidak sama. Samakan dengan KTP lalu coba lagi.');
            }
        } catch (\Throwable $e) {
            return $this->hasil(self::GAGAL, 'Pencarian gagal: ' . $this->ringkasError($e));
        }

        return $this->daftarkan($identitas, $nik, $nama, $tanggalLahir);
    }

    public static function namaBayi(string $nama): bool
    {
        return (bool) preg_match('/^(BAYI|BYI|BY[\s.])/', mb_strtoupper(trim($nama)));
    }

    private function daftarkan(array $identitas, string $nik, string $nama, string $tanggalLahir): array
    {
        $regNo = trim((string) ($identitas['regNo'] ?? ''));
        $dipakaiLain = DB::table('rsmst_pasiens')
            ->whereRaw('TRIM(nik_bpjs) = ?', [$nik])
            ->when($regNo !== '', fn($query) => $query->where('reg_no', '<>', $regNo))
            ->exists();
        if ($dipakaiLain) {
            return $this->hasil(self::NIK_KEMBAR, 'NIK ini juga dipakai pasien lain di SIRUS, jadi tidak didaftarkan otomatis.');
        }

        $gender = match (mb_strtoupper(trim((string) ($identitas['jenisKelamin'] ?? '')))) {
            'L' => 'male',
            'P' => 'female',
            default => null,
        };
        if ($gender === null) {
            return $this->hasil(self::DATA_KURANG, 'Jenis kelamin belum diisi.');
        }

        // Kode Kemendagri 10 digit diturunkan dari desa. Kode lama SIRUS (mis. 50409015) tidak bisa dipakai.
        $desaId = trim((string) ($identitas['desaId'] ?? ''));
        if (!preg_match('/^\d{10}$/', $desaId)) {
            return $this->hasil(self::WILAYAH_LAMA, 'Belum terdaftar di SATUSEHAT, dan kode desa pasien masih format lama. Perbarui desa di Master Pasien.');
        }

        try {
            $this->siapkan();
            $respons = $this->createPatient([
                'name' => $nama,
                'nik' => $nik,
                'bpjs_number' => $identitas['bpjs'] ?? null,
                'birth_date' => $tanggalLahir,
                'gender' => $gender,
                'phone' => $identitas['telepon'] ?? '',
                'marital_status' => $this->kodeNikah((string) ($identitas['statusNikah'] ?? '')),
                'address' => $this->alamat($identitas, $desaId),
            ]);
        } catch (\Throwable $e) {
            return str_contains($e->getMessage(), 'OperationOutcome')
                ? $this->hasil(self::DITOLAK, 'Ditolak SATUSEHAT: ' . $this->ringkasError($e))
                : $this->hasil(self::GAGAL, 'Pendaftaran gagal: ' . $this->ringkasError($e));
        }

        $ihs = $respons['id'] ?? ($respons['data']['patient_id'] ?? null);

        return filled($ihs)
            ? $this->hasil(self::DIDAFTARKAN, 'Pasien baru terdaftar di SATUSEHAT.', (string) $ihs)
            : $this->hasil(self::GAGAL, 'Pendaftaran gagal: respons tanpa nomor IHS.');
    }

    private function simpan(string $regNo, string $ihs, string $status): void
    {
        DB::transaction(function () use ($regNo, $ihs) {
            DB::table('rsmst_pasiens')->where('reg_no', $regNo)->lockForUpdate()->first();
            DB::table('rsmst_pasiens')->where('reg_no', $regNo)->update(['patient_uuid' => $ihs]);

            // JSON ikut diisi: form Master Pasien membacanya, jadi tidak boleh tertinggal kosong.
            $dataPasien = $this->findDataMasterPasien($regNo);
            $dataPasien['pasien']['regNo'] = $regNo;
            $dataPasien['pasien']['identitas']['patientUuid'] = $ihs;
            self::updateJsonMasterPasien($regNo, $dataPasien);
        });

        CakupanIhsPasien::lupakan();
        Log::info("IHS pasien otomatis: {$regNo} = {$ihs} ({$status})");
    }

    private function panggilCari(array $parameter): array
    {
        return $this->makeRequest('get', '/Patient?' . http_build_query($parameter, '', '&', PHP_QUERY_RFC3986));
    }

    /** Hanya entri yang benar-benar punya nomor IHS: pencarian NIK bisa memuat resource lain. @return list<string> */
    private function daftarIhs(array $bundle): array
    {
        $daftar = [];
        foreach ($bundle['entry'] ?? [] as $entri) {
            foreach ($entri['resource']['identifier'] ?? [] as $identifier) {
                if (str_ends_with((string) ($identifier['system'] ?? ''), '/ihs-number') && filled($entri['resource']['id'] ?? null)) {
                    $daftar[] = (string) $entri['resource']['id'];
                    break;
                }
            }
        }

        return array_values(array_unique($daftar));
    }

    private function namaTerdaftar(array $bundle): string
    {
        foreach ($bundle['entry'] ?? [] as $entri) {
            $nama = trim((string) ($entri['resource']['name'][0]['text'] ?? ''));
            if ($nama !== '') {
                return $nama;
            }
        }

        return '';
    }

    private function alamat(array $identitas, string $desaId): array
    {
        $kode = [
            ['url' => 'province', 'valueCode' => substr($desaId, 0, 2)],
            ['url' => 'city', 'valueCode' => substr($desaId, 0, 4)],
            ['url' => 'district', 'valueCode' => substr($desaId, 0, 6)],
            ['url' => 'village', 'valueCode' => $desaId],
        ];
        foreach (['rt', 'rw'] as $bagian) {
            $nilai = ltrim(trim((string) ($identitas[$bagian] ?? '')), '0');
            if ($nilai !== '') {
                $kode[] = ['url' => $bagian, 'valueCode' => $nilai];
            }
        }

        return [
            'use' => 'home',
            'line' => [trim((string) ($identitas['alamat'] ?? '')) ?: '-'],
            'country' => 'ID',
            'extension' => [[
                'url' => 'https://fhir.kemkes.go.id/r4/StructureDefinition/administrativeCode',
                'extension' => $kode,
            ]],
        ];
    }

    private function kodeNikah(string $status): string
    {
        return in_array($status, ['S', 'M', 'D', 'W'], true) ? $status : 'U';
    }

    private function ringkasError(\Throwable $e): string
    {
        $pesan = preg_replace('/^API request failed:\s*/', '', $e->getMessage());
        $data = json_decode((string) $pesan, true);
        $isu = $data['issue'][0] ?? null;
        if (is_array($isu)) {
            return (string) ($isu['details']['text'] ?? ($isu['diagnostics'] ?? 'tanpa keterangan'));
        }

        return mb_substr(trim((string) preg_replace('/\s+/', ' ', (string) $pesan)), 0, 200);
    }

    private function siapkan(): void
    {
        if (!$this->terinisialisasi) {
            $this->initializeSatuSehat();
            $this->terinisialisasi = true;
        }
    }

    private function hasil(string $status, string $pesan, ?string $ihs = null): array
    {
        return ['status' => $status, 'pesan' => $pesan, 'ihs' => $ihs];
    }
}
