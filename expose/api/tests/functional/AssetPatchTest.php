<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use App\Entity\Asset;

class AssetPatchTest extends AbstractExposeTestCase
{
    public function testOnlyEditableFieldsArePatched(): void
    {
        $ownPublication = $this->createPublication(['ownerId' => KeycloakClientTestMock::USER_UID]);
        $otherPublication = $this->createPublication(['ownerId' => KeycloakClientTestMock::ADMIN_UID]);
        $assetId = $this->createAsset($ownPublication, ['ownerId' => KeycloakClientTestMock::USER_UID]);
        $path = self::getEntityManager()->find(Asset::class, $assetId)->getPath();
        $this->clearEmBeforeApiCall();

        $response = $this->request(KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::USER_UID), 'PATCH', '/assets/'.$assetId, [
            'title' => 'New title',
            'path' => 'another/object.jpg',
            'publication' => '/publications/'.$otherPublication->getId(),
            'ownerId' => KeycloakClientTestMock::ADMIN_UID,
        ]);
        $this->assertSame(200, $response->getStatusCode());

        $this->clearEmBeforeApiCall();
        $asset = self::getEntityManager()->find(Asset::class, $assetId);
        $this->assertSame('New title', $asset->getTitle());
        $this->assertSame($path, $asset->getPath());
        $this->assertSame($ownPublication->getId(), $asset->getPublication()->getId());
        $this->assertSame(KeycloakClientTestMock::USER_UID, $asset->getOwnerId());
    }
}
