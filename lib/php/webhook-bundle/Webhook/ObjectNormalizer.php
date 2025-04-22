<?php

declare(strict_types=1);

namespace Alchemy\WebhookBundle\Webhook;

use Alchemy\WebhookBundle\Normalizer\NormalizerContextBuilderInterface;
use Alchemy\WebhookBundle\Security\WebhookToken;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

readonly class ObjectNormalizer
{
    private NormalizerInterface $normalizer;
    private TokenStorageInterface $tokenStorage;

    public function __construct(
        NormalizerInterface $normalizer,
        private NormalizerContextBuilderInterface $normalizerContextBuilder,
        TokenStorageInterface $tokenStorage,
        private ?array $normalizerRoles = null,
    ) {
        $this->normalizer = $normalizer;
        $this->tokenStorage = $tokenStorage;
    }

    public function normalize(object $object, array $groups): array
    {
        $previousToken = $this->tokenStorage->getToken();
        $token = new WebhookToken($this->normalizerRoles);
        $this->tokenStorage->setToken($token);

        $context = $this->normalizerContextBuilder->buildContext([
            'groups' => $groups,
        ]);

        $data = $this->normalizer->normalize($object, 'json', $context);

        $this->tokenStorage->setToken($previousToken);

        return $data;
    }
}
