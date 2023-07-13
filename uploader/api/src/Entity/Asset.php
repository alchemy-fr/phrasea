<?php

declare(strict_types=1);

namespace App\Entity;

use Alchemy\CoreBundle\Entity\AbstractUuidEntity;
use ApiPlatform\Doctrine\Orm\Filter\BooleanFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use App\Controller\AssetAckAction;
use App\Controller\CreateAssetAction;
use App\Security\Voter\AssetVoter;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ApiResource(
    shortName: 'asset',
    operations: [

        new Get(security: 'is_granted("READ_META", object)'),
        new Post(
            uriTemplate: '/assets/{id}/ack',
            defaults: ['_api_respond' => true],
            controller: AssetAckAction::class,
            security: 'is_granted("'.AssetVoter::ACK.'", object)',
            name: 'post_ack',
        ),
        new GetCollection(),
        new Post(
            defaults: ['_api_receive' => false],
            controller: CreateAssetAction::class,
            openapiContext: [
                'requestBody' => [
                    'content' => [
                        'application/json' => [
                            'examples' => [
                                'Multipart upload' => [
                                    'value' => [
                                        'targetId' => '8ad69673-e1cc-4081-8201-20677e9f9e9c',
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
                                    ],
                                ],
                            ],
                            'schema' => [
                                'oneOf' => [
                                    [
                                        'type' => 'object',
                                        'properties' => [
                                            'targetId' => ['type' => 'string'],
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
                                            'targetId' => ['type' => 'string'],
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
                        'multipart/form-data' => [
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'targetId' => ['type' => 'string'],
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
            validationContext: [
                'groups' => [
                    'Default',
                    'asset_create',
                ],
            ]
        ),
    ],
    normalizationContext: [
        'groups' => ['asset:read'],
    ],
    denormalizationContext: [
        'groups' => ['asset:write'],
    ]
)]
#[ORM\Entity(repositoryClass: AssetRepository::class)]
class Asset extends AbstractUuidEntity
{
    #[ORM\Column(type: 'string', length: 255)]
    private ?string $path = null;

    #[ORM\Column(type: 'json', nullable: true)]
    #[Groups('asset:read')]
    private ?array $data = [];

    /**
     * Dynamic signed URL.
     */
    #[ApiProperty]
    #[Groups(['asset:read'])]
    private ?string $url = null;

    #[Groups('asset:read')]
    #[ORM\Column(type: 'bigint', options: ['unsigned' => true])]
    private ?string $size = null;

    #[ApiProperty(iris: ['http://schema.org/name'])]
    #[ORM\Column(type: 'string', length: 255)]
    #[Groups('asset:read')]
    private ?string $originalName = null;

    #[ApiProperty]
    #[ORM\Column(type: 'string', length: 255)]
    #[Groups('asset:read')]
    private ?string $mimeType = null;

    #[ORM\ManyToOne(targetEntity: Target::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Target $target = null;

    #[ORM\ManyToOne(targetEntity: Commit::class, inversedBy: 'assets')]
    private ?Commit $commit = null;

    #[ORM\Column(type: 'boolean')]
    #[Groups('asset:read')]
    #[ApiFilter(filterClass: BooleanFilter::class)]
    private bool $acknowledged = false;

    #[ApiProperty]
    #[ORM\Column(type: 'datetime')]
    #[Groups('asset:read')]
    private \DateTime $createdAt;

    #[ORM\Column(type: 'string', length: 255)]
    private ?string $userId = null;

    public function __construct()
    {
        parent::__construct();
        $this->createdAt = new \DateTime();
    }

    #[Groups('asset:read')]
    public function getId(): string
    {
        return parent::getId();
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

    public function isCommitted(): bool
    {
        return null !== $this->commit;
    }

    #[Groups('asset:read')]
    public function getFormData(): ?array
    {
        return $this->commit ? $this->commit->getFormData() : null;
    }

    #[ApiProperty]
    public function getToken(): ?string
    {
        return $this->commit ? $this->commit->getToken() : null;
    }

    public function getCommit(): ?Commit
    {
        return $this->commit;
    }

    public function setCommit(?Commit $commit): void
    {
        $this->commit = $commit;
    }

    public function getCreatedAt(): \DateTime
    {
        return $this->createdAt;
    }

    public function getUserId(): string
    {
        return $this->userId;
    }

    public function setUserId(string $userId): void
    {
        $this->userId = $userId;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function setUrl(?string $url): void
    {
        $this->url = $url;
    }

    public function isAcknowledged(): bool
    {
        return $this->acknowledged;
    }

    public function setAcknowledged(bool $acknowledged): void
    {
        $this->acknowledged = $acknowledged;
    }

    public function getTarget(): ?Target
    {
        return $this->target;
    }

    public function setTarget(?Target $target): void
    {
        $this->target = $target;
    }

    public function getData(): array
    {
        return $this->data ?? [];
    }

    public function setData(?array $data): void
    {
        $this->data = $data;
    }
}
