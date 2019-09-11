<?php

declare(strict_types=1);

namespace App\Consumer\Handler;

use App\Entity\User;
use App\Mail\Mailer;
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
     * @var Mailer
     */
    private $mailer;

    /**
     * @var UrlGeneratorInterface
     */
    private $router;
    /**
     * @var InviteManager
     */
    private $inviteManager;

    public function __construct(Mailer $mailer, UrlGeneratorInterface $router, InviteManager $inviteManager)
    {
        $this->mailer = $mailer;
        $this->router = $router;
        $this->inviteManager = $inviteManager;
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
