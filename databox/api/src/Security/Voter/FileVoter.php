<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Core\Asset;
use App\Entity\Core\AssetAttachment;
use App\Entity\Core\File;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

class FileVoter extends AbstractVoter
{
    protected function supports(string $attribute, $subject): bool
    {
        return $subject instanceof File;
    }

    public function supportsType(string $subjectType): bool
    {
        return is_a($subjectType, File::class, true);
    }

    /**
     * @param File $subject
     */
    protected function voteOnAttribute(string $attribute, $subject, TokenInterface $token): bool
    {
        $rows = $this->em->createQueryBuilder()
            ->select('a.id')
            ->distinct()
            ->from(Asset::class, 'a')
            ->leftJoin('a.renditions', 'r')
            ->andWhere('a.source = :f OR r.file = :f')
            ->addGroupBy('a.id')
            ->setParameter('f', $subject->getId())
            ->getQuery()
            ->toIterable()
        ;

        foreach ($rows as $row) {
            $asset = $this->em->find(Asset::class, $row['id']);
            if ($this->security->isGranted($attribute, $asset, $token)) {
                return true;
            }
        }

        $rows = $this->em->createQueryBuilder()
            ->select('IDENTITY(a.asset) AS id')
            ->from(AssetAttachment::class, 'a')
            ->andWhere('a.file = :f')
            ->setParameter('f', $subject->getId())
            ->getQuery()
            ->toIterable()
        ;

        foreach ($rows as $row) {
            $asset = $this->em->find(Asset::class, $row['id']);
            if ($this->security->isGranted($attribute, $asset, $token)) {
                return true;
            }
        }

        return false;
    }
}
