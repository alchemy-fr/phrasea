<?php

declare(strict_types=1);

namespace App\Security\Voter;

use Alchemy\AclBundle\AclObjectInterface;
use Alchemy\AclBundle\Model\AclUserInterface;
use Alchemy\AclBundle\Security\Voter\SetPermissionVoter as BaseSetPermissionVoter;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

#[AsDecorator(BaseSetPermissionVoter::class)]
class SetPermissionVoter extends AbstractVoter
{
    final public const string ACL_READ = 'ACL_READ';
    final public const string ACL_WRITE = 'ACL_WRITE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array(
            $attribute, [
                self::ACL_READ,
                self::ACL_WRITE,
            ],
            true
        ) && $subject instanceof AclObjectInterface;
    }

    public function supportsAttribute(string $attribute): bool
    {
        return in_array(
            $attribute, [
                self::ACL_READ,
                self::ACL_WRITE,
            ],
            true
        );
    }

    public function supportsType(string $subjectType): bool
    {
        return is_a($subjectType, AclObjectInterface::class, true);
    }

    /**
     * @param AclObjectInterface $subject
     */
    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof AclUserInterface) {
            return false;
        }

        return match ($attribute) {
            self::ACL_READ, self::ACL_WRITE => $this->security->isGranted(AbstractVoter::EDIT_PERMISSIONS, $subject),
            default => false,
        };
    }
}
