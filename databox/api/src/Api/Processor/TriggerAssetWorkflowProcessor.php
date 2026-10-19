<?php

declare(strict_types=1);

namespace App\Api\Processor;

use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Core\Asset;
use App\Service\Workflow\AssetWorkflowTrigger;

final class TriggerAssetWorkflowProcessor implements ProcessorInterface
{
    use SecurityAwareTrait;

    public function __construct(
        private readonly AssetWorkflowTrigger $assetWorkflowTrigger,
    ) {
    }

    /**
     * @param Asset $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Asset
    {
        $this->assetWorkflowTrigger->trigger($data, $this->getStrictUserOrOAuthClient()->getUserIdentifier());

        return $data;
    }
}
