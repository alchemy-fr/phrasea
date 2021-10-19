<?php

declare(strict_types=1);

namespace App\Api\DataTransformer;

use App\Api\Model\Output\AssetOutput;
use App\Entity\Core\Asset;
use App\Entity\Core\Collection;
use App\Entity\Core\CollectionAsset;
use App\Entity\Core\File;
use App\Entity\Core\SubDefinition;
use App\Security\Voter\AssetVoter;
use App\Security\Voter\CollectionVoter;
use Doctrine\ORM\EntityManagerInterface;

class AssetOutputDataTransformer extends AbstractSecurityDataTransformer
{
    private EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    /**
     * @param Asset $object
     */
    public function transform($object, string $to, array $context = [])
    {
        $output = new AssetOutput();
        $output->setCreatedAt($object->getCreatedAt());
        $output->setUpdatedAt($object->getUpdatedAt());
        $output->setId($object->getId());
        $output->setTitle($object->getTitle());
        $output->setPrivacy($object->getPrivacy());
        $output->setTags($object->getTags()->getValues());
        $output->setWorkspace($object->getWorkspace());


        foreach ([
            'preview',
            'thumbnail',
            'thumbnailActive',
                 ] as $type) {
            if (null !== $file = $this->getSubDefFile($object, $type)) {
                $output->{'set'.ucfirst($type)}($file);
            }
        }

        $output->setCollections($object->getCollections()->map(function (CollectionAsset $collectionAsset): Collection {
            return $collectionAsset->getCollection();
        })->filter(function (Collection $collection): bool {
            return $this->isGranted(CollectionVoter::LIST, $collection);
        })->getValues());

        $output->setCapabilities([
            'canEdit' => $this->isGranted(AssetVoter::EDIT, $object),
            'canDelete' => $this->isGranted(AssetVoter::DELETE, $object),
            'canEditPermissions' => $this->isGranted(AssetVoter::EDIT_PERMISSIONS, $object),
        ]);

        return $output;
    }

    private function getSubDefFile(Asset $asset, string $type): ?File
    {
        $subDef = $this->em->getRepository(SubDefinition::class)
            ->findSubDefByType($asset->getId(), $type);

        if ($subDef instanceof SubDefinition) {
            return $subDef->getFile();
        }

        return null;
    }

    public function supportsTransformation($data, string $to, array $context = []): bool
    {
        return AssetOutput::class === $to && $data instanceof Asset;
    }
}
