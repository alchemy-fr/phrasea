<?php

declare(strict_types=1);

namespace Alchemy\CoreBundle\Entity;

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
    private UuidInterface|string $id;

    public function __construct(string|UuidInterface|null $id = null)
    {
        if (null !== $id) {
            if ($id instanceof UuidInterface) {
                $this->id = $id;
            } else {
                $this->id = Uuid::fromString($id);
            }
        } else {
            $this->id = Uuid::uuid4();
        }
    }

    public function getId(): string
    {
        if (is_string($this->id)) {
            return $this->id;
        }

        return $this->id->toString();
    }

    public function __serialize(): array
    {
        return [
            'id' => $this->getId(),
        ];
    }

    public function __unserialize($data): void
    {
        $this->id = Uuid::fromString($data['id']);
    }
}
