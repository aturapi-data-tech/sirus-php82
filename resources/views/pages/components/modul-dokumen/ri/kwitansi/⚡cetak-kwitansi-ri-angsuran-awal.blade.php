<?php

use Livewire\Component;
use Livewire\Attributes\On;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Kwitansi Angsuran Awal (titipan) Rawat Inap — satu baris rstxn_ripaymentdtls.
 * Pengganti cetak_kwitansi form 6i yang tercetak setelah titipan disimpan.
 */
new class extends Component {

    #[On('cetak-kwitansi-ri-angsuran-awal.open')]
    public function open(int $ripayNo): mixed
    {
        $hdr = DB::selectOne(
            "
            SELECT
                pay.ripay_no,
                TO_CHAR(pay.ripay_date, 'DD/MM/YYYY HH24:MI') AS ripay_date,
                pay.ripay_bayar,
                pay.shift,
                pay.emp_id,
                pay.acc_id,
                pay.rihdr_no,
                r.reg_no,
                TO_CHAR(r.entry_date, 'DD/MM/YYYY HH24:MI') AS entry_date,
                r.klaim_id,
                p.reg_name,
                p.sex,
                p.birth_place,
                p.address,
                TO_CHAR(p.birth_date, 'DD/MM/YYYY') AS birth_date,
                k.klaim_desc,
                a.acc_name,
                e.emp_name,
                rm.room_name
            FROM rstxn_ripaymentdtls pay
            JOIN rstxn_rihdrs r ON r.rihdr_no = pay.rihdr_no
            JOIN rsmst_pasiens p ON p.reg_no = r.reg_no
            LEFT JOIN rsmst_klaimtypes k ON k.klaim_id = r.klaim_id
            LEFT JOIN acmst_accounts a ON a.acc_id = pay.acc_id
            LEFT JOIN immst_employers e ON e.emp_id = pay.emp_id
            LEFT JOIN (
                SELECT t.rihdr_no, MAX(t.trfr_no) AS trfr_no
                FROM rsmst_trfrooms t
                GROUP BY t.rihdr_no
            ) tlast ON tlast.rihdr_no = pay.rihdr_no
            LEFT JOIN rsmst_trfrooms trf ON trf.rihdr_no = tlast.rihdr_no AND trf.trfr_no = tlast.trfr_no
            LEFT JOIN rsmst_rooms rm ON rm.room_id = trf.room_id
            WHERE pay.ripay_no = :ripayno
            ",
            ['ripayno' => $ripayNo],
        );

        if (!$hdr) {
            $this->dispatch('toast', type: 'error', message: 'Data angsuran awal tidak ditemukan.');
            return null;
        }

        // Akumulasi titipan s.d. kwitansi ini — pasien bisa menitip berkali-kali.
        // Urut ripay_no (MAX+1, naik sesuai urutan entri), bukan ripay_date yang bisa diisi mundur.
        $totalSampaiIni = (int) DB::table('rstxn_ripaymentdtls')
            ->where('rihdr_no', $hdr->rihdr_no)
            ->where('ripay_no', '<=', $hdr->ripay_no)
            ->sum('ripay_bayar');

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
            'regNo'      => $hdr->reg_no,
            'regName'    => $hdr->reg_name,
            'sex'        => $hdr->sex,
            'birthPlace' => $hdr->birth_place,
            'address'    => $hdr->address,
            'birthDate'  => $hdr->birth_date ?? '-',
            'umur'       => $umurLabel,

            'ripayNo'    => $hdr->ripay_no,
            'ripayDate'  => $hdr->ripay_date ?? '-',
            'shift'      => $hdr->shift ?? '-',
            'entryDate'  => $hdr->entry_date ?? '-',
            'roomDesc'   => $hdr->room_name ?? '-',
            'klaimName'  => $hdr->klaim_desc ?? ($hdr->klaim_id ?? '-'),
            'accName'    => $hdr->acc_name ?? ($hdr->acc_id ?? '-'),

            'nominal'        => (int) $hdr->ripay_bayar,
            'totalSampaiIni' => $totalSampaiIni,

            'kasirName' => $hdr->emp_name ?? ($hdr->emp_id ?? null),
            'tglCetak'  => Carbon::now(config('app.timezone'))->translatedFormat('d/m/Y'),
            'jamCetak'  => Carbon::now(config('app.timezone'))->format('H:i'),
            'cetakOleh' => auth()->user()->myuser_name ?? '-',
        ];

        $pdf = Pdf::loadView('pages.components.modul-dokumen.ri.kwitansi.cetak-kwitansi-ri-angsuran-awal-print', ['data' => $data])->setPaper('A4');

        $filename = 'kwitansi-ri-angsuran-awal-' . ($hdr->reg_no ?? $ripayNo) . '-' . $ripayNo . '.pdf';

        return response()->streamDownload(fn() => print $pdf->output(), $filename, ['Content-Type' => 'application/pdf']);
    }
};
?>
<div></div>
