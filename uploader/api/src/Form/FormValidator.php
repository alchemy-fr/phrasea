<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Target;
use App\Form\Builder\BuilderFormHandler;

final readonly class FormValidator
{
    /**
     * What a target without form answers to a value: it accepts none.
     */
    public const string NO_FORM_ERROR = 'This form should not contain extra fields.';

    public function __construct(
        private BuilderFormHandler $builderFormHandler,
    ) {
    }

    /**
     * @return array<string, string[]> the messages by field key, "_form" for the whole form
     */
    public function validateForm(array $data, Target $target): array
    {
        $definition = $target->getFormDefinition();
        if (null !== $definition) {
            return $this->builderFormHandler->validate($definition, $data);
        }

        if ([] !== self::cleanExtraFields($data)) {
            return ['_form' => [self::NO_FORM_ERROR]];
        }

        return [];
    }

    /**
     * The data to store with the commit. A form keeps its own fields only and imposes its hidden values;
     * without form, the data is kept as is.
     */
    public function prepareData(array $data, Target $target): array
    {
        $definition = $target->getFormDefinition();
        if (null !== $definition) {
            return $this->builderFormHandler->prepare($definition, $data);
        }

        return $data;
    }

    public static function cleanExtraFields(array $data): array
    {
        foreach ($data as $key => $v) {
            if (str_starts_with((string) $key, '__')) {
                unset($data[$key]);
            }
        }

        return $data;
    }
}
