<?php

declare(strict_types=1);

namespace App\Consumer\Handler\Image;

use Alchemy\RenditionFactory\DTO\BuildConfig\BuildConfig;
use Alchemy\RenditionFactory\DTO\BuildConfig\FamilyBuildConfig;
use Alchemy\RenditionFactory\DTO\BuildConfig\Transformation;
use Alchemy\RenditionFactory\DTO\CreateRenditionOptions;
use Alchemy\RenditionFactory\DTO\FamilyEnum;
use Alchemy\RenditionFactory\Exception\NoBuildConfigException;
use Alchemy\RenditionFactory\Exception\UnsupportedSourceFileException;
use Alchemy\RenditionFactory\RenditionCreator;
use Alchemy\RenditionFactory\Transformer\Image\Imagine\ImagineTransformerModule;
use App\Entity\Core\File;
use App\Entity\Core\ImageSet;
use App\Service\Asset\FileFetcher;
use App\Service\Image\ImageSetManager;
use App\Service\Storage\FileManager;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Replaces the uploaded original of a raster image with an optimized version
 * and builds its thumbnail, both from the original.
 */
#[AsMessageHandler]
final readonly class ProcessImageSetHandler
{
    public const int IMAGE_SIZE = 2000;
    public const int IMAGE_QUALITY = 85;
    public const int THUMBNAIL_SIZE = 512;
    public const int THUMBNAIL_QUALITY = 70;

    public function __construct(
        private EntityManagerInterface $em,
        private RenditionCreator $renditionCreator,
        private FileFetcher $fileFetcher,
        private FileManager $fileManager,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(ProcessImageSet $message): void
    {
        $imageSet = $this->em->find(ImageSet::class, $message->getImageSetId());
        if (!$imageSet instanceof ImageSet || $imageSet->isSvg() || null !== $imageSet->getThumbnailFile()) {
            return;
        }
        $original = $imageSet->getImageFile();
        if (null === $original || !in_array($original->getType(), ImageSetManager::RASTER_TYPES, true)) {
            return;
        }

        $src = $this->fileFetcher->getFile($original);
        try {
            $image = $this->createFile($imageSet, $src, $original, self::IMAGE_SIZE, self::IMAGE_QUALITY);
            $thumbnail = $this->createFile($imageSet, $src, $original, self::THUMBNAIL_SIZE, self::THUMBNAIL_QUALITY);
        } catch (UnsupportedSourceFileException|NoBuildConfigException $e) {
            $this->logger->warning('Image could not be processed: the original is kept', [
                'exception' => $e,
                'imageSetId' => $imageSet->getId(),
            ]);

            return;
        } finally {
            $this->renditionCreator->cleanUp();
            if (file_exists($src)) {
                unlink($src);
            }
        }

        $imageSet->setImageFile($image);
        $imageSet->setThumbnailFile($thumbnail);
        // FileListener deletes its object from the storage
        $this->em->remove($original);
        $this->em->flush();
    }

    private function createFile(ImageSet $imageSet, string $src, File $original, int $size, int $quality): File
    {
        $output = $this->renditionCreator->createRendition($src, $original->getType(), self::createBuildConfig($size, $quality), new CreateRenditionOptions());

        $file = $this->fileManager->createFileFromPath($imageSet->getWorkspace(), $output->getPath(), $output->getType());
        $file->setNoAnalysisNeeded();

        return $file;
    }

    private static function createBuildConfig(int $size, int $quality): BuildConfig
    {
        return new BuildConfig([
            FamilyEnum::Image->value => new FamilyBuildConfig([
                new Transformation(ImagineTransformerModule::NAME, true, [
                    'format' => 'webp',
                    // Not part of the module's configuration tree, but handed to the image export
                    'quality' => $quality,
                    'filters' => [
                        'auto_rotate' => null,
                        'strip' => null,
                        'thumbnail' => [
                            'size' => [$size, $size],
                            'mode' => 'inset',
                            'allow_upscale' => false,
                        ],
                    ],
                ], null),
            ], []),
        ], null);
    }
}
