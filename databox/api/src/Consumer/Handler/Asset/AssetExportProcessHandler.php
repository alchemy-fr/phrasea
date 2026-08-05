<?php

declare(strict_types=1);

namespace App\Consumer\Handler\Asset;

use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use Alchemy\CoreBundle\Util\DoctrineUtil;
use Alchemy\CoreBundle\Util\FilesystemUtils;
use Alchemy\CoreBundle\Util\StringUtil;
use Alchemy\MetadataManipulatorBundle\MetadataManipulator;
use Alchemy\StorageBundle\Storage\FileStorageManager;
use Alchemy\StorageBundle\Storage\PathGeneratorInterface;
use Alchemy\StorageBundle\Storage\UrlSigner;
use Alchemy\StorageBundle\Util\FileUtil;
use Alchemy\Zippy\Zippy;
use App\Entity\Core\AssetExport;
use App\Entity\Core\AssetRendition;
use App\Integration\PusherTrait;
use App\Model\ExportStatusEnum;
use App\Repository\Core\AssetRenditionRepository;
use App\Security\Voter\AbstractVoter;
use App\Service\Asset\Attribute\AssetNameResolver;
use App\Service\Asset\Attribute\AttributeMetadataEmbedder;
use App\Service\Asset\FileFetcher;
use App\Service\Metadata\RenditionDefinitionMetadataEmbedder;
use Doctrine\ORM\EntityManagerInterface;
use PHPExiftool\Driver\Metadata\MetadataBag;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class AssetExportProcessHandler
{
    use PusherTrait;
    use SecurityAwareTrait;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AssetNameResolver $assetNameResolver,
        private readonly FileFetcher $fileFetcher,
        private readonly MetadataManipulator $metadataManipulator,
        private readonly RenditionDefinitionMetadataEmbedder $definitionMetadataEmbedder,
        private readonly FileStorageManager $fileStorageManager,
        private readonly PathGeneratorInterface $pathGenerator,
        private readonly UrlSigner $urlSigner,
        private readonly AttributeMetadataEmbedder $attributeMetadataEmbedder,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(AssetExportProcess $message): void
    {
        /** @var AssetExport $export */
        $export = DoctrineUtil::findStrict($this->em, AssetExport::class, $message->id);
        $export->setStatus(ExportStatusEnum::InProgress);
        $this->em->persist($export);
        $this->em->flush();

        $this->triggerExportPush($export->getId(), 'progress', [
            'progress' => 0,
        ]);

        try {
            $renditionIds = $export->getRenditions();
            $userData = $export->getUserData();

            $assets = $export->getAssets();
            $total = count($assets) + 2;
            $i = 0;
            $fileCount = 0;

            $archiveDir = sys_get_temp_dir().'/'.uniqid('archive-dir');
            mkdir($archiveDir, 0755, true);

            // Hardcoded metadata depends only on the rendition definition: resolve lazily, once per definition.
            $definitionMetadataCache = [];

            try {
                foreach ($assets as $assetId) {
                    $renditions = $this->em->getRepository(AssetRendition::class)->findAssetRenditions($assetId, [
                        AssetRenditionRepository::OPT_DEFINITION_IDS => $renditionIds,
                        AssetRenditionRepository::OPT_WITH_FILE => true,
                    ]);

                    // Attribute values to embed depend only on the asset: resolve once for all its renditions.
                    $attributeMetadata = false;

                    /** @var AssetRendition[] $renditions */
                    foreach ($renditions as $rendition) {
                        $asset = $rendition->getAsset();

                        if (!$this->isGrantedForUser($userData, AbstractVoter::READ, $rendition)) {
                            continue;
                        }

                        $file = $rendition->getFile();
                        $extension = FileUtil::getExtensionFromType($file->getType());
                        $ext = $extension ? '.'.$extension : '';

                        $assetName = $this->assetNameResolver->resolveNameAsString($asset);
                        $renditionName = $rendition->getName();

                        $path = sprintf('%s/%s-%s-%s%s', $archiveDir, StringUtil::slugify($renditionName), StringUtil::slugify($assetName ?? ''), $assetId, $ext);
                        $this->fileFetcher->getFile($file, path: $path);

                        // The exported file keeps the metadata already embedded in the rendition file:
                        // the source file metadata is not copied anymore.
                        $metadata = new MetadataBag();

                        // Embed attribute values into the metadata according to attribute definitions
                        // "writeMetadata", when the rendition definition allows it.
                        if ($rendition->getDefinition()->isWriteMetadata()) {
                            if (false === $attributeMetadata) {
                                $attributeMetadata = $this->attributeMetadataEmbedder->buildMetadataBag($asset);
                            }

                            if ($attributeMetadata instanceof MetadataBag) {
                                foreach ($attributeMetadata as $meta) {
                                    $metadata->set($meta->getTagGroup()->getId(), $meta);
                                }
                            }
                        }

                        // Embed the hardcoded metadata configured on the rendition definition.
                        $definition = $rendition->getDefinition();
                        if (!array_key_exists($definition->getId(), $definitionMetadataCache)) {
                            $definitionMetadataCache[$definition->getId()] = $this->definitionMetadataEmbedder->buildMetadataBag($definition);
                        }
                        $definitionMetadata = $definitionMetadataCache[$definition->getId()];
                        if (null !== $definitionMetadata) {
                            foreach ($definitionMetadata as $meta) {
                                $metadata->set($meta->getTagGroup()->getId(), $meta);
                            }
                        }

                        if (count($metadata) > 0) {
                            try {
                                $writer = $this->metadataManipulator->createWriter();

                                $tmpFile = sys_get_temp_dir().'/'.uniqid('metadata-file');
                                $writer->write($path, $metadata, destination: $tmpFile);
                                unlink($path);
                                rename($tmpFile, $path);
                            } catch (\Throwable $e) {
                                // The rendition file format may not support metadata writing; skip embedding for this file.
                                $this->logger->warning('Failed to write metadata into exported file', [
                                    'exception' => $e,
                                    'assetId' => $assetId,
                                    'rendition' => $renditionName,
                                ]);
                            }
                        }

                        ++$fileCount;
                    }

                    $this->em->clear();

                    $this->triggerExportPush($export->getId(), 'progress', [
                        'progress' => ++$i / $total,
                    ]);
                }

                $export = $this->refresh($export);

                if (0 === $fileCount) {
                    $export->setStatus(ExportStatusEnum::Failed);
                    $this->em->persist($export);
                    $this->em->flush();

                    $this->triggerExportPush($export->getId(), 'error', [
                        'error' => 'No files to export (insufficient permissions).',
                    ]);

                    return;
                }

                $archivePath = $this->pathGenerator->generatePath('zip', 'exports/');
                $archiveSrc = sys_get_temp_dir().'/'.uniqid('archive-file').'.zip';

                $zippy = Zippy::load();
                $zippy->create($archiveSrc, [
                    'content' => $archiveDir,
                ]);
                $this->triggerExportPush($export->getId(), 'progress', [
                    'progress' => ++$i / $total,
                ]);

                $fd = fopen($archiveSrc, 'r');
                if (false === $fd) {
                    throw new \RuntimeException(sprintf('Unable to open file %s', $archiveSrc));
                }
                $this->fileStorageManager->storeStream($archivePath, $fd);
                fclose($fd);
                $export->setPath($archivePath);

                $this->triggerExportPush($export->getId(), 'progress', [
                    'progress' => ++$i / $total,
                ]);

                $export->setStatus(ExportStatusEnum::Ready);
                $this->em->persist($export);
                $this->em->flush();

                $this->triggerExportPush($export->getId(), 'ready', [
                    'downloadUrl' => $this->urlSigner->getSignedUrl($archivePath),
                ]);
            } finally {
                FilesystemUtils::rrmdir($archiveDir);
            }
        } catch (\Throwable $e) {
            $export = $this->refresh($export);
            $export->setStatus(ExportStatusEnum::Failed);
            $this->em->persist($export);
            $this->em->flush();

            $this->triggerExportPush($export->getId(), 'error', [
                'error' => 'Unexpected error while preparing export.',
            ]);
        }
    }

    private function refresh(AssetExport $export): AssetExport
    {
        return DoctrineUtil::findStrict($this->em, AssetExport::class, $export->getId());
    }
}
