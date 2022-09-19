<?php

declare(strict_types=1);

namespace App\Integration;

use App\Entity\Core\File;
use App\Entity\Integration\IntegrationData;
use App\Entity\Integration\WorkspaceIntegration;
use Doctrine\ORM\EntityManagerInterface;

class IntegrationDataManager
{
    private EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    public function storeData(WorkspaceIntegration $workspaceIntegration, ?File $file, string $name, string $value): void
    {
        $data = new IntegrationData();
        $data->setIntegration($workspaceIntegration);
        $data->setFile($file);
        $data->setName($name);
        $data->setValue($value);

        $this->em->persist($data);
        $this->em->flush($data);
    }

    public function hasData(WorkspaceIntegration $workspaceIntegration, ?File $file, string $name): bool
    {
        $data = $this->em->getRepository(IntegrationData::class)
            ->findOneBy([
                'integration' => $workspaceIntegration->getId(),
                'file' => $file,
                'name' => $name,
            ]);

        return $data instanceof IntegrationData;
    }

    public function getData(WorkspaceIntegration $workspaceIntegration, ?File $file, string $name): ?IntegrationData
    {
        return $this->em->getRepository(IntegrationData::class)
            ->findOneBy([
                'integration' => $workspaceIntegration->getId(),
                'file' => $file,
                'name' => $name,
            ]);
    }
}
