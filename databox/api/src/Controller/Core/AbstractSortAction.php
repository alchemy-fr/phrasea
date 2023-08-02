<?php

declare(strict_types=1);

namespace App\Controller\Core;

use App\Entity\Core\Workspace;
use App\Security\Voter\WorkspaceVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

abstract class AbstractSortAction extends AbstractController
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    protected function sort(Request $request, string $class, string $positionField, bool $reverse = false): void
    {
        $ids = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        if (empty($ids)) {
            return;
        }

        if ($reverse) {
            $ids = array_reverse($ids);
        }

        $firstItem = $this->em->find($class, $ids[0]);
        if (null === $firstItem) {
            throw new NotFoundHttpException(sprintf('%s %s not found', $class, $ids[0]));
        }
        if (!method_exists($firstItem, 'getWorkspace')) {
            throw new \RuntimeException(sprintf('Class %s must implement getWorkspace method to be sortable', $class));
        }

        /** @var Workspace $workspace */
        $workspace = $firstItem->getWorkspace();
        $this->denyAccessUnlessGranted(WorkspaceVoter::EDIT, $workspace);

        $this->em->wrapInTransaction(function () use ($class, $ids, $positionField, $workspace) {
            $i = 0;

            $query = $this->em->createQueryBuilder()
                ->update($class, 't')
                ->set('t.'.$positionField, ':p')
                ->andWhere('t.workspace = :ws')
                ->andWhere('t.id = :id')
                ->getQuery();

            foreach ($ids as $id) {
                $query
                    ->setParameter('id', $id)
                    ->setParameter('p', $i++)
                    ->setParameter('ws', $workspace->getId())
                    ->execute()
                ;
            }
        });
    }
}
