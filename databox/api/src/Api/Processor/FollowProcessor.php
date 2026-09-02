<?php

declare(strict_types=1);

namespace App\Api\Processor;

use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use Alchemy\NotifierBundle\Manager\SubscriptionManager;
use Alchemy\NotifierBundle\Model\NotifySelectorDto;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Api\Model\Input\FollowInput;
use App\Entity\FollowableInterface;
use App\Security\Voter\AbstractVoter;

class FollowProcessor implements ProcessorInterface
{
    use FollowEventResolverTrait;
    use SecurityAwareTrait;

    public function __construct(
        private readonly SubscriptionManager $subscriptionManager,
    ) {
    }

    /**
     * @param FollowInput $data
     */
    public function process($data, Operation $operation, array $uriVariables = [], array $context = []): FollowableInterface
    {
        $user = $this->getStrictUser();
        $object = $context['previous_data'];
        assert($object instanceof FollowableInterface);
        $this->denyAccessUnlessGranted(AbstractVoter::READ, $object);

        foreach ($this->resolveEvents($object, $data->key) as $event) {
            $this->subscriptionManager->subscribe($user->getId(), new NotifySelectorDto(
                event: $event,
                objectType: $object->getObjectType(),
                objectId: $object->getId(),
            ));
        }

        return $object;
    }
}
