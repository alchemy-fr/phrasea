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

    public function label(): string
    {
        return match ($this) {
            self::Email => 'Email',
            self::Sms => 'SMS',
            self::InApp => 'In-app',
        };
    }

    /**
     * @return array<string, self>
     */
    public static function getChoices(): array
    {
        $choices = [];
        foreach (self::cases() as $case) {
            $choices[$case->label()] = $case;
        }

        return $choices;
    }
}
