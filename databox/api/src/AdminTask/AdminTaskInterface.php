<?php

namespace App\AdminTask;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag(self::TAG)]
interface AdminTaskInterface
{
    final public const string TAG = 'app.admin_task';

    public static function getName(): string;

    public function handle(array $payload, RunContext $context): void;
}
