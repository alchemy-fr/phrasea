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
abstract class AbstractUuidEntity implements \Serializable
{
    /**
     * @var UuidInterface|string
     * @Groups({"_"})
     * @ORM\Id
     * @ORM\Column(type="uuid", unique=true)
     */
    private $id;

    public function __construct()
    {
        $this->id = Uuid::uuid4();
    }

    public function getId(): string
    {
        if (is_string($this->id)) {
            $this->id = Uuid::fromString($this->id);
        }

        return $this->id->__toString();
    }

    public function serialize()
    {
        $this->id = $this->getId();
    }

    public function unserialize($data)
    {
    }

    public function __wakeup()
    {
        if (is_string($this->id)) {
            $this->id = Uuid::fromString($this->id);
        }
    }
}
