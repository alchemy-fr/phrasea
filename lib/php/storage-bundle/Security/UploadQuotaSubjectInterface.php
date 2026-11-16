<?php

declare(strict_types=1);

namespace Alchemy\StorageBundle\Security;

/**
 * A user whose identifier varies for a same quota holder (e.g. one identifier
 * per browser session of a shared link): the multipart upload limits apply
 * to the quota key instead, so that changing the variable part does not reset them.
 */
interface UploadQuotaSubjectInterface
{
    /**
     * The user identifier itself or its fixed part: every upload owned by this
     * key or by "<key>:<anything>" counts against the same limits.
     */
    public function getUploadQuotaKey(): string;
}
