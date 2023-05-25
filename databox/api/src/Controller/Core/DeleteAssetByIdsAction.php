<?php

declare(strict_types=1);

namespace App\Controller\Core;

use App\Consumer\Handler\Asset\AssetDeleteHandler;
use App\Entity\Core\Asset;
use App\Security\Voter\AssetVoter;
use Arthem\Bundle\RabbitBundle\Producer\EventProducer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class DeleteAssetByIdsAction extends AbstractController
{
    public function __construct(private readonly EventProducer $eventProducer, private readonly EntityManagerInterface $em)
    {
    }

    public function __invoke(Request $request)
    {
        /** @var array $ids */
        $ids = $request->request->get('ids');
        if (!$ids) {
            throw new BadRequestHttpException('Missing "ids"');
        }

        $assets = $this->em->getRepository(Asset::class)
            ->findByIds($ids);

        foreach ($assets as $asset) {
            $this->denyAccessUnlessGranted(AssetVoter::DELETE, $asset);
            $this->eventProducer->publish(AssetDeleteHandler::createEvent($asset->getId()));
        }

        return new Response('', 204);
    }
}
