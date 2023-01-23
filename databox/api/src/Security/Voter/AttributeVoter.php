<?php

declare(strict_types=1);

namespace App\Security\Voter;

use Alchemy\AclBundle\Security\PermissionInterface;
use App\Entity\Core\Attribute;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

class AttributeVoter extends AbstractVoter
{
    protected function supports(string $attribute, $subject)
    {
        return $subject instanceof Attribute;
    }

    /**
     * @param Attribute $subject
     */
    protected function voteOnAttribute(string $attribute, $subject, TokenInterface $token)
    {
        switch ($attribute) {
            case self::READ:
                return $this->security->isGranted(self::READ, $subject->getAsset())
                    && (
                        $subject->getDefinition()->getClass()->isPublic()
                        || $this->security->isGranted(PermissionInterface::VIEW, $subject->getDefinition()->getClass())
                    );
            case self::CREATE:
            case self::EDIT:
            case self::DELETE:
                return $this->security->isGranted(AssetVoter::EDIT_ATTRIBUTES, $subject->getAsset())
                    && (
                        $subject->getDefinition()->getClass()->isEditable()
                        || $this->security->isGranted(PermissionInterface::EDIT, $subject->getDefinition()->getClass())
                    );
        }

        return false;
    }
}
