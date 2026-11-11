<?php

declare(strict_types=1);

namespace App\Api\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\PublicationShare;
use App\Repository\PublicationShareRepository;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The share of a token, public: the same 404 for an unknown, expired or revoked token.
 */
final readonly class PublicationShareResolveProvider implements ProviderInterface
{
    public function __construct(private PublicationShareRepository $shareRepository)
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): PublicationShare
    {
        $token = (string) ($uriVariables['token'] ?? '');

        if ('' !== $token && strlen($token) <= PublicationShare::TOKEN_LENGTH) {
            foreach ($this->shareRepository->findActiveByTokens([$token]) as $share) {
                if (hash_equals($share->getToken(), $token)) {
                    return $share;
                }
            }
        }

        throw new NotFoundHttpException('Share link not found');
    }
}
