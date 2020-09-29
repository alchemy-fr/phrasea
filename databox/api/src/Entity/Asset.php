<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Core\Annotation\ApiProperty;
use ApiPlatform\Core\Annotation\ApiResource;
use App\Entity\Traits\CreatedAtTrait;
use App\Entity\Traits\UpdatedAtTrait;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * @ORM\Entity()
 * @ApiResource()
 */
class Asset
{
    use CreatedAtTrait;
    use UpdatedAtTrait;

    /**
     * @ORM\Id
     * @ApiProperty(identifier=true)
     * @ORM\Column(type="uuid", unique=true)
     */
    protected UuidInterface $id;

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
    private ?int $checksum = null;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private ?string $path = null;

    /**
     * Dynamic signed URL.
     *
     * @ApiProperty(writable=false)
     * @Groups({"record_read"})
     */
    private ?string $url = null;

    /**
     * @ORM\Column(type="string", length=255)
     * @Groups({"record_read"})
     */
    private ?string $ownerId = null;

    public function __construct()
    {
        $this->id = Uuid::uuid4();
    }

    public function getId(): string
    {
        return $this->id->__toString();
    }

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

    public function getOwnerId(): ?string
    {
        return $this->ownerId;
    }

    public function setOwnerId(?string $ownerId): void
    {
        $this->ownerId = $ownerId;
    }
}
