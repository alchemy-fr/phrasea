<?php

declare(strict_types=1);

namespace App\Integration;

use App\Entity\AbstractUuidEntity;
use App\Entity\Basket\Basket;
use App\Entity\Core\File;
use App\Entity\Integration\AbstractIntegrationData;
use App\Entity\Integration\IntegrationBasketData;
use App\Entity\Integration\IntegrationFileData;
use App\Entity\Integration\WorkspaceIntegration;
use Doctrine\ORM\EntityManagerInterface;

readonly class IntegrationDataManager
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function getWorkspaceIntegration(string $id): WorkspaceIntegration
    {
        $workspaceIntegration = $this->em->find(WorkspaceIntegration::class, $id);
        if (!$workspaceIntegration instanceof WorkspaceIntegration) {
            throw new \InvalidArgumentException(sprintf('WorkspaceIntegration %s not found', $id));
        }

        return $workspaceIntegration;
    }

    public function storeFileData(WorkspaceIntegration $workspaceIntegration, ?File $file, string $name, string $value, ?string $keyId = null, bool $multiple = false): IntegrationFileData
    {
        return $this->storeData(IntegrationFileData::class, $workspaceIntegration, $file, $name, $value, $keyId, $multiple);
    }

    public function storeBasketData(WorkspaceIntegration $workspaceIntegration, ?Basket $basket, string $name, string $value, ?string $keyId = null, bool $multiple = false): IntegrationBasketData
    {
        return $this->storeData(IntegrationBasketData::class, $workspaceIntegration, $basket, $name, $value, $keyId, $multiple);
    }

    /**
     * @template T
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    public function storeData(string $class, WorkspaceIntegration $workspaceIntegration, ?AbstractUuidEntity $object, string $name, string $value, ?string $keyId = null, bool $multiple = false): AbstractIntegrationData
    {
        $data = null;
        if (!$multiple || null !== $keyId) {
            $data = $this->getData($class, $workspaceIntegration, $object, $name, $keyId);
        }

        if (null === $data) {
            /** @var AbstractIntegrationData $data */
            $data = new $class;
            $data->setIntegration($workspaceIntegration);
            $data->setObject($object);
            $data->setName($name);
        }
        $data->setValue($value);
        $data->setKeyId($keyId);

        $this->em->persist($data);
        $this->em->flush($data);

        return $data;
    }

    /**
     * @return IntegrationFileData|IntegrationFileData[]|null
     */
    public function getFileData(WorkspaceIntegration $workspaceIntegration, ?File $file, string $name, ?string $keyId = null, bool $multiple = false): IntegrationFileData|array|null
    {
        return $this->getData(IntegrationFileData::class, $workspaceIntegration, $file, $name, $keyId, $multiple);
    }

    /**
     * @return IntegrationBasketData|IntegrationBasketData[]|null
     */
    public function getBasketData(WorkspaceIntegration $workspaceIntegration, ?Basket $basket, string $name, ?string $keyId = null, bool $multiple = false): IntegrationBasketData|array|null
    {
        return $this->getData(IntegrationBasketData::class, $workspaceIntegration, $basket, $name, $keyId, $multiple);
    }

    /**
     * @template T
     *
     * @param class-string<T> $class
     *
     * @return T|T[]|null
     */
    private function getData(string $class, WorkspaceIntegration $workspaceIntegration, ?AbstractUuidEntity $object, string $name, ?string $keyId = null, bool $multiple = false): object|array|null
    {
        $repository = $this->em->getRepository($class);

        $criteria = [
            'integration' => $workspaceIntegration->getId(),
            'object' => $object?->getId(),
            'name' => $name,
        ];

        if (null !== $keyId) {
            $criteria['keyId'] = $keyId;
        }

        if ($multiple) {
            return $repository->findBy($criteria);
        } else {
            return $repository->findOneBy($criteria);
        }
    }

    public function deleteFileDataById(WorkspaceIntegration $workspaceIntegration, string $id): void
    {
        $this->deleteById(IntegrationFileData::class, $workspaceIntegration, $id);
    }

    public function deleteBasketDataById(WorkspaceIntegration $workspaceIntegration, string $id): void
    {
        $this->deleteById(IntegrationBasketData::class, $workspaceIntegration, $id);
    }


    /**
     * @template T
     *
     * @param class-string<T> $class
     */
    private function deleteById(string $class, WorkspaceIntegration $workspaceIntegration, string $id): void
    {
        $data = $this->getById($class, $workspaceIntegration, $id);

        if (null !== $data) {
            $this->em->remove($data);
            $this->em->flush($data);
        }
    }

    /**
     * @template T
     *
     * @param class-string<T> $class
     */
    public function getById(string $class, WorkspaceIntegration $workspaceIntegration, string $id): ?AbstractIntegrationData
    {
        return $this->em->getRepository($class)
            ->findOneBy([
                'id' => $id,
                'integration' => $workspaceIntegration->getId(),
            ]);
    }
}
