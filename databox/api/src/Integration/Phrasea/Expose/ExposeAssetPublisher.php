<?php

declare(strict_types=1);

namespace App\Integration\Phrasea\Expose;

use App\Entity\Core\Asset;
use App\Entity\Integration\IntegrationToken;
use App\Integration\IntegrationConfig;
use App\Integration\Phrasea\Expose\Sync\ExposeAsset;
use App\Integration\Phrasea\Expose\Sync\ExposeSubDefinition;
use App\Model\MediaClip;
use App\Service\Clip\ClipException;
use App\Service\Clip\ClipFamily;
use App\Service\Storage\RenditionManager;
use Psr\Log\LoggerInterface;

/**
 * Publishes Databox assets to an Expose publication, keeping the published ones in sync:
 * the source file (cut to the clip) and the renditions used as preview and thumbnail.
 *
 * The published assets are matched by the annotations Databox stores on them
 * ("clientAnnotations"), so that publishing again only sends what changed.
 */
final readonly class ExposeAssetPublisher
{
    /**
     * The renditions published as sub-definitions, by the name of their use.
     */
    private const array SUB_DEFINITIONS = [
        'preview',
        'thumbnail',
    ];

    public function __construct(
        private ExposeClient $exposeClient,
        private RenditionManager $renditionManager,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @param array                                 $publication the publication as returned by Expose (with its assets)
     * @param callable(array $annotations): ?string $keyOf       the key of a published asset, null to ignore it
     *
     * @return array<string, ExposeAsset> indexed by key
     */
    public function readPublicationAssets(array $publication, callable $keyOf): array
    {
        $exposeAssets = [];
        foreach ($publication['assets'] ?? [] as $asset) {
            if (empty($asset['clientAnnotations'])) {
                continue;
            }

            $annotations = json_decode((string) $asset['clientAnnotations'], true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($annotations)) {
                continue;
            }
            $key = $keyOf($annotations);
            if (null === $key) {
                continue;
            }

            $subDefinitions = array_map(function (array $subDef): ExposeSubDefinition {
                $subDefAnnotations = json_decode($subDef['clientAnnotations'] ?? '{}', true, 512, JSON_THROW_ON_ERROR);

                return new ExposeSubDefinition(
                    $subDef['id'],
                    $subDef['name'],
                    $subDefAnnotations['renditionId'] ?? '',
                    $subDefAnnotations['fileId'] ?? '',
                    $subDefAnnotations['clip'] ?? null,
                );
            }, $asset['subDefinitions'] ?? []);

            $exposeAssets[$key] = new ExposeAsset(
                $asset['id'],
                $key,
                $annotations['fileId'] ?? '',
                $asset['title'] ?? null,
                $asset['description'] ?? null,
                $asset['translations'] ?? [],
                $subDefinitions,
                $annotations['clip'] ?? null,
            );
        }

        return $exposeAssets;
    }

    /**
     * Publishes an asset which has a source file, or updates its published version: the file is
     * published again when it (or its clip) changed, the properties are patched when they changed.
     *
     * @param MediaClip|null   $baseClip    the clip of the asset, restricted to the family of each published file
     * @param string           $key         the key of the published asset (see readPublicationAssets())
     * @param array            $annotations what identifies the published asset (the file and clip annotations are added)
     * @param ExposeAsset|null $existing    the published version of the asset
     *
     * @return ExposeAsset|null the published asset, null when its source could not be clipped (nothing is published then)
     */
    public function publish(
        IntegrationConfig $config,
        IntegrationToken $token,
        string $publicationId,
        Asset $asset,
        ?MediaClip $baseClip,
        string $key,
        array $annotations,
        ?ExposeAsset $existing,
    ): ?ExposeAsset {
        $source = $asset->getSource() ?? throw new \InvalidArgumentException(sprintf('Asset "%s" has no source file', $asset->getId()));
        $fileId = $source->getId();
        $sourceClip = ClipFamily::restrict($baseClip, $source->getType());
        $clipHash = $sourceClip?->getHash();

        $assetProperties = $this->exposeClient->getAssetProperties($asset, [
            'clientAnnotations' => json_encode(self::fileAnnotations($annotations + [
                'fileId' => $fileId,
            ], $clipHash), JSON_THROW_ON_ERROR),
        ]);

        if (null !== $existing && $existing->fileId === $fileId && $existing->clipHash === $clipHash) {
            $exposeAssetId = $existing->id;
            if (
                $assetProperties['title'] !== $existing->title
                || $assetProperties['description'] !== $existing->description
                || $assetProperties['translations'] !== $existing->translations
            ) {
                $this->exposeClient->putAsset($config, $token, $exposeAssetId, $assetProperties);
            }
            $publishedSubDefinitions = $existing->subDefinitions;
        } else {
            if (null !== $existing) {
                $this->exposeClient->deleteAsset($config, $token, $existing->id);
            }

            $exposeAssetId = $this->postAsset($config, $token, $publicationId, $asset, $assetProperties, $sourceClip, $annotations);
            if (null === $exposeAssetId) {
                return null;
            }
            $publishedSubDefinitions = [];
        }

        $subDefinitions = $this->publishSubDefinitions($config, $token, $exposeAssetId, $asset, $baseClip, $publishedSubDefinitions, $annotations);

        return new ExposeAsset(
            $exposeAssetId,
            $key,
            $fileId,
            $assetProperties['title'],
            $assetProperties['description'],
            $assetProperties['translations'],
            $subDefinitions,
            $clipHash,
        );
    }

    /**
     * @param ExposeSubDefinition[] $published
     *
     * @return ExposeSubDefinition[] the sub-definitions of the published asset
     */
    private function publishSubDefinitions(
        IntegrationConfig $config,
        IntegrationToken $token,
        string $exposeAssetId,
        Asset $asset,
        ?MediaClip $baseClip,
        array $published,
        array $annotations,
    ): array {
        $toDelete = [];
        foreach ($published as $subDef) {
            $toDelete[$subDef->subDefinitionName] = $subDef;
        }

        $subDefinitions = [];
        foreach (self::SUB_DEFINITIONS as $renditionName) {
            $rendition = $this->renditionManager->getAssetRenditionUsedAs($renditionName, $asset->getId());
            $renditionFile = $rendition?->getFile();
            if (null === $renditionFile) {
                continue;
            }

            // The image thumbnail of a video only gets the crop
            $renditionClip = ClipFamily::restrict($baseClip, $renditionFile->getType());
            $renditionClipHash = $renditionClip?->getHash();

            $existingSubDef = $toDelete[$renditionName] ?? null;
            unset($toDelete[$renditionName]);

            if (null !== $existingSubDef) {
                if ($existingSubDef->fileId === $renditionFile->getId() && $existingSubDef->clipHash === $renditionClipHash) {
                    $subDefinitions[] = $existingSubDef;
                    continue;
                }

                $this->exposeClient->deleteSubDefinition($config, $token, $existingSubDef->id);
            }

            try {
                $subDefinitionId = $this->exposeClient->postSubDefinition(
                    $config,
                    $token,
                    $exposeAssetId,
                    $renditionName,
                    $rendition,
                    [
                        'clientAnnotations' => json_encode(self::fileAnnotations([
                            'renditionId' => $rendition->getId(),
                            'fileId' => $renditionFile->getId(),
                        ], $renditionClipHash), JSON_THROW_ON_ERROR),
                    ],
                    $renditionClip,
                );
                $subDefinitions[] = new ExposeSubDefinition(
                    $subDefinitionId,
                    $renditionName,
                    $rendition->getId(),
                    $renditionFile->getId(),
                    $renditionClipHash,
                );
            } catch (ClipException $e) {
                // Never publish the whole file in place of the clipped one
                $this->logger->error('Expose publication: a rendition could not be clipped, it is not published', [
                    'exception' => $e,
                    'assetId' => $asset->getId(),
                    'renditionId' => $rendition->getId(),
                    'annotations' => $annotations,
                ]);
            }
        }

        foreach ($toDelete as $subDef) {
            $this->exposeClient->deleteSubDefinition($config, $token, $subDef->id);
        }

        return $subDefinitions;
    }

    /**
     * @return string|null the Expose asset id, null when the clipped file could not be produced
     */
    private function postAsset(
        IntegrationConfig $config,
        IntegrationToken $token,
        string $publicationId,
        Asset $asset,
        array $assetProperties,
        ?MediaClip $clip,
        array $annotations,
    ): ?string {
        try {
            return $this->exposeClient->postAsset($config, $token, $publicationId, $asset, $assetProperties, $clip);
        } catch (ClipException $e) {
            // Never publish the whole file in place of the clipped one
            $this->logger->error('Expose publication: an asset could not be clipped, it is not published', [
                'exception' => $e,
                'assetId' => $asset->getId(),
                'annotations' => $annotations,
            ]);

            return null;
        }
    }

    /**
     * The clip hash is only set on clipped files: the assets published before the clips
     * existed are not uploaded again.
     */
    private static function fileAnnotations(array $annotations, ?string $clipHash): array
    {
        if (null !== $clipHash) {
            $annotations['clip'] = $clipHash;
        }

        return $annotations;
    }
}
