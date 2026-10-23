<?php
// Alat bantu sementara: hitung record transaksi Administrasi RI per kunjungan, hasil ditampilkan lewat toast.
// Dipakai melacak kunjungan yang tab Lain-Lain-nya kehabisan memori saat render
// (baris rstxn_riothers berlipat dari paket ber-Qty besar, atau master rsmst_others dobel).
// Hanya SELECT. Trigger dari daftar-ri: dispatch 'daftar-ri.cek-jumlah-record.open' dengan riHdrNo.

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;

new class extends Component {
    /* Tabel yang dibaca tab-tab Administrasi RI, semuanya ber-kolom rihdr_no */
    private const TABEL_ADMINISTRASI_RI = [
        'rstxn_rivisits'      => 'Visit',
        'rstxn_rikonsuls'     => 'Konsul',
        'rstxn_riactparams'   => 'Jasa Medis',
        'rstxn_riactdocs'     => 'Jasa Dokter',
        'rstxn_rilabs'        => 'Laboratorium',
        'rstxn_riradiologs'   => 'Radiologi',
        'rstxn_ritempadmins'  => 'Trf UGD/RJ',
        'rstxn_riothers'      => 'Lain-Lain',
        'rstxn_rioks'         => 'Operasi (OK)',
        'rsmst_trfrooms'      => 'Kamar',
        'rstxn_ribonobats'    => 'Bon Resep',
        'rstxn_riobatrtns'    => 'Return Obat',
        'rstxn_riobats'       => 'Obat Pinjam',
    ];

    #[On('daftar-ri.cek-jumlah-record.open')]
    public function cekJumlahRecord(?int $riHdrNo = null): void
    {
        if (!$riHdrNo) {
            $this->dispatch('toast', type: 'error', message: 'No. RI tidak valid.');
            return;
        }

        $barisList = [];
        $adaYangBesar = false;
        foreach (self::TABEL_ADMINISTRASI_RI as $tabel => $label) {
            try {
                $jumlah = DB::table($tabel)->where('rihdr_no', $riHdrNo)->count();
                $adaYangBesar = $adaYangBesar || $jumlah > 1000;
                $barisList[] = e($label) . ': <b>' . number_format($jumlah) . '</b>';
            } catch (\Throwable $e) {
                $barisList[] = e($label) . ': gagal dihitung';
            }
        }

        // Jumlah baris yang benar-benar dirender tab Lain-Lain (JOIN ke master, sama dengan findData()).
        $jumlahLainLainTerjoin = DB::table('rstxn_riothers')
            ->join('rsmst_others', 'rstxn_riothers.other_id', '=', 'rsmst_others.other_id')
            ->where('rstxn_riothers.rihdr_no', $riHdrNo)
            ->count();
        $adaYangBesar = $adaYangBesar || $jumlahLainLainTerjoin > 1000;
        $barisList[] = 'Lain-Lain setelah JOIN master: <b>' . number_format($jumlahLainLainTerjoin) . '</b>';

        $this->dispatch(
            'toast',
            type: $adaYangBesar ? 'warning' : 'info',
            message: "Jumlah record RI {$riHdrNo}<br>" . implode('<br>', $barisList),
            opts: ['timeOut' => 20000, 'closeButton' => true],
        );
    }
};
?>

<div></div>
