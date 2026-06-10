<?php

declare(strict_types=1);

namespace App\Service\Asset\Attribute\AttributeEntity\Exporter;

use App\Api\Model\Input\ExportEntitiesInput;
use App\Entity\Core\AttributeEntity;
use App\Entity\Core\EntityList;
use App\Repository\Core\AttributeEntityRepository;
use App\Service\Asset\Attribute\AttributeEntity\Importer\CsvAttributeEntityImporter;
use Doctrine\ORM\EntityManagerInterface;

final readonly class CsvAttributeEntityExporter implements AttributeEntityExporterInterface
{
    public function __construct(
        private EntityManagerInterface $em,
        private AttributeEntityRepository $attributeEntityRepository,
    ) {
    }

    public static function getName(): string
    {
        return 'csv';
    }

    public function export(EntityList $entityList, ExportEntitiesInput $options): callable
    {
        $headers = [
            'id',
            'value',
            'emoji',
            'color',
            'status',
        ];

        $allLocales = null;
        $locale = $options->locale;
        if (!$locale) {
            $allLocales = $entityList->getWorkspace()->getEnabledLocales();
            foreach ($allLocales as $l) {
                $headers[] = CsvAttributeEntityImporter::TRANSLATION_PREFIX.$l;
            }
        }

        return function () use ($entityList, $headers, $locale, $allLocales) {
            $stdout = fopen('php://output', 'w');
            $listId = $entityList->getId();

            fputcsv($stdout, $headers);

            $page = 0;
            while (true) {
                if (!$this->iterate($stdout, $listId, $page++, $allLocales, $locale)) {
                    break;
                }
            }
        };
    }

    private function iterate($stream, string $listId, int $page, ?array $locales, ?string $locale): bool
    {
        $limit = 200;
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
            fputcsv($stream, [
                $result->getId(),
                $locale ? $result->getTranslations()[$locale] ?? '' : $result->getValue(),
                $result->getEmoji(),
                $result->getColor(),
                $result->getStatus(),
                ...($locales ? array_map(fn (string $l): string => $result->getTranslations()[$l] ?? '', $locales) : []),
            ]);
            ++$i;
        }

        $this->em->clear();

        return $i === $limit;
    }

    public function getMimeType(ExportEntitiesInput $options): string
    {
        return 'text/csv';
    }

    public function getFilename(EntityList $entityList, ExportEntitiesInput $options): string
    {
        return $entityList->getName().($options->locale ? '-'.$options->locale : '').'.csv';
    }
}
