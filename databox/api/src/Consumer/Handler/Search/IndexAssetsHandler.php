<?php

namespace App\Consumer\Handler\Search;

use App\Elasticsearch\AssetIndexer;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class IndexAssetsHandler
{
    public function __construct(
        private readonly AssetIndexer $assetIndexer,
    ) {
    }

    public function __invoke(IndexAssets $message): void
    {
        $this->assetIndexer->index(new NullOutput());
    }
}
