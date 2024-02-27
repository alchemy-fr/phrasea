<?php

declare(strict_types=1);

namespace Alchemy\ESBundle\Message;

use Alchemy\ESBundle\Indexer\SearchIndexer;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
readonly class ESIndexHandler
{
    public function __construct(private SearchIndexer $searchIndexer)
    {
    }

    public function __invoke(ESIndex $message): void
    {
        $this->searchIndexer->index($message->getObjects(), $message->getDepth(), $message->getParents());
    }
}
