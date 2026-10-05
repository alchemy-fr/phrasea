<?php

declare(strict_types=1);

namespace App\Api\Processor;

use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use ApiPlatform\Validator\ValidatorInterface;
use App\Api\Provider\ShareReadProvider;
use App\Entity\Core\Share;
use App\Security\Voter\AssetVoter;
use Doctrine\ORM\PersistentCollection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class ShareProcessor implements ProcessorInterface
{
    use SecurityAwareTrait;
    use WithOwnerIdProcessorTrait;

    public function __construct(
        private readonly ShareReadProvider $shareReadProvider,
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private readonly ProcessorInterface $decorated,
        private readonly ValidatorInterface $validator,
    ) {
    }

    /**
     * @param Share $data
     */
    public function process($data, Operation $operation, array $uriVariables = [], array $context = []): Share
    {
        $assets = $data->getAssetsList();
        if (empty($assets)) {
            throw new BadRequestHttpException('A share must contain at least one asset');
        }

        $workspaceId = $assets[0]->getWorkspaceId();
        foreach ($assets as $asset) {
            if ($asset->getWorkspaceId() !== $workspaceId) {
                throw new BadRequestHttpException('All shared assets must belong to the same workspace');
            }
        }

        // On update, EDIT is granted on the share before the payload is read:
        // the assets it adds must be readable and shareable, as on creation.
        $collection = $data->getAssets();
        $addedAssets = $collection instanceof PersistentCollection ? $collection->getInsertDiff() : $assets;
        foreach ($addedAssets as $asset) {
            if (!$this->isGranted(AssetVoter::READ, $asset) || !$this->isGranted(AssetVoter::SHARE, $asset)) {
                throw new AccessDeniedHttpException(sprintf('You are not allowed to share asset "%s"', $asset->getId()));
            }
        }

        $item = $this->decorated->process($this->processOwnerId($data), $operation, $uriVariables, $context);
        $this->validator->validate($item);

        return $this->shareReadProvider->provideShare($item);
    }
}
