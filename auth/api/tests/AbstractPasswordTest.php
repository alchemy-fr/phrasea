<?php

declare(strict_types=1);

namespace App\Tests;

use App\Entity\ResetPasswordRequest;
use App\Entity\User;
use Symfony\Component\HttpFoundation\Response;

abstract class AbstractPasswordTest extends AbstractTestCase
{
    protected function assertPasswordResetRequestCount(int $count): void
    {
        $requests = self::getEntityManager()
            ->getRepository(ResetPasswordRequest::class)
            ->findAll();

        $this->assertEquals($count, count($requests));
    }

    protected function createResetPasswordRequest(string $username): ResetPasswordRequest
    {
        $em = self::getEntityManager();

        $user = $em->getRepository(User::class)->findOneBy(['username' => $username]);

        $request = new ResetPasswordRequest($user, 'the_token');

        $em->persist($request);
        $em->flush();

        return $request;
    }

    protected function assertPasswordIsInvalid(string $username, string $password): void
    {
        $response = $this->requestToken($username, $password);
        $this->assertEquals(400, $response->getStatusCode());
    }

    protected function assertPasswordIsValid(string $username, string $password): void
    {
        $response = $this->requestToken($username, $password);
        $this->assertEquals(200, $response->getStatusCode());
    }

    private function requestToken(string $username, string $password): Response
    {
        return $this->request(null, 'POST', '/oauth/v2/token', [
            'username' => $username,
            'password' => $password,
            'grant_type' => 'password',
            'client_id' => self::CLIENT_ID,
            'client_secret' => self::CLIENT_SECRET,
        ]);
    }
}
