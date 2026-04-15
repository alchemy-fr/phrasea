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
            throw new \RuntimeException(sprintf('Class %s must implement getList method to be sortable', $firstItem::class));
        }

        /** @var Profile $list */
        $list = $firstItem->getProfile();
        $this->denyAccessUnlessGranted(AbstractVoter::EDIT, $list);

        $queryBuilder
            ->andWhere('t.list = :list');

        return [
            'list' => $list->getId(),
        ];
    }
}
