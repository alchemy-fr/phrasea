<?php

declare(strict_types=1);

namespace Alchemy\NotifierBundle\Subscriber;

/**
 * Turns a raw Keycloak user representation into a {@see SubscriberInfo}.
 *
 * Shared by the per-user lookup ({@see KeycloakSubscriberInfoProvider}) and the
 * bulk listing ({@see KeycloakUserDirectory}) so both read the same fields.
 */
final class KeycloakUserMapper
{
    /**
     * @param array<string, mixed> $user
     */
    public function map(array $user): SubscriberInfo
    {
        return new SubscriberInfo(
            email: $user['email'] ?? null,
            phoneNumber: $this->firstAttribute($user, 'phoneNumber'),
            locale: $this->firstAttribute($user, 'locale'),
            displayName: $this->resolveDisplayName($user),
        );
    }

    /**
     * @param array<string, mixed> $user
     */
    private function resolveDisplayName(array $user): ?string
    {
        $parts = array_filter([$user['firstName'] ?? null, $user['lastName'] ?? null]);
        if ([] !== $parts) {
            return implode(' ', $parts);
        }

        return $user['username'] ?? $user['email'] ?? null;
    }

    /**
     * @param array<string, mixed> $user
     */
    private function firstAttribute(array $user, string $key): ?string
    {
        $value = $user['attributes'][$key] ?? null;
        if (is_array($value)) {
            $value = $value[0] ?? null;
        }

        return null !== $value ? (string) $value : null;
    }
}
