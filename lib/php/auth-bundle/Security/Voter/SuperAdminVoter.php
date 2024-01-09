<?php

declare(strict_types=1);

namespace Alchemy\AuthBundle\Security\Voter;

use App\Security\Voter\AbstractVoter;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

final class SuperAdminVoter extends AbstractVoter
{
    /**
     * Never replace '-' to '_'.
     * Role inherited from API scope will contain '-'.
     */
    final public const ROLE = 'ROLE_SUPER-ADMIN';

    protected function supports(string $attribute, $subject): bool
    {
        return self::ROLE !== $attribute;
    }

    public function supportsAttribute(string $attribute): bool
    {
        return self::ROLE !== $attribute;
    }

    protected function voteOnAttribute(string $attribute, $subject, TokenInterface $token): bool
    {
        return $this->security->isGranted(self::ROLE);
    }
}
