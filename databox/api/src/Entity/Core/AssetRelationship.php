<?php

declare(strict_types=1);

namespace App\Entity\Core;

use App\Entity\AbstractUuidEntity;
use App\Entity\Traits\CreatedAtTrait;
use App\Entity\Traits\UpdatedAtTrait;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity()
 * @ORM\Table()
 */
class AssetRelationship extends AbstractUuidEntity
{
    use CreatedAtTrait;
    use UpdatedAtTrait;

    /**
     * The type of relationship.
     *
     * @ORM\Column(type="string", length=20)
     */
    private ?string $type = null;

    /**
     * Whether the two assets can't live alone (being deleted, moved to another collection...).
     *
     * @ORM\Column(type="boolean")
     */
    private bool $sticky = false;

    /**
     * @ORM\ManyToOne(targetEntity=Asset::class)
     * @ORM\JoinColumn(nullable=true)
     */
    private ?Asset $source = null;

    /**
     * @ORM\ManyToOne(targetEntity=Asset::class)
     * @ORM\JoinColumn(nullable=true)
     */
    private ?Asset $target = null;

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(?string $type): void
    {
        $this->type = $type;
    }

    public function isSticky(): bool
    {
        return $this->sticky;
    }

    public function setSticky(bool $sticky): void
    {
        $this->sticky = $sticky;
    }

    public function getSource(): ?Asset
    {
        return $this->source;
    }

    public function setSource(?Asset $source): void
    {
        $this->source = $source;
    }

    public function getTarget(): ?Asset
    {
        return $this->target;
    }

    public function setTarget(?Asset $target): void
    {
        $this->target = $target;
    }
}
