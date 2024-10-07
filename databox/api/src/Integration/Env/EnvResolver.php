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

    public function getEnv(?string $workspaceId, string $key): string
    {
        $params = [
            'workspace' => $workspaceId,
            'name' => $key,
        ];

        $secret = $this->em->getRepository(WorkspaceSecret::class)
            ->findOneBy($params);
        if ($secret instanceof WorkspaceSecret) {
            try {
                return $this->secretsManager->decryptSecret($secret->getValue());
            } catch (\Exception $e) {
                throw new \RuntimeException(sprintf('Failed to decrypt WorkspaceSecret "%s" (workspace: %s): %s', $key, $workspaceId ?? 'NULL', $e->getMessage()), 0, $e);
            }
        }

        $env = $this->em->getRepository(WorkspaceEnv::class)
            ->findOneBy($params);
        if ($env instanceof WorkspaceEnv) {
            return $env->getValue();
        }

        return getenv(self::ENV_PREFIX.$key) ?: '';
    }
}
