<?php

use Livewire\Component;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

new class extends Component {

    public string $userMessage = '';
    public array $messages = [];
    public bool $isLoading = false;
    public string $sessionId = '';

    public function mount(): void
    {
        $this->sessionId = 'sirus-chat-' . auth()->id() . '-' . now()->format('Ymd');
    }

    public function sendMessage(): void
    {
        $text = trim($this->userMessage);
        if ($text === '' || $this->isLoading) {
            return;
        }

        $this->messages[] = ['role' => 'user', 'content' => $text];
        $this->userMessage = '';
        $this->isLoading = true;

        try {
            $response = $this->processQuestion($text);
            $this->messages[] = ['role' => 'assistant', 'content' => $response];
        } catch (\Throwable $e) {
            Log::error('AI Chat error', ['error' => $e->getMessage()]);
            $this->messages[] = [
                'role' => 'assistant',
                'content' => 'Maaf, terjadi kesalahan saat memproses pertanyaan. Coba lagi nanti.',
            ];
        }

        $this->isLoading = false;
        $this->dispatch('message-sent');
    }

    public function clearChat(): void
    {
        $this->messages = [];
        $this->sessionId = 'sirus-chat-' . auth()->id() . '-' . now()->format('YmdHis');
    }

    private function processQuestion(string $question): string
    {
        $doctorContext = $this->resolveDoctor($question);

        $enrichedQuestion = $this->enrichWithContext($question);
        if ($doctorContext) {
            $enrichedQuestion .= "\n\n[DATA DOKTER TERVALIDASI: {$doctorContext}]";
        }

        $sqlResponse = $this->askAI($this->getSqlPrompt(), $enrichedQuestion);

        $sql = $this->extractSql($sqlResponse);

        if ($sql === null) {
            return 'Maaf, pertanyaan ini di luar cakupan data yang tersedia. Saya bisa membantu dengan data kunjungan pasien, pendapatan, obat, gaji dokter, laboratorium, dan data operasional RS lainnya.';
        }

        $queryResult = $this->executeSafeQuery($sql);
        $resultData = json_decode($queryResult, true);

        if (isset($resultData['error'])) {
            $response = $this->askAI(
                $this->getFormatPrompt(),
                "Pertanyaan user: {$question}\n\nMaaf, data tidak dapat diambil saat ini. Berikan jawaban yang membantu dalam bahasa Indonesia. Jangan tampilkan detail teknis."
            );
            return $this->stripSqlFromResponse($response);
        }

        $response = $this->askAI(
            $this->getFormatPrompt(),
            "Pertanyaan user: {$question}\n\nHasil data ({$resultData['row_count']} baris):\n" . json_encode($resultData['data'] ?? [], JSON_UNESCAPED_UNICODE) . "\n\nFormat hasilnya dalam bahasa Indonesia dengan tabel markdown yang rapi. Format angka pakai titik ribuan."
        );
        return $this->stripSqlFromResponse($response);
    }

    private function resolveDoctor(string $question): ?string
    {
        $keywords = ['dokter', 'dr', 'dr.', 'doctor'];
        $lower = mb_strtolower($question);
        $foundName = null;

        foreach ($keywords as $kw) {
            if (($pos = mb_strpos($lower, $kw)) !== false) {
                $after = trim(mb_substr($question, $pos + mb_strlen($kw)));
                $after = ltrim($after, '. ');
                $words = preg_split('/\s+/', $after);
                $nameParts = [];
                foreach ($words as $w) {
                    if (preg_match('/^[a-zA-Z\x{00C0}-\x{024F}\']+$/u', $w)) {
                        $nameParts[] = $w;
                        if (count($nameParts) >= 3) break;
                    } else {
                        break;
                    }
                }
                if (!empty($nameParts)) {
                    $foundName = implode(' ', $nameParts);
                    break;
                }
            }
        }

        if (!$foundName) {
            return null;
        }

        try {
            $searchName = '%' . mb_strtoupper($foundName) . '%';
            $doctors = DB::connection('oracle_ai')
                ->select("SELECT dr_id, dr_name FROM rs.rsmst_doctors WHERE UPPER(dr_name) LIKE ? AND ROWNUM <= 5", [$searchName]);

            if (empty($doctors)) {
                return "Tidak ditemukan dokter dengan nama '{$foundName}'. Mungkin nama lengkapnya berbeda.";
            }

            $list = array_map(fn($d) => "dr_id={$d->dr_id}, dr_name={$d->dr_name}", $doctors);
            return "Ditemukan " . count($doctors) . " dokter cocok: " . implode(' | ', $list) . ". Gunakan dr_id untuk filter query.";
        } catch (\Throwable $e) {
            Log::warning('Doctor resolve error', ['name' => $foundName, 'error' => $e->getMessage()]);
            return null;
        }
    }

    private function enrichWithContext(string $question): string
    {
        if (mb_strlen($question) > 30 || count($this->messages) < 2) {
            return $question;
        }

        $context = [];
        $recent = array_slice($this->messages, -6);
        foreach ($recent as $msg) {
            $role = $msg['role'] === 'user' ? 'User' : 'Asisten';
            $snippet = mb_substr($msg['content'], 0, 200);
            $context[] = "{$role}: {$snippet}";
        }

        return "Konteks percakapan sebelumnya:\n" . implode("\n", $context) . "\n\nPertanyaan terbaru: {$question}";
    }

    private function stripSqlFromResponse(string $response): string
    {
        $response = preg_replace('/```sql\s*.*?\s*```/s', '', $response);
        $response = preg_replace('/```\s*SELECT\b.*?\s*```/si', '', $response);
        $response = preg_replace('/\bSELECT\b\s+.+?\bFROM\b\s+\S+.*?(?=\n\n|\z)/si', '', $response);
        $response = preg_replace('/\b(?:FROM|WHERE|GROUP BY|ORDER BY|HAVING|JOIN|LEFT JOIN|INNER JOIN)\b\s+rs\.\w+.*/mi', '', $response);
        $response = preg_replace('/\brs\.\w+\b/', '', $response);
        $response = preg_replace('/\b(?:COUNT|SUM|AVG|MAX|MIN|NVL|TO_DATE|TO_CHAR|TRUNC|DISTINCT|ROWNUM)\s*\(/i', '', $response);
        return trim(preg_replace('/\n{3,}/', "\n\n", $response));
    }

    private function extractSql(string $response): ?string
    {
        if (preg_match('/```sql\s*(.*?)\s*```/s', $response, $m)) {
            return trim($m[1]);
        }
        if (preg_match('/```\s*(SELECT\b.*?)\s*```/si', $response, $m)) {
            return trim($m[1]);
        }
        if (preg_match('/^\s*(SELECT\b.+)/si', $response, $m)) {
            return trim(rtrim($m[1], '`'));
        }
        return null;
    }

    private function askAI(string $systemPrompt, string $userMessage): string
    {
        $apiUrl = config('services.openclaw.url', 'http://127.0.0.1:18789');
        $apiKey = config('services.openclaw.key', '');

        $response = Http::timeout(120)
            ->withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
            ])
            ->post($apiUrl . '/v1/chat/completions', [
                'model' => 'openclaw/default',
                'user' => $this->sessionId,
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userMessage],
                ],
            ]);

        if ($response->failed()) {
            throw new \RuntimeException('API error: ' . $response->status());
        }

        $data = $response->json();
        return $data['choices'][0]['message']['content'] ?? 'Tidak ada jawaban.';
    }

    private function executeSafeQuery(string $sql): string
    {
        $sql = trim($sql);

        if ($sql === '') {
            return json_encode(['error' => 'Query kosong']);
        }

        $upperSql = strtoupper($sql);

        $forbidden = ['INSERT', 'UPDATE', 'DELETE', 'DROP', 'ALTER', 'TRUNCATE', 'MERGE', 'CREATE', 'GRANT', 'REVOKE', 'EXEC', 'EXECUTE', 'CALL'];
        foreach ($forbidden as $keyword) {
            if (preg_match('/\b' . $keyword . '\b/', $upperSql)) {
                return json_encode(['error' => 'Query ditolak: hanya SELECT yang diperbolehkan']);
            }
        }

        if (!preg_match('/^\s*SELECT\b/i', $sql)) {
            return json_encode(['error' => 'Query harus dimulai dengan SELECT']);
        }

        $sensitiveTable = ['USERS', 'PERMISSIONS', 'ROLES', 'MODEL_HAS_ROLES', 'ROLE_HAS_PERMISSIONS', 'PERSONAL_ACCESS_TOKENS'];
        foreach ($sensitiveTable as $table) {
            if (preg_match('/\b' . $table . '\b/i', $sql)) {
                return json_encode(['error' => 'Akses ke tabel ' . $table . ' tidak diizinkan']);
            }
        }

        if (!preg_match('/ROWNUM\s*<=/i', $sql)) {
            $sql = 'SELECT * FROM (' . rtrim($sql, "; \t\n\r") . ') WHERE ROWNUM <= 100';
        }

        try {
            $results = DB::connection('oracle_ai')->select($sql);
            $rows = array_map(fn($row) => (array) $row, $results);

            if (empty($rows)) {
                return json_encode(['data' => [], 'message' => 'Tidak ada data ditemukan', 'row_count' => 0]);
            }

            return json_encode([
                'data' => array_slice($rows, 0, 100),
                'row_count' => count($rows),
                'columns' => array_keys($rows[0]),
            ], JSON_UNESCAPED_UNICODE);

        } catch (\Throwable $e) {
            Log::warning('AI query error', ['sql' => $sql, 'error' => $e->getMessage()]);
            return json_encode(['error' => 'Query error: ' . $e->getMessage()]);
        }
    }

    private function getSqlPrompt(): string
    {
        return <<<'PROMPT'
Kamu adalah SQL generator untuk database Oracle 10g rumah sakit SIRUS (RSI Madinah).

TUGAS: Generate SATU query Oracle SELECT berdasarkan pertanyaan user.

ATURAN OUTPUT KETAT:
- Output HANYA blok ```sql ... ```, TIDAK BOLEH ada teks lain
- JANGAN pernah bertanya balik, minta klarifikasi, atau menulis penjelasan
- Kalau ragu, tetap generate query terbaik
- Kalau benar-benar tidak bisa: ```sql
SELECT 'Data tidak tersedia' AS info FROM DUAL
```

ATURAN SQL:
- HANYA SELECT
- Oracle 10g — JANGAN pakai FETCH FIRST, pakai ROWNUM: SELECT * FROM (...) WHERE ROWNUM <= 100
- NVL() untuk NULL, TO_DATE('2026-01-01','YYYY-MM-DD') untuk tanggal
- TO_CHAR(tanggal, 'YYYY-MM') untuk filter bulan/tahun
- Prefix SEMUA tabel dengan rs.: FROM rs.rstxn_rjhdrs

VALIDASI DOKTER:
- Kalau ada [DATA DOKTER TERVALIDASI: ...] di pertanyaan, gunakan dr_id dari data itu untuk filter query (WHERE dr_id = <id>)
- JANGAN pakai LIKE dr_name kalau sudah ada dr_id yang tervalidasi
- Kalau ada beberapa dokter cocok, pilih yang paling relevan berdasarkan nama

=== TABEL & KOLOM (prefix rs.) ===

MASTER:
- rsmst_pasiens: reg_no PK, reg_name, sex, birth_date, address, phone, nik
- rsmst_doctors: dr_id PK, dr_name, poli_id, dr_phone, dr_nik, dr_status
- rsmst_polis: poli_id PK, poli_desc, poli_bpjs, spesialis_status
- rsmst_klaimtypes: klaim_id PK, klaim_desc, klaim_status (nilai: 'BPJS' atau non-BPJS)
- rsmst_accdocs: accdoc_id PK, accdoc_desc (master tindakan dokter)
- rsmst_actemps: acte_id PK, acte_desc (master jasa karyawan)
- rsmst_actparamedics: actpm_id PK, actpm_desc (master tindakan paramedis)
- rsmst_bangsals: bangsal_id PK, bangsal_name
- rsmst_rooms: room_id PK, room_name, room_price
- rsmst_beds: bed_no PK, bed_desc, bed_bangsal, bed_aktif
- rsmst_identitases: identitas RS (1 row)

RAWAT JALAN (RJ):
- rstxn_rjhdrs: rj_no PK, rj_date, reg_no, dr_id, poli_id, klaim_id, rj_status, no_antrian, vno_sep, datadaftarpolirj_json
  JOIN: rsmst_pasiens ON reg_no, rsmst_polis ON poli_id, rsmst_doctors ON dr_id, rsmst_klaimtypes ON klaim_id
- rstxn_rjaccdocs: rj_no FK, dr_id — jasa dokter RJ
- rstxn_rjactemps: rj_no FK, acte_id, acte_price — jasa karyawan RJ
- rstxn_rjactparams: rj_no FK, actpm_id — jasa paramedis RJ
- rstxn_rjdtls, rstxn_rjobats, rstxn_rjlabs, rstxn_rjrads, rstxn_rjothers, rstxn_rjcashins, rstxn_rjdiagrefs

UGD (PK = rj_no, tanggal = rj_date, status = rj_status — SAMA dengan RJ!):
- rstxn_ugdhdrs: rj_no PK, rj_date, reg_no, dr_id, klaim_id, rj_status, no_antrian, vno_sep, datadaftarugd_json
  JOIN: rsmst_pasiens ON reg_no, rsmst_doctors ON dr_id, rsmst_klaimtypes ON klaim_id
- rstxn_ugdaccdocs, rstxn_ugdactemps, rstxn_ugdactparams
- rstxn_ugddtls, rstxn_ugdobats, rstxn_ugdlabs, rstxn_ugdrads, rstxn_ugdothers, rstxn_ugdcashins

RAWAT INAP (RI):
- rstxn_rihdrs: rihdr_no PK, reg_no, dr_id, entry_date, exit_date, ri_status, klaim_id, vno_sep, bed_no
  JOIN: rsmst_pasiens ON reg_no, rsmst_doctors ON dr_id, rsmst_klaimtypes ON klaim_id
- rstxn_riactdocs: rihdr_no FK — jasa dokter RI
- rstxn_riactparams: rihdr_no FK, actpm_id — jasa paramedis RI
- rstxn_rivisits: rihdr_no FK — visit dokter RI
- rstxn_rikonsuls: rihdr_no FK — konsul dokter RI
- rstxn_ridtls, rstxn_riobats, rstxn_rilabs, rstxn_riradiologs, rstxn_riothers, rstxn_ripaymentdtls

KAMAR OPERASI (OK):
- rstxn_oks: ok_reg PK, ok_date, ok_status, dr_id, rihdr_no, ref_no, status_rjri (RJ/UGD/RI default RI)
  JOIN: rsmst_doctors ON dr_id
- rstxn_okacts: ok_reg FK, accdoc_id — tindakan operasi
  JOIN: rsmst_accdocs ON accdoc_id

GAJI DOKTER:
- rstxn_gajidoctorhdrs: gajidoctor_no PK, dr_id, bulan_jasa, tahun_jasa, jasa_total, total_gaji, gaji_diterima, gaji_status, tanggal_bayar, skema_gaji_pokok, potongan_rs_persen, potongan_rs, pph21, npwp_status
  JOIN: rsmst_doctors ON dr_id
- rstxn_gajidoctordtls: gajidoctor_no FK — detail gaji

KELENGKAPAN EMR (Rekam Medis Elektronik):
- rsview_ermstatus: txn_date, txn_no, reg_no, reg_name, erm_status, layanan_status (RJ/UGD/RI), poli, kd_dr_bpjs, nokartu_bpjs
  erm_status: A=Belum Selesai/Proses Dilayani, L=Selesai (EMR lengkap)
  Untuk kelengkapan EMR per dokter: JOIN rsview_ermstatus e ON e.txn_no = h.rj_no (RJ/UGD) atau e.txn_no = h.rihdr_no (RI)
  Bisa juga langsung pakai kolom erm_status di tabel header: rstxn_rjhdrs.erm_status, rstxn_ugdhdrs.erm_status, rstxn_rihdrs.erm_status
  Contoh: persentase kelengkapan = COUNT(CASE WHEN erm_status='L' THEN 1 END) / COUNT(*) * 100

VIEW (data gabungan siap pakai):
- rsview_rjkasir — kasir RJ lengkap
- rsview_ugdkasir — kasir UGD lengkap
- rsview_rihdrs — RI lengkap
- rsview_newdocsalaries: dr_id, dr_name, desc_doc, group_doc, doc_nominal, klaim_id, rj_date — pendapatan dokter
- rsview_ermstatus — status EMR gabungan RJ/UGD/RI (untuk laporan kelengkapan rekam medis)

LAB:
- lbtxn_checkuphdrs: checkup_no PK, ref_no, checkup_date, status_rjri (RJ/RI/UGD)
- lbtxn_checkupdtls: checkup_no FK, item_id, result

FARMASI/GUDANG:
- immst_products: product_id PK, product_name, satuan, harga, product_type
- imtxn_receivehdrs/receivedtls — penerimaan barang
- imtxn_slshdrs/slsdtls — pengeluaran obat
- imtxn_trfhdrs/trfdtls — transfer gudang

KLINIK:
- rstxn_rjhdrks — kunjungan klinik

=== STATUS VALUES (PENTING!) ===

rj_status (rstxn_rjhdrs & rstxn_ugdhdrs):
  'A' = Antrian, 'L' = Selesai, 'F' = Batal, 'I' = Transfer
  Laporan pendapatan/jasa: filter rj_status = 'L'
  Laporan kunjungan: rj_status NOT IN ('A','F') atau rj_status = 'L'

ri_status (rstxn_rihdrs):
  'P' = Pulang, 'F' = Batal, 'I' = Masih Dirawat
  Laporan keuangan RI: filter ri_status = 'P', pakai exit_date sebagai periode

gaji_status (rstxn_gajidoctorhdrs):
  'D' = Draft, 'F' = Final

ok_status (rstxn_oks):
  'F' = Batal (exclude: COALESCE(ok_status,'A') <> 'F')

erm_status (rstxn_rjhdrs, rstxn_ugdhdrs, rstxn_rihdrs, rsview_ermstatus):
  'A' = Belum Selesai/Proses Dilayani (EMR belum lengkap)
  'L' = Selesai (EMR sudah lengkap)
  Persentase kelengkapan EMR = jumlah erm_status='L' / total * 100
  Untuk "dokter mana yang EMR-nya tidak lengkap": filter erm_status <> 'L' atau erm_status = 'A'

status_rjri (rstxn_oks):
  'RJ', 'UGD', 'RI' (default RI: NVL(status_rjri,'RI'))

=== KLAIM/PENJAMIN ===

Filter BPJS: k.klaim_status = 'BPJS' (JOIN rsmst_klaimtypes k ON k.klaim_id = h.klaim_id)
Filter UMUM: k.klaim_status != 'BPJS'
Klaim ID JM juga termasuk BPJS meskipun klaim_status-nya mungkin bukan 'BPJS'

Mapping klaim_id:
  BPJS: PB, JM, HI
  Asuransi Pemerintah: JR, JS, TP
  Asuransi Swasta: JML
  Bayar Sendiri: UM, KW, HC, KR
  Lain-lain: DK

PENTING — klaim_id = 'KR' (Kronis):
  KR = kunjungan ambil obat kronis, BUKAN kunjungan pelayanan/pemeriksaan dokter
  Pasien KR tidak ada EMR (hanya ambil obat), jadi:
  - Laporan KUNJUNGAN: WAJIB exclude klaim_id != 'KR'
  - Laporan KELENGKAPAN EMR: WAJIB exclude klaim_id != 'KR'
  - Laporan PENDAPATAN/OBAT: boleh include KR (ada transaksi obat)

=== POLA QUERY UMUM ===

Kunjungan RJ per poli:
SELECT po.poli_desc, COUNT(*) jml FROM rs.rstxn_rjhdrs h JOIN rs.rsmst_polis po ON po.poli_id=h.poli_id WHERE h.rj_date BETWEEN TO_DATE('...') AND TO_DATE('...') AND h.rj_status='L' GROUP BY po.poli_desc ORDER BY jml DESC

Pendapatan dokter via view:
SELECT v.dr_name, k.klaim_status, SUM(v.doc_nominal) total FROM rs.rsview_newdocsalaries v JOIN rs.rsmst_klaimtypes k ON k.klaim_id=v.klaim_id WHERE TO_CHAR(v.rj_date,'MM/YYYY')='...' GROUP BY v.dr_name,k.klaim_status

Gaji dokter:
SELECT d.dr_name, h.jasa_total, h.total_gaji, h.gaji_diterima, h.gaji_status FROM rs.rstxn_gajidoctorhdrs h JOIN rs.rsmst_doctors d ON d.dr_id=h.dr_id WHERE h.tahun_jasa=2026 AND h.bulan_jasa=7

Kasus operasi:
SELECT d.dr_name, a.accdoc_desc, COUNT(*) jml FROM rs.rstxn_oks o JOIN rs.rstxn_okacts t ON t.ok_reg=o.ok_reg LEFT JOIN rs.rsmst_accdocs a ON a.accdoc_id=t.accdoc_id LEFT JOIN rs.rsmst_doctors d ON d.dr_id=o.dr_id WHERE COALESCE(o.ok_status,'A')<>'F' AND o.ok_date>=TO_DATE('...') GROUP BY d.dr_name,a.accdoc_desc
PROMPT;
    }

    private function getFormatPrompt(): string
    {
        return <<<'PROMPT'
Kamu adalah AI Asisten SIRUS untuk evaluasi laporan manajemen RSI Madinah.

FORMAT JAWABAN:
- Bahasa Indonesia
- Data tabular → tabel markdown
- Sertakan periode data yang diquery
- Format angka ribuan pakai titik (contoh: 1.234.567)
- Berikan ringkasan/insight singkat di akhir kalau relevan
- DILARANG KERAS menampilkan SQL, query, SELECT, FROM, WHERE, JOIN, atau sintaks database apapun
- DILARANG KERAS menyebut nama tabel (rs.xxx, rstxn_xxx, rsmst_xxx, rsview_xxx, dll) atau nama kolom teknis
- DILARANG KERAS bertanya balik, minta klarifikasi, atau menyarankan query tambahan
- DILARANG KERAS berkata "saya perlu query tambahan" atau "mari kita tarik data"
- Langsung sajikan data yang tersedia dan analisisnya
- Kalau data kosong atau error, jawab singkat "Data tidak ditemukan untuk periode ini"
- Kalau data terpotong, sampaikan "Data yang ditampilkan adalah sampel" tanpa menyebut ROWNUM atau limit teknis
PROMPT;
    }
};
?>

