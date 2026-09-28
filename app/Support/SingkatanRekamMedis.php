<?php

namespace App\Support;

/**
 * Daftar singkatan & simbol rekam medis — salinan Lampiran I (boleh) dan II (dilarang)
 * Keputusan Direktur 006/SK/RSUI-MDN/01/2026, dipakai SPO 382/SPO/RSUI-MDN/01/2026.
 * Kolom 'tulis' (pengganti) & 'bisaTerbaca' (salah baca yang dicegah) diambil dari materi
 * sosialisasi "Singkatan dan Simbol yang Dilarang dalam Rekam Medis". Bila SK direvisi, perbarui daftar ini bersamaan.
 *
 * Tampil di modal <x-panduan-penulisan-rm.modal> (tombol bantuan di topbar).
 */
class SingkatanRekamMedis
{
    public const SUMBER_SK = '006/SK/RSUI-MDN/01/2026';

    public const SUMBER_SPO = '382/SPO/RSUI-MDN/01/2026';

    /** Aturan penulisan inti (SPO 382 bagian A & C, Ketentuan Umum Lampiran SK 006). */
    public static function aturan(): array
    {
        return [
            'Singkatan yang tidak ada di daftar BOLEH berarti tidak boleh dipakai. Ragu? Tulis lengkap.',
            'Singkatan & simbol DILARANG tidak dipakai dalam bentuk apa pun: isian RME, tulisan tangan, resep, maupun label obat.',
            'Satu singkatan hanya boleh punya satu arti di seluruh rumah sakit.',
            'Nama obat selalu ditulis lengkap, terlebih obat high alert.',
            'Dosis: 1 mg (bukan 1,0 mg), 0,5 mg (bukan ,5 mg). Satuan ditulis mg, mcg, mL, atau unit — tanpa titik.',
            'Menerima catatan/instruksi dengan singkatan terlarang atau meragukan? Konfirmasi dulu ke penulisnya sebelum dikerjakan, terutama instruksi obat.',
            'Butuh singkatan yang belum ada di daftar? Jangan dipakai dulu — usulkan ke Komite/Tim Rekam Medis melalui kepala unit.',
        ];
    }

    /**
     * Konteks penulisan — pengelompokan BANTU di modal panduan supaya PPA cepat menemukan
     * singkatan yang relevan dengan formulir yang sedang diisi. BUKAN bagian SK 006: status
     * boleh/dilarang tetap mengikuti Lampiran I/II. Satu kode boleh masuk beberapa konteks.
     */
    public static function konteks(): array
    {
        return [
            ['id' => 'resep', 'label' => 'Resep & Obat', 'dipakaiDi' => 'e-resep, rekonsiliasi obat, catatan pemberian obat, telaah apotek, label obat'],
            ['id' => 'soap', 'label' => 'CPPT / SOAP & Asesmen', 'dipakaiDi' => 'CPPT, SBAR, asesmen awal medis/keperawatan, resume medis, ringkasan pulang'],
            ['id' => 'fisik', 'label' => 'Pemeriksaan Fisik & TTV', 'dipakaiDi' => 'pemeriksaan fisik, tanda vital, observasi lanjutan, EWS'],
            ['id' => 'diagnosis', 'label' => 'Diagnosis', 'dipakaiDi' => 'diagnosis kerja/banding, resume medis, rujukan'],
            ['id' => 'penunjang', 'label' => 'Penunjang Lab & Radiologi', 'dipakaiDi' => 'order & hasil laboratorium, radiologi, EKG'],
            ['id' => 'kebidanan', 'label' => 'Kebidanan & Neonatus', 'dipakaiDi' => 'dokumen VK, persalinan, nifas, bayi baru lahir'],
            ['id' => 'tindakan', 'label' => 'Tindakan, Bedah & Trauma', 'dipakaiDi' => 'laporan operasi, tindakan medis, kasus trauma/tulang'],
            ['id' => 'dokumen', 'label' => 'Modul Dokumen & Administrasi', 'dipakaiDi' => 'consent, surat keterangan, pulang APS, transfer, pelaporan insiden'],
        ];
    }

