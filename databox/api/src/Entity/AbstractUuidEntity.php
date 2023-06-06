<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * @ORM\MappedSuperclass
 */
abstract class AbstractUuidEntity
{
    /**
     * @Groups({"_"})
     *
     * @ORM\Id
     * @ORM\Column(type="uuid", unique=true)
     */
    private string $id;

    public function __construct()
    {
        $this->id = Uuid::uuid4()->toString();
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function __serialize(): array
    {
        return [
            'id' => $this->getId(),
        ];
    }

    public function __unserialize($data)
    {
        $this->id = $data['id'];
    }
}
