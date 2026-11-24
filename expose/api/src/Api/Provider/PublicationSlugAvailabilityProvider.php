<?php

declare(strict_types=1);

namespace App\Api\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Publication;
use App\Security\Voter\PublicationVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Answers with a bare JSON boolean, so the response is built here instead of
 * going through the resource serialization.
 *
 * Reserved to the users who may create a publication: the answer tells whether a
 * publication exists, readable or not.
 */
final readonly class PublicationSlugAvailabilityProvider implements ProviderInterface
{
    public function __construct(
        private EntityManagerInterface $em,
        private Security $security,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): JsonResponse
    {
        if (!$this->security->isGranted(PublicationVoter::CREATE, new Publication())) {
            throw new AccessDeniedHttpException('You are not allowed to create publications.');
        }

        /** @var Request $request */
        $request = $context['request'];

        $publication = $this->em
            ->getRepository(Publication::class)
            ->findOneBy(['slug' => (string) $request->attributes->get('slug')]);

        return new JsonResponse(!$publication instanceof Publication);
    }
}
