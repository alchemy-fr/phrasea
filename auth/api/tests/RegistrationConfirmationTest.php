<?php

declare(strict_types=1);

namespace App\Tests;

use App\Entity\User;
use App\User\UserManager;
use Hautelook\AliceBundle\PhpUnit\ReloadDatabaseTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class RegistrationConfirmationTest extends WebTestCase
{
    use ReloadDatabaseTrait;

    public function testRegistrationConfirmationSuccess(): void
    {
        $client = static::createClient();
        $user = $this->createUser();

        $client->request('GET', sprintf(
            '/en/security/register/confirm/%s/%s',
            $user->getId(),
            $user->getSecurityToken()
        ));
        $client->followRedirect();

        $this->assertEquals(200, $client->getResponse()->getStatusCode());
        $this->assertStringContainsString(
            'Registration complete!',
            $client->getResponse()->getContent()
        );
    }

    public function testRegistrationConfirmationWithInvalidToken(): void
    {
        $client = static::createClient();
        $user = $this->createUser();

        $client->request('GET', sprintf(
            '/en/security/register/confirm/%s/%s',
            $user->getId(),
            'invalid_token'
        ));

        $this->assertEquals(400, $client->getResponse()->getStatusCode());
    }

    public function testRegistrationConfirmationWithInvalidUserId(): void
    {
        $client = static::createClient();
        $user = $this->createUser();

        $client->request('GET', sprintf(
            '/en/security/register/confirm/%s/%s',
            'invalid_user_id',
            $user->getSecurityToken()
        ));

        $this->assertEquals(400, $client->getResponse()->getStatusCode());
    }

    private function createUser(): User
    {
        $userManager = self::$container->get(UserManager::class);
        $user = $userManager->createUser();
        $user->setUsername('test@confirm.com');
        $user->setPlainPassword('secret');
        $userManager->encodePassword($user);
        $userManager->persistUser($user);

        return $user;
    }
}
