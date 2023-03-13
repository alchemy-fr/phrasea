<?php

declare(strict_types=1);

namespace App\Security\Voter;

use Alchemy\AclBundle\Security\PermissionInterface;
use App\Entity\Template\TemplateAttribute;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

class TemplateAttributeVoter extends AbstractVoter
{
    protected function supports(string $attribute, $subject)
    {
        return $subject instanceof TemplateAttribute;
    }

    /**
     * @param TemplateAttribute $subject
     */
    protected function voteOnAttribute(string $attribute, $subject, TokenInterface $token)
    {
        switch ($attribute) {
            case self::READ:
                return $this->security->isGranted(self::READ, $subject->getTemplate())
                    && (
                        $subject->getDefinition()->getClass()->isPublic()
                        || $this->security->isGranted(PermissionInterface::VIEW, $subject->getDefinition()->getClass())
                    );
            case self::CREATE:
            case self::EDIT:
            case self::DELETE:
                return $this->security->isGranted(AbstractVoter::EDIT, $subject->getTemplate());
        }

        return false;
    }
}
