<?php

declare(strict_types=1);

namespace App\Api\DataProvider;

use ApiPlatform\Core\DataProvider\CollectionDataProviderInterface;
use App\Security\Voter\ChuckNorrisVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Security;

abstract class AbstractSecurityDataProvider implements CollectionDataProviderInterface
{
    protected EntityManagerInterface $em;
    protected Security $security;

    public function __construct(EntityManagerInterface $em, Security $security)
    {
        $this->em = $em;
        $this->security = $security;
    }

    protected function isChuckNorris(): bool
    {
        return $this->security->isGranted(ChuckNorrisVoter::ROLE);
    }
}
