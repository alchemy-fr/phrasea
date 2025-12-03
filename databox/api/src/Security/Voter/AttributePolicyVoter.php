<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Core\AttributePolicy;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

class AttributePolicyVoter extends AbstractVoter
{
    final public const string READ_ADMIN = 'READ_ADMIN';
    private const string SCOPE_PREFIX = 'attribute-policy:';

    protected function supports(string $attribute, $subject): bool
    {
        return $subject instanceof AttributePolicy;
    }

    public function supportsType(string $subjectType): bool
    {
        return is_a($subjectType, AttributePolicy::class, true);
    }

    /**
     * @param AttributePolicy $subject
     */
    protected function voteOnAttribute(string $attribute, $subject, TokenInterface $token): bool
    {
        if ($this->tokenHasScope($token, $attribute, self::SCOPE_PREFIX)) {
            return true;
        }

        $workspaceEditor = fn (): bool => $this->security->isGranted(self::EDIT, $subject->getWorkspace(), $token);
        $workspaceReader = fn (): bool => $this->security->isGranted(self::READ, $subject->getWorkspace(), $token);

        return match ($attribute) {
            self::CREATE, self::EDIT, self::DELETE => $workspaceEditor()
                || $this->tokenHasScope($token, $attribute, self::SCOPE_PREFIX),
            self::READ_ADMIN => $workspaceEditor() || $this->tokenHasScope($token, self::READ, self::SCOPE_PREFIX),
            self::READ => $workspaceReader() || $this->tokenHasScope($token, $attribute, self::SCOPE_PREFIX),
            default => false,
        };
    }
}
