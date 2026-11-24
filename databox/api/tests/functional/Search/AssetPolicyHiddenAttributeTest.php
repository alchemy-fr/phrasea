<?php

declare(strict_types=1);

namespace App\Tests\Functional\Search;

use App\Attribute\Type\EntityAttributeType;
use App\Entity\Core\Asset;
use App\Entity\Core\AssetPolicy\AssetPolicy;
use App\Entity\Core\AssetPolicy\AssetPolicyUser;
use App\Entity\Core\Attribute;
use App\Entity\Core\AttributeDefinition;
use App\Entity\Core\Collection;
use App\Entity\Core\Workspace;
use App\Service\Asset\AssetPolicy\AssetPolicyManager;
use App\Tests\Functional\AbstractSearchTestCase;
use App\Tests\Functional\Api\Attribute\AttributeApiTestTrait;

/**
 * A definition that an asset policy hides from a user on an asset is, for this user and this
 * asset, as unreadable as a definition they may not read: neither the output (attributes, name,
 * group value) nor any search answer (facets, conditions, full text, sort, suggestions, totals,
 * usage counts) may reveal its value.
 */
class AssetPolicyHiddenAttributeTest extends AbstractSearchTestCase
{
    use AttributeApiTestTrait;

    private const string TARGET = self::USER;

    private Workspace $workspace;
    private AttributeDefinition $title;
    private Collection $secretCollection;

    private function createWorkspaceWithTitle(): void
    {
        $this->workspace = $this->createWorkspace(['public' => true, 'ownerId' => self::ADMIN]);
        $this->addUserOnWorkspace(self::TARGET, $this->workspace->getId());
        $this->addUserOnWorkspace(self::OTHER, $this->workspace->getId());
        $this->title = $this->createAttributeDefinition([
            'workspace' => $this->workspace,
            'name' => 'Ptitle',
            'slug' => 'ptitle',
            'sortable' => true,
            'facet_enabled' => true,
            'no_flush' => true,
        ]);
        $this->title->setSuggest(true);
        $this->secretCollection = $this->createCollection(['workspace' => $this->workspace, 'public' => true, 'name' => 'Secret']);
    }

    private function createPublicAsset(string $name, ?Collection $referenceCollection, array $attributes): Asset
    {
        return $this->createAsset([
            'workspace' => $this->workspace,
            'name' => $name,
            'public' => true,
            'collectionId' => $referenceCollection?->getId(),
            'attributes' => array_map(static fn (array $a): array => ['definition' => $a[0], 'value' => $a[1]], $attributes),
        ]);
    }

    /**
     * @param AttributeDefinition[] $definitions
     */
    private function hideFromTarget(array $definitions, array $conditions): void
    {
        $em = self::getEntityManager();

        $policy = new AssetPolicy();
        $policy->setWorkspace($this->workspace);
        $policy->setOwnerId(self::ADMIN);
        $policy->setName('Hide');
        $policy->setConditions($conditions);
        $policy->setActions(array_map(static fn (AttributeDefinition $d): array => [
            'action' => AssetPolicyManager::ACTION_HIDE_ATTRIBUTE,
            'definitionId' => $d->getId(),
        ], $definitions));
        $user = new AssetPolicyUser();
        $user->setPolicy($policy);
        $user->setUserType(AssetPolicyUser::TYPE_USER);
        $user->setUserId(self::TARGET);
        $policy->getUsers()->add($user);
        $em->persist($policy);
        $em->flush();
    }

    private function inCollection(Collection $collection): array
    {
        return [['field' => 'collection', 'operator' => '=', 'value' => $collection->getId()]];
    }

    private function search(string $userId, array $query, int $expectedCode = 200): array
    {
        $response = $this->api('GET', '/assets', $userId, options: ['query' => $query]);
        $this->assertSame($expectedCode, $response->getStatusCode(), $response->getContent(false));

        return $response->toArray(false);
    }

    private function searchIds(string $userId, array $query): array
    {
        return array_column($this->search($userId, $query)['member'], 'id');
    }

