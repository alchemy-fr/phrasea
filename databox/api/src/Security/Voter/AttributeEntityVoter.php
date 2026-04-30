<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Core\AttributeEntity;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

class AttributeEntityVoter extends AbstractVoter
{
    private const string SCOPE_PREFIX = 'attribute-entity:';

    protected function supports(string $attribute, $subject): bool
    {
        return $subject instanceof AttributeEntity;
    }

    public function supportsType(string $subjectType): bool
    {
        return is_a($subjectType, AttributeEntity::class, true);
    }

    /**
     * @param AttributeEntity $subject
     */
    protected function voteOnAttribute(string $attribute, $subject, TokenInterface $token): bool
    {
        if ($this->tokenHasScope($token, $attribute, self::SCOPE_PREFIX)) {
            return true;
        }

        $typeEditor = fn (): bool => $this->security->isGranted(self::EDIT, $subject->getList());
        $typeReader = fn (): bool => $this->security->isGranted(self::READ, $subject->getList());

        $isCreator = function () use ($subject): bool {
            $userId = $this->security->getUser()?->getUserIdentifier();

            return null !== $userId && $userId === $subject->getCreatorId();
        };

        return match ($attribute) {
            self::CREATE => $typeEditor() || $subject->getList()->isAllowNewValues(),
            self::EDIT, self::DELETE => $typeEditor() || ($isCreator() && !$subject->isApproved()),
            self::READ => $typeReader() && ($subject->isApproved() || $isCreator()),
            default => false,
        };
    }
}
