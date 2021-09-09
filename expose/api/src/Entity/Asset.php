<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Core\Annotation\ApiProperty;
use ApiPlatform\Core\Annotation\ApiResource;
use ApiPlatform\Core\Annotation\ApiSubresource;
use App\Controller\DeleteAssetsAction;
use App\Controller\GetAssetWithSlugAction;
use App\Entity\Traits\ClientAnnotationsTrait;
use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * @ORM\Entity(repositoryClass="App\Repository\AssetRepository")
 * @ApiResource(
 *     normalizationContext=Asset::API_READ,
 *     itemOperations={
 *         "get"={},
 *         "delete"={
 *             "security"="is_granted('DELETE', object)"
 *         },
 *         "get_with_slug"={
 *              "controller"=GetAssetWithSlugAction::class,
 *              "method"="GET",
 *              "path"="/publications/{publicationSlug}/assets/{assetSlug}",
 *              "defaults"={
 *                   "_api_receive"=false
 *              },
 *          },
 *         "put"={
 *              "security"="is_granted('EDIT', object)"
 *         },
 *         "delete_by_asset_id"={
 *             "controller"=DeleteAssetsAction::class,
 *             "method"="DELETE",
 *             "path"="/assets/delete-by-asset-id/{assetId}",
 *             "openapi_context"={
 *                  "summary"="Delete all assets by the given assetId",
 *                  "description"="Delete all assets by the given assetId",
 *             },
 *             "read"=false,
 *         }
 *     },
 * )
 */
class Asset implements MediaInterface
{
    const GROUP_READ = 'asset:read';

    const API_READ = [
        'groups' => [self::GROUP_READ],
        'swagger_definition_name' => 'Read',
    ];

    use ClientAnnotationsTrait;

    /**
     * @ApiProperty(identifier=true)
     * @Groups({"asset:read", "publication:read"})
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
     * @Groups({"asset:read"})
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private ?string $assetId = null;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private ?string $path = null;

    /**
     * @Groups({"asset:read", "publication:read"})
     * @ORM\Column(type="bigint", options={"unsigned"=true})
     */
    private ?int $size = null;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     * @ApiProperty()
     * @Groups({"asset:read", "publication:read"})
     */
    private ?string $title = null;

    /**
     * @ORM\Column(type="text", nullable=true)
     * @ApiProperty()
     * @Groups({"asset:read", "publication:read"})
     */
    private ?string $description = null;

    /**
     * @ORM\Column(type="string", length=255)
     * @ApiProperty(iri="http://schema.org/name")
     * @Groups({"asset:read", "publication:read"})
     */
    private ?string $originalName = null;

    /**
     * @ORM\Column(type="string", length=255)
     * @ApiProperty()
     * @Groups({"asset:read", "publication:read", "publication:index"})
     */
    private ?string $mimeType = null;

    /**
     * @ApiProperty()
     * @ORM\Column(type="string", nullable=true)
     * @Groups({"publication:admin:read"})
     */
    private ?string $ownerId = null;

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
     * @ORM\OneToMany(targetEntity="App\Entity\PublicationAsset", mappedBy="asset")
     */
    private ?Collection $publications = null;

    /**
     * @var SubDefinition[]|Collection
     *
     * @ApiSubresource()
     * @Groups({"asset:read", "publication:read"})
     * @ORM\OneToMany(targetEntity="App\Entity\SubDefinition", mappedBy="asset", cascade={"remove"})
     */
    private ?Collection $subDefinitions = null;

    /**
     * Location latitude.
     *
     * @ApiProperty()
     * @ORM\Column(type="float", nullable=true)
     * @Groups({"asset:read", "publication:read"})
     */
    private ?float $lat = null;

    /**
     * Location longitude.
     *
     * @ApiProperty()
     * @ORM\Column(type="float", nullable=true)
     * @Groups({"asset:read", "publication:read"})
     */
    private ?float $lng = null;

    /**
     * @ApiProperty()
     * @ORM\Column(type="text", nullable=true)
     * @Groups({"asset:admin:read"})
     */
    private ?string $webVTT = null;

