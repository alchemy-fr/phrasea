<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * @ORM\MappedSuperclass
 */
abstract class AbstractUuidEntity
{
    /**
     * @Groups({"tag:index", "tag:read"})
     * @ORM\Id
     * @ORM\Column(type="uuid", unique=true)
     */
    private UuidInterface $id;

    public function __construct()
    {
        $this->id = Uuid::uuid4();
    }

    public function getId(): string
    {
        return $this->id->__toString();
    }
}
