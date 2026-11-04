<?php

declare(strict_types=1);

namespace App\Tests\Functional\Automation;

use Alchemy\MessengerBundle\Transport\TestTransport;
use App\Automation\AutomationRunner;
use App\Entity\Automation\AutomationRun;
use App\Entity\Automation\SavedSearchAutomation;
use App\Entity\SavedSearch\SavedSearch;
use App\Model\AutomationScopeEnum;
use App\Model\AutomationTriggerEnum;
use App\Model\SavedSearchPrivacyEnum;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

trait AutomationTestTrait
{
    protected function createSavedSearchWith(string $ownerId, array $data): SavedSearch
    {
        $em = self::getEntityManager();
        $savedSearch = new SavedSearch();
        $savedSearch->setName('Automated search');
        $savedSearch->setOwnerId($ownerId);
        $savedSearch->setPrivacy(SavedSearchPrivacyEnum::Secret);
        $savedSearch->setData($data);
        $em->persist($savedSearch);
        $em->flush();

        return $savedSearch;
    }

    /**
     * Written as is: the action configurations are not validated.
     */
    protected function createAutomationEntity(SavedSearch $savedSearch, array $actions, AutomationScopeEnum $scope = AutomationScopeEnum::New, array $options = []): SavedSearchAutomation
    {
        $em = self::getEntityManager();
        $automation = new SavedSearchAutomation();
        $automation->setSavedSearch($em->find(SavedSearch::class, $savedSearch->getId()));
        $automation->setName($options['name'] ?? 'Automation');
        $automation->setScope($scope);
        $automation->setActions($actions);
        $automation->setEnabled($options['enabled'] ?? true);
        $automation->setSchedule($options['schedule'] ?? null);
        $automation->setNextRunAt($options['nextRunAt'] ?? null);
        $automation->setConsecutiveFailures($options['consecutiveFailures'] ?? 0);
        $em->persist($automation);
        $em->flush();

        return $automation;
    }

    /**
     * Runs the automation synchronously, as the worker does.
     */
    protected function runAutomation(string $automationId, bool $dryRun = false, AutomationTriggerEnum $trigger = AutomationTriggerEnum::Manual): AutomationRun
    {
        $em = self::getEntityManager();
        $run = new AutomationRun();
        $run->setAutomation($em->find(SavedSearchAutomation::class, $automationId));
        $run->setTrigger($trigger);
        $run->setDryRun($dryRun);
        $em->persist($run);
        $em->flush();

        self::getService(AutomationRunner::class)->run($run);

        $em->clear();

        return $em->find(AutomationRun::class, $run->getId());
    }

    protected function findAutomation(string $id): SavedSearchAutomation
    {
        $em = self::getEntityManager();
        $em->clear();

        return $em->find(SavedSearchAutomation::class, $id);
    }

    protected static function interceptTransport(string $name): InMemoryTransport
    {
        /** @var TestTransport $transport */
        $transport = self::getContainer()->get('messenger.transport.'.$name);

        return $transport->intercept();
    }
}
