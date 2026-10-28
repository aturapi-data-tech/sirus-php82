{{-- Partial coding-anamnesa-ugd — Status Psikologis (Playbook IGD bab 8a): baca saja, kodenya tetap
     dari App\Support\Terminologi\StatusPsikologisMap (dicentang di EMR, tak perlu coding). --}}
@forelse ($statusPsikologisBaris as $baris)
    <x-satu-sehat.baris-baca :label="$loop->first ? 'Status Psikologis' : ''" :isi="$baris['isi']" :kode="$baris['kode']" />
@empty
    <x-satu-sehat.baris-baca label="Status Psikologis" isi="" kode="belum dicentang di EMR — tidak dikirim (opsional)" />
@endforelse
