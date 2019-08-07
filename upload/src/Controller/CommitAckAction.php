<?php

declare(strict_types=1);

namespace App\Controller;

use App\Consumer\Handler\CommitAcknowledgeHandler;
use App\Consumer\Handler\CommitHandler;
use App\Form\FormValidator;
use App\Entity\Commit;
use App\Model\User;
use App\Security\Voter\CommitVoter;
use App\Storage\AssetManager;
use Arthem\Bundle\RabbitBundle\Consumer\Event\EventMessage;
use Arthem\Bundle\RabbitBundle\Producer\EventProducer;
use Doctrine\ORM\EntityManagerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\FrameworkBundle\Controller\ControllerTrait;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class CommitAckAction extends AbstractController
{
    use ControllerTrait;

    /**
     * @var EventProducer
     */
    private $eventProducer;

    /**
     * @var EntityManagerInterface
     */
    private $em;

    public function __construct(
        EventProducer $eventProducer,
        EntityManagerInterface $em
    ) {
        $this->eventProducer = $eventProducer;
        $this->em = $em;
    }

    public function __invoke(string $id)
    {
        $commit = $this->em->find(Commit::class, $id);
        if (null === $commit) {
            throw new NotFoundHttpException('Commit not found');
        }

        $this->denyAccessUnlessGranted(CommitVoter::ACK, $commit);

        $this->eventProducer->publish(new EventMessage(CommitAcknowledgeHandler::EVENT, [
            'id' => $commit->getId()
        ]));

        return new JsonResponse(true);
    }
}
