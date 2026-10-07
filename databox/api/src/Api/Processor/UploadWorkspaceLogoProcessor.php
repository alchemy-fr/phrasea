<?php

declare(strict_types=1);

namespace App\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Core\Workspace;
use App\Service\Storage\FileManager;
use App\Service\Storage\MultipartUploadResolver;
use App\Service\Workspace\LogoManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final readonly class UploadWorkspaceLogoProcessor implements ProcessorInterface
{
    private const int MAX_SIZE = 5 * 1024 * 1024;

    public function __construct(
        private EntityManagerInterface $em,
        private MultipartUploadResolver $multipartUploadResolver,
        private FileManager $fileManager,
        private LogoManager $logoManager,
    ) {
    }

    /**
     * @param Workspace $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Workspace
    {
        $upload = $this->multipartUploadResolver->resolveFromRequest($context['request']);

        if ($upload->getSize() > self::MAX_SIZE) {
            throw new BadRequestHttpException(sprintf('Logo must not exceed %d MB', self::MAX_SIZE / 1024 / 1024));
        }

        $file = $this->fileManager->createFileFromMultipartUpload($upload, $data);
        $this->logoManager->setLogo($data, $file);
        $this->em->flush();

        return $data;
    }
}
