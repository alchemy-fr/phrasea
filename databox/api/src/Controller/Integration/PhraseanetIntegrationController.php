<?php

declare(strict_types=1);

namespace App\Controller\Integration;

use Alchemy\StorageBundle\Storage\FileStorageManager;
use App\Asset\FileUrlResolver;
use App\Consumer\Handler\Phraseanet\PhraseanetDownloadSubdefHandler;
use App\Consumer\Handler\Phraseanet\PhraseanetGenerateAssetRenditionsEnqueueMethodHandler;
use App\Entity\Core\Asset;
use App\Entity\Core\File;
use App\Integration\IntegrationManager;
use App\Security\JWTTokenManager;
use App\Storage\FileManager;
use App\Storage\RenditionManager;
use Arthem\Bundle\RabbitBundle\Producer\EventProducer;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route(path="/integrations/phraseanet", name="integration_phraseanet_")
 */
class PhraseanetIntegrationController extends AbstractController
{
    public const ASSET_NAME_PREFIX = 'gen-sub-def-';

    /**
     * @Route(path="/{integrationId}/renditions/incoming/{assetId}", methods={"POST"}, name="incoming_rendition")
     */
    public function incomingRenditionAction(
        string $integrationId,
        string $assetId,
        Request $request,
        RenditionManager $renditionManager,
        FileManager $fileManager,
        FileStorageManager $storageManager,
        JWTTokenManager $JWTTokenManager,
        EntityManagerInterface $em
    ): Response {
        $token = $request->request->get('token');
        if (!$token) {
            throw new UnauthorizedHttpException('Missing token');
        }
        try {
            $JWTTokenManager->validateToken($assetId, $token);
        } catch (\InvalidArgumentException $e) {
            throw new AccessDeniedHttpException('Invalid token', $e);
        }

        ini_set('max_execution_time', '600');
        $fileInfo = $request->request->get('file_info');
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
        } catch (InvalidArgumentException $e) {
            throw new BadRequestHttpException(sprintf('Undefined rendition definition "%s"', $name), $e);
        }

        $path = $fileManager->storeFile(
            $asset->getWorkspace(),
            $uploadedFile->getRealPath(),
            $uploadedFile->getType(),
            null,
            $uploadedFile->getClientOriginalName()
        );

        $renditionManager->createOrReplaceRendition(
            $asset,
            $definition,
            File::STORAGE_S3_MAIN,
            $path,
            $uploadedFile->getMimeType(),
            $uploadedFile->getSize(),
            $uploadedFile->getClientOriginalName()
        );

        $em->flush();

        return new Response();
    }

    /**
     * @Route(path="/{integrationId}/events", methods={"POST"}, name="webhook_event")
     */
    public function webhookEventAction(
        Request $request,
        EventProducer $eventProducer,
        LoggerInterface $logger
    ): Response {
        $json = \GuzzleHttp\json_decode($request->getContent(), true);

        switch ($json['event']) {
            case 'record.subdef.created':
                $data = $json['data'];
                if (1 === preg_match('#^'.preg_quote(self::ASSET_NAME_PREFIX, '#').'([0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12})(\..+)?$#', $data['original_name'], $groups)) {
                    $assetId = $groups[1];

                    $logger->debug(sprintf('Received webhook "%s" for asset "%s"', $json['event'], $assetId));

                    // TODO Temporary hack
                    $url = preg_replace('#^http://localhost/#', 'https://'.$json['url'].'/', $data['permalink']);

                    $logger->debug(sprintf('URL: %s', $url));
                    $eventProducer->publish(PhraseanetDownloadSubdefHandler::createEvent(
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

    /**
     * @Route(path="/{integrationId}/assets/{id}", methods={"GET"}, name="asset")
     */
    public function assetAction(
        $integrationId,
        string $id,
        FileUrlResolver $fileUrlResolver,
        EntityManagerInterface $em,
        LoggerInterface $logger,
        IntegrationManager $integrationManager,
        Request $request
    ): Response {
        $logger->debug(sprintf('Fetch asset "%s" from Phraseanet enqueue', $id));

        $auth = $request->headers->get('Authorization', '');
        if (1 !== preg_match('#^AssetToken (.+)$#', $auth, $matches)) {
            throw new UnauthorizedHttpException('Missing AssetToken authorization');
        }
        $assetToken = $matches[1];

        $integration = $integrationManager->loadIntegration($integrationId);
        $options = $integrationManager->getIntegrationConfiguration($integration);

        $asset = $em->find(Asset::class, $id);
        if (!$asset instanceof Asset) {
            throw new NotFoundHttpException(sprintf('Asset "%s" not found for Phraseanet enqueue', $id));
        }
        if ($assetToken !== PhraseanetGenerateAssetRenditionsEnqueueMethodHandler::generateAssetToken($asset)) {
            throw new AccessDeniedHttpException('Invalid Asset token');
        }

        return new JsonResponse([
            'id' => $asset->getId(),
            'originalName' => sprintf('%s%s.%s', self::ASSET_NAME_PREFIX, $asset->getId(), $asset->getFile()->getExtension()),
            'url' => $fileUrlResolver->resolveUrl($asset->getFile()),
            'formData' => [
                'collection_destination' => $options['collectionId'],
            ],
        ]);
    }

    /**
     * @Route(path="/{integrationId}/commits/{id}/ack", methods={"POST"}, name="enqueue_ack")
     */
    public function enqueueAckAction(
        string $integrationId,
        string $id,
        LoggerInterface $logger
    ): Response {
        $logger->debug(sprintf('Phraseanet enqueue acknowledgement received for asset "%s"', $id));

        return new Response();
    }
}
