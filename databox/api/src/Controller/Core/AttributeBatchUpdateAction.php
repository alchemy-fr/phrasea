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
        $input = $data->batchUpdate;
        $workspaceId = $this->batchAttributeManager->validate($input->assets, $input);

        if (null !== $workspaceId) {
            $this->batchAttributeManager->handleBatch($workspaceId, $input->assets, $input);
        }

        return new Response('');
    }
}
