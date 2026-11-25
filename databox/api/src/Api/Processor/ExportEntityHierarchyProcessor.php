<?php

declare(strict_types=1);

namespace App\Api\Processor;

use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use Alchemy\CoreBundle\Util\DoctrineUtil;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Attribute\Hierarchy\EntityHierarchyImporter;
use App\Entity\Core\AttributeEntity;
use App\Entity\Core\EntityList;
use App\Repository\Core\AttributeEntityRepository;
use App\Security\Voter\AbstractVoter;
use App\Service\Asset\Attribute\AttributeEntity\Importer\CsvAttributeEntityImporter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The hierarchy of the list as the CSV ImportEntityHierarchyProcessor reads, depth first: the siblings in their
 * order, which the import keeps. Each row also has the columns of the values CSV (CsvAttributeEntityExporter)
 * but id and external_id, which the entity_external_id column replaces, and whether the entity is a placeholder.
 */
final class ExportEntityHierarchyProcessor implements ProcessorInterface
{
    use SecurityAwareTrait;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AttributeEntityRepository $repository,
    ) {
    }

    public function process($data, Operation $operation, array $uriVariables = [], array $context = []): Response
    {
        $list = DoctrineUtil::findStrict($this->em, EntityList::class, (string) $uriVariables['id']);
        $this->denyAccessUnlessGranted(AbstractVoter::EDIT, $list);
        $entities = $this->repository->findHierarchyEntities($list->getId());

        $children = [];
        foreach ($entities as $entity) {
            $children[$entity->getParentId() ?? ''][] = $entity;
        }
        $locales = $list->getWorkspace()->getEnabledLocales();

        return new StreamedResponse(function () use ($children, $locales): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, [
                EntityHierarchyImporter::COL_ENTITY,
                EntityHierarchyImporter::COL_PARENT,
                'value',
                'emoji',
                'color',
                'status',
                ...array_map(fn (string $l): string => CsvAttributeEntityImporter::TRANSLATION_PREFIX.$l, $locales),
                'placeholder',
            ], escape: '');
            foreach ($children[''] ?? [] as $top) {
                $this->write($out, $top, null, $children, $locales);
            }
            fclose($out);
        }, Response::HTTP_OK, [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => sprintf('attachment; filename="%s-hierarchy.csv"', preg_replace('/[^\w.-]+/u', '_', (string) $list->getName())),
        ]);
    }

    /**
     * @param resource                         $out
     * @param array<string, AttributeEntity[]> $children
     * @param string[]                         $locales
     */
    private function write($out, AttributeEntity $entity, ?AttributeEntity $parent, array $children, array $locales): void
    {
        $translations = $entity->getTranslations() ?? [];
        fputcsv($out, [
            self::key($entity),
            null !== $parent ? self::key($parent) : '',
            $entity->getValue(),
            $entity->getEmoji(),
            $entity->getColor(),
            $entity->getStatus(),
            ...array_map(fn (string $l): string => $translations[$l] ?? '', $locales),
            $entity->isPlaceholder() ? '1' : '',
        ], escape: '');

        foreach ($children[$entity->getId()] ?? [] as $child) {
            $this->write($out, $child, $entity, $children, $locales);
        }
    }

    private static function key(AttributeEntity $entity): string
    {
        return $entity->getExternalId() ?? $entity->getId();
    }
}
