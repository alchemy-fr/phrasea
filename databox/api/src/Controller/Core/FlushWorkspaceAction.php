<?php

declare(strict_types=1);

namespace App\Controller\Core;

use App\Consumer\Handler\Workspace\FlushWorkspaceHandler;
use App\Entity\Core\Workspace;
use App\Security\Voter\WorkspaceVoter;
use Arthem\Bundle\RabbitBundle\Producer\EventProducer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class FlushWorkspaceAction extends AbstractController
{
    private EventProducer $eventProducer;
    private EntityManagerInterface $em;

    public function __construct(EventProducer $eventProducer, EntityManagerInterface $em)
    {
        $this->eventProducer = $eventProducer;
        $this->em = $em;
    }

    public function __invoke(string $id)
    {
        $workspace = $this->em->find(Workspace::class, $id);

        if (!$workspace instanceof Workspace) {
            throw new NotFoundHttpException(sprintf('Workspace "%s" not found', $id));
        }

        $this->denyAccessUnlessGranted(WorkspaceVoter::EDIT, $workspace);

        $this->eventProducer->publish(FlushWorkspaceHandler::createEvent($workspace->getId()));

        return new Response('', 204);
    }
}
