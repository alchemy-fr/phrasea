<?php

declare(strict_types=1);

namespace App\User;

use App\Entity\Core\UserPreference;
use Doctrine\ORM\EntityManagerInterface;

class UserPreferencesManager
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function updatePreferences(string $userId, string $name, $value): UserPreference
    {
        $userPref = $this->getPreferences($userId);

        $data = $userPref->getData();
        $data[$name] = $value;
        $userPref->setData($data);

        $this->em->persist($userPref);
        $this->em->flush();

        return $userPref;
    }

    public function getPreferences(string $userId): UserPreference
    {
        $userPref = $this->em->getRepository(UserPreference::class)
            ->findOneBy([
                'userId' => $userId,
            ]);

        if (null === $userPref) {
            $userPref = new UserPreference();
            $userPref->setUserId($userId);
        }

        return $userPref;
    }
}
