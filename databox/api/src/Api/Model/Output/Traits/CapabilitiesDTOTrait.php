<?php

declare(strict_types=1);

namespace App\Api\Model\Output\Traits;

use ApiPlatform\Core\Annotation\ApiProperty;

trait CapabilitiesDTOTrait
{
    /**
     * @ApiProperty(attributes={
     *  "json_schema_context"={"type"="object"}
     * })
     */
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
