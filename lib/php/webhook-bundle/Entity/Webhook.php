<?php

declare(strict_types=1);

namespace Alchemy\WebhookBundle\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Doctrine\UuidType;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Validator\Constraints\Url;

#[ORM\Entity]
class Webhook
{
    final public const string ALL_EVENTS = '_all';

    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private UuidInterface|string $id;

    #[ORM\Column(type: Types::STRING, length: 1024, nullable: false)]
    #[Url]
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
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): string
    {
        if (is_string($this->id)) {
            return $this->id;
        }

        return $this->id->toString();
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
