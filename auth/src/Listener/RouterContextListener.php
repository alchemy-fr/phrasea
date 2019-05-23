<?php

declare(strict_types=1);

namespace App\Listener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\FilterControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\RouterInterface;

class RouterContextListener implements EventSubscriberInterface
{
    /**
     * @var RouterInterface
     */
    private $router;

    /**
     * @var string
     */
    private $baseUrl;

    public function __construct(RouterInterface $router, string $baseUrl)
    {
        $this->router = $router;
        $this->baseUrl = $baseUrl;
    }

    public function onController(FilterControllerEvent $event)
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
            KernelEvents::CONTROLLER => 'onController',
        ];
    }

}
