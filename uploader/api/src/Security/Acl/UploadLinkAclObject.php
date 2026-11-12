<?php

declare(strict_types=1);

namespace App\Security\Acl;

/**
 * Creating upload links (and managing one's own).
 */
final readonly class UploadLinkAclObject extends AbstractGlobalAclObject
{
    public const string OBJECT_KEY = 'upload_link';
}
