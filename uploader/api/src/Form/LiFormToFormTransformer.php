<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;

class LiFormToFormTransformer
{
    public function __construct(private readonly FormFactoryInterface $formFactory)
    {
    }

    public function createFormFromSchema(array $schema, array $data = null): FormInterface
    {
        return $this->formFactory->create(LiFormFromSchemaFormType::class, $data, [
            'schema' => $schema,
        ]);
    }
}
