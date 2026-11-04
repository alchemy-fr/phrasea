<?php

declare(strict_types=1);

namespace App\Automation;

use App\Security\Secrets\SecretsManager;

/**
 * Keeps the secrets of the action configurations (credentials of a destination…) encrypted:
 * encrypted when written, never output (null + "<field>Set" flag) and decrypted by the worker only,
 * the only one holding the secret key.
 *
 * The secret fields are given in dot notation (e.g. "credentials.password").
 */
final readonly class ActionConfigSecretsHandler
{
    public function __construct(
        private AutomationActionRegistry $registry,
        private SecretsManager $secretsManager,
    ) {
    }

    /**
     * Encrypts the secrets of a written configuration. An empty secret keeps the previous one.
     *
     * @param array|null $previousConfig the stored (encrypted) configuration of the same action, if any
     */
    public function encrypt(string $type, array $config, ?array $previousConfig = null): array
    {
        foreach ($this->getSecretFields($type) as $path) {
            $value = self::getValue($config, $path);
            if (null === $value || '' === $value) {
                $previous = null !== $previousConfig ? self::getValue($previousConfig, $path) : null;
                $config = null === $previous || '' === $previous
                    ? self::unsetValue($config, $path)
                    : self::setValue($config, $path, $previous);

                continue;
            }

            $config = self::setValue($config, $path, $this->secretsManager->encryptSecret((string) $value));
        }

        return $config;
    }

    /**
     * Hides the secrets of a stored configuration.
     */
    public function mask(string $type, array $config): array
    {
        foreach ($this->getSecretFields($type) as $path) {
            $value = self::getValue($config, $path);
            $config = self::setValue($config, $path, null);
            $config = self::setValue($config, $path.'Set', null !== $value && '' !== $value);
        }

        return $config;
    }

    public function decrypt(string $type, array $config): array
    {
        foreach ($this->getSecretFields($type) as $path) {
            $value = self::getValue($config, $path);
            if (is_string($value) && '' !== $value) {
                $config = self::setValue($config, $path, $this->secretsManager->decryptSecret($value));
            }
        }

        return $config;
    }

    /**
     * @return string[]
     */
    private function getSecretFields(string $type): array
    {
        return $this->registry->has($type) ? $this->registry->get($type)->getSecretFields() : [];
    }

    private static function getValue(array $config, string $path): mixed
    {
        foreach (explode('.', $path) as $key) {
            if (!is_array($config) || !array_key_exists($key, $config)) {
                return null;
            }
            $config = $config[$key];
        }

        return $config;
    }

    private static function setValue(array $config, string $path, mixed $value): array
    {
        $keys = explode('.', $path);
        $last = count($keys) - 1;
        $ref = &$config;
        foreach ($keys as $i => $key) {
            if ($i < $last && (!isset($ref[$key]) || !is_array($ref[$key]))) {
                $ref[$key] = [];
            }
            $ref = &$ref[$key];
        }
        $ref = $value;

        return $config;
    }

    private static function unsetValue(array $config, string $path): array
    {
        $keys = explode('.', $path);
        $last = array_pop($keys);
        $ref = &$config;
        foreach ($keys as $key) {
            if (!isset($ref[$key]) || !is_array($ref[$key])) {
                return $config;
            }
            $ref = &$ref[$key];
        }
        unset($ref[$last]);

        return $config;
    }
}
