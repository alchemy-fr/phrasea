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
    private const string ENV_PREFIX = 'PWI_';

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

    /**
     * The names that `${NAME}` resolves to a `PWI_<NAME>` environment variable of
     * the instance (the last source, after the secrets and the variables): valid
     * names whose value is not empty, without the prefix, sorted.
     *
     * @return list<string>
     */
    public static function getSystemEnvNames(): array
    {
        $names = [];
        foreach (getenv() as $key => $value) {
            // A numeric variable name becomes an int key
            $key = (string) $key;
            if (!str_starts_with($key, self::ENV_PREFIX) || '' === $value) {
                continue;
            }

            $name = substr($key, \strlen(self::ENV_PREFIX));
            if (1 === preg_match(WorkspaceSecret::NAME_PATTERN, $name)) {
                $names[] = $name;
            }
        }
        sort($names, \SORT_STRING);

        return $names;
    }
}
