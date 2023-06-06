<?php

declare(strict_types=1);

namespace App\Security\Secrets;

class EncryptionManager
{
    public function generateKeyPair(): SodiumKeyPair
    {
        $keyPair = sodium_crypto_box_keypair();
        $secret = sodium_crypto_box_secretkey($keyPair);
        $public = sodium_crypto_box_publickey($keyPair);

        $sodiumKeyPair = new SodiumKeyPair(base64_encode($public), base64_encode($secret));

        sodium_memzero($secret);

        return $sodiumKeyPair;
    }

    public function encryptDataWithPublicKey(string $data, string $publicKey): string
    {
        if (empty($publicKey)) {
            throw new \InvalidArgumentException('Empty public key');
        }

        $decodedPK = base64_decode($publicKey);
        if (!$decodedPK) {
            throw new \InvalidArgumentException('Invalid base64 publicKey');
        }

        return base64_encode(sodium_crypto_box_seal($data, $decodedPK));
    }

    public function decryptDataWithKeyPair(string $data, SodiumKeyPair $keyPair): string
    {
        $keyPair = sodium_crypto_box_keypair_from_secretkey_and_publickey(base64_decode($keyPair->getSecret()), base64_decode($keyPair->getPublic()));

        $result = sodium_crypto_box_seal_open(base64_decode($data), $keyPair);
        if (false === $result) {
            throw new \RuntimeException(sprintf('Failed to decrypt data %s', $data));
        }

        return $result;
    }
}
