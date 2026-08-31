<?php

declare(strict_types=1);

namespace Alchemy\NotifierBundle\Entity;

use Alchemy\CoreBundle\Entity\AbstractUuidEntity;
use Alchemy\CoreBundle\Entity\Traits\CreatedAtTrait;
use Alchemy\NotifierBundle\Channel\ChannelType;
use Alchemy\NotifierBundle\Repository\BroadcastRepository;
use Alchemy\NotifierBundle\Topic\BuiltInTopic;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * History of one broadcast: what was sent, to which audience, by whom, and how
 * it went.
 *
 * The row is created when the broadcast is requested and completed by the
 * worker once the audience has been walked, so an unfinished (or crashed) run
 * stays visible with `completedAt` still null.
 */
#[ORM\Entity(repositoryClass: BroadcastRepository::class)]
#[ORM\Table(name: 'notifier_broadcast')]
#[ORM\Index(name: 'idx_notifier_broadcast_created', fields: ['createdAt'])]
#[ORM\Index(name: 'idx_notifier_broadcast_initiator', fields: ['initiatorUserId'])]
class Broadcast extends AbstractUuidEntity
{
    use CreatedAtTrait;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $topic;

    /**
     * Template parameters the notification was rendered with.
     *
     * @var array<string, mixed>
     */
    #[ORM\Column(type: Types::JSON)]
    private array $payload = [];

    /**
     * Channel values the delivery was restricted to; null means every channel
     * of the topic.
     *
     * @var array<int, string>|null
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $channels = null;

    /**
     * Name of the user directory the audience was taken from.
     */
    #[ORM\Column(type: Types::STRING, length: 50)]
    private string $directory;

    /**
     * userId of whoever triggered the broadcast; null when it was not sent by a
     * user (CLI, scheduled job).
     */
    #[ORM\Column(type: Types::GUID, nullable: true)]
    private ?string $initiatorUserId = null;

    #[ORM\Column(type: Types::GUID, nullable: true)]
    private ?string $excludeUserId = null;

    #[ORM\Column(type: Types::INTEGER)]
    private int $deliveredCount = 0;

    #[ORM\Column(type: Types::INTEGER)]
    private int $failedCount = 0;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $startedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $completedAt = null;

    /**
     * Not persisted: only tells the admin form whether the sender wants to
     * receive their own announcement.
     */
    private bool $excludeInitiator = true;

    public function __construct(string $topic = BuiltInTopic::ADMIN_MESSAGE, string $directory = '')
    {
        parent::__construct();
        $this->topic = $topic;
        $this->directory = $directory;
    }

    public function getTopic(): string
    {
        return $this->topic;
    }

    public function setTopic(string $topic): void
    {
        $this->topic = $topic;
    }

    /**
     * @return array<string, mixed>
     */
    public function getPayload(): array
    {
        return $this->payload;
    }

    /**
     * @return array<int, string>|null
     */
    public function getChannels(): ?array
    {
        return $this->channels;
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function setPayload(array $payload): void
    {
        $this->payload = $payload;
    }

    /**
     * @param array<int, string>|null $channels
     */
    public function setChannels(?array $channels): void
    {
        $this->channels = null === $channels ? null : array_values($channels);
    }

    public function getDirectory(): string
    {
        return $this->directory;
    }

    public function setDirectory(string $directory): void
    {
        $this->directory = $directory;
    }

    public function getInitiatorUserId(): ?string
    {
        return $this->initiatorUserId;
    }

    public function setInitiatorUserId(?string $initiatorUserId): void
    {
        $this->initiatorUserId = $initiatorUserId;
    }

    public function getExcludeUserId(): ?string
    {
        return $this->excludeUserId;
    }

    public function setExcludeUserId(?string $excludeUserId): void
    {
        $this->excludeUserId = $excludeUserId;
    }

    public function isExcludeInitiator(): bool
    {
        return $this->excludeInitiator;
    }

    public function setExcludeInitiator(bool $excludeInitiator): void
    {
        $this->excludeInitiator = $excludeInitiator;
    }

    /**
     * Subject, body and link are the payload of the built-in `admin:message`
     * topic; they are exposed as properties so the admin form can edit the
     * JSON payload field by field.
     */
    public function getSubject(): ?string
    {
        return $this->payload['subject'] ?? null;
    }

    public function setSubject(?string $subject): void
    {
        $this->payload['subject'] = $subject;
    }

    public function getBody(): ?string
    {
        return $this->payload['body'] ?? null;
    }

    public function setBody(?string $body): void
    {
        $this->payload['body'] = $body;
    }

    public function getUrl(): ?string
    {
        return $this->payload['url'] ?? null;
    }

    public function setUrl(?string $url): void
    {
        $this->payload['url'] = $url;
    }

    public function getDeliveredCount(): int
    {
        return $this->deliveredCount;
    }

    public function getFailedCount(): int
    {
        return $this->failedCount;
    }

    public function getStartedAt(): ?\DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function getCompletedAt(): ?\DateTimeImmutable
    {
        return $this->completedAt;
    }

    public function start(): void
    {
        // A retried message starts over, counters included
        $this->startedAt = new \DateTimeImmutable();
        $this->completedAt = null;
        $this->deliveredCount = 0;
        $this->failedCount = 0;
    }

    public function complete(int $deliveredCount, int $failedCount): void
    {
        $this->deliveredCount = $deliveredCount;
        $this->failedCount = $failedCount;
        $this->completedAt = new \DateTimeImmutable();
    }

    /**
     * Human-readable channel list (`All channels of the topic` when unrestricted).
     */
    public function getChannelLabels(): string
    {
        if (null === $this->channels || [] === $this->channels) {
            return 'All';
        }

        return implode(', ', array_map(
            static fn (string $c): string => ChannelType::tryFrom($c)?->label() ?? $c,
            $this->channels,
        ));
    }

    public function __toString(): string
    {
        return sprintf('%s (%s)', $this->topic, $this->createdAt?->format('Y-m-d H:i') ?? '—');
    }
}
