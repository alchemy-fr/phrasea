<?php

declare(strict_types=1);

namespace App\Security\Voter;

use Alchemy\AuthBundle\Security\JwtUser;
use App\Entity\Core\AssetExport;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;

class AssetExportVoter extends AbstractVoter
{
    public function __construct(private readonly RequestStack $requestStack)
    {
    }

    protected function supports(string $attribute, $subject): bool
    {
        return $subject instanceof AssetExport;
    }

    #[\Override]
    public function supportsType(string $subjectType): bool
    {
        return is_a($subjectType, AssetExport::class, true);
    }

    /**
     * @param AssetExport $subject
     */
    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();

        return match ($attribute) {
            // The signed URL is available only to its owner or the named
            // recipient presenting the per-export capability held by Order.
            self::READ => $user instanceof JwtUser && (
                $subject->getOwnerId() === $user->getId()
                || $this->hasDelegation($subject, $user)
            ),
            self::EDIT => $user instanceof JwtUser && $subject->getOwnerId() === $user->getId(),
            default => false,
        };
    }

    private function hasDelegation(AssetExport $export, JwtUser $user): bool
    {
        if ($export->getDelegateUserId() !== $user->getId() || null === $export->getDelegationHash()) {
            return false;
        }

        $capability = $this->requestStack->getCurrentRequest()?->headers->get('X-Phrasea-Export-Grant');
        if (!is_string($capability) || !preg_match('/^[a-f0-9]{64}$/D', $capability)) {
            return false;
        }

        return hash_equals($export->getDelegationHash(), hash('sha256', $capability));
    }
}
