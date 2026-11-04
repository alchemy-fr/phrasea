<?php

namespace App\OperationTask\Task;

use App\Elasticsearch\AssetIndexer;
use App\OperationTask\OperationTaskInterface;
use App\OperationTask\RunContext;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

#[AsTaggedItem(index: self::NAME)]
final readonly class IndexAssetsTask implements OperationTaskInterface
{
    public const string NAME = 'index_assets';

    public function __construct(
        private AssetIndexer $assetIndexer,
    ) {
    }

    public static function getName(): string
    {
        return self::NAME;
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
