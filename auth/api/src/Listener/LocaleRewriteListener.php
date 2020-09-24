<?php

declare(strict_types=1);

namespace App\Listener;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Routing\RouteCollection;

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
        array $supportedLocales = array('en'),
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

        $routeExists = false; //by default assume route does not exist.

        foreach ($this->routeCollection as $routeObject) {
            $routePath = $routeObject->getPath();
            if ($routePath === '/{_locale}'.$path) {
                $routeExists = true;
                break;
            }
        }

        if ($routeExists) {
            $locale = $request->getPreferredLanguage();
            if ($locale == '' || $this->isLocaleSupported($locale) == false) {
                $locale = $request->getDefaultLocale();
            }

            $event->setResponse(new RedirectResponse('/'.$locale.$path));
        }
    }

    public static function getSubscribedEvents()
    {
        return [
            // must be registered before the default Locale listener
            KernelEvents::REQUEST => array(array('onKernelRequest', 18)),
        ];
    }
}
