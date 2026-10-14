<?php

declare(strict_types=1);

namespace App\Api\Processor\Sort;

use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Core\Workspace;
use App\Security\Voter\AbstractVoter;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Persists the order of the items given as a JSON list of IDs (request body).
 */
abstract class AbstractSortProcessor implements ProcessorInterface
{
    use SecurityAwareTrait;

    public function __construct(
        protected readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * @return class-string
     */
    abstract protected function getClass(): string;

    abstract protected function getPositionField(): string;

    protected function isReversed(): bool
    {
        return false;
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): null
    {
        $ids = json_decode($context['request']->getContent(), true, 512, JSON_THROW_ON_ERROR);
        if (empty($ids)) {
            return null;
        }

        if ($this->isReversed()) {
            $ids = array_reverse($ids);
        }

        $class = $this->getClass();
        $firstItem = $this->em->find($class, $ids[0]);
        if (null === $firstItem) {
            throw new NotFoundHttpException(sprintf('%s %s not found', $class, $ids[0]));
        }

        $this->em->wrapInTransaction(function () use ($class, $ids, $firstItem): void {
            $i = 0;

            $queryBuilder = $this->em->createQueryBuilder()
                ->update($class, 't')
                ->set('t.'.$this->getPositionField(), ':p')
                ->andWhere('t.id = :id');
            $params = $this->buildQuery($queryBuilder, $firstItem);

            $query = $queryBuilder->getQuery();

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

        return null;
    }

    /**
     * Restricts the update to the scope of the first item, which must be editable.
     *
     * @return array<string, mixed> the query parameters
     */
    protected function buildQuery(QueryBuilder $queryBuilder, object $firstItem): array
    {
        if (!method_exists($firstItem, 'getWorkspace')) {
            throw new \RuntimeException(sprintf('Class %s must implement getWorkspace method to be sortable', $firstItem::class));
        }

        /** @var Workspace $workspace */
        $workspace = $firstItem->getWorkspace();
        $this->denyAccessUnlessGranted(AbstractVoter::EDIT, $workspace);

        $queryBuilder->andWhere('t.workspace = :ws');

        return [
            'ws' => $workspace->getId(),
        ];
    }
}
