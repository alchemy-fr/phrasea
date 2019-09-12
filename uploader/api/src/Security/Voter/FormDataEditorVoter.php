<?php

declare(strict_types=1);

namespace App\Security\Voter;

use Alchemy\RemoteAuthBundle\Model\RemoteUser;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\Security;

class FormDataEditorVoter extends Voter
{
    const EDIT_FORM_SCHEMA = 'EDIT_FORM_SCHEMA';
    const EDIT_BULK_DATA = 'EDIT_BULK_DATA';

    /**
     * @var Security
     */
    private $security;

    public function __construct(Security $security)
    {
        $this->security = $security;
    }

    protected function supports($attribute, $subject)
    {
        return in_array($attribute, [
                self::EDIT_FORM_SCHEMA,
                self::EDIT_BULK_DATA,
            ], true) && null === $subject;
    }

    protected function voteOnAttribute($attribute, $subject, TokenInterface $token)
    {
        $user = $token->getUser();

        if (!$user instanceof RemoteUser) {
            return false;
        }

        switch ($attribute) {
            case self::EDIT_FORM_SCHEMA:
            case self::EDIT_BULK_DATA:
                return $this->canEdit();
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
