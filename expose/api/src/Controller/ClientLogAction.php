<?php

declare(strict_types=1);

namespace App\Controller;

use Alchemy\ReportBundle\ReportUserService;
use App\Entity\Asset;
use App\Entity\Publication;
use App\Listener\TerminateStackListener;
use App\Report\ExposeLogActionInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route(path="/logs", methods={"POST"})
 */
class ClientLogAction
{
    public function __construct(
        private readonly ReportUserService $reportClient,
        private readonly TerminateStackListener $terminateStackListener,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * @Route(path="/publication-view/{id}")
     */
    public function logPublicationView(string $id, Request $request): Response
    {
        $publication = $this->em->find(Publication::class, $id) ?? throw new NotFoundHttpException();

        return $this->pushLog(
            $request,
            ExposeLogActionInterface::PUBLICATION_VIEW,
            $publication->getId()
        );
    }

    /**
     * @Route(path="/asset-view/{id}")
     */
    public function logAssetView(string $id, Request $request): Response
    {
        $asset = $this->em->find(Asset::class, $id) ?? throw new NotFoundHttpException();

        $payload = [];
        if (null !== $asset->getAssetId()) {
            $payload['trackingId'] = $asset->getAssetId();
        }

        return $this->pushLog(
            $request,
            ExposeLogActionInterface::ASSET_VIEW,
            $asset->getId(),
            $payload
        );
    }

    private function pushLog(Request $request, string $action, string $item = null, array $payload = []): Response
    {
        $this->terminateStackListener->addCallback(function () use ($request, $action, $item, $payload): void {
            $this->reportClient->pushHttpRequestLog(
                $request,
                $action,
                $item,
                $payload
            );
        });

        return new JsonResponse(true);
    }
}
