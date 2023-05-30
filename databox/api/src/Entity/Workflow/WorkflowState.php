<?php

declare(strict_types=1);

namespace App\Entity\Workflow;

use Alchemy\Workflow\Doctrine\Entity\WorkflowState as BaseWorkflowState;
use Alchemy\Workflow\State\WorkflowState as ModelWorkflowState;
use ApiPlatform\Core\Annotation\ApiResource;
use App\Controller\Workflow\GetWorkflowAction;
use App\Controller\Workflow\RerunJobAction;
use App\Entity\Core\Asset;
use App\Security\Voter\AbstractVoter;
use App\Workflow\Event\IncomingUploaderFileWorkflowEvent;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity()
 */
#[ApiResource(
    collectionOperations: [
        'get'=> [
            'security' => 'is_granted("ROLE_USER")',
        ]
    ],
    itemOperations: [
        'get' => [
            'security' => 'is_granted("'.AbstractVoter::READ.'", object)',
            'controller' => GetWorkflowAction::class,
            'output' => false,
        ],
        'post_rerun_job' => [
            'method' => 'POST',
            'path' => '/workflows/{id}/jobs/{jobId}/rerun',
            'controller' => RerunJobAction::class,
        ],
    ],
    shortName: 'workflows',
)]
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

        $event = $state->getEvent();
        if (null !== $event) {
            $inputs = $event->getInputs();

            if ($event->getName() !== IncomingUploaderFileWorkflowEvent::EVENT && isset($inputs['assetId'])) {
                $this->asset = $em->getReference(Asset::class, $inputs['assetId']);
            }
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
