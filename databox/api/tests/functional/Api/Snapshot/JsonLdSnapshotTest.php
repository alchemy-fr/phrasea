<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api\Snapshot;

use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use App\Attribute\Type\TextAttributeType;
use App\Entity\Basket\Basket;
use App\Entity\Basket\BasketAsset;
use App\Entity\Core\Asset;
use App\Entity\Core\Attribute;
use App\Entity\Core\AttributeDefinition;
use App\Entity\Core\AttributePolicy;
use App\Entity\Core\Collection;
use App\Entity\Core\File;
use App\Entity\Core\Tag;
use App\Entity\Core\Workspace;
use App\Service\Workspace\WorkspaceCreator;
use App\Tests\Functional\AbstractSearchTestCase;

/**
 * Safety net of the JSON shape of the main read endpoints.
 *
 * The responses are normalized (fixture IDs replaced by their name, other UUIDs,
 * dates and signatures masked, keys sorted) and compared to the recorded
 * snapshots of __snapshots__/. Record them again with UPDATE_SNAPSHOTS=1 after
 * an intended change of the API output.
 */
final class JsonLdSnapshotTest extends AbstractSearchTestCase
{
    private const string USER = KeycloakClientTestMock::USER_UID;

    private const array IDS = [
        'workspace' => '5a000000-0000-4000-8000-000000000001',
        'root' => '5a000000-0000-4000-8000-000000000002',
        'child' => '5a000000-0000-4000-8000-000000000003',
        'asset' => '5a000000-0000-4000-8000-000000000004',
        'other' => '5a000000-0000-4000-8000-000000000005',
        'file' => '5a000000-0000-4000-8000-000000000006',
        'tag' => '5a000000-0000-4000-8000-000000000007',
        'basket' => '5a000000-0000-4000-8000-000000000008',
        'basketAsset' => '5a000000-0000-4000-8000-000000000009',
        'titleDef' => '5a000000-0000-4000-8000-00000000000a',
        'keywordsDef' => '5a000000-0000-4000-8000-00000000000b',
        'policy' => '5a000000-0000-4000-8000-00000000000c',
    ];

    public static function getEndpoints(): iterable
    {
        yield 'asset item' => ['/assets/{asset}'];
        yield 'asset list' => ['/assets?workspaces[]={workspace}&order[@createdAt]=asc'];
        yield 'collection item' => ['/collections/{child}'];
        yield 'collection children' => ['/collections?parent={root}'];
        yield 'basket item' => ['/baskets/{basket}'];
        yield 'basket assets' => ['/baskets/{basket}/assets'];
        yield 'workspace item' => ['/workspaces/{workspace}'];
        yield 'attribute definition list' => ['/attribute-definitions?workspaceId={workspace}'];
        yield 'tag list' => ['/tags?workspace={workspace}'];
        yield 'file item' => ['/files/{file}'];
        yield 'asset item as json' => ['/assets/{asset}', 'application/json'];
    }

