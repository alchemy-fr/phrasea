<?php

declare(strict_types=1);

namespace App\Controller\Core;

use App\Api\Model\Input\Attribute\AttributeBatchUpdateInput;
use App\Attribute\BatchAttributeManager;
use App\Controller\Traits\UserControllerTrait;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class AttributeBatchUpdateAction extends AbstractController
{
    use UserControllerTrait;

    public function __construct(private readonly BatchAttributeManager $batchAttributeManager)
    {
    }

    public function __invoke(AttributeBatchUpdateInput $data, Request $request): Response
    {
        $this->batchAttributeManager->validate($data->workspaceId, $data->assets, $data);

        if (null !== $workspaceId) {
            $this->batchAttributeManager->handleBatch(
                $data->workspaceId,
                $data->assets,
                $data,
                $this->getStrictUser(),
                true,
            );
        }

        return new Response('');
    }
}
