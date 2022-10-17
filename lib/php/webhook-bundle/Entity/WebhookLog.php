<?php

declare(strict_types=1);

namespace Alchemy\WebhookBundle\Entity;

use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;

/**
 * @ORM\Entity()
 */
class WebhookLog
{
    /**
     * @var Uuid
     *
     * @ORM\Id
     * @ORM\Column(type="uuid", unique=true)
     * @ORM\GeneratedValue(strategy="CUSTOM")
     * @ORM\CustomIdGenerator(class="Ramsey\Uuid\Doctrine\UuidGenerator")
     */
    protected $id;

    /**
     * @ORM\ManyToOne(targetEntity="Alchemy\WebhookBundle\Entity\Webhook", inversedBy="failures")
     * @ORM\JoinColumn(nullable=false, onDelete="CASCADE")
     */
    private ?Webhook $webhook = null;

    /**
     * @ORM\Column(type="string", length=255, nullable=false)
     */
    private ?string $event = null;

    /**
     * @ORM\Column(type="json", nullable=false)
     */
    private array $payload = [];

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private ?string $response = null;

    /**
     * @ORM\Column(type="datetime", nullable=false)
     */
    private DateTimeInterface $createdAt;

    public function __construct()
    {
        $this->createdAt = new DateTimeImmutable();
    }

    public function getId()
    {
        return $this->id->__toString();
    }

    public function getCreatedAt(): DateTimeInterface
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
