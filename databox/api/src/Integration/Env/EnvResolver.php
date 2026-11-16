<?php

declare(strict_types=1);

namespace App\Integration\Env;

use App\Entity\Integration\WorkspaceEnv;
use App\Entity\Integration\WorkspaceSecret;
use App\Security\Secrets\SecretsManager;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

final readonly class EnvResolver
{
    // Stands for Phrasea Workspace Integration
    private const string ENV_PREFIX = 'PWI_';

    public function __construct(
        private SecretsManager $secretsManager,
        private EntityManagerInterface $em,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @param bool $allowInstance false when the value may be sent to a host chosen by a
     *                            workspace editor: the `PWI_<NAME>` variable of the instance
     *                            then resolves to an empty string
     */
    public function getEnv(?string $workspaceId, string $key, bool $allowInstance = true): string
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

        $value = getenv(self::ENV_PREFIX.$key) ?: '';
        if ('' !== $value && !$allowInstance) {
            $this->logger->warning(sprintf(
                'Instance variable "%s%s" not resolved for an integration of workspace "%s": it calls a URL set by its editor',
                self::ENV_PREFIX,
                $key,
                $workspaceId ?? 'NULL',
            ));

            return '';
        }

        return $value;
    }

    /**
     * Whether a secret or a variable of the workspace (of the instance for a null
     * workspace) has this name, without decrypting it.
     */
    public function isDefinedInWorkspace(?string $workspaceId, string $key): bool
    {
        $params = [
            'workspace' => $workspaceId,
            'name' => $key,
        ];

        return null !== $this->em->getRepository(WorkspaceSecret::class)->findOneBy($params)
            || null !== $this->em->getRepository(WorkspaceEnv::class)->findOneBy($params);
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
