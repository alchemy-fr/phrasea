<?php

declare(strict_types=1);

namespace App\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Core\Workspace;
use App\Service\Storage\FileManager;
use App\Service\Storage\MultipartUploadResolver;
use App\Service\Workspace\TermsManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final readonly class UploadWorkspaceTermsPdfProcessor implements ProcessorInterface
{
    private const int MAX_SIZE = 20 * 1024 * 1024;

    public function __construct(
        private EntityManagerInterface $em,
        private MultipartUploadResolver $multipartUploadResolver,
        private FileManager $fileManager,
        private TermsManager $termsManager,
    ) {
    }

    /**
     * @param Workspace $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Workspace
    {
        $upload = $this->multipartUploadResolver->resolveFromRequest($context['request']);

        if ('application/pdf' !== $upload->getType()) {
            throw new BadRequestHttpException(sprintf('Terms file must be a PDF, got "%s"', $upload->getType()));
        }
        if ($upload->getSize() > self::MAX_SIZE) {
            throw new BadRequestHttpException(sprintf('Terms PDF must not exceed %d MB', self::MAX_SIZE / 1024 / 1024));
        }

        $file = $this->fileManager->createFileFromMultipartUpload($upload, $data);
        $this->termsManager->setTermsPdfFromFile($data, $file);
        $this->em->flush();

        return $data;
    }
}
