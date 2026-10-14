<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Core\AttributeEntity;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;

class AttributeEntityVoter extends AbstractVoter
{
    private const string SCOPE_PREFIX = 'attribute-entity:';

    protected function supports(string $attribute, $subject): bool
    {
        return $subject instanceof AttributeEntity;
    }

    #[\Override]
    public function supportsType(string $subjectType): bool
    {
        return is_a($subjectType, AttributeEntity::class, true);
    }

    /**
     * @param AttributeEntity $subject
     */
    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        if ($this->tokenHasScope($token, $attribute, self::SCOPE_PREFIX)) {
            return true;
        }

        $isTypeEditor = fn (): bool => $this->security->isGranted(self::EDIT, $subject->getList());
        $isTypeReader = fn (): bool => $this->security->isGranted(self::READ, $subject->getList());

        $isCreator = function () use ($subject): bool {
            $userId = $this->security->getUser()?->getUserIdentifier();

            return null !== $userId && $userId === $subject->getCreatorId();
        };

        return match ($attribute) {
            // An open list takes new values from its readers, not from anyone
            self::CREATE => $isTypeEditor() || ($subject->getList()->isAllowNewValues() && $isTypeReader()),
            self::EDIT, self::DELETE => $isTypeEditor() || ($isCreator() && !$subject->isApproved()),
            self::READ => $isTypeEditor() || ($isTypeReader() && ($subject->isApproved() || $isCreator())),
            default => false,
        };
    }
}