    /** Id konteks → kode Lampiran I. Dipisah dari dilarang karena kode sama bisa beda arti (OD, AF, °). */
    private static function konteksBoleh(): array
    {
        return [
            'soap' => [
                'A', 'A/E', 'Ass', 'Ax', 'B', 'BAB', 'BAK', 'C', 'Dbn', 'DD', 'Dx', 'Dysp', 'e.c', 'K.0', 'PDX',
                'PEX', 'PF', 'PMX', 'PTX', 'RPD', 'RPK', 'SDE', 'SQO', 'St', 'Taa', 'TAK', 'TM', 'TS', 'Tx',
                'WDx', 'Ψ', '♀', '♂', '✝', '●', '↑', '↓', '=', '≠',
            ],
            'fisik' => [
                'A', 'A-i-c-d', 'Abd', 'Auric', 'B', 'BB', 'Br', 'BU', 'C', 'C/', 'CM', 'COA', 'Cyan', 'Dbn',
                'Dysp', 'Ext', 'GCS', 'Ict', 'Jr', 'JVP', 'K/L', 'Kep', 'L', 'LMN', 'M-', 'Mot', 'N', 'NT',
                'OD', 'OS', 'P/', 'PBI', 'Reg', 'RF', 'Rh', 'Rom', 'RP/Rpat', 'RR', 'RT', 'S1 – S2', 'Sat',
                'Si.S2', 'Sp 02', 't', 'T/TD', 'Taa', 'TAK', 'TH/thx', 'TIO', 'ttb', 'Ttu', 'Tu', 'uk', 'UMN',
                'Ves', 'wh', '↑', '↓', '°',
            ],
            'resep' => [
                'a.c', 'Caps', 'Cth', 'd.c', 'i.m', 'i.v', 'Inj', 'NSAID', 'O2', 'p.c', 'p.o', 'p.r.n', 'PTX',
                'Tab', 'Tx',
            ],
            'diagnosis' => [
                'ACS', 'AF', 'AFP', 'AN', 'App', 'ARDS', 'ASD', 'ASHD', 'BBB', 'BPH', 'Ca.', 'CC', 'CHF', 'CKB',
                'CKR', 'COB', 'Comcer', 'COPD', 'COR', 'COS', 'CPD', 'CVA', 'DD', 'DHF', 'DM', 'DOA', 'DSS',
                'DVT', 'Dx', 'e.c', 'EDH', 'FAM', 'FBC', 'FC', 'Fr,Fx', 'FUO', 'GE', 'GERD', 'HCC', 'HHF',
                'HIL', 'HIM', 'HM', 'HT', 'ICD', 'IDDM', 'IMA', 'Isk', 'ISPA', 'ITP', 'IUFD', 'KET', 'KLL',
                'KP', 'KPD', 'LBP', 'MH', 'MODS', 'NIDDM', 'OA', 'RA', 'SAH', 'SCH', 'SDH', 'SH', 'SRMD', 'STD',
                'TBC', 'TIA', 'TN', 'TOA', 'TTH', 'URI', 'UTI', 'VSD', 'WDx', '#',
            ],
            'tindakan' => [
                'AJ', 'BSO', 'C1.C2…C8', 'CKB', 'CKR', 'COB', 'Comcer', 'COR', 'COS', 'EDH', 'FBC', 'Fr,Fx',
                'K-L', 'KLL', 'L1.L2….L5', 'NGT', 'OK', 'Psg', 'Rom', 'SC', 'SCH', 'SDH', 'T1. T2.T3…T12',
                'TENS', 'TKR', 'TUR', 'UPPA', 'VE', '#',
            ],
            'dokumen' => [
                'APS', 'BTK', 'DOA', 'IGD', 'IKP', 'IPI', 'KNC', 'KPC', 'KRS', 'KTC', 'KTD', 'MRS', 'OB', 'OK',
                'PL', 'Regt', 'SKS', 'UPPA', 'Ψ', '♀', '♂', '✝', '●',
            ],
            'kebidanan' => [
                'AS', 'BBL', 'BBLR', 'BBLSR', 'CPD', 'DJJ', 'EMAS', 'FU', 'IUFD', 'KET', 'KPD', 'NA',
                'Ped', 'PP', 'Ret', 'SC', 'Spt b', 'TFU', 'TN', 'UUB', 'UUK', 'V/V', 'VE', 'VT',
            ],
            'penunjang' => [
                'BSN', 'BTA', 'Chl', 'CO2', 'CT Scan', 'DL', 'EKG', 'GDA', 'GDP', 'H2O', 'Hb', 'Hct', 'IVP',
                'K', 'LFT', 'MRI', 'O2', 'p.h', 'Ph', 'RBC', 'RFT', 'Ro', 'SK', 'TH/thx', 'UL', 'WBC',
            ],
        ];
    }

    /** Id konteks → kode Lampiran II. */
    private static function konteksDilarang(): array
    {
        return [
            'resep' => [
                'U', 'AB', 'IU', 'AF', 'MS', 'PCT', 'Cefo', 'Ceftri', 'QD / qd', 'QOD / qod', 'MSO4', 'MgSO4',
                'cc', 'µg', 'SC / SQ / sub q', 'OD', 'AD / AS / AU', 'IN', 'IJ', 'D/C', 'TIW', 'UD', 'Ʒ', 'x3d',
                '> dan <', '/', '@', '&', '+', '°', 'Ø, 0, Φ',
                'Angka nol di belakang koma (1,0 mg)', 'Tanpa angka nol di depan koma (,5 mg)',
                'Titik sesudah satuan (mg. / mL.)',
            ],
            'diagnosis' => [
                'AB', 'Inf', 'Taxe TF',
            ],
            'soap' => [
                'Inf', 'SB', 'IWIR', 'Ma/mi', 'Px', 'Ka/Ki', 'Obs', 'NK', 'Pac', 'MPS', 'T.a.a/t.a.k', 'a/i',
                'V', 'T9', 'R', 'Inc', 'Lanj', 'D/C', '@', '&', '+', '–',
            ],
            'tindakan' => [
                'VS', 'Ind', 'Ka/Ki', 'R',
            ],
            'kebidanan' => [
                'Ind', 'TP', 'HB', 'SF', 'FT', 'ASIL', 'RG', 'Spt',
            ],
            'penunjang' => [
                'Dr', 'PCT',
            ],
            'dokumen' => [
                'Px', 'Obs', 'PB,P/B', 'D/C',
            ],
            'fisik' => [
                'Ka/Ki', 'T.a.a/t.a.k', 'R', '+', '°', '–',
            ],
        ];
    }

