<?php

declare(strict_types=1);

namespace Alchemy\WebhookBundle\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Doctrine\UuidGenerator;
use Ramsey\Uuid\Doctrine\UuidType;
use Ramsey\Uuid\Uuid;

#[ORM\Entity]
class WebhookLog
{
    /**
     * @var Uuid
     */
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    protected $id;

    #[ORM\ManyToOne(targetEntity: Webhook::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Webhook $webhook = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: false)]
    private ?string $event = null;

    #[ORM\Column(type: Types::JSON, nullable: false)]
    private array $payload = [];

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $response = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: false)]
    private readonly \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId()
    {
        return $this->id->__toString();
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getEvent(): ?string
    {
        return $this->event;
    }

    public function setEvent(string $event): void
    {
        $this->event = $event;
    }

    public function getPayload(): array
    {
        return $this->payload;
    }

    public function setPayload(array $payload): void
    {
        $this->payload = $payload;
    }

    public function getResponse(): ?string
    {
        return $this->response;
    }

    public function setResponse(?string $response): void
    {
        $this->response = $response;
    }

    public function getWebhook(): ?Webhook
    {
        return $this->webhook;
    }

    public function setWebhook(?Webhook $webhook): void
    {
        $this->webhook = $webhook;
    }
}
