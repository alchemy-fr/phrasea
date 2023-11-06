<?php

declare(strict_types=1);

namespace App\Controller;

use Alchemy\AuthBundle\Security\JwtUser;
use App\Consumer\Handler\DownloadHandler;
use App\Form\FormValidator;
use App\Model\DownloadUrl;
use Arthem\Bundle\RabbitBundle\Consumer\Event\EventMessage;
use Arthem\Bundle\RabbitBundle\Producer\EventProducer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class DownloadUrlAction extends AbstractController
{
    public function __construct(
        private readonly EventProducer $eventProducer,
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

        $this->eventProducer->publish(new EventMessage(DownloadHandler::EVENT, [
            'url' => $data->getUrl(),
            'form_data' => $data->getData(),
            'user_id' => $user->getId(),
            'target_id' => $data->getTarget()->getId(),
            'locale' => $request->getLocale() ?? $request->getDefaultLocale(),
        ]));

        return new JsonResponse(true);
    }
}
