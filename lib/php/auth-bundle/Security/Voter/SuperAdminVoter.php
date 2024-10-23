<?php

declare(strict_types=1);

namespace Alchemy\AuthBundle\Security\Voter;

use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Contracts\Service\Attribute\Required;

final class SuperAdminVoter extends Voter
{
    protected Security $security;

    #[Required]
    public function setSecurity(Security $security): void
    {
        $this->security = $security;
    }

    /**
     * Never replace '-' to '_'.
     * Role inherited from API scope will contain '-'.
     */
    final public const string ROLE = 'ROLE_SUPER-ADMIN';

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
