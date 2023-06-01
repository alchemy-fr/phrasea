<?php

declare(strict_types=1);

namespace App\Integration\Env;

use App\Entity\Integration\WorkspaceSecret;
use App\Security\Secrets\SecretsManager;
use Doctrine\ORM\EntityManagerInterface;

final readonly class EnvResolver
{
    // Stands for Phrasea Workspace Integration
    private const ENV_PREFIX = 'PWI_';

    public function __construct(
        private SecretsManager $secretsManager,
        private EntityManagerInterface $em,
    ) {
    }

    public function resolve(string $workspaceId, array $config): array
    {
        $secrets = $this->em->getRepository(WorkspaceSecret::class)
            ->findBy([
                'workspace' => $workspaceId,
            ]);

        $secretIndex = [];
        foreach ($secrets as $secret) {
            $secretIndex[$secret->getName()] = new LazySecret($this->secretsManager, $secret->getValue());
        }

        return $this->resolveArrayNode($config, $secretIndex);
    }

    /**
     * @param array<string, LazySecret> $secrets
     */
    public function resolveArrayNode(array $config, array $secrets): array
    {
        $replace = fn (array $match): string => isset($secrets[$match[1]]) ? $secrets[$match[1]]?->getDecrypted() : (getenv(self::ENV_PREFIX.$match[1]) ?: '');

        foreach ($config as $key => $value) {
            if (is_array($value)) {
                $config[$key] = $this->resolveArrayNode($value, $secrets);
            } elseif (is_string($value)) {
                $resolved = preg_replace_callback('#\$\{([^}]+)}#i', $replace, $value);
                $resolved = preg_replace_callback('#\$([A-Z\d_]+)#i', $replace, $resolved);
                if ($resolved !== $value) {
                    $config[$key] = $resolved;
                }
            }
        }

        return $config;
    }
}
