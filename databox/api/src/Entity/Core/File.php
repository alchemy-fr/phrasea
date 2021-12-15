<?php

declare(strict_types=1);

namespace App\Entity\Core;

use ApiPlatform\Core\Annotation\ApiResource;
use App\Entity\AbstractUuidEntity;
use App\Entity\Traits\CreatedAtTrait;
use App\Entity\Traits\UpdatedAtTrait;
use App\Entity\Traits\WorkspaceTrait;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity()
 * @ApiResource()
 */
class File extends AbstractUuidEntity
{
    public const STORAGE_S3_MAIN = 's3_main';
    public const STORAGE_PUBLIC_URL = 'public_url';

    use CreatedAtTrait;
    use UpdatedAtTrait;
    use WorkspaceTrait;

    /**
     * The MIME type.
     *
     * @ORM\Column(type="string", length=100, nullable=true)
     */
    private ?string $type = null;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private ?int $size = null;

    /**
     * @ORM\Column(type="string", length=64, nullable=true)
     */
    private ?string $checksum = null;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private ?string $path = null;

    /**
     * @ORM\Column(type="string", length=150, nullable=false)
     */
    private ?string $storage = null;

    public function getPath(): ?string
    {
        return $this->path;
    }

    public function setPath(?string $path): void
    {
        $this->path = $path;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(?string $type): void
    {
        $this->type = $type;
    }

    public function getSize(): ?int
    {
        return $this->size;
    }

    public function setSize(?int $size): void
    {
        $this->size = $size;
    }

    public function __toString()
    {
        return $this->getId();
    }

    public function getStorage(): ?string
    {
        return $this->storage;
    }

    public function setStorage(?string $storage): void
    {
        $this->storage = $storage;
    }
}
