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

        $isTypeEditor = fn (): bool => $this->security->isGranted(self::EDIT, $subject->getList());
        $isTypeReader = fn (): bool => $this->security->isGranted(self::READ, $subject->getList());

        return match ($attribute) {
            self::CREATE, self::DELETE, self::EDIT => $isTypeEditor(),
            self::READ => $isTypeReader(),
            default => false,
        };
    }
}
