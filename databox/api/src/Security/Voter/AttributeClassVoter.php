<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Core\AttributeClass;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

class AttributeClassVoter extends AbstractVoter
{
    final public const string READ_ADMIN = 'READ_ADMIN';

    final public const string SCOPE_PREFIX = 'ROLE_ATTRIBUTE-CLASS:';

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

        return match ($attribute) {
            self::CREATE => $workspaceEditor() || $this->security->isGranted(self::SCOPE_PREFIX.'CREATE'),
            self::EDIT => $workspaceEditor() || $this->security->isGranted(self::SCOPE_PREFIX.'EDIT'),
            self::DELETE => $workspaceEditor() || $this->security->isGranted(self::SCOPE_PREFIX.'DELETE'),
            self::READ_ADMIN => $workspaceEditor() || $this->security->isGranted(self::SCOPE_PREFIX.'READ'),
            self::READ => true,
            default => false,
        };
    }
}
