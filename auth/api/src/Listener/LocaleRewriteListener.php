<?php

declare(strict_types=1);

namespace App\Listener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\RouterInterface;

class LocaleRewriteListener implements EventSubscriberInterface
{
    private RouterInterface $router;
    private RouteCollection $routeCollection;
    private string $defaultLocale;
    private array $supportedLocales = [];
    private string $localeRouteParam;

    public function __construct(
        RouterInterface $router,
        $defaultLocale = 'en',
        array $supportedLocales = ['en'],
        $localeRouteParam = '_locale'
    ) {
        $this->router = $router;
        $this->routeCollection = $router->getRouteCollection();
        $this->defaultLocale = $defaultLocale;
        $this->supportedLocales = $supportedLocales;
        $this->localeRouteParam = $localeRouteParam;
    }

    public function isLocaleSupported($locale)
    {
//        return in_array($locale, $this->supportedLocales);
        return true;
    }

    public function onKernelRequest(GetResponseEvent $event)
    {
        $request = $event->getRequest();
        $path = $request->getPathInfo();

        foreach ($this->routeCollection as $routeObject) {
            $routePath = $routeObject->getPath();
            if ($routePath === '/{_locale}'.$path) {
                $locale = $request->getPreferredLanguage();
                if ('' == $locale || false == $this->isLocaleSupported($locale)) {
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
