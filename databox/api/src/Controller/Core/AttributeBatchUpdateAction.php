<?php

declare(strict_types=1);

namespace App\Controller\Core;

use App\Api\Model\Input\Attribute\AssetAttributeBatchUpdateInput;
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

    public function __invoke(AssetAttributeBatchUpdateInput $data, Request $request)
    {
        $workspaceId = $this->batchAttributeManager->validate($data->assets, $data);

        if (null !== $workspaceId) {
            $this->batchAttributeManager->handleBatch(
                $workspaceId,
                $data->assets,
                $data,
                $this->getStrictUser(),
                true,
            );
        }

        return new Response('');
    }
}
