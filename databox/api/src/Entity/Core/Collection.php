<?php

declare(strict_types=1);

namespace App\Entity\Core;

use Alchemy\AclBundle\AclObjectInterface;
use ApiPlatform\Core\Annotation\ApiResource;
use App\Entity\AbstractUuidEntity;
use App\Entity\SearchableEntityInterface;
use App\Entity\SearchDependencyInterface;
use App\Entity\Traits\CreatedAtTrait;
use App\Entity\Traits\LocaleTrait;
use App\Entity\Traits\UpdatedAtTrait;
use App\Entity\Traits\WorkspacePrivacyTrait;
use App\Entity\Traits\WorkspaceTrait;
use App\Entity\TranslatableInterface;
use App\Entity\WithOwnerIdInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection as DoctrineCollection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Serializer\Annotation\MaxDepth;

/**
 * @ORM\Entity(repositoryClass="App\Repository\Core\CollectionRepository")
 * @ORM\Table(uniqueConstraints={@ORM\UniqueConstraint(name="uniq_coll_ws_key",columns={"workspace_id", "key"})})
 * @ApiResource()
 */
class Collection extends AbstractUuidEntity implements WithOwnerIdInterface, AclObjectInterface, TranslatableInterface, SearchableEntityInterface, SearchDependencyInterface
{
    use CreatedAtTrait;
    use UpdatedAtTrait;
    use WorkspaceTrait;
    use LocaleTrait;
    use WorkspacePrivacyTrait;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private ?string $title = null;

    /**
     * @ORM\Column(type="string", length=36)
     */
    private ?string $ownerId = null;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Core\Collection", inversedBy="children")
     * @ORM\JoinColumn(nullable=true)
     * @MaxDepth(1)
     */
    private ?self $parent = null;

    /**
     * @var self[]
     *
     * @ORM\OneToMany(targetEntity="App\Entity\Core\Collection", mappedBy="parent", cascade={"remove"})
     * @ORM\JoinColumn(nullable=true)
     * @MaxDepth(1)
     */
    private ?DoctrineCollection $children = null;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Core\CollectionAsset", mappedBy="collection", cascade={"persist", "remove"})
     */
    private ?DoctrineCollection $assets = null;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Core\Asset", mappedBy="referenceCollection", cascade={"remove"})
     */
    private ?DoctrineCollection $referenceAssets = null;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Core\Workspace", inversedBy="collections")
     * @ORM\JoinColumn(nullable=false)
     */
    protected ?Workspace $workspace = null;

    /**
     * Unique key by workspace. Used to prevent duplicates.
     *
     * @ORM\Column(type="string", length=4096, nullable=true)
     */
    private ?string $key = null;

    public function __construct()
    {
        parent::__construct();
        $this->children = new ArrayCollection();
        $this->assets = new ArrayCollection();
        $this->referenceAssets = new ArrayCollection();
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
        if ($parent->getWorkspace() !== $this->getWorkspace()) {
            throw new BadRequestHttpException('Cannot add a sub-collection in a different workspace');
        }

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

    public function getAbsolutePath(): string
    {
        $path = '/'.$this->getId();
        if (null !== $this->parent) {
            return $this->parent->getAbsolutePath().$path;
        }

        return $path;
    }

    public function getPathDepth(): int
    {
        $depth = 0;
        $ptr = $this;
        while (null !== $ptr = $ptr->parent) {
            ++$depth;
        }

        return $depth;
    }

    public function isRoot(): bool
    {
        return null === $this->parent;
    }

    public function getBestPrivacyInParentHierarchy(): int
    {
        $bestPrivacy = $this->privacy;

        // Early return if best
        if (WorkspaceItemPrivacyInterface::PUBLIC === $bestPrivacy) {
            return $this->privacy;
        }

        if (
            null !== $this->parent
            && ($better = $this->parent->getBestPrivacyInParentHierarchy()) > $bestPrivacy
        ) {
            return $better;
        }

        return $bestPrivacy;
    }

    public function getBestPrivacyInDescendantHierarchy(): int
    {
        $bestPrivacy = $this->privacy;
        // Early return if best
        if (WorkspaceItemPrivacyInterface::PUBLIC === $bestPrivacy) {
            return $this->privacy;
        }

        foreach ($this->children as $child) {
            if (($better = $child->getBestPrivacyInParentHierarchy()) > $bestPrivacy) {
                // Early return if best
                if (WorkspaceItemPrivacyInterface::PUBLIC === $bestPrivacy) {
                    return $this->privacy;
                }
                $bestPrivacy = $better;
            }
        }

        return $bestPrivacy;
    }

    public function isVisible(): bool
    {
        return $this->privacy >= WorkspaceItemPrivacyInterface::PRIVATE;
    }

    public function getAbsoluteTitle(): string
    {
        $path = $this->getTitle();
        if (null !== $this->parent) {
            return $this->parent->getAbsoluteTitle().' / '.$path;
        }

        return $path;
    }

    public function getAclOwnerId(): string
    {
        return $this->getOwnerId() ?? '';
    }

    public function __toString()
    {
        return $this->getAbsoluteTitle() ?? $this->getId();
    }

    public function getKey(): ?string
    {
        return $this->key;
    }

    public function setKey(?string $key): void
    {
        $this->key = $key;
    }
}
