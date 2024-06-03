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
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

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

    public function storeFileData(WorkspaceIntegration $workspaceIntegration, ?string $userId, ?File $file, string $name, string $value, ?string $keyId = null, bool $multiple = false): IntegrationFileData
    {
        return $this->storeData(IntegrationFileData::class, $workspaceIntegration, $userId, $file, $name, $value, $keyId, $multiple);
    }

    public function storeBasketData(WorkspaceIntegration $workspaceIntegration, ?string $userId, Basket $basket, string $name, string $value, ?string $keyId = null, bool $multiple = false): IntegrationBasketData
    {
        return $this->storeData(IntegrationBasketData::class, $workspaceIntegration, $userId, $basket, $name, $value, $keyId, $multiple);
    }

    /**
     * @template T
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    public function storeData(string $class, WorkspaceIntegration $workspaceIntegration, ?string $userId, ?AbstractUuidEntity $object, string $name, string $value, ?string $keyId = null, bool $multiple = false): AbstractIntegrationData
    {
        $data = null;
        if (!$multiple || null !== $keyId) {
            $data = $this->getData($class, $workspaceIntegration, $userId, $object, $name, $keyId);
        }

        if (null === $data) {
            /** @var AbstractIntegrationData $data */
            $data = new $class;
            $data->setIntegration($workspaceIntegration);
            $data->setObject($object);
            $data->setName($name);
        }
        $data->setValue($value);
        $data->setUserId($userId);
        $data->setKeyId($keyId);

        $this->em->persist($data);
        $this->em->flush($data);

        return $data;
    }

    /**
     * @return IntegrationFileData|IntegrationFileData[]|null
     */
    public function getFileData(WorkspaceIntegration $workspaceIntegration, ?string $userId, ?File $file, string $name, ?string $keyId = null, bool $multiple = false): IntegrationFileData|array|null
    {
        return $this->getData(IntegrationFileData::class, $workspaceIntegration, $userId, $file, $name, $keyId, $multiple);
    }

    /**
     * @return IntegrationBasketData|IntegrationBasketData[]|null
     */
    public function getBasketData(WorkspaceIntegration $workspaceIntegration, ?string $userId, ?Basket $basket, string $name, ?string $keyId = null, bool $multiple = false): IntegrationBasketData|array|null
    {
        return $this->getData(IntegrationBasketData::class, $workspaceIntegration, $userId, $basket, $name, $keyId, $multiple);
    }

    /**
     * @template T
     *
     * @param class-string<T> $class
     *
     * @return T|T[]|null
     */
    private function getData(string $class, WorkspaceIntegration $workspaceIntegration, ?string $userId, ?AbstractUuidEntity $object, string $name, ?string $keyId = null, bool $multiple = false): object|array|null
    {
        $repository = $this->em->getRepository($class);

        $criteria = [
            'integration' => $workspaceIntegration->getId(),
            'object' => $object?->getId(),
            'name' => $name,
            'userId' => $userId,
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

    public function deleteFileDataById(WorkspaceIntegration $workspaceIntegration, string $id, ?string $userId): void
    {
        $this->deleteById(IntegrationFileData::class, $workspaceIntegration, $id, $userId);
    }

    public function deleteBasketDataById(WorkspaceIntegration $workspaceIntegration, string $id, ?string $userId): void
    {
        $this->deleteById(IntegrationBasketData::class, $workspaceIntegration, $id, $userId);
    }


    /**
     * @template T
     *
     * @param class-string<T> $class
     */
    private function deleteById(string $class, WorkspaceIntegration $workspaceIntegration, string $id, ?string $userId): void
    {
        $data = $this->getById($class, $workspaceIntegration, $id, $userId);
        $this->em->remove($data);
        $this->em->flush($data);
    }

    /**
     * @template T
     *
     * @param class-string<T> $class
     */
    public function getById(string $class, WorkspaceIntegration $workspaceIntegration, string $id, ?string $userId): AbstractIntegrationData
    {
        $data = $this->em->getRepository($class)
            ->findOneBy([
                'id' => $id,
                'integration' => $workspaceIntegration->getId(),
                'userId' => $userId,
            ]);

        if (null === $data) {
            throw new \InvalidArgumentException(sprintf('%s "%s" not found', $class, $id));
        }

        return $data;
    }

    /**
     * @template T
     *
     * @param class-string<T> $class
     */
    public function getByIdTrusted(string $class, string $id): AbstractIntegrationData
    {
        /** @var AbstractIntegrationData $data */
        $data = $this->em->find($class, $id);
        if (null === $data) {
            throw new \InvalidArgumentException(sprintf('%s "%s" not found', $class, $id));
        }

        return $data;
    }
}
