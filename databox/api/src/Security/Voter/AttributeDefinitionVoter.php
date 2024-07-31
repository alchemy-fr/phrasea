<?php

declare(strict_types=1);

namespace App\Security\Voter;

use Alchemy\AclBundle\Security\PermissionInterface;
use App\Entity\Core\AttributeDefinition;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

class AttributeDefinitionVoter extends AbstractVoter
{
    final public const VIEW_ATTRIBUTES = 'VIEW_ATTRIBUTES';

    protected function supports(string $attribute, $subject): bool
    {
        return $subject instanceof AttributeDefinition;
    }

    public function supportsType(string $subjectType): bool
    {
        return is_a($subjectType, AttributeDefinition::class, true);
    }

    /**
     * @param AttributeDefinition $subject
     */
    protected function voteOnAttribute(string $attribute, $subject, TokenInterface $token): bool
    {
        $workspace = $subject->getWorkspace();

        return match ($attribute) {
            self::VIEW_ATTRIBUTES => $subject->getClass()->isPublic()
                || $this->hasAcl(PermissionInterface::VIEW, $subject->getClass(), $token),
            self::READ, self::CREATE, self::EDIT, self::DELETE => $this->security->isGranted(self::EDIT, $workspace),
            default => false,
        };
    }
}
