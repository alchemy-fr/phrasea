<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Target;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;

class FormValidator
{
    public function __construct(private readonly LiFormToFormTransformer $formGenerator, private readonly FormSchemaManager $schemaLoader)
    {
    }

    public function validateForm(array $data, Target $target, Request $request): array
    {
        $schema = $this->schemaLoader->loadSchema($target->getId(), $request->getLocale());
        $form = $this->formGenerator->createFormFromSchema($schema);

        $data = self::cleanExtraFields($data);
        $form->submit($data);
        $errors = [];
        if ($form->isSubmitted() && $form->isValid()) {
            return $errors;
        }

        return $this->getFormErrors($form);
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
