<?php

declare(strict_types=1);

namespace App\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Core\Workspace;
use App\Service\Workspace\LogoManager;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DeleteWorkspaceLogoProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $em,
        private LogoManager $logoManager,
    ) {
    }

    /**
     * @param Workspace $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): null
    {
        $this->logoManager->removeLogo($data);
        $this->em->flush();

        return null;
    }
}
