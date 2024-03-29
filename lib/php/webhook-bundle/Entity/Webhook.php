<?php

declare(strict_types=1);

namespace Alchemy\WebhookBundle\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Doctrine\UuidGenerator;
use Ramsey\Uuid\Doctrine\UuidType;
use Ramsey\Uuid\Uuid;

#[ORM\Entity]
class Webhook
{
    final public const ALL_EVENTS = '_all';

    /**
     * @var Uuid
     */
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    protected $id;

    #[ORM\Column(type: Types::STRING, length: 1024, nullable: false)]
    private ?string $url = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $secret = null;

    #[ORM\Column(type: Types::BOOLEAN, nullable: false)]
    private bool $verifySSL = true;

    #[ORM\Column(type: Types::BOOLEAN, nullable: false)]
    private bool $active = true;

    /**
     * Null if all events are active.
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $events = null;

    #[ORM\Column(type: Types::JSON, nullable: false)]
    private array $options = [];

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

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function setUrl(?string $url): void
    {
        $this->url = $url;
    }

    public function getSecret(): ?string
    {
        return $this->secret;
    }

    public function setSecret(?string $secret): void
    {
        $this->secret = $secret;
    }

    public function isVerifySSL(): bool
    {
        return $this->verifySSL;
    }

    public function setVerifySSL(bool $verifySSL): void
    {
        $this->verifySSL = $verifySSL;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): void
    {
        $this->active = $active;
    }

    public function getOptions(): array
    {
        return $this->options;
    }

    public function setOptions(array $options): void
    {
        $this->options = $options;
    }

    public function getTimeout(): int
    {
        return $this->options['timeout'] ?? 30;
    }

    public function setTimeout(?int $timeout): void
    {
        $this->options['timeout'] = $timeout;
    }

    public function getEvents(): ?array
    {
        return $this->events;
    }

    public function hasEvent(string $event): bool
    {
        if (null === $this->events) {
            return true;
        }

        return in_array($event, $this->events, true);
    }

    public function setEvents(?array $events): void
    {
        $this->events = $events;
    }

    public function eventsLabel(): string
    {
        if (null === $this->events) {
            return 'All';
        }

        if (empty($this->events)) {
            return 'None';
        }

        return implode(', ', $this->events);
    }
}
