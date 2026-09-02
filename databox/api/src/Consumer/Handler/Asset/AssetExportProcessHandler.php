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
use App\Entity\Core\Asset;
use App\Entity\Core\AssetAttachment;
use App\Entity\Core\AssetExport;
use App\Entity\Core\AssetRendition;
use App\Entity\Core\RenditionDefinition;
use App\Entity\Core\Workspace;
use App\Integration\PusherTrait;
use App\Model\ExportStatusEnum;
use App\Model\UserData;
use App\Repository\Core\AssetRenditionRepository;
use App\Security\Voter\AbstractVoter;
use App\Service\Asset\Attribute\AssetNameResolver;
use App\Service\Asset\Attribute\AttributeMetadataEmbedder;
use App\Service\Asset\FileFetcher;
use App\Service\Metadata\RenditionDefinitionMetadataEmbedder;
use App\Service\Workspace\TermsManager;
use App\Service\Workspace\TermsPdfGenerator;
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
        private readonly TermsManager $termsManager,
        private readonly TermsPdfGenerator $termsPdfGenerator,
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
            $workspaceIds = [];

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

                        $definition = $rendition->getDefinition();

                        if ($definition?->isWriteMetadata()) {
                            $bag = new MetadataBag();

                            if (RenditionDefinition::BUILD_MODE_PICK_SOURCE === $definition->getBuildMode()) {
                                foreach ($file->getMetadataValues() as $tagGroupId => $values) {
                                    $meta = $this->metadataManipulator->createMetadata($tagGroupId);
                                    $tagGroup = $meta->getTagGroup();
                                    if (!$tagGroup->isWritable() || str_starts_with($tagGroupId, 'System:')) {
                                        continue;
                                    }

                                    if ($tagGroup->isMulti()) {
                                        $meta->setValue($values);
                                    } else {
                                        $meta->setValue(reset($values));
                                    }
                                    $bag->set($tagGroup->getId(), $meta);
                                }
                            }

                            if (false === $attributeMetadata) {
                                $attributeMetadata = $this->attributeMetadataEmbedder->buildMetadataBag($asset);
                            }

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

                                    $tmpFile = sys_get_temp_dir().'/'.uniqid('metadata-file');
                                    $writer->write($path, $bag, destination: $tmpFile);
                                    unlink($path);
                                    rename($tmpFile, $path);
                                } catch (\Throwable $e) {
                                    // The rendition file format may not support metadata writing; skip embedding for this file.
                                    $this->logger->error('Failed to write metadata into exported file', [
                                        'exception' => $e,
                                        'assetId' => $assetId,
                                        'rendition' => $renditionName,
                                    ]);
                                }
                            }
                        }

                        ++$fileCount;
                        $workspaceIds[$asset->getWorkspaceId()] = true;
                    }

                    $fileCount += $this->exportAttachments($assetId, $userData, $archiveDir);

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

                $this->exportTermsPdf(array_keys($workspaceIds), $archiveDir);

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

    private function exportAttachments(string $assetId, UserData $userData, string $archiveDir): int
    {
        $count = 0;

        $asset = $this->em->find(Asset::class, $assetId);
        if (!$asset instanceof Asset || !$this->isGrantedForUser($userData, AbstractVoter::READ, $asset)) {
            return $count;
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

            try {
                $this->fileFetcher->getFile($file, path: $path);
                ++$count;
            } catch (\Throwable $e) {
                $this->logger->error('Failed to fetch attachment file for export', [
                    'exception' => $e,
                    'assetId' => $assetId,
                    'attachmentId' => $attachment->getId(),
                ]);
            }
        }

        return $count;
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
