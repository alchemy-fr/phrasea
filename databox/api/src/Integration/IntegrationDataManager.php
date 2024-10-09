<?php

declare(strict_types=1);

namespace App\Integration;

use Alchemy\CoreBundle\Entity\AbstractUuidEntity;
use App\Entity\Integration\IntegrationData;
use App\Entity\Integration\WorkspaceIntegration;
use App\Repository\Integration\IntegrationDataRepository;
use Arthem\ObjectReferenceBundle\Mapper\ObjectMapper;
use Doctrine\ORM\EntityManagerInterface;

readonly class IntegrationDataManager
{
    public function __construct(
        private EntityManagerInterface $em,
        private IntegrationDataRepository $repository,
        private ObjectMapper $objectMapper,
    ) {
    }

    public function getWorkspaceIntegration(string $id): WorkspaceIntegration
    {
        $workspaceIntegration = $this->em->find(WorkspaceIntegration::class, $id);
        if (!$workspaceIntegration instanceof WorkspaceIntegration) {
            throw new \InvalidArgumentException(sprintf('WorkspaceIntegration %s not found', $id));
        }

        return $workspaceIntegration;
    }

    public function storeData(WorkspaceIntegration $workspaceIntegration, ?string $userId, ?AbstractUuidEntity $object, string $name, string $value, ?string $keyId = null, bool $multiple = false): IntegrationData
    {
        $data = null;
        if (!$multiple || null !== $keyId) {
            $data = $this->getData($workspaceIntegration, $userId, $object, $name, $keyId);
        }

        if (null === $data) {
            $data = new IntegrationData();
            $data->setIntegration($workspaceIntegration);
            $data->setObject($object);
            $data->setName($name);
        }
        $data->setValue($value);
        $data->setUserId($userId);
        $data->setKeyId($keyId);

        $this->em->persist($data);
        $this->em->flush();

        return $data;
    }

    public function findBy(array $criteria, ?array $orderBy = null, ?int $limit = null, ?int $offset = null): array
    {
        return $this->repository->findBy($this->normalizeCriteria($criteria), $orderBy, $limit, $offset);
    }

    public function findOneBy(array $criteria, ?array $orderBy = null): ?IntegrationData
    {
        return $this->repository->findOneBy($this->normalizeCriteria($criteria), $orderBy);
    }

    private function normalizeCriteria(array $criteria): array
    {
        if (array_key_exists('object', $criteria)) {
            $object = $criteria['object'];
            if ($object instanceof AbstractUuidEntity) {
                $criteria['objectType'] = $this->objectMapper->getObjectKey($object);
                $criteria['objectId'] = $object->getId();
            } else {
                $criteria['objectId'] = null;
            }

            unset($criteria['object']);
        }

        return $criteria;
    }

    public function getData(WorkspaceIntegration $workspaceIntegration, ?string $userId, ?AbstractUuidEntity $object, string $name, ?string $keyId = null, bool $multiple = false): IntegrationData|array|null
    {
        $criteria = [
            'integration' => $workspaceIntegration->getId(),
            'object' => $object,
            'name' => $name,
            'userId' => $userId,
        ];

        if (null !== $keyId) {
            $criteria['keyId'] = $keyId;
        }

        if ($multiple) {
            return $this->findBy($criteria);
        } else {
            return $this->findOneBy($criteria);
        }
    }

    public function deleteById(WorkspaceIntegration $workspaceIntegration, string $id, ?string $userId): void
    {
        $data = $this->getById($workspaceIntegration, $id, $userId);
        $this->em->remove($data);
        $this->em->flush($data);
    }

    public function getById(WorkspaceIntegration $workspaceIntegration, string $id, ?string $userId): IntegrationData
    {
        $data = $this->repository
            ->findOneBy([
                'id' => $id,
                'integration' => $workspaceIntegration->getId(),
                'userId' => $userId,
            ]);

        if (null === $data) {
            throw new \InvalidArgumentException(sprintf('%s "%s" not found', IntegrationData::class, $id));
        }

        return $data;
    }

    public function getByIdTrusted(string $id): IntegrationData
    {
        /** @var IntegrationData $data */
        $data = $this->repository->find($id);
        if (null === $data) {
            throw new \InvalidArgumentException(sprintf('%s "%s" not found', IntegrationData::class, $id));
        }

        return $data;
    }
}
