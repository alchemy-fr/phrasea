<?php

declare(strict_types=1);

namespace App\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Asset;
use App\Security\Voter\AssetVoter;
use App\Storage\AssetManager;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final readonly class DeleteAssetsByAssetIdProcessor implements ProcessorInterface
{
    public function __construct(
        private AssetManager $assetManager,
        private Security $security,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): null
    {
        /** @var Request $request */
        $request = $context['request'];

        $this->assetManager->deleteByAssetId(
            (string) $request->attributes->get('assetId'),
            function (Asset $asset): bool {
                if ($this->security->isGranted(AssetVoter::DELETE, $asset)) {
                    return true;
                }

                // An asset the user may not read is left out as if it did not exist:
                // refusing it would tell whether the asset is published elsewhere.
                if ($this->security->isGranted(AssetVoter::READ, $asset)) {
                    throw new AccessDeniedHttpException(sprintf('Cannot delete asset "%s"', $asset->getId()));
                }

                return false;
            },
        );

        return null;
    }
}
