<?php

declare(strict_types=1);

namespace App\Storage;

use App\Entity\Asset;
use App\Entity\Publication;
use App\Entity\SubDefinition;
use App\Security\Voter\PublicationVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Security;

class AssetManager
{
    private EntityManagerInterface $em;
    private Security $security;

    public function __construct(EntityManagerInterface $em, Security $security)
    {
        $this->em = $em;
        $this->security = $security;
    }

    public function createAsset(
        Publication $publication,
        string $path,
        string $mimeType,
        string $originalName,
        int $size,
        array $options = []
    ): Asset {
        $asset = new Asset();
        $asset->setPath($path);
        $asset->setMimeType($mimeType);
        $asset->setOriginalName($originalName);
        $asset->setSize($size);

        $asset->setPublication($publication);
        if (isset($options['position'])) {
            $asset->setPosition((int) $options['position']);
        }

        if (isset($options['slug'])) {
            $asset->setSlug($options['slug']);
        }

        if (isset($options['description'])) {
            $asset->setDescription($options['description']);
        }

        if (isset($options['asset_id'])) {
            $asset->setAssetId($options['asset_id']);
        }
        if ($options['use_as_cover'] ?? false) {
            $publication->setCover($asset);
        }
        if ($options['use_as_package'] ?? false) {
            $publication->setPackage($asset);
        }
        if (isset($options['lat'])) {
            $asset->setLat((float) $options['lat']);
        }
        if (isset($options['lng'])) {
            $asset->setLng((float) $options['lng']);
        }
        if (isset($options['altitude'])) {
            $asset->setAltitude((float) $options['altitude']);
        }
        if (isset($options['webVTT'])) {
            $asset->setWebVTT($options['webVTT']);
        }

        $this->em->persist($publication);
        $this->em->persist($asset);
        $this->em->flush();

        return $asset;
    }

    public function deleteByAssetId(string $assetId): void
    {
        $assets = $this->em->getRepository(Asset::class)
            ->findBy([
                'assetId' => $assetId,
            ]);

        foreach ($assets as $asset) {
            $this->em->remove($asset);
        }

        $this->em->flush();
    }

    public function createSubDefinition(
        Asset $asset,
        string $name,
        string $path,
        string $mimeType,
        int $size,
        array $options = []
    ): SubDefinition {
        $existingSubDef = $this
            ->em
            ->getRepository(SubDefinition::class)
            ->findSubDefinitionByType($asset, $name);

        if ($existingSubDef instanceof SubDefinition) {
            throw new BadRequestHttpException(sprintf('Sub definition named "%s" already exists for this asset', $name));
        }

        $subDefinition = new SubDefinition();
        $subDefinition->setName($name);
        $subDefinition->setPath($path);
        $subDefinition->setMimeType($mimeType);
        $subDefinition->setSize($size);
        $subDefinition->setAsset($asset);

        if ($options['use_as_preview'] ?? false) {
            $asset->setPreviewDefinition($subDefinition);
        }
        if ($options['use_as_thumbnail'] ?? false) {
            $asset->setThumbnailDefinition($subDefinition);
        }

        $this->em->persist($subDefinition);
        $this->em->flush();

        return $subDefinition;
    }

    public function getPublicationWithEditGrant(string $id): Publication
    {
        $publication = $this->em->find(Publication::class, $id);
        if (!$publication) {
            throw new NotFoundHttpException(sprintf('Publication %s not found', $id));
        }

        if (!$this->security->isGranted(PublicationVoter::EDIT, $publication)) {
            throw new AccessDeniedHttpException('Cannot add asset to publication (no edit permission)');
        }

        return $publication;
    }

    public function findAsset(string $id): Asset
    {
        $asset = $this->em->find(Asset::class, $id);
        if (!$asset instanceof Asset) {
            throw new NotFoundHttpException(sprintf('Asset "%s" not found', $id));
        }

        return $asset;
    }
}
