<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Core\AssetPolicy\AssetPolicy;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

class AssetPolicyVoter extends AbstractVoter
{
    final public const string READ_ADMIN = 'READ_ADMIN';
    private const string SCOPE_PREFIX = 'asset-policy:';

    protected function supports(string $attribute, $subject): bool
    {
        return $subject instanceof AssetPolicy;
    }

    #[\Override]
    public function supportsType(string $subjectType): bool
    {
        return is_a($subjectType, AssetPolicy::class, true);
    }

    /**
     * @param AssetPolicy $subject
     */
    protected function voteOnAttribute(string $attribute, $subject, TokenInterface $token): bool
    {
        if ($this->tokenHasScope($token, $attribute, self::SCOPE_PREFIX)) {
            return true;
        }

        $isWorkspaceEditor = fn (): bool => $this->security->isGranted(self::EDIT, $subject->getWorkspace());

        return match ($attribute) {
            self::READ, self::CREATE, self::EDIT, self::DELETE => $isWorkspaceEditor(),
            default => false,
        };
    }
}
