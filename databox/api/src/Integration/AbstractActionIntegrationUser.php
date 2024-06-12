<?php

declare(strict_types=1);

namespace App\Integration;

use App\Entity\Integration\IntegrationData;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Contracts\Service\Attribute\Required;

abstract class AbstractActionIntegrationUser extends AbstractIntegration implements UserActionsIntegrationInterface
{
    protected EntityManagerInterface $em;
    protected IntegrationDataManager $integrationDataManager;
    private SerializerInterface $serializer;

    protected function serializeData(IntegrationData $data): string
    {
        return $this->serializer->serialize($data, 'json', [
            'groups' => [IntegrationData::GROUP_LIST, '_'],
        ]);
    }

    protected function createNewDataResponse(mixed $data): JsonResponse
    {
        return new JsonResponse($this->serializeData($data), 201, [], true);
    }

    #[Required]
    public function setIntegrationDataManager(IntegrationDataManager $integrationDataManager): void
    {
        $this->integrationDataManager = $integrationDataManager;
    }

    #[Required]
    public function setSerializer(SerializerInterface $serializer): void
    {
        $this->serializer = $serializer;
    }
}
