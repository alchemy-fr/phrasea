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
 * @ORM\Table(uniqueConstraints={@ORM\UniqueConstraint(name="unique_url", columns={"publication_id", "slug"})})
 * @ApiResource(
 *     normalizationContext=PublicationAsset::API_READ,
 *     iri="http://alchemy.fr/PublicationAsset",
 *     itemOperations={
 *         "get"={
 *              "security"="is_granted('publication:read_details', object.getPublication())"
 *         },
 *         "put"={
 *              "security"="is_granted('EDIT', object.getPublication())"
 *         },
 *         "delete"={
 *              "security"="is_granted('EDIT', object.getPublication())"
 *         }
 *     },
 *     collectionOperations={
 *         "post"={
 *              "security"="is_granted('publication:publish')"
 *         }
 *     }
 * )
 */
class PublicationAsset
{
    const GROUP_READ = 'pubasset:read';

    const API_READ = [
        'groups' => [self::GROUP_READ],
        'swagger_definition_name' => 'Read',
    ];

    /**
     * @ApiProperty(identifier=true)
     * @Groups({"publication:read", "pubasset:read"})
     *
     * @var Uuid
     *
     * @ORM\Id
     * @ORM\Column(type="uuid", unique=true)
     */
    protected $id;

    /**
     * @ApiProperty(
     *     attributes={
     *         "swagger_context"={
     *             "$ref"="#/definitions/Publication",
     *         }
     *     }
     * )
     * @Groups({"pubasset:read"})
     * @ORM\ManyToOne(targetEntity="Publication", inversedBy="assets")
     * @ORM\JoinColumn(nullable=false, onDelete="CASCADE")
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
     * @Groups({"publication:read", "pubasset:read"})
     * @ORM\ManyToOne(targetEntity="Asset", inversedBy="publications")
     * @ORM\JoinColumn(nullable=false, onDelete="CASCADE")
     */
    private ?Asset $asset = null;

    /**
     * Direct access to asset.
     *
     * @ApiProperty()
     * @Groups({"publication:read", "pubasset:read", "asset:read"})
     *
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    protected ?string $slug = null;

    /**
     * @ApiProperty()
     *
     * @ORM\Column(type="text", nullable=true)
     */
    private ?string $description = null;

    /**
     * @ORM\Column(type="datetime")
     * @ApiProperty()
     */
    private DateTime $createdAt;

    public function __construct()
    {
        $this->createdAt = new DateTime();
        $this->id = Uuid::uuid4();
    }

    public function getId()
    {
        return $this->id->__toString();
    }

    public function getPublication(): ?Publication
    {
        return $this->publication;
    }

    public function setPublication(Publication $publication): void
    {
        $this->publication = $publication;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    public function getAsset(): ?Asset
    {
        return $this->asset;
    }

    public function setAsset(Asset $asset): void
    {
        $this->asset = $asset;
    }

    public function getCreatedAt(): DateTime
    {
        return $this->createdAt;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(?string $slug): void
    {
        $this->slug = $slug;
    }
}
