<?php

declare(strict_types=1);

namespace Alchemy\StorageBundle\Entity;

use ApiPlatform\Core\Annotation\ApiProperty;
use ApiPlatform\Core\Annotation\ApiResource;
use Alchemy\StorageBundle\Controller\MultipartUploadPartAction;
use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * @ORM\Entity()
 * @ApiResource(
 *     shortName="Upload",
 *     normalizationContext={
 *         "groups"={"upload:read"},
 *     },
 *     denormalizationContext={
 *         "groups"={"upload:write"},
 *     },
 *     collectionOperations={
 *         "get"={
 *             "security"="is_granted('ROLE_ADMIN')"
 *         },
 *         "post"={
 *             "openapi_context"={
 *                 "summary"="Create a multi part upload.",
 *             }
 *         },
 *     },
 *     itemOperations={
 *         "get",
 *         "part"={
 *             "method"="POST",
 *             "path"="/uploads/{id}/part",
 *             "controller"=MultipartUploadPartAction::class,
*              "openapi_context"={
 *                 "summary"="Get next upload URL for the next part of file to upload.",
 *                 "parameters"={
 *                     {
 *                         "in"="path",
 *                         "name"="id",
 *                         "type"="string",
 *                         "description"="The upload ID",
 *                     },
 *                 },
 *                 "requestBody": {
 *                     "required": true,
 *                     "content": {
 *                         "application/json": {
 *                             "schema": {
 *                                 "type": "object",
 *                                 "properties": {
 *                                     "part": {
 *                                         "type": "integer",
 *                                     },
 *                                 },
 *                             },
 *                         },
 *                     },
 *                 },
 *                 "responses": {
 *                     "200": {
 *                         "description"="An object containing signed URL for direct upload to S3",
 *                         "content"={
 *                             "application/json"={
 *                                 "schema": {
 *                                     "type": "object",
 *                                     "properties": {
 *                                         "url": {
 *                                             "type": "string",
 *                                         },
 *                                     },
 *                                 },
 *                             },
 *                         },
 *                     },
 *                 },
 *             },
 *         },
 *         "delete"={
 *             "openapi_context"={
 *                 "summary"="Cancel an upload",
 *                 "description"="Cancel an upload.",
 *             }
 *         },
 *     }
 * )
 */
class MultipartUpload
{
    /**
     * @ApiProperty(identifier=true)
     * @var Uuid
     *
     * @ORM\Id
     * @ORM\Column(type="uuid", unique=true)
     * @Groups("upload:read")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255)
     * @Groups({"upload:read", "upload:write"})
     */
    private ?string $filename = null;

    /**
     * @ORM\Column(type="string", length=150)
     * @Groups({"upload:read", "upload:write"})
     */
    private ?string $type = null;

    /**
     * @ORM\Column(name="size", type="bigint", options={"unsigned"=true})
     */
    private ?string $sizeAsString = null;

    /**
     * @ORM\Column(type="string", length=150)
     * @ApiProperty(writable=false)
     */
    private string $uploadId;

    /**
     * @ORM\Column(type="string", length=255)
     * @ApiProperty(writable=false)
     */
    private ?string $path = null;

    /**
     * @ORM\Column(type="boolean")
     * @ApiProperty(writable=false)
     * @Groups("upload:read")
     */
    private bool $complete = false;

    /**
     * @ORM\Column(type="datetime")
     * @ApiProperty(writable=false)
     * @Groups("upload:read")
     */
    private ?DateTimeInterface $createdAt = null;

    public function __construct()
    {
        $this->id = Uuid::uuid4();
        $this->createdAt = new DateTimeImmutable();
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

    public function getCreatedAt(): DateTimeInterface
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

    /**
     * @ApiProperty()
     * @Groups("upload:read")
     */
    public function getSize(): int
    {
        return (int) $this->sizeAsString;
    }

    /**
     * @ApiProperty()
     * @Groups("upload:write")
     */
    public function setSize(int $size): void
    {
        $this->sizeAsString = (string) $size;
    }
}
