<?php

use Livewire\Component;
use Livewire\Attributes\On;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

new class extends Component {

    /**
     * Rincian pemakaian obat satu kunjungan RI (bukan per nota resep):
     * OBAT PINJAM (rstxn_riobats) + RESEP OBAT (imtxn_slsdtls lewat nota ter-post)
     * + BIAYA TINDAKAN LAIN-LAIN (acte_price tiap nota) = TOTAL PEMAKAIAN OBAT.
     * Nilainya sengaja dibuat sama dengan yang ditagihkan kwitansi detail
     * (obatPinjam + bonResep + resepLunas), makanya nota status 'A' (draft
     * apotek, belum dibayar/ditagihkan) tidak ikut dihitung.
     */
    #[On('cetak-rincian-obat-ri.open')]
    public function open(int $riHdrNo): mixed
    {
        $hdr = DB::selectOne(
            "
            SELECT
                a.rihdr_no,
                a.reg_no,
                b.reg_name,
                b.address,
                b.sex,
                b.birth_place,
                TO_CHAR(b.birth_date, 'DD/MM/YYYY') AS birth_date,
                TO_CHAR(a.entry_date, 'DD/MM/YYYY HH24:MI') AS entry_date,
                TO_CHAR(a.exit_date,  'DD/MM/YYYY HH24:MI') AS exit_date,
                a.emp_id,
                a.klaim_id,
                k.klaim_desc
            FROM  rstxn_rihdrs  a
            JOIN  rsmst_pasiens b ON b.reg_no = a.reg_no
            LEFT JOIN rsmst_klaimtypes k ON k.klaim_id = a.klaim_id
            WHERE a.rihdr_no = :rihdr
            ",
            ['rihdr' => $riHdrNo],
        );

        if (!$hdr) {
            $this->dispatch('toast', type: 'error', message: 'Data RI tidak ditemukan.');
            return null;
        }

        // ─── OBAT PINJAM — digabung per produk (satu produk bisa dipinjam berkali-kali) ───
        $obatPinjam = DB::table('rstxn_riobats as o')
            ->leftJoin('immst_products as p', 'p.product_id', '=', 'o.product_id')
            ->where('o.rihdr_no', $riHdrNo)
            ->selectRaw("
                o.product_id,
                NVL(p.product_name, o.product_id) AS product_name,
                SUM(NVL(o.riobat_qty, 0)) AS qty,
                SUM(NVL(o.riobat_qty, 0) * NVL(o.riobat_price, 0)) AS total
            ")
            ->groupBy('o.product_id', DB::raw('NVL(p.product_name, o.product_id)'))
            ->orderBy(DB::raw('NVL(p.product_name, o.product_id)'))
            ->get();

        // ─── RESEP OBAT — semua nota apotek ter-post milik kunjungan ini ───
        $resepObat = DB::table('imtxn_slsdtls as d')
            ->join('imtxn_slshdrs as s', 's.sls_no', '=', 'd.sls_no')
            ->leftJoin('immst_products as p', 'p.product_id', '=', 'd.product_id')
            ->where('s.rihdr_no', $riHdrNo)
            ->where('s.status', 'L')
            ->selectRaw("
                d.product_id,
                NVL(p.product_name, d.product_id) AS product_name,
                SUM(NVL(d.qty, 0)) AS qty,
                SUM(NVL(d.qty, 0) * NVL(d.sales_price, 0)) AS total
            ")
            ->groupBy('d.product_id', DB::raw('NVL(p.product_name, d.product_id)'))
            ->orderBy(DB::raw('NVL(p.product_name, d.product_id)'))
            ->get();

        // ─── RETUR OBAT — hanya tampil bila ada; mengurangi total ───
        $returObat = DB::table('rstxn_riobatrtns as r')
            ->leftJoin('immst_products as p', 'p.product_id', '=', 'r.product_id')
            ->where('r.rihdr_no', $riHdrNo)
            ->selectRaw("
                r.product_id,
                NVL(p.product_name, r.product_id) AS product_name,
                SUM(NVL(r.riobat_qty, 0)) AS qty,
                SUM(NVL(r.riobat_qty, 0) * NVL(r.riobat_price, 0)) AS total
            ")
            ->groupBy('r.product_id', DB::raw('NVL(p.product_name, r.product_id)'))
            ->orderBy(DB::raw('NVL(p.product_name, r.product_id)'))
            ->get();

        // BIAYA TINDAKAN LAIN-LAIN = jasa karyawan (acte_price) yang dipungut per nota resep.
        $tindakanLain = (int) DB::table('imtxn_slshdrs')
            ->where('rihdr_no', $riHdrNo)
            ->where('status', 'L')
            ->sum('acte_price');

        $totalObatPinjam = (int) $obatPinjam->sum('total');
        $totalResepObat  = (int) $resepObat->sum('total');
        $totalReturObat  = (int) $returObat->sum('total');

        if ($totalObatPinjam === 0 && $totalResepObat === 0 && $tindakanLain === 0) {
            $this->dispatch('toast', type: 'warning', message: 'Belum ada pemakaian obat pada kunjungan ini.');
            return null;
        }

        $totalPemakaian = $totalObatPinjam + $totalResepObat + $tindakanLain - $totalReturObat;

        $kasirName = !empty($hdr->emp_id)
            ? DB::table('immst_employers')->where('emp_id', $hdr->emp_id)->value('emp_name')
            : null;

        // Umur dihitung ulang dari birth_date (kolom thn/bln/hari snapshot, jangan dipakai)
        $umurLabel = '-';
        if (!empty($hdr->birth_date)) {
            try {
                $diff = Carbon::createFromFormat('d/m/Y', $hdr->birth_date)->diff(now());
                $umurLabel = "{$diff->y} Thn {$diff->m} Bln {$diff->d} Hr";
            } catch (\Throwable $e) {
                $umurLabel = '-';
            }
        }

        $data = [
            // Pasien
            'regNo'      => $hdr->reg_no,
            'regName'    => $hdr->reg_name,
            'address'    => $hdr->address,
            'sex'        => $hdr->sex,
            'birthPlace' => $hdr->birth_place,
            'birthDate'  => $hdr->birth_date ?? '-',
            'umur'       => $umurLabel,

            // Kunjungan
            'rihdrNo'    => $hdr->rihdr_no,
            'entryDate'  => $hdr->entry_date ?? '-',
            'exitDate'   => $hdr->exit_date ?? '-',
            'klaimName'  => $hdr->klaim_desc ?? ($hdr->klaim_id ?? '-'),

            // Rincian
            'obatPinjam'      => $obatPinjam,
            'resepObat'       => $resepObat,
            'returObat'       => $returObat,
            'totalObatPinjam' => $totalObatPinjam,
            'totalResepObat'  => $totalResepObat,
            'totalReturObat'  => $totalReturObat,
            'tindakanLain'    => $tindakanLain,
            'totalPemakaian'  => $totalPemakaian,

            // Footer
            'kasirName' => $kasirName,
            'tglCetak'  => Carbon::now(config('app.timezone'))->translatedFormat('d/m/Y'),
            'jamCetak'  => Carbon::now(config('app.timezone'))->format('H:i'),
            'cetakOleh' => auth()->user()->myuser_name ?? '-',
        ];

        $pdf = Pdf::loadView('pages.components.modul-dokumen.ri.kwitansi.cetak-rincian-obat-ri-print', ['data' => $data])->setPaper('A4');

        $filename = 'rincian-obat-ri-' . ($hdr->reg_no ?? $riHdrNo) . '-' . $riHdrNo . '.pdf';

        return response()->streamDownload(fn() => print $pdf->output(), $filename, ['Content-Type' => 'application/pdf']);
    }
};
?>
<div></div>
