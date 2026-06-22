<?php

namespace App\OperationTask;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag(self::TAG)]
interface OperationTaskInterface
{
    final public const string TAG = 'app.operation_task';

    public static function getName(): string;

    public function handle(array $payload, RunContext $context): void;

    public function validate(array $payload): void;
}
