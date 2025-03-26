<?php

declare(strict_types=1);

namespace App\Security\Voter;

use Alchemy\CoreBundle\Util\DoctrineUtil;
use App\Entity\Core\Collection;
use App\Entity\Core\RenditionRule;
use App\Entity\Core\Workspace;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

class RenditionRuleVoter extends AbstractVoter
{
    public static function getScopePrefix(): string
    {
        return 'rendition-rule:';
    }

    protected function supports(string $attribute, $subject): bool
    {
        return $subject instanceof RenditionRule;
    }

    public function supportsType(string $subjectType): bool
    {
        return is_a($subjectType, RenditionRule::class, true);
    }

    /**
     * @param RenditionRule $subject
     */
    protected function voteOnAttribute(string $attribute, $subject, TokenInterface $token): bool
    {
        $object = match ($subject->getObjectType()) {
            RenditionRule::TYPE_WORKSPACE => DoctrineUtil::findStrict($this->em, Workspace::class, $subject->getObjectId()),
            RenditionRule::TYPE_COLLECTION => DoctrineUtil::findStrict($this->em, Collection::class, $subject->getObjectId()),
        };
        $objectEditor = fn (): bool => $this->security->isGranted(AbstractVoter::EDIT, $object);
        $objectReader = fn (): bool => $this->security->isGranted(AbstractVoter::READ, $object);

        return match ($attribute) {
            self::CREATE, self::DELETE, self::EDIT => $objectEditor() || $this->hasScope($token, $attribute),
            self::READ => $objectReader(),
            default => false,
        };
    }
}
