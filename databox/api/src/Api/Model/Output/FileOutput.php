<?php

declare(strict_types=1);

namespace App\Api\Model\Output;

use ApiPlatform\Core\Annotation\ApiProperty;
use ApiPlatform\Core\Annotation\ApiResource;
use App\Api\Model\Output\Traits\CreatedAtDTOTrait;
use App\Api\Model\Output\Traits\UpdatedAtDTOTrait;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * @ApiResource(
 *  shortName="file",
 *  normalizationContext="file:read",
 * )
 */
class FileOutput extends AbstractUuidDTO
{
    use CreatedAtDTOTrait;
    use UpdatedAtDTOTrait;

    /**
     * The MIME type.
     */
    private ?string $type = null;

    /**
     */
    private ?int $size = null;

    /**
     */
    private ?int $checksum = null;

    /**
     */
    private ?string $path = null;

    /**
     * Signed URL.
     *
     * @ApiProperty(writable=false)
     * @Groups({"file:read"})
     */
    private ?string $url = null;

    public function getPath(): ?string
    {
        return $this->path;
    }

    public function setPath(?string $path): void
    {
        $this->path = $path;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function setUrl(?string $url): void
    {
        $this->url = $url;
    }
}
