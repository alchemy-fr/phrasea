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
 * Imports a tabulated thesaurus (see TabulatedThesaurus) into the list hierarchy.
 *
 * Every line is checked before anything is written: an invalid entity rejects the whole import. The entities are then
 * written in batches of BATCH_SIZE, each one committed and reported as progress (checking counts for one half,
 * writing for the other); a database failure in the middle leaves a partial import, completed by importing the
 * file again.
 *
 * A line updates the entity of the same value under the same parent, else the only entity of that value in the
 * list (moved there), else a new one. Its status follows the line; its color, translations and synonyms are
 * added to the existing ones. The siblings are ordered as in the file.
 */
#[AsTaggedItem(index: TabulatedThesaurus::FORMAT)]
final readonly class TabulatedThesaurusImporter implements AttributeEntityImporterInterface
{
    private const int BATCH_SIZE = 500;

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

    public function import(EntityList $entityList, string $data, ?string $defaultLocale = null, ?\Closure $onProgress = null): void
    {
        $lines = $this->parse($entityList, $data, $defaultLocale);
        $restoreLogger = DoctrineUtil::disableLogger($this->em);
        try {
            $this->importLines($entityList, $lines, $onProgress ?? static function (): void {});
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
     * @param list<TabulatedThesaurusLine>          $lines
     * @param \Closure(int $done, int $total): void $onProgress
     */
    private function importLines(EntityList $entityList, array $lines, \Closure $onProgress): void
    {
        $count = \count($lines);
        $total = 2 * $count;
        $onProgress(0, $total);

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
            if (0 === $index % self::BATCH_SIZE && 0 !== $index) {
                $onProgress($index, $total);
            }
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

        // The existing entities are managed: every flush writes them, their new ancestors are persisted before the first one
        /** @var array<string, AttributeEntity> $early */
        $early = [];
        foreach ($imported as $entity) {
            if ($this->em->contains($entity)) {
                for ($p = $entity->getParent(); null !== $p && !$this->em->contains($p) && !isset($early[$p->getId()]); $p = $p->getParent()) {
                    $early[$p->getId()] = $p;
                }
            }
        }
        foreach ($early as $entity) {
            $this->em->persist($entity);
        }
        // Then in the order of the file, parents before their children. The entities stay managed, as parents
        $written = \count($early);
        foreach ($imported as $entity) {
            if (isset($early[$entity->getId()])) {
                continue;
            }
            $this->em->persist($entity);
            if (0 === ++$written % self::BATCH_SIZE) {
                $this->em->flush();
                $onProgress($count + $written, $total);
            }
        }
        $this->em->flush();
        $onProgress($total, $total);
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
