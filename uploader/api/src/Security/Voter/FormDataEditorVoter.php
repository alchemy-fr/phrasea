<?php

declare(strict_types=1);

namespace App\Security\Voter;

use Alchemy\AclBundle\Security\PermissionInterface;
use Alchemy\RemoteAuthBundle\Model\RemoteUser;
use App\Entity\BulkData;
use App\Entity\FormSchema;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\Security;

class FormDataEditorVoter extends Voter
{
    const EDIT_FORM_SCHEMA = 'EDIT_FORM_SCHEMA';
    const EDIT_BULK_DATA = 'EDIT_BULK_DATA';

    private Security $security;

    public function __construct(Security $security)
    {
        $this->security = $security;
    }

    protected function supports($attribute, $subject)
    {
        return in_array($attribute, [
                self::EDIT_FORM_SCHEMA,
                self::EDIT_BULK_DATA,
            ], true);
    }

    protected function voteOnAttribute($attribute, $subject, TokenInterface $token)
    {
        $user = $token->getUser();

        if (!$user instanceof RemoteUser) {
            return false;
        }

        switch ($attribute) {
            case self::EDIT_FORM_SCHEMA:
                return $this->security->isGranted('ROLE_SUPER_ADMIN') ||
                    $this->security->isGranted(PermissionInterface::EDIT, new FormSchema());
            case self::EDIT_BULK_DATA:
                return $this->security->isGranted('ROLE_SUPER_ADMIN') ||
                    $this->security->isGranted(PermissionInterface::EDIT, new BulkData());
            default:
                return false;
        }
    }

    private function canEdit(): bool
    {
        if ($this->security->isGranted('ROLE_SUPER_ADMIN')) {
            return true;
        }

        return false;
    }
}
