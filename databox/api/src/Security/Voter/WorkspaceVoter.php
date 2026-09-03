<?php

declare(strict_types=1);

namespace App\Security\Voter;

use Alchemy\AclBundle\Security\PermissionInterface;
use Alchemy\AuthBundle\Security\JwtUser;
use Alchemy\CoreBundle\Cache\TemporaryCacheFactory;
use App\Entity\Core\Workspace;
use App\Service\Workspace\TermsManager;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Contracts\Cache\CacheInterface;

class WorkspaceVoter extends AbstractVoter implements AssetContainerVoterInterface
{
    final public const string SCOPE_PREFIX = 'workspace:';
    final public const string CREATE_COLLECTION = 'CREATE_COLLECTION';
    final public const string MANAGER_USERS = 'MANAGER_USERS';

    /**
     * Base read access, without requiring the workspace Terms & Conditions
     * to be signed. Used to fetch the workspace (and its terms) in order
     * to present and sign them.
     */
    final public const string READ_NO_TERMS = 'READ_NO_TERMS';

    private readonly CacheInterface $cache;

    public function __construct(
        TemporaryCacheFactory $cacheFactory,
        private readonly TermsManager $termsManager,
    ) {
        $this->cache = $cacheFactory->createCache();
    }

    protected function supports(string $attribute, $subject): bool
    {
        return $subject instanceof Workspace && !is_numeric($attribute);
    }

    #[\Override]
    public function supportsAttribute(string $attribute): bool
    {
        return !is_numeric($attribute);
    }

    #[\Override]
    public function supportsType(string $subjectType): bool
    {
        return is_a($subjectType, Workspace::class, true);
    }

    /**
     * @param Workspace $subject
     */
    protected function voteOnAttribute(string $attribute, $subject, TokenInterface $token): bool
    {
        return $this->cache->get(sprintf('%s,%s,%s', $attribute, $subject->getId(), spl_object_id($token)), fn () => $this->doVote($attribute, $subject, $token));
    }

    private function doVote(string $attribute, Workspace $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        $userId = $user instanceof JwtUser ? $user->getId() : false;
        $isCreator = fn (): bool => $userId && $subject->getOwnerId() === $userId;

        if ($this->tokenHasScope($token, $attribute, self::SCOPE_PREFIX)) {
            return true;
        }

        return match ($attribute) {
            // Create a new Workspace
            AbstractVoter::CREATE => $this->isAdmin(),
            self::CREATE_COLLECTION => $isCreator()
                || $this->hasAcl([
                    PermissionInterface::CREATE,
                    PermissionInterface::OWNER,
                ], $subject, $token),
            AbstractVoter::READ => $this->doVote(self::READ_NO_TERMS, $subject, $token)
                && $this->hasAcceptedTerms($subject, $token),
            self::READ_NO_TERMS => $isCreator()
                || $subject->isPublic()
                || $this->hasAcl([
                    PermissionInterface::VIEW,
                    PermissionInterface::OWNER,
                ], $subject, $token),
            AbstractVoter::EDIT => $isCreator()
                || $this->hasAcl([
                    PermissionInterface::EDIT,
                    PermissionInterface::OWNER,
                ], $subject, $token),
            AbstractVoter::DELETE => $isCreator()
                || $this->hasAcl([
                    PermissionInterface::DELETE,
                ], $subject, $token),
            // Add or remove users/groups to workspace (only VIEW permission)
            // TODO implement UI to add/remove users/groups on client
            self::MANAGER_USERS => $isCreator()
                || $this->hasAcl([
                    PermissionInterface::OWNER,
                ], $subject, $token)
                || $this->hasMetadata(DataboxExtraPermissionInterface::PERM_MANAGE_USERS, $subject, $token),
            AbstractVoter::EDIT_PERMISSIONS, AbstractVoter::OWNER => $isCreator()
                || $this->hasAcl(PermissionInterface::OWNER, $subject, $token),
            AssetContainerVoterInterface::ASSET_VIEW => $isCreator()
                || $this->hasAcl([
                    PermissionInterface::CHILD_VIEW,
                    PermissionInterface::CHILD_OWNER,
                    PermissionInterface::OWNER,
                ], $subject, $token),
            AssetContainerVoterInterface::ASSET_QUARANTINE => $isCreator()
                || $this->hasAcl([
                    PermissionInterface::OWNER,
                ], $subject, $token)
                || $this->hasMetadata(DataboxExtraPermissionInterface::PERM_QUARANTINE, $subject, $token),
            AssetContainerVoterInterface::ASSET_QUARANTINE_BYPASS => $isCreator()
                || $this->hasAcl([
                    PermissionInterface::OWNER,
                ], $subject, $token)
                || $this->hasMetadata(DataboxExtraPermissionInterface::PERM_QUARANTINE_BY_PASS, $subject, $token),
            AssetContainerVoterInterface::ASSET_CREATE => $isCreator()
                || $this->hasAcl([
                    PermissionInterface::CHILD_CREATE,
                    PermissionInterface::OWNER,
                ], $subject, $token),
            AssetContainerVoterInterface::ASSET_SHARE => $isCreator()
                || $this->hasAcl([
                    PermissionInterface::CHILD_SHARE,
                    PermissionInterface::OWNER,
                ], $subject, $token),
            AssetContainerVoterInterface::ASSET_EDIT_ATTRIBUTES => $isCreator()
                || $this->hasAcl([
                    PermissionInterface::CHILD_EDIT,
                    PermissionInterface::CHILD_OWNER,
                    PermissionInterface::OWNER,
                ], $subject, $token),
            AssetContainerVoterInterface::ASSET_EDIT => $isCreator()
                || $this->hasAcl([
                    PermissionInterface::CHILD_OPERATOR,
                    PermissionInterface::CHILD_OWNER,
                    PermissionInterface::OWNER,
                ], $subject, $token),
            AssetContainerVoterInterface::ASSET_DELETE => $isCreator()
                || $this->hasAcl([
                    PermissionInterface::CHILD_DELETE,
                    PermissionInterface::CHILD_OWNER,
                    PermissionInterface::OWNER,
                ], $subject, $token),
            AssetContainerVoterInterface::ASSET_OWNER => $isCreator()
                || $this->hasAcl([
                    PermissionInterface::CHILD_OWNER,
                    PermissionInterface::OWNER,
                ], $subject, $token),
            AssetContainerVoterInterface::ASSET_EDIT_PERMISSIONS => $isCreator()
                || $this->hasMetadata(DataboxExtraPermissionInterface::PERM_EDIT_PERMISSIONS, $subject, $token)
                || $this->hasAcl([
                    PermissionInterface::OWNER,
                ], $subject, $token),
            default => false,
        };
    }

    /**
     * Whether the current user may access the workspace content with regard
     * to its Terms & Conditions: no terms defined, user is the workspace
     * owner, anonymous access (terms are informational only), or the current
     * terms version has been signed.
     */
    private function hasAcceptedTerms(Workspace $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        $userId = $user instanceof JwtUser ? $user->getId() : null;

        if (null === $userId) {
            return true;
        }

        if ($subject->getOwnerId() === $userId) {
            return true;
        }

        $terms = $this->termsManager->getCurrentTerms($subject);
        if (null === $terms) {
            return true;
        }

        return $this->termsManager->hasSigned($terms, $userId);
    }
}
