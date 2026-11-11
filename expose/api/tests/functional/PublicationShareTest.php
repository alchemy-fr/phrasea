<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Alchemy\AclBundle\Security\PermissionInterface;
use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use App\Entity\PublicationShare;
use Symfony\Component\HttpFoundation\Response;

class PublicationShareTest extends AbstractExposeTestCase
{
    public function testOwnerManagesTheShares(): void
    {
        $publicationId = $this->createPublication([
            'ownerId' => KeycloakClientTestMock::USER_UID,
            'enabled' => false,
        ])->getId();
        $token = KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::USER_UID);

        $expiresAt = new \DateTimeImmutable('+30 days');
        $response = $this->request($token, 'POST', '/publications/'.$publicationId.'/shares', [
            'name' => 'For John',
            'expiresAt' => $expiresAt->format(\DATE_ATOM),
        ]);
        $this->assertSame(201, $response->getStatusCode(), $this->describe($response));
        $share = $this->decode($response);
        $this->assertSame('For John', $share['name']);
        $this->assertSame(PublicationShare::TOKEN_LENGTH, strlen($share['token']));
        $this->assertStringEndsWith('/s/'.$share['token'], $share['url']);
        $this->assertSame(KeycloakClientTestMock::USER_UID, $share['createdBy']);
        $this->assertSame(PublicationShare::STATUS_ACTIVE, $share['status']);
        $this->assertSame($expiresAt->getTimestamp(), (new \DateTimeImmutable($share['expiresAt']))->getTimestamp());
        $this->assertNull($share['revokedAt'] ?? null);

        $response = $this->request($token, 'POST', '/publications/'.$publicationId.'/shares', []);
        $this->assertSame(201, $response->getStatusCode(), $this->describe($response));
        $endless = $this->decode($response);
        $this->assertNull($endless['expiresAt'] ?? null);
        $this->assertNotSame($share['token'], $endless['token']);

        $shares = $this->listShares($publicationId, $token);
        $this->assertEqualsCanonicalizing([$share['id'], $endless['id']], array_column($shares, 'id'));

        // Extend
        $later = new \DateTimeImmutable('+60 days');
        $response = $this->request($token, 'PATCH', '/publication-shares/'.$share['id'], [
            'expiresAt' => $later->format(\DATE_ATOM),
        ]);
        $this->assertSame(200, $response->getStatusCode(), $this->describe($response));
        $this->assertSame($later->getTimestamp(), (new \DateTimeImmutable($this->decode($response)['expiresAt']))->getTimestamp());

        // No end
        $response = $this->request($token, 'PATCH', '/publication-shares/'.$share['id'], [
            'expiresAt' => null,
            'name' => 'For John Doe',
        ]);
        $this->assertSame(200, $response->getStatusCode(), $this->describe($response));
        $patched = $this->decode($response);
        $this->assertNull($patched['expiresAt'] ?? null);
        $this->assertSame('For John Doe', $patched['name']);
        $this->assertSame($share['token'], $patched['token']);

        // Revoke, twice
        foreach ([1, 2] as $attempt) {
            $response = $this->request($token, 'POST', '/publication-shares/'.$share['id'].'/revoke');
            $this->assertSame(200, $response->getStatusCode(), $this->describe($response));
            $revoked = $this->decode($response);
            $this->assertNotNull($revoked['revokedAt'] ?? null);
            $this->assertSame(KeycloakClientTestMock::USER_UID, $revoked['revokedBy']);
            $this->assertSame(PublicationShare::STATUS_REVOKED, $revoked['status']);
        }

        $shares = $this->listShares($publicationId, $token);
        $this->assertCount(2, $shares);
        $listed = array_values(array_filter($shares, fn (array $s): bool => $s['id'] === $share['id']))[0];
        $this->assertSame(PublicationShare::STATUS_REVOKED, $listed['status']);

        // A revoked share cannot be modified
        $response = $this->request($token, 'PATCH', '/publication-shares/'.$share['id'], [
            'expiresAt' => $later->format(\DATE_ATOM),
        ]);
        $this->assertSame(422, $response->getStatusCode(), $this->describe($response));

