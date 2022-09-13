<?php

declare(strict_types=1);

namespace App\Api\DataTransformer;

use App\Api\Model\Output\WorkspaceIntegrationOutput;
use App\Entity\Integration\IntegrationData;
use App\Entity\Integration\WorkspaceIntegration;
use Doctrine\ORM\EntityManagerInterface;
use GuzzleHttp\Psr7\Query;
use function GuzzleHttp\Psr7\parse_query;

class WorkspaceIntegrationOutputDataTransformer extends AbstractSecurityDataTransformer
{
    private EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    /**
     * @param WorkspaceIntegration $object
     */
    public function transform($object, string $to, array $context = [])
    {
        $output = new WorkspaceIntegrationOutput();
        $output->setCreatedAt($object->getCreatedAt());
        $output->setUpdatedAt($object->getUpdatedAt());
        $output->setId($object->getId());
        $output->setTitle($object->getTitle());
        $output->setEnabled($object->isEnabled());
        $output->setIntegration($object->getIntegration());

        $uri = $context['request_uri'];
        $qs = parse_url($uri, PHP_URL_QUERY);
        $filters = Query::parse($qs);

        if (isset($filters['assetId'])) {
            /** @var IntegrationData[] $data */
            $data = $this->em->getRepository(IntegrationData::class)
                ->findBy([
                    'integration' => $object->getId(),
                    'asset' => $filters['assetId'],
                ]);

            $output->setData($data);
        }

        return $output;
    }

    public function supportsTransformation($data, string $to, array $context = []): bool
    {
        return WorkspaceIntegrationOutput::class === $to && $data instanceof WorkspaceIntegration;
    }
}
