<?php

declare(strict_types=1);

namespace App\Automation\Action;

use Alchemy\CoreBundle\Util\StringUtil;
use Alchemy\StorageBundle\Util\FileUtil;
use App\Automation\AutomationActionState;
use App\Automation\AutomationBatch;
use App\Automation\AutomationRunContext;
use App\Automation\Export\ExportPathTemplate;
use App\Automation\Export\RemoteDestinationConfig;
use App\Automation\Export\RemoteFilesystemFactory;
use App\Entity\Core\Asset;
use App\Entity\Core\AssetRendition;
use App\Entity\Core\File;
use App\Entity\Core\RenditionDefinition;
use App\Security\Voter\AbstractVoter;
use App\Service\Asset\Attribute\AssetNameResolver;
use App\Service\Asset\FileFetcher;
use Doctrine\ORM\EntityManagerInterface;
use League\Flysystem\FilesystemOperator;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

/**
 * Uploads files of the assets (the source file, or renditions chosen by the name of their definition)
 * to an S3 bucket, an SFTP or an FTP server.
 *
 * An existing remote file is kept when overwrite is off, and counted as exported: a failed asset
 * can be retried without uploading again what already went through.
 */
#[AsTaggedItem(index: self::TYPE)]
final class RemoteExportAction extends AbstractAutomationAction
{
    public const string TYPE = 'remote_export';

    final public const string SOURCE_RENDITION = 'source';

