<?php

declare(strict_types=1);

namespace Alchemy\NotifierBundle\Entity;

use Alchemy\CoreBundle\Entity\AbstractUuidEntity;
use Alchemy\CoreBundle\Entity\Traits\CreatedAtTrait;
use Alchemy\NotifierBundle\Repository\NotificationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Persisted in-app notification (history + unread badge for the InApp channel).
 *
 * Only the raw `topic` + `payload` are stored; the human-readable content is
 * rendered (Twig) lazily, at read time.
 */
#[ORM\Entity(repositoryClass: NotificationRepository::class)]
#[ORM\Table(name: 'notifier_notification')]
#[ORM\Index(name: 'idx_notifier_notification_subscriber', fields: ['subscriber', 'createdAt'])]
class Notification extends AbstractUuidEntity
{
    use CreatedAtTrait;

    #[ORM\ManyToOne(targetEntity: Subscriber::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Subscriber $subscriber;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $topic;

    /**
     * Template parameters used to render the notification at read time.
     *
     * @var array<string, mixed>
     */
    #[ORM\Column(type: Types::JSON)]
    private array $payload = [];

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $readAt = null;

    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(Subscriber $subscriber, string $topic, array $payload = [])
    {
        parent::__construct();
        $this->subscriber = $subscriber;
        $this->topic = $topic;
        $this->payload = $payload;
    }

    public function getSubscriber(): Subscriber
    {
        return $this->subscriber;
    }

    public function getTopic(): string
    {
        return $this->topic;
    }

    /**
     * @return array<string, mixed>
     */
    public function getPayload(): array
    {
        return $this->payload;
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function setPayload(array $payload): void
    {
        $this->payload = $payload;
    }

    public function getReadAt(): ?\DateTimeImmutable
    {
        return $this->readAt;
    }

    public function isRead(): bool
    {
        return null !== $this->readAt;
    }

    public function markAsRead(): void
    {
        $this->readAt ??= new \DateTimeImmutable();
    }

    public function markAsUnread(): void
    {
        $this->readAt = null;
    }
}
