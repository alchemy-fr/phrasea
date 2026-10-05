<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api\Workspace;

use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use App\Tests\Functional\AbstractDataboxTestCase;

/**
 * Read-only reference resources computed in memory (no database row):
 * locales, field types, built-in attributes, integration types and the
 * rendition build reference, plus the API documentation itself.
 */
final class ReferenceDataTest extends AbstractDataboxTestCase
{
    use WorkspaceTestHelperTrait;

    private const string USER = KeycloakClientTestMock::USER_UID;

    public static function referenceCollectionsProvider(): array
    {
        return [
            ['/locales', 'locale'],
            ['/field-types', 'field-type'],
            ['/built-in-attributes', 'built-in-attribute'],
            ['/integration-types', 'integration-type'],
        ];
    }

    /**
     * @dataProvider referenceCollectionsProvider
     */
    public function testReferenceCollectionsArePublic(string $uri, string $type): void
    {
        $client = static::createClient();
        foreach ([null, self::USER] as $userId) {
            $response = $client->request('GET', $uri, [
                'headers' => self::authHeaders($userId),
            ]);
            $this->assertResponseIsSuccessful();
            $data = $response->toArray();
            $this->assertSame('/contexts/'.$type, $data['@context']);
            $this->assertSame('hydra:Collection', $data['@type']);
            $this->assertNotEmpty($data['hydra:member']);
            $this->assertSame($type, $data['hydra:member'][0]['@type']);
        }
    }

    public function testLocales(): void
    {
        $client = static::createClient();
        $response = $client->request('GET', '/locales');
        $members = $response->toArray()['hydra:member'];
        $byId = array_column($members, null, 'id');
        $this->assertArrayHasKey('fr', $byId);
        $this->assertArrayHasKey('fr_CA', $byId);
        $this->assertGreaterThan(100, count($members));

        $this->assertSame('/locales/fr_CA', $byId['fr_CA']['@id']);
        $this->assertSame('fr', $byId['fr_CA']['language']);
        $this->assertSame('CA', $byId['fr_CA']['region']);

        $response = $client->request('GET', '/locales/fr_CA');
        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertSame('fr_CA', $data['id']);
        $this->assertSame('fr', $data['language']);
        $this->assertSame('CA', $data['region']);
        $this->assertSame('French (Canada)', $data['name']);
        $this->assertSame('Français (Canada)', $data['nativeName']);

        // Script subtag
        $response = $client->request('GET', '/locales/zh_Hant_TW');
        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertSame('zh', $data['language']);
        $this->assertSame('Hant', $data['script']);
        $this->assertSame('TW', $data['region']);

        $client->request('GET', '/locales/xx_YY');
        $this->assertResponseStatusCodeSame(404);
    }

    public function testFieldTypes(): void
    {
        $client = static::createClient();
        $response = $client->request('GET', '/field-types');
        $members = $response->toArray()['hydra:member'];
        $names = array_column($members, 'name');
        $this->assertContains('text', $names);
        $this->assertContains('date', $names);
        // Internal types are not listed
        $this->assertNotContains('collection_path', $names);
        $this->assertNotContains('asset_status', $names);

        // Translated display name, sorted on it
        $byName = array_column($members, null, 'name');
        $this->assertSame('Text', $byName['text']['displayName']);
        $displayNames = array_column($members, 'displayName');
        $sorted = $displayNames;
        sort($sorted);
        $this->assertSame($sorted, $displayNames);
    }

    public function testGetFieldTypeItem(): void
    {
        $this->markTestIncomplete('BUG: GET /field-types/{name} answers 500 "Call to a member function getRepository() on null": FieldTypeProvider inherits AbstractCollectionProvider::provide() which delegates item operations to the Doctrine item provider, whereas FieldType is an in-memory model (BuiltInAttributeProvider overrides provide() for the same reason)');

        $client = static::createClient();
        $response = $client->request('GET', '/field-types/text');
        $this->assertResponseIsSuccessful();
        $this->assertSame('text', $response->toArray()['name']);

        $client->request('GET', '/field-types/unknown');
        $this->assertResponseStatusCodeSame(404);
    }

