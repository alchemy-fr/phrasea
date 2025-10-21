<?php

declare(strict_types=1);

namespace App\Api\Processor;

use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use Alchemy\CoreBundle\Util\DoctrineUtil;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Api\Model\Input\CollectionsRestoreInput;
use App\Consumer\Handler\Collection\CollectionsRestore;
use App\Entity\Core\Collection;
use App\Repository\Core\AssetRepository;
use App\Security\Voter\AbstractVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;

class CollectionsRestoreProcessor implements ProcessorInterface
{
    use SecurityAwareTrait;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AssetRepository $assetRepository,
        private readonly MessageBusInterface $bus,
    ) {
    }

    /**
     * @param CollectionsRestoreInput $data
     */
    public function process($data, Operation $operation, array $uriVariables = [], array $context = []): Response
    {
        $collections = DoctrineUtil::iterateIds($this->em->getRepository(Collection::class), $data->ids);
        foreach ($collections as $collection) {
            $this->denyAccessUnlessGranted(AbstractVoter::DELETE, $collection);
        }

        $this->bus->dispatch(new CollectionsRestore($data->ids));

        return new Response('', Response::HTTP_NO_CONTENT);
    }
}
