<?php

declare(strict_types=1);

namespace App\Controller;

use Alchemy\AuthBundle\Security\JwtUser;
use App\Entity\Asset;
use App\Entity\Publication;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class GetAssetWithSlugAction extends AbstractController
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function __invoke(string $publicationSlug, string $assetSlug): Asset
    {
        $publication = $this->em
            ->getRepository(Publication::class)
            ->findOneBy(['slug' => $publicationSlug]);

        if (
            !$publication instanceof Publication
            || !$publication->getConfig()->isEnabled() && !$this->isGranted(JwtUser::ROLE_ADMIN)
        ) {
            throw new NotFoundHttpException();
        }

        /** @var Asset|null $asset */
        $asset = $this->em
            ->getRepository(Asset::class)
            ->findBySlug($publication, $assetSlug);

        if (!$asset instanceof Asset) {
            throw new NotFoundHttpException();
        }

        return $asset;
    }
}
