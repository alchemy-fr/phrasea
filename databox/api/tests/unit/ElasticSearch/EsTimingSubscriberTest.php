<?php

declare(strict_types=1);

namespace App\Tests\Unit\ElasticSearch;

use Alchemy\CoreBundle\Listener\ServerTimingCollector;
use App\Elasticsearch\EsTimingSubscriber;
use Elastic\Elasticsearch\Exception\ServerResponseException;
use Elastica\Response;
use FOS\ElasticaBundle\Event\ElasticaRequestExceptionEvent;
use FOS\ElasticaBundle\Event\PostElasticaRequestEvent;
use FOS\ElasticaBundle\Event\PreElasticaRequestEvent;
use GuzzleHttp\Psr7\Request;
use PHPUnit\Framework\TestCase;

class EsTimingSubscriberTest extends TestCase
{
    public function testEachRequestIsTimed(): void
    {
        $collector = new ServerTimingCollector();
        $subscriber = new EsTimingSubscriber($collector);
        $request = new Request('POST', 'http://elasticsearch:9200/asset/_search');

        $subscriber->onPreRequest(new PreElasticaRequestEvent('asset/_search', 'POST', [], []));
        usleep(20_000);
        $subscriber->onPostRequest(new PostElasticaRequestEvent($request, new Response('{}', 200)));

        $subscriber->onPreRequest(new PreElasticaRequestEvent('asset/_search', 'POST', [], []));
        $subscriber->onRequestException(new ElasticaRequestExceptionEvent($request, new ServerResponseException('timeout')));

        $entries = $collector->getEntries();
        $this->assertSame(['es'], array_keys($entries));
        $this->assertSame(2, $entries['es']['count']);
        $this->assertSame('queries', $entries['es']['desc']);
        $this->assertGreaterThanOrEqual(20.0, $entries['es']['dur']);
    }

    public function testAnUnmatchedPostRequestIsIgnored(): void
    {
        $collector = new ServerTimingCollector();
        $subscriber = new EsTimingSubscriber($collector);

        $subscriber->onPostRequest(new PostElasticaRequestEvent(new Request('GET', 'http://elasticsearch:9200/'), new Response('{}', 200)));

        $this->assertSame([], $collector->getEntries());
    }
}
