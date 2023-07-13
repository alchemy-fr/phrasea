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
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
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
    /**
     * @var Uuid
     */
    #[ApiProperty(identifier: true)]
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[Groups('upload:read')]
    private readonly UuidInterface $id;

    #[ORM\Column(type: 'string', length: 255)]
    #[Groups(['upload:read', 'upload:write'])]
    private ?string $filename = null;

    #[ORM\Column(type: 'string', length: 150)]
    #[Groups(['upload:read', 'upload:write'])]
    private ?string $type = null;

    #[ORM\Column(name: 'size', type: 'bigint', options: ['unsigned' => true])]
    private ?string $sizeAsString = null;

    #[ApiProperty(writable: false)]
    #[ORM\Column(type: 'string', length: 150)]
    private string $uploadId;

    #[ApiProperty(writable: false)]
    #[ORM\Column(type: 'string', length: 255)]
    private ?string $path = null;

    #[ApiProperty(writable: false)]
    #[ORM\Column(type: 'boolean')]
    #[Groups('upload:read')]
    private bool $complete = false;

    #[ApiProperty(writable: false)]
    #[ORM\Column(type: 'datetime')]
    #[Groups('upload:read')]
    private ?\DateTimeInterface $createdAt = null;

    public function __construct()
    {
        $this->id = Uuid::uuid4();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): string
    {
        return $this->id->toString();
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

    public function getCreatedAt(): \DateTimeInterface
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
