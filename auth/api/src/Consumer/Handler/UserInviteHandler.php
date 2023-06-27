<?php

declare(strict_types=1);

namespace App\Consumer\Handler;

use Alchemy\NotifyBundle\Notify\NotifierInterface;
use App\Entity\User;
use App\User\InviteManager;
use Arthem\Bundle\RabbitBundle\Consumer\Event\AbstractEntityManagerHandler;
use Arthem\Bundle\RabbitBundle\Consumer\Event\EventMessage;
use Arthem\Bundle\RabbitBundle\Consumer\Exception\ObjectNotFoundForHandlerException;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManager;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class UserInviteHandler extends AbstractEntityManagerHandler
{
    final public const EVENT = 'user_invite';

    public function __construct(private readonly NotifierInterface $notifier, private readonly UrlGeneratorInterface $router, private readonly InviteManager $inviteManager)
    {
    }

    public function handle(EventMessage $message): void
    {
        $userId = $message->getPayload()['id'];

        /** @var EntityManager $em */
        $em = $this->getEntityManager();
        $em->beginTransaction();
        try {
            $user = $em->find(User::class, $userId, LockMode::PESSIMISTIC_WRITE);
            if (!$user instanceof User) {
                throw new ObjectNotFoundForHandlerException(User::class, $userId, self::class);
            }

            if (!$this->inviteManager->userCanBeInvited($user)) {
                $em->rollback();

                return;
            }

            $user->setLastInviteAt(new \DateTime());
            $em->persist($user);
            $em->flush();
            $em->commit();
        } catch (\Throwable $e) {
            $em->rollback();
            throw $e;
        }

        $this->notifier->notifyUser(
            $user->getId(),
            'auth/user_invite',
            [
                'url' => $this->router->generate('invite_confirm', [
                    'id' => $user->getId(),
                    'token' => $user->getSecurityToken(),
                    '_locale' => $user->getLocale() ?? 'en',
                ], UrlGeneratorInterface::ABSOLUTE_URL),
            ],
            [
                'email' => $user->getEmail(),
            ]
        );
    }

    public static function getHandledEvents(): array
    {
        return [self::EVENT];
    }
}
