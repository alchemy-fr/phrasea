<?php

declare(strict_types=1);

namespace App\Api\Processor;

use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use Alchemy\CoreBundle\Util\DoctrineUtil;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Api\Model\Input\Automation\TriggerAutomationInput;
use App\Automation\AutomationRunner;
use App\Entity\Automation\AutomationRun;
use App\Entity\Automation\SavedSearchAutomation;
use App\Model\AutomationTriggerEnum;
use App\Security\Voter\SavedSearchAutomationVoter;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Runs an automation on demand (even when disabled), as the owner of its saved search.
 */
final class TriggerAutomationProcessor implements ProcessorInterface
{
    use SecurityAwareTrait;

    public function __construct(
        private readonly AutomationRunner $runner,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * @param TriggerAutomationInput $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): AutomationRun
    {
        // Not the "previous_data" clone: the run references the managed entity
        $automation = DoctrineUtil::findStrict($this->em, SavedSearchAutomation::class, (string) $uriVariables['id']);
        $this->denyAccessUnlessGranted(SavedSearchAutomationVoter::RUN, $automation);

        $run = $this->runner->trigger(
            $automation,
            AutomationTriggerEnum::Manual,
            $this->getStrictUser()->getId(),
            $data->dryRun,
        );

        // The run may have been executed synchronously (sync transport), which clears the entity manager
        return $this->em->find(AutomationRun::class, $run->getId()) ?? $run;
    }
}
