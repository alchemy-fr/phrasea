<?php

declare(strict_types=1);

namespace Alchemy\NotifierBundle\Channel;

enum ChannelType: string
{
    case Email = 'email';
    case Sms = 'sms';
    case InApp = 'in_app';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $c): string => $c->value, self::cases());
    }
}
