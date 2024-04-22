<?php

declare(strict_types=1);

namespace App\Controller;

use Alchemy\AuthBundle\Security\JwtUser;
use App\Consumer\Handler\Download;
use App\Form\FormValidator;
use App\Model\DownloadUrl;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;

final class DownloadUrlAction extends AbstractController
{
    public function __construct(
        private readonly MessageBusInterface $bus,
        private readonly FormValidator $formValidator
    ) {
    }

    public function __invoke(DownloadUrl $data, Request $request, ValidateFormAction $validateFormAction): Response
    {
        $errors = $this->formValidator->validateForm($data->getData(), $data->getTarget(), $request);
        if (!empty($errors)) {
            return new JsonResponse(['errors' => $errors]);
        }

        /** @var JwtUser $user */
        $user = $this->getUser();

        $this->bus->dispatch(new Download(
            $data->getUrl(),
            $user->getId(),
            $data->getTarget()->getId(),
            $data->getData(),
            $request->getLocale() ?? $request->getDefaultLocale(),
        ));

        return new JsonResponse(true);
    }
}
