<?php

namespace App\Api\Provider;

use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use Alchemy\CoreBundle\Util\DoctrineUtil;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Api\Model\Output\CollectionPrivacyInfoOutput;
use App\Entity\Core\Asset;
use App\Entity\Core\Collection;
use App\Repository\Core\CollectionRepository;
use App\Security\Voter\AbstractVoter;

final class CollectionPrivacyInfoProvider implements ProviderInterface
{
    use SecurityAwareTrait;

    public function __construct(
        private readonly CollectionRepository $collectionRepository,

    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        /** @var Collection $collection */
        $collection = DoctrineUtil::findStrictByRepo($this->collectionRepository, $uriVariables['id']);
        $this->denyAccessUnlessGranted(AbstractVoter::READ, $collection);

        $output = new CollectionPrivacyInfoOutput();
        $output->privacy = $collection->getPrivacy();

        $bestPrivacy = $collection->getPrivacy();
        $parent = $collection->getParent();
        while ($parent) {
            $bestPrivacy = max($bestPrivacy, $parent->getPrivacy());
            $parent = $parent->getParent();
        }

        $output->computedPrivacy = $bestPrivacy;

        $newAsset = new Asset();
        $newAsset->setOwnerId($this->getUser()?->getId());
        $newAsset->setReferenceCollection($collection);
        $newAsset->setWorkspace($collection->getWorkspace());

        $output->canEditAssetPrivacy = $this->isGranted(AbstractVoter::EDIT_PERMISSIONS, $newAsset);

        return $output;
    }
}
