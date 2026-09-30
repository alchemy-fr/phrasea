<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use App\Tests\Functional\AbstractDataboxTestCase;

/**
 * The exiftool dictionary (/metadata-tags) suggests the namespaces, then the
 * tags of a namespace once the query contains a colon.
 */
class MetadataTagTest extends AbstractDataboxTestCase
{
    public function testAnonymousIsDenied(): void
    {
        static::createClient()->request('GET', '/metadata-tags');

        $this->assertResponseStatusCodeSame(401);
    }

    public function testNamespacesArePrefixMatchedFirst(): void
    {
        $members = $this->search('ipt');

        $this->assertNotEmpty($members);
        $this->assertSame('IPTC', $members[0]['id']);
        $this->assertSame('IPTC', $members[0]['namespace']);
        $this->assertNull($members[0]['name'] ?? null);
        foreach ($members as $member) {
            $this->assertStringNotContainsString(':', $member['id']);
            $this->assertStringContainsStringIgnoringCase('ipt', $member['id']);
        }
    }

    public function testTagsOfNamespace(): void
    {
        $members = $this->search('iptc:key');

        $ids = array_column($members, 'id');
        $this->assertContains('IPTC:Keywords', $ids);
        foreach ($ids as $id) {
            $this->assertStringStartsWith('IPTC:', $id);
        }

        $keywords = $members[array_search('IPTC:Keywords', $ids, true)];
        $this->assertSame('IPTC', $keywords['namespace']);
        $this->assertSame('Keywords', $keywords['name']);
        $this->assertTrue($keywords['writable']);
        $this->assertTrue($keywords['multi']);
    }

    public function testUnknownNamespaceReturnsNothing(): void
    {
        $this->assertSame([], $this->search('NotANamespace:'));
    }

    private function search(string $query): array
    {
        $response = static::createClient()->request('GET', '/metadata-tags', [
            'query' => ['query' => $query],
            'headers' => [
                'Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::USER_UID),
            ],
        ]);
        $this->assertResponseIsSuccessful();

        return $response->toArray()['hydra:member'];
    }
}
