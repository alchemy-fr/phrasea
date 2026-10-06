<?php

declare(strict_types=1);

namespace App\Api\Processor;

use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Consumer\Handler\Asset\AssetsDelete;
use App\Entity\Core\Asset;
use App\Security\Voter\AbstractVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Messenger\MessageBusInterface;

final class DeleteAssetsByKeysProcessor implements ProcessorInterface
{
    use SecurityAwareTrait;

    public function __construct(
        private readonly MessageBusInterface $bus,
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): null
    {
        $request = $context['request'];
        $keys = $request->request->all('keys');
        if (!$keys) {
            throw new BadRequestHttpException('Missing "keys"');
        }
        $workspaceId = $request->request->get('workspaceId');
        if (!$workspaceId) {
            throw new BadRequestHttpException('Missing "workspace"');
        }

        foreach (array_chunk($keys, 50) as $chunk) {
            $assets = $this->em->getRepository(Asset::class)
                ->findByKeys($chunk, $workspaceId);

            $ids = [];
            foreach ($assets as $asset) {
                $this->denyAccessUnlessGranted(AbstractVoter::DELETE, $asset);
                $ids[] = $asset->getId();
            }
            $this->bus->dispatch(new AssetsDelete($ids));

            $this->em->clear();
        }

        return null;
    }
}
