<?php

declare(strict_types=1);

namespace App\OperationTask\Task;

use App\OperationTask\OperationTaskInterface;
use App\OperationTask\RunContext;
use App\Service\Asset\Attribute\AttributeDefinitionOperationRunner;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Materialize an AttributeDefinition's fallback value into a stored Attribute for every
 * asset of its workspace that has no value yet for that definition.
 */
final readonly class StoreFallbackAttributesTask implements OperationTaskInterface
{
    public function __construct(
        private AttributeDefinitionOperationRunner $runner,
    ) {
    }

    public static function getName(): string
    {
        return 'store_fallback_attributes';
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
            AttributeDefinitionOperationRunner::OP_STORE_FALLBACK,
            $payload['definitionId'],
            $context,
        );
    }
}
