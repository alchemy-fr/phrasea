<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api\Attribute;

use App\Entity\Admin\OperationTask;
use App\Entity\Core\AttributeEntity;
use App\Entity\Core\EntityList;
use App\OperationTask\Task\ImportEntitiesTask;
use App\Tests\Functional\AbstractDataboxTestCase;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Import and export of an entity list as a tabulated thesaurus.
 */
final class EntityListThesaurusTest extends AbstractDataboxTestCase
{
    use AttributeApiTestTrait;

    private const string THESAURUS = <<<'TXT'
        animal
            mammifère [fr]; mammal [en]
                {#ff0000} chien [fr] ; dog [en]
                ! {#00F800} cat [en]; chat [fr]
            oiseau [fr]
                ? pigeon
        object [en] ; objet [fr]
            table
            chaise [fr]
        TXT;

    private EntityList $list;

    private function setUpScene(): void
    {
        $this->getOrCreateDefaultWorkspace(['ownerId' => self::USER]);
        $this->list = $this->createEntityList(['name' => 'Things', 'isHierarchy' => true]);
    }

    private function import(string $data, ?string $defaultLocale = null): ResponseInterface
    {
        return $this->api('POST', '/entity-lists/'.$this->list->getId().'/import', self::USER, array_filter([
            'format' => 'thesaurus',
            'data' => $data,
            'defaultLocale' => $defaultLocale,
        ]));
    }

    /**
     * The state of the import queued by the last call to import().
     *
     * @return array{status: int, output: string|null, progress: int|null, itemTotal: int|null}
     */
    private function importTask(ResponseInterface $response): array
    {
        $this->assertResponseStatusCodeSame(202);
        $taskId = $response->toArray()['taskId'];

        return $this->api('GET', '/entity-lists/'.$this->list->getId().'/import-tasks/'.$taskId, self::USER)->toArray();
    }

    private function export(): string
    {
        $this->api('POST', '/entity-lists/'.$this->list->getId().'/export', self::USER, ['format' => 'thesaurus']);
        $this->assertResponseIsSuccessful();

        // A StreamedResponse: its content is only captured by the kernel browser
        return static::getClient()->getInternalResponse()->getContent();
    }

    /**
     * @return array<string, AttributeEntity>
     */
    private function entitiesByValue(): array
    {
        $em = self::getEntityManager();
        $em->clear();

        $entities = [];
        foreach ($em->getRepository(AttributeEntity::class)->findBy(['list' => $this->list->getId()]) as $entity) {
            $entities[$entity->getValue()] = $entity;
        }

        return $entities;
    }

    public function testImportBuildsTheHierarchy(): void
    {
        $this->setUpScene();

        // The task transport is synchronous in test: the import is done once queued
        $task = $this->importTask($this->import(self::THESAURUS));
        $this->assertSame(OperationTask::STATUS_COMPLETED, $task['status']);
        // Checking, then writing: two steps per line
        $this->assertSame(18, $task['itemTotal']);
        $this->assertSame(18, $task['progress']);

        $e = $this->entitiesByValue();
        $this->assertCount(9, $e);
        $this->assertNull($e['animal']->getParent());
        $this->assertNull($e['object']->getParent());
        $this->assertSame($e['mammifère']->getId(), $e['chien']->getParentId());
        $this->assertSame(2, $e['cat']->getHierarchyDepth());
        $this->assertSame([0, 1], [$e['chien']->getPosition(), $e['cat']->getPosition()]);
        $this->assertSame('#ff0000', $e['chien']->getColor());
        $this->assertSame(['en' => 'cat', 'fr' => 'chat'], $e['cat']->getTranslations());
        $this->assertSame(AttributeEntity::STATUS_REJECTED, $e['cat']->getStatus());
        $this->assertSame(AttributeEntity::STATUS_PENDING, $e['pigeon']->getStatus());
        $this->assertSame(AttributeEntity::STATUS_APPROVED, $e['table']->getStatus());
    }

    public function testExportIsReadBackIdentically(): void
    {
        $this->setUpScene();
        $this->import(self::THESAURUS);

        $exported = $this->export();
        $this->assertSame(implode("\n", [
            'animal',
            "\tmammifère [fr]; mammal [en]",
            "\t\t{#ff0000} chien [fr]; dog [en]",
            "\t\t! {#00f800} cat [en]; chat [fr]",
            "\toiseau [fr]",
            "\t\t? pigeon",
            'object [en]; objet [fr]',
            "\ttable",
            "\tchaise [fr]",
        ])."\n", $exported);

        // Imported again: nothing changes
        $this->import($exported);
        $this->assertResponseIsSuccessful();
        $this->assertCount(9, $this->entitiesByValue());
        $this->assertSame($exported, $this->export());
    }

    public function testExistingEntitiesAreReusedAndMoved(): void
    {
        $this->setUpScene();
        $dog = $this->createEntity($this->list, 'dog', ['translations' => ['de' => 'Hund']]);

        $this->import("animal\n    dog [en]; chien [fr]\n        puppy");
        $this->assertResponseIsSuccessful();

        $e = $this->entitiesByValue();
        $this->assertCount(3, $e);
        $this->assertSame($dog->getId(), $e['dog']->getId());
        $this->assertSame($e['animal']->getId(), $e['dog']->getParentId());
        $this->assertSame(1, $e['dog']->getHierarchyDepth());
        $this->assertSame(2, $e['puppy']->getHierarchyDepth());
        $this->assertSame(['de' => 'Hund', 'en' => 'dog', 'fr' => 'chien'], $e['dog']->getTranslations());
    }

    public function testAnExistingEntityMovesUnderANewParentOfALaterBatch(): void
    {
        $this->setUpScene();
        $dog = $this->createEntity($this->list, 'dog');

        // The entities are written by batches of 500: "dog" is written with the first one, "animal" would be with the second
        $lines = array_map(fn (int $i): string => 'term '.$i, range(1, 600));
        $lines[] = 'animal';
        $lines[] = '    dog';
        $this->assertSame(OperationTask::STATUS_COMPLETED, $this->importTask($this->import(implode("\n", $lines)))['status']);

        $e = $this->entitiesByValue();
        $this->assertCount(602, $e);
        $this->assertSame($dog->getId(), $e['dog']->getId());
        $this->assertSame($e['animal']->getId(), $e['dog']->getParentId());
        $this->assertSame(1, $e['dog']->getHierarchyDepth());
    }

    public function testTermsWithoutLocaleTakeTheDefaultLocale(): void
    {
        $this->setUpScene();

        $this->import("animal\n    chien; toutou; dog [en]", 'fr');
        $this->assertResponseIsSuccessful();

        $e = $this->entitiesByValue();
        $this->assertSame(['fr' => 'animal'], $e['animal']->getTranslations());
        $this->assertSame(['fr' => 'chien', 'en' => 'dog'], $e['chien']->getTranslations());
        $this->assertSame(['fr' => ['toutou']], $e['chien']->getSynonyms());
    }

    public function testTermsInTheSameLocaleAreSynonyms(): void
    {
        $this->setUpScene();

        $this->import('chien [fr]; toutou [fr]; dog [en]; doggy [en]');
        $this->assertResponseIsSuccessful();

        $e = $this->entitiesByValue();
        $this->assertSame(['fr' => 'chien', 'en' => 'dog'], $e['chien']->getTranslations());
        $this->assertSame(['fr' => ['toutou'], 'en' => ['doggy']], $e['chien']->getSynonyms());
        $this->assertSame("chien [fr]; toutou [fr]; dog [en]; doggy [en]\n", $this->export());
    }

    public function testInvalidEntitiesFailTheImportTask(): void
    {
        $this->setUpScene();

        $task = $this->importTask($this->import("animal\n    chien; ".str_repeat('x', 101)));
        $this->assertSame(OperationTask::STATUS_FAILED, $task['status']);
        $this->assertStringContainsString('Line 2: synonyms', (string) $task['output']);
        $this->assertSame([], $this->entitiesByValue());

        // The imported data is not kept in the task
        $tasks = self::getEntityManager()->getRepository(OperationTask::class)->findBy(['task' => ImportEntitiesTask::NAME]);
        $this->assertCount(1, $tasks);
        $this->assertSame(['listId', 'format', 'defaultLocale'], array_keys($tasks[0]->getPayload()));
    }

    public function testTheImportTaskIsOnlyReadThroughItsList(): void
    {
        $this->setUpScene();
        $other = $this->createEntityList(['name' => 'Other']);
        $taskId = $this->import('animal')->toArray()['taskId'];

        $this->api('GET', '/entity-lists/'.$other->getId().'/import-tasks/'.$taskId, self::USER);
        $this->assertResponseStatusCodeSame(404);
        $this->api('GET', '/entity-lists/'.$this->list->getId().'/import-tasks/not-a-uuid', self::USER);
        $this->assertResponseStatusCodeSame(404);
        $this->api('GET', '/entity-lists/'.$this->list->getId().'/import-tasks/'.$taskId, self::OTHER);
        $this->assertResponseStatusCodeSame(403);
    }

    public function testAnUnknownDefaultLocaleIsRejected(): void
    {
        $this->setUpScene();

        $this->import('animal', 'not-a-locale');
        $this->assertResponseStatusCodeSame(422);
    }

    public function testSyntaxErrorsImportNothing(): void
    {
        $this->setUpScene();

        $response = $this->import("animal\n    {#ff0000} {#00ff00} chien\n  chat");
        $this->assertResponseStatusCodeSame(400);
        $detail = $response->toArray(false)['detail'] ?? '';
        $this->assertStringContainsString('Line 2: Several colors', $detail);
        $this->assertStringContainsString('Line 3: The indentation does not match any upper level', $detail);

        $this->assertSame([], $this->entitiesByValue());
    }
}
