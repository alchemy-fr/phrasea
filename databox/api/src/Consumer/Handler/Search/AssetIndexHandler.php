<?php

namespace App\Consumer\Handler\Search;

use App\Elasticsearch\AssetIndexer;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class AssetIndexHandler
{
    public function __construct(
        private readonly AssetIndexer $assetIndexer,
    ) {
    }

    public function __invoke(AssetIndex $message): void
    {
        $this->assetIndexer->index(new NullOutput());
    }
}
