<?php

declare(strict_types=1);

namespace App\Integration\Env;

use App\Security\Secrets\SecretsManager;

final class LazySecret
{
    private ?string $decrypted = null;

    public function __construct(
        private readonly SecretsManager $secretsManager,
        private readonly string $value,
    ) {
    }

    public function getDecrypted(): ?string
    {
        if (null === $this->decrypted) {
            $this->decrypted = $this->secretsManager->decryptSecret($this->value);
        }

        return $this->decrypted;
    }
}
