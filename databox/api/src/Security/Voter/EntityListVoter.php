<?php

declare(strict_types=1);

namespace App\Security\Voter;

use Alchemy\AclBundle\Security\PermissionInterface;
use App\Entity\Core\EntityList;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;

class EntityListVoter extends AbstractVoter
{
    final public const string SCOPE_PREFIX = 'entity-list:';

    /**
     * Manage the values of the list (create, edit, approve, merge, import, export, clear).
     */
    final public const string EDIT_VALUES = 'EDIT_VALUES';

    protected function supports(string $attribute, $subject): bool
    {
        return $subject instanceof EntityList;
    }

    #[\Override]
    public function supportsType(string $subjectType): bool
    {
        return is_a($subjectType, EntityList::class, true);
    }

    /**
     * @param EntityList $subject
     */
    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        if ($this->tokenHasScope($token, $attribute, self::SCOPE_PREFIX)) {
            return true;
        }

        $isWorkspaceEditor = fn (): bool => $this->security->isGranted(self::EDIT, $subject->getWorkspace());
        $isWorkspaceReader = fn (): bool => $this->security->isGranted(self::READ, $subject->getWorkspace());

        // The list ACEs add to the workspace membership, they never replace it.
        // OPERATOR (list properties) implies EDIT (values): masks have no hierarchy in the ACL bundle.
        // The list owner gets nothing from the ownership, or a former workspace editor would keep every right.
        $isOperator = fn (): bool => $isWorkspaceReader()
            && $this->hasAcl(PermissionInterface::OPERATOR, $subject, $token, ownershipGrants: false);
        $isValueEditor = fn (): bool => $isWorkspaceReader()
            && $this->hasAcl([PermissionInterface::EDIT, PermissionInterface::OPERATOR], $subject, $token, ownershipGrants: false);

        return match ($attribute) {
            self::CREATE, self::DELETE, self::EDIT_PERMISSIONS => $isWorkspaceEditor(),
            self::EDIT => $isWorkspaceEditor() || $isOperator(),
            self::EDIT_VALUES => $isWorkspaceEditor() || $isValueEditor(),
            self::READ => $isWorkspaceReader(),
            default => false,
        };
    }
}
