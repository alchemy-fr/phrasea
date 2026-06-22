<?php

declare(strict_types=1);

namespace App\Security\Voter;

use Alchemy\AuthBundle\Security\JwtUser;
use App\Entity\Discussion\Thread;
use App\Service\Discussion\DiscussionManager;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

class ThreadVoter extends AbstractVoter
{
    public function __construct(
        private readonly DiscussionManager $discussionManager,
    ) {
    }

    protected function supports(string $attribute, $subject): bool
    {
        return $subject instanceof Thread;
    }

    #[\Override]
    public function supportsType(string $subjectType): bool
    {
        return is_a($subjectType, Thread::class, true);
    }

    /**
     * @param Thread $subject
     */
    protected function voteOnAttribute(string $attribute, $subject, TokenInterface $token): bool
    {
        $object = $this->discussionManager->getThreadObject($subject);

        return match ($attribute) {
            self::READ => $this->security->isGranted(self::READ, $object),
            self::EDIT => $this->security->isGranted(JwtUser::IS_AUTHENTICATED_FULLY)
                && $this->security->isGranted(self::READ, $object),
            default => false,
        };
    }
}
