<?php

declare(strict_types=1);

namespace App\Controller;

use App\Mail\MailerRabbitProxy;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route(path: '/', methods: ['POST'])]
class ApiController extends AbstractController
{
    #[Route(path: '/send-email')]
    public function sendEmail(Request $request, MailerRabbitProxy $mailerRabbitProxy): JsonResponse
    {
        $mailerRabbitProxy->sendEmail($request);

        return new JsonResponse(true);
    }

    #[Route(path: '/notify-user')]
    public function notifyUser(Request $request, MailerRabbitProxy $mailerRabbitProxy): JsonResponse
    {
        $mailerRabbitProxy->notifyUser($request);

        return new JsonResponse(true);
    }

    #[Route(path: '/notify-topic/{topic}')]
    public function notifyTopic(string $topic, Request $request, MailerRabbitProxy $mailerRabbitProxy): JsonResponse
    {
        $mailerRabbitProxy->notifyTopic($topic, $request);

        return new JsonResponse(true);
    }

    #[Route(path: '/register-user')]
    public function registerUser(Request $request, MailerRabbitProxy $mailerRabbitProxy): JsonResponse
    {
        $mailerRabbitProxy->registerUser($request);

        return new JsonResponse(true);
    }

    #[Route(path: '/delete-user')]
    public function deleteUser(Request $request, MailerRabbitProxy $mailerRabbitProxy): JsonResponse
    {
        $mailerRabbitProxy->deleteUser($request);

        return new JsonResponse(true);
    }
}
