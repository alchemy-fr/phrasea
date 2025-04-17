<?php

declare(strict_types=1);

namespace Alchemy\ESBundle\Message;

use Alchemy\ESBundle\Indexer\EntityGroup;
use Alchemy\ESBundle\Indexer\SearchIndexer;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class ESIndexHandler
{
    private array $done = [];

    public function __construct(private SearchIndexer $searchIndexer)
    {
    }

    public function __invoke(ESIndex $message): void
    {

        foreach ($message->getObjects() as $class => $entities) {
            foreach ($entities as $ids) {
                foreach ($ids as $id) {
                    if (isset($this->done[$class][(string) $id])) {
                        dump(sprintf('Duplicate index operation for %s:%s', $class, $id));
                    }
                    $this->done[$class][(string) $id] = true;
                }
            }
        }

        $this->searchIndexer->index(
            $message->getObjects(),
            $message->getDepth(),
            array_map(fn (array $parent): EntityGroup => EntityGroup::fromArray($parent), $message->getParents())
        );
    }
}