    /**
     * Semua daftar untuk modal panduan. Tiap baris ditambah 'cari' (teks huruf kecil gabungan
     * kode + arti + tulis + bisaTerbaca, dicocokkan kolom pencarian Alpine) dan 'konteks' (id konteks).
     */
    public static function untukPanduan(): array
    {
        $balikPeta = function (array $kodePerKonteks): array {
            $petaKonteks = [];
            foreach ($kodePerKonteks as $idKonteks => $kodeList) {
                foreach ($kodeList as $kode) {
                    $petaKonteks[$kode][] = $idKonteks;
                }
            }

            return $petaKonteks;
        };

        $lengkapi = fn(array $rows, array $petaKonteks) => array_map(
            fn(array $row) => $row + [
                'cari' => mb_strtolower($row['kode'] . ' ' . $row['arti'] . ' ' . ($row['tulis'] ?? '') . ' ' . ($row['bisaTerbaca'] ?? '')),
                'konteks' => $petaKonteks[$row['kode']] ?? [],
            ],
            $rows,
        );

        $petaBoleh = $balikPeta(self::konteksBoleh());

        return [
            'konteks' => self::konteks(),
            'dilarang' => $lengkapi(array_merge(self::singkatanDilarang(), self::simbolDilarang()), $balikPeta(self::konteksDilarang())),
            'singkatanBoleh' => $lengkapi(self::singkatanBoleh(), $petaBoleh),
            'simbolBoleh' => $lengkapi(self::simbolBoleh(), $petaBoleh),
        ];
    }

    public static function simbolBoleh(): array
    {
        return [
            ['kode' => 'Ψ', 'arti' => 'Alergi — ditulis dengan warna biru'],
            ['kode' => '♀', 'arti' => 'Pasien berjenis kelamin perempuan'],
            ['kode' => '♂', 'arti' => 'Pasien berjenis kelamin laki-laki'],
            ['kode' => '✝', 'arti' => 'Pasien yang meninggal'],
            ['kode' => '●', 'arti' => 'Kasus penyakit menular — warna sesuai tabel di bawah'],
            ['kode' => '↑', 'arti' => 'Kenaikan'],
            ['kode' => '↓', 'arti' => 'Penurunan'],
            ['kode' => '=', 'arti' => 'Sama dengan'],
            ['kode' => '≠', 'arti' => 'Tidak sama dengan'],
            ['kode' => '°', 'arti' => 'Derajat'],
            ['kode' => '#', 'arti' => 'Fraktur'],
        ];
    }

    /** Warna simbol ● untuk kasus penyakit menular. */
    public static function warnaPenyakitMenular(): array
    {
        return [
            ['kode' => 'HIV atau AIDS', 'arti' => '● bulatan berwarna MERAH'],
            ['kode' => 'HbsAg positif atau Hepatitis B', 'arti' => '● bulatan berwarna BIRU'],
            ['kode' => 'Tuberculosis positif', 'arti' => '● bulatan berwarna HIJAU'],
        ];
    }

