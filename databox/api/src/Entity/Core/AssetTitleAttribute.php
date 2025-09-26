<?php

declare(strict_types=1);

namespace App\Entity\Core;

use Alchemy\CoreBundle\Entity\AbstractUuidEntity;
use App\Model\AssetTypeEnum;
use App\Validator\SameWorkspaceConstraint;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[SameWorkspaceConstraint(
    properties: ['workspace', 'definition.workspace']
)]
class AssetTitleAttribute extends AbstractUuidEntity
{
    #[ORM\ManyToOne(targetEntity: Workspace::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Workspace $workspace = null;

    #[ORM\ManyToOne(targetEntity: AttributeDefinition::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    protected ?AttributeDefinition $definition = null;

    #[ORM\Column(type: Types::SMALLINT, nullable: false)]
    private int $priority = 0;

    #[ORM\Column(type: Types::SMALLINT, nullable: false, enumType: AssetTypeEnum::class)]
    private AssetTypeEnum $target = AssetTypeEnum::Asset;

    /**
     * Whether to override "title" attribute set on asset.
     */
    #[ORM\Column(type: Types::BOOLEAN, nullable: false)]
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

    public function getTarget(): AssetTypeEnum
    {
        return $this->target;
    }

    public function isForTarget(AssetTypeEnum $target): bool
    {
        return ($this->target->value & $target->value) === $target->value;
    }

    public function setTarget(AssetTypeEnum $target): void
    {
        $this->target = $target;
    }
}
