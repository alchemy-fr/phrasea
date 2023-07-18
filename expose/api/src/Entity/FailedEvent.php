<?php

declare(strict_types=1);

namespace App\Entity;

use Arthem\Bundle\RabbitBundle\Model\FailedEvent as BaseFailedEvent;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Doctrine\UuidGenerator;
use Ramsey\Uuid\Uuid;

#[ORM\Entity]
class FailedEvent extends BaseFailedEvent
{
    /**
     * @var Uuid
     */
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    protected $id;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): string
    {
        return $this->id->__toString();
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getPayloadAsJson(): string
    {
        return json_encode($this->getPayload(), JSON_THROW_ON_ERROR);
    }
}
