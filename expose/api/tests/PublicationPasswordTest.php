<?php

declare(strict_types=1);

namespace App\Tests;

use Alchemy\AuthBundle\Tests\Client\OAuthClientTestMock;
use App\Security\PasswordSecurityMethodInterface;

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

    public function testGetPublicationWithPasswordAsAdmin(): void
    {
        $id = $this->createPublication([
            'password' => 'xxx',
        ])->getId();

        $response = $this->request(OAuthClientTestMock::ADMIN_TOKEN, 'GET', '/publications/'.$id);
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
}
