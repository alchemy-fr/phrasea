<?php

declare(strict_types=1);

namespace App\Tests\Admin;

use Alchemy\AdminBundle\Tests\AbstractAdminTest;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class AdminTest extends AbstractAdminTest
{
    public function testAdmin()
    {
        $this->doTestAllPages();
    }

    protected function getAuthAdminUser(): UserInterface
    {
        $user = new User();
        $user->setId('f70352d6-0b28-4c2e-82a4-d6c2b591b816');
        $user->setEnabled(true);
        $user->setSalt('salt');
        $user->setEmail('admin@test.org');
        $user->setUserRoles([
            'ROLE_SUPER_ADMIN',
        ]);
        /** @var EntityManagerInterface $em */
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->persist($user);
        $em->flush();

        return $user;
    }
}
