<?php

declare(strict_types=1);

namespace App\Controller\Core;

use App\Entity\AttributeList\AttributeList;
use App\Entity\AttributeList\AttributeListItem;
use App\Security\Voter\AbstractVoter;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class AttributeListItemSortAction extends AbstractSortAction
{
    public function __invoke(Request $request): Response
    {
        return $this->sort($request, AttributeListItem::class, 'priority', true);
    }

    protected function buildQuery(QueryBuilder $queryBuilder, object $firstItem): array
    {
        if (!method_exists($firstItem, 'getList')) {
            throw new \RuntimeException(sprintf('Class %s must implement getList method to be sortable', $firstItem::class));
        }

        /** @var AttributeList $list */
        $list = $firstItem->getList();
        $this->denyAccessUnlessGranted(AbstractVoter::EDIT, $list);

        $queryBuilder
            ->andWhere('t.list = :list');

        return [
            'list' => $list->getId()
        ];
    }
}
