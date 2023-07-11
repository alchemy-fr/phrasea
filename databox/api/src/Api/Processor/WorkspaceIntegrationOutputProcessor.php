<?php

declare(strict_types=1);

namespace App\Api\Processor;

use ApiPlatform\Metadata\Operation;
use App\Api\Model\Output\WorkspaceIntegrationOutput;
use App\Entity\Core\File;
use App\Entity\Integration\IntegrationData;
use App\Entity\Integration\WorkspaceIntegration;
use App\Integration\FileActionsIntegrationInterface;
use App\Integration\IntegrationInterface;
use App\Integration\IntegrationManager;
use Doctrine\ORM\EntityManagerInterface;
use GuzzleHttp\Psr7\Query;

class WorkspaceIntegrationOutputProcessor extends AbstractSecurityProcessor
{
    public function __construct(private readonly EntityManagerInterface $em, private readonly IntegrationManager $integrationManager)
    {
    }

    /**
     * @param WorkspaceIntegration $data
     */
    public function process($data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        $output = new WorkspaceIntegrationOutput();
        $output->setCreatedAt($data->getCreatedAt());
        $output->setUpdatedAt($data->getUpdatedAt());
        $output->setId($data->getId());
        $output->setTitle($data->getTitle());
        $output->setEnabled($data->isEnabled());
        $output->setIntegration($data->getIntegration());

        $uri = $context['request_uri'];
        $qs = parse_url((string) $uri, PHP_URL_QUERY);
        $filters = Query::parse($qs);

        $file = null;
        $fileId = $filters['fileId'] ?? null;
        if (null !== $fileId) {
            $file = $this->em->getRepository(File::class)->find($fileId);
            if (!$file instanceof File) {
                throw new \InvalidArgumentException(sprintf('File "%s" not found', $fileId));
            }
        }

        if (null !== $file) {
            /** @var IntegrationData[] $data */
            $data = $this->em->getRepository(IntegrationData::class)
                ->findBy([
                    'integration' => $data->getId(),
                    'file' => $file->getId(),
                ]);

            $output->setData($data);
        }

        $config = $this->integrationManager->getIntegrationConfiguration($data);
        /** @var IntegrationInterface $integration */
        $integration = $config['integration'];
        $output->setConfig($integration->resolveClientConfiguration($data, $config));

        if (null !== $file) {
            if ($integration instanceof FileActionsIntegrationInterface) {
                $output->setSupported($integration->supportsFileActions($file, $config));
            }
        }

        return $output;
    }

    public function supportsTransformation($data, string $to, array $context = []): bool
    {
        return WorkspaceIntegrationOutput::class === $to && $data instanceof WorkspaceIntegration;
    }
}
