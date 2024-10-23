<?php

declare(strict_types=1);

namespace App\Controller\Integration;

use Alchemy\Workflow\State\Repository\StateRepositoryInterface;
use Alchemy\Workflow\WorkflowOrchestrator;
use App\Asset\FileUrlResolver;
use App\Consumer\Handler\Phraseanet\PhraseanetDownloadSubdef;
use App\Entity\Core\Asset;
use App\Integration\IntegrationManager;
use App\Integration\Phraseanet\PhraseanetRenditionIntegration;
use App\Integration\Phraseanet\PhraseanetTokenManager;
use App\Storage\FileManager;
use App\Storage\RenditionManager;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Annotation\Route;

#[Route(path: '/integrations/phraseanet', name: 'integration_phraseanet_')]
class PhraseanetIntegrationController extends AbstractController
{
    final public const string ASSET_NAME_PREFIX = 'gen-sub-def-';

    #[Route(path: '/{integrationId}/renditions/incoming/{assetId}', name: 'incoming_rendition', methods: ['POST'])]
    public function incomingRenditionAction(
        string $integrationId,
        string $assetId,
        Request $request,
        RenditionManager $renditionManager,
        FileManager $fileManager,
        PhraseanetTokenManager $tokenManager,
        EntityManagerInterface $em,
        WorkflowOrchestrator $workflowOrchestrator,
    ): Response {
        $token = $request->request->get('token');
        if (!$token) {
            throw new UnauthorizedHttpException('Missing token');
        }

        $workflowId = $tokenManager->validateToken($assetId, $token);

        ini_set('max_execution_time', '600');
        $fileInfo = $request->request->all('file_info');
        if (empty($fileInfo)) {
            throw new BadRequestHttpException('Missing "file_info"');
        }
        $name = $fileInfo['name'] ?? null;
        $uploadedFile = $request->files->get('file');

        if (empty($name)) {
            throw new BadRequestHttpException('Missing name');
        }

        if (!$uploadedFile instanceof UploadedFile) {
            throw new BadRequestHttpException('Missing file');
        }
        if (!$uploadedFile->isValid()) {
            throw new BadRequestHttpException('Invalid uploaded file');
        }
        if (0 === $uploadedFile->getSize()) {
            throw new BadRequestHttpException('Empty file');
        }

        $asset = $em->getRepository(Asset::class)
            ->find($assetId);

        if (!$asset instanceof Asset) {
            throw new NotFoundHttpException(sprintf('Asset "%s" not found', $assetId));
        }

        try {
            $definition = $renditionManager->getRenditionDefinitionByName($asset->getWorkspace(), $name);
        } catch (\InvalidArgumentException $e) {
            throw new BadRequestHttpException(sprintf('Undefined rendition definition "%s"', $name), $e);
        }

        $file = $fileManager->createFileFromPath(
            $asset->getWorkspace(),
            $uploadedFile->getRealPath(),
            $uploadedFile->getMimeType(),
            null,
            $uploadedFile->getClientOriginalName()
        );

        $renditionManager->createOrReplaceRenditionFile(
            $asset,
            $definition,
            $file,
            null,
            null,
        );

        $em->flush();

        $workflowOrchestrator->continueJob(
            $workflowId,
            PhraseanetRenditionIntegration::getRenditionJobId($integrationId, $definition->getName()),
            ['built' => 1]
        );

        return new Response();
    }

    #[Route(path: '/{integrationId}/events', name: 'webhook_event', methods: ['POST'])]
    public function webhookEventAction(
        $integrationId,
        Request $request,
        MessageBusInterface $bus,
        LoggerInterface $logger,
        WorkflowOrchestrator $workflowOrchestrator,
        StateRepositoryInterface $workflowStateRepository,
    ): Response {
        $json = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);

