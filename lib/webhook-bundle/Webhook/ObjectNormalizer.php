<?php

declare(strict_types=1);

namespace Alchemy\WebhookBundle\Webhook;

use Symfony\Component\Security\Core\Authentication\Token\AnonymousToken;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class ObjectNormalizer
{
    private NormalizerInterface $normalizer;
    private TokenStorageInterface $tokenStorage;
    private ?array $normalizerRoles;

    public function __construct(
        NormalizerInterface $normalizer,
        TokenStorageInterface $tokenStorage,
        ?array $normalizerRoles = null
    )
    {
        $this->normalizer = $normalizer;
        $this->normalizerRoles = $normalizerRoles;
        $this->tokenStorage = $tokenStorage;
    }

    public function normalize(object $object, array $groups): array
    {
        $previousToken = $this->tokenStorage->getToken();
        $token = new AnonymousToken('', 'normalizer.', $this->normalizerRoles);
        $this->tokenStorage->setToken($token);

        $data = $this->normalizer->normalize($object, 'json', [
            'groups' => $groups,
        ]);

        $this->tokenStorage->setToken($previousToken);

        return $data;
    }
}
