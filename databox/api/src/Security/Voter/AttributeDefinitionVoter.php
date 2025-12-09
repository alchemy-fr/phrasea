<?php

declare(strict_types=1);

namespace App\Security\Voter;

use Alchemy\AclBundle\Security\PermissionInterface;
use App\Entity\Core\AttributeDefinition;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

class AttributeDefinitionVoter extends AbstractVoter
{
    private const string SCOPE_PREFIX = 'attribute-definition:';
    final public const string VIEW_ATTRIBUTES = 'VIEW_ATTRIBUTES';

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

        if ($this->tokenHasScope($token, $attribute, self::SCOPE_PREFIX)) {
            return true;
        }

        return match ($attribute) {
            self::VIEW_ATTRIBUTES => $subject->getPolicy()->isPublic()
                || $this->hasAcl(PermissionInterface::VIEW, $subject->getPolicy(), $token),
            self::READ, self::CREATE, self::EDIT, self::DELETE => $this->security->isGranted(self::EDIT, $workspace, $token),
            default => false,
        };
    }
}
