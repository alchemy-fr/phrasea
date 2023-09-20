<?php

declare(strict_types=1);

namespace App\Controller\Core;

use App\Consumer\Handler\Asset\AssetDeleteHandler;
use App\Entity\Core\Asset;
use App\Security\Voter\AbstractVoter;
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

    public function __invoke(Request $request): Response
    {
        $ids = $request->request->all('ids');
        if (empty($ids)) {
            throw new BadRequestHttpException('Missing "ids"');
        }

        $assets = $this->em->getRepository(Asset::class)
            ->findByIds($ids);

        foreach ($assets as $asset) {
            $this->denyAccessUnlessGranted(AbstractVoter::DELETE, $asset);
            $this->eventProducer->publish(AssetDeleteHandler::createEvent($asset->getId()));
        }

        return new Response('', 204);
    }
}
