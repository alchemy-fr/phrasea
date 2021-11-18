<?php

declare(strict_types=1);

namespace Alchemy\StorageBundle\Doctrine;

use Alchemy\StorageBundle\Entity\MultipartUpload;
use Alchemy\StorageBundle\Storage\PathGenerator;
use Alchemy\StorageBundle\Upload\UploadManager;
use Aws\S3\Exception\S3Exception;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Events;

class MultipartUploadListener implements EventSubscriber
{
    private UploadManager $uploadManager;
    private PathGenerator $pathGenerator;

    public function __construct(UploadManager $uploadManager, PathGenerator $pathGenerator)
    {
        $this->uploadManager = $uploadManager;
        $this->pathGenerator = $pathGenerator;
    }

    public function postRemove(LifecycleEventArgs $args): void
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

    public function prePersist(LifecycleEventArgs $args): void
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

    public function getSubscribedEvents()
    {
        return [
            Events::postRemove,
            Events::prePersist,
        ];
    }
}
