<?php

declare(strict_types=1);

namespace App\Service\Asset\Attribute\AttributeEntity\Importer;

use App\Entity\Core\AttributeEntity;
use App\Entity\Core\EntityList;
use App\Repository\Core\AttributeEntityRepository;
use Doctrine\ORM\EntityManagerInterface;

final readonly class RawAttributeEntityImporter implements AttributeEntityImporterInterface
{
    public function __construct(
        private EntityManagerInterface $em,
        private AttributeEntityRepository $attributeEntityRepository,
    ) {
    }

    public static function getName(): string
    {
        return 'raw';
    }

    public function import(EntityList $entityList, string $data): void
    {
        $values = explode("\n", $data)
            |> (fn (array $x): array => array_map(fn (string $r): string => trim($r), $x))
            |> (fn (array $x): array => array_filter($x, fn ($v) => !empty($v)))
            |> array_unique(...)
            |> array_values(...);

        $arrayChunk = array_chunk($values, 200);
        foreach ($arrayChunk as $chunk) {
            $qb = $this->attributeEntityRepository->createQueryBuilder('a')
                ->select('a.value')
                ->andWhere('a.list = :list')
                ->andWhere('a.value IN (:values)')
                ->setParameter('list', $entityList->getId())
                ->setParameter('values', $chunk);

            $existingRows = $qb->getQuery()->getScalarResult();

            $values = array_diff($values, array_column($existingRows, 'value'));
        }

        if (empty($values)) {
            return;
        }

        foreach ($values as $value) {
            $entity = new AttributeEntity();
            $entity->setList($entityList);
            $entity->setValue($value);
            $this->em->persist($entity);
        }

        $this->em->flush();
    }
}
