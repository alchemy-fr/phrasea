<?php

declare(strict_types=1);

namespace App\Entity\Core;

use ApiPlatform\Core\Annotation\ApiResource;
use App\Entity\AbstractUuidEntity;
use App\Entity\Traits\CreatedAtTrait;
use App\Entity\Traits\UpdatedAtTrait;
use App\Entity\TranslationInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection as DoctrineCollection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ORM\Entity()
 * @ApiResource()
 */
class Collection extends AbstractUuidEntity implements TranslationInterface
{
    use CreatedAtTrait;
    use UpdatedAtTrait;

    /**
     * @Assert\Count(min=1)
     * @Assert\All({
     *     @Assert\NotBlank,
     *     @Assert\Length(max=100)
     * })
     * @ORM\Column(type="json", nullable=false)
     */
    private array $titleTranslations = [];

    /**
     * @ORM\Column(type="string", length=255)
     */
    private ?string $ownerId = null;

    /**
     * @ORM\Column(type="boolean", nullable=false)
     */
    private bool $public = false;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Core\Workspace")
     * @ORM\JoinColumn(nullable=false)
     */
    private ?Workspace $workspace = null;

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

    public function getTitle(string $locale): string
    {
        if (!empty($this->titleTranslations[$locale])) {
            return $this->titleTranslations[$locale];
        }

        $fallback = reset($this->titleTranslations);
        if (false === $fallback) {
            return 'Unamed';
        }

        return $fallback;
    }

    public function setTitle(string $locale, string $title): void
    {
        $this->titleTranslations[$locale] = $title;
    }

    public function getTitleEN(): string
    {
        return $this->getTitle('en');
    }

    public function setTitleEN(string $title): void
    {
        $this->setTitle('en', $title);
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

    public function getWorkspace(): ?Workspace
    {
        return $this->workspace;
    }

    public function setWorkspace(?Workspace $workspace): void
    {
        $this->workspace = $workspace;
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
