<?php

declare(strict_types=1);

namespace App\Controller;

use App\Consumer\Handler\CommitAcknowledgeHandler;
use App\Entity\Commit;
use App\Security\Voter\CommitVoter;
use Arthem\Bundle\RabbitBundle\Consumer\Event\EventMessage;
use Arthem\Bundle\RabbitBundle\Producer\EventProducer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class CommitAckAction extends AbstractController
{
    public function __construct(private readonly EventProducer $eventProducer, private readonly EntityManagerInterface $em)
    {
    }

    public function __invoke(string $id)
    {
        $commit = $this->em->find(Commit::class, $id);
        if (null === $commit) {
            throw new NotFoundHttpException('Commit not found');
        }

        $this->denyAccessUnlessGranted(CommitVoter::ACK, $commit);

        if (!$commit->isAcknowledged()) {
            $this->eventProducer->publish(new EventMessage(CommitAcknowledgeHandler::EVENT, [
                'id' => $commit->getId(),
            ]));
        }

        return new JsonResponse(true);
    }
}
