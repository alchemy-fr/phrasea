<?php

declare(strict_types=1);

namespace App\Consumer\Handler\Search;

use Alchemy\ESBundle\Indexer\SearchIndexer;
use Arthem\Bundle\RabbitBundle\Consumer\Event\AbstractEntityManagerHandler;
use Arthem\Bundle\RabbitBundle\Consumer\Event\EventMessage;

class SearchIndexHandler extends AbstractEntityManagerHandler
{
    final public const EVENT = 'es_index_doc';

    public function __construct(private readonly SearchIndexer $searchIndexer)
    {
    }

    public function handle(EventMessage $message): void
    {
        $payload = $message->getPayload();
        $this->searchIndexer->index($payload['objects'], $payload['depth'] ?? 1, []);
    }

    public static function getHandledEvents(): array
    {
        return [self::EVENT];
    }
}
