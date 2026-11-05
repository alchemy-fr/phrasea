<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;

class SubDefinitionSecurityTest extends AbstractExposeTestCase
{
    public function testSubDefinitionsOfAProtectedPublicationAreNotReadable(): void
    {
        $publication = $this->createPublication(['password' => 's3cr3t']);
        $assetId = $this->createAsset($publication);
        $subDefinitionId = $this->createSubDefinition($assetId);
        $this->clearEmBeforeApiCall();

        $response = $this->request(null, 'GET', '/sub-definitions/'.$subDefinitionId);
        $this->assertContains($response->getStatusCode(), [401, 403]);

        $response = $this->request(null, 'GET', '/assets/'.$assetId.'/sub-definitions');
        $this->assertContains($response->getStatusCode(), [401, 403]);

        $response = $this->request(KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID), 'GET', '/assets/'.$assetId.'/sub-definitions');
        $this->assertSame(200, $response->getStatusCode());
    }

    public function testSubDefinitionsOfAPublicPublicationAreReadable(): void
    {
        $publication = $this->createPublication();
        $assetId = $this->createAsset($publication);
        $subDefinitionId = $this->createSubDefinition($assetId);
        $this->clearEmBeforeApiCall();

        $response = $this->request(null, 'GET', '/sub-definitions/'.$subDefinitionId);
        $this->assertSame(200, $response->getStatusCode());

        $response = $this->request(null, 'GET', '/assets/'.$assetId.'/sub-definitions');
        $this->assertSame(200, $response->getStatusCode());
    }

    public function testSubDefinitionListIsRestrictedToAdmins(): void
    {
        $response = $this->request(null, 'GET', '/sub-definitions');
        $this->assertContains($response->getStatusCode(), [401, 403]);

        $response = $this->request(KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::USER_UID), 'GET', '/sub-definitions');
        $this->assertSame(403, $response->getStatusCode());
    }
}
