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
use App\Service\Asset\StoryCoverManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Makes one of the story items the cover of the story (see StoryCoverManager).
 */
final class SetStoryCoverProcessor implements ProcessorInterface
{
    use SecurityAwareTrait;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly StoryCoverManager $storyCoverManager,
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

        $this->storyCoverManager->setCover($story, $cover);
        $this->em->flush();

        return $story;
    }
}
