<?php

declare(strict_types=1);

namespace App\Controller\Core;

use App\Api\Model\Input\AssetGenerateRenditionsInput;
use App\Consumer\Handler\File\GenerateAssetRenditionsHandler;
use App\Entity\Core\Asset;
use App\Security\Voter\AssetVoter;
use Arthem\Bundle\RabbitBundle\Consumer\Event\EventMessage;
use Arthem\Bundle\RabbitBundle\Producer\EventProducer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Generate renditions of an asset.
 */
final class GenerateRenditionsAction extends AbstractController
{
    private EventProducer $eventProducer;
    private EntityManagerInterface $em;

    public function __construct(EventProducer $eventProducer, EntityManagerInterface $em)
    {
        $this->eventProducer = $eventProducer;
        $this->em = $em;
    }

    public function __invoke(string $id, AssetGenerateRenditionsInput $action, Request $request)
    {
        $asset = $this->em->find(Asset::class, $id);
        if (!$asset instanceof Asset) {
            throw new NotFoundHttpException('Asset not found');
        }

        $this->denyAccessUnlessGranted(AssetVoter::EDIT, $asset);

        $this->eventProducer->publish(new EventMessage(GenerateAssetRenditionsHandler::EVENT, [
            'id' => $id,
            'renditions' => $action->renditions,
        ]));

        return new Response();
    }
}
