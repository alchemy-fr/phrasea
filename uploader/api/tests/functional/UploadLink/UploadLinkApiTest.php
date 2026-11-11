<?php

declare(strict_types=1);

namespace App\Tests\Functional\UploadLink;

use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use App\Entity\UploadLink;
use PHPUnit\Framework\Attributes\DataProvider;

class UploadLinkApiTest extends AbstractUploadLinkTestCase
{
    public function testCreatingALinkNeedsThePermission(): void
    {
        $target = $this->createTarget();

        $response = $this->request(KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::USER_UID), 'POST', '/upload-links', [
            'target' => '/targets/'.$target->getId(),
        ]);
        $this->assertSame(403, $response->getStatusCode());

        $response = $this->request(KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::USER_UID), 'GET', '/me');
        $this->assertFalse($response->toArray()['permissions']['upload_link']);

        $response = $this->request(KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID), 'GET', '/me');
        $this->assertTrue($response->toArray()['permissions']['upload_link']);
    }

    public function testCreateALinkWithThePermission(): void
    {
        $target = $this->createTarget();
        $this->grantUploadLink(KeycloakClientTestMock::USER_UID);

        $response = $this->request(KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::USER_UID), 'GET', '/me');
        $this->assertTrue($response->toArray()['permissions']['upload_link']);

        $response = $this->request(KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::USER_UID), 'POST', '/upload-links', [
            'target' => '/targets/'.$target->getId(),
            'name' => 'John',
            'expiresAt' => '2099-01-01T00:00:00+00:00',
            'maxAssets' => 3,
            'maxTotalSize' => 1000,
            'ownerId' => KeycloakClientTestMock::OTHER_USER_UID,
            'usedAssets' => 2,
        ]);
        $this->assertSame(201, $response->getStatusCode(), $response->getContent(false));
        $data = $response->toArray();

        $this->assertMatchesUuid($data['id']);
        $this->assertSame(64, strlen($data['token']));
        $this->assertSame('John', $data['name']);
        $this->assertSame(KeycloakClientTestMock::USER_UID, $data['ownerId']);
        $this->assertSame(0, $data['usedAssets']);
        $this->assertSame(0, $data['usedSize']);
        $this->assertSame(3, $data['maxAssets']);
        $this->assertSame(1000, $data['maxTotalSize']);
        $this->assertSame(UploadLink::STATUS_ACTIVE, $data['status']);
        $this->assertSame($target->getId(), $data['target']['id']);
        $this->assertSame($target->getName(), $data['target']['name']);
        $this->assertArrayNotHasKey('lastFormData', $data);
    }

    public function testInvalidLimitsAreRefused(): void
    {
        $target = $this->createTarget();

        $response = $this->request(KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID), 'POST', '/upload-links', [
            'target' => '/targets/'.$target->getId(),
            'maxAssets' => 0,
        ]);
        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame('maxAssets', $response->toArray(false)['violations'][0]['propertyPath']);
    }

    public function testCreatingALinkNeedsToBeAbleToUploadToTheTarget(): void
    {
        $target = $this->createTarget();
        $target->setAllowedGroups(['restricted-group']);
        self::getEntityManager()->flush();
        $this->grantUploadLink(KeycloakClientTestMock::USER_UID);

        $response = $this->request(KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::USER_UID), 'POST', '/upload-links', [
            'target' => '/targets/'.$target->getId(),
        ]);
        $this->assertSame(403, $response->getStatusCode());
    }

    public function testUsersListTheirLinksAndAdminsEveryLink(): void
    {
        $target = $this->createTarget();
        $other = $this->createTarget('other-target');
        $mine = $this->createLink($target);
        $mineOnOther = $this->createLink($other);
        $theirs = $this->createLink($target, KeycloakClientTestMock::OTHER_USER_UID);

        $response = $this->request(KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::USER_UID), 'GET', '/upload-links');
        $this->assertSame(403, $response->getStatusCode());

        $this->grantUploadLink(KeycloakClientTestMock::USER_UID);
        $response = $this->request(KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::USER_UID), 'GET', '/upload-links');
        $this->assertSame(200, $response->getStatusCode());
        $ids = array_column($response->toArray()['member'], 'id');
        sort($ids);
        $expected = [$mine->getId(), $mineOnOther->getId()];
        sort($expected);
        $this->assertSame($expected, $ids);

        $response = $this->request(KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::USER_UID), 'GET', '/upload-links?target='.$target->getId());
        $this->assertSame([$mine->getId()], array_column($response->toArray()['member'], 'id'));

        $response = $this->request(KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID), 'GET', '/upload-links');
        $this->assertSame(3, $response->toArray()['totalItems']);

        $response = $this->request(KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::USER_UID), 'GET', '/upload-links/'.$theirs->getId());
        $this->assertSame(403, $response->getStatusCode());
    }

    public function testUsersManageTheirOwnLinksOnly(): void
    {
        $target = $this->createTarget();
        $other = $this->createTarget('other-target');
        $mine = $this->createLink($target);
        $theirs = $this->createLink($target, KeycloakClientTestMock::OTHER_USER_UID);
        $this->grantUploadLink(KeycloakClientTestMock::USER_UID);
        $userJwt = KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::USER_UID);

        $response = $this->request($userJwt, 'PATCH', '/upload-links/'.$mine->getId(), [
            'enabled' => false,
            'maxAssets' => null,
            'target' => '/targets/'.$other->getId(),
        ]);
        $this->assertSame(200, $response->getStatusCode(), $response->getContent(false));
        $this->assertSame(UploadLink::STATUS_DISABLED, $response->toArray()['status']);
        $this->assertSame($target->getId(), $this->reloadLink($mine)->getTarget()->getId(), 'The target cannot change');

        $response = $this->request($userJwt, 'PATCH', '/upload-links/'.$theirs->getId(), ['enabled' => false]);
        $this->assertSame(403, $response->getStatusCode());
        $response = $this->request($userJwt, 'DELETE', '/upload-links/'.$theirs->getId());
        $this->assertSame(403, $response->getStatusCode());

        $response = $this->request(KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID), 'PATCH', '/upload-links/'.$theirs->getId(), ['name' => 'Renamed']);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('Renamed', $response->toArray()['name']);

        $response = $this->request($userJwt, 'DELETE', '/upload-links/'.$mine->getId());
        $this->assertSame(204, $response->getStatusCode());
        $this->assertNull(self::getEntityManager()->find(UploadLink::class, $mine->getId()));
    }

    public static function getUnusableLinks(): iterable
    {
        yield 'unknown' => [null, 'upload_link_invalid'];
        yield 'disabled' => [['enabled' => false], 'upload_link_disabled'];
        yield 'expired' => [['expiresAt' => new \DateTimeImmutable('-1 minute')], 'upload_link_expired'];
    }

    #[DataProvider('getUnusableLinks')]
    public function testAnUnusableLinkDoesNotAuthenticate(?array $options, string $code): void
    {
        $token = null === $options ? str_repeat('a', 64) : $this->createLink($this->createTarget(), options: $options)->getToken();

        $response = $this->linkRequest($token, 'GET', '/upload-links/current');

        $this->assertSame(401, $response->getStatusCode());
        $this->assertSame($code, $response->toArray(false)['code']);
    }

    public function testCurrentLink(): void
    {
        $target = $this->createTarget();
        $target->setDescription('Send your pictures');
        $link = $this->createLink($target, options: ['maxAssets' => 10, 'maxTotalSize' => 5000, 'expiresAt' => new \DateTimeImmutable('+1 day')]);
        $link->setLastFormData(['title' => 'Previous']);
        self::getEntityManager()->flush();

        $response = $this->linkRequest($link->getToken(), 'GET', '/upload-links/current');

        $this->assertSame(200, $response->getStatusCode(), $response->getContent(false));
        $data = $response->toArray();
        $this->assertSame($link->getId(), $data['id']);
        $this->assertSame('Photographer', $data['name']);
        $this->assertSame(['id' => $target->getId(), 'name' => $target->getName(), 'description' => 'Send your pictures'], $data['target']);
        $this->assertSame(10, $data['maxAssets']);
        $this->assertSame(5000, $data['maxTotalSize']);
        $this->assertSame(0, $data['usedAssets']);
        $this->assertSame(0, $data['usedSize']);
        $this->assertNotNull($data['expiresAt']);
        $this->assertSame(['title' => 'Previous'], $data['lastFormData']);
        $this->assertArrayNotHasKey('token', $data);
        $this->assertArrayNotHasKey('ownerId', $data);

        $response = $this->request(KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID), 'GET', '/upload-links/current');
        $this->assertSame(403, $response->getStatusCode());
    }

    public static function getRoutesOutsideTheUploadFlow(): iterable
    {
        yield 'me' => ['GET', '/me', null];
        yield 'targets' => ['GET', '/targets', null];
        yield 'target data' => ['GET', '/targets/{target}/data', null];
        yield 'target form edition' => ['PUT', '/targets/{target}/form', ['form' => null]];
        yield 'downloads' => ['POST', '/downloads', ['url' => 'https://example.com/a.jpg', 'target' => '/targets/{target}']];
        yield 'link list' => ['GET', '/upload-links', null];
        yield 'link' => ['GET', '/upload-links/{link}', null];
        yield 'link edition' => ['PATCH', '/upload-links/{link}', ['maxAssets' => 1000]];
        yield 'databox' => ['GET', '/databox/workspaces', null];
        yield 'commits' => ['GET', '/commits', null];
    }

    #[DataProvider('getRoutesOutsideTheUploadFlow')]
    public function testALinkOnlyOpensTheUploadFlow(string $method, string $url, ?array $data): void
    {
        $target = $this->createTarget();
        $link = $this->createLink($target);
        $replace = ['{target}' => $target->getId(), '{link}' => $link->getId()];

        $response = $this->linkRequest(
            $link->getToken(),
            $method,
            strtr($url, $replace),
            null !== $data ? json_decode(strtr(json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES), $replace), true, 512, JSON_THROW_ON_ERROR) : null,
        );

        $this->assertSame(403, $response->getStatusCode(), $response->getContent(false));
    }

    public function testALinkOnlyReadsItsTarget(): void
    {
        $target = $this->createTarget();
        $other = $this->createTarget('other-target');
        $link = $this->createLink($target);

        $response = $this->linkRequest($link->getToken(), 'GET', '/targets/'.$target->getId());
        $this->assertSame(200, $response->getStatusCode());

        $response = $this->linkRequest($link->getToken(), 'GET', '/targets/'.$target->getId().'/form');
        $this->assertSame(200, $response->getStatusCode());

        $response = $this->linkRequest($link->getToken(), 'GET', '/targets/'.$other->getId());
        $this->assertSame(403, $response->getStatusCode());

        $response = $this->linkRequest($link->getToken(), 'GET', '/targets/'.$other->getId().'/form');
        $this->assertSame(403, $response->getStatusCode());
    }
}
