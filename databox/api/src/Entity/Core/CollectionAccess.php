<?php

declare(strict_types=1);

namespace App\Entity\Core;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use MartinGeorgiev\Doctrine\DBAL\Type;
use MartinGeorgiev\Doctrine\DBAL\Types\ValueObject\Ltree;
use Ramsey\Uuid\Doctrine\UuidType;

#[ORM\Table]
#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'collection_access_uniq', columns: ['collection_id', 'user_id'])]
#[ORM\Index(name: 'ca_path_idx', columns: ['path'])]
#[ORM\Index(name: 'ca_privacy_idx', columns: ['privacy'])]
#[ORM\Index(name: 'ca_user_privacy_idx', columns: ['user_id', 'privacy'])]
class CollectionAccess
{
    #[ORM\Id]
    #[ORM\Column(type: Types::INTEGER)]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    private int $id;

    #[ORM\ManyToOne(targetEntity: Collection::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Collection $collection = null;

    #[ORM\ManyToOne(targetEntity: Workspace::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Workspace $workspace = null;

    // NULL for public collections
    #[ORM\Column(type: UuidType::NAME, nullable: true)]
    private ?string $userId = null;

    #[ORM\Column(type: Types::SMALLINT, nullable: true)]
    private ?int $privacy = null;

    #[ORM\Column(type: Type::LTREE)]
    private ?Ltree $path = null;

    public function getId(): string
    {
        return (string) $this->id;
    }

    public function getCollection(): ?Collection
    {
        return $this->collection;
    }

    public function setCollection(?Collection $collection): void
    {
        $this->collection = $collection;
    }

    public function getWorkspace(): ?Workspace
    {
        return $this->workspace;
    }

    public function setWorkspace(?Workspace $workspace): void
    {
        $this->workspace = $workspace;
    }

    public function getUserId(): ?string
    {
        return $this->userId;
    }

    public function setUserId(?string $userId): void
    {
        $this->userId = $userId;
    }

    public function getPrivacy(): ?int
    {
        return $this->privacy;
    }

    public function setPrivacy(?int $privacy): void
    {
        $this->privacy = $privacy;
    }

    public function getPath(): ?Ltree
    {
        return $this->path;
    }

    public function setPath(?Ltree $path): void
    {
        $this->path = $path;
    }
}
