<?php

declare(strict_types=1);

namespace App\Realtime;

use Alchemy\CoreBundle\Pusher\ChannelAuthorizerInterface;
use App\Entity\Basket\Basket;
use App\Entity\Core\Asset;
use App\Entity\Core\AssetExport;
use App\Entity\Core\File;
use App\Entity\Core\Workspace;
use App\Entity\Workflow\WorkflowState;
use App\Security\Voter\AbstractVoter;
use App\Service\Discussion\DiscussionManager;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Grants a channel bound to an entity to whoever may READ that entity, anonymous visitors included.
 *
 * The voters rely on the current security token: $user is not used.
 */
final readonly class EntityChannelAuthorizer implements ChannelAuthorizerInterface
{
    private const array ENTITIES = [
        RealtimeChannel::WORKSPACE => Workspace::class,
        RealtimeChannel::ASSET => Asset::class,
        RealtimeChannel::FILE => File::class,
        RealtimeChannel::BASKET => Basket::class,
        RealtimeChannel::EXPORT => AssetExport::class,
        RealtimeChannel::WORKFLOW => WorkflowState::class,
    ];

    public function __construct(
        private EntityManagerInterface $em,
        private Security $security,
        private DiscussionManager $discussionManager,
    ) {
    }

    public function supports(string $channel): bool
    {
        return null !== $this->getPrefix($channel);
    }

    public function isGranted(string $channel, ?UserInterface $user): bool
    {
        $prefix = $this->getPrefix($channel);
        if (null === $prefix) {
            return false;
        }

        $id = substr($channel, strlen($prefix));
        $object = RealtimeChannel::THREAD === $prefix
            ? $this->findThreadObject($id)
            : $this->find(self::ENTITIES[$prefix], $id);

        return null !== $object && $this->security->isGranted(AbstractVoter::READ, $object);
    }

    private function getPrefix(string $channel): ?string
    {
        foreach ([...array_keys(self::ENTITIES), RealtimeChannel::THREAD] as $prefix) {
            if (str_starts_with($channel, $prefix)) {
                return $prefix;
            }
        }

        return null;
    }

    /**
     * @param class-string $class
     */
    private function find(string $class, string $id): ?object
    {
        if (!Uuid::isValid($id)) {
            return null;
        }

        return $this->em->find($class, $id);
    }

    /**
     * The thread may not exist yet: the channel is granted on the object it is about.
     */
    private function findThreadObject(string $normalizedKey): ?object
    {
        if (!str_contains($normalizedKey, '.')) {
            return null;
        }

        [$objectKey, $objectId] = explode('.', $normalizedKey, 2);

        return $this->discussionManager->findObject($objectKey, $objectId);
    }
}
