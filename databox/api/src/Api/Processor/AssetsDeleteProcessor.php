<?php

declare(strict_types=1);

namespace App\Api\Processor;

use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use Alchemy\CoreBundle\Util\DoctrineUtil;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Api\Model\Input\AssetsDeleteInput;
use App\Consumer\Handler\Asset\AssetsDelete;
use App\Entity\Core\Asset;
use App\Entity\Core\Collection;
use App\Repository\Core\AssetRepository;
use App\Security\Voter\AbstractVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;

class AssetsDeleteProcessor implements ProcessorInterface
{
    use SecurityAwareTrait;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AssetRepository $assetRepository,
        private readonly MessageBusInterface $bus,
    ) {
    }

    /**
     * @param AssetsDeleteInput $data
     */
    public function process($data, Operation $operation, array $uriVariables = [], array $context = []): Response
    {
        $collectionIds = $data->collections;
        $isUnlink = !empty($collectionIds);
        if ($isUnlink) {
            $collections = DoctrineUtil::iterateIds($this->em->getRepository(Collection::class), $collectionIds);
            foreach ($collections as $collection) {
                $this->denyAccessUnlessGranted(AbstractVoter::EDIT, $collection);
            }
        }

        if (!$isUnlink) {
            $assets = DoctrineUtil::iterateIds($this->em->getRepository(Asset::class), $data->ids);
            foreach ($assets as $asset) {
                $this->denyAccessUnlessGranted(AbstractVoter::DELETE, $asset);
            }
        }

        $this->bus->dispatch(new AssetsDelete($data->ids, $data->collections ?? [], $data->hardDelete));

        return new Response('', Response::HTTP_NO_CONTENT);
    }
}
