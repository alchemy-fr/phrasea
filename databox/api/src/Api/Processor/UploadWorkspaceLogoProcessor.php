<?php

declare(strict_types=1);

namespace App\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Core\Workspace;
use App\Service\Storage\MultipartUploadResolver;
use App\Service\Workspace\LogoManager;
use Doctrine\ORM\EntityManagerInterface;

final readonly class UploadWorkspaceLogoProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $em,
        private MultipartUploadResolver $multipartUploadResolver,
        private LogoManager $logoManager,
    ) {
    }

    /**
     * @param Workspace $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Workspace
    {
        $upload = $this->multipartUploadResolver->resolveFromRequest($context['request']);

        $this->logoManager->setLogo($data, $upload);
        $this->em->flush();

        return $data;
    }
}
