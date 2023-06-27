<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Core\Annotation\ApiProperty;
use ApiPlatform\Core\Annotation\ApiResource;
use DateTime;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * @ORM\Entity()
 *
 * @ApiResource(
 *     itemOperations={
 *         "get"={
 *              "security"="is_granted('READ', object)"
 *          },
 *         "put"={
 *              "security"="is_granted('EDIT', object)"
 *         },
 *         "delete"={
 *              "security"="is_granted('DELETE', object)"
 *         },
 *     },
 *     collectionOperations={
 *         "get"={
 *              "security"="is_granted('download_request:list')",
 *          }
 *     }
 * )
 */
class DownloadRequest
{
    /**
     * @ApiProperty(identifier=true)
     *
     * @Groups({"publication:index", "publication:index", "publication:read", "asset:read"})
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
     * @ORM\Column(type="string", length=255)
     */
    private ?string $email = null;

    /**
     * @ORM\Column(type="string", length=5, nullable=true)
     */
    protected ?string $locale = null;

    /**
     * @ApiProperty(
     *     attributes={
     *         "swagger_context"={
     *             "$ref"="#/definitions/Publication",
     *         }
     *     }
     * )
     *
     * @ORM\ManyToOne(targetEntity="Publication")
     * @ORM\JoinColumn(onDelete="CASCADE")
     */
    private ?Publication $publication = null;

    /**
     * @ApiProperty(
     *     attributes={
     *         "swagger_context"={
     *             "$ref"="#/definitions/Asset",
     *         }
     *     }
     * )
     *
     * @ORM\ManyToOne(targetEntity="Asset")
     * @ORM\JoinColumn(onDelete="SET NULL")
     */
    private ?Asset $asset = null;

    /**
     * @ApiProperty(
     *     attributes={
     *         "swagger_context"={
     *             "$ref"="#/definitions/SubDefinition",
     *         }
     *     }
     * )
     *
     * @ORM\ManyToOne(targetEntity="SubDefinition")
     * @ORM\JoinColumn(onDelete="SET NULL")
     */
    private ?SubDefinition $subDefinition = null;

    /**
     * @ORM\Column(type="datetime")
     */
    private \DateTime $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->id = Uuid::uuid4();
    }

    public function getId(): string
    {
        return $this->id->__toString();
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): void
    {
        $this->email = $email;
    }

    public function getPublication(): ?Publication
    {
        return $this->publication;
    }

    public function setPublication(Publication $publication): void
    {
        $this->publication = $publication;
    }

    public function getAsset(): ?Asset
    {
        return $this->asset;
    }

    public function setAsset(?Asset $asset): void
    {
        $this->asset = $asset;
    }

    public function getCreatedAt(): \DateTime
    {
        return $this->createdAt;
    }

    public function getLocale(): string
    {
        return $this->locale ?? 'en';
    }

    public function setLocale(?string $locale): void
    {
        $this->locale = $locale;
    }

    public function getSubDefinition(): ?SubDefinition
    {
        return $this->subDefinition;
    }

    public function setSubDefinition(?SubDefinition $subDefinition): void
    {
        $this->subDefinition = $subDefinition;
    }
}
