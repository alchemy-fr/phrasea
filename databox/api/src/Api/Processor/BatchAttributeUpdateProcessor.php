<?php

declare(strict_types=1);

namespace App\Api\Processor;

use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Api\Model\Input\Attribute\AttributeBatchUpdateInput;
use App\Attribute\BatchAttributeManager;

final class BatchAttributeUpdateProcessor implements ProcessorInterface
{
    use SecurityAwareTrait;

    public function __construct(
        private readonly BatchAttributeManager $batchAttributeManager,
    ) {
    }

    /**
     * @param AttributeBatchUpdateInput $data
     */
    public function process($data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        $this->batchAttributeManager->validate($data->workspaceId, $data->assets, $data);

        $this->batchAttributeManager->handleBatch(
            $data->workspaceId,
            $data->assets,
            $data,
            $this->getStrictUser(),
            true,
        );

        return null;
    }
}
