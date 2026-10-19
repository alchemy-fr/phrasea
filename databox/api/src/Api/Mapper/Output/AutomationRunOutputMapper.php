<?php

declare(strict_types=1);

namespace App\Api\Mapper\Output;

use App\Api\Model\Output\Automation\AutomationRunOutput;
use App\Entity\Automation\AutomationRun;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

#[AsTaggedItem(index: AutomationRunOutput::class)]
class AutomationRunOutputMapper implements OutputMapperInterface
{
    use UserOutputTrait;

    public function supports(object $data): bool
    {
        return $data instanceof AutomationRun;
    }

    /**
     * @param AutomationRun $data
     */
    public function map(object $data, array $context = []): object
    {
        $output = new AutomationRunOutput();
        $output->setId($data->getId());
        $output->setCreatedAt($data->getCreatedAt());

        $output->automationId = $data->getAutomation()?->getId();
        $output->trigger = $data->getTrigger()->value;
        $output->dryRun = $data->isDryRun();
        $output->initiator = $this->transformUser($data->getInitiatorId());
        $output->status = $data->getStatus()->value;
        $output->startedAt = $data->getStartedAt();
        $output->endedAt = $data->getEndedAt();
        $output->matchedCount = $data->getMatchedCount();
        $output->newCount = $data->getNewCount();
        $output->processedCount = $data->getProcessedCount();
        $output->failedCount = $data->getFailedCount();
        $output->actionResults = $data->getActionResults();
        $output->error = $data->getError();

        return $output;
    }
}
