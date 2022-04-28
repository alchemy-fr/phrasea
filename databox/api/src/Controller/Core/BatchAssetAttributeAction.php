<?php

declare(strict_types=1);

namespace App\Controller\Core;

use App\Attribute\BatchAttributeManager;
use App\Entity\Core\Asset;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class BatchAssetAttributeAction extends AbstractController
{
    private BatchAttributeManager $batchAttributeManager;

    public function __construct(BatchAttributeManager $batchAttributeManager)
    {
        $this->batchAttributeManager = $batchAttributeManager;
    }

    public function __invoke(string $id, Asset $data, Request $request)
    {
        $this->batchAttributeManager->handleBatch($data, $data->attributeActions);

        return new Response('');
    }
}
