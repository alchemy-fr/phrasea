<?php

declare(strict_types=1);

namespace App\Entity\Core;

use App\Entity\AbstractUuidEntity;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class AssetTitleAttribute extends AbstractUuidEntity
{
    #[ORM\ManyToOne(targetEntity: Workspace::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Workspace $workspace = null;

    #[ORM\ManyToOne(targetEntity: AttributeDefinition::class)]
    #[ORM\JoinColumn(nullable: false)]
    protected ?AttributeDefinition $definition = null;

    #[ORM\Column(type: 'smallint', nullable: false)]
    private int $priority = 0;

    /**
     * Whether to override "title" attribute set on asset.
     */
    #[ORM\Column(type: 'boolean', nullable: false)]
    private bool $overrides = false;

    public function getWorkspace(): ?Workspace
    {
        return $this->workspace;
    }

    public function setWorkspace(?Workspace $workspace): void
    {
        $this->workspace = $workspace;
    }

    public function getDefinition(): ?AttributeDefinition
    {
        return $this->definition;
    }

    public function setDefinition(?AttributeDefinition $definition): void
    {
        $this->definition = $definition;
    }

    public function getPriority(): int
    {
        return $this->priority;
    }

    public function setPriority(int $priority): void
    {
        $this->priority = $priority;
    }

    public function isOverrides(): bool
    {
        return $this->overrides;
    }

    public function setOverrides(bool $overrides): void
    {
        $this->overrides = $overrides;
    }
}
