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
    public function __construct(private readonly SerializerContextBuilderInterface $decorated, private readonly Security $security, private readonly NormalizerContextBuilderInterface $normalizerContextBuilder)
    {
    }

    public function createFromRequest(Request $request, bool $normalization, ?array $extractedAttributes = null): array
    {
        $context = $this->decorated->createFromRequest($request, $normalization, $extractedAttributes);

        $user = $this->security->getUser();
        $context['userId'] = $user instanceof RemoteUser ? $user->getId() : null;
        $context['groupIds'] = $user instanceof RemoteUser ? $user->getGroupIds() : [];
        $context['groupBy'] = $request->query->get('group');
        $context['filters'] = $request->query->get('filters');

        return $this->normalizerContextBuilder->buildContext($context);
    }
}
