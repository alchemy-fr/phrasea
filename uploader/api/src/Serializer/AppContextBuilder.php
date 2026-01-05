<?php

declare(strict_types=1);

namespace App\Serializer;

use Alchemy\AuthBundle\Security\JwtUser;
use ApiPlatform\State\SerializerContextBuilderInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\HttpFoundation\Request;

#[AsDecorator(decorates: 'api_platform.serializer.context_builder')]
readonly class AppContextBuilder implements SerializerContextBuilderInterface
{
    public function __construct(
        private SerializerContextBuilderInterface $decorated,
        private Security $security,
        private GroupNormalizerContextBuilder $normalizerContextBuilder,
    ) {
    }

    public function createFromRequest(Request $request, bool $normalization, ?array $extractedAttributes = null): array
    {
        $context = $this->decorated->createFromRequest($request, $normalization, $extractedAttributes);

        $user = $this->security->getUser();
        $context['userId'] = $user instanceof JwtUser ? $user->getId() : null;
        $context['groupIds'] = $user instanceof JwtUser ? $user->getGroups() : [];
        $context['groupBy'] = $request->query->all('group');
        $context['filters'] = $request->query->get('filters');

        return $this->normalizerContextBuilder->buildContext($context);
    }
}
