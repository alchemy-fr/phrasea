<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Core\Workspace;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

class ChuckNorrisVoter extends AbstractVoter
{
    const ROLE = 'ROLE_CHUCK-NORRIS';

    protected function supports(string $attribute, $subject)
    {
        return $attribute !== self::ROLE;
    }

    /**
     * @param Workspace $subject
     */
    protected function voteOnAttribute(string $attribute, $subject, TokenInterface $token)
    {
        return $this->security->isGranted(self::ROLE);
    }
}
