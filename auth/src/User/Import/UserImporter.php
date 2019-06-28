<?php

declare(strict_types=1);

namespace App\User\Import;

use App\Consumer\Handler\InviteUsersHandler;
use App\Entity\User;
use App\User\Import\Loader\UserImportLoaderInterface;
use App\User\UserManager;
use Arthem\Bundle\RabbitBundle\Consumer\Event\EventMessage;
use Arthem\Bundle\RabbitBundle\Producer\EventProducer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

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

    /**
     * @var ValidatorInterface
     */
    private $validator;

    /**
     * @var EventProducer
     */
    private $eventProducer;

    public function __construct(
        EntityManagerInterface $em,
        UserManager $userManager,
        UserImportLoaderInterface $userImporter,
        ValidatorInterface $validator,
        EventProducer $eventProducer,
        int $batchSize = 20
    )
    {
        $this->em = $em;
        $this->userManager = $userManager;
        $this->userImporter = $userImporter;
        $this->batchSize = $batchSize;
        $this->validator = $validator;
        $this->eventProducer = $eventProducer;
    }

    /**
     * @param string|resource $src
     */
    public function import($src, bool $inviteUsers = false, array &$violations = []): int
    {
        $this->em->getConnection()->getConfiguration()->setSQLLogger(null);

        $createUser = function (): User {
            return $this->userManager->createUser();
        };
        if (is_string($src)) {
            $resource = fopen($src, 'r');
        } else {
            $resource = $src;
        }

        try {
            $this->em->beginTransaction();

            /** @var User[] $users */
            $users = $this->userImporter->import($resource, $createUser);
            $i = -1;
            $bkUsers = [];
            foreach ($users as $user) {
                ++$i;
                $bkUsers[] = $user;
                /** @var ConstraintViolation[]|ConstraintViolationList $userViolations */
                $userViolations = $this->validator->validate($user);
                if ($userViolations->count() > 0) {
                    foreach ($userViolations as $userViolation) {
                        $violations[] = sprintf('Error at row #%d: %s', $i+2, $userViolation->getMessage());
                    }
                    continue;
                }

                $this->em->persist($user);

                if ($i > 0 && $i % $this->batchSize === 0) {
                    $this->em->flush();
                    $this->em->clear();
                }
            }

            if (!empty($violations)) {
                $this->em->rollback();
                return 0;
            }

            $this->em->flush();
            $this->em->commit();
        } catch (\Throwable $e) {
            $this->em->rollback();
            throw $e;
        }

        if ($inviteUsers) {
            $ids = [];
            foreach ($bkUsers as $user) {
                $ids[] = $user->getId();
            }
            $this->inviteUsers($ids);
        }

        return $i + 1;
    }

    private function inviteUsers(array $ids): void
    {
        $this->eventProducer->publish(new EventMessage(InviteUsersHandler::EVENT, [
            'user_ids' => $ids,
        ]));
    }
}
