<?php

declare(strict_types=1);

namespace App\Controller;

use Alchemy\ReportBundle\ReportUserService;
use App\Entity\Asset;
use App\Entity\Publication;
use App\Entity\SubDefinition;
use App\Security\AssetUrlGenerator;
use App\Security\Voter\PublicationVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class AbstractAssetAction extends AbstractController
{
    protected EntityManagerInterface $em;
    protected ReportUserService $reportClient;
    protected AssetUrlGenerator $assetUrlGenerator;

    protected function getPublication(string $publicationId, $permission = PublicationVoter::READ_DETAILS): Publication
    {
        $publication = $this->em
            ->getRepository(Publication::class)
            ->find($publicationId);

        if (!$publication instanceof Publication) {
            throw new NotFoundHttpException();
        }

        $this->denyAccessUnlessGranted($permission, $publication);

        return $publication;
    }

    protected function getAssetOfPublication(string $assetId, Publication $publication): Asset
    {
        $asset = $this->em->getRepository(Asset::class)
            ->find($assetId);

        if (!$asset instanceof Asset) {
            throw new NotFoundHttpException(sprintf('Asset "%s" not found', $assetId));
        }

        if ($asset->getPublication() !== $publication) {
            throw new NotFoundHttpException(sprintf('Asset "%s" is not from publication "%s"', $asset->getId(), $publication->getId()));
        }

        return $asset;
    }

    protected function getSubDefOfPublication(string $subDefId, Publication $publication): SubDefinition
    {
        $subDef = $this->em->getRepository(SubDefinition::class)->find($subDefId);
        if (!$subDef instanceof SubDefinition) {
            throw new NotFoundHttpException(sprintf('Sub def "%s" not found', $subDefId));
        }

        if ($subDef->getAsset()->getPublication() !== $publication) {
            throw new NotFoundHttpException(sprintf('SubDef "%s" is not from publication "%s"', $subDef->getId(), $publication->getId()));
        }

        return $subDef;
    }

    #[\Symfony\Contracts\Service\Attribute\Required]
    public function setEm(EntityManagerInterface $em): void
    {
        $this->em = $em;
    }

    #[\Symfony\Contracts\Service\Attribute\Required]
    public function setReportClient(ReportUserService $reportClient): void
    {
        $this->reportClient = $reportClient;
    }

    #[\Symfony\Contracts\Service\Attribute\Required]
    public function setAssetUrlGenerator(AssetUrlGenerator $assetUrlGenerator): void
    {
        $this->assetUrlGenerator = $assetUrlGenerator;
    }
}
