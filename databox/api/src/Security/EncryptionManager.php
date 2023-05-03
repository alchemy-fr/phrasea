<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\Encryption\KeyPairEncryptionInterface;
use App\Entity\Participant;
use App\Exception\DecryptException;
use InvalidArgumentException;
use RuntimeException;

class EncryptionManager
{
    public function encryptWithPassword(string $data, string $password): string
    {
        if (empty($password)) {
            throw new InvalidArgumentException('Empty password');
        }

        $method = 'AES-256-CBC';
        $key = hash('sha256', $password, true);
        $iv = openssl_random_pseudo_bytes(16);

        $cipherText = openssl_encrypt($data, $method, $key, OPENSSL_RAW_DATA, $iv);
        $hash = hash_hmac('sha256', $cipherText.$iv, $key, true);

        return base64_encode($iv.$hash.$cipherText);
    }

    public function decryptWithPassword(string $data, string $password): string
    {
        $data = base64_decode($data);
        $method = 'AES-256-CBC';
        $iv = substr($data, 0, 16);
        $hash = substr($data, 16, 32);
        $cipherText = substr($data, 48);
        $key = hash('sha256', $password, true);

        if (!hash_equals(hash_hmac('sha256', $cipherText.$iv, $key, true), $hash)) {
            throw new DecryptException('Cannot decrypt data');
        }

        return openssl_decrypt($cipherText, $method, $key, OPENSSL_RAW_DATA, $iv);
    }

    public function generatePassPhraseProtectedKeyPair(string $passPhrase, ?string &$plainSecretKey = null): array
    {
        $keyPair = sodium_crypto_box_keypair();
        $secret = sodium_crypto_box_secretkey($keyPair);
        $public = sodium_crypto_box_publickey($keyPair);

        $plainSecretKey = base64_encode($secret);

        $data = [
            'public' => base64_encode($public),
            'secret' => $this->encryptWithPassword($plainSecretKey, $passPhrase),
        ];

        sodium_memzero($secret);

        return $data;
    }

    public function generateAndAssignKeyPair(Participant $participant, KeyPairEncryptionInterface $dataContainer, ?string &$plainSecretKey = null): void
    {
        $this->generateAndAssignKeyPairFromPublicKey($participant->getPublicKey(), $dataContainer, $plainSecretKey);
    }

    public function generateAndAssignKeyPairFromPublicKey(string $publicKey, KeyPairEncryptionInterface $dataContainer, ?string &$plainSecretKey = null): void
    {
        if ($dataContainer->getSecretKey()) {
            return;
        }
        $keyPair = $this->generateEncryptedKeyPair($publicKey, $plainSecretKey);

        $dataContainer->setPublicKey($keyPair['public']);
        $dataContainer->setSecretKey($keyPair['secret']);
    }

    /**
     * @param string $encryptionKey The parent public key to encrypt the generated secret key
     */
    public function generateEncryptedKeyPair(string $encryptionKey, ?string &$plainSecretKey = null): array
    {
        $keyPair = sodium_crypto_box_keypair();

        $secret = sodium_crypto_box_secretkey($keyPair);
        $plainSecretKey = base64_encode($secret);
        $public = sodium_crypto_box_publickey($keyPair);

        return [
            'public' => base64_encode($public),
            'secret' => $this->encryptDataWithPublicKey(base64_encode($secret), $encryptionKey),
        ];
    }

    public function generateKeyPair(): array
    {
        $keyPair = sodium_crypto_box_keypair();

        $secret = sodium_crypto_box_secretkey($keyPair);
        $public = sodium_crypto_box_publickey($keyPair);

        return [
            'public' => base64_encode($public),
            'secret' => base64_encode($secret),
        ];
    }

    public function getUnlockedSecretKey(string $secretKey, string $passPhrase): string
    {
        return $this->decryptWithPassword($secretKey, $passPhrase);
    }

    public function changeSecretKeyPassPhrase(string $secretKey, string $oldPassPhrase, string $newPassPhrase): string
    {
        $unlockedSK = $this->getUnlockedSecretKey($secretKey, $oldPassPhrase);

        return $this->encryptWithPassword($unlockedSK, $newPassPhrase);
    }

    public function encryptDataWithPublicKey(string $data, string $publicKey): string
    {
        if (empty($publicKey)) {
            throw new InvalidArgumentException('Empty public key');
        }

        $decodedPK = base64_decode($publicKey);
        if (!$decodedPK) {
            throw new InvalidArgumentException('Invalid base64 publicKey');
        }

        return base64_encode(sodium_crypto_box_seal($data, $decodedPK));
    }

    /**
     * @param array|string|null $data
     *
     * @return array|string|null
     */
    public function encryptArrayWithPublicKey($data, string $publicKey)
    {
        if (null === $data) {
            return null;
        }

        if (!is_array($data)) {
            return $this->encryptDataWithPublicKey($data, $publicKey);
        }

        return array_map(fn ($value) => $this->encryptArrayWithPublicKey($value, $publicKey), $data);
    }

    /**
     * @param array|string|null $data
     *
     * @return array|string|null
     */
    public function decryptArrayWithSecretKey($data, string $secretKey, string $publicKey)
    {
        if (null === $data) {
            return null;
        }

        if (!is_array($data)) {
            return $this->decryptDataWithSecretKey($data, $secretKey, $publicKey);
        }

        return array_map(fn ($value) => $this->decryptArrayWithSecretKey($value, $secretKey, $publicKey), $data);
    }

    public function decryptDataWithProtectedSecretKey(string $data, string $secretKey, string $passPhrase, string $publicKey): string
    {
        return $this->decryptDataWithSecretKey($data, $this->getUnlockedSecretKey($secretKey, $passPhrase), $publicKey);
    }

    public function decryptDataWithSecretKey(string $data, string $secretKey, string $publicKey): string
    {
        $keyPair = sodium_crypto_box_keypair_from_secretkey_and_publickey(base64_decode($secretKey), base64_decode($publicKey));

        $result = sodium_crypto_box_seal_open(base64_decode($data), $keyPair);
        if (false === $result) {
            throw new RuntimeException(sprintf('Failed to decrypt data %s', $data));
        }

        return $result;
    }
}
