<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\BulkData;
use App\Model\BulkDataModel;
use App\Entity\BulkDataRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;

final class BulkDataEditAction extends AbstractController
{
    /**
     * @var BulkDataRepository
     */
    private $repository;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->repository = $entityManager->getRepository(BulkData::class);
    }

    public function __invoke(BulkDataModel $data)
    {
        $this->repository->persistBulkData($data->getData());

        return new JsonResponse(true);
    }
}
