<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use App\Tests\Functional\UploadLink\AbstractUploadLinkTestCase;

class UploadConfigTest extends AbstractUploadLinkTestCase
{
    public function testTheLimitsOfABatchAreReadByUsers(): void
    {
        $response = $this->request(KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::USER_UID), 'GET', '/upload-config');

        $this->assertSame(200, $response->getStatusCode(), $response->getContent(false));
        $data = $response->toArray();
        $this->assertIsInt($data['maxFileSize']);
        $this->assertGreaterThan(0, $data['maxFileSize']);
        $this->assertArrayHasKey('maxCommitSize', $data);
        $this->assertArrayHasKey('maxFileCount', $data);
        $this->assertIsArray($data['allowedTypes']);
        $this->assertSame(rtrim((string) ($_SERVER['UPLOADER_CLIENT_URL'] ?? $_ENV['UPLOADER_CLIENT_URL'] ?? getenv('UPLOADER_CLIENT_URL')), '/'), $data['clientUrl']);
    }

    public function testTheLimitsOfABatchAreReadThroughAnUploadLink(): void
    {
        $link = $this->createLink($this->createTarget());

        $response = $this->linkRequest($link->getToken(), 'GET', '/upload-config');

        $this->assertSame(200, $response->getStatusCode(), $response->getContent(false));
        $this->assertArrayHasKey('maxFileSize', $response->toArray());
    }

    public function testTheLimitsOfABatchAreNotPublic(): void
    {
        $response = $this->request(null, 'GET', '/upload-config');

        $this->assertSame(401, $response->getStatusCode());
    }
}
