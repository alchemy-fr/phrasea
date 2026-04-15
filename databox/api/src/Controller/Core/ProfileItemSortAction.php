<?php

declare(strict_types=1);

namespace App\Controller\Core;

use App\Entity\Profile\Profile;
use App\Entity\Profile\ProfileItem;
use App\Security\Voter\AbstractVoter;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ProfileItemSortAction extends AbstractSortAction
{
    public function __invoke(Request $request): Response
    {
        return $this->sort($request, ProfileItem::class, 'position');
    }

    /**
     * @param ProfileItem $firstItem
     */
    protected function buildQuery(QueryBuilder $queryBuilder, object $firstItem): array
    {
        if (!method_exists($firstItem, 'getProfile')) {
            throw new \RuntimeException(sprintf('Class %s must implement getProfile method to be sortable', $firstItem::class));
        }

        /** @var Profile $profile */
        $profile = $firstItem->getProfile();
        $this->denyAccessUnlessGranted(AbstractVoter::EDIT, $profile);

        $queryBuilder
            ->andWhere('t.profile = :profile');

        return [
            'profile' => $profile->getId(),
        ];
    }
}
