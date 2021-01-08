<?php

declare(strict_types=1);

namespace App\Api\Model\Output;

use ApiPlatform\Core\Annotation\ApiProperty;
use ApiPlatform\Core\Annotation\ApiResource;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * @ApiResource(
 *     shortName="workspace",
 *     normalizationContext="workspace:read",
 * )
 */
class WorkspaceOutput extends AbstractUuidDTO
{
    /**
     * @ApiProperty(writable=false)
     * @Groups({"workspace:read"})
     */
    private string $name;

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }
}
