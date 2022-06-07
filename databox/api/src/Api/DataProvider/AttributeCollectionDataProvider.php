<?php

declare(strict_types=1);

namespace App\Api\DataProvider;

use ApiPlatform\Core\DataProvider\ContextAwareCollectionDataProviderInterface;
use ApiPlatform\Core\DataProvider\RestrictedDataProviderInterface;
use App\Entity\Core\Asset;
use App\Entity\Core\Attribute;
use App\Security\Voter\AssetVoter;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Security;

class AttributeCollectionDataProvider implements ContextAwareCollectionDataProviderInterface, RestrictedDataProviderInterface
{
    private EntityManagerInterface $em;
    private Security $security;

    public function __construct(EntityManagerInterface $em, Security $security)
    {
        $this->em = $em;
        $this->security = $security;
    }

    public function getCollection(string $resourceClass, string $operationName = null, array $context = [])
    {
        $filters = $context['filters'] ?? [];
        if (!isset($filters['asset'])) {
            throw new BadRequestHttpException(sprintf('You must provide "asset" ID to filter out attributes'));
        }

        $asset = $this->em->find(Asset::class, $filters['asset']);
        if (!$asset instanceof Asset) {
            throw new NotFoundHttpException(sprintf('Asset "%s" does not exist', $asset));
        }

        if (!$this->security->isGranted(AssetVoter::READ, $asset)) {
            throw new AccessDeniedHttpException();
        }

        $criteria = [
            'asset' => $asset->getId(),
        ];

        return $this->em->getRepository(Attribute::class)->findBy($criteria, [
            'position' => 'ASC',
        ]);
    }

    public function supports(string $resourceClass, string $operationName = null, array $context = []): bool
    {
        return Attribute::class === $resourceClass;
    }
}
