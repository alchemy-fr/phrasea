<?php

declare(strict_types=1);

namespace App\Consumer\Handler;

use Alchemy\NotifyBundle\Notify\Notifier;
use App\Entity\User;
use App\User\InviteManager;
use Arthem\Bundle\RabbitBundle\Consumer\Event\AbstractEntityManagerHandler;
use Arthem\Bundle\RabbitBundle\Consumer\Event\EventMessage;
use Arthem\Bundle\RabbitBundle\Consumer\Exception\ObjectNotFoundForHandlerException;
use DateTime;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManager;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Throwable;

class UserInviteHandler extends AbstractEntityManagerHandler
{
    const EVENT = 'user_invite';

    /**
     * @var UrlGeneratorInterface
     */
    private $router;
    /**
     * @var InviteManager
     */
    private $inviteManager;
    /**
     * @var Notifier
     */
    private $notifier;

    public function __construct(Notifier $notifier, UrlGeneratorInterface $router, InviteManager $inviteManager)
    {
        $this->router = $router;
        $this->inviteManager = $inviteManager;
        $this->notifier = $notifier;
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
                throw new ObjectNotFoundForHandlerException(User::class, $userId, __CLASS__);
            }

            if (!$this->inviteManager->userCanBeInvited($user)) {
                $em->rollback();

                return;
            }

            $user->setLastInviteAt(new DateTime());
            $em->persist($user);
            $em->flush();
            $em->commit();
        } catch (Throwable $e) {
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
