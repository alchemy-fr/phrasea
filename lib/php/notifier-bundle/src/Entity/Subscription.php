<?php

declare(strict_types=1);

namespace Alchemy\NotifierBundle\Entity;

use Alchemy\CoreBundle\Entity\AbstractUuidEntity;
use Alchemy\CoreBundle\Entity\Traits\CreatedAtTrait;
use Alchemy\NotifierBundle\Repository\SubscriptionRepository;
use Arthem\ObjectReferenceBundle\Mapping\Attribute\ObjectReference;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Links a subscriber to an object it follows, identified by (objectType, objectId).
 */
#[ORM\Entity(repositoryClass: SubscriptionRepository::class)]
#[ORM\Table(name: 'notifier_subscription')]
#[ORM\UniqueConstraint(name: 'uniq_notifier_subscription', fields: ['subscriber', 'objectType', 'objectId'])]
#[ORM\Index(name: 'idx_notifier_subscription_object', fields: ['objectType', 'objectId'])]
class Subscription extends AbstractUuidEntity
{
    use CreatedAtTrait;

    #[ORM\ManyToOne(targetEntity: Subscriber::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Subscriber $subscriber;

    #[ORM\Column(type: Types::STRING, length: 100, nullable: false)]
    private string $topic;

    #[ORM\Column(type: Types::STRING, length: 36, nullable: true)]
    #[ObjectReference(keyLength: 30)]
    private \Closure|AbstractUuidEntity|null $object = null;
    private ?string $objectType = null;
    private ?string $objectId = null;

    public function __construct(Subscriber $subscriber, AbstractUuidEntity $object)
    {
        parent::__construct();
        $this->subscriber = $subscriber;
        $this->object = $object;
    }

    public function getSubscriber(): Subscriber
    {
        return $this->subscriber;
    }

    public function getObject(): ?AbstractUuidEntity
    {
        if ($this->object instanceof \Closure) {
            $this->object = $this->object->call($this);
        }

        return $this->object;
    }

    public function setObject(?AbstractUuidEntity $object): void
    {
        $this->object = $object;
    }
}
