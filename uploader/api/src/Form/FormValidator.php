<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\FormSchema;
use App\Entity\Target;
use App\Form\Builder\BuilderFormHandler;
use App\Form\Builder\FormDefinition;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;

final readonly class FormValidator
{
    public function __construct(
        private LiFormToFormTransformer $formGenerator,
        private FormSchemaManager $schemaLoader,
        private BuilderFormHandler $builderFormHandler,
    ) {
    }

    public function validateForm(array $data, Target $target, Request $request): array
    {
        $formSchema = $this->schemaLoader->loadSchema($target->getId(), $request->getLocale());
        if ($formSchema?->isBuilder()) {
            return $this->builderFormHandler->validate(FormDefinition::fromArray($formSchema->getData()), $data);
        }

        $form = $this->formGenerator->createFormFromSchema($formSchema?->getData() ?? []);

        $data = self::cleanExtraFields($data);
        $form->submit($data);
        $errors = [];
        if ($form->isSubmitted() && $form->isValid()) {
            return $errors;
        }

        return $this->getFormErrors($form);
    }

    /**
     * The data to store with the commit. A builder form keeps its own fields only and imposes its hidden values;
     * LiForm data is kept as is.
     */
    public function prepareData(array $data, Target $target, Request $request): array
    {
        $formSchema = $this->schemaLoader->loadSchema($target->getId(), $request->getLocale());
        if ($formSchema instanceof FormSchema && $formSchema->isBuilder()) {
            return $this->builderFormHandler->prepare(FormDefinition::fromArray($formSchema->getData()), $data);
        }

        return $data;
    }

    public static function cleanExtraFields(array $data): array
    {
        foreach ($data as $key => $v) {
            if (str_starts_with($key, '__')) {
                unset($data[$key]);
            }
        }

        return $data;
    }

    private function getFormErrors(FormInterface $form): array
    {
        $errors = [];

        // Global
        foreach ($form->getErrors() as $error) {
            $errors['_form'][] = $error->getMessage();
        }

        // Fields
        foreach ($form as $child/* @var FormInterface $child */) {
            if (!$child->isValid()) {
                foreach ($child->getErrors() as $error) {
                    $errors[$child->getName()][] = $error->getMessage();
                }
            }
        }

        return $errors;
    }
}
