<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\PublicationAsset;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\Security;

class PublicationAssetVoter extends Voter
{
    const READ = 'READ';

    /**
     * @var Security
     */
    private $security;

    public function __construct(Security $security)
    {
        $this->security = $security;
    }

    protected function supports($attribute, $subject)
    {
        return $subject instanceof PublicationAsset;
    }

    /**
     * @param PublicationAsset|null $subject
     */
    protected function voteOnAttribute($attribute, $subject, TokenInterface $token)
    {
        return $this->security->isGranted(PublicationVoter::READ, $subject->getPublication());
    }
}
