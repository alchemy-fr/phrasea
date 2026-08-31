<?php

declare(strict_types=1);

namespace Alchemy\NotifierBundle\Notification;

/**
 * Wraps a client-side URI (e.g. `/assets/{id}#discussion-{id}`) into an absolute
 * link pointing at the client's notification entry point:
 *
 *     https://databox.example.com/notification-uri?uri=%2Fassets%2F42
 *
 * The client intercepts that route and resolves the final destination itself, so
 * the backend never has to know how the front-end maps a URI to a screen.
 */
final readonly class NotificationUrlGenerator
{
    public function __construct(
        private string $clientUrl = '',
        private string $notificationUriPath = '/notification-uri',
    ) {
    }

    /**
     * Returns the URI untouched when it is already absolute or when no client
     * URL is configured.
     */
    public function generate(?string $uri): ?string
    {
        if (null === $uri || '' === $uri) {
            return null;
        }

        if ('' === $this->clientUrl || preg_match('#^[a-z][a-z0-9+.\-]*://#i', $uri)) {
            return $uri;
        }

        return sprintf(
            '%s/%s?uri=%s',
            rtrim($this->clientUrl, '/'),
            ltrim($this->notificationUriPath, '/'),
            rawurlencode($uri),
        );
    }
}
