<?php

declare(strict_types=1);

namespace Alchemy\WebhookBundle\Webhook;

use Alchemy\WebhookBundle\Normalizer\NormalizerContextBuilderInterface;
use Symfony\Component\Security\Core\Authentication\Token\AnonymousToken;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class ObjectNormalizer
{
    private NormalizerInterface $normalizer;
    private TokenStorageInterface $tokenStorage;
    private ?array $normalizerRoles;
    private NormalizerContextBuilderInterface $normalizerContextBuilder;

    public function __construct(
        NormalizerInterface $normalizer,
        NormalizerContextBuilderInterface $normalizerContextBuilder,
        TokenStorageInterface $tokenStorage,
        ?array $normalizerRoles = null
    )
    {
        $this->normalizer = $normalizer;
        $this->normalizerRoles = $normalizerRoles;
        $this->tokenStorage = $tokenStorage;
        $this->normalizerContextBuilder = $normalizerContextBuilder;
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
