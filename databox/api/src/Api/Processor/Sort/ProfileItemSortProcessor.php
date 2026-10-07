<?php

declare(strict_types=1);

namespace App\Api\Processor\Sort;

use App\Entity\Profile\Profile;
use App\Entity\Profile\ProfileItem;
use App\Security\Voter\AbstractVoter;
use Doctrine\ORM\QueryBuilder;

final class ProfileItemSortProcessor extends AbstractSortProcessor
{
    protected function getClass(): string
    {
        return ProfileItem::class;
    }

    protected function getPositionField(): string
    {
        return 'position';
    }

    /**
     * @param ProfileItem $firstItem
     */
    #[\Override]
    protected function buildQuery(QueryBuilder $queryBuilder, object $firstItem): array
    {
        /** @var Profile $profile */
        $profile = $firstItem->getProfile();
        $this->denyAccessUnlessGranted(AbstractVoter::EDIT, $profile);

        $queryBuilder->andWhere('t.profile = :profile');

        return [
            'profile' => $profile->getId(),
        ];
    }
}
