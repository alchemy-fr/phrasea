<?php

declare(strict_types=1);

namespace App\Api\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Asset;
use App\Entity\Publication;
use App\Security\Voter\PublicationVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class AssetBySlugProvider implements ProviderInterface
{
    public function __construct(
        private EntityManagerInterface $em,
        private Security $security,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): Asset
    {
        $publication = $this->em
            ->getRepository(Publication::class)
            ->findOneBy(['slug' => (string) $uriVariables['publicationSlug']]);

        // A publication the user may not read answers as an unknown one: slugs can be guessed.
        if (!$publication instanceof Publication || !$this->security->isGranted(PublicationVoter::READ, $publication)) {
            throw new NotFoundHttpException();
        }

        /** @var Asset|null $asset */
        $asset = $this->em
            ->getRepository(Asset::class)
            ->findBySlug($publication, (string) $uriVariables['assetSlug']);

        if (!$asset instanceof Asset) {
            throw new NotFoundHttpException();
        }

        return $asset;
    }
}
