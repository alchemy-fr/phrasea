<?php

declare(strict_types=1);

namespace App\Security\Secrets;

final readonly class SecretsManager
{
    public function __construct(
        private EncryptionManager $encryptionManager,
        private string $secretKey,
        private string $publicKey,
    ) {
    }

    public function encryptSecret(string $secret): string
    {
        return $this->encryptionManager->encryptDataWithPublicKey($secret, $this->publicKey);
    }

    public function decryptSecret(string $secret): string
    {
        if ('not_provided' === $this->secretKey) {
            throw new \LogicException('Secret key should not be used in this context');
        }

        return $this->encryptionManager->decryptDataWithKeyPair($secret, new SodiumKeyPair(
            $this->publicKey,
            $this->secretKey,
        ));
    }
}
