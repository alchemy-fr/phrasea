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
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The hierarchy of the list as the CSV ImportEntityHierarchyProcessor reads, depth first.
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

        return new StreamedResponse(function () use ($list, $children): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, [EntityHierarchyImporter::COL_ENTITY, EntityHierarchyImporter::COL_PARENT, EntityHierarchyImporter::COL_POSITION, 'value', 'placeholder'], escape: '');
            $root = $list->getRootEntity();
            if (null !== $root) {
                $this->write($out, $root, null, $children);
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
     */
    private function write($out, AttributeEntity $entity, ?AttributeEntity $parent, array $children): void
    {
        fputcsv($out, [
            self::key($entity),
            null !== $parent ? self::key($parent) : '',
            $entity->getPosition(),
            $entity->getValue(),
            $entity->isPlaceholder() ? '1' : '',
        ], escape: '');

        foreach ($children[$entity->getId()] ?? [] as $child) {
            $this->write($out, $child, $entity, $children);
        }
    }

    private static function key(AttributeEntity $entity): string
    {
        return $entity->getExternalId() ?? $entity->getId();
    }
}
