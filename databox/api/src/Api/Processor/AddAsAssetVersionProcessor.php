<?php

declare(strict_types=1);

namespace App\Api\Processor;

use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use Alchemy\CoreBundle\Util\DoctrineUtil;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Api\Model\Input\AssetAddAsVersionInput;
use App\Doctrine\Delete\AssetDelete;
use App\Entity\Core\Asset;
use App\Security\Voter\AbstractVoter;
use App\Security\Voter\AssetVoter;
use App\Service\Asset\AssetManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Attaches a quarantined asset's source file to an existing (duplicate) asset
 * as its new current source. The existing asset's previous source becomes a
 * file version, and the quarantined asset is removed.
 */
final class AddAsAssetVersionProcessor implements ProcessorInterface
{
    use SecurityAwareTrait;

    private const string DOC_UNIQUE_ID_ANALYZER = 'doc_unique_id';

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AssetManager $assetManager,
        private readonly AssetDelete $assetDelete,
    ) {
    }

    /**
     * @param AssetAddAsVersionInput $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Asset
    {
        $quarantined = DoctrineUtil::findStrict($this->em, Asset::class, $uriVariables['id']);
        $this->denyAccessUnlessGranted(AssetVoter::QUARANTINE_BYPASS, $quarantined);

        $target = DoctrineUtil::findStrict($this->em, Asset::class, $data->targetAssetId);
        $this->denyAccessUnlessGranted(AbstractVoter::EDIT, $target);

        $file = $quarantined->getSource();
        if (null === $file) {
            throw new BadRequestHttpException('Quarantined asset has no source file.');
        }

        if ($quarantined->getWorkspaceId() !== $target->getWorkspaceId()) {
            throw new BadRequestHttpException('Assets are not in the same workspace.');
        }

        $this->assetManager->assignNewAssetSourceFile($target, $file);
        $this->em->flush();

        $this->assetDelete->deleteAssets([$quarantined->getId()]);

        return $target;
    }
}
