<?php

declare(strict_types=1);

namespace App\Security\Voter;

use Alchemy\AuthBundle\Security\JwtUser;
use App\Entity\Automation\SavedSearchAutomation;
use App\Entity\SavedSearch\SavedSearch;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;

/**
 * Automations act as the owner of their saved search: only this owner (or an admin,
 * through AdminVoter) may manage them, never a user granted on the saved search.
 */
class SavedSearchAutomationVoter extends AbstractVoter
{
    final public const string RUN = 'RUN';

    /**
     * On a saved search: create and list its automations.
     */
    final public const string MANAGE_AUTOMATIONS = 'MANAGE_AUTOMATIONS';

    protected function supports(string $attribute, $subject): bool
    {
        return $subject instanceof SavedSearchAutomation
            || ($subject instanceof SavedSearch && self::MANAGE_AUTOMATIONS === $attribute);
    }

    #[\Override]
    public function supportsType(string $subjectType): bool
    {
        return is_a($subjectType, SavedSearchAutomation::class, true)
            || is_a($subjectType, SavedSearch::class, true);
    }

    /**
     * @param SavedSearchAutomation|SavedSearch $subject
     */
    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $savedSearch = $subject instanceof SavedSearchAutomation ? $subject->getSavedSearch() : $subject;
        if (!$savedSearch instanceof SavedSearch) {
            return false;
        }

        $user = $token->getUser();
        $isOwner = $user instanceof JwtUser && $savedSearch->getOwnerId() === $user->getId();

        if ($subject instanceof SavedSearch) {
            return $isOwner;
        }

        return match ($attribute) {
            self::CREATE, self::READ, self::EDIT, self::DELETE, self::RUN => $isOwner,
            default => false,
        };
    }
}
