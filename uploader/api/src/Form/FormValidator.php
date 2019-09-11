<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;

class FormValidator
{
    /**
     * @var LiFormToFormTransformer
     */
    private $formGenerator;
    /**
     * @var FormSchemaManager
     */
    private $schemaLoader;

    public function __construct(
        LiFormToFormTransformer $formGenerator,
        FormSchemaManager $schemaLoader
    ) {
        $this->formGenerator = $formGenerator;
        $this->schemaLoader = $schemaLoader;
    }

    public function validateForm(array $data, Request $request): array
    {
        $schema = $this->schemaLoader->loadSchema($request->getLocale());
        $form = $this->formGenerator->createFormFromSchema($schema);

        $form->submit($data);
        $errors = [];
        if ($form->isSubmitted() && $form->isValid()) {
            return $errors;
        }

        return $this->getFormErrors($form);
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
