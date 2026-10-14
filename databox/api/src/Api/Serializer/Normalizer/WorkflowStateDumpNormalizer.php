<?php

declare(strict_types=1);

namespace App\Api\Serializer\Normalizer;

use Alchemy\Workflow\Dumper\JsonWorkflowDumper;
use Alchemy\Workflow\Planner\WorkflowPlanner;
use Alchemy\Workflow\Repository\WorkflowRepositoryInterface;
use Alchemy\Workflow\State\Repository\StateRepositoryInterface;
use App\Entity\Workflow\WorkflowState;
use App\Service\Asset\Attribute\AssetNameResolver;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Renders a workflow run with its planned jobs and their states
 * (operations having the WorkflowStateDumpNormalizer::CONTEXT_KEY normalization context).
 */
final readonly class WorkflowStateDumpNormalizer implements NormalizerInterface
{
    public const string CONTEXT_KEY = 'workflow_dump';

    public function __construct(
        private StateRepositoryInterface $stateRepository,
        private WorkflowRepositoryInterface $workflowRepository,
        private AssetNameResolver $assetNameResolver,
    ) {
    }

    /**
     * @param WorkflowState $data
     */
    public function normalize(mixed $data, ?string $format = null, array $context = []): \ArrayObject
    {
        $workflowState = $this->stateRepository->getWorkflowState($data->getId());

        $planner = new WorkflowPlanner([$this->workflowRepository->loadWorkflowByName($workflowState->getWorkflowName())]);
        $event = $workflowState->getEvent();
        $plan = null === $event ? $planner->planAll() : $planner->planEvent($event);

        $output = new BufferedOutput();
        (new JsonWorkflowDumper())->dumpWorkflow($workflowState, $plan, $output);

        // Decoded as objects: empty inputs/outputs stay `{}`
        $dump = self::toArrayObject(json_decode($output->fetch(), false, 512, JSON_THROW_ON_ERROR));

        $asset = $data->getAsset();
        $dump['number'] = $data->getNumber();
        $dump['asset'] = null !== $asset ? [
            'id' => $asset->getId(),
            'name' => $this->assetNameResolver->resolveNameAsString($asset),
        ] : null;

        return $dump;
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof WorkflowState && ($context[self::CONTEXT_KEY] ?? false);
    }

    public function getSupportedTypes(?string $format): array
    {
        return [WorkflowState::class => false];
    }

    private static function toArrayObject(mixed $value): mixed
    {
        if ($value instanceof \stdClass) {
            return new \ArrayObject(array_map(self::toArrayObject(...), get_object_vars($value)));
        }
        if (\is_array($value)) {
            return array_map(self::toArrayObject(...), $value);
        }

        return $value;
    }
}
