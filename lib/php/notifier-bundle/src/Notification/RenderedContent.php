<?php

declare(strict_types=1);

namespace Alchemy\NotifierBundle\Notification;

/**
 * The result of rendering a topic template for a given channel.
 */
final readonly class RenderedContent
{
    public function __construct(
        public ?string $subject,
        public string $body,
    ) {
    }
}
