<?php

declare(strict_types=1);

namespace App\Api\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Publication;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Answers with a bare JSON boolean, so the response is built here instead of
 * going through the resource serialization.
 */
final readonly class PublicationSlugAvailabilityProvider implements ProviderInterface
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): JsonResponse
    {
        /** @var Request $request */
        $request = $context['request'];

        $publication = $this->em
            ->getRepository(Publication::class)
            ->findOneBy(['slug' => (string) $request->attributes->get('slug')]);

        return new JsonResponse(!$publication instanceof Publication);
    }
}
