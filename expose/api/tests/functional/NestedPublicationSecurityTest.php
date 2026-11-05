<?php

declare(strict_types=1);

namespace App\Tests\Functional;

class NestedPublicationSecurityTest extends AbstractExposeTestCase
{
    public function testAProtectedChildIsNotDetailedInItsParent(): void
    {
        $parent = $this->createPublication(['title' => 'Parent']);
        $child = $this->createPublication([
            'title' => 'Secret child',
            'password' => 's3cr3t',
            'publiclyListed' => false,
            'parent' => $parent,
        ]);
        $this->createAsset($child, ['description' => 'Secret asset']);
        $this->clearEmBeforeApiCall();

        $response = $this->request(null, 'GET', '/publications/'.$parent->getId());
        $this->assertSame(200, $response->getStatusCode());
        $json = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertCount(1, $json['children']);
        $this->assertSame($child->getId(), $json['children'][0]['id']);
        $this->assertArrayNotHasKey('title', $json['children'][0]);
        $this->assertArrayNotHasKey('cover', $json['children'][0]);
        $this->assertArrayNotHasKey('assets', $json['children'][0]);
        $this->assertStringNotContainsString('Secret', $response->getContent());
    }

    public function testTheCoverOfAProtectedPublicationOnlyExposesItsThumbnail(): void
    {
        $publication = $this->createPublication(['title' => 'Protected', 'password' => 's3cr3t']);
        $assetId = $this->createAsset($publication);
        $this->createSubDefinition($assetId, ['name' => 'thumbnail']);
        $this->clearEmBeforeApiCall();

        foreach (['/publications', '/publications/'.$publication->getId()] as $uri) {
            $response = $this->request(null, 'GET', $uri);
            $this->assertSame(200, $response->getStatusCode());
            $json = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
            $cover = ('/publications' === $uri ? $json[0] : $json)['cover'] ?? [];

            foreach (['url', 'downloadUrl', 'previewUrl', 'posterUrl'] as $key) {
                $this->assertEmpty($cover[$key] ?? null, sprintf('%s exposes the cover %s', $uri, $key));
            }
        }
    }
}