    /** Lampiran I huruf B — 235 singkatan, urut abjad. */
    public static function singkatanBoleh(): array
    {
        return [
            ['kode' => 'A', 'arti' => 'Airway (Jalan Nafas)'],
            ['kode' => 'A-i-c-d', 'arti' => 'Anemia Icterus Cianosis Dispneu'],
            ['kode' => 'a.c', 'arti' => 'Sebelum Makan'],
            ['kode' => 'A/E', 'arti' => 'Assessment/Evaluasi'],
            ['kode' => 'Abd', 'arti' => 'Abdomen (perut)'],
            ['kode' => 'ACS', 'arti' => 'Acute Coronary Syndrome'],
            ['kode' => 'AF', 'arti' => 'Atrial Fibrilasi'],
            ['kode' => 'AFP', 'arti' => 'Acute Flaccid Paralysis'],
            ['kode' => 'AJ', 'arti' => 'Angkat Jahitan'],
            ['kode' => 'AN', 'arti' => 'Anemia'],
            ['kode' => 'App', 'arti' => 'Appendicitis'],
            ['kode' => 'APS', 'arti' => 'Atas Permintaan Sendiri'],
            ['kode' => 'ARDS', 'arti' => 'Adult Respiratory Distress Syndrome'],
            ['kode' => 'AS', 'arti' => 'Apgar Score'],
            ['kode' => 'ASD', 'arti' => 'Atrial Septal Defect'],
            ['kode' => 'ASHD', 'arti' => 'Atherosclerotic Heart Disease'],
            ['kode' => 'Ass', 'arti' => 'Assessment'],
            ['kode' => 'Auric', 'arti' => 'Auriculen (Telinga)'],
            ['kode' => 'Ax', 'arti' => 'Anamnesa'],
            ['kode' => 'B', 'arti' => 'Breathing'],
            ['kode' => 'BAB', 'arti' => 'Buang Air Besar'],
            ['kode' => 'BAK', 'arti' => 'Buang Air Kecil'],
            ['kode' => 'BB', 'arti' => 'Berat Badan'],
            ['kode' => 'BBB', 'arti' => 'Batu Buli-Buli'],
            ['kode' => 'BBL', 'arti' => 'Bayi Baru Lahir'],
            ['kode' => 'BBLR', 'arti' => 'Berat Badan Lahir Rendah'],
            ['kode' => 'BBLSR', 'arti' => 'Berat Badan Lahir Sangat Rendah'],
            ['kode' => 'BPH', 'arti' => 'Benign Prostatic Hypertrophy'],
            ['kode' => 'Br', 'arti' => 'Bronchial'],
            ['kode' => 'BSN', 'arti' => 'Kadar Gula Darah Puasa'],
            ['kode' => 'BSO', 'arti' => 'Bilateral Salphingo Oophorectomy'],
            ['kode' => 'BTA', 'arti' => 'Basil Tahan Asam'],
            ['kode' => 'BTK', 'arti' => 'Banyak Terima Kasih'],
            ['kode' => 'BU', 'arti' => 'Bising Usus'],
            ['kode' => 'C', 'arti' => 'Circulation'],
            ['kode' => 'C/', 'arti' => 'Cor'],
            ['kode' => 'C1.C2…C8', 'arti' => 'Tulang Belakang Bagian Cervical'],
            ['kode' => 'Ca.', 'arti' => 'Cancer'],
            ['kode' => 'Caps', 'arti' => 'Capsul (obat)'],
            ['kode' => 'CC', 'arti' => 'Common Cold'],
            ['kode' => 'CHF', 'arti' => 'Congestive Heart Failure'],
            ['kode' => 'Chl', 'arti' => 'Chloride'],
            ['kode' => 'CKB', 'arti' => 'Cidera Kepala Berat'],
            ['kode' => 'CKR', 'arti' => 'Cidera Kepala Ringan'],
            ['kode' => 'CM', 'arti' => 'Compos Mentis'],
            ['kode' => 'CO2', 'arti' => 'Karbon Dioksida'],
            ['kode' => 'COA', 'arti' => 'Camera Occuli Anterior'],
            ['kode' => 'COB', 'arti' => 'Cedera Otak Berat'],
            ['kode' => 'Comcer', 'arti' => 'Commotio Cerebri'],
            ['kode' => 'COPD', 'arti' => 'Chronic Obstructive Pulmonary Disease'],
            ['kode' => 'COR', 'arti' => 'Cedera Otak Ringan'],
            ['kode' => 'COS', 'arti' => 'Cedera Otak Sedang'],
            ['kode' => 'CPD', 'arti' => 'Cephalo Pelvic Disproportion'],
            ['kode' => 'Cth', 'arti' => 'Sendok Teh'],
            ['kode' => 'CT Scan', 'arti' => 'Computerized Tomography Scanning'],
            ['kode' => 'CVA', 'arti' => 'Cerebro Vascular Accident'],
            ['kode' => 'Cyan', 'arti' => 'Cyanosis'],
            ['kode' => 'd.c', 'arti' => 'Bersama Makan'],
            ['kode' => 'Dbn', 'arti' => 'Dalam Batas Normal'],
            ['kode' => 'DD', 'arti' => 'Differential Diagnosis'],
            ['kode' => 'DHF', 'arti' => 'Dengue Haemorrhagic Fever (Demam Berdarah Dengue)'],
            ['kode' => 'DJJ', 'arti' => 'Denyut Jantung Janin'],
            ['kode' => 'DL', 'arti' => 'Darah Lengkap'],
            ['kode' => 'DM', 'arti' => 'Diabetes Mellitus'],
            ['kode' => 'DOA', 'arti' => 'Dead on Arrival'],
            ['kode' => 'DSS', 'arti' => 'Dengue Shock Syndrome'],
            ['kode' => 'DVT', 'arti' => 'Deep Vein Thrombosis'],
            ['kode' => 'Dx', 'arti' => 'Diagnosa'],
            ['kode' => 'Dysp', 'arti' => 'Sesak'],
            ['kode' => 'e.c', 'arti' => 'Et Causa'],
            ['kode' => 'EDH', 'arti' => 'Epidural Haemorrhage'],
            ['kode' => 'EKG', 'arti' => 'Elektrokardiografi'],
            ['kode' => 'EMAS', 'arti' => 'Expanding Maternal and Neonatal Survival'],
            ['kode' => 'Ext', 'arti' => 'Extremitas (anggota gerak)'],
            ['kode' => 'FAM', 'arti' => 'Fibroadenoma Mammae'],
            ['kode' => 'FBC', 'arti' => 'Fracture Basis Cranii'],
            ['kode' => 'FC', 'arti' => 'Febrile Convulsion'],
            ['kode' => 'Fr,Fx', 'arti' => 'Fraktur'],
            ['kode' => 'FU', 'arti' => 'Fundus Uteri'],
            ['kode' => 'FUO', 'arti' => 'Fever Of Unknown Origin'],
            ['kode' => 'GCS', 'arti' => 'Glasgow Coma Scale'],
            ['kode' => 'GDA', 'arti' => 'Gula Darah Acak'],
            ['kode' => 'GDP', 'arti' => 'Gula Darah Puasa'],
            ['kode' => 'GE', 'arti' => 'Gastro Enteritis'],
            ['kode' => 'GERD', 'arti' => 'Gastroesophageal Reflux Disease'],
            ['kode' => 'H2O', 'arti' => 'Air'],
            ['kode' => 'Hb', 'arti' => 'Haemoglobin'],
            ['kode' => 'HCC', 'arti' => 'Hepato Cell Carcinoma'],
            ['kode' => 'Hct', 'arti' => 'Hematokrit'],
            ['kode' => 'HHF', 'arti' => 'Hypertensive Heart Failure'],
            ['kode' => 'HIL', 'arti' => 'Hernia Inguinalis Lateralis'],
            ['kode' => 'HIM', 'arti' => 'Hernia Inguinalis Medialis'],
            ['kode' => 'HM', 'arti' => 'Hematemesis Melena'],
            ['kode' => 'HT', 'arti' => 'Hipertensi'],
            ['kode' => 'i.m', 'arti' => 'Intramuscular'],
            ['kode' => 'i.v', 'arti' => 'Intra Venous'],
            ['kode' => 'ICD', 'arti' => 'International Classification of Diseases'],
            ['kode' => 'Ict', 'arti' => 'Icterus'],
            ['kode' => 'IDDM', 'arti' => 'Insulin Dependent Diabetes Mellitus'],
            ['kode' => 'IGD', 'arti' => 'Instalasi Gawat Darurat'],
            ['kode' => 'IKP', 'arti' => 'Insiden Keselamatan Pasien'],
            ['kode' => 'IMA', 'arti' => 'Infark Miokard Akut'],
            ['kode' => 'Inj', 'arti' => 'Injeksi'],
            ['kode' => 'IPI', 'arti' => 'Instansi Pelayanan Intensive'],
            ['kode' => 'Isk', 'arti' => 'Infeksi Saluran Kemih'],
            ['kode' => 'ISPA', 'arti' => 'Infeksi Saluran Pernafasan Atas'],
            ['kode' => 'ITP', 'arti' => 'Idiopathic Thrombocytopenic Purpura'],
            ['kode' => 'IUFD', 'arti' => 'Intra Uterine Fetal Death (Bayi Mati Dalam Kandungan)'],
            ['kode' => 'IVP', 'arti' => 'Intra Venous Pyelography'],
            ['kode' => 'Jr', 'arti' => 'Jari'],
            ['kode' => 'JVP', 'arti' => 'Jugular Venous Pressure'],
            ['kode' => 'K', 'arti' => 'Kalium'],
            ['kode' => 'K-L', 'arti' => 'Kumbah Lambung'],
            ['kode' => 'K.0', 'arti' => 'Keluhan Utama/Keadaan Umum'],
            ['kode' => 'K/L', 'arti' => 'Kepala/Leher'],
            ['kode' => 'Kep', 'arti' => 'Kepala'],
            ['kode' => 'KET', 'arti' => 'Kehamilan Ektopik Terganggu'],
            ['kode' => 'KLL', 'arti' => 'Kecelakaan Lalu Lintas'],
            ['kode' => 'KNC', 'arti' => 'Kejadian Nyaris Cidera'],
            ['kode' => 'KP', 'arti' => 'Koch Pulmonum'],
            ['kode' => 'KPC', 'arti' => 'Kejadian Potensial Cedera'],
            ['kode' => 'KPD', 'arti' => 'Ketuban Pecah Dini'],
            ['kode' => 'KRS', 'arti' => 'Keluar Rumah Sakit'],
            ['kode' => 'KTC', 'arti' => 'Kejadian Tidak Cedera'],
            ['kode' => 'KTD', 'arti' => 'Kejadian Tidak Diharapkan'],
            ['kode' => 'L', 'arti' => 'Left (Kiri)'],
            ['kode' => 'L1.L2….L5', 'arti' => 'Tulang Bagian Lumbal'],
            ['kode' => 'LBP', 'arti' => 'Low Back Pain'],
            ['kode' => 'LFT', 'arti' => 'Liver Function Test (Tes Fungsi Hati)'],
            ['kode' => 'LMN', 'arti' => 'Lower Motor Neuron'],
            ['kode' => 'M-', 'arti' => 'Mur2'],
            ['kode' => 'MH', 'arti' => 'Morbus Hansen'],
            ['kode' => 'MODS', 'arti' => 'Multiple Organ Dysfunction Syndrome'],
            ['kode' => 'Mot', 'arti' => 'Motorik'],
            ['kode' => 'MRI', 'arti' => 'Magnetic Resonance Imaging'],
            ['kode' => 'MRS', 'arti' => 'Masuk Rumah Sakit'],
            ['kode' => 'N', 'arti' => 'Nadi'],
            ['kode' => 'NA', 'arti' => 'Neonaterum (Bayi)'],
            ['kode' => 'NGT', 'arti' => 'Nasogastric (Tube)'],
            ['kode' => 'NIDDM', 'arti' => 'Non Insulin Dependent Diabetes Mellitus'],
            ['kode' => 'NSAID', 'arti' => 'Non-Steroidal Anti-Inflammatory Drug'],
            ['kode' => 'NT', 'arti' => 'Nyeri Tekan'],
            ['kode' => 'O2', 'arti' => 'Oksigen'],
            ['kode' => 'OA', 'arti' => 'Osteo Arthritis'],
            ['kode' => 'OB', 'arti' => 'Orang Baru'],
            ['kode' => 'OD', 'arti' => 'Oculus Dextra'],
            ['kode' => 'OK', 'arti' => 'Operating Kamer (Kamar Operasi)'],
            ['kode' => 'OS', 'arti' => 'Oculus Sinistra'],
            ['kode' => 'p.c', 'arti' => 'Sesudah Makan'],
            ['kode' => 'p.h', 'arti' => 'Hydrogen Ion Concentration'],
            ['kode' => 'p.o', 'arti' => 'Per Oral'],
            ['kode' => 'p.r.n', 'arti' => 'Kalau Perlu'],
            ['kode' => 'P/', 'arti' => 'Pullnomal'],
            ['kode' => 'PBI', 'arti' => 'Pupil Besar Isokor'],
            ['kode' => 'PDX', 'arti' => 'Planning Diagnose'],
            ['kode' => 'Ped', 'arti' => 'Pediatrik'],
            ['kode' => 'PEX', 'arti' => 'Planning Edukasi'],
            ['kode' => 'PF', 'arti' => 'Pemeriksaan Fisik'],
            ['kode' => 'Ph', 'arti' => 'Hydrogen Ion Concentration'],
            ['kode' => 'PL', 'arti' => 'Pulang'],
            ['kode' => 'PMX', 'arti' => 'Planning Monitoring'],
            ['kode' => 'PP', 'arti' => 'Post Partum'],
            ['kode' => 'Psg', 'arti' => 'Pasang'],
            ['kode' => 'PTX', 'arti' => 'Planning Terapi'],
            ['kode' => 'RA', 'arti' => 'Rheumatoid Arthritis'],
            ['kode' => 'RBC', 'arti' => 'Red Blood Cell'],
            ['kode' => 'Reg', 'arti' => 'Regular'],
            ['kode' => 'Regt', 'arti' => 'Register'],
            ['kode' => 'Ret', 'arti' => 'Retensio'],
            ['kode' => 'RF', 'arti' => 'Reflex Fisiologis'],
            ['kode' => 'RFT', 'arti' => 'Renal Function Test (Tes Fungsi Ginjal)'],
            ['kode' => 'Rh', 'arti' => 'Ronchi'],
            ['kode' => 'Ro', 'arti' => 'Rontgen'],
            ['kode' => 'Rom', 'arti' => 'Range Of Motion'],
            ['kode' => 'RP/Rpat', 'arti' => 'Refleks Patologis'],
            ['kode' => 'RPD', 'arti' => 'Riwayat Penyakit Dahulu'],
            ['kode' => 'RPK', 'arti' => 'Riwayat Penyakit Keluarga'],
            ['kode' => 'RR', 'arti' => 'Respiratory Rate'],
            ['kode' => 'RT', 'arti' => 'Rectal Touch'],
            ['kode' => 'S1 – S2', 'arti' => 'Bunyi Jantung 1 - Bunyi Jantung 2'],
            ['kode' => 'SAH', 'arti' => 'Sub Arachnoid Hemorrhage'],
            ['kode' => 'Sat', 'arti' => 'Saturasi'],
            ['kode' => 'SC', 'arti' => 'Sectio Caesarea'],
            ['kode' => 'SCH', 'arti' => 'Supracondylar Humerus'],
            ['kode' => 'SDE', 'arti' => 'Sulit Dievaluasi'],
            ['kode' => 'SDH', 'arti' => 'Subdural Hemorrhage'],
            ['kode' => 'SH', 'arti' => 'Sirosis Hepatis'],
            ['kode' => 'Si.S2', 'arti' => 'S5'],
            ['kode' => 'SK', 'arti' => 'Serum Kreatinin'],
            ['kode' => 'SKS', 'arti' => 'Surat Keterangan Sehat'],
            ['kode' => 'Sp 02', 'arti' => 'Saturasi Tekanan Oksigen'],
            ['kode' => 'Spt b', 'arti' => 'Spontan Belakang Kepala (Partus Normal)'],
            ['kode' => 'SQO', 'arti' => 'Status Quo (Tetap )'],
            ['kode' => 'SRMD', 'arti' => 'Stress Related Mucosal Damage'],
            ['kode' => 'St', 'arti' => 'Status'],
            ['kode' => 'STD', 'arti' => 'Sexually Transmitted Disease'],
            ['kode' => 't', 'arti' => 'Temperatur (Suhu Badan)'],
            ['kode' => 'T/TD', 'arti' => 'Tensi (Tekanan Darah)'],
            ['kode' => 'T1. T2.T3…T12', 'arti' => 'Tulang Belakang Bagian Thoracal'],
            ['kode' => 'Taa', 'arti' => 'Tak Ada Apa-Apa'],
            ['kode' => 'Tab', 'arti' => 'Tablet (Obat)'],
            ['kode' => 'TAK', 'arti' => 'Tak Ada Kelainan'],
            ['kode' => 'TBC', 'arti' => 'Tuberculosis'],
            ['kode' => 'TENS', 'arti' => 'Transcutaneous Electrical Nerve Stimulation'],
            ['kode' => 'TFU', 'arti' => 'Tinggi Fundus Uteri'],
            ['kode' => 'TH/thx', 'arti' => 'Thorax (Dada)'],
            ['kode' => 'TIA', 'arti' => 'Transient Ischemic Attack'],
            ['kode' => 'TIO', 'arti' => 'Tekanan Intra Okuler'],
            ['kode' => 'TKR', 'arti' => 'Total Knee Replacement'],
            ['kode' => 'TM', 'arti' => 'Tidak Mampu'],
            ['kode' => 'TN', 'arti' => 'Tetanus Neonatorum'],
            ['kode' => 'TOA', 'arti' => 'Tuba Ovari Abscess'],
            ['kode' => 'TS', 'arti' => 'Teman Sejawat'],
            ['kode' => 'TTH', 'arti' => 'Tension Type Headache'],
            ['kode' => 'ttb', 'arti' => 'Tidak Teraba'],
            ['kode' => 'Ttu', 'arti' => 'Tidak Teratur'],
            ['kode' => 'Tu', 'arti' => 'Tumor'],
            ['kode' => 'TUR', 'arti' => 'Transurethral Resection'],
            ['kode' => 'Tx', 'arti' => 'Terapi'],
            ['kode' => 'uk', 'arti' => 'Ukuran'],
            ['kode' => 'UL', 'arti' => 'Urine Lengkap'],
            ['kode' => 'UMN', 'arti' => 'Upper Motor Neuron'],
            ['kode' => 'UPPA', 'arti' => 'Unit Perawatan Pasca Anestesi'],
            ['kode' => 'URI', 'arti' => 'Upper Respiratory Infection'],
            ['kode' => 'UTI', 'arti' => 'Urinary Tract Infection'],
            ['kode' => 'UUB', 'arti' => 'Ubun-ubun Besar'],
            ['kode' => 'UUK', 'arti' => 'Ubun-ubun Kecil'],
            ['kode' => 'V/V', 'arti' => 'Vulva/Vagina'],
            ['kode' => 'VE', 'arti' => 'Vacum Ekstraksi'],
            ['kode' => 'Ves', 'arti' => 'Vesikuler'],
            ['kode' => 'VSD', 'arti' => 'Ventricular Septal Defect'],
            ['kode' => 'VT', 'arti' => 'Vaginal Toucher'],
            ['kode' => 'WBC', 'arti' => 'White Blood Cell'],
            ['kode' => 'WDx', 'arti' => 'Working Diagnosis'],
            ['kode' => 'wh', 'arti' => 'Wheezing'],
        ];
    }

