<?php

declare(strict_types=1);

namespace App\Security;

use Alchemy\StorageBundle\Security\UploadQuotaSubjectInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Someone uploading through an upload link: no account, only the target of the link.
 */
final readonly class UploadLinkUser implements UserInterface, UploadQuotaSubjectInterface
{
    final public const string ROLE = 'ROLE_UPLOAD_LINK';

    /**
     * Sent by the browser along with the token of the link: tells apart the people sharing the same link.
     */
    final public const string SESSION_HEADER = 'X-Upload-Session';

    private const string IDENTIFIER_PREFIX = 'upload-link:';

    public function __construct(
        private string $linkId,
        private string $targetId,
        private string $ownerId,
        private ?string $sessionId = null,
    ) {
    }

    public function getRoles(): array
    {
        return [self::ROLE];
    }

    /**
     * The link and the browser session: the multipart uploads started by a holder of the link belong to
     * them only, another holder cannot complete or cancel them.
     */
    public function getUserIdentifier(): string
    {
        return self::IDENTIFIER_PREFIX.$this->linkId.(null !== $this->sessionId ? ':'.$this->sessionId : '');
    }

    /**
     * The holders of a link share its limits of multipart uploads, whatever their browser session.
     */
    public function getUploadQuotaKey(): string
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

    public function getSessionId(): ?string
    {
        return $this->sessionId;
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
