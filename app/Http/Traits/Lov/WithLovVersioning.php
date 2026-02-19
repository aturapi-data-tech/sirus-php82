<?php

namespace App\Http\Traits\Lov;

/**
 * Trait WithLovVersioning
 *
 * Trait untuk mengelola versioning LOV (List of Values) component
 * Memungkinkan multiple LOV di-refresh secara independen
 *
 * @property array $lovVersions Array yang menyimpan versi setiap LOV
 */
trait WithLovVersioning
{
    /**
     * Initialize trait
     * Bisa dipanggil di mount/constructor component
     */
    public function initializeWithLovVersioning(): void
    {
        // Initialize lovVersions jika belum ada
        if (!isset($this->lovVersions) || !is_array($this->lovVersions)) {
            $this->lovVersions = [];
        }

        // Auto-register LOV dari property $lovList jika ada
        if (property_exists($this, 'lovList') && is_array($this->lovList)) {
            foreach ($this->lovList as $lovName) {
                $this->registerLov($lovName);
            }
        }
    }

    /**
     * Register LOV baru
     *
     * @param string $lovName Nama LOV (pasien, dokter, ruangan, dll)
     * @param int $initialVersion Versi awal (default 0)
     * @return self
     */
    public function registerLov(string $lovName, int $initialVersion = 0): self
    {
        if (!isset($this->lovVersions[$lovName])) {
            $this->lovVersions[$lovName] = $initialVersion;
        }

        return $this;
    }

    /**
     * Register multiple LOV sekaligus
     *
     * @param array $lovList Daftar nama LOV
     * @return self
     */
    public function registerLovs(array $lovList): self
    {
        foreach ($lovList as $lovName) {
            $this->registerLov($lovName);
        }

        return $this;
    }

    /**
     * Increment versi LOV
     *
     * @param string $lovName Nama LOV yang akan di-increment
     * @return int Versi baru setelah increment
     * @throws \InvalidArgumentException
     */
    public function incrementLovVersion(string $lovName): int
    {
        $this->validateLovExists($lovName);

        return ++$this->lovVersions[$lovName];
    }

    /**
     * Increment multiple LOV sekaligus
     *
     * @param array $lovNames Daftar nama LOV yang akan di-increment
     * @return array Associative array [lovName => newVersion]
     */
    public function incrementLovVersions(array $lovNames): array
    {
        $updated = [];
        foreach ($lovNames as $lovName) {
            $updated[$lovName] = $this->incrementLovVersion($lovName);
        }

        return $updated;
    }

    /**
     * Increment semua LOV yang terdaftar
     *
     * @return array Associative array [lovName => newVersion]
     */
    public function incrementAllLovs(): array
    {
        $updated = [];
        foreach (array_keys($this->lovVersions) as $lovName) {
            $updated[$lovName] = $this->incrementLovVersion($lovName);
        }

        return $updated;
    }

    /**
     * Get current version of a LOV
     *
     * @param string $lovName
     * @return int
     */
    public function getLovVersion(string $lovName): int
    {
        $this->validateLovExists($lovName);

        return $this->lovVersions[$lovName];
    }

    /**
     * Set version LOV secara manual
     *
     * @param string $lovName
     * @param int $version
     * @return self
     */
    public function setLovVersion(string $lovName, int $version): self
    {
        $this->validateLovExists($lovName);
        $this->lovVersions[$lovName] = max(0, $version);

        return $this;
    }

    /**
     * Reset LOV version ke 0
     *
     * @param string|null $lovName Jika null, reset semua LOV
     * @return self
     */
    public function resetLovVersion(?string $lovName = null): self
    {
        if ($lovName) {
            $this->validateLovExists($lovName);
            $this->lovVersions[$lovName] = 0;
        } else {
            foreach (array_keys($this->lovVersions) as $name) {
                $this->lovVersions[$name] = 0;
            }
        }

        return $this;
    }

    /**
     * Check if LOV exists
     *
     * @param string $lovName
     * @return bool
     */
    public function hasLov(string $lovName): bool
    {
        return isset($this->lovVersions[$lovName]);
    }

    /**
     * Get all registered LOVs with their versions
     *
     * @return array
     */
    public function getAllLovVersions(): array
    {
        return $this->lovVersions;
    }

    /**
     * Generate wire:key untuk LOV component
     *
     * @param string $lovName
     * @param array|string $additionalContext Konteks tambahan untuk key (form mode, ID, dll)
     * @return string
     */
    public function lovKey(string $lovName, array|string $additionalContext = []): string
    {
        $this->validateLovExists($lovName);

        $context = is_array($additionalContext)
            ? implode('-', $additionalContext)
            : $additionalContext;

        $base = "lov-{$lovName}-v{$this->lovVersions[$lovName]}";

        return $context ? "{$base}-{$context}" : $base;
    }

    /**
     * Generate array of wire:keys untuk multiple LOVs
     *
     * @param array $lovNames
     * @param array|string $additionalContext
     * @return array
     */
    public function lovKeys(array $lovNames, array|string $additionalContext = []): array
    {
        $keys = [];
        foreach ($lovNames as $lovName) {
            $keys[$lovName] = $this->lovKey($lovName, $additionalContext);
        }

        return $keys;
    }

    /**
     * Unregister LOV (remove from tracking)
     *
     * @param string $lovName
     * @return self
     */
    public function unregisterLov(string $lovName): self
    {
        unset($this->lovVersions[$lovName]);

        return $this;
    }

    /**
     * Validate that LOV exists
     *
     * @param string $lovName
     * @throws \InvalidArgumentException
     */
    protected function validateLovExists(string $lovName): void
    {
        if (!isset($this->lovVersions[$lovName])) {
            throw new \InvalidArgumentException(
                sprintf(
                    'LOV "%s" is not registered. Available LOVs: %s',
                    $lovName,
                    implode(', ', array_keys($this->lovVersions))
                )
            );
        }
    }
}