<div x-data="{
        scrollToBottom() {
            this.$nextTick(() => {
                const el = this.$refs.chatMessages;
                if (el) el.scrollTop = el.scrollHeight;
            });
        }
    }"
    x-init="scrollToBottom()"
    x-on:message-sent.window="scrollToBottom()">

    <x-page-title
        title="AI Asisten Laporan"
        subtitle="Tanya jawab data & evaluasi laporan manajemen RS (read-only)" />

    <div class="w-full min-h-[calc(100vh-5rem)] bg-canvas dark:bg-gray-800">
        <div class="max-w-4xl px-4 py-6 mx-auto sm:px-6">

            {{-- Chat Container --}}
            <div class="flex flex-col overflow-hidden bg-white border border-hairline rounded-xl dark:bg-gray-900 dark:border-gray-700"
                 style="height: calc(100vh - 12rem);">

                {{-- Header --}}
                <div class="flex items-center justify-between px-4 py-3 border-b border-hairline dark:border-gray-700">
                    <div class="flex items-center gap-2">
                        <span class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-brand-green/10 text-brand-green dark:bg-brand-lime/20 dark:text-brand-lime">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
                            </svg>
                        </span>
                        <div>
                            <h3 class="text-sm font-semibold text-ink dark:text-gray-100">AI Asisten</h3>
                            <p class="text-xs text-muted dark:text-gray-400">Evaluasi laporan manajemen</p>
                        </div>
                    </div>
                    @if (count($messages) > 0)
                        <x-secondary-button wire:click="clearChat" class="!px-3 !py-1.5 !text-sm">
                            Bersihkan
                        </x-secondary-button>
                    @endif
                </div>

                {{-- Messages --}}
                <div class="flex-1 px-4 py-4 space-y-4 overflow-y-auto" x-ref="chatMessages"
                    wire:poll.keep-alive="false">

                    @if (count($messages) === 0)
                        {{-- Empty state --}}
                        <div class="flex flex-col items-center justify-center h-full text-center">
                            <div class="inline-flex items-center justify-center w-16 h-16 mb-4 rounded-full bg-brand-green/10 dark:bg-brand-lime/20">
                                <svg class="w-8 h-8 text-brand-green dark:text-brand-lime" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09zM18.259 8.715L18 9.75l-.259-1.035a3.375 3.375 0 00-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 002.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 002.455 2.456L21.75 6l-1.036.259a3.375 3.375 0 00-2.455 2.456zM16.894 20.567L16.5 21.75l-.394-1.183a2.25 2.25 0 00-1.423-1.423L13.5 18.75l1.183-.394a2.25 2.25 0 001.423-1.423l.394-1.183.394 1.183a2.25 2.25 0 001.423 1.423l1.183.394-1.183.394a2.25 2.25 0 00-1.423 1.423z" />
                                </svg>
                            </div>
                            <h3 class="text-base font-semibold text-ink dark:text-gray-100">Halo! Saya AI Asisten SIRUS</h3>
                            <p class="max-w-md mt-2 text-sm text-muted dark:text-gray-400">
                                Tanyakan apa saja tentang data rumah sakit. Saya bisa membantu analisis kunjungan,
                                pendapatan, obat, gaji dokter, dan laporan lainnya.
                            </p>
                            <div class="grid grid-cols-1 gap-2 mt-6 sm:grid-cols-2">
                                <button wire:click="$set('userMessage', 'Berapa total kunjungan RJ bulan ini per poli?')"
                                    class="px-4 py-2 text-xs text-left transition-colors border border-hairline rounded-lg hover:bg-brand-green/5 dark:border-gray-600 dark:hover:bg-brand-lime/10">
                                    <span class="font-medium text-ink dark:text-gray-200">Kunjungan RJ</span>
                                    <span class="block text-muted dark:text-gray-400">Total per poli bulan ini</span>
                                </button>
                                <button wire:click="$set('userMessage', 'Bandingkan pendapatan RJ vs UGD 3 bulan terakhir')"
                                    class="px-4 py-2 text-xs text-left transition-colors border border-hairline rounded-lg hover:bg-brand-green/5 dark:border-gray-600 dark:hover:bg-brand-lime/10">
                                    <span class="font-medium text-ink dark:text-gray-200">Pendapatan</span>
                                    <span class="block text-muted dark:text-gray-400">RJ vs UGD 3 bulan terakhir</span>
                                </button>
                                <button wire:click="$set('userMessage', 'Obat apa yang paling banyak dipakai bulan lalu?')"
                                    class="px-4 py-2 text-xs text-left transition-colors border border-hairline rounded-lg hover:bg-brand-green/5 dark:border-gray-600 dark:hover:bg-brand-lime/10">
                                    <span class="font-medium text-ink dark:text-gray-200">Obat Terlaris</span>
                                    <span class="block text-muted dark:text-gray-400">Top obat bulan lalu</span>
                                </button>
                                <button wire:click="$set('userMessage', 'Ringkasan gaji dokter periode terakhir')"
                                    class="px-4 py-2 text-xs text-left transition-colors border border-hairline rounded-lg hover:bg-brand-green/5 dark:border-gray-600 dark:hover:bg-brand-lime/10">
                                    <span class="font-medium text-ink dark:text-gray-200">Gaji Dokter</span>
                                    <span class="block text-muted dark:text-gray-400">Ringkasan periode terakhir</span>
                                </button>
                            </div>
                        </div>
                    @else
                        @foreach ($messages as $msg)
                            <div class="flex {{ $msg['role'] === 'user' ? 'justify-end' : 'justify-start' }}">
                                <div class="max-w-[80%] {{ $msg['role'] === 'user'
                                    ? 'bg-brand-green text-white rounded-2xl rounded-br-md px-4 py-2.5'
                                    : 'bg-gray-100 text-ink dark:bg-gray-800 dark:text-gray-100 rounded-2xl rounded-bl-md px-4 py-2.5' }}">
                                    @if ($msg['role'] === 'assistant')
                                        <div class="prose prose-sm max-w-none dark:prose-invert
                                            prose-table:border prose-table:border-hairline prose-table:text-sm
                                            prose-th:bg-gray-50 prose-th:px-3 prose-th:py-1.5
                                            prose-td:px-3 prose-td:py-1.5 prose-td:border prose-td:border-hairline
                                            dark:prose-th:bg-gray-700 dark:prose-td:border-gray-600">
                                            {!! \Illuminate\Support\Str::markdown($msg['content']) !!}
                                        </div>
                                    @else
                                        <p class="text-sm whitespace-pre-wrap">{{ $msg['content'] }}</p>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    @endif

                    {{-- Loading indicator --}}
                    <div wire:loading wire:target="sendMessage" class="flex justify-start">
                        <div class="px-4 py-3 bg-gray-100 rounded-2xl rounded-bl-md dark:bg-gray-800">
                            <div class="flex items-center gap-2 text-muted dark:text-gray-400">
                                <x-loading size="sm" />
                                <span class="text-sm">Memproses...</span>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Input --}}
                <div class="px-4 py-3 border-t border-hairline dark:border-gray-700">
                    <form wire:submit="sendMessage" class="flex gap-2">
                        <input type="text"
                            wire:model="userMessage"
                            placeholder="Ketik pertanyaan tentang data RS..."
                            class="flex-1 px-4 py-2.5 text-sm bg-gray-50 border border-hairline rounded-xl
                                focus:outline-none focus:ring-2 focus:ring-brand-lime/40 focus:border-brand-green
                                dark:bg-gray-800 dark:border-gray-600 dark:text-gray-100 dark:placeholder-gray-500"
                            wire:loading.attr="disabled"
                            wire:target="sendMessage"
                            autocomplete="off" />
                        <x-primary-button type="submit"
                            wire:loading.attr="disabled"
                            wire:target="sendMessage"
                            class="!rounded-xl !px-4">
                            <span wire:loading.remove wire:target="sendMessage">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.27 20.876L5.999 12zm0 0h7.5" />
                                </svg>
                            </span>
                            <span wire:loading wire:target="sendMessage">
                                <x-loading size="sm" />
                            </span>
                        </x-primary-button>
                    </form>
                    <p class="mt-2 text-xs text-center text-muted dark:text-gray-500">
                        AI hanya membaca data (read-only). Tidak dapat mengubah atau menghapus data.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>
