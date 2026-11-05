<?php

declare(strict_types=1);

namespace App\Service\Asset\Attribute\AttributeEntity\Exporter;

use App\Api\Model\Input\ExportEntitiesInput;
use App\Entity\Core\AttributeEntity;
use App\Entity\Core\EntityList;
use App\Repository\Core\AttributeEntityRepository;
use App\Service\Asset\Attribute\AttributeEntity\Thesaurus\TabulatedThesaurus;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

/**
 * The list hierarchy as a tabulated thesaurus (see TabulatedThesaurus), indented with tabs, the siblings in their
 * order.
 */
#[AsTaggedItem(index: TabulatedThesaurus::FORMAT)]
final readonly class TabulatedThesaurusExporter implements AttributeEntityExporterInterface
{
    public function __construct(
        private AttributeEntityRepository $repository,
    ) {
    }

    public function export(EntityList $entityList, ExportEntitiesInput $options): callable
    {
        return function () use ($entityList): void {
            $children = [];
            foreach ($this->repository->findHierarchyEntities($entityList->getId()) as $entity) {
                $children[$entity->getParentId() ?? ''][] = $entity;
            }
            foreach ($children as &$siblings) {
                usort($siblings, fn (AttributeEntity $a, AttributeEntity $b): int => [$a->getPosition(), $a->getValue()] <=> [$b->getPosition(), $b->getValue()]);
            }
            unset($siblings);

            $out = fopen('php://output', 'w');
            foreach ($children[''] ?? [] as $top) {
                $this->write($out, $top, 0, $children);
            }
            fclose($out);
        };
    }

    public function getMimeType(ExportEntitiesInput $options): string
    {
        return 'text/plain; charset=utf-8';
    }

    public function getFilename(EntityList $entityList, ExportEntitiesInput $options): string
    {
        return $entityList->getName().'-thesaurus.txt';
    }

    /**
     * @param resource                         $out
     * @param array<string, AttributeEntity[]> $children
     */
    private function write($out, AttributeEntity $entity, int $depth, array $children): void
    {
        fwrite($out, TabulatedThesaurus::formatLine(
            $depth,
            $entity->getStatus(),
            $entity->getColor(),
            (string) $entity->getValue(),
            $entity->getTranslations() ?? [],
            $entity->getSynonyms() ?? [],
        )."\n");

        foreach ($children[$entity->getId()] ?? [] as $child) {
            $this->write($out, $child, $depth + 1, $children);
        }
    }
}
