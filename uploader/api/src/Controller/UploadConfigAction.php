<?php

declare(strict_types=1);

namespace App\Controller;

use Alchemy\StorageBundle\Upload\FileValidator;
use Alchemy\StorageBundle\Upload\MultipartUploadPlanner;
use App\Validation\CommitValidator;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

/**
 * What a batch may hold, for the clients to check it before sending it (the API checks it again): the clients
 * that do not read the stack configuration themselves, like the mobile app, and the holders of an upload link.
 */
#[AsController]
final readonly class UploadConfigAction
{
    final public const string ROUTE_NAME = 'upload_config';

    public function __construct(
        private MultipartUploadPlanner $planner,
        private CommitValidator $commitValidator,
        private FileValidator $fileValidator,
        #[Autowire(env: 'UPLOADER_CLIENT_URL')]
        private string $clientUrl,
    ) {
    }

    #[Route(path: '/upload-config', name: self::ROUTE_NAME, methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        return new JsonResponse([
            // In bytes
            'maxFileSize' => $this->planner->getMaxFileSize(),
            'maxCommitSize' => self::limit($this->commitValidator->getMaxCommitSize()),
            // 1: one file per batch
            'maxFileCount' => self::limit($this->commitValidator->getMaxFileCount()),
            // MIME type mask => allowed extensions (none: those of the type); empty: every file
            'allowedTypes' => (object) $this->fileValidator->getAllowedTypes(),
            // The web client, which serves the upload links (`/l/{token}`)
            'clientUrl' => rtrim($this->clientUrl, '/'),
        ]);
    }

    private static function limit(?int $value): ?int
    {
        return null !== $value && $value > 0 ? $value : null;
    }
}
