<?php

declare(strict_types=1);

namespace App\Model;

enum AuditActorTypeEnum: int
{
    case User = 1;
    case OAuthClient = 2;
    case System = 3;

    public function getKey(): string
    {
        return match ($this) {
            self::User => 'user',
            self::OAuthClient => 'oauth_client',
            self::System => 'system',
        };
    }

    public static function tryFromKey(string $key): ?self
    {
        foreach (self::cases() as $case) {
            if ($case->getKey() === $key) {
                return $case;
            }
        }

        return null;
    }

    /**
     * @return array<string, self> label => case
     */
    public static function getChoices(): array
    {
        return [
            'User' => self::User,
            'OAuth client' => self::OAuthClient,
            'System' => self::System,
        ];
    }
}
