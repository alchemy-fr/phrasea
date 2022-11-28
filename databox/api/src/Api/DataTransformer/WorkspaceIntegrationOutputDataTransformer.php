<?php

declare(strict_types=1);

namespace App\Api\DataTransformer;

use App\Api\Model\Output\WorkspaceIntegrationOutput;
use App\Entity\Core\File;
use App\Entity\Integration\IntegrationData;
use App\Entity\Integration\WorkspaceIntegration;
use App\Integration\FileActionsIntegrationInterface;
use App\Integration\IntegrationInterface;
use App\Integration\IntegrationManager;
use Doctrine\ORM\EntityManagerInterface;
use GuzzleHttp\Psr7\Query;
use InvalidArgumentException;

class WorkspaceIntegrationOutputDataTransformer extends AbstractSecurityDataTransformer
{
    private EntityManagerInterface $em;
    private IntegrationManager $integrationManager;

    public function __construct(EntityManagerInterface $em, IntegrationManager $integrationManager)
    {
        $this->em = $em;
        $this->integrationManager = $integrationManager;
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

        $file = null;
        $fileId = $filters['fileId'] ?? null;
        if (null !== $fileId) {
            $file = $this->em->getRepository(File::class)->find($fileId);
            if (!$file instanceof File) {
                throw new InvalidArgumentException(sprintf('File "%s" not found', $fileId));
            }
        }

        if (null !== $file) {
            /** @var IntegrationData[] $data */
            $data = $this->em->getRepository(IntegrationData::class)
                ->findBy([
                    'integration' => $object->getId(),
                    'file' => $file->getId(),
                ]);

            $output->setData($data);
        }

        $options = $this->integrationManager->getIntegrationConfiguration($object);
        /** @var IntegrationInterface $integration */
        $integration = $options['integration'];
        $output->setOptions($integration->resolveClientOptions($object, $options));

        if (null !== $file) {
            if ($integration instanceof FileActionsIntegrationInterface) {
                $output->setSupported($integration->supportsFileActions($file, $options));
            }
        }

        return $output;
    }

    public function supportsTransformation($data, string $to, array $context = []): bool
    {
        return WorkspaceIntegrationOutput::class === $to && $data instanceof WorkspaceIntegration;
    }
}
