<?php

declare(strict_types=1);

namespace App\Consumer\Handler\Search;

use App\Elasticsearch\ESSearchIndexer;
use Arthem\Bundle\RabbitBundle\Consumer\Event\AbstractEntityManagerHandler;
use Arthem\Bundle\RabbitBundle\Consumer\Event\EventMessage;

class SearchIndexHandler extends AbstractEntityManagerHandler
{
    const EVENT = 'es_index_doc';

    private ESSearchIndexer $searchIndexer;

    public function __construct(ESSearchIndexer $searchIndexer)
    {
        $this->searchIndexer = $searchIndexer;
    }

    public function handle(EventMessage $message): void
    {
        $payload = $message->getPayload();
        $this->searchIndexer->index($payload['objects'], $payload['depth'] ?? 1);
    }

    public static function getHandledEvents(): array
    {
        return [self::EVENT];
    }
}
