<?php

declare(strict_types=1);

namespace Alchemy\CoreBundle\Listener;

use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\RouterInterface;

class RouterContextListener implements EventSubscriberInterface
{
    private RouterInterface $router;
    private string $baseUrl;

    public function __construct(RouterInterface $router, string $baseUrl)
    {
        $this->router = $router;
        $this->baseUrl = $baseUrl;
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

    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::CONTROLLER => 'setContext',
            ConsoleEvents::COMMAND => 'setContext',
        ];
    }
}
