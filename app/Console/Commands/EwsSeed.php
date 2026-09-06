<?php

namespace App\Console\Commands;

use App\Support\Ews\EwsDefault;
use App\Support\Ews\EwsMaster;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Isi awal master EWS (RSMST_EWS_PARAMS / RENTANGS / RESPONS) dari EwsDefault.
 *
 * Jalankan SETELAH docs/ddl-ews.sql. Tanpa opsi hanya mengisi bila ketiga tabel
 * masih kosong — supaya ambang yang sudah dikustomisasi RS lewat /master/ews
 * tidak tertimpa diam-diam. --force mengosongkan dulu lalu mengisi ulang.
 */
class EwsSeed extends Command
{
    protected $signature = 'ews:seed
        {--force : Kosongkan ketiga tabel lalu isi ulang dari bawaan}
        {--dry-run : Tampilkan ringkasan isi bawaan, jangan tulis apa pun}';

    protected $description = 'Isi awal master EWS (parameter, rentang skor, respon) dari App\Support\Ews\EwsDefault';

    public function handle(): int
    {
        $params  = EwsDefault::params();
        $respons = EwsDefault::respons();
        $jumlahRentang = \array_sum(\array_map(fn(array $param) => \count($param['rentang']), $params));

        $this->info('Isi bawaan: ' . \count($params) . ' parameter, ' . $jumlahRentang . ' rentang, ' . \count($respons) . ' respon.');
        foreach (EwsDefault::VARIAN as $kode => $label) {
            $jumlahParam  = \count(\array_filter($params, fn(array $param) => $param['varian'] === $kode));
            $jumlahRespon = \count(\array_filter($respons, fn(array $respon) => $respon['varian'] === $kode));
            $this->line(\sprintf('  %-9s %-24s %2d parameter, %d respon', $kode, $label, $jumlahParam, $jumlahRespon));
        }

        if ($this->option('dry-run')) {
            return self::SUCCESS;
        }

        $sudahAda = DB::table('rsmst_ews_params')->count()
            + DB::table('rsmst_ews_rentangs')->count()
            + DB::table('rsmst_ews_respons')->count();

        if ($sudahAda > 0 && !$this->option('force')) {
            $this->warn("Tabel master EWS sudah berisi {$sudahAda} baris. Tidak ada yang ditulis.");
            $this->line('Pakai --force untuk mengosongkan dan mengisi ulang (kustomisasi hilang).');

            return self::FAILURE;
        }

        DB::transaction(function () use ($params, $respons, $sudahAda) {
            if ($sudahAda > 0) {
                DB::table('rsmst_ews_rentangs')->delete();
                DB::table('rsmst_ews_respons')->delete();
                DB::table('rsmst_ews_params')->delete();
            }

            foreach ($params as $param) {
                $paramId = EwsMaster::idBaru('rsmst_ews_params', 'param_id');
                DB::table('rsmst_ews_params')->insert([
                    'param_id'      => $paramId,
                    'varian'        => $param['varian'],
                    'param_kode'    => $param['param_kode'],
                    'param_desc'    => $param['param_desc'],
                    'tipe'          => $param['tipe'],
                    'satuan'        => $param['satuan'],
                    'urutan'        => $param['urutan'],
                    'wajib'         => $param['wajib'],
                    'gantikan_kode' => $param['gantikan_kode'],
                    'active_status' => $param['active_status'],
                ]);

                foreach ($param['rentang'] as $rentang) {
                    $rentangId = EwsMaster::idBaru('rsmst_ews_rentangs', 'rentang_id');
                    DB::table('rsmst_ews_rentangs')->insert([
                        'rentang_id'   => $rentangId,
                        'param_id'     => $paramId,
                        'urutan'       => $rentang['urutan'],
                        'batas_bawah'  => $rentang['batas_bawah'],
                        'batas_atas'   => $rentang['batas_atas'],
                        'pilihan_kode' => $rentang['pilihan_kode'],
                        'pilihan_desc' => $rentang['pilihan_desc'],
                        'syarat'       => $rentang['syarat'],
                        'usia_min_bln' => $rentang['usia_min_bln'],
                        'usia_max_bln' => $rentang['usia_max_bln'],
                        'skor'         => $rentang['skor'],
                    ]);
                }
            }

            foreach ($respons as $respon) {
                $responId = EwsMaster::idBaru('rsmst_ews_respons', 'respon_id');
                DB::table('rsmst_ews_respons')->insert(['respon_id' => $responId, ...$respon]);
            }
        });

        EwsMaster::flush();
        $this->info('Master EWS terisi. Cache dibersihkan.');

        return self::SUCCESS;
    }
}
