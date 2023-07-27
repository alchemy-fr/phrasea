<?php

declare(strict_types=1);

namespace App\Controller\Core;

use App\Api\Model\Input\Attribute\AssetAttributeBatchUpdateInput;
use App\Attribute\BatchAttributeManager;
use App\Controller\Traits\UserControllerTrait;
use App\Entity\Core\Asset;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;

final class AssetAttributeBatchUpdateAction extends AbstractController
{
    use UserControllerTrait;

    public function __construct(private readonly BatchAttributeManager $batchAttributeManager)
    {
    }

    public function __invoke(string $id, Asset $asset, AssetAttributeBatchUpdateInput $data, Request $request)
    {
        $this->batchAttributeManager->validate([$asset->getId()], $data);

        $this->batchAttributeManager->handleBatch(
            $asset->getWorkspaceId(),
            [$asset->getId()],
            $data,
            $this->getStrictUser(),
            true,
        );

        return $asset;
    }
}
