<?php

declare(strict_types=1);

namespace App\Security\Voter;

use Alchemy\RemoteAuthBundle\Model\RemoteUser;
use App\Entity\Asset;
use App\Entity\Publication;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\Security;

class AssetVoter extends Voter
{
    const READ = 'READ';
    const EDIT = 'EDIT';

    private Security $security;
    private EntityManagerInterface $em;

    public function __construct(Security $security, EntityManagerInterface $em)
    {
        $this->security = $security;
        $this->em = $em;
    }

    protected function supports($attribute, $subject)
    {
        return $subject instanceof Asset;
    }

    /**
     * @param Asset|null $subject
     */
    protected function voteOnAttribute($attribute, $subject, TokenInterface $token)
    {
        if ($this->security->isGranted('ROLE_PUBLISH') || $this->security->isGranted('ROLE_ADMIN')) {
            return true;
        }

        if ($subject instanceof Asset) {
            $user = $token->getUser();

            if ($user instanceof RemoteUser) {
                $publication = $this->em->getRepository(Asset::class)
                    ->findAssetPublicationOwnedBy($subject, $user->getId());

                return $publication instanceof Publication;
            }
        }

        return false;
    }
}
