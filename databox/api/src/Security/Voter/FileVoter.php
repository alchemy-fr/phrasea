<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Core\Asset;
use App\Entity\Core\File;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

class FileVoter extends AbstractVoter
{
    protected function supports(string $attribute, $subject): bool
    {
        return $subject instanceof File;
    }

    /**
     * @param File $subject
     */
    protected function voteOnAttribute(string $attribute, $subject, TokenInterface $token): bool
    {
        $assets = $this->em->createQueryBuilder('a')
            ->select('a')
            ->distinct()
            ->from(Asset::class, 'a')
            ->leftJoin('a.renditions', 'r')
            ->andWhere('a.source = :f OR r.file = :f')
            ->addGroupBy('a.id')
            ->setParameter('f', $subject->getId())
            ->getQuery()
            ->toIterable()
        ;

        foreach ($assets as $asset) {
            if ($this->security->isGranted($attribute, $asset)) {
                return true;
            }
        }

        return false;
    }
}
