<?php

declare(strict_types=1);

namespace App\Workflow\JobHandler;

use Alchemy\Workflow\Executor\Action\ActionInterface;
use Alchemy\Workflow\Executor\RunContext;
use App\Border\UploaderClient;

class UploaderAckAssetJob implements ActionInterface
{
    public function __construct(private readonly UploaderClient $uploaderClient)
    {
    }

    public function handle(RunContext $context): void
    {
        $inputs = $context->getInputs();
        $this->uploaderClient->ackAsset($inputs['baseUrl'], $inputs['assetId'], $inputs['token']);
    }
}
