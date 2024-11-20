<?php

declare(strict_types=1);

namespace Alchemy\CoreBundle\Controller;

use Alchemy\CoreBundle\Message\Debug\SentryDebug;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Annotation\Route;

#[Route(path: '/_health/sentry-test')]
#[AsController]
#[Autoconfigure]
readonly class SentryTestController
{
    public function __construct(private LoggerInterface $logger)
    {
    }

    #[Route(path: '/single-log')]
    public function testSingleLog(): Response
    {
        $this->logger->error('[SINGLE] This is a single log');

        return new Response();
    }

    #[Route(path: '/uncaught')]
    public function testUncaught(): never
    {
        throw new \RuntimeException('[UNCAUGHT] Exception');
    }

    #[Route(path: '/both-log-and-uncaught')]
    public function testBoth(): never
    {
        $this->logger->error('[BOTH] This is a single log');

        throw new \RuntimeException('[BOTH] Exception');
    }

    #[Route(path: '/log-stack')]
    public function logStack(): never
    {
        $this->logger->debug('This is a DEBUG log');
        $this->logger->info('This is a INFO log');
        $this->logger->error('This is a ERROR log');

        throw new \RuntimeException('Stack');
    }

    #[Route(path: '/messenger')]
    public function messenger(MessageBusInterface $bus): Response
    {
        $bus->dispatch(new SentryDebug(date(\DateTimeInterface::ATOM), ['extra' => 'data']));

        return new Response('');
    }
}
