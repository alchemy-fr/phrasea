<?php

declare(strict_types=1);

namespace App\Api\Processor;

use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Core\Asset;
use App\Model\AuditLogActionEnum;
use App\Service\Log\AuditLogManager;
use App\Service\Workflow\AssetWorkflowTrigger;
use Doctrine\ORM\EntityManagerInterface;

final class TriggerAssetWorkflowProcessor implements ProcessorInterface
{
    use SecurityAwareTrait;

    public function __construct(
        private readonly AssetWorkflowTrigger $assetWorkflowTrigger,
        private readonly AuditLogManager $auditLogManager,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * @param Asset $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Asset
    {
        $this->assetWorkflowTrigger->trigger($data, $this->getStrictUserOrOAuthClient()->getUserIdentifier());

        $this->auditLogManager->log(AuditLogActionEnum::WorkflowTriggered, $data);
        $this->em->flush();

        return $data;
    }
}
