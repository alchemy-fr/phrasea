<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;

/**
 * The "query" parameter the select widgets send: a partial match on the title (publications)
 * or the name (profiles).
 */
class SearchQueryTest extends AbstractExposeTestCase
{
    public function testPublicationsAreSearchedByTitle(): void
    {
        foreach (['Summer holidays', 'Winter holidays', 'Work'] as $title) {
            $this->createPublication([
                'title' => $title,
                'no_flush' => true,
                'enabled' => true,
                'publiclyListed' => true,
            ]);
        }
        self::getEntityManager()->flush();

        $this->assertSame(['Summer holidays', 'Winter holidays'], $this->listTitles('/publications?query=HOLIDAYS&order[title]=asc'));
        $this->assertSame(['Work'], $this->listTitles('/publications?query=wor'));
        $this->assertSame([], $this->listTitles('/publications?query=nothing'));
    }

    public function testProfilesAreSearchedByName(): void
    {
        $this->createProfile(['name' => 'Download layout']);
        $this->createProfile(['name' => 'Gallery layout']);
        $this->createProfile(['name' => 'Mapbox']);

        $response = $this->request(KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID), 'GET', '/publication-profiles?query=layout&order[name]=asc');
        $this->assertEquals(200, $response->getStatusCode());
        $json = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(['Download layout', 'Gallery layout'], array_column($json, 'name'));
    }

    private function listTitles(string $url): array
    {
        $response = $this->request(KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::USER_UID), 'GET', $url);
        $this->assertEquals(200, $response->getStatusCode());
        $json = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        return array_column($json, 'title');
    }
}