        $response = $this->request($token, 'DELETE', '/publication-shares/'.$share['id']);
        $this->assertSame(204, $response->getStatusCode(), $this->describe($response));
        $this->assertSame([$endless['id']], array_column($this->listShares($publicationId, $token), 'id'));
    }

    public function testOperatorAclManagesTheShares(): void
    {
        $publicationId = $this->createPublication([
            'ownerId' => KeycloakClientTestMock::ADMIN_UID,
        ])->getId();
        $share = $this->createShare($publicationId);
        $userToken = KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::USER_UID);

        $this->grant($publicationId, PermissionInterface::EDIT);
        $this->assertManagementDenied($publicationId, $share->getId(), $userToken, 403);

        $this->grant($publicationId, PermissionInterface::OPERATOR);

        $this->assertCount(1, $this->listShares($publicationId, $userToken));
        $response = $this->request($userToken, 'POST', '/publications/'.$publicationId.'/shares', ['name' => 'Foo']);
        $this->assertSame(201, $response->getStatusCode(), $this->describe($response));
        $response = $this->request($userToken, 'PATCH', '/publication-shares/'.$share->getId(), ['name' => 'Bar']);
        $this->assertSame(200, $response->getStatusCode(), $this->describe($response));
        $response = $this->request($userToken, 'POST', '/publication-shares/'.$share->getId().'/revoke');
        $this->assertSame(200, $response->getStatusCode(), $this->describe($response));
        $response = $this->request($userToken, 'DELETE', '/publication-shares/'.$share->getId());
        $this->assertSame(204, $response->getStatusCode(), $this->describe($response));
    }

    public function testAnonymousCannotManageTheShares(): void
    {
        $publicationId = $this->createPublication()->getId();
        $share = $this->createShare($publicationId);

        $this->assertManagementDenied($publicationId, $share->getId(), null, 401);
    }

    public function testOtherUserCannotManageTheShares(): void
    {
        $publicationId = $this->createPublication([
            'ownerId' => KeycloakClientTestMock::ADMIN_UID,
        ])->getId();
        $share = $this->createShare($publicationId);

        $this->assertManagementDenied($publicationId, $share->getId(), KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::USER_UID), 403);
    }

    public function testExpirationMustBeInTheFuture(): void
    {
        $publicationId = $this->createPublication()->getId();
        $share = $this->createShare($publicationId, ['expiresAt' => new \DateTimeImmutable('-1 day')]);
        $token = KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID);
        $past = (new \DateTimeImmutable('-1 hour'))->format(\DATE_ATOM);

        $response = $this->request($token, 'POST', '/publications/'.$publicationId.'/shares', ['expiresAt' => $past]);
        $this->assertSame(422, $response->getStatusCode(), $this->describe($response));

        $response = $this->request($token, 'PATCH', '/publication-shares/'.$share->getId(), ['expiresAt' => $past]);
        $this->assertSame(422, $response->getStatusCode(), $this->describe($response));

        // An expired share keeps its expiration while renamed
        $response = $this->request($token, 'PATCH', '/publication-shares/'.$share->getId(), ['name' => 'Renamed']);
        $this->assertSame(200, $response->getStatusCode(), $this->describe($response));
        $this->assertSame(PublicationShare::STATUS_EXPIRED, $this->decode($response)['status']);

        $response = $this->request($token, 'POST', '/publications/'.$publicationId.'/shares', ['name' => str_repeat('a', 256)]);
        $this->assertSame(422, $response->getStatusCode(), $this->describe($response));
    }

    public function testUnknownPublication(): void
    {
        $token = KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID);
        foreach (['8a0f32b6-0f51-4c6b-9d7e-4c8d4a1b3c2d', 'not-a-uuid'] as $id) {
            $this->assertSame(404, $this->request($token, 'GET', '/publications/'.$id.'/shares')->getStatusCode());
            $this->assertSame(404, $this->request($token, 'POST', '/publications/'.$id.'/shares', [])->getStatusCode());
        }
        $this->assertSame(404, $this->request($token, 'POST', '/publication-shares/8a0f32b6-0f51-4c6b-9d7e-4c8d4a1b3c2d/revoke')->getStatusCode());
    }

    public function testResolve(): void
    {
        $publication = $this->createPublication([
            'slug' => 'shared-pub',
            'title' => 'Shared',
            'enabled' => false,
            'password' => 'secret',
        ]);
        $publicationId = $publication->getId();
        $active = $this->createShare($publicationId, ['expiresAt' => new \DateTimeImmutable('+1 day')]);
        $expired = $this->createShare($publicationId, ['expiresAt' => new \DateTimeImmutable('-1 day')]);
        $revoked = $this->createShare($publicationId, ['revoked' => true]);

        $response = $this->request(null, 'GET', '/publication-shares/resolve/'.$active->getToken());
        $this->assertSame(200, $response->getStatusCode(), $this->describe($response));
        $json = $this->decode($response);
        $this->assertSame($publicationId, $json['publication']['id']);
        $this->assertSame('shared-pub', $json['publication']['slug']);
        $this->assertSame('Shared', $json['publication']['title']);
        $this->assertArrayNotHasKey('token', $json);
        $this->assertArrayNotHasKey('assets', $json['publication']);
        $this->assertNotNull($json['expiresAt']);

        $notFound = null;
        foreach ([$expired->getToken(), $revoked->getToken(), str_repeat('x', 64), 'short'] as $token) {
            $response = $this->request(null, 'GET', '/publication-shares/resolve/'.$token);
            $this->assertSame(404, $response->getStatusCode(), $token);
            $body = $this->decode($response);
            unset($body['trace']);
            $notFound ??= $body;
            $this->assertSame($notFound, $body, 'The 404 must not tell the cases apart');
        }
    }

    public function testDeletingThePublicationDeletesItsShares(): void
    {
        $publicationId = $this->createPublication()->getId();
        $shareId = $this->createShare($publicationId)->getId();
        $this->clearEmBeforeApiCall();

        $response = $this->request(KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID), 'DELETE', '/publications/'.$publicationId);
        $this->assertSame(204, $response->getStatusCode(), $this->describe($response));

        self::getEntityManager()->clear();
        $this->assertNull(self::getEntityManager()->find(PublicationShare::class, $shareId));
    }

    private function assertManagementDenied(string $publicationId, string $shareId, ?string $token, int $status): void
    {
        foreach ([
            ['GET', '/publications/'.$publicationId.'/shares', null],
            ['POST', '/publications/'.$publicationId.'/shares', []],
            ['GET', '/publication-shares/'.$shareId, null],
            ['PATCH', '/publication-shares/'.$shareId, ['name' => 'Hacked']],
            ['POST', '/publication-shares/'.$shareId.'/revoke', []],
            ['DELETE', '/publication-shares/'.$shareId, null],
        ] as [$method, $uri, $params]) {
            $response = $this->request($token, $method, $uri, $params);
            $this->assertSame($status, $response->getStatusCode(), $method.' '.$uri.': '.$this->describe($response));
        }
    }

    private function grant(string $publicationId, int $mask): void
    {
        $response = $this->request(KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID), 'PUT', '/permissions/ace', [
            'userType' => 'user',
            'userId' => KeycloakClientTestMock::USER_UID,
            'objectType' => 'publication',
            'objectId' => $publicationId,
            'mask' => $mask,
        ]);
        $this->assertSame(200, $response->getStatusCode(), $this->describe($response));
    }

    private function listShares(string $publicationId, string $token): array
    {
        $response = $this->request($token, 'GET', '/publications/'.$publicationId.'/shares');
        $this->assertSame(200, $response->getStatusCode(), $this->describe($response));

        return $this->decode($response);
    }

    private function decode(Response $response): array
    {
        return json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);
    }

    private function describe(Response $response): string
    {
        return substr((string) $response->getContent(), 0, 800);
    }
}
