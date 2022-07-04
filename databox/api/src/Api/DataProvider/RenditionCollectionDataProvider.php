<?php

declare(strict_types=1);

namespace App\Api\DataProvider;

use ApiPlatform\Core\DataProvider\ContextAwareCollectionDataProviderInterface;
use ApiPlatform\Core\DataProvider\RestrictedDataProviderInterface;
use App\Entity\Core\Asset;
use App\Entity\Core\AssetRendition;
use App\Security\Voter\AssetVoter;
use App\Security\Voter\RenditionVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Security;

class RenditionCollectionDataProvider implements ContextAwareCollectionDataProviderInterface, RestrictedDataProviderInterface
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
        if (!isset($filters['assetId'])) {
            throw new BadRequestHttpException(sprintf('You must provide "assetId" to filter out attributes'));
        }

        $asset = $this->em->find(Asset::class, $filters['assetId']);
        if (!$asset instanceof Asset) {
            throw new NotFoundHttpException(sprintf('Asset "%s" does not exist', $asset));
        }

        if (!$this->security->isGranted(AssetVoter::READ, $asset)) {
            throw new AccessDeniedHttpException('Cannot read asset');
        }

        $renditions = $this->em->getRepository(AssetRendition::class)
            ->createQueryBuilder('t')
            ->andWhere('t.asset = :a')
            ->setParameter('a', $asset->getId())
            ->innerJoin('t.definition', 'd')
            ->addOrderBy('d.priority', 'DESC')
            ->getQuery()
            ->getResult()
        ;

        return array_filter($renditions, function (AssetRendition $rendition): bool {
            return $this->security->isGranted(RenditionVoter::READ, $rendition);
        });
    }

    public function supports(string $resourceClass, string $operationName = null, array $context = []): bool
    {
        return AssetRendition::class === $resourceClass;
    }
}
