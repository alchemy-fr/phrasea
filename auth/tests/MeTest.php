<?php

declare(strict_types=1);

namespace App\Tests;

use App\Entity\OAuthClient;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use OAuth2\OAuth2;

class MeTest extends ApiTestCase
{
    public function testMeOK(): void
    {
        $accessToken = $this->createAccessToken();

        $response = $this->request('GET', '/me', [
            'access_token' => $accessToken,
        ]);
        $this->assertEquals(200, $response->getStatusCode());

        $json = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('user_id', $json);
        $this->assertEquals('foo@bar.com', $json['email']);
    }

    public function testMeGenerates401WithInvalidAccessToken(): void
    {
        $response = $this->request('GET', '/me', [
            'access_token' => 'invalid_token',
        ]);
        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testMeGenerates401WithNoProvidedAccessToken(): void
    {
        $response = $this->request('GET', '/me');
        $this->assertEquals(401, $response->getStatusCode());
    }

    private function createAccessToken(): string
    {
        /** @var OAuth2 $oauth */
        $oauth = self::$container->get('fos_oauth_server.server');
        /** @var EntityManagerInterface $em */
        $em = self::$container->get(EntityManagerInterface::class);

        $client = $em
            ->getRepository(OAuthClient::class)
            ->find(explode('_', self::CLIENT_ID, 2)[0]);

        $user = $em
            ->getRepository(User::class)
            ->findOneBy([
                'email' => 'foo@bar.com',
            ]);

        $credential = $oauth->createAccessToken($client, $user, null, 3600);

        return $credential['access_token'];
    }
}
