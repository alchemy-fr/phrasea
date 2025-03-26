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
        $workspaceEditor = fn (): bool => $this->security->isGranted(AbstractVoter::EDIT, $subject->getWorkspace());
        $workspaceReader = fn (): bool => $this->security->isGranted(AbstractVoter::READ, $subject->getWorkspace());

        return match ($attribute) {
            self::CREATE => $workspaceEditor() || $this->hasScope($token, $attribute),
            self::EDIT => $workspaceEditor() || $this->hasScope($token, $attribute),
            self::DELETE => $workspaceEditor() || $this->hasScope($token, $attribute),
            self::READ => $workspaceReader() || $this->hasScope($token, $attribute),
            default => false,
        };
    }
}
