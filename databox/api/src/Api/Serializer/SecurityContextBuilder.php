<?php

declare(strict_types=1);

namespace App\Api\Serializer;

use Alchemy\RemoteAuthBundle\Model\RemoteUser;
use Alchemy\WebhookBundle\Normalizer\NormalizerContextBuilderInterface;
use ApiPlatform\Core\Serializer\SerializerContextBuilderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Security;

class SecurityContextBuilder implements SerializerContextBuilderInterface
{
    private Security $security;
    private SerializerContextBuilderInterface $decorated;
    private NormalizerContextBuilderInterface $normalizerContextBuilder;

    public function __construct(
        SerializerContextBuilderInterface $decorated,
        Security $security,
        NormalizerContextBuilderInterface $normalizerContextBuilder
    ) {
        $this->decorated = $decorated;
        $this->security = $security;
        $this->normalizerContextBuilder = $normalizerContextBuilder;
    }

    public function createFromRequest(Request $request, bool $normalization, ?array $extractedAttributes = null): array
    {
        $context = $this->decorated->createFromRequest($request, $normalization, $extractedAttributes);

        $user = $this->security->getUser();
        $context['userId'] = $user instanceof RemoteUser ? $user->getId() : null;
        $context['groupIds'] = $user instanceof RemoteUser ? $user->getGroupIds() : [];

        return $this->normalizerContextBuilder->buildContext($context);
    }
}
