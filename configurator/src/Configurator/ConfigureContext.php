<?php

declare(strict_types=1);

namespace App\Configurator;

final readonly class ConfigureContext
{
    /**
     * @param string[] $presets
     * @param bool     $install true on a fresh installation only: one-time resources (such as
     *                          the default admin user) are not created again on an upgrade
     */
    public function __construct(
        public array $presets = [],
        public bool $install = false,
    ) {
    }

    public function hasPreset(string $preset): bool
    {
        return in_array($preset, $this->presets, true);
    }
}
