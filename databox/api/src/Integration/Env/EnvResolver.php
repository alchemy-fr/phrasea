<?php

declare(strict_types=1);

namespace App\Integration\Env;

use App\Entity\Integration\WorkspaceEnv;
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

    public function resolve(?string $workspaceId, array $config): array
    {
        $secrets = $this->em->getRepository(WorkspaceSecret::class)
            ->findBy([
                'workspace' => $workspaceId,
            ]);

        $varIndex = [];
        foreach ($secrets as $secret) {
            $varIndex[$secret->getName()] = new LazySecret($this->secretsManager, $secret->getValue());
        }

        $envs = $this->em->getRepository(WorkspaceEnv::class)
            ->findBy([
                'workspace' => $workspaceId,
            ]);

        foreach ($envs as $env) {
            $varIndex[$env->getName()] = $env->getValue();
        }

        return $this->resolveArrayNode($config, $varIndex);
    }

    /**
     * @param array<string, LazySecret|string> $variables
     */
    public function resolveArrayNode(array $config, array $variables): array
    {
        $replace = function (array $match) use ($variables): string {
            $var = $variables[$match[1]] ?? null;
            if (null !== $var) {
                return $var instanceof LazySecret ? $var->getDecrypted() : $var;
            }

            return getenv(self::ENV_PREFIX.$match[1]) ?: '';
        };

        foreach ($config as $key => $value) {
            if (is_array($value)) {
                $config[$key] = $this->resolveArrayNode($value, $variables);
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
