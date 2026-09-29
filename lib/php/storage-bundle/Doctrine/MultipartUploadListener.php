<?php

declare(strict_types=1);

namespace Alchemy\StorageBundle\Doctrine;

use Alchemy\StorageBundle\Entity\MultipartUpload;
use Alchemy\StorageBundle\Storage\PathGeneratorInterface;
use Alchemy\StorageBundle\Upload\MultipartUploadPlanner;
use Alchemy\StorageBundle\Upload\UploadManager;
use Alchemy\StorageBundle\Util\FileUtil;
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
    public function __construct(
        private UploadManager $uploadManager,
        private PathGeneratorInterface $pathGenerator,
        private MultipartUploadPlanner $planner,
    ) {
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
            $extension = FileUtil::getExtensionFromPath($entity->getFilename());
            $path = $this->pathGenerator->generatePath($extension);

            // Validate the plan before creating anything on S3.
            $chunkSize = $this->planner->resolveChunkSize($entity->getSize());

            $uploadData = $this->uploadManager->prepareMultipartUpload($path, $entity->getType());
            $uploadId = $uploadData->get('UploadId');
            $entity->setUploadId($uploadId);
            $entity->setPath($path);
            $entity->setChunkSize($chunkSize);
            $entity->setUrls($this->uploadManager->getSignedUrls(
                $uploadId,
                $path,
                1,
                $this->planner->getPartCount($entity->getSize(), $chunkSize),
            ));
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