    public function testHiddenValueIsLeftOutOfTheOutputAndOfEverySearchAnswer(): void
    {
        $this->createWorkspaceWithTitle();
        $subCollection = $this->createCollection(['workspace' => $this->workspace, 'public' => true, 'parent' => $this->secretCollection]);
        $otherCollection = $this->createCollection(['workspace' => $this->workspace, 'public' => true]);
        $nameDefinition = $this->findOneBy(AttributeDefinition::class, ['workspace' => $this->workspace->getId(), 'slug' => 'name']);

        // Below the collection of the policy condition
        $hidden = $this->createPublicAsset('Hidden name', $subCollection, [[$this->title, 'Aaa secret']]);
        $b = $this->createPublicAsset('B', null, [[$this->title, 'Bbb']]);
        $c = $this->createPublicAsset('C', $otherCollection, [[$this->title, 'Ccc']]);
        $this->hideFromTarget([$this->title, $nameDefinition], $this->inCollection($this->secretCollection));
        self::populateSearchIndices();
        $all = [$hidden->getId(), $b->getId(), $c->getId()];

        // Output (attributes, name falling back to the file name, group value) and facets
        $query = ['ids' => [$hidden->getId()], 'group' => ['ptitle_text_s']];
        $content = $this->api('GET', '/assets', self::TARGET, options: ['query' => $query])->getContent();
        $this->assertResponseIsSuccessful();
        $this->assertStringNotContainsString('Aaa secret', $content);
        $this->assertStringNotContainsString('Hidden name', $content);
        $content = $this->api('GET', '/assets/'.$hidden->getId(), self::TARGET)->getContent();
        $this->assertStringNotContainsString('Aaa secret', $content);
        $this->assertStringNotContainsString('Hidden name', $content);
        $content = $this->api('GET', '/assets', self::OTHER, options: ['query' => $query])->getContent();
        $this->assertStringContainsString('Aaa secret', $content);
        $this->assertStringContainsString('Hidden name', $content);

        $facets = $this->search(self::TARGET, ['ids' => $all])['facets'];
        $this->assertEqualsCanonicalizing(['Bbb', 'Ccc'], array_column($facets['ptitle_text_s']['buckets'], 'key'));
        $facets = $this->search(self::OTHER, ['ids' => $all])['facets'];
        $this->assertEqualsCanonicalizing(['Aaa secret', 'Bbb', 'Ccc'], array_column($facets['ptitle_text_s']['buckets'], 'key'));

        // Conditions and full text
        $this->assertSame([], $this->searchIds(self::TARGET, ['conditions' => ['ptitle = "Aaa secret"']]));
        $this->assertSame([$hidden->getId()], $this->searchIds(self::OTHER, ['conditions' => ['ptitle = "Aaa secret"']]));
        $this->assertSame([], $this->searchIds(self::TARGET, ['ids' => $all, 'query' => 'secret']));
        $this->assertSame([$hidden->getId()], $this->searchIds(self::OTHER, ['ids' => $all, 'query' => 'secret']));

        // Sort: the hidden value counts as missing
        $this->assertSame([$b->getId(), $c->getId(), $hidden->getId()], $this->searchIds(self::TARGET, ['ids' => $all, 'order' => ['ptitle_text_s' => 'asc']]));
        $this->assertSame([$c->getId(), $b->getId(), $hidden->getId()], $this->searchIds(self::TARGET, ['ids' => $all, 'order' => ['ptitle_text_s' => 'desc']]));
        $this->assertSame([$hidden->getId(), $b->getId(), $c->getId()], $this->searchIds(self::OTHER, ['ids' => $all, 'order' => ['ptitle_text_s' => 'asc']]));

        // Suggestions
        $suggest = fn (string $userId, array $query): array => array_column($this->api('GET', '/assets/suggest', $userId, options: ['query' => $query])->toArray()['member'], 'name');
        $this->assertNotContains('Aaa secret', $suggest(self::TARGET, ['query' => 'Aaa']));
        $this->assertContains('Aaa secret', $suggest(self::OTHER, ['query' => 'Aaa']));
        $this->assertEqualsCanonicalizing(['Bbb', 'Ccc'], $suggest(self::TARGET, ['query' => '', 'definition' => $this->title->getId()]));
        $this->assertEqualsCanonicalizing(['Aaa secret', 'Bbb', 'Ccc'], $suggest(self::OTHER, ['query' => '', 'definition' => $this->title->getId()]));

        // Values of the definition across the assets, and their total
        $values = fn (string $userId, array $query): array => $this->api('GET', '/attributes', $userId, options: ['query' => ['definition' => $this->title->getId()] + $query])->toArray();
        $data = $values(self::TARGET, ['value' => 'Aaa secret']);
        $this->assertSame([], $data['member']);
        $this->assertSame(0, $data['totalItems']);
        $this->assertSame(0, $values(self::TARGET, ['asset' => $hidden->getId()])['totalItems']);
        $this->assertSame(1, $values(self::OTHER, ['value' => 'Aaa secret'])['totalItems']);
        $data = $values(self::TARGET, []);
        $this->assertEqualsCanonicalizing(['Bbb', 'Ccc'], array_column($data['member'], 'value'));
        $this->assertSame(2, $data['totalItems']);
        $this->assertSame(3, $values(self::OTHER, [])['totalItems']);
    }

