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

    public function storeData(WorkspaceIntegration $workspaceIntegration, ?File $file, string $name, string $value, ?string $keyId = null, bool $multiple = false): void
    {
        $data = null;
        if (!$multiple || null !== $keyId) {
            $data = $this->getData($workspaceIntegration, $file, $name, $keyId);
        }

        if (null === $data) {
            $data = new IntegrationData();
            $data->setIntegration($workspaceIntegration);
            $data->setFile($file);
            $data->setName($name);
        }
        $data->setValue($value);
        $data->setKeyId($keyId);

        $this->em->persist($data);
        $this->em->flush($data);
    }

    public function hasData(WorkspaceIntegration $workspaceIntegration, ?File $file, string $name, ?string $keyId = null): bool
    {
        $criteria = [
            'integration' => $workspaceIntegration->getId(),
            'file' => $file,
            'name' => $name,
        ];
        if (null !== $keyId) {
            $criteria['keyId'] = $keyId;
        }

        $data = $this->em->getRepository(IntegrationData::class)
            ->findOneBy($criteria);

        return $data instanceof IntegrationData;
    }

    /**
     * @return IntegrationData|IntegrationData[]|null
     */
    public function getData(WorkspaceIntegration $workspaceIntegration, ?File $file, string $name, ?string $keyId = null, bool $multiple = false)
    {
        $repository = $this->em->getRepository(IntegrationData::class);

        $criteria = [
            'integration' => $workspaceIntegration->getId(),
            'file' => $file ? $file->getId() : null,
            'name' => $name,
        ];

        if (null !== $keyId) {
            $criteria['keyId'] = $keyId;
        }

        if ($multiple) {
            return $repository->findBy($criteria);
        } else {
            dump($criteria);
            return $repository->findOneBy($criteria);
        }
    }
}
