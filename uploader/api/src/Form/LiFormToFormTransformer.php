<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;

class LiFormToFormTransformer
{
    /**
     * @var FormFactoryInterface
     */
    private $formFactory;

    public function __construct(FormFactoryInterface $formFactory)
    {
        $this->formFactory = $formFactory;
    }

    public function createFormFromSchema(array $schema, array $data = null): FormInterface
    {
        return $this->formFactory->create(LiFormFromSchemaFormType::class, $data, [
            'schema' => $schema,
        ]);
    }
}