    /** Lampiran II huruf A. doNotUse = daftar "Do Not Use" The Joint Commission (tanda ** di SK). */
    public static function simbolDilarang(): array
    {
        return [
            ['kode' => 'Ʒ', 'arti' => 'Dram — sangat mirip dengan angka "3"'],
            ['kode' => 'x3d', 'arti' => 'For three days — dapat disalahartikan menjadi 3 dosis', 'tulis' => 'selama 3 hari', 'bisaTerbaca' => '3 dosis'],
            ['kode' => '> dan <', 'arti' => 'Bila ditulis tanpa spasi dapat terbaca sebagai angka lain'],
            ['kode' => '/', 'arti' => 'Pada penulisan dosis, bila ditulis tanpa spasi dapat terbaca angka 1'],
            ['kode' => '@', 'arti' => 'At — dapat terbaca "2"', 'tulis' => 'tulis katanya'],
            ['kode' => '&', 'arti' => 'And — dapat terbaca "2"', 'tulis' => 'tulis katanya'],
            ['kode' => '+', 'arti' => 'Plus atau ada — dapat terbaca "4"', 'tulis' => 'tulis katanya'],
            ['kode' => '°', 'arti' => 'Hour — berisiko terbaca zero (nol)', 'tulis' => 'tulis katanya'],
            ['kode' => 'Ø, 0, Φ', 'arti' => 'Zero atau null sign — berisiko terbaca 4, 6, 8, atau 9'],
            ['kode' => '–', 'arti' => 'Kurang — dapat terbaca "tidak ada"'],
            ['kode' => 'Angka nol di belakang koma (1,0 mg)', 'arti' => 'Koma tidak terlihat sehingga terbaca 10 mg — tulis 1 mg', 'doNotUse' => true],
            ['kode' => 'Tanpa angka nol di depan koma (,5 mg)', 'arti' => 'Koma tidak terlihat sehingga terbaca 5 mg — tulis 0,5 mg', 'doNotUse' => true],
            ['kode' => 'Titik sesudah satuan (mg. / mL.)', 'arti' => 'Titik terbaca angka 1 — tulis mg, mL tanpa titik'],
        ];
    }

