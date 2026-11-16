<?php

declare(strict_types=1);

namespace App\Security;

use Alchemy\StorageBundle\Event\MultipartUploadCreateEvent;
use App\Entity\UploadLink;
use App\Exception\UploadLinkQuotaExceededException;
use App\Repository\UploadLinkRepository;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * A multipart upload is only started through an upload link while the declared file still fits in the
 * limits of the link: an exhausted link opens nothing on the storage. The file is counted against them
 * when the upload is completed (UploadLinkRepository::reserve()).
 */
#[AsEventListener]
final readonly class UploadLinkMultipartUploadListener
{
    public function __construct(
        private UploadLinkRepository $repository,
    ) {
    }

    public function __invoke(MultipartUploadCreateEvent $event): void
    {
        if (!$event->user instanceof UploadLinkUser) {
            return;
        }

        $link = $this->repository->find($event->user->getLinkId());
        if (!$link instanceof UploadLink || !$link->canFit($event->upload->getSize(), new \DateTimeImmutable())) {
            throw new UploadLinkQuotaExceededException();
        }
    }
}
