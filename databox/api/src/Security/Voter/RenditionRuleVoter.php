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
    final public const string SCOPE_PREFIX = 'ROLE_RENDITION-RULE:';

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

        return match ($attribute) {
            self::CREATE => $objectEditor() || $this->security->isGranted(self::SCOPE_PREFIX.'CREATE'),
            self::EDIT => $objectEditor() || $this->security->isGranted(self::SCOPE_PREFIX.'EDIT'),
            self::DELETE => $objectEditor() || $this->security->isGranted(self::SCOPE_PREFIX.'DELETE'),
            self::READ => true,
            default => false,
        };
    }
}
