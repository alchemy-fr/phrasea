<?php

declare(strict_types=1);

namespace Alchemy\NotifierBundle\Entity;

use Alchemy\CoreBundle\Entity\AbstractUuidEntity;
use Alchemy\CoreBundle\Entity\Traits\UpdatedAtTrait;
use Alchemy\NotifierBundle\Channel\ChannelType;
use Alchemy\NotifierBundle\Repository\NotificationPreferenceRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * A per-subscriber opt-in/opt-out for a given (topic, channel) pair.
 * The absence of a row means the channel is enabled by default for the topic.
 */
#[ORM\Entity(repositoryClass: NotificationPreferenceRepository::class)]
#[ORM\Table(name: 'notifier_preference')]
#[ORM\UniqueConstraint(name: 'uniq_notifier_preference', fields: ['subscriber', 'topic', 'channel'])]
class NotificationPreference extends AbstractUuidEntity
{
    use UpdatedAtTrait;

    #[ORM\ManyToOne(targetEntity: Subscriber::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Subscriber $subscriber;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $topic;

    #[ORM\Column(type: Types::STRING, length: 20, enumType: ChannelType::class)]
    private ChannelType $channel;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $enabled = true;

    public function __construct(Subscriber $subscriber, string $topic, ChannelType $channel, bool $enabled = true)
    {
        parent::__construct();
        $this->subscriber = $subscriber;
        $this->topic = $topic;
        $this->channel = $channel;
        $this->enabled = $enabled;
    }

    public function getSubscriber(): Subscriber
    {
        return $this->subscriber;
    }

    public function getTopic(): string
    {
        return $this->topic;
    }

    public function getChannel(): ChannelType
    {
        return $this->channel;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): void
    {
        $this->enabled = $enabled;
    }
}
