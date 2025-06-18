<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Core\RenditionPolicy;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

class RenditionPolicyVoter extends AbstractVoter
{
    final public const string READ_ADMIN = 'READ_ADMIN';

    public static function getScopePrefix(): string
    {
        return 'rendition-policy:';
    }

    protected function supports(string $attribute, $subject): bool
    {
        return $subject instanceof RenditionPolicy;
    }

    public function supportsType(string $subjectType): bool
    {
        return is_a($subjectType, RenditionPolicy::class, true);
    }

    /**
     * @param RenditionPolicy $subject
     */
    protected function voteOnAttribute(string $attribute, $subject, TokenInterface $token): bool
    {
        $workspaceEditor = fn (): bool => $this->security->isGranted(AbstractVoter::EDIT, $subject->getWorkspace());

        return match ($attribute) {
            self::CREATE, self::EDIT, self::DELETE => $workspaceEditor() || $this->hasScope($token, $attribute),
            self::READ_ADMIN => $workspaceEditor()
                || $this->hasScope($token, self::READ),
            self::READ => true,
            default => false,
        };
    }
}
