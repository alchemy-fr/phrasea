<?php

declare(strict_types=1);

namespace App\Api\Processor;

use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use Alchemy\CoreBundle\Util\DoctrineUtil;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Api\Model\Input\AssetsRestoreInput;
use App\Consumer\Handler\Asset\AssetsRestore;
use App\Entity\Core\Asset;
use App\Repository\Core\AssetRepository;
use App\Security\Voter\AbstractVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;

class AssetsRestoreProcessor implements ProcessorInterface
{
    use SecurityAwareTrait;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AssetRepository $assetRepository,
        private readonly MessageBusInterface $bus,
    ) {
    }

    /**
     * @param AssetsRestoreInput $data
     */
    public function process($data, Operation $operation, array $uriVariables = [], array $context = []): Response
    {
        $assets = DoctrineUtil::iterateIds($this->em->getRepository(Asset::class), $data->ids);
        foreach ($assets as $asset) {
            $this->denyAccessUnlessGranted(AbstractVoter::DELETE, $asset);
        }

        $this->bus->dispatch(new AssetsRestore($data->ids));

        return new Response('', Response::HTTP_NO_CONTENT);
    }
}
