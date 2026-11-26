<?php

declare(strict_types=1);

namespace App\Api\Processor;

use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use Alchemy\CoreBundle\Util\UuidUtil;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Consumer\Handler\Asset\AssetsDelete;
use App\Entity\Core\Asset;
use App\Security\AssetAttributeFilterChecker;
use App\Security\Voter\AbstractVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final class DeleteAssetsByKeysProcessor implements ProcessorInterface
{
    use SecurityAwareTrait;

    public function __construct(
        private readonly MessageBusInterface $bus,
        private readonly EntityManagerInterface $em,
        private readonly AssetAttributeFilterChecker $attributeFilterChecker,
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
        $workspaceId = UuidUtil::assertValid($workspaceId, 'workspaceId');

        foreach (array_chunk($keys, 50) as $chunk) {
            $assets = $this->em->getRepository(Asset::class)
                ->findByKeys($chunk, $workspaceId);
            $this->attributeFilterChecker->preloadForCurrentUser($assets);

            $ids = [];
            foreach ($assets as $asset) {
                // An asset the user may not read is left out as if it did not exist:
                // refusing it would tell whether the key is used.
                if ($this->isGranted(AbstractVoter::DELETE, $asset)) {
                    $ids[] = $asset->getId();
                } elseif ($this->isGranted(AbstractVoter::READ, $asset)) {
                    throw new AccessDeniedException(sprintf('Cannot delete asset "%s"', $asset->getId()));
                }
            }
            if (!empty($ids)) {
                $this->bus->dispatch(new AssetsDelete($ids));
            }

            $this->em->clear();
        }

        return null;
    }
}
