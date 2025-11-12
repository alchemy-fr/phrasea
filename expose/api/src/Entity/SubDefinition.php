<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Post;
use App\Controller\CreateSubDefinitionAction;
use App\Repository\SubDefinitionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Doctrine\UuidType;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Table]
#[ORM\UniqueConstraint(name: 'uniq_asset_type', columns: ['asset_id', 'name'])]
#[ORM\Entity(repositoryClass: SubDefinitionRepository::class)]
#[ApiResource(
    shortName: 'sub-definition',
    operations: [
        new Get(),
        new Delete(security: 'is_granted("DELETE", object)'),
        new Post(
            defaults: ['_api_receive' => false],
            controller: CreateSubDefinitionAction::class,
        ),
        new GetCollection(),
    ],
    normalizationContext: [
        'groups' => [self::GROUP_READ],
    ])]
#[ApiResource(
    uriTemplate: '/assets/{id}/sub-definitions.{_format}',
    shortName: 'sub-definition',
    operations: [
        new GetCollection(),
        new Post(
            defaults: ['_api_receive' => false],
            controller: CreateSubDefinitionAction::class,
            openapiContext: [
                'requestBody' => [
                    'content' => [
                        'application/json' => [
                            'examples' => [
                                'Multipart upload' => [
                                    'value' => [
                                        'asset_id' => '031657ca-532f-4460-963f-45ebf1c17c8c',
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
                                        'description' => 'My sub definition was uploaded to S3 first, then I created sub def in expose.',
                                    ],
                                ],
                                'Create asset, then upload to S3' => [
                                    'value' => [
                                        'asset_id' => '031657ca-532f-4460-963f-45ebf1c17c8c',
                                        'upload' => [
                                            'name' => 'sub-def-foo.jpg',
                                            'type' => 'image/jpeg',
                                            'size' => 42,
                                        ],
                                        'title' => 'My first sub definition',
                                        'description' => 'Here we create sub def with file info, then Expose returns a signed upload URL to push the data.',
                                    ],
                                ],
                            ],
                            'schema' => [
                                'anyOf' => [
                                    ['$ref' => '#/components/schemas/sub-definition'],
                                    [
                                        'type' => 'object',
                                        'properties' => [
                                            'asset_id' => [
                                                'type' => 'string',
                                                'required' => true,
                                            ],
                                        ],
                                    ],
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
                                    ['$ref' => '#/components/schemas/sub-definition'],
                                    [
                                        'type' => 'object',
                                        'properties' => [
                                            'asset_id' => [
                                                'type' => 'string',
                                                'required' => true,
                                            ],
                                        ],
                                    ],
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
            ]
        ),
    ],
    uriVariables: [
        'id' => new Link(
            toProperty: 'asset',
            fromClass: Asset::class,
        ),
    ],
    normalizationContext: [
        'groups' => [self::GROUP_READ],
    ],
)]
class SubDefinition implements MediaInterface
{
    final public const string THUMBNAIL = 'thumbnail';
    final public const string PREVIEW = 'preview';
    final public const string POSTER = 'poster';
    final public const string GROUP_READ = 'subdef:read';

    /**
     * @var Uuid
     */
    #[ApiProperty(identifier: true)]
    #[Groups([Asset::GROUP_READ, Publication::GROUP_READ, self::GROUP_READ])]
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    protected $id;

    #[ORM\ManyToOne(targetEntity: Asset::class, inversedBy: 'subDefinitions')]
    #[ORM\JoinColumn(nullable: false)]
    protected ?Asset $asset = null;

    #[Groups([Asset::GROUP_READ, Publication::GROUP_READ, self::GROUP_READ])]
    #[ORM\Column(type: Types::STRING, length: 30)]
    private ?string $name = null;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private ?string $path = null;

    #[Groups([self::GROUP_READ, Publication::GROUP_READ, Asset::GROUP_READ])]
    #[ORM\Column(type: Types::BIGINT, options: ['unsigned' => true])]
    private ?string $size = null;

    #[ORM\Column(type: Types::STRING, length: 255)]
    #[Groups([self::GROUP_READ, Asset::GROUP_READ])]
    private ?string $mimeType = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups([self::GROUP_READ])]
    private ?\DateTimeImmutable $createdAt = null;

    #[Groups([self::GROUP_READ, Asset::GROUP_READ, Publication::GROUP_READ])]
    private ?string $url = null;

    #[Groups([self::GROUP_READ, Asset::GROUP_READ, Publication::GROUP_READ])]
    private ?string $downloadUrl = null;

    #[Groups([self::GROUP_READ, Asset::GROUP_READ])]
    private ?string $uploadURL = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->id = Uuid::uuid4();
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
        return (int) $this->size;
    }

    public function setSize(int $size): void
    {
        $this->size = (string) $size;
    }

    public function getMimeType(): string
    {
        return $this->mimeType;
    }

    public function setMimeType(string $mimeType): void
    {
        $this->mimeType = $mimeType;
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

    public function getDownloadUrl(): ?string
    {
        return $this->downloadUrl;
    }

    public function setDownloadUrl(string $downloadUrl): void
    {
        $this->downloadUrl = $downloadUrl;
    }

    public function getAsset(): Asset
    {
        return $this->asset;
    }

    public function setAsset(Asset $asset): void
    {
        $this->asset = $asset;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getUploadURL(): ?string
    {
        return $this->uploadURL;
    }

    public function setUploadURL(?string $uploadURL): void
    {
        $this->uploadURL = $uploadURL;
    }
}
