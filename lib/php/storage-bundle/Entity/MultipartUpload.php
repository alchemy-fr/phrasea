<?php

declare(strict_types=1);

namespace Alchemy\StorageBundle\Entity;

use Alchemy\StorageBundle\Controller\MultipartUploadPartAction;
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
        new Get(),
        new Post(openapiContext: ['summary' => 'Create a multi part upload.']),
        new Post(
            uriTemplate: '/uploads/{id}/part',
            controller: MultipartUploadPartAction::class,
            openapiContext: [
                'summary' => 'Get next upload URL for the next part of file to upload.',
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
                    [
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
        new Delete(openapiContext: ['summary' => 'Cancel an upload', 'description' => 'Cancel an upload.']),
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

    public function setFilename(string $filename): void
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
}
