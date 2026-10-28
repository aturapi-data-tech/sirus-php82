{{-- Partial coding-anamnesa-ugd — Kedatangan (Playbook IGD 3a–3c): baca saja, kodenya tetap dari
     App\Support\Terminologi\KedatanganIgd (dihitung di kelas induk, $kedatanganBaris). --}}
<div>
    <x-satu-sehat.kotak-coding judul="Kedatangan & Triase (3a–3c, otomatis dari Pengkajian Perawatan)">
        <div class="space-y-2">
            @foreach ($kedatanganBaris as $item)
                <x-satu-sehat.baris-baca :label="$item['label']" :isi="$item['isi']" :kode="$item['kode']" />
            @endforeach
        </div>
    </x-satu-sehat.kotak-coding>
</div>
