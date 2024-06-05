<?php

declare(strict_types=1);

namespace App\Integration\Action;

use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use App\Entity\Integration\IntegrationData;
use App\Integration\IntegrationDataManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Contracts\Service\Attribute\Required;

trait UserActionsTrait
{
    use SecurityAwareTrait;

    protected EntityManagerInterface $em;
    protected IntegrationDataManager $integrationDataManager;
    protected SerializerInterface $serializer;

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

    #[Required]
    public function setEm(EntityManagerInterface $em): void
    {
        $this->em = $em;
    }
}
