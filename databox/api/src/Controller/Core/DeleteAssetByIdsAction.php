<?php

declare(strict_types=1);

namespace App\Controller\Core;

use App\Consumer\Handler\Asset\AssetDelete;
use App\Consumer\Handler\Asset\AssetDeleteHandler;
use App\Entity\Core\Asset;
use App\Security\Voter\AbstractVoter;
use Symfony\Component\Messenger\MessageBusInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class DeleteAssetByIdsAction extends AbstractController
{
    public function __construct(private readonly MessageBusInterface $bus, private readonly EntityManagerInterface $em)
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
            $this->bus->dispatch(new AssetDelete($asset->getId()));
        }

        return new Response('', 204);
    }
}
