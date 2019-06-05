<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Core\Annotation\ApiProperty;
use ApiPlatform\Core\Annotation\ApiResource;
use App\Controller\CreateAssetAction;
use App\Controller\DownloadAssetAction;
use DateTime;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * @ORM\Entity(repositoryClass="App\Entity\AssetRepository")
 * @ApiResource(
 *     iri="http://schema.org/MediaObject",
 *     normalizationContext={
 *         "groups"={"asset_read"},
 *     },
 *     itemOperations={
 *         "get",
 *         "download"={
 *             "method"="GET",
 *             "path"="/assets/{id}/download",
 *             "controller"=DownloadAssetAction::class,
 *         }
 *     },
 *     collectionOperations={
 *         "post"={
 *             "controller"=CreateAssetAction::class,
 *             "defaults"={
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
     * @var array|null
     * @Groups("asset_read")
     * @ORM\Column(type="json_array", nullable=true)
     */
    private $formData;

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
     * @var DateTime
     *
     * @ORM\Column(type="datetime")
     * @ApiProperty()
     * @Groups("asset_read")
     */
    private $createdAt;

    public function __construct(?string $id = null)
    {
        $this->createdAt = new DateTime();
        $this->id = null !== $id ? Uuid::fromString($id) : Uuid::uuid4();
    }

    public function getId()
    {
        return $this->id->__toString();
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

    public function getFormData(): ?array
    {
        return $this->formData;
    }

    public function setFormData(?array $formData): void
    {
        $this->formData = $formData;
    }

    public function getCreatedAt(): DateTime
    {
        return $this->createdAt;
    }
}
