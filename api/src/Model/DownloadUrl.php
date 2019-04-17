<?php

declare(strict_types=1);

namespace App\Model;

use Doctrine\ORM\Mapping as ORM;
use ApiPlatform\Core\Annotation\ApiResource;
use Symfony\Component\HttpFoundation\File\File;

/**
 * @ORM\Entity
 * @ApiResource(
 *     normalizationContext={
 *         "groups"={"asset_read"},
 *     },
 *     collectionOperations={
 *         "post"={
 *             "controller"=DownloadUrlAction::class,
 *             "defaults"={
 *                 "_api_receive"=false,
 *             },
 *             "validation_groups"={"Default", "asset_create"},
 *             "swagger_context"={
 *                 "consumes"={
 *                     "multipart/form-data",
 *                 },
 *                 "parameters"={
 *                     {
 *                         "in"="formData",
 *                         "name"="file",
 *                         "type"="file",
 *                         "description"="The file to upload",
 *                     },
 *                 },
 *             },
 *         },
 *     },
 *     itemOperations={},
 * )
 */
class DownloadUrl
{
    /**
     * @var string
     */
    private $path;

    /**
     * @var int
     */
    private $size;

    /**
     * @var string
     */
    private $originalName;

    public function getFile(): ?File
    {
        return $this->file;
    }

    public function setFile(File $file): void
    {
        $this->file = $file;
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
        return $this->size;
    }

    public function setSize(int $size): void
    {
        $this->size = $size;
    }

    public function getOriginalName(): string
    {
        return $this->originalName;
    }

    public function setOriginalName(string $originalName): void
    {
        $this->originalName = $originalName;
    }
}
