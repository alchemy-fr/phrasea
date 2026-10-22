<?php

declare(strict_types=1);

namespace App\Service\Image;

use Alchemy\MessengerBundle\Listener\PostFlushStack;
use Alchemy\StorageBundle\Entity\MultipartUpload;
use Alchemy\StorageBundle\Storage\FileStorageManager;
use App\Consumer\Handler\Image\ProcessImageSet;
use App\Entity\Core\File;
use App\Entity\Core\ImageSet;
use App\Entity\Core\Workspace;
use App\Service\Asset\FileUrlResolver;
use App\Service\Storage\FileManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final readonly class ImageSetManager
{
    public const array RASTER_TYPES = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/gif',
    ];

    public function __construct(
        private EntityManagerInterface $em,
        private FileManager $fileManager,
        private FileStorageManager $fileStorageManager,
        private FileUrlResolver $fileUrlResolver,
        private InlineSvg $inlineSvg,
        private PostFlushStack $postFlushStack,
    ) {
    }

    /**
     * Creates the image from a completed S3 multipart upload. A raster image keeps the
     * upload as its original until ProcessImageSetHandler replaces it; an SVG is inlined.
     *
     * @param int $maxSize Limit of a raster image, in bytes
     */
    public function createFromUpload(MultipartUpload $upload, Workspace $workspace, int $maxSize): ImageSet
    {
        $imageSet = new ImageSet();
        $imageSet->setWorkspace($workspace);

        try {
            if (InlineSvg::TYPE === $upload->getType()) {
                $imageSet->setSvgData($this->inlineSvg->readUpload($upload));
                $this->em->persist($imageSet);

                return $imageSet;
            }

            if (!in_array($upload->getType(), self::RASTER_TYPES, true)) {
                throw new BadRequestHttpException(sprintf('Invalid image type "%s": allowed types are %s', $upload->getType() ?? '', implode(', ', [...self::RASTER_TYPES, InlineSvg::TYPE])));
            }
            if ($upload->getSize() > $maxSize) {
                throw new BadRequestHttpException(sprintf('Image must not exceed %d MB', $maxSize / 1024 / 1024));
            }
        } catch (BadRequestHttpException $e) {
            $this->deleteUploadedObject($upload);

            throw $e;
        }

        $file = $this->fileManager->createFileFromMultipartUpload($upload, $workspace);
        $imageSet->setImageFile($file);
        $this->em->persist($imageSet);

        $this->postFlushStack->addBusMessage(new ProcessImageSet($imageSet->getId()));

        return $imageSet;
    }

    public function resolveImageUrl(?ImageSet $imageSet): ?string
    {
        if (null === $imageSet) {
            return null;
        }
        if (null !== $svg = $imageSet->getSvgData()) {
            return InlineSvg::toDataUri($svg);
        }

        return $this->resolveFileUrl($imageSet->getImageFile());
    }

    /**
     * The thumbnail, falling back to the image until it is built (or for images
     * that never had one, such as the logos uploaded before thumbnails existed).
     */
    public function resolveThumbnailUrl(?ImageSet $imageSet): ?string
    {
        if (null === $imageSet) {
            return null;
        }
        if (null !== $svg = $imageSet->getSvgData()) {
            return InlineSvg::toDataUri($svg);
        }

        return $this->resolveFileUrl($imageSet->getThumbnailFile() ?? $imageSet->getImageFile());
    }

    /**
     * The image as a data URI, for documents that cannot fetch it (generated PDFs).
     */
    public function resolveDataUri(?ImageSet $imageSet): ?string
    {
        if (null === $imageSet) {
            return null;
        }
        if (null !== $svg = $imageSet->getSvgData()) {
            return InlineSvg::toDataUri($svg);
        }

        $file = $imageSet->getImageFile();
        if (null === $file || File::STORAGE_S3_MAIN !== $file->getStorage()) {
            return null;
        }

        $stream = $this->fileStorageManager->getStream($file->getPath());
        try {
            return sprintf('data:%s;base64,%s', $file->getType(), base64_encode(stream_get_contents($stream)));
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
    }

    /**
     * Loads the given images and their files in one query, then signs their URLs at once,
     * so that resolving the URLs of a page of owners does not query once per owner.
     *
     * @param array<ImageSet|null> $imageSets
     */
    public function preload(array $imageSets): void
    {
        $imageSets = array_filter($imageSets);
        if (empty($imageSets)) {
            return;
        }

        $uninitialized = array_map(
            fn (ImageSet $imageSet): string => $imageSet->getId(),
            array_filter($imageSets, $this->em->isUninitializedObject(...)),
        );
        if (!empty($uninitialized)) {
            $this->em->createQueryBuilder()
                ->select('i, f, t')
                ->from(ImageSet::class, 'i')
                ->leftJoin('i.imageFile', 'f')
                ->leftJoin('i.thumbnailFile', 't')
                ->andWhere('i.id IN (:ids)')
                ->setParameter('ids', array_values(array_unique($uninitialized)))
                ->getQuery()
                ->getResult();
        }

        $files = [];
        foreach ($imageSets as $imageSet) {
            foreach ([$imageSet->getImageFile(), $imageSet->getThumbnailFile()] as $file) {
                if (null !== $file) {
                    $files[$file->getId()] = $file;
                }
            }
        }

        // Images loaded earlier without their files
        $uninitializedFiles = array_keys(array_filter($files, $this->em->isUninitializedObject(...)));
        if (!empty($uninitializedFiles)) {
            $this->em->getRepository(File::class)->findBy(['id' => $uninitializedFiles]);
        }

        $this->fileUrlResolver->preloadUrls(array_values($files));
    }

    private function resolveFileUrl(?File $file): ?string
    {
        return null !== $file ? $this->fileUrlResolver->resolveUrl($file) : null;
    }

    private function deleteUploadedObject(MultipartUpload $upload): void
    {
        if ($this->fileStorageManager->has($upload->getPath())) {
            $this->fileStorageManager->delete($upload->getPath());
        }
    }
}
