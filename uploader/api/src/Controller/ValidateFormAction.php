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
    public function __construct(private readonly FormValidator $formValidator, private readonly ValidatorInterface $validator)
    {
    }

    public function __invoke(FormData $data, Request $request)
    {
        $this->validator->validate($data);
        $errors = $this->formValidator->validateForm($data->getData(), $data->getTarget(), $request);

        return new JsonResponse(['errors' => $errors]);
    }
}
