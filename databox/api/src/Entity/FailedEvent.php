<?php

declare(strict_types=1);

namespace App\Entity;

use Arthem\Bundle\RabbitBundle\Model\FailedEvent as BaseFailedEvent;
use DateTime;
use Doctrine\ORM\Mapping as ORM;
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
    #[ORM\CustomIdGenerator(class: \Ramsey\Uuid\Doctrine\UuidGenerator::class)]
    protected $id;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTime $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    public function getId(): string
    {
        return $this->id->__toString();
    }

    public function getCreatedAt(): \DateTime
    {
        return $this->createdAt;
    }

    public function getPayloadAsJson(): string
    {
        return json_encode($this->getPayload(), JSON_THROW_ON_ERROR);
    }
}
