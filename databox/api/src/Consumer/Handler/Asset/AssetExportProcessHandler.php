<?php

declare(strict_types=1);

namespace App\Consumer\Handler\Asset;

use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use Alchemy\CoreBundle\Util\DoctrineUtil;
use Alchemy\CoreBundle\Util\FilesystemUtils;
use Alchemy\CoreBundle\Util\StringUtil;
use Alchemy\CoreBundle\Util\ZipUtil;
use Alchemy\MetadataManipulatorBundle\MetadataManipulator;
use Alchemy\StorageBundle\Storage\FileStorageManager;
use Alchemy\StorageBundle\Storage\PathGeneratorInterface;
use Alchemy\StorageBundle\Util\FileUtil;
use App\Entity\Basket\BasketAsset;
use App\Entity\Core\Asset;
use App\Entity\Core\AssetAttachment;
use App\Entity\Core\AssetExport;
use App\Entity\Core\AssetRendition;
use App\Entity\Core\File;
use App\Entity\Core\Workspace;
use App\Integration\PusherTrait;
use App\Model\ExportStatusEnum;
use App\Model\MediaClip;
use App\Model\UserData;
use App\Repository\Core\AssetRenditionRepository;
use App\Security\Voter\AbstractVoter;
use App\Service\Asset\Attribute\AssetNameResolver;
use App\Service\Asset\Attribute\AttributeMetadataEmbedder;
use App\Service\Asset\FileFetcher;
use App\Service\Clip\ClipException;
use App\Service\Clip\ClipResolver;
use App\Service\Clip\MediaClipper;
use App\Service\Metadata\FileMetadataEmbedder;
use App\Service\Metadata\RenditionDefinitionMetadataEmbedder;
use App\Service\Workspace\TermsManager;
use App\Service\Workspace\TermsPdfGenerator;
use Doctrine\ORM\EntityManagerInterface;
use PHPExiftool\Driver\Metadata\MetadataBag;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class AssetExportProcessHandler
{
    use PusherTrait;
    use SecurityAwareTrait;

    /**
     * Items in a batch, per file downloaded at once.
     */
    private const int BATCH_SIZE_FACTOR = 4;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AssetNameResolver $assetNameResolver,
        private readonly FileFetcher $fileFetcher,
        private readonly MetadataManipulator $metadataManipulator,
        private readonly RenditionDefinitionMetadataEmbedder $definitionMetadataEmbedder,
        private readonly FileMetadataEmbedder $fileMetadataEmbedder,
        private readonly FileStorageManager $fileStorageManager,
        private readonly PathGeneratorInterface $pathGenerator,
        private readonly AttributeMetadataEmbedder $attributeMetadataEmbedder,
        private readonly TermsManager $termsManager,
        private readonly TermsPdfGenerator $termsPdfGenerator,
        private readonly LoggerInterface $logger,
        private readonly MediaClipper $mediaClipper,
        #[Autowire(env: 'int:EXPORT_DOWNLOAD_CONCURRENCY')]
        private readonly int $downloadConcurrency,
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

            // Scalars only: the entity manager is cleared after each batch
            $items = $this->getExportItems($export);
            $total = count($items) + 2;
            $i = 0;
            $fileCount = 0;

            $archiveDir = sys_get_temp_dir().'/'.uniqid('archive-dir');
            mkdir($archiveDir, 0755, true);
            $archiveSrc = null;

            // Hardcoded metadata depends only on the rendition definition: resolve lazily, once per definition.
            $definitionMetadataCache = [];
            $workspaceIds = [];
            $attachmentsExported = [];

            try {
                // The files of a batch are downloaded at once, then processed one by one, in the order of the items
                foreach (array_chunk($items, max(1, $this->downloadConcurrency) * self::BATCH_SIZE_FACTOR) as $batch) {
                    /** @var array<string, File> $toDownload */
                    $toDownload = [];
                    $batchTasks = [];
                    foreach ($batch as $item) {
                        $attachmentTasks = [];
                        if (!isset($attachmentsExported[$item['assetId']])) {
                            $attachmentsExported[$item['assetId']] = true;
                            $attachmentTasks = $this->prepareAttachments($item['assetId'], $userData, $archiveDir, $toDownload);
                        }

                        $batchTasks[] = [
                            'renditions' => $this->prepareRenditions($item, $renditionIds, $userData, $archiveDir, $toDownload),
                            'attachments' => $attachmentTasks,
                        ];
                    }

                    $failures = $this->fileFetcher->getFiles($toDownload, $this->downloadConcurrency);

                    foreach ($batch as $k => $item) {
                        $clip = MediaClip::fromArray($item['clip']);

                        // The attribute values depend on the rendition definition, and the source
                        // overrides on the exported file: cache both by what they depend on.
                        $sourceMetadataCache = [];
                        $attributeMetadataCache = [];

                        foreach ($batchTasks[$k]['renditions'] as $task) {
                            if (isset($failures[$task['path']])) {
                                throw $failures[$task['path']];
                            }

                            if ($this->exportRendition($task, $item, $clip, $sourceMetadataCache, $attributeMetadataCache, $definitionMetadataCache)) {
                                ++$fileCount;
                                $workspaceIds[$task['rendition']->getAsset()->getWorkspaceId()] = true;
                            }
                        }

                        foreach ($batchTasks[$k]['attachments'] as $task) {
                            if (isset($failures[$task['path']])) {
                                $this->logger->error('Failed to fetch attachment file for export', [
                                    'exception' => $failures[$task['path']],
                                    'assetId' => $item['assetId'],
                                    'attachmentId' => $task['attachmentId'],
                                ]);

                                continue;
                            }

                            ++$fileCount;
                        }

                        $this->triggerExportPush($export->getId(), 'progress', [
                            'progress' => ++$i / $total,
                        ]);
                    }

                    // The entities of a batch are used from its preparation to its processing
                    $this->em->clear();
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

                $this->exportTermsPdf(array_keys($workspaceIds), $archiveDir);

                $archivePath = $this->pathGenerator->generatePath('zip', 'exports/');
                $archiveSrc = sys_get_temp_dir().'/'.uniqid('archive-file').'.zip';

                ZipUtil::zipDirectory($archiveDir, $archiveSrc, 'content');
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

                // The signed URL is only served by GET /asset-exports/{id}, to the export owner
                $this->triggerExportPush($export->getId(), 'ready', []);
            } finally {
                FilesystemUtils::rrmdir($archiveDir);
                if (null !== $archiveSrc && is_file($archiveSrc)) {
                    unlink($archiveSrc);
                }
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

    /**
     * The clip of a basket item replaces the region of interest of its asset.
     *
     * @return list<array{assetId: string, basketAssetId: string|null, clip: array|null}>
     */
    private function getExportItems(AssetExport $export): array
    {
        $ignoreRoi = $export->isIgnoreRoi();
        $basketAssetIds = $export->getBasketAssets();
        if (null === $basketAssetIds) {
            $assetIds = $export->getAssets();

            /** @var array<string, Asset> $assets */
            $assets = [];
            if (!$ignoreRoi) {
                foreach ($this->em->getRepository(Asset::class)->findBy(['id' => $assetIds]) as $asset) {
                    $assets[$asset->getId()] = $asset;
                }
            }

            return array_map(static fn (string $assetId): array => [
                'assetId' => $assetId,
                'basketAssetId' => null,
                'clip' => isset($assets[$assetId]) ? ClipResolver::forAsset($assets[$assetId], $ignoreRoi)?->toArray() : null,
            ], $assetIds);
        }

        /** @var array<string, BasketAsset> $basketAssets */
        $basketAssets = [];
        foreach ($this->em->getRepository(BasketAsset::class)->findBy(['id' => $basketAssetIds]) as $basketAsset) {
            $basketAssets[$basketAsset->getId()] = $basketAsset;
        }

        $items = [];
        foreach ($basketAssetIds as $basketAssetId) {
            // Removed from its basket since the export was requested
            if (null === $basketAsset = $basketAssets[$basketAssetId] ?? null) {
                continue;
            }

            $items[] = [
                'assetId' => $basketAsset->getAsset()->getId(),
                'basketAssetId' => $basketAsset->getId(),
                'clip' => ClipResolver::forBasketAsset($basketAsset, $ignoreRoi)?->toArray(),
            ];
        }

        return $items;
    }

    /**
     * The renditions of the item the user may read, scheduled for download into $toDownload.
     *
     * @param array{assetId: string, basketAssetId: string|null, clip: array|null} $item
     * @param string[]                                                             $renditionIds
     * @param array<string, File>                                                  $toDownload
     *
     * @return list<array{rendition: AssetRendition, path: string, pathPrefix: string, ext: string}>
     */
    private function prepareRenditions(array $item, array $renditionIds, UserData $userData, string $archiveDir, array &$toDownload): array
    {
        // The same asset may be exported twice, from two basket items
        $nameSuffix = $item['basketAssetId'] ?? $item['assetId'];

        $renditions = $this->em->getRepository(AssetRendition::class)->findAssetRenditions($item['assetId'], [
            AssetRenditionRepository::OPT_DEFINITION_IDS => $renditionIds,
            AssetRenditionRepository::OPT_WITH_FILE => true,
        ]);

        $tasks = [];
        /** @var AssetRendition[] $renditions */
        foreach ($renditions as $rendition) {
            if (!$this->isGrantedForUser($userData, AbstractVoter::READ, $rendition)) {
                continue;
            }

            $file = $rendition->getFile();
            $extension = FileUtil::getExtensionFromType($file->getType());
            $ext = $extension ? '.'.$extension : '';

            $assetName = $this->assetNameResolver->resolveNameAsString($rendition->getAsset());

            $pathPrefix = sprintf('%s/%s-%s-%s', $archiveDir, StringUtil::slugify($rendition->getName()), StringUtil::slugify($assetName ?? ''), $nameSuffix);
            $path = $pathPrefix.$ext;
            if (isset($toDownload[$path])) {
                // The same asset listed twice in the batch: its file, processed in place, is exported once
                continue;
            }

            $toDownload[$path] = $file;
            $tasks[] = [
                'rendition' => $rendition,
                'path' => $path,
                'pathPrefix' => $pathPrefix,
                'ext' => $ext,
            ];
        }

        return $tasks;
    }

    /**
     * Clips the downloaded rendition file, then writes its metadata.
     *
     * @param array{rendition: AssetRendition, path: string, pathPrefix: string, ext: string} $task
     * @param array{assetId: string, basketAssetId: string|null, clip: array|null}            $item
     * @param array<string, MetadataBag|null>                                                 $sourceMetadataCache
     * @param array<string, MetadataBag|null>                                                 $attributeMetadataCache
     * @param array<string, MetadataBag|null>                                                 $definitionMetadataCache
     *
     * @return bool whether the file is exported
     */
    private function exportRendition(
        array $task,
        array $item,
        ?MediaClip $clip,
        array &$sourceMetadataCache,
        array &$attributeMetadataCache,
        array &$definitionMetadataCache,
    ): bool {
        $rendition = $task['rendition'];
        $asset = $rendition->getAsset();
        $file = $rendition->getFile();
        $renditionName = $rendition->getName();
        $path = $task['path'];

        if (null !== $clip) {
            try {
                $clipped = $this->mediaClipper->apply($path, $file->getType(), $clip);
                if ($clipped->transformed) {
                    unlink($path);
                    $path = $task['pathPrefix'].(null !== $clipped->extension ? '.'.$clipped->extension : $task['ext']);
                    rename($clipped->path, $path);
                }
            } catch (ClipException $e) {
                // Never export the whole file in place of the clipped one
                $this->logger->error('Failed to clip the exported file: it is not exported', [
                    'exception' => $e,
                    'assetId' => $item['assetId'],
                    'basketAssetId' => $item['basketAssetId'],
                    'rendition' => $renditionName,
                ]);
                @unlink($path);

                return false;
            } finally {
                $this->mediaClipper->cleanUp();
            }
        }

        $definition = $rendition->getDefinition();

        if ($definition?->isWriteMetadata()) {
            $bag = new MetadataBag();

            // the metadata the application overrode on the source file, and only
            // when that very file is the one being exported
            if (!array_key_exists($file->getId(), $sourceMetadataCache)) {
                $sourceMetadataCache[$file->getId()] = $this->fileMetadataEmbedder->buildExportedFileMetadataBag($asset, $file);
            }
            $sourceMetadata = $sourceMetadataCache[$file->getId()];

            if ($sourceMetadata instanceof MetadataBag) {
                foreach ($sourceMetadata as $meta) {
                    $bag->set($meta->getTagGroup()->getId(), $meta);
                }
            }

            if (!array_key_exists($definition->getId(), $attributeMetadataCache)) {
                $attributeMetadataCache[$definition->getId()] = $this->attributeMetadataEmbedder->buildMetadataBag($asset, $definition);
            }
            $attributeMetadata = $attributeMetadataCache[$definition->getId()];

            if ($attributeMetadata instanceof MetadataBag) {
                foreach ($attributeMetadata as $meta) {
                    $bag->set($meta->getTagGroup()->getId(), $meta);
                }
            }

            if (!array_key_exists($definition->getId(), $definitionMetadataCache)) {
                $definitionMetadataCache[$definition->getId()] = $this->definitionMetadataEmbedder->buildMetadataBag($definition);
            }
            $definitionMetadata = $definitionMetadataCache[$definition->getId()];
            if (null !== $definitionMetadata) {
                foreach ($definitionMetadata as $meta) {
                    $bag->set($meta->getTagGroup()->getId(), $meta);
                }
            }

            if ($bag->count() > 0) {
                try {
                    $writer = $this->metadataManipulator->createWriter();
                    $writer->disableConversion();

                    $tmpFile = sys_get_temp_dir().'/'.uniqid('metadata-file');
                    $writer->write($path, $bag, destination: $tmpFile);
                    unlink($path);
                    rename($tmpFile, $path);
                } catch (\Throwable $e) {
                    // The rendition file format may not support metadata writing; skip embedding for this file.
                    $this->logger->error('Failed to write metadata into exported file', [
                        'exception' => $e,
                        'assetId' => $item['assetId'],
                        'rendition' => $renditionName,
                    ]);
                }
            }
        }

        return true;
    }

    /**
     * The attachments of the asset the user may read, scheduled for download into $toDownload.
     *
     * @param array<string, File> $toDownload
     *
     * @return list<array{attachmentId: string, path: string}>
     */
    private function prepareAttachments(string $assetId, UserData $userData, string $archiveDir, array &$toDownload): array
    {
        $tasks = [];

        $asset = $this->em->find(Asset::class, $assetId);
        if (!$asset instanceof Asset || !$this->isGrantedForUser($userData, AbstractVoter::READ, $asset)) {
            return $tasks;
        }

        /** @var AssetAttachment[] $attachments */
        $attachments = $this->em->getRepository(AssetAttachment::class)->findBy([
            'asset' => $assetId,
        ], [
            'priority' => 'DESC',
        ]);

        foreach ($attachments as $attachment) {
            $attachedAsset = $attachment->getAttachment();
            $file = $attachedAsset?->getSource();
            if (null === $file) {
                continue;
            }

            if (!$this->isGrantedForUser($userData, AbstractVoter::READ, $attachedAsset)) {
                continue;
            }

            $extension = FileUtil::getExtensionFromType($file->getType());
            $ext = $extension ? '.'.$extension : '';

            $name = $attachment->getName() ?: pathinfo($file->getFileName(), PATHINFO_FILENAME);

            $dir = $archiveDir.'/attachments';
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            $path = sprintf('%s/%s-%s%s', $dir, StringUtil::slugify($name), $attachment->getId(), $ext);
            $toDownload[$path] = $file;
            $tasks[] = [
                'attachmentId' => $attachment->getId(),
                'path' => $path,
            ];
        }

        return $tasks;
    }

    /**
     * @param string[] $workspaceIds
     */
    private function exportTermsPdf(array $workspaceIds, string $archiveDir): void
    {
        foreach ($workspaceIds as $workspaceId) {
            $workspace = $this->em->find(Workspace::class, $workspaceId);
            if (!$workspace instanceof Workspace || !$workspace->isAttachTermsToExports()) {
                continue;
            }

            $terms = $this->termsManager->getCurrentTerms($workspace);
            if (null === $terms) {
                continue;
            }

            try {
                $pdf = $this->termsManager->getPdfContent($terms) ?? $this->termsPdfGenerator->generatePdf($terms);
                file_put_contents(sprintf('%s/terms-%s-v%d.pdf', $archiveDir, StringUtil::slugify($workspace->getSlug()), $terms->getVersion()), $pdf);
            } catch (\Throwable $e) {
                $this->logger->error('Failed to generate Terms & Conditions PDF for export', [
                    'exception' => $e,
                    'workspaceId' => $workspaceId,
                ]);
            }
        }
    }

    private function refresh(AssetExport $export): AssetExport
    {
        return DoctrineUtil::findStrict($this->em, AssetExport::class, $export->getId());
    }
}
