<?php

declare(strict_types=1);

namespace App\Controller;

use App\Form\FormSchemaManager;
use App\Form\FormValidator;
use App\Form\LiFormToFormTransformer;
use App\Model\FormData;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

final class ValidateFormAction extends AbstractController
{
    /**
     * @var FormValidator
     */
    private $formValidator;

    public function __construct(FormValidator $formValidator)
    {
        $this->formValidator = $formValidator;
    }

    public function __invoke(FormData $data, Request $request)
    {
        $errors = $this->formValidator->validateForm($data->getData(), $request);

        return new JsonResponse(['errors' => $errors]);
    }
}