    private const string STATE_FILESYSTEM = 'filesystem';
    private const string STATE_CONNECTION_ERROR = 'connectionError';

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly RemoteFilesystemFactory $filesystemFactory,
        private readonly FileFetcher $fileFetcher,
        private readonly AssetNameResolver $assetNameResolver,
    ) {
    }

    public function getSecretFields(): array
    {
        return RemoteDestinationConfig::SECRET_FIELDS;
    }

    public function validateConfig(array $config): array
    {
        $destination = RemoteDestinationConfig::validate($config);
        $protocol = $destination['protocol'];

        $pathTemplate = $config['pathTemplate'] ?? null;
        if (null === $pathTemplate || '' === $pathTemplate) {
            $pathTemplate = ExportPathTemplate::DEFAULT;
        }
        if (!is_string($pathTemplate)) {
            self::throwViolations(['pathTemplate' => 'This value should be a string.']);
        }
        $pathTemplate = ltrim(trim($pathTemplate), '/');
        if (null !== $error = ExportPathTemplate::validate($pathTemplate)) {
            self::throwViolations(['pathTemplate' => $error]);
        }

        return [
            'protocol' => $protocol,
            'renditions' => $this->validateRenditions($config),
            'pathTemplate' => $pathTemplate,
            'overwrite' => self::getBool($config, 'overwrite', false),
            $protocol => $destination[$protocol],
        ];
    }

    public function processBatch(AutomationBatch $batch): void
    {
        $state = $batch->state;
        if (isset($state->data[self::STATE_CONNECTION_ERROR])) {
            foreach ($batch->assets as $asset) {
                $state->markFailed($asset, $state->data[self::STATE_CONNECTION_ERROR]);
            }

            return;
        }

        /** @var FilesystemOperator $filesystem */
        $filesystem = $state->data[self::STATE_FILESYSTEM] ??= $this->filesystemFactory->create($batch->config);
        $config = $batch->config;
        $renditions = $this->findRenditions($batch->assets, $config['renditions']);

        $assets = array_values($batch->assets);
        foreach ($assets as $i => $asset) {
            if (!$this->isGranted(AbstractVoter::READ, $asset)) {
                $state->markSkipped($asset, 'Not allowed to read the asset');
                continue;
            }

            $files = $this->getFiles($asset, $config['renditions'], $renditions[$asset->getId()] ?? []);
            if (empty($files)) {
                $state->markSkipped($asset, 'No file to export');
                continue;
            }

            $error = null;
            $variables = null;
            foreach ($files as $renditionName => $file) {
                $variables ??= $this->getAssetVariables($asset, $config['pathTemplate']);

                try {
                    $path = ExportPathTemplate::render($config['pathTemplate'], $variables + self::getFileVariables($file, $renditionName, $asset));
                    $this->upload($filesystem, $file, $path, $config['overwrite']);
                } catch (\Throwable $e) {
                    if (null !== $destinationError = RemoteDestinationConfig::getDestinationError($e)) {
                        // Unreachable destination: do not wait for each remaining asset to fail alike
                        $state->data[self::STATE_CONNECTION_ERROR] = $destinationError;
                        foreach (array_slice($assets, $i) as $remaining) {
                            $state->markFailed($remaining, $destinationError);
                        }

                        return;
                    }

                    $error = sprintf('%s: %s', $renditionName, $e->getMessage());
                    break;
                }
            }

            if (null !== $error) {
                $state->markFailed($asset, $error);
            } else {
                $state->markProcessed($asset);
            }
        }
    }

    public function finish(AutomationActionState $state, array $config, AutomationRunContext $context): void
    {
        // Closes the connection (FTP and SFTP adapters disconnect when destroyed)
        unset($state->data[self::STATE_FILESYSTEM]);
    }

    private function upload(FilesystemOperator $filesystem, File $file, string $path, bool $overwrite): void
    {
        if (!$overwrite && $filesystem->fileExists($path)) {
            return;
        }

        $tmp = tempnam(sys_get_temp_dir(), 'automation-export');
        try {
            $this->fileFetcher->getFile($file, path: $tmp);
            $fd = fopen($tmp, 'r');
            if (false === $fd) {
                throw new \RuntimeException(sprintf('Unable to open file %s', $tmp));
            }

            try {
                $filesystem->writeStream($path, $fd);
            } finally {
                if (is_resource($fd)) {
                    fclose($fd);
                }
            }
        } finally {
            if (file_exists($tmp)) {
                unlink($tmp);
            }
        }
    }

    /**
     * @param string[]                      $renditionNames
     * @param array<string, AssetRendition> $assetRenditions indexed by definition name
     *
     * @return array<string, File> the files to export, indexed by rendition name
     */
    private function getFiles(Asset $asset, array $renditionNames, array $assetRenditions): array
    {
        if (empty($renditionNames)) {
            $source = $asset->getSource();

            return null !== $source && $this->fileFetcher->isFetchable($source)
                ? [self::SOURCE_RENDITION => $source]
                : [];
        }

        $files = [];
        foreach ($renditionNames as $name) {
            $rendition = $assetRenditions[$name] ?? null;
            $file = $rendition?->getFile();
            // Missing, not built yet or not allowed: this rendition is skipped
            if (null === $file || !$this->fileFetcher->isFetchable($file) || !$this->isGranted(AbstractVoter::READ, $rendition)) {
                continue;
            }

            $files[$name] = $file;
        }

        return $files;
    }

    /**
     * @param Asset[]  $assets
     * @param string[] $names
     *
     * @return array<string, array<string, AssetRendition>> indexed by asset ID, then by definition name
     */
    private function findRenditions(array $assets, array $names): array
    {
        if (empty($names) || empty($assets)) {
            return [];
        }

        /** @var AssetRendition[] $renditions */
        $renditions = $this->em->createQueryBuilder()
            ->select('r, d, f')
            ->from(AssetRendition::class, 'r')
            ->innerJoin('r.definition', 'd')
            ->innerJoin('r.file', 'f')
            ->andWhere('r.asset IN (:assets)')
            ->andWhere('d.name IN (:names)')
            ->setParameter('assets', array_map(fn (Asset $asset): string => $asset->getId(), $assets))
            ->setParameter('names', $names)
            ->getQuery()
            ->getResult();

        $index = [];
        foreach ($renditions as $rendition) {
            $index[$rendition->getAsset()->getId()][$rendition->getDefinition()->getName()] = $rendition;
        }

        return $index;
    }

    /**
     * @return array<string, string|null>
     */
    private function getAssetVariables(Asset $asset, string $pathTemplate): array
    {
        $used = ExportPathTemplate::getUsedVariables($pathTemplate);
        $variables = [
            'assetId' => $asset->getId(),
            'workspace' => $asset->getWorkspace()?->getSlug() ?? StringUtil::slugify((string) $asset->getWorkspace()?->getName()),
            'collection' => null,
            'title' => null,
            // The creation date of the asset, not of the run: a retried asset goes to the same place
            'date' => $asset->getCreatedAt()?->format('Y-m-d'),
        ];

        if (in_array('collection', $used, true)) {
            $collectionName = $asset->getReferenceCollection()?->getName();
            $variables['collection'] = null !== $collectionName ? StringUtil::slugify($collectionName) : null;
        }
        if (in_array('title', $used, true)) {
            $title = StringUtil::slugify((string) $this->assetNameResolver->resolveNameAsString($asset));
            $variables['title'] = '' !== $title ? $title : $asset->getId();
        }

        return $variables;
    }

    /**
     * @return array<string, string|null>
     */
    private static function getFileVariables(File $file, string $renditionName, Asset $asset): array
    {
        $ext = FileUtil::getExtensionFromType($file->getType()) ?? $file->getExtension();
        $originalName = $file->getOriginalName();
        if (null === $originalName || '' === $originalName) {
            // A rendition file has no name of its own: the name of the source with the rendition extension
            $sourceName = $asset->getSource()?->getOriginalName();
            $baseName = null !== $sourceName && '' !== $sourceName ? pathinfo($sourceName, PATHINFO_FILENAME) : $asset->getId();
            $originalName = $baseName.(null !== $ext && '' !== $ext ? '.'.$ext : '');
        }

        return [
            'rendition' => $renditionName,
            'ext' => $ext,
            'originalName' => $originalName,
        ];
    }

    /**
     * @return string[]
     */
    private function validateRenditions(array $config): array
    {
        $value = $config['renditions'] ?? [];
        if (!is_array($value)) {
            self::throwViolations(['renditions' => 'This value should be a list.']);
        }

        $names = [];
        foreach ($value as $name) {
            if (!is_string($name) || '' === trim($name)) {
                self::throwViolations(['renditions' => 'This value should be a list of rendition names.']);
            }
            $names[] = trim($name);
        }
        $names = array_values(array_unique($names));

        foreach ($names as $name) {
            /** @var RenditionDefinition[] $definitions */
            $definitions = $this->em->getRepository(RenditionDefinition::class)->findBy(['name' => $name]);
            $readable = array_filter($definitions, fn (RenditionDefinition $definition): bool => $this->isGranted(AbstractVoter::READ, $definition));
            if (empty($readable)) {
                self::throwViolations(['renditions' => sprintf('Rendition "%s" not found.', $name)]);
            }
        }

        return $names;
    }

    private static function getBool(array $config, string $key, bool $default): bool
    {
        $value = $config[$key] ?? null;
        if (null === $value) {
            return $default;
        }
        if (!is_bool($value)) {
            self::throwViolations([$key => 'This value should be a boolean.']);
        }

        return $value;
    }
}
