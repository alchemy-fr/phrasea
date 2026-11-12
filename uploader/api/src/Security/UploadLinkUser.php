<?php

declare(strict_types=1);

namespace App\Security;

use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Someone uploading through an upload link: no account, only the target of the link.
 */
final readonly class UploadLinkUser implements UserInterface
{
    final public const string ROLE = 'ROLE_UPLOAD_LINK';

    private const string IDENTIFIER_PREFIX = 'upload-link:';

    public function __construct(
        private string $linkId,
        private string $targetId,
        private string $ownerId,
    ) {
    }

    public function getRoles(): array
    {
        return [self::ROLE];
    }

    /**
     * Stable for the link: the multipart uploads it starts belong to it.
     */
    public function getUserIdentifier(): string
    {
        return self::IDENTIFIER_PREFIX.$this->linkId;
    }

    public function getLinkId(): string
    {
        return $this->linkId;
    }

    public function getTargetId(): string
    {
        return $this->targetId;
    }

    /**
     * The creator of the link, to whom what is uploaded through it is attributed.
     */
    public function getOwnerId(): string
    {
        return $this->ownerId;
    }

    /**
     * The user the actions are recorded for (report logs): the creator of the link.
     */
    public function getId(): string
    {
        return $this->ownerId;
    }
}
