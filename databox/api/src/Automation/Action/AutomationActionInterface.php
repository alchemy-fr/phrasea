<?php

declare(strict_types=1);

namespace App\Automation\Action;

use ApiPlatform\Validator\Exception\ValidationException;
use App\Automation\AutomationActionState;
use App\Automation\AutomationBatch;
use App\Automation\AutomationRunContext;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * An action of an automation, indexed by its type:
 *
 *     #[AsTaggedItem(index: self::TYPE)]
 *     final class MyAction extends AbstractAutomationAction
 *     {
 *         public const string TYPE = 'my_action';
 *     }
 *
 * Actions are run as the owner of the saved search, so every permission check
 * goes through the current token. As a failed asset is retried at the next run,
 * an action must be idempotent (or tolerate being repeated).
 */
#[AutoconfigureTag(self::TAG)]
interface AutomationActionInterface
{
    final public const string TAG = 'app.automation_action';

    /**
     * Normalizes and validates the configuration when the automation is written,
     * as the user writing it.
     *
     * @return array the normalized configuration
     *
     * @throws ValidationException
     */
    public function validateConfig(array $config): array;

    /**
     * Paths (dot notation) of the configuration values to encrypt.
     *
     * @return string[]
     */
    public function getSecretFields(): array;

    /**
     * Applies the action on a batch of assets, reporting each asset in the state:
     * processed, skipped (considered handled) or failed (retried at the next run).
     * An exception fails the whole batch.
     */
    public function processBatch(AutomationBatch $batch): void;

    /**
     * Called once after the last batch (even when no asset matched), for aggregated actions.
     */
    public function finish(AutomationActionState $state, array $config, AutomationRunContext $context): void;
}
