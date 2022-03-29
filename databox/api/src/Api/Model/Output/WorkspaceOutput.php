<?php

declare(strict_types=1);

namespace App\Api\Model\Output;

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
     * @Groups({"workspace:index", "workspace:read", "collection:index", "collection:read"})
     */
    protected array $capabilities = [];

    /**
     * @Groups({"workspace:index", "workspace:read", "collection:index", "collection:read", "asset:index", "asset:read"})
     */
    private string $name;

    /**
     * @Groups({"workspace:index", "workspace:read"})
     */
    private string $slug;

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): void
    {
        $this->slug = $slug;
    }
}
