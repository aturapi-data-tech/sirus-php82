<?php

namespace App\Console\Commands;

use App\Support\AutomaticStopOrder\AutomaticStopOrderDefault;
use App\Support\AutomaticStopOrder\AutomaticStopOrderMaster;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Isi awal master Automatic Stop Order (RSMST_STOP_ORDER_GOLONGANS) dari AutomaticStopOrderDefault.
 *
 * Jalankan SETELAH docs/ddl-automatic-stop-order.sql. Tanpa opsi hanya mengisi bila tabel golongan
 * masih kosong. --force mengosongkan golongan DAN pemetaan obat (FK) lalu mengisi
 * ulang golongan saja — pemetaan obat tidak pernah di-seed.
 */
class AutomaticStopOrderSeed extends Command
{
    protected $signature = 'automatic-stop-order:seed
        {--force : Kosongkan golongan + pemetaan obat lalu isi ulang golongan dari bawaan}
        {--dry-run : Tampilkan ringkasan isi bawaan, jangan tulis apa pun}';

    protected $description = 'Isi awal master Automatic Stop Order (golongan obat + batas hari stop order) dari App\Support\AutomaticStopOrder\AutomaticStopOrderDefault';

    public function handle(): int
    {
        $daftarGolongan = AutomaticStopOrderDefault::golongan();

        $this->info('Isi bawaan: ' . \count($daftarGolongan) . ' golongan.');
        foreach ($daftarGolongan as $golongan) {
            $batasMinimal = $golongan['batas_minimal_hari'] === null ? '   ' : \sprintf('%2d-', $golongan['batas_minimal_hari']);
            $this->line(\sprintf('  %s%2d hari  %-22s %s', $batasMinimal, $golongan['batas_hari'], $golongan['golongan_kode'], $golongan['golongan_nama']));
        }

        if ($this->option('dry-run')) {
            return self::SUCCESS;
        }

        $sudahAda = DB::table('rsmst_stop_order_golongans')->count();
        $jumlahProduk = DB::table('rsmst_stop_order_products')->count();

        if ($sudahAda > 0 && !$this->option('force')) {
            $this->warn("Tabel golongan Automatic Stop Order sudah berisi {$sudahAda} baris ({$jumlahProduk} pemetaan obat). Tidak ada yang ditulis.");
            $this->line('Pakai --force untuk mengosongkan dan mengisi ulang (pemetaan obat ikut terhapus).');

            return self::FAILURE;
        }

        DB::transaction(function () use ($daftarGolongan, $sudahAda) {
            if ($sudahAda > 0) {
                DB::table('rsmst_stop_order_products')->delete();
                DB::table('rsmst_stop_order_golongans')->delete();
            }

            foreach ($daftarGolongan as $golongan) {
                DB::table('rsmst_stop_order_golongans')->insert([
                    'golongan_id' => AutomaticStopOrderMaster::idBaru('rsmst_stop_order_golongans', 'golongan_id'),
                    ...$golongan,
                ]);
            }
        });

        AutomaticStopOrderMaster::flush();
        $this->info('Master Automatic Stop Order terisi. Cache dibersihkan.');

        return self::SUCCESS;
    }
}
