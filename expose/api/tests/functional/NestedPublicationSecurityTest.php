<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use App\Doctrine\PublicationExtension;
use App\Entity\Publication;
use PHPUnit\Framework\Attributes\DataProvider;

class NestedPublicationSecurityTest extends AbstractExposeTestCase
{
    #[DataProvider('getHiddenOptions')]
    public function testTheDescendantsOfAHiddenPublicationAreHidden(array $hiddenOptions): void
    {
        $root = $this->createPublication(['title' => 'Root', 'downloadEnabled' => true] + $hiddenOptions);
        $parent = $this->createPublication(['title' => 'Parent', 'downloadEnabled' => true, 'parent' => $root]);
        $child = $this->createPublication(['title' => 'Child', 'downloadEnabled' => true, 'parent' => $parent]);
        $assetId = $this->createAsset($child, ['description' => 'Hidden asset']);
        $this->clearEmBeforeApiCall();

        foreach ([$parent, $child] as $publication) {
            $response = $this->request(null, 'GET', '/publications/'.$publication->getId());
            $this->assertContains($response->getStatusCode(), [401, 403], $publication->getTitle());
        }

        $response = $this->request(null, 'GET', '/assets/'.$assetId);
        $this->assertStringNotContainsString('Hidden asset', $response->getContent());
        $this->assertContains($this->request(null, 'GET', '/publications/'.$child->getId().'/assets')->getStatusCode(), [401, 403]);
        $this->assertContains($this->request(null, 'GET', '/publications/'.$child->getId().'/archive')->getStatusCode(), [401, 403]);

        $this->assertNotListed([$parent->getId(), $child->getId()], '/publications?flatten=true');
        $this->assertNotListed([$child->getId()], '/publications?parentId='.$parent->getId());
    }

    public static function getHiddenOptions(): array
    {
        return [
            'disabled' => [['enabled' => false]],
            'expired' => [['endDate' => new \DateTimeImmutable('-1 hour')]],
            'not started' => [['startDate' => new \DateTimeImmutable('+1 hour')]],
        ];
    }

    public function testTheDescendantsOfAPublicationHiddenByItsProfileAreHidden(): void
    {
        $profileId = $this->createProfile(['enabled' => false]);
        $root = $this->createPublication(['title' => 'Root', 'profile_id' => $profileId]);
        $root->getConfig()->setEnabled(null);
        self::getEntityManager()->flush();
        $child = $this->createPublication(['title' => 'Child', 'parent' => $root]);
        $this->clearEmBeforeApiCall();

        $this->assertContains($this->request(null, 'GET', '/publications/'.$child->getId())->getStatusCode(), [401, 403]);
        $this->assertNotListed([$child->getId()], '/publications?flatten=true');
    }

    public function testTheDescendantsOfAVisiblePublicationAreVisible(): void
    {
        $root = $this->createPublication(['title' => 'Root']);
        $parent = $this->createPublication(['title' => 'Parent', 'parent' => $root]);
        $child = $this->createPublication(['title' => 'Child', 'parent' => $parent]);
        $assetId = $this->createAsset($child, ['description' => 'Visible asset']);
        $this->clearEmBeforeApiCall();

        $response = $this->request(null, 'GET', '/publications/'.$child->getId());
        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('Visible asset', $this->request(null, 'GET', '/assets/'.$assetId)->getContent());

        $this->assertContains($child->getId(), $this->getListedIds('/publications?flatten=true'));
        $this->assertSame([$child->getId()], $this->getListedIds('/publications?parentId='.$parent->getId()));
    }

    public function testTheDescendantsOfAHiddenPublicationAreListedToItsOwner(): void
    {
        $root = $this->createPublication(['title' => 'Root', 'enabled' => false]);
        $child = $this->createPublication(['title' => 'Child', 'parent' => $root, 'ownerId' => KeycloakClientTestMock::USER_UID]);
        $this->clearEmBeforeApiCall();

        $token = KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::USER_UID);
        $response = $this->request($token, 'GET', '/publications?flatten=true');
        $this->assertSame(200, $response->getStatusCode());
        $ids = array_column(json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR), 'id');
        $this->assertContains($child->getId(), $ids);
        $this->assertSame(200, $this->request($token, 'GET', '/publications/'.$child->getId())->getStatusCode());
    }

    public function testThePublicationsDeeperThanTheListedDepthAreNotListed(): void
    {
        $parent = null;
        $publications = [];
        for ($depth = 0; $depth <= PublicationExtension::MAX_ANCESTOR_DEPTH + 1; ++$depth) {
            $parent = $this->createPublication(['title' => 'Depth '.$depth] + (null !== $parent ? ['parent' => $parent] : []));
            $publications[] = $parent->getId();
        }
        $this->clearEmBeforeApiCall();

        $listed = $this->getListedIds('/publications?flatten=true');
        $this->assertSame(array_slice($publications, 0, PublicationExtension::MAX_ANCESTOR_DEPTH + 1), array_values(array_intersect($publications, $listed)));
    }

    /**
     * @param list<string> $ids
     */
    private function assertNotListed(array $ids, string $uri): void
    {
        $listed = $this->getListedIds($uri);
        foreach ($ids as $id) {
            $this->assertNotContains($id, $listed, $uri);
        }
    }

    /**
     * @return list<string>
     */
    private function getListedIds(string $uri): array
    {
        $response = $this->request(null, 'GET', $uri);
        $this->assertSame(200, $response->getStatusCode(), $uri);

        return array_column(json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR), 'id');
    }

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

    public function testAHiddenChildIsFilteredOutWithoutChangingTheParentEntity(): void
    {
        $parent = $this->createPublication(['title' => 'Parent']);
        $visible = $this->createPublication(['title' => 'Visible child', 'parent' => $parent]);
        $hidden = $this->createPublication(['title' => 'Hidden child', 'enabled' => false, 'parent' => $parent]);
        $this->clearEmBeforeApiCall();

        $response = $this->request(null, 'GET', '/publications/'.$parent->getId());
        $this->assertSame(200, $response->getStatusCode());
        $json = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame([$visible->getId()], array_column($json['children'], 'id'));
        $this->assertSame(1, $json['childrenCount']);
        $this->assertStringNotContainsString('Hidden child', $response->getContent());

        $children = self::getEntityManager()->find(Publication::class, $parent->getId())->getChildren();
        $this->assertEqualsCanonicalizing(
            [$visible->getId(), $hidden->getId()],
            array_map(fn (Publication $child): string => $child->getId(), $children->getValues()),
        );
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
