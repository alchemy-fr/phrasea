<?php

declare(strict_types=1);

namespace App\Tests\Functional\Search;

use Alchemy\AclBundle\Security\PermissionInterface;
use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use App\Entity\Core\Workspace;
use App\Entity\Core\WorkspaceItemPrivacyInterface;
use App\Service\Workspace\TermsManager;
use App\Tests\Functional\AbstractSearchTestCase;

/**
 * A workspace whose current Terms & Conditions are not signed by the user
 * is left out of every search and listing until they sign them.
 */
class WorkspaceTermsSearchTest extends AbstractSearchTestCase
{
    public function testUnsignedTermsHideTheWorkspaceFromSearchesAndLists(): void
    {
        $gated = $this->createWorkspace(['ownerId' => 'another_owner', 'slug' => 'gated']);
        $open = $this->createWorkspace(['ownerId' => 'another_owner', 'slug' => 'open']);
        foreach ([$gated, $open] as $workspace) {
            $this->grantUserOnObject(KeycloakClientTestMock::USER_UID, $workspace, PermissionInterface::VIEW);
        }

        $gatedCollection = $this->createCollection(['workspace' => $gated, 'ownerId' => 'another_owner', 'public' => true, 'name' => 'Gated collection']);
        $openCollection = $this->createCollection(['workspace' => $open, 'ownerId' => 'another_owner', 'public' => true, 'name' => 'Open collection']);
        // collection_access (root collections listing) is only computed on PostgreSQL
        foreach ([$gatedCollection, $openCollection] as $collection) {
            $this->createCollectionAccess($collection, null, WorkspaceItemPrivacyInterface::PUBLIC);
        }
        $this->createAsset(['workspace' => $gated, 'public' => true, 'name' => 'Gated asset', 'collectionId' => $gatedCollection->getId(), 'tags' => ['GatedTag']]);
        $this->createAsset(['workspace' => $open, 'public' => true, 'name' => 'Open asset', 'collectionId' => $openCollection->getId(), 'tags' => ['OpenTag']]);

        /** @var TermsManager $termsManager */
        $termsManager = self::getService(TermsManager::class);
        $terms = $termsManager->updateTerms($gated, 'Accept these terms.');
        self::getEntityManager()->flush();
        $this->assertNotNull($terms);
        $gatedId = $gated->getId();
        self::getEntityManager()->clear();

        self::populateSearchIndices();

        $assets = $this->getAsUser('/assets');
        $this->assertSame(1, $assets['totalItems']);
        $this->assertSame(['Open asset'], array_column($assets['member'], 'name'));
        $this->assertStringNotContainsString($gatedId, json_encode($assets['facets'] ?? [], JSON_THROW_ON_ERROR));
        $this->assertStringNotContainsString('Gated collection', json_encode($assets['facets'] ?? [], JSON_THROW_ON_ERROR));

        $this->assertSame(['Open collection'], array_column($this->getAsUser('/collections')['member'], 'name'));
        $this->assertSame([], $this->getAsUser('/collections?query=Gated')['member']);
        $this->assertSame(['OpenTag'], array_column($this->getAsUser('/attribute-entities')['member'], 'value'));

        /** @var TermsManager $termsManager */
        $termsManager = self::getService(TermsManager::class);
        $terms = $termsManager->getCurrentTerms(self::getEntityManager()->find(Workspace::class, $gatedId));
        $this->assertNotNull($terms);
        $termsManager->sign($terms, KeycloakClientTestMock::USER_UID);

        $assets = $this->getAsUser('/assets');
        $this->assertSame(2, $assets['totalItems']);
        $this->assertStringContainsString($gatedId, json_encode($assets['facets'] ?? [], JSON_THROW_ON_ERROR));

        $collections = array_column($this->getAsUser('/collections')['member'], 'name');
        sort($collections);
        $this->assertSame(['Gated collection', 'Open collection'], $collections);
        $this->assertSame(['Gated collection'], array_column($this->getAsUser('/collections?query=Gated')['member'], 'name'));
        $tags = array_column($this->getAsUser('/attribute-entities')['member'], 'value');
        sort($tags);
        $this->assertSame(['GatedTag', 'OpenTag'], $tags);
    }

    private function getAsUser(string $uri): array
    {
        $response = self::createClient()->request('GET', $uri, [
            'headers' => [
                'Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::USER_UID),
            ],
        ]);
        $this->assertResponseIsSuccessful();

        return $response->toArray();
    }
}
