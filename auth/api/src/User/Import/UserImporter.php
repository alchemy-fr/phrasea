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
    public function __construct(private readonly EntityManagerInterface $em, private readonly UserManager $userManager, private readonly UserImportLoaderInterface $userImporter, private readonly ValidatorInterface $validator, private readonly EventProducer $eventProducer, private readonly int $batchSize = 20)
    {
    }

    /**
     * @param string|resource $src
     */
    public function import($src, bool $inviteUsers = false, array &$violations = []): int
    {
        $this->em->getConnection()->getConfiguration()->setSQLLogger(null);

        $createUser = fn (): User => $this->userManager->createUser();
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
                        $violations[] = sprintf('Error at row #%d: %s', $i + 2, $userViolation->getMessage());
                    }
                    continue;
                }

                $this->em->persist($user);

                if ($i > 0 && 0 === $i % $this->batchSize) {
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
