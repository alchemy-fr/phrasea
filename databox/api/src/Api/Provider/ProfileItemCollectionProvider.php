<?php

declare(strict_types=1);

namespace App\Api\Provider;

use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use Alchemy\CoreBundle\Util\DoctrineUtil;
use ApiPlatform\Metadata\Operation;
use App\Api\Traits\CollectionProviderAwareTrait;
use App\Repository\Profile\ProfileRepository;
use App\Security\Voter\AbstractVoter;

class ProfileItemCollectionProvider extends AbstractCollectionProvider
{
    use SecurityAwareTrait;
    use CollectionProviderAwareTrait;

    public function __construct(private readonly ProfileRepository $repository)
    {
    }

    protected function provideCollection(Operation $operation, array $uriVariables = [], array $context = []): array|object
    {
        $profile = DoctrineUtil::findStrictByRepo($this->repository, $uriVariables['id'], throw404: true);
        $this->denyAccessUnlessGranted(AbstractVoter::READ, $profile);

        return $this->collectionProvider->provide($operation, $uriVariables, $context);
    }
}