    /** Lampiran II huruf B — 51 singkatan. doNotUse = tanda ** di SK. */
    public static function singkatanDilarang(): array
    {
        return [
            ['kode' => 'U', 'arti' => 'Unit', 'doNotUse' => true, 'tulis' => 'unit', 'bisaTerbaca' => '0, 4, atau cc'],
            ['kode' => 'AB', 'arti' => 'Antibiotik', 'tulis' => 'nama antibiotiknya', 'bisaTerbaca' => 'antibiotik, golongan darah, abortus'],
            ['kode' => 'Inf', 'arti' => 'Infeksi'],
            ['kode' => 'IU', 'arti' => 'International Unit', 'doNotUse' => true, 'tulis' => 'unit', 'bisaTerbaca' => 'IV atau 10'],
            ['kode' => 'SB', 'arti' => ''],
            ['kode' => 'VS', 'arti' => 'Vena Sectie'],
            ['kode' => 'IWIR', 'arti' => ''],
            ['kode' => 'AF', 'arti' => 'Alinamin F', 'tulis' => 'Alinamin F', 'bisaTerbaca' => 'atrial fibrilasi'],
            ['kode' => 'Ind', 'arti' => 'Induksi'],
            ['kode' => 'Ma/mi', 'arti' => ''],
            ['kode' => 'Dr', 'arti' => 'Darah Rutin'],
            ['kode' => 'Px', 'arti' => '', 'tulis' => 'tulis lengkap', 'bisaTerbaca' => 'pasien, pemeriksaan, prognosis'],
            ['kode' => 'Ka/Ki', 'arti' => '', 'tulis' => 'kanan / kiri / residu', 'bisaTerbaca' => 'kanan atau residu'],
            ['kode' => 'TP', 'arti' => 'Tali Pusat'],
            ['kode' => 'HB', 'arti' => 'Head Box', 'tulis' => 'head box', 'bisaTerbaca' => 'hemoglobin'],
            ['kode' => 'Obs', 'arti' => 'Observasi', 'tulis' => 'observasi', 'bisaTerbaca' => 'observasi atau obstetri'],
            ['kode' => 'MS', 'arti' => 'morfin sulfat', 'doNotUse' => true, 'tulis' => 'morfin sulfat', 'bisaTerbaca' => 'magnesium sulfat'],
            ['kode' => 'NK', 'arti' => ''],
            ['kode' => 'Pac', 'arti' => ''],
            ['kode' => 'MPS', 'arti' => ''],
            ['kode' => 'SF', 'arti' => 'susu formula'],
            ['kode' => 'T.a.a/t.a.k', 'arti' => ''],
            ['kode' => 'PCT', 'arti' => '', 'tulis' => 'paracetamol', 'bisaTerbaca' => 'paracetamol atau procalcitonin'],
            ['kode' => 'FT', 'arti' => 'Foto Terapi'],
            ['kode' => 'a/i', 'arti' => ''],
            ['kode' => 'V', 'arti' => ''],
            ['kode' => 'ASIL', 'arti' => ''],
            ['kode' => 'T9', 'arti' => ''],
            ['kode' => 'Cefo', 'arti' => '', 'tulis' => 'cefotaxime', 'bisaTerbaca' => 'sefalosporin yang mana'],
            ['kode' => 'PB,P/B', 'arti' => 'Pasien Baru'],
            ['kode' => 'R', 'arti' => 'Right/Kanan maupun Residu', 'tulis' => 'kanan / kiri / residu', 'bisaTerbaca' => 'kanan atau residu'],
            ['kode' => 'Ceftri', 'arti' => '', 'tulis' => 'ceftriaxone', 'bisaTerbaca' => 'sefalosporin yang mana'],
            ['kode' => 'Inc', 'arti' => ''],
            ['kode' => 'Lanj', 'arti' => ''],
            ['kode' => 'Taxe TF', 'arti' => 'Bisa diartikan dua diagnose tifoid fever atau trigger finger', 'tulis' => 'tulis diagnosisnya', 'bisaTerbaca' => 'tifoid fever atau trigger finger'],
            ['kode' => 'RG', 'arti' => 'Rawat Gabung'],
            ['kode' => 'Spt', 'arti' => '', 'tulis' => 'Spt B / spontan', 'bisaTerbaca' => 'arti ganda'],
            ['kode' => 'QD / qd', 'arti' => 'sekali sehari', 'doNotUse' => true, 'tulis' => 'sekali sehari', 'bisaTerbaca' => 'qid (4 kali sehari)'],
            ['kode' => 'QOD / qod', 'arti' => 'selang sehari', 'doNotUse' => true, 'tulis' => 'selang sehari', 'bisaTerbaca' => 'qd atau qid'],
            ['kode' => 'MSO4', 'arti' => 'morfin sulfat', 'doNotUse' => true, 'tulis' => 'morfin sulfat', 'bisaTerbaca' => 'magnesium sulfat'],
            ['kode' => 'MgSO4', 'arti' => 'magnesium sulfat', 'doNotUse' => true, 'tulis' => 'magnesium sulfat', 'bisaTerbaca' => 'morfin sulfat'],
            ['kode' => 'cc', 'arti' => 'sentimeter kubik', 'tulis' => 'mL', 'bisaTerbaca' => 'u (unit)'],
            ['kode' => 'µg', 'arti' => 'mikrogram', 'tulis' => 'mcg', 'bisaTerbaca' => 'mg'],
            ['kode' => 'SC / SQ / sub q', 'arti' => 'subkutan', 'tulis' => 'subkutan', 'bisaTerbaca' => 'SL (sublingual)'],
            ['kode' => 'OD', 'arti' => 'sekali sehari', 'tulis' => 'sekali sehari', 'bisaTerbaca' => 'mata kanan'],
            ['kode' => 'AD / AS / AU', 'arti' => 'telinga', 'tulis' => 'telinga kanan / kiri / keduanya', 'bisaTerbaca' => 'mata (OD/OS/OU)'],
            ['kode' => 'IN', 'arti' => 'intranasal', 'tulis' => 'intranasal', 'bisaTerbaca' => 'IM atau IV'],
            ['kode' => 'IJ', 'arti' => 'injeksi', 'tulis' => 'injeksi', 'bisaTerbaca' => 'IM atau IV'],
            ['kode' => 'D/C', 'arti' => 'pulang atau hentikan', 'tulis' => 'pulang / hentikan', 'bisaTerbaca' => 'pulang atau hentikan'],
            ['kode' => 'TIW', 'arti' => 'tiga kali seminggu', 'tulis' => 'tiga kali seminggu', 'bisaTerbaca' => '3 kali sehari'],
            ['kode' => 'UD', 'arti' => 'sesuai petunjuk'],
        ];
    }
}
