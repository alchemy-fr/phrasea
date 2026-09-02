<?php

declare(strict_types=1);

namespace App\Api\Provider;

use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Api\Model\Output\AssetDuplicateOutput;
use App\Entity\Core\File;
use App\Repository\Core\FileRepository;
use App\Security\Voter\AbstractVoter;
use App\Service\Asset\DuplicateAssetResolver;

/**
 * Resolves the duplicates of a file into assets so the client can display
 * them with their thumbnail (file info modal).
 */
final class FileDuplicatesProvider implements ProviderInterface
{
    use SecurityAwareTrait;

    public function __construct(
        private readonly FileRepository $fileRepository,
        private readonly DuplicateAssetResolver $duplicateAssetResolver,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ?AssetDuplicateOutput
    {
        $file = $this->fileRepository->find($uriVariables['id']);
        if (!$file instanceof File) {
            return null;
        }
        $this->denyAccessUnlessGranted(AbstractVoter::READ, $file);

        return new AssetDuplicateOutput($this->duplicateAssetResolver->resolveDuplicates($file));
    }
}
