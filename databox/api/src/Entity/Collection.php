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

/**
 * @ORM\Entity()
 * @ApiResource()
 */
class Collection
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
     * @ORM\Column(type="string", length=100, nullable=false)
     */
    private ?string $title = null;

    /**
     * @ORM\Column(type="string", length=255)
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

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): void
    {
        $this->title = $title;
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
