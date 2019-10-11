<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Core\Annotation\ApiProperty;
use ApiPlatform\Core\Annotation\ApiResource;
use App\Controller\CreateAssetAction;
use DateTime;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * @ORM\Entity()
 * @ORM\Table(uniqueConstraints={@ORM\UniqueConstraint(name="unique_direct_url", columns={"publication_id", "direct_url_path"})})
 * @ApiResource(
 *     iri="http://schema.org/MediaObject",
 *     normalizationContext={
 *         "groups"={"asset_read"},
 *     },
 *     itemOperations={
 *         "get"={"access_control"="is_granted('read_meta', object)"},
 *     },
 *     collectionOperations={
 *         "post"={
 *             "controller"=CreateAssetAction::class,
 *             "defaults"={
 *                  "_api_receive"=false
 *             },
 *             "validation_groups"={"Default", "asset_create"},
 *             "swagger_context"={
 *                 "consumes"={
 *                     "multipart/form-data",
 *                 },
 *                 "parameters"={
 *                     {
 *                         "in"="formData",
 *                         "name"="file",
 *                         "type"="file",
 *                         "description"="The file to upload",
 *                     },
 *                 },
 *             },
 *         }
 *     }
 * )
 */
class Asset
{
    /**
     * @ApiProperty(identifier=true)
     * @Groups("asset_read")
     *
     * @var Uuid
     *
     * @ORM\Id
     * @ORM\Column(type="uuid", unique=true)
     */
    protected $id;

    /**
     * @ApiProperty()
     * @Groups("asset_read")
     *
     * @var string|null
     *
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    protected $assetId;

    /**
     * @var string
     * @ORM\Column(type="string", length=255)
     */
    private $path;

    /**
     * @var int
     * @Groups("asset_read")
     * @ORM\Column(type="integer")
     */
    private $size;

    /**
     * @var string
     *
     * @ORM\Column(type="string", length=255)
     * @ApiProperty(iri="http://schema.org/name")
     * @Groups("asset_read")
     */
    private $originalName;

    /**
     * @var string
     *
     * @ORM\Column(type="string", length=255)
     * @ApiProperty()
     * @Groups("asset_read")
     */
    private $mimeType;

    /**
     * @var Publication
     *
     * @ORM\ManyToOne(targetEntity="App\Entity\Publication", inversedBy="assets")
     */
    private $publication;

    /**
     * Direct access to asset
     *
     * @ApiProperty()
     * @Groups("asset_read")
     *
     * @var string|null
     *
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    protected $directUrlPath;

    /**
     * @var DateTime
     *
     * @ORM\Column(type="datetime")
     * @ApiProperty()
     * @Groups("asset_read")
     */
    private $createdAt;

    public function __construct()
    {
        $this->createdAt = new DateTime();
        $this->id = Uuid::uuid4();
    }

    public function getId()
    {
        return $this->id->__toString();
    }

    public function getAssetId(): ?string
    {
        return $this->assetId;
    }

    public function setAssetId(?string $assetId): void
    {
        $this->assetId = $assetId;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function setPath(string $path): void
    {
        $this->path = $path;
    }

    public function getSize(): int
    {
        return $this->size;
    }

    public function setSize(int $size): void
    {
        $this->size = $size;
    }

    public function getOriginalName(): string
    {
        return $this->originalName;
    }

    public function setOriginalName(string $originalName): void
    {
        $this->originalName = $originalName;
    }

    public function getMimeType(): string
    {
        return $this->mimeType;
    }

    public function setMimeType(string $mimeType): void
    {
        $this->mimeType = $mimeType;
    }

    public function getPublication(): Publication
    {
        return $this->publication;
    }

    public function setPublication(Publication $publication): void
    {
        $this->publication = $publication;
    }

    public function getCreatedAt(): DateTime
    {
        return $this->createdAt;
    }

    public function getDirectUrlPath(): ?string
    {
        return $this->directUrlPath;
    }

    public function setDirectUrlPath(?string $directUrlPath): void
    {
        $this->directUrlPath = $directUrlPath;
    }
}
