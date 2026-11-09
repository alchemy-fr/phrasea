<?php

declare(strict_types=1);

namespace App\Service\Asset\Attribute\AttributeEntity\Importer;

use Alchemy\CoreBundle\Util\DoctrineUtil;
use App\Entity\Core\AttributeEntity;
use App\Entity\Core\EntityList;
use App\Repository\Core\AttributeEntityRepository;
use App\Service\Asset\Attribute\AttributeEntity\Thesaurus\TabulatedThesaurus;
use App\Service\Asset\Attribute\AttributeEntity\Thesaurus\TabulatedThesaurusLine;
use App\Service\Asset\Attribute\AttributeEntity\Thesaurus\TabulatedThesaurusSyntaxException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Imports a tabulated thesaurus (see TabulatedThesaurus) into the list hierarchy, all or nothing.
 *
 * A line updates the entity of the same value under the same parent, else the only entity of that value in the
 * list (moved there), else a new one. Its status follows the line; its color, translations and synonyms are
 * added to the existing ones. The siblings are ordered as in the file.
 */
#[AsTaggedItem(index: TabulatedThesaurus::FORMAT)]
final readonly class TabulatedThesaurusImporter implements AttributeEntityImporterInterface
{
    public function __construct(
        private EntityManagerInterface $em,
        private AttributeEntityRepository $repository,
        private ValidatorInterface $validator,
    ) {
    }

    public function validate(EntityList $entityList, string $data, ?string $defaultLocale = null): void
    {
        $this->parse($entityList, $data, $defaultLocale);
    }

    public function import(EntityList $entityList, string $data, ?string $defaultLocale = null): void
    {
        $lines = $this->parse($entityList, $data, $defaultLocale);
        $restoreLogger = DoctrineUtil::disableLogger($this->em);
        try {
            $this->importLines($entityList, $lines);
        } finally {
            $restoreLogger();
        }
    }

    /**
     * @return list<TabulatedThesaurusLine>
     */
    private function parse(EntityList $entityList, string $data, ?string $defaultLocale): array
    {
        try {
            $lines = TabulatedThesaurus::parse($data, $defaultLocale);
        } catch (TabulatedThesaurusSyntaxException $e) {
            throw new BadRequestHttpException($e->getMessage(), $e);
        }

        if (!$entityList->isHierarchy()) {
            foreach ($lines as $line) {
                if (null !== $line->parent) {
                    throw new BadRequestHttpException(sprintf('Line %d: the list is not a hierarchy: its entities cannot be indented', $line->line));
                }
            }
        }

        return $lines;
    }

    /**
     * @param list<TabulatedThesaurusLine> $lines
     */
    private function importLines(EntityList $entityList, array $lines): void
    {

        $all = $this->repository->findHierarchyEntities($entityList->getId());
        $byPlace = [];
        $byValue = [];
        foreach ($all as $entity) {
            $byPlace[self::placeKey($entity->getParentId(), (string) $entity->getValue())] ??= $entity;
            $byValue[(string) $entity->getValue()][] = $entity;
        }

        $taken = [];
        $imported = [];
        $nextPosition = [];
        $errors = [];
        foreach ($lines as $index => $line) {
            $parent = null !== $line->parent ? $imported[$line->parent] : null;
            $entity = $this->find($parent, $line->value, $byPlace, $byValue, $taken);
            if (null === $entity) {
                $entity = new AttributeEntity();
                $entity->setList($entityList);
                $entity->setValue($line->value);
                $all[] = $entity;
            }
            $taken[$entity->getId()] = true;
            $imported[$index] = $entity;

            if ($entity->getParent() !== $parent) {
                $entity->setParent($parent);
            }
            $parentKey = $parent?->getId() ?? '';
            $nextPosition[$parentKey] ??= 0;
            $entity->setPosition($nextPosition[$parentKey]++);
            $entity->setStatus($line->status);
            if (null !== $line->color) {
                $entity->setColor($line->color);
            }
            if (!empty($line->translations)) {
                $entity->setTranslations(array_merge($entity->getTranslations() ?? [], $line->translations));
            }
            if (!empty($line->synonyms)) {
                $synonyms = $entity->getSynonyms() ?? [];
                foreach ($line->synonyms as $locale => $terms) {
                    $synonyms[$locale] = array_values(array_unique(array_merge($synonyms[$locale] ?? [], $terms)));
                }
                $entity->setSynonyms($synonyms);
            }

            foreach ($this->validator->validate($entity) as $violation) {
                $errors[] = sprintf('Line %d: %s: %s', $line->line, $violation->getPropertyPath(), $violation->getMessage());
            }
        }

        if (!empty($errors)) {
            $this->em->clear();
            throw new BadRequestHttpException(sprintf("The thesaurus contains invalid entities:\n%s", implode("\n", $errors)));
        }

        // Moved entities carry their branch along: every depth of the list is computed again
        $children = [];
        foreach ($all as $entity) {
            $children[$entity->getParent()?->getId() ?? ''][] = $entity;
        }
        $queue = array_map(fn (AttributeEntity $e): array => [$e, 0], $children[''] ?? []);
        // Read by index: array_shift() would make it quadratic
        for ($i = 0; $i < \count($queue); ++$i) {
            [$entity, $depth] = $queue[$i];
            $entity->setHierarchyDepth($depth);
            foreach ($children[$entity->getId()] ?? [] as $child) {
                $queue[] = [$child, $depth + 1];
            }
        }

        $this->em->wrapInTransaction(function () use ($imported): void {
            foreach ($imported as $entity) {
                $this->em->persist($entity);
            }
            $this->em->flush();
        });
    }

    /**
     * @param array<string, AttributeEntity>       $byPlace
     * @param array<string, list<AttributeEntity>> $byValue
     * @param array<string, true>                  $taken
     */
    private function find(?AttributeEntity $parent, string $value, array $byPlace, array $byValue, array $taken): ?AttributeEntity
    {
        $inPlace = $byPlace[self::placeKey($parent?->getId(), $value)] ?? null;
        if (null !== $inPlace && !isset($taken[$inPlace->getId()])) {
            return $inPlace;
        }

        $free = array_values(array_filter($byValue[$value] ?? [], fn (AttributeEntity $e): bool => !isset($taken[$e->getId()])));

        return 1 === \count($free) ? $free[0] : null;
    }

    private static function placeKey(?string $parentId, string $value): string
    {
        return ($parentId ?? '')."\0".$value;
    }
}
