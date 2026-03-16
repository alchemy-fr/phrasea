<?php

declare(strict_types=1);

namespace App\Security\Voter;

use Alchemy\AclBundle\Security\PermissionInterface;
use Alchemy\CoreBundle\Cache\TemporaryCacheFactory;
use App\Entity\Core\AssetRendition;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Contracts\Cache\CacheInterface;

class AssetRenditionVoter extends AbstractVoter
{
    private CacheInterface $cache;

    public function __construct(
        TemporaryCacheFactory $cacheFactory,
    ) {
        $this->cache = $cacheFactory->createCache();
    }

    protected function supports(string $attribute, $subject): bool
    {
        return $subject instanceof AssetRendition;
    }

    public function supportsType(string $subjectType): bool
    {
        return is_a($subjectType, AssetRendition::class, true);
    }

    /**
     * @param AssetRendition $subject
     */
    protected function voteOnAttribute(string $attribute, $subject, TokenInterface $token): bool
    {
        return $this->cache->get(sprintf('%s,%s,%s', $attribute, $subject->getId(), spl_object_id($token)), function () use ($attribute, $subject, $token) {
            return $this->doVote($attribute, $subject, $token);
        });
    }

    private function doVote(string $attribute, AssetRendition $subject, TokenInterface $token): bool
    {
        $canRead = fn (): bool => $subject->getDefinition()->getPolicy()->isPublic() || $this->hasAcl(PermissionInterface::CHILD_VIEW, $subject->getDefinition()->getPolicy(), $token);

        return match ($attribute) {
            self::READ => $canRead(),
            self::CREATE, self::EDIT, self::DELETE => $canRead() && $this->security->isGranted(AssetVoter::EDIT, $subject->getAsset()),
            default => false,
        };
    }
}
