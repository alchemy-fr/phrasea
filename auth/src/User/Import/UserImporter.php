<?php

declare(strict_types=1);

namespace App\User\Import;

use App\Entity\User;
use App\User\Import\Loader\UserImportLoaderInterface;
use App\User\UserManager;
use Doctrine\ORM\EntityManagerInterface;

class UserImporter
{
    /**
     * @var EntityManagerInterface
     */
    private $em;

    /**
     * @var UserManager
     */
    private $userManager;
    /**
     * @var UserImportLoaderInterface
     */
    private $userImporter;
    /**
     * @var int
     */
    private $batchSize;

    public function __construct(
        EntityManagerInterface $em,
        UserManager $userManager,
        UserImportLoaderInterface $userImporter,
        int $batchSize = 10
    )
    {
        $this->em = $em;
        $this->userManager = $userManager;
        $this->userImporter = $userImporter;
        $this->batchSize = $batchSize;
    }

    public function import(string $src): int
    {
        $this->em->getConnection()->getConfiguration()->setSQLLogger(null);

        $createUser = function (): User {
            return $this->userManager->createUser();
        };
        $resource = fopen($src, 'r');

        try {
            $this->em->beginTransaction();

            $users = $this->userImporter->import($resource, $createUser);
            $i = 0;
            foreach ($users as $user) {
                $this->em->persist($user);

                if ($i > 0 && $i % $this->batchSize === 0) {
                    $this->em->flush();
                    $this->em->clear();
                }
                $i++;
            }

            $this->em->flush();
            $this->em->commit();
        } catch (\Throwable $e) {
            $this->em->rollback();
            throw $e;
        }

        return $i;
    }
}
