<?php

declare(strict_types=1);

namespace App\Api\Model\Output\Traits;

trait CapabilitiesDTOTrait
{
    protected array $capabilities = [];

    public function getCapabilities(): array
    {
        return $this->capabilities;
    }

    public function setCapabilities(array $capabilities): void
    {
        $this->capabilities = $capabilities;
    }
}
