<?php

declare(strict_types=1);

namespace App\Tests\Functional\Automation\Action;

use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use App\Automation\Action\AutomationActionInterface;
use App\Automation\AutomationActionState;
use App\Automation\AutomationBatch;
use App\Automation\AutomationRunContext;
use App\Automation\UserContextSwitcher;
use App\Entity\Core\Asset;
use App\Model\AutomationTriggerEnum;

/**
 * Runs an action on a batch of assets as a given user, as the runner does.
 */
trait AutomationActionTestTrait
{
    protected static function createRunContext(string $ownerId = KeycloakClientTestMock::USER_UID): AutomationRunContext
    {
        return new AutomationRunContext(
            runId: 'run',
            automationId: 'automation',
            automationName: 'Test',
            savedSearchId: 'search',
            savedSearchName: 'Search',
            ownerId: $ownerId,
            trigger: AutomationTriggerEnum::Manual,
            dryRun: false,
        );
    }

    /**
     * @param Asset[] $assets
     * @param array   $config the configuration, validated as the owner
     */
    protected function processAction(
        AutomationActionInterface $action,
        array $config,
        array $assets,
        string $userId = KeycloakClientTestMock::USER_UID,
        ?AutomationActionState $state = null,
    ): AutomationActionState {
        $state ??= new AutomationActionState($action::class);
        $em = self::getEntityManager();
        $em->clear();
        $assets = array_map(fn (Asset $asset): Asset => $em->find(Asset::class, $asset->getId()), $assets);
        $config = $this->validateActionConfig($action, $config);

        self::getService(UserContextSwitcher::class)->runAs($userId, function () use ($action, $config, $assets, $state, $userId): void {
            $state->beginBatch();
            $action->processBatch(new AutomationBatch($assets, $config, $state, self::createRunContext($userId)));
        });
        $em->clear();

        return $state;
    }

    protected function validateActionConfig(AutomationActionInterface $action, array $config, string $userId = KeycloakClientTestMock::USER_UID): array
    {
        return self::getService(UserContextSwitcher::class)->runAs($userId, fn (): array => $action->validateConfig($config));
    }
}
