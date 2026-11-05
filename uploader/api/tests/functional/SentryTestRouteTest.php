<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;

class SentryTestRouteTest extends AbstractUploaderTestCase
{
    public function testSentryTestRoutesAreRestrictedToAdmins(): void
    {
        $response = $this->request(KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::USER_UID), 'GET', '/_health/sentry-test/single-log');
        $this->assertSame(403, $response->getStatusCode());

        $response = $this->request(KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID), 'GET', '/_health/sentry-test/single-log');
        $this->assertSame(200, $response->getStatusCode());
    }
}
