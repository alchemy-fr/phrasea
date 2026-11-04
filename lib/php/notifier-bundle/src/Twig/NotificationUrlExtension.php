<?php

declare(strict_types=1);

namespace Alchemy\NotifierBundle\Twig;

use Alchemy\NotifierBundle\Notification\NotificationUrlGenerator;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class NotificationUrlExtension extends AbstractExtension
{
    public function __construct(
        private readonly NotificationUrlGenerator $urlGenerator,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('notification_url', $this->urlGenerator->generate(...)),
        ];
    }
}
