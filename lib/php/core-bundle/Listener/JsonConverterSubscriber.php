<?php

declare(strict_types=1);

namespace Alchemy\CoreBundle\Listener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
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

    public function convertJsonStringToArray(ControllerEvent $event)
    {
        $request = $event->getRequest();
        if ('json' !== $request->getContentTypeFormat() || empty($request->getContent())) {
            return;
        }
        $data = json_decode($request->getContent(), true);
        if (JSON_ERROR_NONE !== \json_last_error()) {
            throw new BadRequestHttpException('Invalid json body: '.\json_last_error_msg());
        }
        $request->request->replace(is_array($data) ? $data : []);
    }
}
