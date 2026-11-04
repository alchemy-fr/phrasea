<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Entity\Core\RenditionDefinition;
use App\Entity\Core\RenditionPolicy;
use App\Model\AssetTypeEnum;
use App\Tests\Functional\AbstractDataboxTestCase;
use App\Tests\Functional\Api\Attribute\AttributeApiTestTrait;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The ORM collections share one pagination convention: "limit" is the page size, "page"
 * the page number, and "view.next" links the next page as long as there is one.
 *
 * Their order is stable (it always ends with the ID), so that following "next" walks
 * the whole list with no item twice and no item skipped.
 */
final class PaginationTest extends AbstractDataboxTestCase
{
    use AttributeApiTestTrait;

    public static function collections(): array
    {
        return [
            'attribute definitions' => ['/attribute-definitions'],
            'rendition definitions' => ['/rendition-definitions'],
            'workspaces' => ['/workspaces'],
            'entity lists' => ['/entity-lists'],
            'tags' => ['/tags'],
            'attribute entities' => ['/attribute-entities'],
            'attribute filter rules' => ['/attribute-filter-rules'],
        ];
    }

    #[DataProvider('collections')]
    public function testFollowingNextWalksTheWholeCollection(string $uri): void
    {
        $query = $this->createThreeItems($uri);

        $all = $this->api('GET', $uri, self::USER, options: ['query' => [...$query, 'limit' => 100]]);
        $this->assertResponseStatusCodeSame(200);
        $allIds = array_column($all->toArray()['member'], '@id');
        $this->assertGreaterThanOrEqual(3, count($allIds));
        $this->assertSame(array_values(array_unique($allIds)), $allIds);

        $response = $this->api('GET', $uri, self::USER, options: ['query' => [...$query, 'limit' => 2]]);
        $this->assertResponseStatusCodeSame(200);
        $data = $response->toArray();
        $this->assertCount(2, $data['member']);
        $this->assertSame(count($allIds), $data['totalItems']);

        $walkedIds = array_column($data['member'], '@id');
        $pages = 1;
        while (null !== $next = $data['view']['next'] ?? null) {
            parse_str((string) parse_url($next, PHP_URL_QUERY), $nextQuery);
            $this->assertSame((string) ($pages + 1), $nextQuery['page'] ?? null);
            $this->assertSame('2', $nextQuery['limit'] ?? null);

            $data = $this->api('GET', $next, self::USER)->toArray();
            $this->assertResponseStatusCodeSame(200);
            $this->assertNotEmpty($data['member']);
            $this->assertLessThanOrEqual(2, count($data['member']));
            $walkedIds = [...$walkedIds, ...array_column($data['member'], '@id')];
            ++$pages;
        }

        $this->assertSame((int) ceil(count($allIds) / 2), $pages);
        $this->assertSame($allIds, $walkedIds);
    }

    public function testLastPageOfThreeItemsHasOneItemAndNoNext(): void
    {
        $workspace = $this->getOrCreateDefaultWorkspace(['ownerId' => self::USER]);
        foreach (['Alpha', 'Bravo', 'Charlie'] as $name) {
            $this->findOrCreateTagByName($name, $workspace);
        }

        $first = $this->api('GET', '/tags', self::USER, options: ['query' => ['workspace' => $workspace->getId(), 'limit' => 2]])->toArray();
        $this->assertSame(['Alpha', 'Bravo'], array_column($first['member'], 'name'));
        $this->assertSame(3, $first['totalItems']);
        $this->assertStringContainsString('page=2', $first['view']['next']);
        $this->assertStringContainsString('limit=2', $first['view']['next']);

        $last = $this->api('GET', $first['view']['next'], self::USER)->toArray();
        $this->assertSame(['Charlie'], array_column($last['member'], 'name'));
        $this->assertArrayNotHasKey('next', $last['view']);
    }

    /**
     * @return array<string, string> the query restricting the collection to the created items' scope
     */
    private function createThreeItems(string $uri): array
    {
        $workspace = $this->getOrCreateDefaultWorkspace(['ownerId' => self::USER]);
        $em = self::getEntityManager();

        switch ($uri) {
            case '/attribute-definitions':
                foreach (['Alpha', 'Bravo', 'Charlie'] as $name) {
                    $this->createAttributeDefinition(['name' => $name]);
                }

                return ['workspaceId' => $workspace->getId()];
            case '/rendition-definitions':
                $policy = new RenditionPolicy();
                $policy->setWorkspace($workspace);
                $policy->setName('Pagination');
                $policy->setPublic(true);
                $policy->setEditable(false);
                $em->persist($policy);
                foreach (['Alpha', 'Bravo', 'Charlie'] as $name) {
                    $definition = new RenditionDefinition();
                    $definition->setWorkspace($workspace);
                    $definition->setPolicy($policy);
                    $definition->setName($name);
                    $definition->setTarget(AssetTypeEnum::Both);
                    $definition->setBuildMode(RenditionDefinition::BUILD_MODE_PICK_SOURCE);
                    $em->persist($definition);
                }
                $em->flush();

                return ['workspaceId' => $workspace->getId()];
            case '/workspaces':
                foreach (['Alpha', 'Bravo', 'Charlie'] as $name) {
                    $this->createOtherWorkspace(['name' => $name, 'ownerId' => self::USER]);
                }

                return [];
            case '/entity-lists':
                foreach (['Alpha', 'Bravo', 'Charlie'] as $name) {
                    $this->createEntityList(['name' => $name]);
                }

                return ['workspace' => $workspace->getId()];
            case '/tags':
                foreach (['Alpha', 'Bravo', 'Charlie'] as $name) {
                    $this->findOrCreateTagByName($name, $workspace);
                }

                return ['workspace' => $workspace->getId()];
            case '/attribute-entities':
                $list = $this->createEntityList(['name' => 'Colors']);
                foreach (['Blue', 'Green', 'Red'] as $value) {
                    $this->createEntity($list, $value);
                }

                return ['list' => $list->getId()];
            case '/attribute-filter-rules':
                $tag = $this->findOrCreateTagByName('Secret', $workspace);
                foreach ([['a'], ['b'], ['c']] as $userIds) {
                    self::getAttributeFilterManager()->saveRule($workspace, $userIds, [], sprintf('@tag = "%s"', $tag->getId()));
                }

                return ['workspaceId' => $workspace->getId()];
        }

        throw new \InvalidArgumentException(sprintf('Unsupported collection "%s"', $uri));
    }
}
