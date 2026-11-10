<?php

declare(strict_types=1);

namespace App\Api\Mapper\Input;

use ApiPlatform\Validator\Exception\ValidationException;
use App\Api\Model\Input\Automation\SavedSearchAutomationInput;
use App\Automation\Action\AutomationAwareActionInterface;
use App\Automation\Action\ScopeAwareActionInterface;
use App\Automation\Action\ShareExportAction;
use App\Automation\ActionConfigSecretsHandler;
use App\Automation\AutomationActionRegistry;
use App\Automation\Schedule\CronHelper;
use App\Entity\Automation\SavedSearchAutomation;
use App\Entity\Core\Workspace;
use App\Entity\SavedSearch\SavedSearch;
use App\Model\AutomationScopeEnum;
use App\Security\Voter\AbstractVoter;
use App\Security\Voter\SavedSearchAutomationVoter;
use Ramsey\Uuid\Uuid;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

#[AsTaggedItem(index: SavedSearchAutomationInput::class)]
class SavedSearchAutomationInputMapper extends AbstractInputMapper implements InputMapperInterface
{
    public function __construct(
        private readonly AutomationActionRegistry $actionRegistry,
        private readonly ActionConfigSecretsHandler $secretsHandler,
        private readonly CronHelper $cronHelper,
    ) {
    }

    /**
     * @param SavedSearchAutomationInput $data
     */
    public function map(object $data, ?object $target, array $context = []): ?object
    {
        if ($target instanceof SavedSearchAutomation) {
            // The actions are validated with the rights of the current user: check first that they are the owner's
            $this->denyAccessUnlessGranted(AbstractVoter::EDIT, $target);
            $object = $target;
        } else {
            $object = new SavedSearchAutomation();
            $object->setCreatedBy($this->getStrictUser()->getId());

            if (null === $data->savedSearch || '' === $data->savedSearch) {
                self::throwViolation('savedSearch', 'This value should not be blank.');
            }
            if (!str_starts_with($data->savedSearch, '/') && !Uuid::isValid($data->savedSearch)) {
                self::throwViolation('savedSearch', 'Saved search not found.');
            }
            /** @var SavedSearch $savedSearch */
            $savedSearch = $this->getEntity(SavedSearch::class, $data->savedSearch);
            $this->denyAccessUnlessGranted(SavedSearchAutomationVoter::MANAGE_AUTOMATIONS, $savedSearch);
            $object->setSavedSearch($savedSearch);
        }

        if (null !== $data->name) {
            $object->setName(trim($data->name));
        }
        $workspaceWritten = self::hasKey('workspaceId', $data->workspaceId, $context);
        if ($workspaceWritten) {
            $object->setWorkspace($this->mapWorkspace($data->workspaceId));
        }
        if (null !== $data->enabled) {
            $object->setEnabled($data->enabled);
        }
        if (null !== $data->scope) {
            $scope = AutomationScopeEnum::tryFrom($data->scope)
                ?? self::throwViolation('scope', sprintf('The scope must be one of: %s.', implode(', ', AutomationScopeEnum::values())));
            $object->setScope($scope);
        }
        if (null !== $data->maxAssetsPerRun) {
            $object->setMaxAssetsPerRun($data->maxAssetsPerRun);
        }
        if (null !== $data->timezone) {
            if (!CronHelper::isValidTimezone($data->timezone)) {
                self::throwViolation('timezone', 'This value is not a valid timezone.');
            }
            $object->setTimezone($data->timezone);
        }
        if (null !== $data->schedule) {
            $schedule = trim($data->schedule);
            $object->setSchedule('' === $schedule ? null : $schedule);
        }
        if (null !== $object->getSchedule() && (null !== $data->schedule || null !== $data->timezone)) {
            if (null !== $error = $this->cronHelper->validate($object->getSchedule(), $object->getTimezone())) {
                self::throwViolation('schedule', $error);
            }
        }
        if (null !== $data->actions) {
            $object->setActions($this->mapActions($data->actions, $object->getActions()));
        }
        if (null === $target || null !== $data->scope || null !== $data->actions || $workspaceWritten) {
            $this->checkActionConstraints($object, null !== $data->actions, $workspaceWritten);
        }

        if (null === $target || null !== $data->schedule || null !== $data->timezone || null !== $data->enabled) {
            $object->setNextRunAt($object->isEnabled() && null !== $object->getSchedule()
                ? $this->cronHelper->getNextRunAt($object->getSchedule(), $object->getTimezone())
                : null
            );
            if ($object->isEnabled() && true === $data->enabled) {
                $object->setConsecutiveFailures(0);
            }
        }

        return $object;
    }

