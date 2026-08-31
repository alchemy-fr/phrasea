<?php

declare(strict_types=1);

namespace Alchemy\NotifierBundle\Entity;

use Alchemy\CoreBundle\Entity\AbstractUuidEntity;
use Alchemy\CoreBundle\Entity\Traits\CreatedAtTrait;
use Alchemy\NotifierBundle\Repository\NotificationDigestRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Buffer of pending events for one (subscriber, topic, channel) bucket, waiting
 * to be sent as a single grouped notification (digest).
 *
 * The row lives only while a digest window is open: it is created by the first
 * buffered event, grown atomically by the next ones, and deleted when flushed.
 * Rows are written through {@see NotificationDigestRepository} in raw SQL (the
 * append must be a single atomic upsert); the entity mostly carries the schema.
 */
#[ORM\Entity(repositoryClass: NotificationDigestRepository::class)]
#[ORM\Table(name: 'notifier_digest')]
#[ORM\UniqueConstraint(name: 'uniq_notifier_digest_bucket', fields: ['subscriber', 'topic', 'channel'])]
#[ORM\Index(name: 'idx_notifier_digest_last_event', fields: ['lastEventAt'])]
class NotificationDigest extends AbstractUuidEntity
{
    use CreatedAtTrait;

    /**
     * Events kept beyond this count only bump `eventCount`/`lastEventAt`; the
     * digest then reports "and N more".
     */
    public const int MAX_EVENTS = 100;

    #[ORM\ManyToOne(targetEntity: Subscriber::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Subscriber $subscriber;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $topic;

    /**
     * ChannelType value the buffered events were headed to.
     */
    #[ORM\Column(type: Types::STRING, length: 20)]
    private string $channel;

    /**
     * Buffered events, capped at {@see MAX_EVENTS}.
     *
     * @var array<int, array{params: array<string, mixed>, at: string}>
     */
    #[ORM\Column(type: Types::JSON)]
    private array $events = [];

    /**
     * True total of buffered events, still counting past the cap.
     */
    #[ORM\Column(type: Types::INTEGER)]
    private int $eventCount = 0;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $firstEventAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $lastEventAt;

    public function getSubscriber(): Subscriber
    {
        return $this->subscriber;
    }

    public function getTopic(): string
    {
        return $this->topic;
    }

    public function getChannel(): string
    {
        return $this->channel;
    }

    /**
     * @return array<int, array{params: array<string, mixed>, at: string}>
     */
    public function getEvents(): array
    {
        return $this->events;
    }

    public function getEventCount(): int
    {
        return $this->eventCount;
    }

    public function getFirstEventAt(): \DateTimeImmutable
    {
        return $this->firstEventAt;
    }

    public function getLastEventAt(): \DateTimeImmutable
    {
        return $this->lastEventAt;
    }
}
