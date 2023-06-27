<?php

declare(strict_types=1);

namespace App\Listener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\RouterInterface;

class LocaleRewriteListener implements EventSubscriberInterface
{
    private readonly RouteCollection $routeCollection;

    public function __construct(
        private readonly RouterInterface $router,
        private readonly string $defaultLocale = 'en',
        private readonly array $supportedLocales = ['en'],
        private readonly string $localeRouteParam = '_locale'
    ) {
        $this->routeCollection = $router->getRouteCollection();
    }

    public function isLocaleSupported($locale): bool
    {
        //        return in_array($locale, $this->supportedLocales);
        return true;
    }

    public function onKernelRequest(RequestEvent $event)
    {
        $request = $event->getRequest();
        $path = $request->getPathInfo();

        foreach ($this->routeCollection as $routeObject) {
            $routePath = $routeObject->getPath();
            if ($routePath === '/{_locale}'.$path) {
                $locale = $request->getPreferredLanguage();
                if ('' == $locale || false === $this->isLocaleSupported($locale)) {
                    $locale = $request->getDefaultLocale();
                }

                $locale = str_replace('_', '-', $locale);
                $event->setResponse(new RedirectResponse('/'.$locale.$path));
                break;
            }
        }
    }

    public static function getSubscribedEvents()
    {
        return [
            // must be registered before the default Locale listener
            KernelEvents::REQUEST => [['onKernelRequest', 18]],
        ];
    }
}
