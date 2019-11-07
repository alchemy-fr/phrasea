<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Core\Annotation\ApiProperty;
use ApiPlatform\Core\Annotation\ApiResource;
use ApiPlatform\Core\Annotation\ApiSubresource;
use App\Controller\CreateAssetAction;
use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * @ORM\Entity()
 * @ApiResource(
 *     normalizationContext=Asset::API_READ,
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
 *                 "summary"="Upload asset",
 *                 "consumes"={
 *                     "multipart/form-data",
 *                 },
 *                 "parameters"={
 *                     {
 *                         "in"="formData",
 *                         "name"="file",
 *                         "type"="file",
 *                         "required"=true,
 *                         "description"="The file to upload",
 *                     },
 *                     {
 *                         "in"="formData",
 *                         "name"="publication_id",
 *                         "type"="string",
 *                         "description"="Attach asset to a publication (optional)",
 *                     },
*                      {
*                          "in"="body",
*                          "name"="asset",
*                      },
 *                 }
 *             },
 *         }
 *     }
 * )
 */
class Asset implements MediaInterface
{
    const API_READ = [
        'groups' => ['asset:read'],
        'swagger_definition_name' => 'Read',
    ];

    /**
     * @ApiProperty(identifier=true)
     * @Groups({"asset:read", "publication:read"})
     *
     * @var Uuid
     *
     * @ORM\Id
     * @ORM\Column(type="uuid", unique=true)
     */
    protected $id;

    /**
     * @ApiProperty()
     *
     * @var string|null
     *
     * @Groups({"asset:read"})
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
     * @Groups({"asset:read", "publication:read"})
     * @ORM\Column(type="integer")
     */
    private $size;

    /**
     * @var string|null
     *
     * @ORM\Column(type="string", length=255, nullable=true)
     * @ApiProperty()
     * @Groups({"asset:read", "publication:read"})
     */
    private $title;

    /**
     * @var string|null
     *
     * @ORM\Column(type="text", nullable=true)
     * @ApiProperty()
     * @Groups({"asset:read", "publication:read"})
     */
    private $description;

    /**
     * @var string
     *
     * @ORM\Column(type="string", length=255)
     * @ApiProperty(iri="http://schema.org/name")
     * @Groups({"asset:read", "publication:read"})
     */
    private $originalName;

    /**
     * @var string
     *
     * @ORM\Column(type="string", length=255)
     * @ApiProperty()
     * @Groups({"asset:read", "publication:read", "publication:list"})
     */
    private $mimeType;

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
     * @Groups({"asset:read"})
     * @ORM\OneToMany(targetEntity="App\Entity\PublicationAsset", mappedBy="asset")
     */
    private $publications;

    /**
     * @var SubDefinition[]|Collection
     *
     * @ApiSubresource()
     * @Groups({"asset:read", "publication:read"})
     * @ORM\OneToMany(targetEntity="App\Entity\SubDefinition", mappedBy="asset")
     */
    private $subDefinitions;

    /**
     * @var SubDefinition|null
     *
     * @ORM\ManyToOne(targetEntity="App\Entity\SubDefinition")
     */
    private $previewDefinition;

    /**
     * @var SubDefinition|null
     *
     * @ORM\ManyToOne(targetEntity="App\Entity\SubDefinition")
     */
    private $thumbnailDefinition;

    /**
     * @var DateTime
     *
     * @ORM\Column(type="datetime")
     * @ApiProperty()
     * @Groups({"asset:read"})
     */
    private $createdAt;

    /**
     * @ApiProperty()
     * @Groups({"asset:read", "publication:read"})
     * @var string
     */
    private $url;

    /**
     * @ApiProperty()
     * @Groups({"asset:read", "publication:read", "publication:list"})
     * @var string
     */
    private $downloadUrl;

    /**
     * @ApiProperty()
     * @Groups({"asset:read", "publication:read", "publication:list"})
     * @var string
     */
    private $thumbUrl;

    public function __construct()
    {
        $this->createdAt = new DateTime();
        $this->publications = new ArrayCollection();
        $this->subDefinitions = new ArrayCollection();
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

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): void
    {
        $this->title = $title;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
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

    /**
     * @return Publication[]|Collection
     */
    public function getPublications(): Collection
    {
        return $this->publications;
    }

    public function addPublication(PublicationAsset $publication): void
    {
        $this->publications->add($publication);
    }

    public function removePublication(PublicationAsset $publication): void
    {
        $this->publications->removeElement($publication);
    }

    public function getCreatedAt(): DateTime
    {
        return $this->createdAt;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function setUrl(?string $url): void
    {
        $this->url = $url;
    }

    public function getThumbUrl(): ?string
    {
        return $this->thumbUrl;
    }

    public function setThumbUrl(?string $thumbUrl): void
    {
        $this->thumbUrl = $thumbUrl;
    }

    public function getDownloadUrl(): string
    {
        return $this->downloadUrl;
    }

    public function setDownloadUrl(string $downloadUrl): void
    {
        $this->downloadUrl = $downloadUrl;
    }

    /**
     * @return SubDefinition[]|Collection
     */
    public function getSubDefinitions(): Collection
    {
        return $this->subDefinitions;
    }

    public function getPreviewDefinition(): ?SubDefinition
    {
        return $this->previewDefinition;
    }

    public function setPreviewDefinition(?SubDefinition $previewDefinition): void
    {
        $this->previewDefinition = $previewDefinition;
    }

    public function getThumbnailDefinition(): ?SubDefinition
    {
        return $this->thumbnailDefinition;
    }

    public function setThumbnailDefinition(?SubDefinition $thumbnailDefinition): void
    {
        $this->thumbnailDefinition = $thumbnailDefinition;
    }
}
