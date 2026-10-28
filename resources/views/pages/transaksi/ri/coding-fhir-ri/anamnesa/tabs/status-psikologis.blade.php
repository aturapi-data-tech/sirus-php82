{{-- Partial coding-anamnesa-ri — Status Psikologis (Playbook RI bab 5b): baca saja, dari status emosional
     Pengkajian Awal; kodenya tetap dari StatusPsikologisMap::aktifRi (tak perlu coding). --}}
@forelse ($statusPsikologisBaris as $baris)
    <x-satu-sehat.baris-baca :label="$loop->first ? 'Status Psikologis' : ''" :isi="$baris['isi']" :kode="$baris['kode']" />
@empty
    <x-satu-sehat.baris-baca label="Status Psikologis" isi="" kode="status emosional belum diisi di Pengkajian Awal — tidak dikirim (opsional)" />
@endforelse
