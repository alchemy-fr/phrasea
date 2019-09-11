<?php

declare(strict_types=1);

namespace App\Tests;

use App\Entity\ResetPasswordRequest;
use App\Entity\User;

abstract class AbstractPasswordTest extends ApiTestCase
{
    protected function assertPasswordResetRequestCount(int $count): void
    {
        $requests = self::getEntityManager()
            ->getRepository(ResetPasswordRequest::class)
            ->findAll();

        $this->assertEquals($count, count($requests));
    }

    protected function createResetPasswordRequest(string $userEmail): ResetPasswordRequest
    {
        $em = self::getEntityManager();

        $user = $em->getRepository(User::class)->findOneBy(['email' => $userEmail]);

        $request = new ResetPasswordRequest($user, 'the_token');

        $em->persist($request);
        $em->flush();

        return $request;
    }
}
