<?php

declare(strict_types=1);

namespace App\Controller\Core;

use Alchemy\CoreBundle\Util\DoctrineUtil;
use App\Consumer\Handler\Asset\AssetDelete;
use App\Entity\Core\Asset;
use App\Entity\Core\Collection;
use App\Security\Voter\AbstractVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Messenger\MessageBusInterface;

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

        $collectionIds = $request->request->all('collections');
        $hardDelete = empty($collectionIds);
        if (!$hardDelete) {
            $collections = DoctrineUtil::iterateIds($this->em->getRepository(Collection::class), $collectionIds);
            foreach ($collections as $collection) {
                $this->denyAccessUnlessGranted(AbstractVoter::EDIT, $collection);
            }
        }

        if ($hardDelete) {
            $assets = DoctrineUtil::iterateIds($this->em->getRepository(Asset::class), $ids);
            $ids = [];
            foreach ($assets as $asset) {
                if ($this->isGranted(AbstractVoter::DELETE, $asset)) {
                    $ids[] = $asset->getId();
                }
            }
        }

        $this->bus->dispatch(new AssetDelete($ids, $collectionIds));

        return new Response('', 204);
    }
}
