<?php

declare(strict_types=1);

namespace App\Elasticsearch;

use Alchemy\CoreBundle\Listener\ServerTimingCollector;
use FOS\ElasticaBundle\Event\ElasticaRequestExceptionEvent;
use FOS\ElasticaBundle\Event\PostElasticaRequestEvent;
use FOS\ElasticaBundle\Event\PreElasticaRequestEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Adds the time spent in Elasticsearch (round trip of every request of the FOS Elastica
 * clients) to the Server-Timing of the request.
 */
final class EsTimingSubscriber
{
    public const string NAME = 'es';

    private ?int $start = null;

    public function __construct(private readonly ServerTimingCollector $collector)
    {
    }

    #[AsEventListener]
    public function onPreRequest(PreElasticaRequestEvent $event): void
    {
        $this->start = hrtime(true);
    }

    #[AsEventListener]
    public function onPostRequest(PostElasticaRequestEvent $event): void
    {
        $this->stop();
    }

    #[AsEventListener]
    public function onRequestException(ElasticaRequestExceptionEvent $event): void
    {
        $this->stop();
    }

    private function stop(): void
    {
        if (null === $this->start) {
            return;
        }

        $this->collector->add(self::NAME, (hrtime(true) - $this->start) / 1_000_000, 'queries');
        $this->start = null;
    }
}
