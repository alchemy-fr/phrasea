<?php

declare(strict_types=1);

namespace App\Tests;

use App\Entity\ResetPasswordRequest;
use App\Entity\User;

class ResetPasswordTest extends ApiTestCase
{
    public function testResetPasswordOK(): void
    {
        $request = $this->createResetPasswordRequest('foo@bar.com');

        $uri = sprintf(
            '/password/reset/%s/%s',
            $request->getId(),
            $request->getToken()
        );
        $this->client->request('GET', $uri, [
            'email' => 'foo@bar.com',
        ]);
        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());

        $this->client->submitForm('Reset password', [
            'reset_password_form[new_password][first]' => 'new_secret',
            'reset_password_form[new_password][second]' => 'new_secret',
        ]);

        $this->assertTrue(
            $this->client->getResponse()->isRedirect('/password/reset/changed')
        );

        $this->assertPasswordIsInvalid('foo@bar.com', 'secret');
        $this->assertPasswordIsValid('foo@bar.com', 'new_secret');
    }

    private function createResetPasswordRequest(string $userEmail): ResetPasswordRequest
    {
        $em = self::getEntityManager();

        $user = $em->getRepository(User::class)->findOneBy(['email' => $userEmail]);

        $request = new ResetPasswordRequest($user, 'the_token');

        $em->persist($request);
        $em->flush();

        return $request;
    }
}
