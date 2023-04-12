<?php

declare(strict_types=1);

namespace App\Workflow\Action;

use Alchemy\Workflow\Executor\Action\ActionInterface;
use Alchemy\Workflow\Executor\RunContext;
use App\Entity\Core\Asset;
use App\Integration\IntegrationManager;
use Arthem\Bundle\RabbitBundle\Consumer\Exception\ObjectNotFoundForHandlerException;
use Doctrine\ORM\EntityManagerInterface;

readonly class NewAssetIntegrationCollectionAction implements ActionInterface
{
    public function __construct(
        private IntegrationManager $integrationManager,
        private EntityManagerInterface $em,
    )
    {
    }

    public function handle(RunContext $context): void
    {
        $inputs = $context->getInputs();
        $id = $inputs['assetId'];

        $asset = $this->em->find(Asset::class, $id);
        if (!$asset instanceof Asset) {
            throw new ObjectNotFoundForHandlerException(Asset::class, $id, __CLASS__);
        }

        $this->integrationManager->handleAsset($asset);
    }
}
