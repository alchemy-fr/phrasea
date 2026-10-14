<?php

declare(strict_types=1);

namespace App\Api\Processor;

use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use Alchemy\CoreBundle\Util\DoctrineUtil;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Api\Model\Input\StoryCoverInput;
use App\Entity\Core\Asset;
use App\Entity\Core\CollectionAsset;
use App\Repository\Core\CollectionAssetRepository;
use App\Security\Voter\AbstractVoter;
use App\Service\Asset\AssetManager;
use App\Service\Asset\FileCopier;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Makes one of the story items the cover of the story: its source file is
 * copied as the story source (the story renditions are then generated from it).
 * The file is copied, not shared: the story and the item have their own life
 * cycle (deletion, new source versions, analysis).
 */
final class SetStoryCoverProcessor implements ProcessorInterface
{
    use SecurityAwareTrait;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AssetManager $assetManager,
        private readonly FileCopier $fileCopier,
    ) {
    }

    /**
     * @param StoryCoverInput $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Asset
    {
        $story = DoctrineUtil::findStrict($this->em, Asset::class, (string) $uriVariables['id']);
        $this->denyAccessUnlessGranted(AbstractVoter::EDIT, $story);

        $storyCollection = $story->getStoryCollection();
        if (null === $storyCollection) {
            throw new BadRequestHttpException('Asset is not a story.');
        }

        $cover = DoctrineUtil::findStrict($this->em, Asset::class, $data->assetId);
        $this->denyAccessUnlessGranted(AbstractVoter::READ, $cover);

        /** @var CollectionAssetRepository $repository */
        $repository = $this->em->getRepository(CollectionAsset::class);
        if (null === $repository->findCollectionAsset($cover->getId(), $storyCollection->getId())) {
            throw new BadRequestHttpException('Cover asset does not belong to the story.');
        }

        $file = $cover->getSource();
        if (null === $file) {
            throw new BadRequestHttpException('Cover asset has no source file.');
        }

        $this->assetManager->assignNewAssetSourceFile($story, $file);
        $this->em->flush();

        return $story;
    }
}
