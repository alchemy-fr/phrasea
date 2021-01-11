<?php

declare(strict_types=1);

namespace App\Entity\Core;

use Alchemy\AclBundle\AclObjectInterface;
use ApiPlatform\Core\Annotation\ApiResource;
use App\Entity\AbstractUuidEntity;
use App\Entity\SearchableEntityInterface;
use App\Entity\SearchDependencyInterface;
use App\Entity\Traits\CreatedAtTrait;
use App\Entity\Traits\TranslatableTrait;
use App\Entity\Traits\UpdatedAtTrait;
use App\Entity\Traits\WorkspaceTrait;
use App\Entity\TranslatableInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection as DoctrineCollection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use App\Api\Model\Output\CollectionOutput;

/**
 * @ORM\Entity()
 * @ApiResource(
 *  shortName="collection",
 *  normalizationContext={"groups"={"_", "collection:index"}},
 *  output=CollectionOutput::class,
 *  input=false,
 * )
 */
class Collection extends AbstractUuidEntity implements AclObjectInterface, TranslatableInterface, SearchableEntityInterface, SearchDependencyInterface
{
    use CreatedAtTrait;
    use UpdatedAtTrait;
    use WorkspaceTrait;
    use TranslatableTrait;

    /**
     * @Assert\NotBlank
     * @Assert\Length(max=255)
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private ?string $title = null;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private ?string $ownerId = null;

    /**
     * @ORM\Column(type="boolean", nullable=false)
     */
    private bool $public = false;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Core\Collection", inversedBy="children")
     * @ORM\JoinColumn(nullable=true)
     */
    private ?self $parent = null;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Core\Collection", mappedBy="parent", cascade={"remove"})
     * @ORM\JoinColumn(nullable=true)
     */
    private ?DoctrineCollection $children = null;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Core\CollectionAsset", mappedBy="collection", cascade={"persist", "remove"})
     * @ORM\JoinColumn(nullable=true)
     */
    private ?DoctrineCollection $assets = null;

    public function __construct()
    {
        parent::__construct();
        $this->children = new ArrayCollection();
        $this->assets = new ArrayCollection();
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): void
    {
        $this->title = $title;
    }

    public function getParent(): ?self
    {
        return $this->parent;
    }

    public function setParent(?self $parent): void
    {
        $this->parent = $parent;
    }

    /**
     * @return Collection[]
     */
    public function getChildren(): DoctrineCollection
    {
        return $this->children;
    }

    public function getOwnerId(): ?string
    {
        return $this->ownerId;
    }

    public function setOwnerId(?string $ownerId): void
    {
        $this->ownerId = $ownerId;
    }

    /**
     * @return CollectionAsset[]
     */
    public function getAssets(): DoctrineCollection
    {
        return $this->assets;
    }

    public function isPublic(): bool
    {
        return $this->public;
    }

    public function setPublic(bool $public): void
    {
        $this->public = $public;
    }
}