    /**
     * @ApiProperty(writable=false)
     * @Groups({"publication:read"})
     */
    private ?string $webVTTLink = null;

    /**
     * Location altitude.
     *
     * @ApiProperty()
     * @ORM\Column(type="float", nullable=true)
     * @Groups({"asset:read", "publication:read"})
     */
    private ?float $altitude = null;

    /**
     * @ORM\Column(type="datetime")
     * @ApiProperty(writable=false)
     * @Groups({"asset:read"})
     */
    private ?DateTime $createdAt = null;

    /**
     * @ApiProperty(writable=false)
     * @Groups({"publication:read"})
     */
    private ?string $url = null;

    /**
     * @ApiProperty(writable=false)
     * @Groups({"publication:read", "publication:index"})
     */
    private ?string $downloadUrl = null;

    /**
     * @ApiProperty(writable=false)
     * @Groups({"publication:read", "publication:index"})
     */
    private ?string $thumbUrl = null;

    /**
     * @ApiProperty(writable=false)
     * @Groups({"publication:read", "publication:index"})
     */
    private ?string $previewUrl = null;

    /**
     * @ApiProperty(writable=false)
     * @Groups({"asset:read"})
     */
    private ?string $uploadURL = null;

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
     * @return PublicationAsset[]|Collection
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

    public function getPreviewUrl(): ?string
    {
        return $this->previewUrl;
    }

    public function setPreviewUrl(?string $previewUrl): void
    {
        $this->previewUrl = $previewUrl;
    }

    public function getDownloadUrl(): ?string
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
        foreach ($this->getSubDefinitions() as $subDefinition) {
            if ($subDefinition->getName() === SubDefinition::PREVIEW) {
                return $subDefinition;
            }
        }

        return null;
    }

    public function setPreviewDefinition(?SubDefinition $previewDefinition): void
    {
        $previewDefinition->setName(SubDefinition::PREVIEW);
        $previewDefinition->setAsset($this);
    }

    public function getThumbnailDefinition(): ?SubDefinition
    {
        foreach ($this->getSubDefinitions() as $subDefinition) {
            if ($subDefinition->getName() === SubDefinition::THUMBNAIL) {
                return $subDefinition;
            }
        }

        return null;
    }

    public function setThumbnailDefinition(?SubDefinition $thumbnailDefinition): void
    {
        $thumbnailDefinition->setName(SubDefinition::THUMBNAIL);
        $thumbnailDefinition->setAsset($this);
    }

    public function getOwnerId(): ?string
    {
        return $this->ownerId;
    }

    public function setOwnerId(?string $ownerId): void
    {
        $this->ownerId = $ownerId;
    }

    public function getLat(): ?float
    {
        return $this->lat;
    }

    public function setLat(?float $lat): void
    {
        $this->lat = $lat;
    }

    public function getLng(): ?float
    {
        return $this->lng;
    }

    public function setLng(?float $lng): void
    {
        $this->lng = $lng;
    }

    public function getAltitude(): ?float
    {
        return $this->altitude;
    }

    public function setAltitude(?float $altitude): void
    {
        $this->altitude = $altitude;
    }

    public function getGeoPoint(): ?string
    {
        return $this->lat ? sprintf('[%.4f, %.4f]', $this->lat, $this->lng) : null;
    }

    public function getWebVTT(): ?string
    {
        return $this->webVTT;
    }

    public function setWebVTT(?string $webVTT): void
    {
        $this->webVTT = $webVTT;
    }

    public function getWebVTTLink(): ?string
    {
        return $this->webVTTLink;
    }

    public function setWebVTTLink(?string $webVTTLink): void
    {
        $this->webVTTLink = $webVTTLink;
    }

    public function getUploadURL(): ?string
    {
        return $this->uploadURL;
    }

    public function setUploadURL(?string $uploadURL): void
    {
        $this->uploadURL = $uploadURL;
    }

    public function __toString()
    {
        return $this->getId().($this->title ? '-'.$this->title : '');
    }
}
