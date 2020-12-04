<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Core\Annotation\ApiResource;
use App\Entity\Traits\CreatedAtTrait;
use App\Entity\Traits\UpdatedAtTrait;
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
     * @ORM\ManyToOne(targetEntity="App\Entity\Workspace", inversedBy="children")
     * @ORM\JoinColumn(nullable=true)
     */
    private ?Workspace $workspace = null;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Collection", inversedBy="children")
     * @ORM\JoinColumn(nullable=true)
     */
    private ?self $parent = null;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Collection", mappedBy="parent", cascade={"remove"})
     * @ORM\JoinColumn(nullable=true)
     */
    private ?DoctrineCollection $children = null;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\CollectionAsset", mappedBy="collection", cascade={"remove"})
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

    public function getParent(): self
    {
        return $this->parent;
    }

    public function setParent(self $parent): void
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
}
