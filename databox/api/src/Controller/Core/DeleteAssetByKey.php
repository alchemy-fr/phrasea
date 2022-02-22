<?php

declare(strict_types=1);

namespace App\Controller\Core;

use App\Api\Model\Input\DeleteAssetByKeyInput;
use App\Consumer\Handler\Asset\AssetDeleteHandler;
use App\Entity\Core\Asset;
use App\Security\Voter\AssetVoter;
use Arthem\Bundle\RabbitBundle\Producer\EventProducer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class DeleteAssetByKey extends AbstractController
{
    private EventProducer $eventProducer;
    private EntityManagerInterface $em;

    public function __construct(EventProducer $eventProducer, EntityManagerInterface $em)
    {
        $this->eventProducer = $eventProducer;
        $this->em = $em;
    }

    public function __invoke(Request $request)
    {
        $key = $request->request->get('key');
        $workspaceId = $request->request->get('workspaceId');
        if (!$key) {
            throw new BadRequestHttpException('Missing "key"');
        }
        if (!$workspaceId) {
            throw new BadRequestHttpException('Missing "workspace"');
        }

        $asset = $this->em->getRepository(Asset::class)
            ->findByKey($key, $workspaceId);
        ;
        if (!$asset instanceof Asset) {
            throw new NotFoundHttpException('Asset not found');
        }

        $this->denyAccessUnlessGranted(AssetVoter::DELETE, $asset);

        $this->eventProducer->publish(AssetDeleteHandler::createEvent($asset->getId()));

        return new Response('', 204);
    }
}
