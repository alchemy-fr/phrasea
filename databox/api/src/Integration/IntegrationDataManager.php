<?php

declare(strict_types=1);

namespace App\Integration;

use App\Entity\Core\Asset;
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

    public function storeValue(WorkspaceIntegration $workspaceIntegration, ?Asset $asset, string $name, string $value): void
    {
        $data = new IntegrationData();
        $data->setIntegration($workspaceIntegration);
        $data->setAsset($asset);
        $data->setName($name);
        $data->setValue($value);

        $this->em->persist($data);
        $this->em->flush($data);
    }

    public function hasValue(WorkspaceIntegration $workspaceIntegration, ?Asset $asset, string $name): bool
    {
        $data = $this->em->getRepository(IntegrationData::class)
            ->findOneBy([
                'integration' => $workspaceIntegration->getId(),
                'asset' => $asset,
                'name' => $name,
            ]);

        return $data instanceof IntegrationData;
    }
}
