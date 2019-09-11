<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\BulkData;
use App\Entity\BulkDataRepository;
use Doctrine\ORM\EntityManagerInterface;
use stdClass;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;

final class BulkDataAction extends AbstractController
{
    /**
     * @var BulkDataRepository
     */
    private $repository;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->repository = $entityManager->getRepository(BulkData::class);
    }

    public function __invoke()
    {
        $bulkData = $this->repository->getBulkData();

        return new JsonResponse(null !== $bulkData ? $bulkData->getData() : new stdClass());
    }
}
