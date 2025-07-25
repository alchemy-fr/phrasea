<?php

declare(strict_types=1);

namespace App\Security\Voter;

use Alchemy\AclBundle\Security\PermissionInterface;
use App\Entity\Template\TemplateAttribute;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

class TemplateAttributeVoter extends AbstractVoter
{
    protected function supports(string $attribute, $subject): bool
    {
        return $subject instanceof TemplateAttribute;
    }

    public function supportsType(string $subjectType): bool
    {
        return is_a($subjectType, TemplateAttribute::class, true);
    }

    /**
     * @param TemplateAttribute $subject
     */
    protected function voteOnAttribute(string $attribute, $subject, TokenInterface $token): bool
    {
        return match ($attribute) {
            self::READ => $this->security->isGranted(self::READ, $subject->getTemplate())
                && (
                    $subject->getDefinition()->getPolicy()->isPublic()
                    || $this->hasAcl(PermissionInterface::VIEW, $subject->getDefinition()->getPolicy(), $token)
                ),
            self::CREATE, self::EDIT, self::DELETE => $this->security->isGranted(AbstractVoter::EDIT, $subject->getTemplate()),
            default => false,
        };
    }
}
