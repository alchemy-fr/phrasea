<?php

declare(strict_types=1);

namespace Alchemy\NotifierBundle\Entity;

use Alchemy\CoreBundle\Entity\AbstractUuidEntity;
use Alchemy\CoreBundle\Entity\Traits\CreatedAtTrait;
use Alchemy\NotifierBundle\Repository\SubscriptionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Links a subscriber to an object it follows, identified by (objectType, objectId).
 */
#[ORM\Entity(repositoryClass: SubscriptionRepository::class)]
#[ORM\Table(name: 'notifier_subscription')]
#[ORM\UniqueConstraint(name: 'uniq_notifier_subscription', fields: ['subscriber', 'topic', 'objectType', 'objectId'])]
#[ORM\Index(name: 'idx_notifier_subscription_topic', fields: ['topic'])]
#[ORM\Index(name: 'idx_notifier_subscription_topic_object', fields: ['topic', 'objectType', 'objectId'])]
#[ORM\Index(name: 'idx_notifier_subscription_object', fields: ['objectType', 'objectId'])]
class Subscription extends AbstractUuidEntity
{
    use CreatedAtTrait;

    #[ORM\ManyToOne(targetEntity: Subscriber::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Subscriber $subscriber;

    #[ORM\Column(type: Types::STRING, length: 100, nullable: false)]
    private string $topic;

    #[ORM\Column(type: Types::STRING, length: 30, nullable: true)]
    private ?string $objectType = null;

    #[ORM\Column(type: Types::STRING, length: 36, nullable: true)]
    private ?string $objectId = null;

    public function __construct(Subscriber $subscriber, string $topic, ?string $objectType = null, ?string $objectId = null)
    {
        parent::__construct();
        $this->topic = $topic;
        $this->subscriber = $subscriber;
        $this->objectType = $objectType;
        $this->objectId = $objectId;
    }

    public function getSubscriber(): Subscriber
    {
        return $this->subscriber;
    }

    public function getObjectType(): ?string
    {
        return $this->objectType;
    }

    public function getObjectId(): ?string
    {
        return $this->objectId;
    }
}
