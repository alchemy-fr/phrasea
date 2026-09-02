<?php

declare(strict_types=1);

namespace App\Api\OutputTransformer;

use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use Alchemy\CoreBundle\Cache\TemporaryCacheFactory;
use App\Api\Model\Output\AlternateUrlOutput;
use App\Api\Model\Output\FileOutput;
use App\Entity\Core\AlternateUrl;
use App\Entity\Core\Asset;
use App\Entity\Core\AssetFileVersion;
use App\Entity\Core\AssetRendition;
use App\Entity\Core\File;
use App\Security\Voter\AbstractVoter;
use App\Service\Asset\Attribute\AssetNameResolver;
use App\Service\Asset\FileUrlResolver;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Cache\CacheInterface;

class FileOutputTransformer implements OutputTransformerInterface
{
    use SecurityAwareTrait;
    use GroupsHelperTrait;

    private CacheInterface $cache;

    public function __construct(
        private readonly FileUrlResolver $fileUrlResolver,
        private readonly EntityManagerInterface $em,
        private readonly AssetNameResolver $assetNameResolver,
        TemporaryCacheFactory $temporaryCacheFactory,
    ) {
        $this->cache = $temporaryCacheFactory->createCache();
    }

    public function supports(string $outputClass, object $data): bool
    {
        return FileOutput::class === $outputClass && $data instanceof File;
    }

    /**
     * @param File $data
     */
    public function transform(object $data, string $outputClass, array &$context = []): object
    {
        $output = new FileOutput();
        $output->setCreatedAt($data->getCreatedAt());
        $output->setUpdatedAt($data->getUpdatedAt());
        $output->setId($data->getId());
        $output->setType($data->getType());
        $output->extension = $data->getExtension();
        $output->fileName = $data->getFileName();
        $output->setSize((int) $data->getSize());
        $output->checksum = $data->getChecksum();
        $output->docUniqueId = $data->getDocUniqueId();
        $output->analysis = $data->getAnalysis();

        if ($data->getWorkspace()->isFileAnalysisRequired()) {
            if ($data->isAnalyzed()) {
                $output->accepted = $data->isAccepted();
            }
        } else {
            $output->accepted = true;
        }

        if (!$data->isAccepted()) {
            $output->analysis = $data->getAnalysis();
        }

        if ($this->hasGroup(File::GROUP_METADATA, $context)) {
            $output->metadata = $data->getMetadata();
            $output->analysis = $data->getAnalysis();
        }

        // Only resolved when the file is the root resource (GET /files/{id}),
        // not when embedded in asset/rendition outputs.
        if ($this->hasGroup(File::GROUP_LIST, $context) || $this->hasGroup(File::GROUP_READ, $context)) {
            $output->usages = $this->resolveUsages($data);
        }

        if ($data->isPathPublic()) {
            $output->setUrl($this->fileUrlResolver->resolveUrl($data));
        }

        $urls = [];
        if (null !== $data->getAlternateUrls()) {
            foreach ($data->getAlternateUrls() as $type => $url) {
                $urls[] = new AlternateUrlOutput($type, $url, $this->resolveAlternateUrlLabel(
                    $data->getWorkspaceId(),
                    $type
                ));
            }
        }

        $output->setAlternateUrls($urls);

        return $output;
    }

    private function resolveUsages(File $file): array
    {
        $usages = [];

        /** @var Asset[] $assets */
        $assets = $this->em->getRepository(Asset::class)->findBy(['source' => $file->getId()]);
        foreach ($assets as $asset) {
            if (!$this->isGranted(AbstractVoter::READ, $asset)) {
                continue;
            }

            $usages[] = [
                'type' => 'source',
                'assetId' => $asset->getId(),
                'assetTitle' => $this->assetNameResolver->resolveNameAsString($asset),
            ];
        }

        /** @var AssetFileVersion[] $versions */
        $versions = $this->em->getRepository(AssetFileVersion::class)->findBy(['file' => $file->getId()]);
        foreach ($versions as $version) {
            $asset = $version->getAsset();
            if (null === $asset || !$this->isGranted(AbstractVoter::READ, $asset)) {
                continue;
            }

            $usages[] = [
                'type' => 'version',
                'assetId' => $asset->getId(),
                'assetTitle' => $this->assetNameResolver->resolveNameAsString($asset),
                'name' => $version->getName(),
            ];
        }

        /** @var AssetRendition[] $renditions */
        $renditions = $this->em->getRepository(AssetRendition::class)->findBy(['file' => $file->getId()]);
        foreach ($renditions as $rendition) {
            $asset = $rendition->getAsset();
            if (!$this->isGranted(AbstractVoter::READ, $asset)) {
                continue;
            }

            $usages[] = [
                'type' => 'rendition',
                'assetId' => $asset->getId(),
                'assetTitle' => $this->assetNameResolver->resolveNameAsString($asset),
                'name' => $rendition->getName(),
            ];
        }

        return $usages;
    }

    private function resolveAlternateUrlLabel(string $workspaceId, string $type): ?string
    {
        return $this->cache->get(sprintf('%s_%s', $workspaceId, $type), function () use ($workspaceId, $type): ?string {
            /** @var AlternateUrl|null $label */
            $label = $this->em->getRepository(AlternateUrl::class)
                ->findOneBy([
                    'workspace' => $workspaceId,
                    'type' => $type,
                ]);

            return $label?->getLabel();
        });
    }
}
