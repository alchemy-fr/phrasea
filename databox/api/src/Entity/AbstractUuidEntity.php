<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiProperty;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Doctrine\UuidType;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\MappedSuperclass]
abstract class AbstractUuidEntity
{
    #[Groups(['_'])]
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ApiProperty(identifier: true)]
    private UuidInterface $id;

    public function __construct(string $id = null)
    {
        $this->id = null !== $id ? Uuid::fromString($id) : Uuid::uuid4();
    }

    public function getId(): string
    {
        return $this->id->toString();
    }

    public function __serialize(): array
    {
        return [
            'id' => $this->getId(),
        ];
    }

    public function __unserialize($data)
    {
        $this->id = Uuid::fromString($data['id']);
    }
}
