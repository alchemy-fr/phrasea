<?php

declare(strict_types=1);

namespace App\Security\Voter;

use Alchemy\AclBundle\Security\PermissionInterface;
use Alchemy\RemoteAuthBundle\Model\RemoteUser;
use App\Entity\FormSchema;
use App\Entity\TargetParams;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\Security;

class FormDataEditorVoter extends Voter
{
    final public const EDIT_FORM_SCHEMA = 'EDIT_FORM_SCHEMA';
    final public const EDIT_TARGET_DATA = 'EDIT_TARGET_DATA';

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

        if (!$user instanceof RemoteUser) {
            return false;
        }

        return match ($attribute) {
            self::EDIT_FORM_SCHEMA => $this->security->isGranted('ROLE_SUPER_ADMIN')
                || $this->security->isGranted(PermissionInterface::EDIT, new FormSchema()),
            self::EDIT_TARGET_DATA => $this->security->isGranted('ROLE_SUPER_ADMIN')
                || $this->security->isGranted(PermissionInterface::EDIT, new TargetParams()),
            default => false,
        };
    }
}
