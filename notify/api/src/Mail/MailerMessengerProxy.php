<?php

declare(strict_types=1);

namespace App\Mail;

use App\Consumer\Handler\DeleteUser;
use App\Consumer\Handler\NotifyTopic;
use App\Consumer\Handler\NotifyUser;
use App\Consumer\Handler\RegisterUser;
use App\Consumer\Handler\SendEmail;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class MailerMessengerProxy
{
    public function __construct(private MessageBusInterface $bus, private Mailer $mailer)
    {
    }

    public function sendEmail(Request $request): void
    {
        $email = $request->request->get('email');
        if (!$email) {
            throw new BadRequestHttpException('Missing email');
        }
        $template = $request->request->get('template');
        if (!$template) {
            throw new BadRequestHttpException('Missing template');
        }
        $locale = $request->request->get('locale');
        if (!$locale) {
            throw new BadRequestHttpException('Missing locale');
        }
        $parameters = $request->request->all('parameters');

        $this->mailer->validateParameters($template, $parameters);

        $this->bus->dispatch(new SendEmail(
            $email,
            $template,
            $parameters,
            $locale,
        ));
    }

    public function notifyUser(Request $request): void
    {
        $userId = $request->request->get('user_id');
        if (!$userId) {
            throw new BadRequestHttpException('Missing user_id');
        }
        $contactInfo = $request->request->get('contact_info');

        $template = $request->request->get('template');
        if (!$template) {
            throw new BadRequestHttpException('Missing template');
        }

        $parameters = $request->request->all('parameters');

        $this->mailer->validateParameters($template, $parameters);

        $this->bus->dispatch(new NotifyUser(
            $userId,
            $template,
            $parameters,
            $contactInfo,
        ));
    }

    public function notifyTopic(string $topic, Request $request): void
    {
        $template = $request->request->get('template');
        if (!$template) {
            throw new BadRequestHttpException('Missing template');
        }

        $parameters = $request->request->all('parameters');

        $this->mailer->validateParameters($template, $parameters);

        $this->bus->dispatch(new NotifyTopic(
            $topic,
            $template,
            $parameters,
        ));
    }

    public function registerUser(Request $request): void
    {
        $userId = $request->request->get('user_id');
        if (!$userId) {
            throw new BadRequestHttpException('Missing user_id');
        }
        $contactInfo = $request->request->get('contact_info');
        if (!$contactInfo) {
            throw new BadRequestHttpException('Missing contact_info');
        }
        if (!is_array($contactInfo)) {
            throw new BadRequestHttpException('contact_info must be an array');
        }

        $this->bus->dispatch(new RegisterUser($userId, $contactInfo));
    }

    public function deleteUser(Request $request): void
    {
        $userId = $request->request->get('user_id');
        if (!$userId) {
            throw new BadRequestHttpException('Missing user_id');
        }

        $this->bus->dispatch(new DeleteUser($userId));
    }
}
