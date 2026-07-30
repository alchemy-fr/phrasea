<?php

declare(strict_types=1);

namespace App\OperationTask\Task;

use App\OperationTask\OperationTaskInterface;
use App\OperationTask\RunContext;
use App\Service\Asset\Attribute\AttributeDefinitionOperationRunner;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Recompute an AttributeDefinition's initial value (from its initialValues Twig templates
 * and/or readFromMetadata) for every asset of its workspace, overwriting the previously
 * stored attributes for that definition.
 */
final readonly class RecomputeInitialValuesTask implements OperationTaskInterface
{
    public function __construct(
        private AttributeDefinitionOperationRunner $runner,
    ) {
    }

    public static function getName(): string
    {
        return 'recompute_initial_values';
    }

    public function validate(array $payload): void
    {
        if (empty($payload['definitionId'] ?? null)) {
            throw new BadRequestHttpException('definitionId is required');
        }
    }

    public function handle(array $payload, RunContext $context): void
    {
        $this->runner->dispatch(
            AttributeDefinitionOperationRunner::OP_RECOMPUTE_INITIAL,
            $payload['definitionId'],
            $context,
        );
    }
}
