<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use App\Controller\CreateAssetAction;
use App\Controller\DeleteAssetsAction;
use App\Controller\GetAssetWithSlugAction;
use App\Entity\Traits\ClientAnnotationsTrait;
use App\Repository\AssetRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Doctrine\UuidType;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Table]
#[ORM\Index(columns: ['asset_id'], name: 'assetId')]
#[ORM\Entity(repositoryClass: AssetRepository::class)]
#[ApiResource(
    operations: [
        new Get(
            // No security here because normalizer will hide data if publication is not authorized
            name: self::GET_ASSET_ROUTE_NAME,
        ),
        new Delete(
            security: 'is_granted("DELETE", object)'
        ),
        new Get(
            uriTemplate: '/publications/{publicationSlug}/assets/{assetSlug}',
            defaults: ['_api_receive' => false],
            controller: GetAssetWithSlugAction::class
        ),
        new Put(security: 'is_granted("EDIT", object)'),
        new Delete(
            uriTemplate: '/assets/delete-by-asset-id/{assetId}',
            uriVariables: [],
            controller: DeleteAssetsAction::class,
            openapiContext: [
                'summary' => 'Delete all assets by the given assetId',
                'description' => 'Delete all assets by the given assetId',
            ],
            read: false,
        ),
        new Post(
            controller: CreateAssetAction::class,
            openapiContext: [
                'requestBody' => [
                    'content' => [
                        'application/json' => [
                            'examples' => [
                                'Multipart upload' => [
                                    'value' => [
                                        'multipart' => [
                                            'uploadId' => '123-456',
                                            'parts' => [
                                                [
                                                    'ETag' => '812d692260ab94dd85a5aa7a6caef68d',
                                                    'PartNumber' => 1,
                                                ],
                                                [
                                                    'ETag' => '4dd85a5aa7a6caef68d812d692260ab9',
                                                    'PartNumber' => 2,
                                                ],
                                            ],
                                        ],
                                        'title' => 'My first asset',
                                        'description' => 'My asset was uploaded to S3 first, then created in expose.',
                                        'lat' => 48.8,
                                        'lng' => 2.42,
                                    ],
                                ],
                                'Create asset, then upload to S3' => [
                                    'value' => [
                                        'upload' => [
                                            'name' => 'foo.jpg',
                                            'type' => 'image/jpeg',
                                            'size' => 42,
                                        ],
                                        'title' => 'My first asset',
                                        'description' => 'Here we create asset with file info, then Expose returns a signed upload URL to push the data.',
                                    ],
                                ],
                            ],
                            'schema' => [
                                'anyOf' => [
                                    ['$ref' => '#/components/schemas/Asset'],
                                    [
                                        'oneOf' => [
                                            [
                                                'type' => 'object',
                                                'properties' => [
                                                    'multipart' => [
                                                        'type' => 'object',
                                                        'properties' => [
                                                            'uploadId' => ['type' => 'string'],
                                                            'parts' => [
                                                                'type' => 'array',
                                                                'items' => [
                                                                    'type' => 'object',
                                                                    'properties' => [
                                                                        'ETag' => ['type' => 'string'],
                                                                        'PartNumber' => ['type' => 'integer'],
                                                                    ],
                                                                ],
                                                            ],
                                                        ],
                                                    ],
                                                ],
                                            ],
                                            [
                                                'type' => 'object',
                                                'properties' => [
                                                    'upload' => [
                                                        'type' => 'object',
                                                        'properties' => [
                                                            'name' => ['type' => 'string'],
                                                            'type' => ['type' => 'string'],
                                                            'size' => ['type' => 'integer'],
                                                        ],
                                                    ],
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                        'multipart/form-data' => [
                            'schema' => [
                                'anyOf' => [
                                    ['$ref' => '#/components/schemas/Asset'],
                                    [
                                        'type' => 'object',
                                        'properties' => [
                                            'file' => [
                                                'type' => 'string',
                                                'format' => 'binary',
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            deserialize: false
        ),
        new GetCollection(),
    ],
    normalizationContext: [
        'groups' => [self::GROUP_READ],
    ],
)]
#[ApiResource(
    uriTemplate: '/publications/{id}/assets.{_format}',
    shortName: 'asset',
    operations: [
        new GetCollection(),
        new Post(
            controller: CreateAssetAction::class,
            openapiContext: [
                'requestBody' => [
                    'content' => [
                        'application/json' => [
                            'examples' => [
                                'Multipart upload' => [
                                    'value' => [
                                        'multipart' => [
                                            'uploadId' => '123-456',
                                            'parts' => [
                                                [
                                                    'ETag' => '812d692260ab94dd85a5aa7a6caef68d',
                                                    'PartNumber' => 1,
                                                ],
                                                [
                                                    'ETag' => '4dd85a5aa7a6caef68d812d692260ab9',
                                                    'PartNumber' => 2,
                                                ],
                                            ],
                                        ],
                                        'title' => 'My first asset',
                                        'description' => 'My asset was uploaded to S3 first, then created in expose.',
                                        'lat' => 48.8,
                                        'lng' => 2.42,
                                    ],
                                ],
                                'Create asset, then upload to S3' => [
                                    'value' => [
                                        'upload' => [
                                            'name' => 'foo.jpg',
                                            'type' => 'image/jpeg',
                                            'size' => 42,
                                        ],
                                        'title' => 'My first asset',
                                        'description' => 'Here we create asset with file info, then Expose returns a signed upload URL to push the data.',
                                    ],
                                ],
                            ],
                            'schema' => [
                                'anyOf' => [
                                    ['$ref' => '#/components/schemas/Asset'],
                                    [
                                        'oneOf' => [
                                            [
                                                'type' => 'object',
                                                'properties' => [
                                                    'multipart' => [
                                                        'type' => 'object',
                                                        'properties' => [
                                                            'uploadId' => ['type' => 'string'],
                                                            'parts' => [
                                                                'type' => 'array',
                                                                'items' => [
                                                                    'type' => 'object',
                                                                    'properties' => [
                                                                        'ETag' => ['type' => 'string'],
                                                                        'PartNumber' => ['type' => 'integer'],
                                                                    ],
                                                                ],
                                                            ],
                                                        ],
                                                    ],
                                                ],
                                            ],
                                            [
                                                'type' => 'object',
                                                'properties' => [
                                                    'upload' => [
                                                        'type' => 'object',
                                                        'properties' => [
                                                            'name' => ['type' => 'string'],
                                                            'type' => ['type' => 'string'],
                                                            'size' => ['type' => 'integer'],
                                                        ],
                                                    ],
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                        'multipart/form-data' => [
                            'schema' => [
                                'anyOf' => [
                                    ['$ref' => '#/components/schemas/Asset'],
                                    [
                                        'type' => 'object',
                                        'properties' => [
                                            'file' => [
                                                'type' => 'string',
                                                'format' => 'binary',
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            deserialize: false
        ),
    ],
    uriVariables: [
        'id' => new Link(toProperty: 'publication', fromClass: Publication::class, identifiers: ['id']),
    ],

)]
#[ApiFilter(filterClass: OrderFilter::class, properties: ['title', 'position' => 'ASC', 'createdAt' => 'ASC'], arguments: ['orderParameterName' => 'order'])]
class Asset implements MediaInterface, \Stringable
{
    use ClientAnnotationsTrait;

    final public const string GET_ASSET_ROUTE_NAME = 'get_asset';
    final public const string GROUP_READ = 'asset:r';
    final public const string GROUP_ADMIN_READ = 'admin:'.self::GROUP_READ;

    /**
     * @var Uuid
     */
    #[ApiProperty(identifier: true)]
    #[Groups(['_', self::GROUP_READ, Publication::GROUP_READ])]
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private UuidInterface $id;

    #[Groups([Publication::GROUP_READ, self::GROUP_READ])]
    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $assetId = null;

    #[Groups([Publication::GROUP_READ, self::GROUP_READ])]
    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    #[Assert\Length(max: 100)]
    private ?string $trackingId = null;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private ?string $path = null;

    #[Groups([self::GROUP_READ, Publication::GROUP_READ])]
    #[ORM\Column(type: Types::BIGINT, options: ['unsigned' => true])]
    private ?string $size = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    #[Groups([self::GROUP_READ, Publication::GROUP_READ])]
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups([self::GROUP_READ, Publication::GROUP_READ])]
    private ?string $description = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Groups([self::GROUP_READ, Publication::GROUP_READ])]
    private ?array $translations = null;

    #[ORM\Column(type: Types::STRING, length: 255)]
    #[Groups([self::GROUP_READ, Publication::GROUP_READ])]
    private ?string $originalName = null;

    #[ORM\Column(type: Types::STRING, length: 255)]
    #[Groups([self::GROUP_READ, Publication::GROUP_READ, Publication::GROUP_INDEX])]
    private ?string $mimeType = null;

    #[ORM\Column(type: Types::STRING, nullable: true)]
    #[Groups([Publication::GROUP_ADMIN_READ])]
    private ?string $ownerId = null;

    /**
     * Direct access to asset.
     */
    #[Groups([Publication::GROUP_READ, self::GROUP_READ])]
    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    protected ?string $slug = null;

    #[ORM\Column(type: Types::SMALLINT, options: ['default' => 0])]
    protected int $position = 0;

    #[ORM\ManyToOne(targetEntity: Publication::class, inversedBy: 'assets')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['_', self::GROUP_READ])]
    private ?Publication $publication = null;

    /**
     * @var SubDefinition[]|Collection
     */
    /**
     * @var SubDefinition[]|Collection
     */
    #[Groups([self::GROUP_READ, Publication::GROUP_READ])]
    #[ORM\OneToMany(mappedBy: 'asset', targetEntity: SubDefinition::class, cascade: ['remove'])]
    private ?Collection $subDefinitions = null;

    /**
     * Location latitude.
     */
    #[ORM\Column(type: Types::FLOAT, nullable: true)]
    #[Groups([self::GROUP_READ, Publication::GROUP_READ])]
    private ?float $lat = null;

    /**
     * Location longitude.
     */
    #[ORM\Column(type: Types::FLOAT, nullable: true)]
    #[Groups([self::GROUP_READ, Publication::GROUP_READ])]
    private ?float $lng = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Groups([Asset::GROUP_ADMIN_READ])]
    #[Assert\All([
        new Assert\Collection(
            fields: [
                'label' => [
                    new Assert\NotBlank(),
                    new Assert\Length(min: 2, max: 100),
                ],
                'locale' => [
                    new Assert\Optional(),
                    new Assert\Length(min: 2, max: 10),
                ],
                'id' => [
                    new Assert\NotBlank(),
                    new Assert\Length(min: 10, max: 36),
                ],
                'content' => [
                    new Assert\NotBlank(),
                ],
                'kind' => [
                    new Assert\Optional(),
                ],
            ],
        ),
    ])]
    private ?array $webVTT = null;

    #[ApiProperty(writable: false)]
    #[Groups([self::GROUP_READ, Publication::GROUP_READ])]
    private ?array $webVTTLinks = null;

    /**
     * Location altitude.
     */
    #[ORM\Column(type: Types::FLOAT, nullable: true)]
    #[Groups([self::GROUP_READ, Publication::GROUP_READ])]
    private ?float $altitude = null;

    #[ApiProperty(writable: false)]
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups([self::GROUP_READ])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ApiProperty(writable: false)]
    #[Groups([self::GROUP_READ, Publication::GROUP_READ])]
    private ?string $url = null;

    #[ApiProperty(writable: false)]
    #[Groups([self::GROUP_READ, Publication::GROUP_READ, Publication::GROUP_INDEX])]
    private ?string $downloadUrl = null;

    #[ApiProperty(writable: false)]
    #[Groups([self::GROUP_READ, Publication::GROUP_READ, Publication::GROUP_INDEX])]
    private ?string $thumbUrl = null;

    #[ApiProperty(writable: false)]
    #[Groups([self::GROUP_READ, Publication::GROUP_READ, Publication::GROUP_INDEX])]
    private ?string $previewUrl = null;

    #[ApiProperty(writable: false)]
    #[Groups([self::GROUP_READ, Publication::GROUP_READ, Publication::GROUP_INDEX])]
    private ?string $posterUrl = null;

    #[ApiProperty(writable: false)]
    #[Groups([self::GROUP_READ])]
    private ?string $uploadURL = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
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
        return (int) $this->size;
    }

    public function setSize($size): void
    {
        $this->size = (string) $size;
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

    public function setPublication(?Publication $publication): void
    {
        $this->publication = $publication;
    }

    public function getCreatedAt(): \DateTimeImmutable
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

    public function getPosterUrl(): ?string
    {
        return $this->posterUrl;
    }

    public function setPosterUrl(?string $posterUrl): void
    {
        $this->posterUrl = $posterUrl;
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
            if (SubDefinition::PREVIEW === $subDefinition->getName()) {
                return $subDefinition;
            }
        }

        return null;
    }

    public function getPosterDefinition(): ?SubDefinition
    {
        foreach ($this->getSubDefinitions() as $subDefinition) {
            if (SubDefinition::POSTER === $subDefinition->getName()) {
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

    public function setPosterDefinition(?SubDefinition $posterDefinition): void
    {
        $posterDefinition->setName(SubDefinition::POSTER);
        $posterDefinition->setAsset($this);
    }

    public function getThumbnailDefinition(): ?SubDefinition
    {
        foreach ($this->getSubDefinitions() as $subDefinition) {
            if (SubDefinition::THUMBNAIL === $subDefinition->getName()) {
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

    public function getWebVTT(): ?array
    {
        if (null === $this->webVTT) {
            return null;
        }

        return array_values($this->webVTT);
    }

    public function getWebVTTById(string $id): ?array
    {
        return $this->webVTT[$id] ?? null;
    }

    public function setWebVTT(?array $webVTT): void
    {
        $newValues = array_map(function (array $wv): array {
            if (!isset($wv['id'])) {
                $wv['id'] = Uuid::uuid4()->toString();
            }

            return $wv;
        }, $webVTT);

        $this->webVTT = [];
        foreach ($newValues as $v) {
            $this->webVTT[$v['id']] = $v;
        }
    }

    public function getWebVTTLinks(): ?array
    {
        return $this->webVTTLinks;
    }

    public function setWebVTTLinks(?array $webVTTLinks): void
    {
        $this->webVTTLinks = $webVTTLinks;
    }

    public function getUploadURL(): ?string
    {
        return $this->uploadURL;
    }

    public function setUploadURL(?string $uploadURL): void
    {
        $this->uploadURL = $uploadURL;
    }

    public function __toString(): string
    {
        return $this->getId().($this->title ? '-'.$this->title : '');
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): void
    {
        $this->position = $position;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(?string $slug): void
    {
        $this->slug = $slug;
    }

    public function getTranslations(): ?array
    {
        return $this->translations;
    }

    public function setTranslations(?array $translations): void
    {
        $this->translations = $translations;
    }

    public function getTrackingId(): ?string
    {
        return $this->trackingId;
    }

    public function setTrackingId(?string $trackingId): void
    {
        $this->trackingId = $trackingId;
    }
}
