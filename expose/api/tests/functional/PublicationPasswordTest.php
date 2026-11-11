<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use App\Security\PasswordSecurityMethodInterface;
use Symfony\Component\HttpFoundation\Response;

class PublicationPasswordTest extends AbstractExposeTestCase
{
    public function testGetPublicationWithPassword(): void
    {
        $id = $this->createPublication([
            'password' => 'xxx',
        ])->getId();

        $response = $this->request(null, 'GET', '/publications/'.$id);
        $json = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertArrayHasKey('authorized', $json);
        $this->assertFalse($json['authorized']);
        $this->assertArrayHasKey('id', $json);
        $this->assertArrayHasKey('title', $json);
        $this->assertArrayNotHasKey('assets', $json);

        $passwords = base64_encode(json_encode([
            $id => 'xxx',
        ], JSON_THROW_ON_ERROR));
        $response = $this->request(null, 'GET', '/publications/'.$id, [], [], [
            'HTTP_X-Passwords' => $passwords,
        ]);
        $json = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        if (200 !== $response->getStatusCode()) {
            dump($response->getContent());
        }
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertTrue($json['authorized']);

        // Test invalid password
        $passwords = base64_encode(json_encode([
            $id => 'aaa',
        ], JSON_THROW_ON_ERROR));
        $response = $this->request(null, 'GET', '/publications/'.$id, [], [], [
            'HTTP_X-Passwords' => $passwords,
        ]);
        $json = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertFalse($json['authorized']);
        $this->assertEquals(PasswordSecurityMethodInterface::ERROR_INVALID_PASSWORD, $json['authorizationError']);
    }

    public function testMalformedPasswordsAreRefused(): void
    {
        $id = $this->createPublication([
            'password' => 'xxx',
        ])->getId();

        foreach ([42, [$id => ['xxx']], [$id => 42]] as $passwords) {
            $response = $this->request(null, 'GET', '/publications/'.$id, [], [], [
                'HTTP_X-Passwords' => base64_encode(json_encode($passwords, JSON_THROW_ON_ERROR)),
            ]);
            $this->assertEquals(200, $response->getStatusCode());
            $json = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
            $this->assertFalse($json['authorized']);
        }
    }

