<?php

declare(strict_types=1);

namespace App\Entity\Traits;

use ApiPlatform\Core\Annotation\ApiProperty;
use Symfony\Component\Serializer\Annotation\Groups;

trait CapabilitiesTrait
{
    /**
     * @ApiProperty(attributes={
     *  "json_schema_context"={"type"="object"}
     * })
     */
    #[Groups(['_', 'publication:index', 'publication:read', 'profile:index', 'profile:read'])]
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