    /**
     * @dataProvider getEndpoints
     */
    public function testResponseShape(string $uri, string $accept = 'application/ld+json'): void
    {
        $this->createScene();
        static::populateSearchIndices();

        $uri = preg_replace_callback('/\{(\w+)\}/', static fn (array $m): string => self::IDS[$m[1]], $uri);
        $response = static::createClient()->request('GET', $uri, [
            'headers' => [
                'Accept' => $accept,
                'Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor(self::USER),
            ],
        ]);
        $this->assertResponseIsSuccessful();

        $actual = json_encode(self::normalize($response->toArray()), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n";

        $file = __DIR__.'/__snapshots__/'.preg_replace('/\W+/', '_', $this->dataName()).'.json';
        if (getenv('UPDATE_SNAPSHOTS') || !file_exists($file)) {
            @mkdir(\dirname($file), 0777, true);
            file_put_contents($file, $actual);
            $this->markTestIncomplete(sprintf('Snapshot recorded: %s', basename($file)));
        }

        $this->assertSame(file_get_contents($file), $actual, sprintf('JSON shape of %s changed (UPDATE_SNAPSHOTS=1 to record it)', $uri));
    }

    private function createScene(): void
    {
        $em = self::getEntityManager();

        $workspace = new Workspace();
        self::forceEntityId($workspace, self::IDS['workspace']);
        $workspace->setName('Snapshot workspace');
        $workspace->setSlug('snapshot-workspace');
        $workspace->setOwnerId(self::USER);
        $workspace->setEnabledLocales(['en', 'fr']);
        self::getService(WorkspaceCreator::class)->createWorkspace($workspace);
        $em->flush();
        $this->addUserOnWorkspace(self::USER, $workspace->getId());
        $this->defaultWorkspace = $workspace;

        $policy = new AttributePolicy();
        self::forceEntityId($policy, self::IDS['policy']);
        $policy->setWorkspace($workspace);
        $policy->setName('Snapshot policy');
        $policy->setEditable(true);
        $policy->setPublic(true);
        $em->persist($policy);
        $title = $this->createDefinition('titleDef', 'Title', $policy, false);
        $keywords = $this->createDefinition('keywordsDef', 'Keywords', $policy, true);

        $tag = new Tag();
        self::forceEntityId($tag, self::IDS['tag']);
        $tag->setName('Nature');
        $tag->setWorkspace($workspace);
        $em->persist($tag);

        $root = $this->createCollection(['id' => self::IDS['root'], 'name' => 'Root', 'workspace' => $workspace, 'ownerId' => self::USER, 'no_flush' => true]);
        $this->createCollection(['id' => self::IDS['child'], 'name' => 'Child', 'workspace' => $workspace, 'ownerId' => self::USER, 'parent' => $root, 'no_flush' => true]);
        $em->flush();

        $file = new File();
        self::forceEntityId($file, self::IDS['file']);
        $file->setWorkspace($workspace);
        $file->setStorage(File::STORAGE_URL);
        $file->setPath('https://example.com/photo.jpg');
        $file->setPathPublic(true);
        $file->setType('image/jpeg');
        $file->setExtension('jpg');
        $file->setOriginalName('photo.jpg');
        $file->setSize(1234);
        $em->persist($file);
        $em->flush();

        $asset = $this->createPinnedAsset('asset', 'Photo', $workspace, $em->find(Collection::class, self::IDS['child']));
        $asset->setSource($file);
        $asset->addTag($tag);
        $this->addAttribute($asset, $title, 'Hello');
        $this->addAttribute($asset, $keywords, 'first', 0);
        $this->addAttribute($asset, $keywords, 'second', 1);
        $other = $this->createPinnedAsset('other', 'Other', $workspace, $root);
        $em->flush();

        $basket = new Basket(self::IDS['basket']);
        $basket->setName('My basket');
        $basket->setOwnerId(self::USER);
        $em->persist($basket);
        $basketAsset = new BasketAsset();
        self::forceEntityId($basketAsset, self::IDS['basketAsset']);
        $basketAsset->setBasket($basket);
        $basketAsset->setAsset($other);
        $basketAsset->setOwnerId(self::USER);
        $basketAsset->setPosition(0);
        $em->persist($basketAsset);
        $em->flush();
    }

    private function createDefinition(string $key, string $name, AttributePolicy $policy, bool $multiple): AttributeDefinition
    {
        $definition = new AttributeDefinition();
        self::forceEntityId($definition, self::IDS[$key]);
        $definition->setWorkspace($policy->getWorkspace());
        $definition->setPolicy($policy);
        $definition->setName($name);
        $definition->setType(TextAttributeType::NAME);
        $definition->setMultiple($multiple);
        self::getEntityManager()->persist($definition);

        return $definition;
    }

    private function createPinnedAsset(string $key, string $name, Workspace $workspace, Collection $collection): Asset
    {
        $em = self::getEntityManager();

        $asset = new Asset();
        self::forceEntityId($asset, self::IDS[$key]);
        $asset->name = $name;
        $asset->setWorkspace($workspace);
        $asset->setOwnerId(self::USER);
        $asset->setReferenceCollection($collection);
        $em->persist($asset->addToCollection($collection));
        $em->persist($asset);

        return $asset;
    }

    private function addAttribute(Asset $asset, AttributeDefinition $definition, string $value, int $position = 0): void
    {
        $attribute = new Attribute();
        $attribute->setAsset($asset);
        $attribute->setDefinition($definition);
        $attribute->setPosition($position);
        $attribute->setOrigin(Attribute::ORIGIN_MACHINE);
        $attribute->setValue($value);
        self::getEntityManager()->persist($attribute);
    }

    /**
     * Fixture IDs become "<name>", other UUIDs "<uuid>"; dates, signatures and
     * generated IDs are masked and keys are sorted.
     */
    private static function normalize(mixed $data): mixed
    {
        if (\is_array($data)) {
            unset($data['debug:es']);
            // Epoch milliseconds of the date histogram facets
            if (\is_int($data['key'] ?? null) && $data['key'] > 1_000_000_000_000) {
                $data['key'] = '<timestamp>';
            }
            $data = array_map(self::normalize(...), $data);
            if (!array_is_list($data)) {
                ksort($data);
            }

            return $data;
        }

        if (!\is_string($data)) {
            return $data;
        }

        $data = str_replace(array_values(self::IDS), array_map(static fn (string $name): string => '<'.$name.'>', array_keys(self::IDS)), $data);
        $data = preg_replace('/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/', '<uuid>', $data);
        $data = preg_replace('#/\.well-known/genid/[0-9a-f]+#', '<genid>', $data);
        $data = preg_replace('/\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(\.\d+)?([+-]\d{2}:\d{2}|Z)/', '<date>', $data);

        return preg_replace('/(X-Amz-[A-Za-z]+|Expires|Signature)=[^&"]+/', '$1=<masked>', $data);
    }
}