    /**
     * @param array<int, mixed>                        $actions
     * @param list<array{type: string, config: array}> $previousActions
     *
     * @return list<array{type: string, config: array}>
     */
    private function mapActions(array $actions, array $previousActions): array
    {
        // The automation keeps a single share
        $shareExports = 0;
        foreach (array_values($actions) as $i => $action) {
            if (is_array($action) && ShareExportAction::TYPE === ($action['type'] ?? null) && ++$shareExports > 1) {
                self::throwViolation(sprintf('actions[%d].type', $i), 'An automation can only have one share export action.');
            }
        }

        $mapped = [];
        foreach (array_values($actions) as $i => $action) {
            $type = is_array($action) ? ($action['type'] ?? null) : null;
            if (!is_string($type) || !$this->actionRegistry->has($type)) {
                self::throwViolation(sprintf('actions[%d].type', $i), sprintf('Unsupported action type "%s".', is_string($type) ? $type : ''));
            }
            $config = $action['config'] ?? [];
            if (!is_array($config)) {
                self::throwViolation(sprintf('actions[%d].config', $i), 'This value should be an object.');
            }

            try {
                $config = $this->actionRegistry->get($type)->validateConfig($config);
            } catch (ValidationException $e) {
                $violations = new ConstraintViolationList();
                foreach ($e->getConstraintViolationList() as $violation) {
                    $violations->add(new ConstraintViolation(
                        $violation->getMessage(),
                        $violation->getMessageTemplate(),
                        $violation->getParameters(),
                        $violation->getRoot(),
                        sprintf('actions[%d].config.%s', $i, $violation->getPropertyPath()),
                        $violation->getInvalidValue(),
                    ));
                }

                throw new ValidationException($violations);
            }

            // A secret left empty keeps the value of the action at the same position, if of the same type
            $previous = $previousActions[$i] ?? null;
            $previousConfig = null !== $previous && ($previous['type'] ?? null) === $type ? ($previous['config'] ?? []) : null;

            $mapped[] = [
                'type' => $type,
                'config' => $this->secretsHandler->encrypt($type, $config, $previousConfig),
            ];
        }

        return $mapped;
    }

    private function mapWorkspace(?string $workspaceId): ?Workspace
    {
        $workspaceId = trim((string) $workspaceId);
        if ('' === $workspaceId) {
            return null;
        }

        $pos = strrpos($workspaceId, '/');
        $id = false === $pos ? $workspaceId : substr($workspaceId, $pos + 1);
        $workspace = Uuid::isValid($id) ? $this->em->find(Workspace::class, $id) : null;
        if (null === $workspace || !$this->isGranted(AbstractVoter::READ, $workspace)) {
            self::throwViolation('workspaceId', 'Workspace not found.');
        }

        return $workspace;
    }

    /**
     * Some options of the actions depend on other fields of the automation (scope, workspace).
     *
     * @param bool $actionsWritten   whether to report the violations on the actions, or on the field of the automation
     * @param bool $workspaceWritten whether a missing workspace is reported on the workspace
     */
    private function checkActionConstraints(SavedSearchAutomation $automation, bool $actionsWritten, bool $workspaceWritten): void
    {
        $violations = new ConstraintViolationList();
        foreach ($automation->getActions() as $i => $definition) {
            $type = (string) ($definition['type'] ?? '');
            if (!$this->actionRegistry->has($type)) {
                continue;
            }
            $action = $this->actionRegistry->get($type);
            if (!$action instanceof ScopeAwareActionInterface && !$action instanceof AutomationAwareActionInterface) {
                continue;
            }

            $config = $this->secretsHandler->decrypt($type, $definition['config'] ?? []);
            if ($action instanceof ScopeAwareActionInterface) {
                foreach ($action->getScopeViolations($config, $automation->getScope()) as $key => $message) {
                    $path = $actionsWritten ? sprintf('actions[%d].config.%s', $i, $key) : 'scope';
                    $violations->add(new ConstraintViolation($message, $message, [], null, $path, null));
                }
            }
            if ($action instanceof AutomationAwareActionInterface) {
                foreach ($action->getAutomationViolations($config, $automation) as $key => $message) {
                    $onActions = $actionsWritten && !($workspaceWritten && AutomationAwareActionInterface::WORKSPACE_KEY === $key);
                    $path = $onActions ? sprintf('actions[%d].config.%s', $i, $key) : 'workspaceId';
                    $violations->add(new ConstraintViolation($message, $message, [], null, $path, null));
                }
            }
        }

        if (count($violations) > 0) {
            throw new ValidationException($violations);
        }
    }

    private static function throwViolation(string $path, string $message): never
    {
        $list = new ConstraintViolationList();
        $list->add(new ConstraintViolation($message, $message, [], null, $path, null));

        throw new ValidationException($list);
    }
}
