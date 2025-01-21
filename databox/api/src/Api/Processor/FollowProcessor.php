<?php

declare(strict_types=1);

namespace App\Api\Processor;

use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use Alchemy\NotifyBundle\Notification\NotifierInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Api\Model\Input\FollowInput;
use App\Entity\FollowableInterface;
use App\Security\Voter\AbstractVoter;

class FollowProcessor implements ProcessorInterface
{
    use SecurityAwareTrait;

    public function __construct(
        private readonly NotifierInterface $notifier,
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

        $topicKeys = $object->getTopicKeys();
        $key = $data->key;

        if (null === $key) {
            foreach ($topicKeys as $topicKey) {
                $this->notifier->addTopicSubscribers($topicKey, [$user->getId()]);
            }
        } else {
            if (!in_array($key, $topicKeys, true)) {
                throw new \InvalidArgumentException(sprintf('Invalid topic key "%s"', $key));
            }

            $this->notifier->addTopicSubscribers($key, [$user->getId()]);
        }

        return $object;
    }
}