    public function testGetPublicationWithPasswordAsAdmin(): void
    {
        $id = $this->createPublication([
            'password' => 'xxx',
        ])->getId();

        $response = $this->request(KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID), 'GET', '/publications/'.$id);
        $json = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertArrayHasKey('authorized', $json);
        $this->assertTrue($json['authorized']);
        $this->assertArrayHasKey('id', $json);
        $this->assertArrayHasKey('title', $json);
        $this->assertArrayHasKey('assets', $json);
    }

    public function testGetNestedPublicationWithPasswordOnRootNode(): void
    {
        $rootId = $this->createPublication([
            'password' => 'root_secret',
        ])->getId();
        $childId = $this->createPublication([
            'parent_id' => $rootId,
        ])->getId();

        $response = $this->request(null, 'GET', '/publications/'.$rootId);
        $this->assertEquals(200, $response->getStatusCode());
        $json = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertFalse($json['authorized']);
        $this->assertEquals($rootId, $json['securityContainerId']);

        $response = $this->request(null, 'GET', '/publications/'.$childId);
        $this->assertEquals(200, $response->getStatusCode());
        $json = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertFalse($json['authorized']);
        $this->assertEquals($rootId, $json['securityContainerId']);

        $passwords = base64_encode(json_encode([
            $rootId => 'root_secret',
        ], JSON_THROW_ON_ERROR));
        $response = $this->request(null, 'GET', '/publications/'.$childId, [], [], [
            'HTTP_X-Passwords' => $passwords,
        ]);
        $json = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertTrue($json['authorized']);

        $response = $this->request(null, 'GET', '/publications/'.$rootId, [], [], [
            'HTTP_X-Passwords' => $passwords,
        ]);
        $json = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertTrue($json['authorized']);
    }

    public function testGetNestedPublicationWithPasswordOnChildNode(): void
    {
        $rootId = $this->createPublication()->getId();
        $childId = $this->createPublication([
            'parent_id' => $rootId,
            'password' => 'child_secret',
        ])->getId();

        $response = $this->request(null, 'GET', '/publications/'.$rootId);
        $this->assertEquals(200, $response->getStatusCode());
        $json = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertTrue($json['authorized']);
        $this->assertEquals($rootId, $json['securityContainerId']);

        $response = $this->request(null, 'GET', '/publications/'.$childId);
        $this->assertEquals(200, $response->getStatusCode());
        $json = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertFalse($json['authorized']);
        $this->assertEquals($childId, $json['securityContainerId']);

        $passwords = base64_encode(json_encode([
            $childId => 'child_secret',
        ], JSON_THROW_ON_ERROR));
        $response = $this->request(null, 'GET', '/publications/'.$childId, [], [], [
            'HTTP_X-Passwords' => $passwords,
        ]);
        $json = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertTrue($json['authorized']);

        $response = $this->request(null, 'GET', '/publications/'.$rootId, [], [], [
            'HTTP_X-Passwords' => $passwords,
        ]);
        $json = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertTrue($json['authorized']);
    }

    public function testGetNestedPublicationWithPasswordOnBothNode(): void
    {
        $rootId = $this->createPublication([
            'password' => 'root_secret',
        ])->getId();
        $childId = $this->createPublication([
            'parent_id' => $rootId,
            'password' => 'child_secret',
        ])->getId();

        $passwords = base64_encode(json_encode([
            $rootId => 'root_secret',
        ], JSON_THROW_ON_ERROR));
        $response = $this->request(null, 'GET', '/publications/'.$rootId, [], [], [
            'HTTP_X-Passwords' => $passwords,
        ]);
        $json = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertTrue($json['authorized']);
        $this->assertEquals($rootId, $json['securityContainerId']);

        $response = $this->request(null, 'GET', '/publications/'.$childId, [], [], [
            'HTTP_X-Passwords' => $passwords,
        ]);
        $json = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertFalse($json['authorized']);
        $this->assertEquals($childId, $json['securityContainerId']);

        $passwords = base64_encode(json_encode([
            $childId => 'child_secret',
        ], JSON_THROW_ON_ERROR));
        $response = $this->request(null, 'GET', '/publications/'.$childId, [], [], [
            'HTTP_X-Passwords' => $passwords,
        ]);
        $json = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertTrue($json['authorized']);

        $response = $this->request(null, 'GET', '/publications/'.$rootId, [], [], [
            'HTTP_X-Passwords' => $passwords,
        ]);
        $json = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertFalse($json['authorized']);
    }

    public function testWrongPasswordsLockTheSecurityContainer(): void
    {
        $id = $this->createPublication(['password' => 'xxx'])->getId();
        $otherId = $this->createPublication(['password' => 'yyy'])->getId();

        // A valid password is never counted.
        for ($i = 0; $i < 12; ++$i) {
            $this->assertTrue($this->getWithPasswords($id, [$id => 'xxx'])['authorized']);
        }

        // A wrong password counts once per request.
        for ($i = 0; $i < 10; ++$i) {
            $json = $this->getWithPasswords($id, [$id => 'wrong']);
            $this->assertFalse($json['authorized']);
            $this->assertSame(PasswordSecurityMethodInterface::ERROR_INVALID_PASSWORD, $json['authorizationError'], 'Attempt '.($i + 1));
        }

        $json = $this->getWithPasswords($id, [$id => 'xxx']);
        $this->assertFalse($json['authorized']);
        $this->assertSame(PasswordSecurityMethodInterface::ERROR_TOO_MANY_ATTEMPTS, $json['authorizationError']);

        $json = $this->getWithPasswords($otherId, [$otherId => 'yyy']);
        $this->assertTrue($json['authorized']);
    }

    public function testEditorsNeverReadThePassword(): void
    {
        $adminToken = KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID);
        $profileId = $this->createProfile(['password' => 'profile_secret']);
        $rootId = $this->createPublication(['password' => 'root_secret'])->getId();
        $childId = $this->createPublication(['parent_id' => $rootId, 'password' => 'child_secret'])->getId();
        $publicId = $this->createPublication()->getId();
        $this->clearEmBeforeApiCall();

        foreach ([
            '/publications/'.$rootId,
            '/publications/'.$childId,
            '/publication-profiles/'.$profileId,
        ] as $uri) {
            $response = $this->request($adminToken, 'GET', $uri);
            $this->assertSame(200, $response->getStatusCode(), $uri);
            $this->assertStringNotContainsString('_secret', (string) $response->getContent(), $uri);
            $this->assertStringNotContainsString('$2y$', (string) $response->getContent(), $uri);
            $json = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
            $this->assertTrue($json['config']['hasPassword'], $uri);
            $this->assertArrayNotHasKey('password', $json['config']['securityOptions'], $uri);
        }

        $json = $this->getJson('/publications/'.$publicId, $adminToken);
        $this->assertFalse($json['config']['hasPassword']);

        $stored = self::getEntityManager()->getConnection()->fetchOne('SELECT config_security_options FROM publication WHERE id = :id', ['id' => $rootId]);
        $this->assertStringNotContainsString('root_secret', $stored);
        $this->assertStringContainsString('password', $stored);
    }

    public function testEditingKeepsThePasswordUnlessANewOneIsGiven(): void
    {
        $adminToken = KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID);
        $id = $this->createPublication(['password' => 'xxx'])->getId();
        $this->clearEmBeforeApiCall();

        $this->patchConfig($id, [
            'enabled' => true,
            'securityMethod' => 'password',
            'securityOptions' => [],
        ]);
        $this->assertTrue($this->getWithPasswords($id, [$id => 'xxx'])['authorized']);

        $this->patchConfig($id, [
            'securityOptions' => ['password' => 'new_one'],
        ]);
        $this->assertFalse($this->getWithPasswords($id, [$id => 'xxx'])['authorized']);
        $this->assertTrue($this->getWithPasswords($id, [$id => 'new_one'])['authorized']);
        $this->assertTrue($this->getJson('/publications/'.$id, $adminToken)['config']['hasPassword']);

        // Leaving the password method drops the password.
        $this->patchConfig($id, [
            'securityMethod' => 'authentication',
        ]);
        $this->assertFalse($this->getJson('/publications/'.$id, $adminToken)['config']['hasPassword']);
    }

    public function testInheritedPasswordIsCheckedOnTheContainer(): void
    {
        $rootId = $this->createPublication(['password' => 'root_secret'])->getId();
        $childId = $this->createPublication(['parent_id' => $rootId])->getId();

        $json = $this->getWithPasswords($childId, [$rootId => 'root_secret']);
        $this->assertTrue($json['authorized']);
        $this->assertSame($rootId, $json['securityContainerId']);

        $json = $this->getWithPasswords($childId, [$rootId => 'wrong', $childId => 'root_secret']);
        $this->assertFalse($json['authorized']);
        $this->assertSame(PasswordSecurityMethodInterface::ERROR_INVALID_PASSWORD, $json['authorizationError']);
    }

    private function patchConfig(string $id, array $config): void
    {
        $response = $this->request(KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID), 'PATCH', '/publications/'.$id, [
            'config' => $config,
        ]);
        $this->assertSame(200, $response->getStatusCode(), $this->describe($response));
    }

    private function getWithPasswords(string $id, array $passwords): array
    {
        $response = $this->request(null, 'GET', '/publications/'.$id, [], [], [
            'HTTP_X-Passwords' => base64_encode(json_encode($passwords, JSON_THROW_ON_ERROR)),
        ]);
        $this->assertSame(200, $response->getStatusCode(), $this->describe($response));

        return json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
    }

    private function getJson(string $uri, string $accessToken): array
    {
        $response = $this->request($accessToken, 'GET', $uri);
        $this->assertSame(200, $response->getStatusCode(), $this->describe($response));

        return json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
    }

    private function describe(Response $response): string
    {
        return substr((string) $response->getContent(), 0, 500);
    }
}
