<?php

declare(strict_types=1);

namespace Alchemy\CoreBundle\Listener;

use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Routing\RouterInterface;

class RouterContextListener implements EventSubscriberInterface
{
    public function __construct(private readonly RouterInterface $router, private readonly string $baseUrl)
    {
    }

    public function setContext()
    {
        $context = $this->router->getContext();

        $info = parse_url($this->baseUrl);

        $context->setScheme($info['scheme']);
        $context->setHost($info['host']);
        $context->setBaseUrl($info['path'] ?? '');
        if (isset($info['port'])) {
            if ('https' === $info['scheme']) {
                $context->setHttpsPort($info['port']);
            } else {
                $context->setHttpPort($info['port']);
            }
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ConsoleEvents::COMMAND => 'setContext',
        ];
    }
}
