<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Core\AttributeEntity;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

class AttributeEntityVoter extends AbstractVoter
{
    public static function getScopePrefix(): string
    {
        return 'attribute-entity:';
    }

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
        $typeEditor = fn (): bool => $this->security->isGranted(AbstractVoter::EDIT, $subject->getList());
        $typeReader = fn (): bool => $this->security->isGranted(AbstractVoter::READ, $subject->getList());

        return match ($attribute) {
            self::CREATE => $typeEditor() || $this->hasScope($token, $attribute),
            self::EDIT => $typeEditor() || $this->hasScope($token, $attribute),
            self::DELETE => $typeEditor() || $this->hasScope($token, $attribute),
            self::READ => $typeReader() || $this->hasScope($token, $attribute),
            default => false,
        };
    }
}
