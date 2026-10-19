<?php

declare(strict_types=1);

namespace App\Api\Mapper\Input;

use ApiPlatform\Validator\Exception\ValidationException;
use App\Api\Model\Input\Automation\SavedSearchAutomationInput;
use App\Automation\ActionConfigSecretsHandler;
use App\Automation\AutomationActionRegistry;
use App\Automation\Schedule\CronHelper;
use App\Entity\Automation\SavedSearchAutomation;
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
        if (null !== $data->enabled) {
            $object->setEnabled($data->enabled);
        }
        if (null !== $data->scope) {
            $scope = AutomationScopeEnum::tryFrom($data->scope)
                ?? self::throwViolation('scope', sprintf('The scope must be one of: %s.', implode(', ', AutomationScopeEnum::values())));
            $object->setScope($scope);
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

    private static function throwViolation(string $path, string $message): never
    {
        $list = new ConstraintViolationList();
        $list->add(new ConstraintViolation($message, $message, [], null, $path, null));

        throw new ValidationException($list);
    }
}
