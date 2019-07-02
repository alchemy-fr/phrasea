<?php

declare(strict_types=1);

namespace App\Consumer\Handler;

use App\Entity\User;
use App\Mail\Mailer;
use Arthem\Bundle\RabbitBundle\Consumer\Event\AbstractEntityManagerHandler;
use Arthem\Bundle\RabbitBundle\Consumer\Event\EventMessage;
use Arthem\Bundle\RabbitBundle\Consumer\Exception\ObjectNotFoundForHandlerException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class UserInviteHandler extends AbstractEntityManagerHandler
{
    const EVENT = 'user_invite';

    /**
     * @var Mailer
     */
    private $mailer;

    /**
     * @var UrlGeneratorInterface
     */
    private $router;

    public function __construct(Mailer $mailer, UrlGeneratorInterface $router)
    {
        $this->mailer = $mailer;
        $this->router = $router;
    }

    public function handle(EventMessage $message): void
    {
        $userId = $message->getPayload()['id'];

        $em = $this->getEntityManager();

        $user = $em->find(User::class, $userId);
        if (!$user instanceof User) {
            throw new ObjectNotFoundForHandlerException(User::class, $userId, __CLASS__);
        }

        $this->mailer->send(
            $user->getEmail(),
            'You\'re invited to Uploader!',
            'mail/user_invite.html.twig',
            [
                'url' => $this->router->generate('invite_confirm', [
                    'id' => $user->getId(),
                    'token' => $user->getSecurityToken(),
                ], UrlGeneratorInterface::ABSOLUTE_URL),
            ]
        );
    }

    public static function getHandledEvents(): array
    {
        return [self::EVENT];
    }
}
