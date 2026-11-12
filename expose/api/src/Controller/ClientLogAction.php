<?php

declare(strict_types=1);

namespace App\Controller;

use Alchemy\MessengerBundle\Listener\TerminateStackListener;
use Alchemy\ReportBundle\ReportUserService;
use App\Entity\Asset;
use App\Entity\Publication;
use App\Report\ExposeLogActionInterface;
use App\Security\Voter\AssetVoter;
use App\Security\Voter\PublicationVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '/logs', methods: ['POST'])]
class ClientLogAction
{
    public function __construct(
        private readonly ReportUserService $reportClient,
        private readonly TerminateStackListener $terminateStackListener,
        private readonly EntityManagerInterface $em,
        private readonly Security $security,
        #[Autowire(service: 'limiter.client_log')]
        private readonly RateLimiterFactoryInterface $clientLogLimiter,
    ) {
    }

    #[Route(path: '/publication-view/{id}')]
    public function logPublicationView(string $id, Request $request): Response
    {
        $this->denyTooManyRequests($request);
        $publication = $this->em->find(Publication::class, $id) ?? throw new NotFoundHttpException();
        $this->denyAccessUnlessGranted(PublicationVoter::READ, $publication);

        return $this->pushLog(
            $request,
            ExposeLogActionInterface::PUBLICATION_VIEW,
            $publication->getId()
        );
    }

    #[Route(path: '/asset-view/{id}')]
    public function logAssetView(string $id, Request $request): Response
    {
        $this->denyTooManyRequests($request);
        $asset = $this->em->find(Asset::class, $id) ?? throw new NotFoundHttpException();
        $this->denyAccessUnlessGranted(AssetVoter::READ, $asset);

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

    private function denyTooManyRequests(Request $request): void
    {
        $limit = $this->clientLogLimiter->create($request->getClientIp() ?? '')->consume();
        if (!$limit->isAccepted()) {
            throw new TooManyRequestsHttpException(max(0, $limit->getRetryAfter()->getTimestamp() - time()));
        }
    }

    private function denyAccessUnlessGranted(string $attribute, object $subject): void
    {
        if (!$this->security->isGranted($attribute, $subject)) {
            throw new AccessDeniedHttpException();
        }
    }

    private function pushLog(Request $request, string $action, ?string $item = null, array $payload = []): Response
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
