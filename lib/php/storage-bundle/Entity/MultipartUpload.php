<?php

declare(strict_types=1);

namespace Alchemy\StorageBundle\Entity;

use Alchemy\StorageBundle\Controller\MultipartUploadCancelAction;
use Alchemy\StorageBundle\Controller\MultipartUploadPartAction;
use Alchemy\StorageBundle\Controller\MultipartUploadPartsAction;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Doctrine\UuidType;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Serializer\Annotation\Groups;

#[ApiResource(
    shortName: 'Upload',
    operations: [
        new Get(security: 'is_granted("IS_AUTHENTICATED_FULLY")'),
        new Post(
            normalizationContext: ['groups' => ['upload:read', 'upload:urls']],
            security: 'is_granted("IS_AUTHENTICATED_FULLY")',
            openapiContext: [
                'summary' => 'Create a multi part upload.',
                'description' => 'The server decides the part size ("chunkSize") from the file size and returns the presigned PUT URLs of every part ("urls", keyed by part number). URLs are valid for 3 hours; ask "/uploads/{id}/parts" for fresh ones.',
            ],
        ),
        new Post(
            uriTemplate: '/uploads/{id}/parts',
            security: 'is_granted("IS_AUTHENTICATED_FULLY")',
            controller: MultipartUploadPartsAction::class,
            openapiContext: [
                'summary' => 'Get the presigned upload URLs of all the remaining parts.',
                'description' => 'Returns the part size, the number of parts and the presigned PUT URLs of the parts from "from" (default 1) to the last one. Used to resume an upload or to refresh expired URLs.',
                'parameters' => [
                    [
                        'in' => 'path',
                        'name' => 'id',
                        'type' => 'string',
                        'description' => 'The upload ID',
                    ],
                ],
                'requestBody' => [
                    'required' => false,
                    'content' => [
                        'application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'from' => [
                                        'type' => 'integer',
                                        'description' => 'First part number to return (>= 1)',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                'responses' => [
                    '200' => [
                        'description' => 'The upload plan and the presigned URLs for direct upload to S3',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'chunkSize' => ['type' => 'integer'],
                                        'partCount' => ['type' => 'integer'],
                                        'urls' => [
                                            'type' => 'object',
                                            'additionalProperties' => ['type' => 'string'],
                                            'description' => 'Part number => presigned PUT URL',
                                        ],
                                    ],
                                ],
                            ],
                        ]],
                ],
            ]),
        new Post(
            uriTemplate: '/uploads/{id}/part',
            security: 'is_granted("IS_AUTHENTICATED_FULLY")',
            controller: MultipartUploadPartAction::class,
            deprecationReason: 'Use the "urls" returned when creating the upload, or POST /uploads/{id}/parts.',
            openapiContext: [
                'summary' => 'Get the upload URL for a single part of the file to upload.',
                'parameters' => [
                    [
                        'in' => 'path',
                        'name' => 'id',
                        'type' => 'string',
                        'description' => 'The upload ID',
                    ],
                ],
                'requestBody' => [
                    'required' => true,
                    'content' => [
                        'application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'part' => [
                                        'type' => 'integer',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                'responses' => [
                    '200' => [
                        'description' => 'An object containing signed URL for direct upload to S3',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'url' => [
                                            'type' => 'string',
                                        ],
                                    ],
                                ],
                            ],
                        ]],
                ],
            ]),
        new Delete(
            controller: MultipartUploadCancelAction::class,
            security: 'is_granted("IS_AUTHENTICATED_FULLY")',
            openapiContext: [
                'summary' => 'Cancel an upload',
                'description' => 'Cancel an upload.',
            ]
        ),

        new GetCollection(security: 'is_granted(\'ROLE_ADMIN\')'),
    ],
    normalizationContext: ['groups' => ['upload:read']],
    denormalizationContext: ['groups' => ['upload:write']]
)]
#[ORM\Entity]
class MultipartUpload
{
    #[Groups(['upload:read'])]
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ApiProperty(identifier: true)]
    private string $id;

    #[ORM\Column(type: Types::STRING, length: 255)]
    #[Groups(['upload:read', 'upload:write'])]
    private ?string $filename = null;

    #[ORM\Column(type: Types::STRING, length: 150)]
    #[Groups(['upload:read', 'upload:write'])]
    private ?string $type = null;

    #[ORM\Column(name: 'size', type: 'bigint', options: ['unsigned' => true])]
    private ?string $sizeAsString = null;

    /**
     * Part size decided by the server when the upload was created. Persisted
     * so that a resumed upload keeps slicing the file the same way even if the
     * multipart limits were reconfigured in between.
     */
    #[ApiProperty(writable: false)]
    #[ORM\Column(name: 'chunk_size', type: 'bigint', nullable: true, options: ['unsigned' => true])]
    private ?string $chunkSizeAsString = null;

    /**
     * Presigned PUT URL of every part, keyed by part number. Not persisted:
     * only filled (and exposed) in the response of the creation request.
     *
     * @var array<int, string>
     */
    #[ApiProperty(writable: false)]
    #[Groups(['upload:urls'])]
    private array $urls = [];

    #[ApiProperty(writable: false)]
    #[ORM\Column(type: Types::STRING, length: 150)]
    private string $uploadId;

    #[ApiProperty(writable: false)]
    #[ORM\Column(type: Types::STRING, length: 255)]
    private ?string $path = null;

    #[ApiProperty(writable: false)]
    #[ORM\Column(type: Types::BOOLEAN)]
    #[Groups(['upload:read'])]
    private bool $complete = false;

    #[ApiProperty(writable: false)]
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['upload:read'])]
    private ?\DateTimeImmutable $createdAt = null;

    public function __construct()
    {
        $this->id = Uuid::uuid4()->toString();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getFilename(): ?string
    {
        return $this->filename;
    }

    public function setFilename(?string $filename): void
    {
        $this->filename = $filename;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): void
    {
        $this->type = $type;
    }

    public function getUploadId(): string
    {
        return $this->uploadId;
    }

    public function setUploadId(string $uploadId): void
    {
        $this->uploadId = $uploadId;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function hasPath(): bool
    {
        return null !== $this->path;
    }

    public function setPath(string $path): void
    {
        $this->path = $path;
    }

    public function isComplete(): bool
    {
        return $this->complete;
    }

    public function setComplete(bool $complete): void
    {
        $this->complete = $complete;
    }

    #[Groups('upload:read')]
    public function getSize(): int
    {
        return (int) $this->sizeAsString;
    }

    #[Groups('upload:write')]
    public function setSize(int $size): void
    {
        $this->sizeAsString = (string) $size;
    }

    #[Groups('upload:read')]
    public function getChunkSize(): ?int
    {
        return null !== $this->chunkSizeAsString ? (int) $this->chunkSizeAsString : null;
    }

    public function setChunkSize(int $chunkSize): void
    {
        $this->chunkSizeAsString = (string) $chunkSize;
    }

    /**
     * @return array<int, string>
     */
    public function getUrls(): array
    {
        return $this->urls;
    }

    /**
     * @param array<int, string> $urls
     */
    public function setUrls(array $urls): void
    {
        $this->urls = $urls;
    }
}
