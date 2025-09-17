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
        if ($this->tokenHasScope($token, self::SCOPE_PREFIX, $attribute)) {
            return true;
        }

        $typeEditor = fn (): bool => $this->security->isGranted(AbstractVoter::EDIT, $subject->getList());
        $typeReader = fn (): bool => $this->security->isGranted(AbstractVoter::READ, $subject->getList());

        return match ($attribute) {
            self::CREATE, self::DELETE, self::EDIT => $typeEditor(),
            self::READ => $typeReader(),
            default => false,
        };
    }
}
