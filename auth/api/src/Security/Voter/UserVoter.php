<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\AccessToken;
use App\Entity\OAuthClient;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use FOS\OAuthServerBundle\Security\Authentication\Token\OAuthToken;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class UserVoter extends Voter
{
    /**
     * @var EntityManagerInterface
     */
    private $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    protected function supports($attribute, $subject)
    {
        return $subject instanceof User;
    }

    protected function voteOnAttribute($attribute, $subject, TokenInterface $token)
    {
        if ($token instanceof OAuthToken && null === $token->getUser()) {
            $accessToken = $this->em->getRepository(AccessToken::class)->findOneBy([
                'token' => $token->getToken(),
            ]);

            /** @var OAuthClient $client */
            $client = $accessToken->getClient();

            if ($client->hasAuthorization(ClientAuthorizations::READ_USERS)) {
                return true;
            }
        }

        if ($token->getUser() instanceof User && $token->getUser() === $subject) {
            return true;
        }

        return false;
    }
}