        switch ($json['event']) {
            case 'record.subdef.created':
                $data = $json['data'];
                $uuidRegex = '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}';
                if (1 === preg_match('#^'.preg_quote(self::ASSET_NAME_PREFIX, '#').'('.$uuidRegex.')_('.$uuidRegex.')(\..+)?$#', (string) $data['original_name'], $groups)) {
                    [, $assetId, $workflowId] = $groups;

                    $logger->debug(sprintf('Received webhook "%s" for workflow "%s"', $json['event'], $workflowId));

                    try {
                        $workflowState = $workflowStateRepository->getWorkflowState($workflowId);
                    } catch (\InvalidArgumentException) {
                        break;
                    }
                    $inputs = $workflowState->getEvent()->getInputs();
                    if ($assetId !== $inputs['assetId']) {
                        break;
                    }
                    $assetId = $inputs['assetId'];
                    $workflowOrchestrator->continueJob(
                        $workflowId,
                        PhraseanetRenditionIntegration::getRenditionJobId($integrationId, $data['subdef_name']),
                        ['built' => 1]
                    );

                    // TODO Temporary hack
                    $url = preg_replace('#^http://localhost/#', 'https://'.$json['url'].'/', (string) $data['permalink']);

                    $logger->debug(sprintf('URL: %s', $url));
                    $bus->dispatch(new PhraseanetDownloadSubdef(
                        $assetId,
                        (string) $data['databox_id'],
                        (string) $data['record_id'],
                        $data['subdef_name'],
                        $url,
                        $data['type'],
                        (int) $data['size']
                    ));
                }

                break;
            default:
                break;
        }

        return new Response();
    }

    #[Route(path: '/{integrationId}/workflows/{workflowId}/assets/{assetId}', name: 'asset', methods: ['GET'])]
    public function assetAction(
        $integrationId,
        string $workflowId,
        string $assetId,
        FileUrlResolver $fileUrlResolver,
        EntityManagerInterface $em,
        LoggerInterface $logger,
        IntegrationManager $integrationManager,
        StateRepositoryInterface $workflowStateRepository,
        PhraseanetTokenManager $tokenManager,
        Request $request,
    ): Response {
        $logger->debug(sprintf('Fetch asset "%s" from Phraseanet enqueue', $assetId));

        $auth = $request->headers->get('Authorization', '');
        if (1 !== preg_match('#^AssetToken (.+)$#', $auth, $matches)) {
            throw new UnauthorizedHttpException('Missing AssetToken authorization');
        }
        $assetToken = $matches[1];

        $integration = $integrationManager->loadIntegration($integrationId);
        $options = $integrationManager->getIntegrationConfiguration($integration);

        $asset = $em->find(Asset::class, $assetId);
        if (!$asset instanceof Asset) {
            throw new NotFoundHttpException(sprintf('Asset "%s" not found for Phraseanet enqueue', $assetId));
        }

        $tokenManager->validateToken($asset->getId(), $assetToken);

        return new JsonResponse([
            'id' => $asset->getId(),
            'originalName' => sprintf('%s%s_%s.%s', self::ASSET_NAME_PREFIX, $asset->getId(), $workflowId, $asset->getSource()->getExtension()),
            'url' => $fileUrlResolver->resolveUrl($asset->getSource()),
            'formData' => [
                'collection_destination' => $options['collectionId'],
            ],
        ]);
    }

    #[Route(path: '/{integrationId}/workflows/{workflowId}/commits/{assetId}/ack', name: 'enqueue_ack', methods: ['POST'])]
    public function enqueueAckAction(
        string $integrationId,
        string $assetId,
        LoggerInterface $logger,
    ): Response {
        $logger->debug(sprintf('Phraseanet enqueue acknowledgement received for asset "%s"', $assetId));

        return new Response();
    }

    #[Route(path: '/{integrationId}/workflows/{workflowId}/assets/{assetId}/ack', name: 'enqueue_asset_ack', methods: ['POST'])]
    public function enqueueAssetAckAction(
        string $integrationId,
        string $assetId,
        LoggerInterface $logger,
    ): Response {
        $logger->debug(sprintf('Phraseanet enqueue acknowledgement received for asset ID "%s"', $assetId));

        return new Response();
    }
}
