<?php

declare(strict_types=1);

namespace App\Security\Voter;

use Alchemy\AclBundle\Security\PermissionInterface;
use Alchemy\AuthBundle\Security\JwtUser;
use App\Entity\UploadLink;
use App\Security\Acl\UploadLinkAclObject;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Creating upload links needs the `upload_link` permission; their creator manages them, the admins manage
 * every link.
 */
class UploadLinkVoter extends Voter
{
    final public const string CREATE = 'UPLOAD_LINK_CREATE';
    final public const string READ = 'UPLOAD_LINK_READ';
    final public const string EDIT = 'UPLOAD_LINK_EDIT';
    final public const string DELETE = 'UPLOAD_LINK_DELETE';

    public function __construct(private readonly Security $security)
    {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        if (self::CREATE === $attribute) {
            return null === $subject;
        }

        return $subject instanceof UploadLink && in_array($attribute, [self::READ, self::EDIT, self::DELETE], true);
    }

    /**
     * @param UploadLink|null $subject
     */
    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        if (!$user instanceof JwtUser) {
            return false;
        }

        if ($this->security->isGranted(JwtUser::ROLE_ADMIN)) {
            return true;
        }

        $canCreate = $this->security->isGranted(PermissionInterface::EDIT, new UploadLinkAclObject());
        if (self::CREATE === $attribute) {
            return $canCreate;
        }

        return $canCreate && $subject->getOwnerId() === $user->getId();
    }
}
