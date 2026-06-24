<?php

namespace App\OperationTask\Task;

use App\Elasticsearch\AssetIndexer;
use App\OperationTask\OperationTaskInterface;
use App\OperationTask\RunContext;

final readonly class IndexAssetsTask implements OperationTaskInterface
{
    public function __construct(
        private AssetIndexer $assetIndexer,
    ) {
    }

    public static function getName(): string
    {
        return 'index_assets';
    }

    public function validate(array $payload): void
    {
    }

    public function handle(array $payload, RunContext $context): void
    {
        $this->assetIndexer->index(
            $context,
            $payload['assetId'] ?? null,
            $payload['workspaceId'] ?? null
        );
    }
}
