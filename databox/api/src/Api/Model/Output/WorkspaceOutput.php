<?php

declare(strict_types=1);

namespace App\Api\Model\Output;

use ApiPlatform\Core\Annotation\ApiProperty;
use ApiPlatform\Core\Annotation\ApiResource;
use App\Api\Model\Output\Traits\CapabilitiesDTOTrait;
use App\Entity\Core\Collection;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * @ApiResource(
 *     shortName="workspace",
 *     normalizationContext="workspace:read",
 * )
 */
class WorkspaceOutput extends AbstractUuidOutput
{
    use CapabilitiesDTOTrait;

    /**
     * @Groups({"workspace:index", "workspace:read"})
     */
    protected array $capabilities = [];

    /**
     * @Groups({"workspace:index", "workspace:read"})
     */
    private string $name;

    /**
     * @var Collection[]
     * @Groups({"workspace:index"})
     */
    private ?array $collections = null;

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getCollections(): ?array
    {
        return $this->collections;
    }

    public function setCollections(?array $collections): void
    {
        $this->collections = $collections;
    }
}
