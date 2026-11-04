<?php

declare(strict_types=1);

namespace Alchemy\NotifierBundle\Email;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Supplies the logo shown in the header of the notification emails. Providers
 * are asked in priority order and the first non-null source wins; without any,
 * the Phrasea logo is shown next to the application name.
 */
#[AutoconfigureTag(self::TAG)]
interface EmailLogoProviderInterface
{
    public const string TAG = 'alchemy_notifier.email_logo_provider';

    /**
     * An absolute http(s) URL or a base64 `data:image/...` URI.
     */
    public function getLogoSrc(): ?string;
}
