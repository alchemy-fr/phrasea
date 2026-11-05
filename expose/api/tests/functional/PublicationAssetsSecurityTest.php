<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;

class PublicationAssetsSecurityTest extends AbstractExposeTestCase
{
    public function testAssetsOfAProtectedPublicationAreNotListed(): void
    {
        $publication = $this->createPublication(['password' => 's3cr3t']);
        $this->createAsset($publication);
        $this->clearEmBeforeApiCall();

        $response = $this->request(null, 'GET', '/publications/'.$publication->getId().'/assets');
        $this->assertContains($response->getStatusCode(), [401, 403]);

        $response = $this->request(KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID), 'GET', '/publications/'.$publication->getId().'/assets');
        $this->assertSame(200, $response->getStatusCode());
    }

    public function testAssetsOfAPublicPublicationDoNotExposeTheirStoragePath(): void
    {
        $publication = $this->createPublication();
        $this->createAsset($publication);
        $this->clearEmBeforeApiCall();

        $response = $this->request(null, 'GET', '/publications/'.$publication->getId().'/assets');
        $this->assertSame(200, $response->getStatusCode());
        $json = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $assets = $json['hydra:member'] ?? $json['member'] ?? $json;
        $this->assertCount(1, $assets);
        $this->assertArrayNotHasKey('path', $assets[0]);
        $this->assertArrayNotHasKey('ownerId', $assets[0]);
    }

    public function testAssetListIsRestrictedToAdmins(): void
    {
        $response = $this->request(null, 'GET', '/assets');
        $this->assertContains($response->getStatusCode(), [401, 403]);

        $response = $this->request(KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::USER_UID), 'GET', '/assets');
        $this->assertSame(403, $response->getStatusCode());

        $response = $this->request(KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID), 'GET', '/assets');
        $this->assertSame(200, $response->getStatusCode());
    }
}
