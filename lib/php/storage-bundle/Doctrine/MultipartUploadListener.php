<?php

declare(strict_types=1);

namespace Alchemy\StorageBundle\Doctrine;

use Alchemy\StorageBundle\Entity\MultipartUpload;
use Alchemy\StorageBundle\Storage\PathGenerator;
use Alchemy\StorageBundle\Upload\UploadManager;
use Aws\S3\Exception\S3Exception;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\PostRemoveEventArgs;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Events;

#[AsDoctrineListener(Events::postRemove)]
#[AsDoctrineListener(Events::prePersist)]
final readonly class MultipartUploadListener implements EventSubscriber
{

    public function __construct(private UploadManager $uploadManager, private PathGenerator $pathGenerator)
    {
    }

    public function postRemove(PostRemoveEventArgs $args): void
    {
        $entity = $args->getObject();
        if ($entity instanceof MultipartUpload && !$entity->isComplete()) {
            try {
                $this->uploadManager->cancelMultipartUpload($entity->getPath(), $entity->getUploadId());
            } catch (S3Exception $e) {
                if ('NoSuchUpload' !== $e->getAwsErrorCode()) {
                    throw $e;
                }
            }
        }
    }

    public function prePersist(PrePersistEventArgs $args): void
    {
        $entity = $args->getObject();
        if ($entity instanceof MultipartUpload && !$entity->hasPath()) {
            $extension = pathinfo($entity->getFilename(), PATHINFO_EXTENSION);
            $path = $this->pathGenerator->generatePath($extension);

            $uploadData = $this->uploadManager->prepareMultipartUpload($path, $entity->getType());
            $entity->setUploadId($uploadData->get('UploadId'));
            $entity->setPath($path);
        }
    }

    public function getSubscribedEvents(): array
    {
        return [
            Events::postRemove,
            Events::prePersist,
        ];
    }
}
