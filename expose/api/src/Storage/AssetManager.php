<?php

declare(strict_types=1);

namespace App\Storage;

use App\Entity\Asset;
use App\Entity\Publication;
use App\Entity\PublicationAsset;
use App\Entity\SubDefinition;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class AssetManager
{
    /**
     * @var EntityManagerInterface
     */
    private $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
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

        if (isset($options['direct_url_path'])) {
            $asset->setDirectUrlPath($options['direct_url_path']);
        }
        if (isset($options['asset_id'])) {
            $asset->setAssetId($options['asset_id']);
        }
        if (isset($options['publication_id'])) {
            $publication = $this->getPublication($options['publication_id']);
            $publicationAsset = new PublicationAsset();
            $publicationAsset->setPublication($publication);
            $publicationAsset->setAsset($asset);
            $asset->addPublication($publicationAsset);
            $this->em->persist($publicationAsset);
        }

        $this->em->persist($asset);
        $this->em->flush();

        return $asset;
    }


    public function createSubDefinition(
        string $name,
        string $path,
        string $mimeType,
        int $size,
        Asset $asset,
        array $options = []
    ): SubDefinition {

        $existingSubDef = $this->em->getRepository(SubDefinition::class)
            ->findSubDefinitionByType($asset->getId(), $name);
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

    public function findAssetSubDefinition(string $assetId, string $subDefType): SubDefinition
    {
        $subDef = $this->em
            ->getRepository(SubDefinition::class)
            ->findSubDefinitionByType($assetId, $subDefType);
        if (!$subDef instanceof SubDefinition) {
            throw new NotFoundHttpException('SubDefinition '.$assetId.'/'.$subDefType.' not found');
        }

        return $subDef;
    }
}
