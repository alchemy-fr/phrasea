<?php

declare(strict_types=1);

namespace App\Controller\Core;

use App\Entity\Core\Workspace;
use App\Security\Voter\AbstractVoter;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

abstract class AbstractSortAction extends AbstractController
{
    public function __construct(protected readonly EntityManagerInterface $em)
    {
    }

    protected function sort(Request $request, string $class, string $positionField, bool $reverse = false): Response
    {
        $ids = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        if (empty($ids)) {
            return new Response();
        }

        if ($reverse) {
            $ids = array_reverse($ids);
        }

        $firstItem = $this->em->find($class, $ids[0]);
        if (null === $firstItem) {
            throw new NotFoundHttpException(sprintf('%s %s not found', $class, $ids[0]));
        }

        $this->em->wrapInTransaction(function () use ($class, $ids, $firstItem, $positionField) {
            $i = 0;

            $queryBuilder = $this->em->createQueryBuilder()
                ->update($class, 't')
                ->set('t.'.$positionField, ':p')
                ->andWhere('t.id = :id');
            $params = $this->buildQuery($queryBuilder, $firstItem);

            $query = $queryBuilder
                ->getQuery();

            foreach ($ids as $id) {
                $query
                    ->setParameter('id', $id)
                    ->setParameter('p', $i++);

                foreach ($params as $key => $value) {
                    $query->setParameter($key, $value);
                }

                $query->execute();
            }
        });

        return new Response();
    }

    protected function buildQuery(QueryBuilder $queryBuilder, object $firstItem): array
    {
        if (!method_exists($firstItem, 'getWorkspace')) {
            throw new \RuntimeException(sprintf('Class %s must implement getWorkspace method to be sortable', $firstItem::class));
        }

        /** @var Workspace $workspace */
        $workspace = $firstItem->getWorkspace();
        $this->denyAccessUnlessGranted(AbstractVoter::EDIT, $workspace);

        $queryBuilder
            ->andWhere('t.workspace = :ws');

        return [
            'ws' => $workspace->getId()
        ];
    }
}
