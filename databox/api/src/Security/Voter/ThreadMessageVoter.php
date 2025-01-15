<?php

declare(strict_types=1);

namespace App\Security\Voter;

use Alchemy\AuthBundle\Security\JwtUser;
use App\Entity\Discussion\Message;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

class ThreadMessageVoter extends AbstractVoter
{
    protected function supports(string $attribute, $subject): bool
    {
        return $subject instanceof Message;
    }

    public function supportsType(string $subjectType): bool
    {
        return is_a($subjectType, Message::class, true);
    }

    /**
     * @param Message $subject
     */
    protected function voteOnAttribute(string $attribute, $subject, TokenInterface $token): bool
    {
        switch ($attribute) {
            case self::EDIT:
            case self::DELETE:
                $user = $token->getUser();

                return $user instanceof JwtUser && $subject->getAuthorId() === $user->getId();
        }

        return false;
    }
}
