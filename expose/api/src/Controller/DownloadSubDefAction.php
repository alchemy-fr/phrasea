<?php

declare(strict_types=1);

namespace App\Controller;

use Alchemy\ReportBundle\ReportUserService;
use App\Entity\Publication;
use App\Entity\PublicationAsset;
use App\Entity\SubDefinition;
use App\Report\ExposeLogActionInterface;
use App\Security\AssetUrlGenerator;
use App\Security\Voter\PublicationVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/publications/{publicationId}/subdef/{subDefId}/download", name="download_subdef", methods={"GET"})
 */
final class DownloadSubDefAction extends AbstractController
{
    private EntityManagerInterface $em;
    private ReportUserService $reportClient;
    private AssetUrlGenerator $assetUrlGenerator;

    public function __construct(EntityManagerInterface $em, ReportUserService $reportClient, AssetUrlGenerator $assetUrlGenerator)
    {
        $this->em = $em;
        $this->reportClient = $reportClient;
        $this->assetUrlGenerator = $assetUrlGenerator;
    }

    public function __invoke(string $publicationId, string $subDefId, Request $request): RedirectResponse
    {
        /** @var Publication|null $publication */
        $publication = $this->em->getRepository(Publication::class)->find($publicationId);

        if (!$publication instanceof Publication) {
            throw new NotFoundHttpException();
        }

        $this->denyAccessUnlessGranted(PublicationVoter::READ_DETAILS, $publication);

        $subDef = $this->em->getRepository(SubDefinition::class)->find($subDefId);
        if (!$subDef instanceof SubDefinition) {
            throw new NotFoundHttpException(sprintf('Sub def "%s" not found', $subDefId));
        }

        $publicationAsset = $this->em->getRepository(PublicationAsset::class)
            ->findOneBy([
                'publication' => $publication->getId(),
                'asset' => $subDef->getAsset()->getId(),
            ]);

        if (!$publicationAsset instanceof PublicationAsset) {
            throw new NotFoundHttpException('PublicationAsset not found');
        }

        $asset = $publicationAsset->getAsset();

        $this->reportClient->pushHttpRequestLog(
            $request,
            ExposeLogActionInterface::ASSET_DOWNLOAD,
            $asset->getId(),
            [
                'publicationId' => $publication->getId(),
                'publicationTitle' => $publication->getTitle(),
                'assetTitle' => $asset->getTitle(),
                'subDefinitionName' => $subDef->getName(),
            ]
        );

        return new RedirectResponse($this->assetUrlGenerator->generateSubDefinitionUrl($subDef, true));
    }
}
