<?php

declare(strict_types=1);

namespace App\Api\Provider;

use Alchemy\AuthBundle\Security\JwtUser;
use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Api\Traits\ItemProviderAwareTrait;
use App\Elasticsearch\ESDocumentStateManager;
use App\Entity\Core\Asset;
use App\Security\Voter\AbstractVoter;
use Doctrine\ORM\EntityManagerInterface;

final class AssetElasticsearchDocumentProvider implements ProviderInterface
{
    use SecurityAwareTrait;
    use ItemProviderAwareTrait;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ESDocumentStateManager $esDocumentStateManager,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $asset = $this->itemProvider->provide($operation, $uriVariables, $context);
        $this->denyAccessUnlessGranted(AbstractVoter::READ, $asset);
        $this->denyAccessUnlessGranted(JwtUser::ROLE_TECH);

        if ($asset instanceof Asset) {
            return $this->esDocumentStateManager->getObjectState($asset);
        }

        return null;
    }
}
