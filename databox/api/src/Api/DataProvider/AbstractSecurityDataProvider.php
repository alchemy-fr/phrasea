<?php

declare(strict_types=1);

namespace App\Api\DataProvider;

use ApiPlatform\Core\DataProvider\CollectionDataProviderInterface;
use App\Security\Voter\ChuckNorrisVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Security;

abstract class AbstractSecurityDataProvider implements CollectionDataProviderInterface
{
    public function __construct(protected EntityManagerInterface $em, protected Security $security)
    {
    }

    protected function isChuckNorris(): bool
    {
        return $this->security->isGranted(ChuckNorrisVoter::ROLE);
    }
}
