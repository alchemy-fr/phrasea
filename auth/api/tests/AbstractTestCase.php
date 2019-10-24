<?php

declare(strict_types=1);

namespace App\Tests;

use Alchemy\ApiTest\ApiTestCase;
use Hautelook\AliceBundle\PhpUnit\ReloadDatabaseTrait;
use Symfony\Component\BrowserKit\Client;
use Symfony\Component\HttpFoundation\Response;

abstract class AbstractTestCase extends ApiTestCase
{
    use ReloadDatabaseTrait;

    const CLIENT_ID = 'mobile-app_12356789abcdefghijklmnopqrstuvwx';
    const CLIENT_SECRET = 'cli3nt_s3cr3t';

    /**
     * @var Client
     */
    protected $client;

    /**
     * @return string The access token
     */
    protected function authenticateUser(string $email, string $password): string
    {
        $response = $this->request(null, 'POST', '/oauth/v2/token', [
            'username' => $email,
            'password' => $password,
            'grant_type' => 'password',
            'client_id' => self::CLIENT_ID,
            'client_secret' => self::CLIENT_SECRET,
        ]);
        $json = json_decode($response->getContent(), true);

        return $json['access_token'];
    }

    protected function assertNoCookie(Response $response): void
    {
        $this->assertNull($response->headers->get('Set-Cookie'));
    }

    protected function assertResponseToken(array $json, bool $refreshTokenExpected = true)
    {
        $this->assertRegExp('#^[a-zA-Z0-9]+$#', $json['access_token']);
        $this->assertArrayHasKey('scope', $json);
        $this->assertEquals('7776000', $json['expires_in']);
        $this->assertEquals('bearer', $json['token_type']);
        if ($refreshTokenExpected) {
            $this->assertRegExp('#^[a-zA-Z0-9]+$#', $json['refresh_token']);
        }
    }
}
