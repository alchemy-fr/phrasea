<?php

declare(strict_types=1);

namespace Alchemy\WebhookBundle\Webhook;

use Alchemy\WebhookBundle\Normalizer\NormalizerContextBuilderInterface;
use Symfony\Component\Security\Core\Authentication\Token\AnonymousToken;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class ObjectNormalizer
{
    private readonly NormalizerInterface $normalizer;
    private readonly TokenStorageInterface $tokenStorage;

    public function __construct(
        NormalizerInterface $normalizer,
        private readonly NormalizerContextBuilderInterface $normalizerContextBuilder,
        TokenStorageInterface $tokenStorage,
        private readonly ?array $normalizerRoles = null,
    ) {
        $this->normalizer = $normalizer;
        $this->tokenStorage = $tokenStorage;
    }

    public function normalize(object $object, array $groups): array
    {
        $previousToken = $this->tokenStorage->getToken();
        $token = new AnonymousToken('', 'normalizer.', $this->normalizerRoles);
        $this->tokenStorage->setToken($token);

        $context = $this->normalizerContextBuilder->buildContext([
            'groups' => $groups,
        ]);

        $data = $this->normalizer->normalize($object, 'json', $context);

        $this->tokenStorage->setToken($previousToken);

        return $data;
    }
}
