<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class ImportRjhdrsCsv extends Command
{
    protected $signature = 'import:rjhdrs-csv
        {file : Path absolut ke file CSV}
        {--connection=oracle : Nama koneksi DB tujuan}
        {--since= : Filter RJ_DATE >= tanggal (format YYYY-MM-DD, default: 1 hari tgl bulan ini)}
        {--chunk=50 : Jumlah baris per commit}
        {--dry-run : Hanya hitung, tidak insert}';

    protected $description = 'Import CSV RSTXN_RJHDRS, skip baris yang gagal (unique/integrity/parse)';

    private const TABLE = 'rstxn_rjhdrs';
    private const DATE_COLS = [
        'RJ_DATE',
        'PAY_DATE',
        'WAKTU_MASUK_PELAYANAN',
        'WAKTU_MASUK_APT',
        'WAKTU_SELESAI_PELAYANAN',
        'WAKTU_MASUK_POLI',
    ];

    public function handle(): int
    {
        $file = $this->argument('file');
        if (!is_readable($file)) {
            $this->error("File tidak ditemukan / tidak bisa dibaca: {$file}");
            return self::FAILURE;
        }

        $since = $this->option('since') ?: date('Y-m-01');
        $sinceTs = strtotime($since . ' 00:00:00');
        if ($sinceTs === false) {
            $this->error("Format --since tidak valid: {$since}");
            return self::FAILURE;
        }

        $conn = DB::connection($this->option('connection'));
        $chunk = max(1, (int) $this->option('chunk'));
        $dryRun = (bool) $this->option('dry-run');

        $this->info("File      : {$file}");
        $this->info("Koneksi   : {$this->option('connection')}");
        $this->info("Since     : {$since}");
        $this->info("Chunk     : {$chunk}");
        $this->info("Dry-run   : " . ($dryRun ? 'ya' : 'tidak'));
        $this->newLine();

        $fh = fopen($file, 'r');
        if ($fh === false) {
            $this->error("Gagal membuka file.");
            return self::FAILURE;
        }

        $header = fgetcsv($fh);
        if (!$header) {
            $this->error("File kosong atau header tidak terbaca.");
            fclose($fh);
            return self::FAILURE;
        }
        $header = array_map(fn($h) => strtoupper(trim($h)), $header);

        $rjDateIdx = array_search('RJ_DATE', $header, true);
        if ($rjDateIdx === false) {
            $this->error("Kolom RJ_DATE tidak ada di header CSV.");
            fclose($fh);
            return self::FAILURE;
        }

        $badPath = $file . '.bad';
        $bad = fopen($badPath, 'w');
        fputcsv($bad, array_merge($header, ['__error__']));

        $total = $read = $filteredOut = $inserted = $skipped = 0;
        $buffer = [];

        $bar = $this->output->createProgressBar();
        $bar->setFormat(' %current% [%bar%] %elapsed:6s% | ok:%ok% skip:%skip% filter:%filter%');
        $bar->setMessage('0', 'ok');
        $bar->setMessage('0', 'skip');
        $bar->setMessage('0', 'filter');
        $bar->start();

        while (($row = fgetcsv($fh)) !== false) {
            $read++;
            if (count($row) !== count($header)) {
                fputcsv($bad, array_merge($row, ['kolom tidak match header']));
                $skipped++;
                $this->advanceBar($bar, $inserted, $skipped, $filteredOut);
                continue;
            }

            $assoc = array_combine($header, $row);

            $rjDateRaw = $assoc['RJ_DATE'] ?? null;
            $rjDateTs = $rjDateRaw ? strtotime($rjDateRaw) : false;
            if ($rjDateTs === false || $rjDateTs < $sinceTs) {
                $filteredOut++;
                $this->advanceBar($bar, $inserted, $skipped, $filteredOut);
                continue;
            }

            foreach (self::DATE_COLS as $col) {
                if (isset($assoc[$col]) && $assoc[$col] !== '') {
                    $ts = strtotime($assoc[$col]);
                    $assoc[$col] = $ts !== false ? date('Y-m-d H:i:s', $ts) : null;
                } else {
                    $assoc[$col] = null;
                }
            }

            foreach ($assoc as $k => $v) {
                if ($v === '') {
                    $assoc[$k] = null;
                }
            }

            $buffer[] = array_change_key_case($assoc, CASE_LOWER);

            if (count($buffer) >= $chunk) {
                [$ok, $fail] = $this->flushBuffer($conn, $buffer, $bad, $dryRun);
                $inserted += $ok;
                $skipped += $fail;
                $buffer = [];
                $this->advanceBar($bar, $inserted, $skipped, $filteredOut);
            }
        }

        if (!empty($buffer)) {
            [$ok, $fail] = $this->flushBuffer($conn, $buffer, $bad, $dryRun);
            $inserted += $ok;
            $skipped += $fail;
        }

        $total = $read;
        $bar->finish();
        fclose($fh);
        fclose($bad);
        $this->newLine(2);

        $this->info("Total dibaca   : {$total}");
        $this->info("Filter (skip)  : {$filteredOut}  (RJ_DATE < {$since})");
        $this->info("Berhasil insert: {$inserted}");
        $this->info("Gagal / skip   : {$skipped}");
        if ($skipped > 0) {
            $this->line("Detail error ada di: {$badPath}");
        } else {
            @unlink($badPath);
        }

        return self::SUCCESS;
    }

    private function flushBuffer($conn, array $rows, $badFh, bool $dryRun): array
    {
        $ok = 0;
        $fail = 0;

        foreach ($rows as $row) {
            if ($dryRun) {
                $ok++;
                continue;
            }
            try {
                $conn->table(self::TABLE)->insert($row);
                $ok++;
            } catch (Throwable $e) {
                $fail++;
                fputcsv($badFh, array_merge(array_values($row), [
                    substr($e->getMessage(), 0, 300),
                ]));
            }
        }
        return [$ok, $fail];
    }

    private function advanceBar($bar, int $ok, int $skip, int $filter): void
    {
        $bar->setMessage((string) $ok, 'ok');
        $bar->setMessage((string) $skip, 'skip');
        $bar->setMessage((string) $filter, 'filter');
        $bar->advance();
    }
}
