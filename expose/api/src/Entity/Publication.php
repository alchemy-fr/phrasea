<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Core\Annotation\ApiProperty;
use ApiPlatform\Core\Annotation\ApiResource;
use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * @ORM\Entity()
 * @ApiResource(
 *     normalizationContext={"groups"={"publication:read"}},
 *     itemOperations={
 *         "get"={"security"="is_granted('ROLE_ADMIN') or object.isEnabled()"},
 *     },
 *     collectionOperations={
 *         "post"={
 *         }
 *     }
 * )
 */
class Publication
{
    /**
     * @ApiProperty(identifier=true)
     * @Groups({"publication:read", "asset:read"})
     *
     * @var Uuid
     *
     * @ORM\Id
     * @ORM\Column(type="uuid", unique=true)
     */
    private $id;

    /**
     * @ApiProperty()
     *
     * @var string
     *
     * @ORM\Column(type="string", length=255)
     * @Groups({"publication:read"})
     */
    private $title;

    /**
     * @var PublicationAsset[]|Collection
     *
     * @ApiProperty(
     *     attributes={
     *         "swagger_context"={
     *             "$ref"="#/definitions/PublicationAsset",
     *         }
     *     }
     * )
     * @Groups({"publication:read"})
     * @ORM\OneToMany(targetEntity="PublicationAsset", mappedBy="publication")
     */
    private $assets;

    /**
     * @var bool
     *
     * @ApiProperty()
     * @ORM\Column(type="boolean")
     * @Groups({"publication:read"})
     */
    private $enabled = false;

    /**
     * @var string
     *
     * @ApiProperty()
     * @Groups({"publication:read"})
     * @ORM\Column(type="string", length=20)
     */
    private $layout;

    /**
     * @var string|null
     *
     * @ApiProperty()
     * @Groups({"publication:read"})
     * @ORM\Column(type="string", length=30, nullable=true)
     */
    private $theme;

    /**
     * @var DateTime|null
     *
     * @ApiProperty()
     * @Groups({"publication:read"})
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $beginsAt;

    /**
     * @var DateTime|null
     *
     * @ApiProperty()
     * @Groups({"publication:read"})
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $expiresAt;

    /**
     * @var DateTime
     *
     * @ORM\Column(type="datetime")
     * @Groups({"publication:read"})
     */
    private $createdAt;

    public function __construct()
    {
        $this->createdAt = new DateTime();
        $this->assets = new ArrayCollection();
        $this->id = Uuid::uuid4();
    }

    public function getId()
    {
        return $this->id->__toString();
    }

    /**
     * @return Asset[]
     */
    public function getAssets(): Collection
    {
        return $this->assets;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function getCreatedAt(): DateTime
    {
        return $this->createdAt;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): void
    {
        $this->enabled = $enabled;
    }

    public function addAsset(Asset $asset): void
    {
        $asset->getPublications()->add($this);
        $this->assets->add($asset);
    }

    public function removeAsset(Asset $asset): void
    {
        $asset->getPublications()->removeElement($this);
        $this->assets->removeElement($asset);
    }

    public function getLayout(): ?string
    {
        return $this->layout;
    }

    public function setLayout(string $layout): void
    {
        $this->layout = $layout;
    }

    public function getBeginsAt(): ?DateTime
    {
        return $this->beginsAt;
    }

    public function setBeginsAt(?DateTime $beginsAt): void
    {
        $this->beginsAt = $beginsAt;
    }

    public function getExpiresAt(): ?DateTime
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(?DateTime $expiresAt): void
    {
        $this->expiresAt = $expiresAt;
    }

    public function getTheme(): ?string
    {
        return $this->theme;
    }

    public function setTheme(?string $theme): void
    {
        $this->theme = $theme;
    }
}

