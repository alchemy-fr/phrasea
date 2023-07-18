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
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Serializer\Annotation\Groups;

#[ApiResource(operations: [new Get(), new Delete(security: 'is_granted("DELETE", object)'), new Post(), new GetCollection()], normalizationContext: ['groups' => ['subdef:read'], 'swagger_definition_name' => 'Read'])]
#[ORM\Table]
#[ORM\UniqueConstraint(name: 'uniq_asset_type', columns: ['asset_id', 'name'])]
#[ORM\Entity(repositoryClass: SubDefinitionRepository::class)]
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
                                    ['$ref' => '#/components/schemas/SubDefinition'],
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
                                    ['$ref' => '#/components/schemas/SubDefinition'],
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
    uriVariables: ['id' => new Link(fromClass: Asset::class, identifiers: ['id'])],
)]

class SubDefinition implements MediaInterface
{
    final public const THUMBNAIL = 'thumbnail';
    final public const PREVIEW = 'preview';
    final public const POSTER = 'poster';

    final public const API_READ = [
        'groups' => ['subdef:read'],
        'swagger_definition_name' => 'Read',
    ];

    /**
     * @var Uuid
     */
    #[ApiProperty(identifier: true)]
    #[Groups(['asset:read', 'publication:read', 'subdef:read'])]
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    protected $id;

    #[ORM\ManyToOne(targetEntity: Asset::class, inversedBy: 'subDefinitions')]
    #[ORM\JoinColumn(nullable: false)]
    protected ?Asset $asset = null;

    #[ApiProperty]
    #[Groups(['asset:read', 'publication:read', 'subdef:read'])]
    #[ORM\Column(type: 'string', length: 30)]
    private ?string $name = null;

    #[ORM\Column(type: 'string', length: 255)]
    private ?string $path = null;

    #[Groups(['subdef:read', 'publication:read', 'asset:read'])]
    #[ORM\Column(type: 'bigint', options: ['unsigned' => true])]
    private ?string $size = null;

    #[ApiProperty]
    #[ORM\Column(type: 'string', length: 255)]
    #[Groups(['subdef:read', 'asset:read'])]
    private ?string $mimeType = null;

    /**
     * @var \DateTime
     */
    #[ApiProperty]
    #[ORM\Column(type: 'datetime')]
    #[Groups(['subdef:read'])]
    private ?\DateTime $createdAt = null;

    #[ApiProperty]
    #[Groups(['subdef:read', 'asset:read', 'publication:read'])]
    private ?string $url = null;

    #[ApiProperty]
    #[Groups(['subdef:read', 'asset:read', 'publication:read'])]
    private ?string $downloadUrl = null;

    #[ApiProperty]
    #[Groups(['subdef:read', 'asset:read'])]
    private ?string $uploadURL = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
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

    public function getCreatedAt(): \DateTime
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
