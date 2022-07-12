<?php

declare(strict_types=1);

namespace App\Listener;

use App\Entity\SamlIdentity;
use App\Entity\User;
use App\Saml\SamlGroupManager;
use Doctrine\ORM\EntityManagerInterface;
use Hslavich\OneloginSamlBundle\Security\Authentication\Token\SamlTokenInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Symfony\Component\Security\Http\SecurityEvents;

class SamlAuthenticationListener implements EventSubscriberInterface
{
    private EntityManagerInterface $em;
    private SamlGroupManager $groupManager;

    public function __construct(EntityManagerInterface $em, SamlGroupManager $groupManager)
    {
        $this->em = $em;
        $this->groupManager = $groupManager;
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
            $user = $token->getUser();
            $samlIdentity = $this->getSamlIdentity($user, $token);
            $samlIdentity->setAttributes($token->getAttributes());

            $this->groupManager->updateGroups($token->getIdpName(), $user, $token);

            $this->em->persist($samlIdentity);
            $this->em->flush();
        }
    }

    private function getSamlIdentity(User $user, SamlTokenInterface $token): SamlIdentity
    {
        $samlIdentity = $this->em->getRepository(SamlIdentity::class)
            ->findOneBy([
                'user' => $user->getId(),
                'provider' => $token->getIdpName(),
            ]);

        if (null === $samlIdentity) {
            $samlIdentity = new SamlIdentity();
            $samlIdentity->setUser($user);
            $samlIdentity->setProvider($token->getIdpName());
        }

        return $samlIdentity;
    }
}
