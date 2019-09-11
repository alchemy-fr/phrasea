<?php

declare(strict_types=1);

namespace App\Consumer\Handler;

use App\Entity\User;
use App\Mail\Mailer;
use Arthem\Bundle\RabbitBundle\Consumer\Event\AbstractEntityManagerHandler;
use Arthem\Bundle\RabbitBundle\Consumer\Event\EventMessage;
use Arthem\Bundle\RabbitBundle\Consumer\Exception\ObjectNotFoundForHandlerException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class RegistrationHandler extends AbstractEntityManagerHandler
{
    const EVENT = 'registration';

    /**
     * @var Mailer
     */
    private $mailer;

    /**
     * @var UrlGeneratorInterface
     */
    private $urlGenerator;

    public function __construct(Mailer $mailer, UrlGeneratorInterface $urlGenerator)
    {
        $this->mailer = $mailer;
        $this->urlGenerator = $urlGenerator;
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
            'Registration',
            'mail/registration.html.twig',
            [
                'confirm_url' => $this->urlGenerator->generate('registration_confirm', [
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
