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
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Post;
use App\Api\Model\Output\WorkflowStateOutput;
use App\Api\Processor\CancelWorkflowProcessor;
use App\Api\Processor\RerunWorkflowJobProcessor;
use App\Api\Serializer\Normalizer\WorkflowStateDumpNormalizer;
use App\Entity\Core\Asset;
use App\Service\Workflow\Event\IncomingUploaderFileWorkflowEvent;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping as ORM;

#[ApiResource(
    shortName: 'workflows',
    operations: [
        new Get(
            normalizationContext: [WorkflowStateDumpNormalizer::CONTEXT_KEY => true],
            security: 'is_granted("READ", object)',
        ),
        new Post(
            uriTemplate: '/workflows/{id}/jobs/{jobId}/rerun',
            throwOnNotFound: true,
            uriVariables: [
                'id' => new Link(fromClass: self::class, identifiers: ['id']),
            ],
            status: 200,
            normalizationContext: [WorkflowStateDumpNormalizer::CONTEXT_KEY => true],
            security: 'is_granted("EDIT", object)',
            input: false,
            processor: RerunWorkflowJobProcessor::class,
        ),
        new Post(
            uriTemplate: '/workflows/{id}/cancel',
            throwOnNotFound: true,
            uriVariables: [
                'id' => new Link(fromClass: self::class, identifiers: ['id']),
            ],
            status: 200,
            normalizationContext: [WorkflowStateDumpNormalizer::CONTEXT_KEY => true],
            security: 'is_granted("EDIT", object)',
            input: false,
            processor: CancelWorkflowProcessor::class,
        ),
        new GetCollection(
            normalizationContext: [
                'groups' => [self::GROUP_LIST],
            ],
            security: 'is_granted("'.JwtUser::IS_AUTHENTICATED_FULLY.'")',
            output: WorkflowStateOutput::class,
        )],
)]
#[ORM\Entity]
#[ApiFilter(filterClass: SearchFilter::class, properties: ['asset' => 'exact', 'status' => 'exact'])]
class WorkflowState extends BaseWorkflowState
{
    final public const string INITIATOR_ID = 'initiatorId';
    final public const string GROUP_LIST = 'workflow:index';

    #[ORM\Column(type: Types::STRING, length: 36, nullable: true)]
    private ?string $initiatorId = null;

    #[ORM\ManyToOne(targetEntity: Asset::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?Asset $asset = null;

    /**
     * Rank of this run among the runs of the same workflow on the same asset
     * (1 for the first ingest of an asset), set when it is created.
     */
    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $number = null;

    #[\Override]
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

    public function getNumber(): ?int
    {
        return $this->number;
    }

    public function setNumber(?int $number): void
    {
        $this->number = $number;
    }
}
