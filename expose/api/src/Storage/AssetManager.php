<?php

declare(strict_types=1);

namespace App\Storage;

use App\Entity\Asset;
use App\Entity\Publication;
use App\Entity\PublicationAsset;
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
        if (isset($options['description'])) {
            $asset->setDescription($options['description']);
        }

        if (isset($options['asset_id'])) {
            $asset->setAssetId($options['asset_id']);
        }
        if (isset($options['use_as_cover'])) {
            $publication = $this->getPublication($options['use_as_cover']);
            $publication->setCover($asset);
            $this->em->persist($publication);
        }
        if (isset($options['use_as_package'])) {
            $publication = $this->getPublication($options['use_as_package']);
            $publication->setPackage($asset);
            $this->em->persist($publication);
        }
        if (isset($options['publication_id'])) {
            $publication = $this->getPublication($options['publication_id']);
            $publicationAsset = new PublicationAsset();
            $publicationAsset->setPublication($publication);
            $publicationAsset->setAsset($asset);
            if (isset($options['position'])) {
                $publicationAsset->setPosition((int) $options['position']);
            }
            $asset->addPublication($publicationAsset);

            if (isset($options['slug'])) {
                $publicationAsset->setSlug($options['slug']);
            }

            $this->em->persist($publicationAsset);
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

    public function deletePublicationAssetsByPublicationAndAsset(string $publicationId, string $assetId): void
    {
        $publicationAssets = $this->em->getRepository(PublicationAsset::class)
            ->findBy([
                'publication' => $publicationId,
                'asset' => $assetId,
            ]);

        foreach ($publicationAssets as $publicationAsset) {
            $this->em->remove($publicationAsset);
        }

        $this->em->flush();
    }

    public function createSubDefinition(
        string $name,
        string $path,
        string $mimeType,
        int $size,
        Asset $asset,
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

    private function getPublication(string $id): Publication
    {
        /** @var Publication $publication */
        $publication = $this->em->find(Publication::class, $id);
        if (!$publication) {
            throw new NotFoundHttpException(sprintf('Publication %s not found', $id));
        }

        if (
            !$this->security->isGranted(PublicationVoter::EDIT, $publication)
            && !$this->security->isGranted(PublicationVoter::CREATE, $publication)
        ) {
            throw new AccessDeniedHttpException();
        }

        return $publication;
    }

    public function findAsset(string $id): Asset
    {
        $asset = $this->em->find(Asset::class, $id);
        if (!$asset instanceof Asset) {
            throw new NotFoundHttpException('Asset '.$id.' not found');
        }

        return $asset;
    }
}
