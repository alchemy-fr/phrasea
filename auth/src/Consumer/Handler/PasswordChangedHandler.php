<?php

declare(strict_types=1);

namespace App\Consumer\Handler;

use App\Entity\AccessToken;
use App\Entity\User;
use App\Mail\Mailer;
use Arthem\Bundle\RabbitBundle\Consumer\Event\AbstractEntityManagerHandler;
use Arthem\Bundle\RabbitBundle\Consumer\Event\EventMessage;
use Arthem\Bundle\RabbitBundle\Consumer\Exception\ObjectNotFoundForHandlerException;

class PasswordChangedHandler extends AbstractEntityManagerHandler
{
    const EVENT = 'password_changed';

    /**
     * @var Mailer
     */
    private $mailer;

    public function __construct(Mailer $mailer)
    {
        $this->mailer = $mailer;
    }

    public function handle(EventMessage $message): void
    {
        $userId = $message->getPayload()['id'];

        $em = $this->getEntityManager();

        $user = $em->find(User::class, $userId);
        if (!$user instanceof User) {
            throw new ObjectNotFoundForHandlerException(User::class, $userId, __CLASS__);
        }

        $em
            ->getRepository(AccessToken::class)
            ->revokeTokens($user);

        $this->mailer->send(
            $user->getEmail(),
            'Password changed',
            'mail/password_changed.html.twig'
        );
    }

    public static function getHandledEvents(): array
    {
        return [self::EVENT];
    }
}
