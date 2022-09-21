<?php

declare(strict_types=1);

namespace App\Controller;

use ApiPlatform\Core\Validator\ValidatorInterface;
use App\Form\FormValidator;
use App\Model\FormData;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

final class ValidateFormAction extends AbstractController
{
    private FormValidator $formValidator;
    private ValidatorInterface $validator;

    public function __construct(FormValidator $formValidator, ValidatorInterface $validator)
    {
        $this->formValidator = $formValidator;
        $this->validator = $validator;
    }

    public function __invoke(FormData $data, Request $request)
    {
        file_put_contents("/configs/trace.txt", sprintf("%s (%d) \n", __FILE__, __LINE__), FILE_APPEND);
        $this->validator->validate($data);
        $errors = $this->formValidator->validateForm($data->getData(), $data->getTarget(), $request);

        return new JsonResponse(['errors' => $errors]);
    }
}
