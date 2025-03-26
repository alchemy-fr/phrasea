<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Core\AttributeClass;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

class AttributeClassVoter extends AbstractVoter
{
    final public const string READ_ADMIN = 'READ_ADMIN';

    public static function getScopePrefix(): string
    {
        return 'attribute-class:';
    }

    protected function supports(string $attribute, $subject): bool
    {
        return $subject instanceof AttributeClass;
    }

    public function supportsType(string $subjectType): bool
    {
        return is_a($subjectType, AttributeClass::class, true);
    }

    /**
     * @param AttributeClass $subject
     */
    protected function voteOnAttribute(string $attribute, $subject, TokenInterface $token): bool
    {
        $workspaceEditor = fn (): bool => $this->security->isGranted(AbstractVoter::EDIT, $subject->getWorkspace());
        $workspaceReader = fn (): bool => $this->security->isGranted(AbstractVoter::READ, $subject->getWorkspace());

        return match ($attribute) {
            self::CREATE, self::EDIT, self::DELETE => $workspaceEditor() || $this->hasScope($token, $attribute),
            self::READ_ADMIN => $workspaceEditor() || $this->hasScope($token, 'read'),
            self::READ => $workspaceReader() || $this->hasScope($token, $attribute),
            default => false,
        };
    }
}
