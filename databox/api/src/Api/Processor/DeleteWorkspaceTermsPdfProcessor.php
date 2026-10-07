<?php

declare(strict_types=1);

namespace App\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Core\Workspace;
use App\Service\Workspace\TermsManager;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DeleteWorkspaceTermsPdfProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $em,
        private TermsManager $termsManager,
    ) {
    }

    /**
     * @param Workspace $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): null
    {
        $this->termsManager->removeTermsPdf($data);
        $this->em->flush();

        return null;
    }
}
