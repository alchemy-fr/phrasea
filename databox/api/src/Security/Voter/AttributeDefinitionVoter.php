<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Core\AttributeDefinition;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

class AttributeDefinitionVoter extends AbstractVoter
{
    protected function supports(string $attribute, $subject)
    {
        return $subject instanceof AttributeDefinition;
    }

    /**
     * @param AttributeDefinition $subject
     */
    protected function voteOnAttribute(string $attribute, $subject, TokenInterface $token)
    {
        $workspace = $subject->getWorkspace();

        switch ($attribute) {
            case self::READ:
            case self::CREATE:
            case self::EDIT:
            case self::DELETE:
                return $this->security->isGranted(self::EDIT, $workspace);
        }

        return false;
    }
}