    /**
     * Without condition, a policy hides the definition from every asset of its workspace: the
     * attribute key is then unreadable, it can neither sort nor group the results.
     */
    public function testUnconditionalPolicyMakesTheKeyUnreadable(): void
    {
        $this->createWorkspaceWithTitle();
        $asset = $this->createPublicAsset('A', null, [[$this->title, 'Aaa secret']]);
        $this->hideFromTarget([$this->title], []);
        self::populateSearchIndices();

        $this->assertSame([], $this->search(self::TARGET, ['ids' => [$asset->getId()]])['facets']['ptitle_text_s']['buckets'] ?? []);
        // Like any field the user may read nowhere
        $this->search(self::TARGET, ['conditions' => ['ptitle = "Aaa secret"']], 400);
        $this->search(self::TARGET, ['order' => ['ptitle_text_s' => 'asc']], 400);
        $this->search(self::TARGET, ['group' => ['ptitle_text_s']], 400);
        $this->search(self::TARGET, ['group' => ['unknown_text_s']], 400);

        $this->assertSame([$asset->getId()], $this->searchIds(self::OTHER, ['conditions' => ['ptitle = "Aaa secret"']]));
        $this->search(self::OTHER, ['order' => ['ptitle_text_s' => 'asc'], 'group' => ['ptitle_text_s']]);
    }

    /**
     * The usage of an entity only counts the values the user may read: neither those of a
     * definition they may not read nor those an asset policy hides from them.
     */
    public function testEntityUsageCountsReadableValuesOnly(): void
    {
        $em = self::getEntityManager();
        $this->createWorkspaceWithTitle();
        $list = $this->createEntityList(['name' => 'Persons', 'workspace' => $this->workspace]);
        $persons = $this->createAttributeDefinition(['workspace' => $this->workspace, 'name' => 'Persons', 'slug' => 'persons', 'multiple' => true, 'type' => EntityAttributeType::NAME, 'list' => $list]);
        $privatePolicy = $this->createAttributePolicy(['workspace' => $this->workspace, 'name' => 'Private', 'public' => false]);
        $privatePersons = $this->createAttributeDefinition(['workspace' => $this->workspace, 'name' => 'Private persons', 'slug' => 'privatepersons', 'multiple' => true, 'type' => EntityAttributeType::NAME, 'list' => $list, 'policy' => $privatePolicy]);
        $alice = $this->createEntity($list, 'Alice');

        $hiddenAsset = $this->createPublicAsset('Hidden', $this->secretCollection, []);
        $visibleAsset = $this->createPublicAsset('Visible', null, []);
        foreach ([[$hiddenAsset, $persons], [$visibleAsset, $persons], [$visibleAsset, $privatePersons]] as [$asset, $definition]) {
            $attribute = new Attribute();
            $attribute->setAsset($asset);
            $attribute->setDefinition($definition);
            $attribute->setValue($alice->getId());
            $attribute->setOrigin(Attribute::ORIGIN_HUMAN);
            $em->persist($attribute);
        }
        $em->flush();
        $this->hideFromTarget([$persons], $this->inCollection($this->secretCollection));
        self::populateSearchIndices();

        $usage = fn (string $userId): array => array_column($this->api('GET', '/attribute-entities', $userId, options: ['query' => [
            'list' => $list->getId(),
            'usage' => 1,
        ]])->toArray()['member'], 'usage', 'id')[$alice->getId()];

        $this->assertSame(['attributes' => 3, 'humanAttributes' => 3], $usage(self::ADMIN));
        $this->assertSame(['attributes' => 2, 'humanAttributes' => 2], $usage(self::OTHER));
        $this->assertSame(['attributes' => 1, 'humanAttributes' => 1], $usage(self::TARGET));
    }
}
