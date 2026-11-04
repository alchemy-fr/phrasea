<?php

declare(strict_types=1);

namespace Alchemy\NotifierBundle\Security;

use Alchemy\NotifierBundle\Entity\Subscriber;
use Alchemy\NotifierBundle\Manager\SubscriberManager;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

final readonly class CurrentSubscriberResolver
{
    public function __construct(
        private Security $security,
        private SubscriberManager $subscriberManager,
    ) {
    }

    public function getUserId(): string
    {
        $user = $this->security->getUser();
        if (null === $user) {
            throw new UnauthorizedHttpException('Bearer', 'Authentication required.');
        }

        return $user->getUserIdentifier();
    }

    public function getSubscriber(): Subscriber
    {
        return $this->subscriberManager->getOrCreate($this->getUserId());
    }
}
