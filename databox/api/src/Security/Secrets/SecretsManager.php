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

    /**
     * Never returns a placeholder without the secret key: a value compared to a request (a
     * webhook key…) would then match whoever sends the placeholder.
     *
     * @throws SecretKeyUnavailableException in a process without the secret key (the API)
     */
    public function decryptSecret(string $secret): string
    {
        if ('' === $this->secretKey) {
            throw new SecretKeyUnavailableException('Secrets can only be decrypted by the workers, holding the secret key');
        }

        return $this->encryptionManager->decryptDataWithKeyPair($secret, new SodiumKeyPair(
            $this->publicKey,
            $this->secretKey,
        ));
    }
}
