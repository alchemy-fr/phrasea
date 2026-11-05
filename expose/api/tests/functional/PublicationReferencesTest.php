<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use App\Entity\Publication;

class PublicationReferencesTest extends AbstractExposeTestCase
{
    public function testAChildCannotBeAddedToAPublicationOfAnotherUser(): void
    {
        $victim = $this->createPublication(['ownerId' => KeycloakClientTestMock::ADMIN_UID]);
        $own = $this->createPublication(['ownerId' => KeycloakClientTestMock::USER_UID]);
        $this->clearEmBeforeApiCall();

        $response = $this->request(KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::USER_UID), 'PATCH', '/publications/'.$own->getId(), [
            'parent' => '/publications/'.$victim->getId(),
        ]);
        $this->assertSame(403, $response->getStatusCode());

        $this->clearEmBeforeApiCall();
        $this->assertNull(self::getEntityManager()->find(Publication::class, $own->getId())->getParent());
    }

    public function testTheCoverMustBeAnAssetOfThePublication(): void
    {
        $victim = $this->createPublication(['ownerId' => KeycloakClientTestMock::ADMIN_UID, 'password' => 's3cr3t']);
        $victimAssetId = $this->createAsset($victim);
        $own = $this->createPublication(['ownerId' => KeycloakClientTestMock::USER_UID]);
        $ownAssetId = $this->createAsset($own);
        $this->clearEmBeforeApiCall();

        $response = $this->request(KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::USER_UID), 'PATCH', '/publications/'.$own->getId(), [
            'cover' => '/assets/'.$victimAssetId,
        ]);
        $this->assertSame(400, $response->getStatusCode());

        $response = $this->request(KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::USER_UID), 'PATCH', '/publications/'.$own->getId(), [
            'cover' => '/assets/'.$ownAssetId,
        ]);
        $this->assertSame(200, $response->getStatusCode());
    }
}
