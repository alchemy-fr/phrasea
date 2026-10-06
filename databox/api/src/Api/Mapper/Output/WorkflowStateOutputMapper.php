<?php

declare(strict_types=1);

namespace App\Api\Mapper\Output;

use App\Api\Model\Output\WorkflowStateOutput;
use App\Entity\Workflow\WorkflowState;
use App\Service\Asset\Attribute\AssetNameResolver;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

#[AsTaggedItem(index: WorkflowStateOutput::class)]
final readonly class WorkflowStateOutputMapper implements OutputMapperInterface
{
    public function __construct(
        private AssetNameResolver $assetNameResolver,
    ) {
    }

    public function supports(object $data): bool
    {
        return $data instanceof WorkflowState;
    }

    /**
     * @param WorkflowState $data
     */
    public function map(object $data, array $context = []): object
    {
        $output = new WorkflowStateOutput();
        $output->id = $data->getId();
        $output->name = $data->getName();
        $output->status = $data->getStatus();
        $output->number = $data->getNumber();
        $output->eventName = $data->getEventName();
        $output->startedAt = $data->getStartedAt();
        $output->endedAt = $data->getEndedAt();
        $output->duration = $data->getDurationString();

        if (null !== $asset = $data->getAsset()) {
            $output->assetId = $asset->getId();
            $output->assetName = $this->assetNameResolver->resolveNameAsString($asset);
        }

        return $output;
    }
}
