<?php

declare(strict_types=1);

namespace App\Security\Voter;

use Alchemy\AclBundle\Security\PermissionInterface;
use Alchemy\AuthBundle\Security\JwtUser;
use App\Entity\FormSchema;
use App\Entity\TargetParams;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class FormDataEditorVoter extends Voter
{
    final public const string EDIT_FORM_SCHEMA = 'EDIT_FORM_SCHEMA';
    final public const string EDIT_TARGET_DATA = 'EDIT_TARGET_DATA';

    public function __construct(private readonly Security $security)
    {
    }

    protected function supports($attribute, $subject): bool
    {
        return in_array($attribute, [
            self::EDIT_FORM_SCHEMA,
            self::EDIT_TARGET_DATA,
        ], true);
    }

    protected function voteOnAttribute($attribute, $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        if (!$user instanceof JwtUser) {
            return false;
        }

        return match ($attribute) {
            self::EDIT_FORM_SCHEMA => $this->security->isGranted(JwtUser::ROLE_ADMIN)
                || $this->security->isGranted(PermissionInterface::EDIT, new FormSchema()),
            self::EDIT_TARGET_DATA => $this->security->isGranted(JwtUser::ROLE_ADMIN)
                || $this->security->isGranted(PermissionInterface::EDIT, new TargetParams()),
            default => false,
        };
    }
}
