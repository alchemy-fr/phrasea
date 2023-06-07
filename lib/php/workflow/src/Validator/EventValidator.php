<?php

declare(strict_types=1);

namespace Alchemy\Workflow\Validator;

use Alchemy\Workflow\Event\WorkflowEvent;
use Alchemy\Workflow\Exception\InvalidEventException;
use Alchemy\Workflow\Model\OnEvent;

final class EventValidator implements EventValidatorInterface
{
    public function validateEvent(OnEvent $onEvent, WorkflowEvent $event): void
    {
        $inputs = $event->getInputs();

        foreach ($onEvent->getInputs() as $name => $spec) {
            if ($spec['required'] ?? false) {
                if (!isset($inputs[$name])) {
                    throw new InvalidEventException(sprintf('Input "%s" is required for event "%s"', $name, $onEvent->getName()));
                }
            }

            if ($inputs->offsetExists($name) && !empty($spec['type'])) {
                $inputType = get_debug_type($inputs[$name]);
                if ($inputType !== $spec['type']) {
                    throw new InvalidEventException(sprintf('Input "%s" must be type of %s for event "%s" (%s given)', $name, $spec['type'], $onEvent->getName(), $inputType));
                }
            }
        }
    }
}
