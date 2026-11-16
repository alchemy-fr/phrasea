<?php

declare(strict_types=1);

namespace Alchemy\StorageBundle\Entity;

use Alchemy\StorageBundle\Api\Dto\MultipartUploadPartsOutput;
use Alchemy\StorageBundle\Api\Dto\MultipartUploadPartUrlOutput;
use Alchemy\StorageBundle\Api\Processor\MultipartUploadCancelProcessor;
use Alchemy\StorageBundle\Api\Processor\MultipartUploadCreateProcessor;
use Alchemy\StorageBundle\Api\Processor\MultipartUploadPartsProcessor;
use Alchemy\StorageBundle\Api\Processor\MultipartUploadPartUrlProcessor;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use ApiPlatform\OpenApi\Model\Parameter as OpenApiParameter;
use ApiPlatform\OpenApi\Model\RequestBody as OpenApiRequestBody;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Doctrine\UuidType;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    shortName: 'Upload',
    operations: [
        new Get(security: self::OWNER_SECURITY),
        new Post(
            normalizationContext: ['groups' => ['upload:read', 'upload:urls']],
            security: 'is_granted("IS_AUTHENTICATED_FULLY")',
            processor: MultipartUploadCreateProcessor::class,
            openapi: new OpenApiOperation(
                summary: 'Create a multi part upload.',
                description: 'The server decides the part size ("chunkSize") from the file size and returns the presigned PUT URLs of the first 1000 parts at most ("urls", keyed by part number); ask "/uploads/{id}/parts" for the following ones. URLs are valid for 3 hours; ask "/uploads/{id}/parts" for fresh ones. A file above the maximum size of the application is refused (400). Answers 429 when the user sends too many upload requests (see the Retry-After header) or has too many uploads in progress.',
            ),
        ),
        new Post(
            uriTemplate: '/uploads/{id}/parts',
            throwOnNotFound: true,
            status: 200,
            security: self::OWNER_SECURITY,
            input: false,
            normalizationContext: ['groups' => ['upload:parts']],
            output: MultipartUploadPartsOutput::class,
            processor: MultipartUploadPartsProcessor::class,
            openapi: new OpenApiOperation(
                summary: 'Get the presigned upload URLs of all the remaining parts.',
                description: 'Returns the part size, the number of parts and the presigned PUT URLs of the parts from "from" (default 1), 1000 at most. Used to resume an upload, to refresh expired URLs or to get the URLs following the ones already received. Answers 429 when the user sends too many upload requests (see the Retry-After header).',
                parameters: [
                    new OpenApiParameter(
                        name: 'id',
                        in: 'path',
                        description: 'The upload ID',
                        required: true,
                        schema: ['type' => 'string'],
                    ),
                ],
                requestBody: new OpenApiRequestBody(
                    content: new \ArrayObject([
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
                    ]),
                    required: false,
                ),
            ),
        ),
        new Post(
            uriTemplate: '/uploads/{id}/part',
            throwOnNotFound: true,
            status: 200,
            security: self::OWNER_SECURITY,
            input: false,
            normalizationContext: ['groups' => ['upload:part_url']],
            output: MultipartUploadPartUrlOutput::class,
            processor: MultipartUploadPartUrlProcessor::class,
            deprecationReason: 'Use the "urls" returned when creating the upload, or POST /uploads/{id}/parts.',
            openapi: new OpenApiOperation(
                summary: 'Get the upload URL for a single part of the file to upload.',
                parameters: [
                    new OpenApiParameter(
                        name: 'id',
                        in: 'path',
                        description: 'The upload ID',
                        required: true,
                        schema: ['type' => 'string'],
                    ),
                ],
                requestBody: new OpenApiRequestBody(
                    content: new \ArrayObject([
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
                    ]),
                    required: true,
                ),
            ),
        ),
        new Delete(
            security: self::OWNER_SECURITY,
            processor: MultipartUploadCancelProcessor::class,
            openapi: new OpenApiOperation(
                summary: 'Cancel an upload',
                description: 'Cancel an upload.',
            ),
        ),

        new GetCollection(security: 'is_granted(\'ROLE_ADMIN\')'),
    ],
    normalizationContext: ['groups' => ['upload:read']],
    denormalizationContext: ['groups' => ['upload:write']]
)]
#[ORM\Entity]
#[ORM\Index(name: 'multipart_upload_owner_created_idx', columns: ['owner_id', 'created_at'])]
class MultipartUpload
{
    /**
     * Only the user (or OAuth client) who started an upload can read, sign,
     * cancel or complete it. Uploads created before the owner was recorded
     * have none and are left to the admins.
     */
    final public const string OWNER_SECURITY = 'is_granted("ROLE_ADMIN") or (user !== null and object.isOwnedBy(user.getUserIdentifier()))';

    #[Groups(['upload:read'])]
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ApiProperty(identifier: true)]
    private string $id;

    #[ORM\Column(type: Types::STRING, length: 255)]
    #[Groups(['upload:read', 'upload:write'])]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private ?string $filename = null;

    #[ORM\Column(type: Types::STRING, length: 150)]
    #[Groups(['upload:read', 'upload:write'])]
    #[Assert\NotBlank]
    #[Assert\Length(max: 150)]
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

    /**
     * User identifier of the token which created the upload (user or OAuth client).
     */
    #[ApiProperty(writable: false)]
    #[ORM\Column(name: 'owner_id', type: Types::STRING, length: 255, nullable: true)]
    private ?string $ownerId = null;

    #[ApiProperty(writable: false)]
    #[ORM\Column(type: Types::BOOLEAN)]
    #[Groups(['upload:read'])]
    private bool $complete = false;

    /**
     * When the upload was reserved by the request using it (UploadManager::claim()):
     * an upload backs a single resource, the S3 object is never shared.
     */
    #[ApiProperty(writable: false)]
    #[ORM\Column(name: 'consumed_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $consumedAt = null;

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

    public function getOwnerId(): ?string
    {
        return $this->ownerId;
    }

    public function setOwnerId(?string $ownerId): void
    {
        $this->ownerId = $ownerId;
    }

    public function isOwnedBy(string $userIdentifier): bool
    {
        return null !== $this->ownerId && $this->ownerId === $userIdentifier;
    }

    public function isComplete(): bool
    {
        return $this->complete;
    }

    public function setComplete(bool $complete): void
    {
        $this->complete = $complete;
    }

    public function getConsumedAt(): ?\DateTimeImmutable
    {
        return $this->consumedAt;
    }

    public function setConsumedAt(?\DateTimeImmutable $consumedAt): void
    {
        $this->consumedAt = $consumedAt;
    }

    public function isConsumed(): bool
    {
        return null !== $this->consumedAt;
    }

    #[Groups('upload:read')]
    #[Assert\NotNull]
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
