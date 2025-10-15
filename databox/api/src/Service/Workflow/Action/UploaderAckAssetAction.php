<?php

declare(strict_types=1);

namespace App\Service\Workflow\Action;

use Alchemy\Workflow\Executor\Action\ActionInterface;
use Alchemy\Workflow\Executor\RunContext;
use App\Border\UploaderClient;

readonly class UploaderAckAssetAction implements ActionInterface
{
    public function __construct(private UploaderClient $uploaderClient)
    {
    }

    public function handle(RunContext $context): void
    {
        $inputs = $context->getInputs();
        $this->uploaderClient->ackAsset($inputs['baseUrl'], $inputs['assetId'], $inputs['token']);
    }
}
