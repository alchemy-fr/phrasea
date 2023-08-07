<?php

declare(strict_types=1);

namespace App\Api\Serializer;

use Alchemy\AuthBundle\Security\JwtUser;
use Alchemy\WebhookBundle\Normalizer\NormalizerContextBuilderInterface;
use ApiPlatform\Serializer\SerializerContextBuilderInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;

readonly class SecurityContextBuilder implements SerializerContextBuilderInterface
{
    public function __construct(
        private SerializerContextBuilderInterface $decorated,
        private Security $security,
        private NormalizerContextBuilderInterface $normalizerContextBuilder,
    ) {
    }

    public function createFromRequest(Request $request, bool $normalization, array $extractedAttributes = null): array
    {
        $context = $this->decorated->createFromRequest($request, $normalization, $extractedAttributes);

        $user = $this->security->getUser();
        $context['userId'] = $user instanceof JwtUser ? $user->getId() : null;
        $context['groupIds'] = $user instanceof JwtUser ? $user->getGroupIds() : [];
        $context['groupBy'] = $request->query->all('group');
        $context['filters'] = $request->query->get('filters');

        return $this->normalizerContextBuilder->buildContext($context);
    }
}