    public function testBuiltInAttributes(): void
    {
        $client = static::createClient();
        $response = $client->request('GET', '/built-in-attributes');
        $members = $response->toArray()['hydra:member'];
        $byId = array_column($members, null, 'id');
        $this->assertArrayHasKey('@createdAt', $byId);
        $createdAt = $byId['@createdAt'];
        $this->assertSame('createdAt', $createdAt['name']);
        $this->assertNotSame('', $createdAt['displayName']);
        foreach (['type', 'multiple', 'facetEnabled', 'sortable', 'searchable', 'enabled'] as $key) {
            $this->assertArrayHasKey($key, $createdAt);
        }

        $displayNames = array_column($members, 'displayName');
        $sorted = $displayNames;
        sort($sorted);
        $this->assertSame($sorted, $displayNames);

        // Item served from the same in-memory list
        $response = $client->request('GET', '/built-in-attributes/'.rawurlencode('@createdAt'));
        $this->assertResponseIsSuccessful();
        $item = $response->toArray();
        unset($item['@context']);
        $this->assertSame($createdAt, $item);

        $client->request('GET', '/built-in-attributes/'.rawurlencode('@unknown'));
        $this->assertResponseStatusCodeSame(404);
    }

    public function testIntegrationTypes(): void
    {
        $client = static::createClient();
        $response = $client->request('GET', '/integration-types');
        $members = $response->toArray()['hydra:member'];
        $byName = array_column($members, null, 'name');
        $this->assertArrayHasKey('core.rendition', $byName);

        $rendition = $byName['core.rendition'];
        // Dots are not allowed in the identifier: they are encoded as "--"
        $this->assertSame('core--rendition', $rendition['id']);
        $this->assertSame('/integration-types/core--rendition', $rendition['@id']);
        $this->assertNotSame('', $rendition['displayName']);
        $this->assertIsString($rendition['description']);
        $this->assertNotSame('', $rendition['reference']);
        $this->assertIsArray($rendition['categories']);
        $this->assertIsArray($rendition['features']);
        $this->assertIsBool($rendition['requiresWorkspace']);

        $response = $client->request('GET', '/integration-types/core--rendition');
        $this->assertResponseIsSuccessful();
        $this->assertSame('core.rendition', $response->toArray()['name']);

        $client->request('GET', '/integration-types/core--unknown');
        $this->assertResponseStatusCodeSame(404);
    }

    public function testRenditionBuildReference(): void
    {
        $response = static::createClient()->request('GET', '/rendition-build-reference');

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertSame('rendition-build-reference', $data['@type']);
        $this->assertSame('/rendition-build-reference', $data['@id']);
        $this->assertNotSame('', $data['reference']);
        $this->assertNotEmpty($data['references']);
        foreach ($data['references'] as $module) {
            $this->assertNotSame('', $module['name']);
            $this->assertArrayHasKey('description', $module);
            $this->assertIsString($module['reference']);
        }
        $this->assertContains('imagine', array_column($data['references'], 'name'));
    }

    public function testOpenApiDocumentationIsGenerated(): void
    {
        $client = static::createClient();
        $response = $client->request('GET', '/docs', [
            'headers' => ['Accept' => 'application/vnd.openapi+json'],
        ]);
        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertStringStartsWith('3.', $data['openapi']);
        foreach ([
            '/workspaces',
            '/workspaces/{id}',
            '/workspaces/{id}/flush',
            '/workspaces/{id}/logo',
            '/workspaces-by-slug/{slug}',
            '/locales',
            '/field-types',
            '/built-in-attributes',
            '/integration-types/{id}',
            '/rendition-build-reference',
            '/assets',
            '/collections',
        ] as $path) {
            $this->assertArrayHasKey($path, $data['paths'], $path);
        }
        $this->assertGreaterThan(100, count($data['paths']));

        // Hydra documentation as well
        $response = $client->request('GET', '/docs.jsonld');
        $this->assertResponseIsSuccessful();
        $this->assertNotEmpty($response->toArray()['hydra:supportedClass']);
    }

    public function testJsonLdContexts(): void
    {
        $client = static::createClient();
        foreach (['workspace', 'locale', 'field-type', 'built-in-attribute', 'integration-type', 'rendition-build-reference', 'asset', 'collection'] as $shortName) {
            $response = $client->request('GET', '/contexts/'.$shortName);
            $this->assertResponseIsSuccessful($shortName);
            $this->assertArrayHasKey('@context', $response->toArray());
        }

        $client->request('GET', '/contexts/not-a-resource');
        $this->assertResponseStatusCodeSame(404);
    }
}
