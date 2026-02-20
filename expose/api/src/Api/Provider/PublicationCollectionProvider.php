<?php

declare(strict_types=1);

namespace App\Api\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Publication;
use Doctrine\ORM\EntityManagerInterface;

final readonly class PublicationCollectionProvider implements ProviderInterface
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $publications = $this->em->getRepository(Publication::class)
            ->createQueryBuilder('p')
            ->getQuery()
            ->getResult();

        $response = [];
        foreach ($publications as $publication) {
            $cover = $publication->getCover();
            if (null == $cover) {
                $cover = $publication->getAssets()->toArray()[0] ?? null;
            }

            $response[] = [
                'id' => $publication->getId(),
                'title' => $publication->getTitle(),
                'description' => $publication->getDescription(),
                'translations' => $publication->getTranslations(),
                'cover' => $cover,
                'slug' => $publication->getSlug(),
                'config' => $publication->getConfig(),
                'authorized' => $publication->isAuthorized(),
                'securityContainerId' => $publication->getSecurityContainerId(),
                'authorizationError' => $publication->getAuthorizationError(),
                'capabilities' => $publication->getCapabilities(),
            ];
        }

        return $response;
    }
}
