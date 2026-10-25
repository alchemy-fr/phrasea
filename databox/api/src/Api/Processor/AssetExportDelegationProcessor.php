<?php

declare(strict_types=1);

namespace App\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Core\AssetExport;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final readonly class AssetExportDelegationProcessor implements ProcessorInterface
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    /** @param AssetExport $data */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): AssetExport
    {
        if (null === $data->getDelegateUserId() || null === $data->getDelegationHash()) {
            throw new BadRequestHttpException('Delegated export requires both recipient and capability hash');
        }

        $this->em->flush();

        return $data;
    }
}
