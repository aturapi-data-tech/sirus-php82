<?php

namespace App\Http\Traits\Concerns;

use App\Support\AksesHalaman;

/**
 * Tab yang mengikuti HAK HALAMAN ASALNYA — untuk halaman bertab yang menanam komponen halaman
 * lain (Antrian Apotek, Antrian Kasir, Casemix).
 *
 * Tiap tab dipetakan ke route halaman asal yang komponennya ditanam (tab RJ di Antrian Apotek
 * = `rj.antrian-apotek`). Tab hanya tampil & bisa dipilih bila user boleh Lihat halaman asal itu
 * — hak tetap diatur di kelompok jalurnya masing-masing di Role Control. Tanpa ini, siapa pun
 * yang boleh membuka halaman bertab bisa memakai semua tab walau tak punya hak halaman asalnya
 * (aksi Livewire komponen tertanam dicek terhadap route halaman bertab, bukan halaman asal).
 *
 * Pemakai:
 *   - properti `#[Locked] public string $activeTab` (diganti hanya lewat setTab()),
 *   - method petaTabHalaman(): tab => route asal (tab milik halaman ini sendiri → route halaman ini;
 *     JANGAN dibiarkan tanpa cek: di mode `log` halaman tetap terbuka bagi yang tak berhak),
 *   - panggil pastikanTabBoleh() di mount(), cek bolehTab($tab) di setTab() & di tiap <x-tab>.
 */
trait WithTabHakAksesTrait
{
    /** @return array<string, string> tab => route halaman asal */
    abstract protected function petaTabHalaman(): array;

    public function bolehTab(string $tab): bool
    {
        $peta = $this->petaTabHalaman();
        if (!array_key_exists($tab, $peta)) {
            return false;
        }

        return AksesHalaman::boleh(auth()->user(), $peta[$tab]);
    }

    /** @return array<int, string> tab yang boleh dipakai user, urut sesuai peta. */
    public function tabTersedia(): array
    {
        return array_values(array_filter(array_keys($this->petaTabHalaman()), fn (string $tab) => $this->bolehTab($tab)));
    }

    /** Tab aktif yang tak boleh (default / dari URL) diganti tab pertama yang boleh; '' bila tak ada. */
    protected function pastikanTabBoleh(): void
    {
        if (!$this->bolehTab($this->activeTab)) {
            $this->activeTab = $this->tabTersedia()[0] ?? '';
        }
    }
}
