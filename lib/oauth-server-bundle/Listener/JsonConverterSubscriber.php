<?php

declare(strict_types=1);

namespace Alchemy\OAuthServerBundle\Listener;

use function json_last_error;
use function json_last_error_msg;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\FilterControllerEvent;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

class JsonConverterSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::CONTROLLER => 'convertJsonStringToArray',
        ];
    }

    public function convertJsonStringToArray(FilterControllerEvent $event)
    {
        $request = $event->getRequest();
        if ('json' !== $request->getContentType() || empty($request->getContent())) {
            return;
        }
        $data = json_decode($request->getContent(), true);
        if (JSON_ERROR_NONE !== json_last_error()) {
            throw new BadRequestHttpException('invalid json body: '.json_last_error_msg());
        }
        $request->request->replace(is_array($data) ? $data : []);
    }
}
