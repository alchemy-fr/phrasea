<?php

namespace App\Consumer\Handler\Asset;

use Alchemy\AuthBundle\Repository\UserRepository;
use Alchemy\AuthBundle\Security\JwtUser;
use Alchemy\AuthBundle\Security\RoleMapper;
use Alchemy\NotifierBundle\Manager\NotifierManager;
use Alchemy\NotifierBundle\Manager\SubscriptionManager;
use Alchemy\NotifierBundle\Model\NotifySelectorDto;
use Alchemy\NotifierBundle\Model\TopicDto;
use App\Entity\Core\Asset;
use App\Security\Voter\AbstractVoter;
use App\Service\Asset\Attribute\AssetNameResolver;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpClientExceptionInterface;

abstract readonly class AbstractNotifyHandler
{
    public function __construct(
        protected AssetNameResolver $assetNameResolver,
        protected NotifierManager $notifierManager,
        protected EntityManagerInterface $em,
        protected UserRepository $userRepository,
        private SubscriptionManager $subscriptionManager,
        private Security $security,
        private RoleMapper $roleMapper,
        private LoggerInterface $logger,
    ) {
    }

    protected function getUsername(string $userId): string
    {
        $user = $this->userRepository->getUser($userId);

        return $user ? $user['username'] : 'Deleted User';
    }

    /**
     * The subscribers of $selector who can read $asset, as a selector of users: an attribute filter
     * rule may hide the asset from some of them, who must not get its name. Null when there is none.
     *
     * @param array<string, bool> $readers whether each user can read the asset, shared between the
     *                                     selectors of a notification
     */
    protected function createReadersSelector(NotifySelectorDto $selector, Asset $asset, TopicDto $topic, array &$readers): ?NotifySelectorDto
    {
        $userIds = [];
        foreach ($this->subscriptionManager->getSubscriberUserIds($selector) as $userId) {
            if ($readers[$userId] ??= $this->canRead($userId, $asset)) {
                $userIds[] = $userId;
            }
        }

        return [] === $userIds ? null : new NotifySelectorDto(userIds: $userIds, topic: $topic);
    }

    private function canRead(string $userId, Asset $asset): bool
    {
        try {
            $user = new JwtUser(
                '',
                $userId,
                $userId,
                $this->roleMapper->getRoles($this->userRepository->getUserRoles($userId)),
                $this->userRepository->getUserGroupIds($userId),
            );
        } catch (ClientExceptionInterface) {
            // A user unknown to the identity provider
            return false;
        } catch (HttpClientExceptionInterface $e) {
            // The identity provider is unavailable: this subscriber misses the notification, not the others
            $this->logger->warning(sprintf('Unable to read the permissions of user "%s" for a notification: %s', $userId, $e->getMessage()), [
                'exception' => $e,
            ]);

            return false;
        }

        return $this->security->isGrantedForUser($user, AbstractVoter::READ, $asset);
    }
}
