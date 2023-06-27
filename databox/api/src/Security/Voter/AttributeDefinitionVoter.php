<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Core\AttributeDefinition;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

class AttributeDefinitionVoter extends AbstractVoter
{
    protected function supports(string $attribute, $subject): bool
    {
        return $subject instanceof AttributeDefinition;
    }

    /**
     * @param AttributeDefinition $subject
     */
    protected function voteOnAttribute(string $attribute, $subject, TokenInterface $token): bool
    {
        $workspace = $subject->getWorkspace();

        return match ($attribute) {
            self::READ, self::CREATE, self::EDIT, self::DELETE => $this->security->isGranted(self::EDIT, $workspace),
            default => false,
        };
    }
}
