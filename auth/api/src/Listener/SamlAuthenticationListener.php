<?php

declare(strict_types=1);

namespace App\Listener;

use App\Entity\SamlIdentity;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\Expr\Join;
use Hslavich\OneloginSamlBundle\Security\Authentication\Token\SamlTokenInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Symfony\Component\Security\Http\SecurityEvents;

class SamlAuthenticationListener implements EventSubscriberInterface
{
    /**
     * @var EntityManagerInterface
     */
    private $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    public static function getSubscribedEvents()
    {
        return [
            SecurityEvents::INTERACTIVE_LOGIN => 'onAuthenticate',
        ];
    }

    public function onAuthenticate(InteractiveLoginEvent $event): void
    {
        $token = $event->getAuthenticationToken();
        if ($token instanceof SamlTokenInterface) {
            /** @var User $user */
            $samlIdentity = $token->getUser();

        }
    }

    private function getSamlIdentity(User $user, $token): SamlIdentity
    {
        $user = $this->em->createQueryBuilder()
            ->select('u')
            ->from(User::class, 'u')
            ->innerJoin(SamlIdentity::class, 'i', Join::WITH, 'i.user = u.id')
            ->andWhere('i. = :username')
            ->andWhere('i.username = :username')
            ->setParameter('username', $user->getUsername())
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
}
