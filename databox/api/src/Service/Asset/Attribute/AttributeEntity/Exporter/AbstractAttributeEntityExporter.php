<?php

declare(strict_types=1);

namespace App\Service\Asset\Attribute\AttributeEntity\Exporter;

use App\Entity\Core\AttributeEntity;
use App\Repository\Core\AttributeEntityRepository;
use Doctrine\ORM\EntityManagerInterface;

abstract readonly class AbstractAttributeEntityExporter implements AttributeEntityExporterInterface
{
    public function __construct(
        private EntityManagerInterface $em,
        private AttributeEntityRepository $attributeEntityRepository,
    ) {
    }

    protected function handle(callable $handler, string $listId): void
    {
        $page = 0;
        while (true) {
            if (!$this->iterate($handler, $listId, $page++)) {
                return;
            }
        }
    }

    private function iterate(callable $handler, string $listId, int $page): bool
    {
        $limit = 2;
        /** @var AttributeEntity[] $results */
        $results = $this->attributeEntityRepository->createQueryBuilder('t')
            ->select('t')
            ->andWhere('t.list = :list')
            ->setFirstResult($page * $limit)
            ->setMaxResults($limit)
            ->setParameter('list', $listId)
            ->getQuery()
            ->toIterable()
        ;

        $i = 0;
        foreach ($results as $result) {
            $handler($result);
            ++$i;
        }

        $this->em->clear();

        return $i === $limit;
    }
}
