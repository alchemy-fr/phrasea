<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Core\Annotation\ApiProperty;
use ApiPlatform\Core\Annotation\ApiResource;
use App\Controller\MultipartUploadPartAction;
use App\Controller\MultipartUploadStartAction;
use App\Controller\MultipartUploadCancelAction;
use DateTime;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * @ORM\Entity()
 * @ApiResource(
 *     shortName="Upload",
 *     normalizationContext={
 *         "groups"={"upload_read"},
 *     },
 *     collectionOperations={
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
 *             "openapi_context"={
 *                 "summary"="Get next upload URL for the next part of file to upload.",
 *                 "description"="Allow to delete already uploaded parts.",
 *             },
*              "swagger_context"={
 *                 "parameters"={
 *                     {
 *                         "in"="path",
 *                         "name"="id",
 *                         "type"="string",
 *                         "description"="The upload ID",
 *                     },
 *                     {
 *                         "in"="json",
 *                         "name"="part",
 *                         "type"="integer",
 *                         "description"="The file part to upload",
 *                     },
 *                 },
 *                 "responses": {
 *                     "200": {
 *                         "description"="An object containing signed URL for direct upload to S3",
 *                         "schema": {
 *                             "type": "object",
 *                             "properties": {
 *                                 "url": {
 *                                     "type": "string",
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
 *                 "description"="Allow to delete already uploaded parts.",
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
     * @Groups("upload_read")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255)
     * @Groups("upload_read")
     */
    private string $filename;

    /**
     * @ORM\Column(type="string", length=150)
     * @Groups("upload_read")
     */
    private string $type;

    /**
     * @var int
     * @ORM\Column(type="integer")
     * @Groups("upload_read")
     */
    private int $size;

    /**
     * @ORM\Column(type="string", length=150)
     * @ApiProperty(writable=false)
     */
    private string $uploadId;

    /**
     * @ORM\Column(type="string", length=255)
     * @ApiProperty(writable=false)
     */
    private string $path;

    /**
     * @ORM\Column(type="boolean")
     * @ApiProperty(writable=false)
     * @Groups("upload_read")
     */
    private bool $complete = false;

    /**
     * @var DateTime
     *
     * @ORM\Column(type="datetime")
     * @ApiProperty(writable=false)
     * @Groups("upload_read")
     */
    private $createdAt;

    public function __construct()
    {
        $this->id = Uuid::uuid4();
        $this->createdAt = new DateTime();
    }

    public function getId(): string
    {
        return $this->id->toString();
    }

    public function getFilename(): string
    {
        return $this->filename;
    }

    public function setFilename(string $filename): void
    {
        $this->filename = $filename;
    }

    public function getType(): string
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

    public function getCreatedAt(): DateTime
    {
        return $this->createdAt;
    }

    public function getPath(): string
    {
        return $this->path;
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

    public function getSize(): int
    {
        return $this->size;
    }

    public function setSize(int $size): void
    {
        $this->size = $size;
    }
}
