<?php

declare(strict_types=1);

namespace App\Controller\Core;

use App\Attribute\BatchAttributeManager;
use App\Entity\Core\Attribute;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class AttributeBatchUpdateAction extends AbstractController
{
    private BatchAttributeManager $batchAttributeManager;

    public function __construct(BatchAttributeManager $batchAttributeManager)
    {
        $this->batchAttributeManager = $batchAttributeManager;
    }

    public function __invoke(Attribute $data, Request $request)
    {
        $this->batchAttributeManager->handleMultiAssetBatch($data->batchUpdate);

        return new Response('');
    }
}
