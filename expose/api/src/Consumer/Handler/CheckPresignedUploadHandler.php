<?php

declare(strict_types=1);

namespace App\Consumer\Handler;

use Alchemy\StorageBundle\Upload\UploadManager;
use App\Entity\Asset;
use App\Entity\SubDefinition;
use Aws\S3\Exception\S3Exception;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Stamp\DelayStamp;

/**
 * A presigned PUT binds neither the size nor the content of the file: they are
 * checked once the URL expired, when the stored object cannot change any more.
 * The real size and the type of the content replace the declared ones; an
 * entity whose content is refused is deleted, with its file.
 */
#[AsMessageHandler]
final readonly class CheckPresignedUploadHandler
{
    /**
     * Margin after the expiry of the URL, for a PUT started just before.
     */
    private const int MARGIN = 3600;

    public function __construct(
        private EntityManagerInterface $em,
        private UploadManager $uploadManager,
        private LoggerInterface $logger,
    ) {
    }

    public static function createDelayStamp(): DelayStamp
    {
        return new DelayStamp((UploadManager::PUT_URL_TTL + self::MARGIN) * 1000);
    }

    public function __invoke(CheckPresignedUpload $message): void
    {
        $entity = $this->em->find($message->entityClass, $message->id);
        if (!$entity instanceof Asset && !$entity instanceof SubDefinition) {
            return;
        }

        try {
            $size = $this->uploadManager->getStoredSize($entity->getPath());
        } catch (S3Exception $e) {
            if (404 !== $e->getStatusCode()) {
                throw $e;
            }

            // Nothing to serve, nothing to check
            $this->logger->warning(sprintf('Expose: the file of %s "%s" was never uploaded', $message->entityClass, $message->id));

            return;
        }

        $filename = $entity instanceof Asset ? $entity->getOriginalName() : $entity->getPath();
        try {
            $type = $this->uploadManager->validateStoredContent($entity->getPath(), $filename, $entity->getMimeType(), $size);
        } catch (BadRequestHttpException $e) {
            $this->logger->warning(sprintf('Expose: the file of %s "%s" is refused, it is deleted: %s', $message->entityClass, $message->id, $e->getMessage()));
            // The file goes with it (AssetListener)
            $this->em->remove($entity);
            $this->em->flush();

            return;
        }

        $entity->setSize($size);
        $entity->setMimeType($type);
        $this->em->flush();
    }
}
