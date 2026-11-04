<?php

declare(strict_types=1);

namespace App\Api\Mapper\Output;

use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use App\Api\Model\Output\Automation\SavedSearchAutomationOutput;
use App\Automation\ActionConfigSecretsHandler;
use App\Entity\Automation\SavedSearchAutomation;
use App\Security\Voter\AbstractVoter;
use App\Security\Voter\SavedSearchAutomationVoter;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

#[AsTaggedItem(index: SavedSearchAutomationOutput::class)]
class SavedSearchAutomationOutputMapper implements OutputMapperInterface
{
    use SecurityAwareTrait;
    use UserOutputTrait;
    use GroupsHelperTrait;

    public function __construct(
        private readonly ActionConfigSecretsHandler $secretsHandler,
    ) {
    }

    public function supports(object $data): bool
    {
        return $data instanceof SavedSearchAutomation;
    }

    /**
     * @param SavedSearchAutomation $data
     */
    public function map(object $data, array $context = []): object
    {
        $output = new SavedSearchAutomationOutput();
        $output->setId($data->getId());
        $output->setCreatedAt($data->getCreatedAt());
        $output->setUpdatedAt($data->getUpdatedAt());

        $output->savedSearchId = $data->getSavedSearch()?->getId();
        $output->name = $data->getName();
        $output->enabled = $data->isEnabled();
        $output->schedule = $data->getSchedule();
        $output->timezone = $data->getTimezone();
        $output->scope = $data->getScope()->value;
        $output->actions = array_map(fn (array $action): array => [
            'type' => $action['type'],
            'config' => $this->secretsHandler->mask($action['type'], $action['config'] ?? []),
        ], $data->getActions());
        $output->nextRunAt = $data->getNextRunAt();
        $output->lastRunAt = $data->getLastRunAt();
        $output->lastRunStatus = $data->getLastRunStatus()?->value;
        $output->consecutiveFailures = $data->getConsecutiveFailures();

        if ($this->hasGroup([SavedSearchAutomation::GROUP_READ], $context)) {
            $output->createdBy = $this->transformUser($data->getCreatedBy());
        }

        if ($this->hasGroup([SavedSearchAutomation::GROUP_LIST, SavedSearchAutomation::GROUP_READ], $context)) {
            $output->setCapabilities([
                'edit' => $this->isGranted(AbstractVoter::EDIT, $data),
                'delete' => $this->isGranted(AbstractVoter::DELETE, $data),
                'run' => $this->isGranted(SavedSearchAutomationVoter::RUN, $data),
            ]);
        }

        return $output;
    }
}
