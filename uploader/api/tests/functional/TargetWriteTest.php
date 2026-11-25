<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use PHPUnit\Framework\Attributes\DataProvider;

class TargetWriteTest extends AbstractUploaderTestCase
{
    public function testAdminCreatesAndUpdatesATarget(): void
    {
        $token = KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID);

        $response = $this->request($token, 'POST', '/targets', [
            'slug' => 'my-target',
            'name' => 'My target',
            'description' => 'Where my files go',
            'targetUrl' => null,
        ]);
        $this->assertEquals(201, $response->getStatusCode());
        $data = $response->toArray();
        $this->assertSame('my-target', $data['slug']);
        $this->assertSame('My target', $data['name']);
        $this->assertSame('Where my files go', $data['description']);

        $response = $this->request($token, 'PATCH', '/targets/'.$data['id'], [
            'name' => 'Renamed',
            'description' => null,
        ]);
        $this->assertEquals(200, $response->getStatusCode());
        $data = $response->toArray();
        $this->assertSame('Renamed', $data['name']);
        $this->assertNull($data['description'] ?? null);
    }

    public function testAdminReadsTheSettingsOfATarget(): void
    {
        $token = KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID);
        $id = $this->createTargetWithSettings($token);

        $response = $this->request($token, 'GET', '/targets/'.$id.'/settings');
        $this->assertEquals(200, $response->getStatusCode());
        $data = $response->toArray();
        $this->assertSame('Pushed', $data['name']);
        $this->assertSame('https://example.com/upload', $data['targetUrl']);
        $this->assertSame('my-collection', $data['defaultDestination']);
        $this->assertSame('OAuth', $data['authorizationScheme']);
        $this->assertSame(['group-a', 'group-b'], $data['allowedGroups']);
        $this->assertTrue($data['hasAuthorizationKey']);
        $this->assertArrayNotHasKey('authorizationKey', $data);

        $response = $this->request($token, 'GET', '/targets/'.$id);
        $this->assertEquals(200, $response->getStatusCode());
        $data = $response->toArray();
        foreach (['targetUrl', 'defaultDestination', 'authorizationScheme', 'allowedGroups', 'hasAuthorizationKey', 'authorizationKey'] as $key) {
            $this->assertArrayNotHasKey($key, $data);
        }
    }

    public function testUserCannotReadNorUpdateTheSettingsOfATarget(): void
    {
        $id = $this->createTargetWithSettings(KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID));
        $userToken = KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::USER_UID);

        $response = $this->request($userToken, 'GET', '/targets/'.$id.'/settings');
        $this->assertEquals(403, $response->getStatusCode());

        $response = $this->request($userToken, 'PATCH', '/targets/'.$id, [
            'name' => 'Hijacked',
        ]);
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testPatchKeepsTheAuthorizationKeyUnlessRemoved(): void
    {
        $token = KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID);
        $id = $this->createTargetWithSettings($token);

        $response = $this->request($token, 'PATCH', '/targets/'.$id, [
            'name' => 'Renamed',
            'allowedGroups' => ['group-c'],
        ]);
        $this->assertEquals(200, $response->getStatusCode());
        $data = $response->toArray();
        $this->assertSame('Renamed', $data['name']);
        $this->assertSame('https://example.com/upload', $data['targetUrl']);
        $this->assertSame(['group-c'], $data['allowedGroups']);
        $this->assertTrue($data['hasAuthorizationKey']);
        $this->assertArrayNotHasKey('authorizationKey', $data);

        $data = $this->request($token, 'GET', '/targets/'.$id.'/settings')->toArray();
        $this->assertSame(['group-c'], $data['allowedGroups']);
        $this->assertTrue($data['hasAuthorizationKey']);

        $response = $this->request($token, 'PATCH', '/targets/'.$id, [
            'targetUrl' => null,
            'authorizationScheme' => null,
            'authorizationKey' => null,
            'allowedGroups' => [],
        ]);
        $this->assertEquals(200, $response->getStatusCode());
        $data = $response->toArray();
        $this->assertNull($data['targetUrl'] ?? null);
        $this->assertNull($data['authorizationScheme'] ?? null);
        $this->assertNull($data['allowedGroups'] ?? null);
        $this->assertFalse($data['hasAuthorizationKey']);
    }

    public function testAuthorizationKeyDoesNotFollowTheUrlToAnotherOrigin(): void
    {
        $token = KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID);
        $id = $this->createTargetWithSettings($token);

        $data = $this->request($token, 'PATCH', '/targets/'.$id, [
            'targetUrl' => 'https://EXAMPLE.com:443/other-path',
        ])->toArray();
        $this->assertSame('https://EXAMPLE.com:443/other-path', $data['targetUrl']);
        $this->assertTrue($data['hasAuthorizationKey']);

        $data = $this->request($token, 'PATCH', '/targets/'.$id, [
            'authorizationKey' => 'other-secret',
            'targetUrl' => 'https://other.example.com/upload',
        ])->toArray();
        $this->assertTrue($data['hasAuthorizationKey']);

        foreach (['https://elsewhere.example.com/upload', 'https://elsewhere.example.com:8443/upload', 'http://elsewhere.example.com:8443/upload'] as $url) {
            $data = $this->request($token, 'PATCH', '/targets/'.$id, [
                'authorizationKey' => 'secret',
            ])->toArray();
            $this->assertTrue($data['hasAuthorizationKey']);

            $response = $this->request($token, 'PATCH', '/targets/'.$id, [
                'targetUrl' => $url,
            ]);
            $this->assertEquals(200, $response->getStatusCode());
            $data = $response->toArray();
            $this->assertSame($url, $data['targetUrl']);
            $this->assertFalse($data['hasAuthorizationKey'], $url);
        }

        $data = $this->request($token, 'GET', '/targets/'.$id.'/settings')->toArray();
        $this->assertFalse($data['hasAuthorizationKey']);
    }

    public function testAllowedGroupsAreStrings(): void
    {
        $token = KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID);
        $id = $this->createTargetWithSettings($token);

        $response = $this->request($token, 'PATCH', '/targets/'.$id, [
            'allowedGroups' => ['group-a', 42],
        ]);
        $this->assertEquals(422, $response->getStatusCode());
    }

    public function testTargetSlugIsUnique(): void
    {
        $this->getOrCreateDefaultTarget();

        $response = $this->request(KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID), 'POST', '/targets', [
            'slug' => 'test-default',
            'name' => 'Duplicate',
        ]);
        $this->assertEquals(422, $response->getStatusCode());
    }

    #[DataProvider('getInvalidSlugs')]
    public function testTargetSlugIsValidated(string $slug): void
    {
        $response = $this->request(KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID), 'POST', '/targets', [
            'slug' => $slug,
            'name' => 'Invalid slug',
        ]);
        $this->assertEquals(422, $response->getStatusCode());
        $this->assertSame('slug', $response->toArray(false)['violations'][0]['propertyPath']);
    }

    public static function getInvalidSlugs(): array
    {
        return [
            'trailing characters' => ['my-target!'],
            'space' => ['my target'],
            'trailing newline' => ["my-target\n"],
            'too long' => ['a'.str_repeat('b', 100)],
        ];
    }

    public function testTargetUrlMayBeAHostOfTheStack(): void
    {
        $response = $this->request(KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID), 'POST', '/targets', [
            'name' => 'Stack',
            'targetUrl' => 'http://databox-api/integrations/uploader/incoming-uploads',
        ]);
        $this->assertEquals(201, $response->getStatusCode());
    }

    public function testTargetNameIsRequired(): void
    {
        $response = $this->request(KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID), 'POST', '/targets', [
            'slug' => 'no-name',
        ]);
        $this->assertEquals(422, $response->getStatusCode());
    }

    public function testUserCannotCreateATarget(): void
    {
        $response = $this->request(KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::USER_UID), 'GET', '/me');
        $this->assertFalse($response->toArray()['permissions']['target']);
        $response = $this->request(KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID), 'GET', '/me');
        $this->assertTrue($response->toArray()['permissions']['target']);

        $response = $this->request(KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::USER_UID), 'POST', '/targets', [
            'slug' => 'forbidden',
            'name' => 'Forbidden',
        ]);
        $this->assertEquals(403, $response->getStatusCode());
    }

    private function createTargetWithSettings(string $token): string
    {
        $response = $this->request($token, 'POST', '/targets', [
            'name' => 'Pushed',
            'targetUrl' => 'https://example.com/upload',
            'defaultDestination' => 'my-collection',
            'authorizationScheme' => 'OAuth',
            'authorizationKey' => 'secret',
            'allowedGroups' => ['group-a', 'group-b'],
        ]);
        $this->assertEquals(201, $response->getStatusCode());

        return $response->toArray()['id'];
    }
}
