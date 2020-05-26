<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Asset;
use App\Entity\Publication;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class GetAssetWithSlugAction extends AbstractController
{
    /**
     * @var EntityManagerInterface
     */
    private $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    public function __invoke(string $publicationSlug, string $assetSlug): Asset
    {
        $publication = $this->em
            ->getRepository(Publication::class)
            ->findOneBy(['slug' => $publicationSlug]);

        if (
            !$publication instanceof Publication
            || !$publication->getConfig()->isEnabled() && !$this->isGranted('ROLE_ADMIN')
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
