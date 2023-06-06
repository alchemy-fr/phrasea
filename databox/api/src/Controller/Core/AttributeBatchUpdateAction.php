<?php

declare(strict_types=1);

namespace App\Controller\Core;

use App\Attribute\BatchAttributeManager;
use App\Controller\Traits\UserControllerTrait;
use App\Entity\Core\Attribute;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class AttributeBatchUpdateAction extends AbstractController
{
    use UserControllerTrait;

    public function __construct(private readonly BatchAttributeManager $batchAttributeManager)
    {
    }

    public function __invoke(Attribute $data, Request $request)
    {
        $input = $data->batchUpdate;
        $workspaceId = $this->batchAttributeManager->validate($input->assets, $input);

        if (null !== $workspaceId) {
            $this->batchAttributeManager->handleBatch(
                $workspaceId,
                $input->assets,
                $input,
                $this->getStrictUser(),
                true,
            );
        }

        return new Response('');
    }
}
