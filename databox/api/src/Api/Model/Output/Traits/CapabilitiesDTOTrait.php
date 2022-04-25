<?php

declare(strict_types=1);

namespace App\Api\Model\Output\Traits;

use ApiPlatform\Core\Annotation\ApiProperty;

trait CapabilitiesDTOTrait
{
    /**
     * @var array{
     *     canEdit: boolean,
     *     canDelete: boolean,
     *     canEditPermissions: boolean,
     * }
     * @ApiProperty(openapiContext={
     *     type="object"
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
