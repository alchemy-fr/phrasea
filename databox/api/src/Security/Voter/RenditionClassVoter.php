<?php

declare(strict_types=1);

namespace App\Security\Voter;

use Alchemy\AclBundle\Security\PermissionInterface;
use App\Entity\Core\RenditionClass;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

class RenditionClassVoter extends AbstractVoter
{
    const SCOPE_PREFIX = 'ROLE_RENDITION-CLASS:';

    protected function supports(string $attribute, $subject)
    {
        return $subject instanceof RenditionClass;
    }

    /**
     * @param RenditionClass $subject
     */
    protected function voteOnAttribute(string $attribute, $subject, TokenInterface $token)
    {
        $workspace = $subject->getWorkspace();
        if ($this->security->isGranted(PermissionInterface::OWNER, $workspace)) {
            return true;
        }

        switch ($attribute) {
            case self::CREATE:
                return $this->security->isGranted(self::SCOPE_PREFIX.'CREATE');
            case self::EDIT:
                return $this->security->isGranted(self::SCOPE_PREFIX.'EDIT');
            case self::DELETE:
                return $this->security->isGranted(self::SCOPE_PREFIX.'DELETE');
        }

        return false;
    }
}