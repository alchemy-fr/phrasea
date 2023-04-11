<?php

declare(strict_types=1);

namespace App\Workflow\JobHandler;

use Alchemy\Workflow\Executor\Action\ActionInterface;
use Alchemy\Workflow\Executor\RunContext;
use App\Entity\Core\Asset;
use App\Integration\IntegrationManager;
use Arthem\Bundle\RabbitBundle\Consumer\Exception\ObjectNotFoundForHandlerException;

class NewAssetIntegrationCollectionJob implements ActionInterface
{
    public function __construct(private readonly IntegrationManager $integrationManager)
    {
    }

    public function handle(RunContext $context): void
    {
        $inputs = $context->getInputs();
        $id = $inputs['id'];

        $em = $this->getEntityManager();
        $asset = $em->find(Asset::class, $id);
        if (!$asset instanceof Asset) {
            throw new ObjectNotFoundForHandlerException(Asset::class, $id, __CLASS__);
        }

        $this->integrationManager->handleAsset($asset);
    }
}
