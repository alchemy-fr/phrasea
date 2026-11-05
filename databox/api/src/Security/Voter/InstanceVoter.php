<?php

declare(strict_types=1);

namespace App\Security\Voter;

use Alchemy\CoreBundle\Cache\TemporaryCacheFactory;
use App\Security\InstancePermission;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * Votes on the instance capabilities (InstancePermission::*), asked without subject:
 * granted by a metadata of an ACE on the whole object type. Admins are granted by AdminVoter.
 */
class InstanceVoter extends AbstractVoter
{
    private readonly CacheInterface $cache;

    public function __construct(TemporaryCacheFactory $cacheFactory)
    {
        $this->cache = $cacheFactory->createCache();
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return null === $subject && InstancePermission::isInstancePermission($attribute);
    }

    #[\Override]
    public function supportsAttribute(string $attribute): bool
    {
        return InstancePermission::isInstancePermission($attribute);
    }

    #[\Override]
    public function supportsType(string $subjectType): bool
    {
        return 'null' === $subjectType;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        return $this->cache->get(
            sprintf('%s,%s', $attribute, spl_object_id($token)),
            fn (): bool => $this->hasTypeMetadata(
                InstancePermission::getMetadata($attribute),
                InstancePermission::getEntityClass($attribute),
                $token,
            ),
        );
    }
}
