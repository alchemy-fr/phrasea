<?php

declare(strict_types=1);

namespace App\Security\Voter;

use Alchemy\AclBundle\Security\PermissionInterface;
use App\Entity\Core\Attribute;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

class AttributeVoter extends AbstractVoter
{
    protected function supports(string $attribute, $subject): bool
    {
        return $subject instanceof Attribute;
    }

    public function supportsType(string $subjectType): bool
    {
        return is_a($subjectType, Attribute::class, true);
    }

    /**
     * @param Attribute $subject
     */
    protected function voteOnAttribute(string $attribute, $subject, TokenInterface $token): bool
    {
        $attributeDefinition = $subject->getDefinition();

        return match ($attribute) {
            self::READ => $this->security->isGranted(self::READ, $subject->getAsset())
                && (
                    $attributeDefinition->getClass()->isPublic()
                    || $this->hasAcl(PermissionInterface::VIEW, $attributeDefinition->getClass(), $token)
                ),
            self::CREATE, self::EDIT, self::DELETE => $this->security->isGranted(AssetVoter::EDIT_ATTRIBUTES, $subject->getAsset())
                && (
                    $attributeDefinition->getClass()->isEditable()
                    || $this->hasAcl(PermissionInterface::EDIT, $attributeDefinition->getClass(), $token)
                ),
            default => false,
        };
    }
}
