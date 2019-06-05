<?php

declare(strict_types=1);

namespace App\Controller;

use App\Form\FormSchemaManager;
use App\Form\LiFormToFormTransformer;
use App\Model\FormData;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

final class ValidateFormAction extends AbstractController
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
    )
    {
        $this->formGenerator = $formGenerator;
        $this->schemaLoader = $schemaLoader;
    }

    public function __invoke(FormData $data, Request $request)
    {
        $formData = $data->getData();

        $schema = $this->schemaLoader->loadSchema($request->getLocale());
        $form = $this->formGenerator->createFormFromConfig($schema);

        $form->submit($formData);
        if ($form->isSubmitted() && $form->isValid()) {
            return new JsonResponse(['errors' => []]);
        }


        return new JsonResponse(['errors' => $this->getFormErrors($form)]);
    }

    protected function getFormErrors(FormInterface $form): array
    {
        $errors = [];

        // Global
        foreach ($form->getErrors() as $error) {
            $errors['_form'][] = $error->getMessage();
        }

        // Fields
        foreach ($form as $child/** @var FormInterface $child */) {
            if (!$child->isValid()) {
                foreach ($child->getErrors() as $error) {
                    $errors[$child->getName()][] = $error->getMessage();
                }
            }
        }

        return $errors;
    }
}
