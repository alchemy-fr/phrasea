<?php

declare(strict_types=1);

namespace App\Entity\Workflow;

use Alchemy\AuthBundle\Security\JwtUser;
use Alchemy\Workflow\Doctrine\Entity\WorkflowState as BaseWorkflowState;
use Alchemy\Workflow\State\WorkflowState as ModelWorkflowState;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use App\Controller\Workflow\GetWorkflowAction;
use App\Controller\Workflow\RerunJobAction;
use App\Entity\Core\Asset;
use App\Workflow\Event\IncomingUploaderFileWorkflowEvent;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping as ORM;

#[ApiResource(
    shortName: 'workflows',
    operations: [
    new Get(
        controller: GetWorkflowAction::class,
        security: 'is_granted("READ", object)',
        output: false
    ),
    new Post(
        uriTemplate: '/workflows/{id}/jobs/{jobId}/rerun',
        controller: RerunJobAction::class
    ),
    new GetCollection(
        security: 'is_granted("'.JwtUser::IS_AUTHENTICATED_FULLY.'")',
    )]
)]
#[ORM\Entity]
#[ApiFilter(filterClass: SearchFilter::class, properties: ['asset' => 'exact'])]
class WorkflowState extends BaseWorkflowState
{
    final public const INITIATOR_ID = 'initiatorId';

    #[ORM\Column(type: 'string', length: 36, nullable: true)]
    private ?string $initiatorId = null;

    #[ORM\ManyToOne(targetEntity: Asset::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?Asset $asset = null;

    public function setState(ModelWorkflowState $state, EntityManagerInterface $em): void
    {
        parent::setState($state, $em);

        $event = $state->getEvent();
        if (null !== $event) {
            $inputs = $event->getInputs();

            if (IncomingUploaderFileWorkflowEvent::EVENT !== $event->getName() && isset($inputs['assetId'])) {
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
