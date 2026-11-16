<?php

declare(strict_types=1);

namespace Alchemy\StorageBundle\Event;

use Alchemy\StorageBundle\Entity\MultipartUpload;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Dispatched before a multipart upload is created on the storage, once the
 * generic limits passed: a listener refuses it by throwing an HTTP exception
 * (e.g. a quota of the application which the declared size would exceed).
 */
final readonly class MultipartUploadCreateEvent
{
    public function __construct(
        public MultipartUpload $upload,
        public UserInterface $user,
    ) {
    }
}
