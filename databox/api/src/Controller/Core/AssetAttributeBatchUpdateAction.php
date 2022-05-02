<?php

declare(strict_types=1);

namespace App\Controller\Core;

use App\Attribute\BatchAttributeManager;
use App\Entity\Core\Asset;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;

final class AssetAttributeBatchUpdateAction extends AbstractController
{
    private BatchAttributeManager $batchAttributeManager;

    public function __construct(BatchAttributeManager $batchAttributeManager)
    {
        $this->batchAttributeManager = $batchAttributeManager;
    }

    public function __invoke(string $id, Asset $data, Request $request)
    {
        $this->batchAttributeManager->handleBatch($data->getWorkspaceId(), [$data->getId()], $data->attributeActions);

        return $data;
    }
}
