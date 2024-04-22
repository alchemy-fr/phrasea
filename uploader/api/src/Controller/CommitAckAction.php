<?php

declare(strict_types=1);

namespace App\Controller;

use App\Consumer\Handler\CommitAcknowledge;
use App\Entity\Commit;
use App\Security\Voter\CommitVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Messenger\MessageBusInterface;

final class CommitAckAction extends AbstractController
{
    public function __construct(
        private readonly MessageBusInterface $bus,
        private readonly EntityManagerInterface $em
    ) {
    }

    public function __invoke(string $id): JsonResponse
    {
        $commit = $this->em->find(Commit::class, $id);
        if (null === $commit) {
            throw new NotFoundHttpException('Commit not found');
        }

        $this->denyAccessUnlessGranted(CommitVoter::ACK, $commit);

        if (!$commit->isAcknowledged()) {
            $this->bus->dispatch(new CommitAcknowledge($commit->getId()));
        }

        return new JsonResponse(true);
    }
}
