<?php

declare(strict_types=1);

namespace App\Consumer\Handler;

use Alchemy\NotifyBundle\Notify\NotifierInterface;
use App\Entity\User;
use Arthem\Bundle\RabbitBundle\Consumer\Event\AbstractEntityManagerHandler;
use Arthem\Bundle\RabbitBundle\Consumer\Event\EventMessage;
use Arthem\Bundle\RabbitBundle\Consumer\Exception\ObjectNotFoundForHandlerException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class RegistrationHandler extends AbstractEntityManagerHandler
{
    final public const EVENT = 'registration';

    public function __construct(private readonly NotifierInterface $notifier, private readonly UrlGeneratorInterface $urlGenerator)
    {
    }

    public function handle(EventMessage $message): void
    {
        $userId = $message->getPayload()['id'];

        $em = $this->getEntityManager();

        $user = $em->find(User::class, $userId);
        if (!$user instanceof User) {
            throw new ObjectNotFoundForHandlerException(User::class, $userId, self::class);
        }

        $this->notifier->notifyUser(
            $user->getId(),
            'auth/registration',
            [
                'confirm_url' => $this->urlGenerator->generate('registration_confirm', [
                    '_locale' => $user->getLocale(),
                    'id' => $user->getId(),
                    'token' => $user->getSecurityToken(),
                ], UrlGeneratorInterface::ABSOLUTE_URL),
            ],
            [
                'email' => $user->getEmail(),
                'locale' => $user->getLocale(),
            ]
        );
    }

    public static function getHandledEvents(): array
    {
        return [self::EVENT];
    }
}
