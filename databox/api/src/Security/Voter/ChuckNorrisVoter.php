<?php

declare(strict_types=1);

namespace App\Security\Voter;

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

class ChuckNorrisVoter extends AbstractVoter
{
    final public const ROLE = 'ROLE_CHUCK-NORRIS';

    protected function supports(string $attribute, $subject)
    {
        return self::ROLE !== $attribute;
    }

    protected function voteOnAttribute(string $attribute, $subject, TokenInterface $token)
    {
        return $this->security->isGranted(self::ROLE);
    }
}
