<?php

declare(strict_types=1);

namespace Alchemy\CoreBundle\Tests\Listener;

use Alchemy\CoreBundle\Listener\ServerTimingCollector;
use Alchemy\CoreBundle\Listener\ServerTimingListener;
use Monolog\Handler\TestHandler;
use Monolog\Level;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class ServerTimingListenerTest extends TestCase
{
    private ServerTimingCollector $collector;
    private TestHandler $logs;

    protected function setUp(): void
    {
        $this->collector = new ServerTimingCollector();
        $this->logs = new TestHandler();
    }

    public function testTheResponseGetsAServerTimingHeader(): void
    {
        $this->collector->add('es', 12.25, 'queries');
        $this->collector->add('es', 3.5, 'queries');
        $this->collector->add('other', 1.0);

        $request = $this->createRequest(0.5);
        $response = new Response();
        $this->createListener(10000)->onResponse(new ResponseEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST, $response));

        $header = (string) $response->headers->get('Server-Timing');
        $this->assertMatchesRegularExpression('#^app;dur=\d+\.\d, es;dur=15\.8;desc="2 queries", other;dur=1\.0$#', $header);
        preg_match('#^app;dur=(\d+\.\d)#', $header, $matches);
        $this->assertGreaterThanOrEqual(500.0, (float) $matches[1]);
    }

    public function testSubRequestsGetNoServerTimingHeader(): void
    {
        $response = new Response();
        $this->createListener(10000)->onResponse(new ResponseEvent($this->createMock(HttpKernelInterface::class), $this->createRequest(0), HttpKernelInterface::SUB_REQUEST, $response));

        $this->assertFalse($response->headers->has('Server-Timing'));
    }

    public function testASlowRequestIsLoggedWithoutItsQueryString(): void
    {
        $this->collector->add('es', 2500.4, 'queries');

        $request = $this->createRequest(12, '/assets?query=secret+terms&limit=50');
        $request->attributes->set('_route', '_api_/assets{._format}_get_collection');

        $this->createListener(10000)->onTerminate(new TerminateEvent($this->createMock(HttpKernelInterface::class), $request, new Response('', 200)));

        $records = $this->logs->getRecords();
        $this->assertCount(1, $records);
        $this->assertSame(Level::Warning, $records[0]->level);
        $this->assertSame('Slow request', $records[0]->message);
        $context = $records[0]->context;
        $this->assertSame('GET', $context['method']);
        $this->assertSame('_api_/assets{._format}_get_collection', $context['route']);
        $this->assertSame('/assets', $context['path']);
        $this->assertSame(200, $context['status']);
        $this->assertGreaterThanOrEqual(12000, $context['duration_ms']);
        $this->assertSame(['es' => ['duration_ms' => 2500, 'count' => 1]], $context['timings']);
        $this->assertIsInt($context['memory_peak']);
        $this->assertStringNotContainsString('secret', json_encode($context, JSON_THROW_ON_ERROR));
    }

    public function testARequestUnderTheThresholdIsNotLogged(): void
    {
        $this->createListener(10000)->onTerminate(new TerminateEvent($this->createMock(HttpKernelInterface::class), $this->createRequest(2), new Response()));

        $this->assertSame([], $this->logs->getRecords());
    }

    public function testAZeroThresholdDisablesTheLog(): void
    {
        $this->createListener(0)->onTerminate(new TerminateEvent($this->createMock(HttpKernelInterface::class), $this->createRequest(60), new Response()));

        $this->assertSame([], $this->logs->getRecords());
    }

    public function testTheCollectorIsResettable(): void
    {
        $this->collector->add('es', 1.0);
        $this->collector->reset();

        $this->assertSame([], $this->collector->getEntries());
    }

    private function createListener(int $threshold): ServerTimingListener
    {
        return new ServerTimingListener($this->collector, new Logger('test', [$this->logs]), $threshold);
    }

    private function createRequest(float $secondsAgo, string $uri = '/assets'): Request
    {
        return Request::create($uri, server: ['REQUEST_TIME_FLOAT' => microtime(true) - $secondsAgo]);
    }
}
