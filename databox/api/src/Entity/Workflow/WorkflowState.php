<?php

declare(strict_types=1);

namespace App\Entity\Workflow;

use Alchemy\Workflow\Doctrine\Entity\WorkflowState as BaseWorkflowState;
use Alchemy\Workflow\State\WorkflowState as ModelWorkflowState;
use App\Entity\Core\Asset;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity()
 */
class WorkflowState extends BaseWorkflowState
{
    final public const INITIATOR_ID = 'initiatorId';

    /**
     * @ORM\Column(type="string", length=36, nullable=true)
     */
    private ?string $initiatorId = null;

    /**
     * @ORM\ManyToOne(targetEntity=Asset::class)
     * @ORM\JoinColumn(nullable=true, onDelete="CASCADE")
     */
    private ?Asset $asset = null;

    public function setState(ModelWorkflowState $state, EntityManagerInterface $em): void
    {
        parent::setState($state, $em);

        $inputs = $state->getEvent()->getInputs();

        if (isset($inputs['assetId'])) {
            $this->asset = $em->getReference(Asset::class, $inputs['assetId']);
        }

        $context = $state->getContext();
        if (isset($context[self::INITIATOR_ID])) {
            $this->initiatorId = $context[self::INITIATOR_ID];
        }
    }

    public function getInitiatorId(): ?string
    {
        return $this->initiatorId;
    }

    public function getAsset(): ?Asset
    {
        return $this->asset;
    }
}
