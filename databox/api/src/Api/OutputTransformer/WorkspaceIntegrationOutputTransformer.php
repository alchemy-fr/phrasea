<?php

declare(strict_types=1);

namespace App\Api\OutputTransformer;

use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use App\Api\Model\Output\WorkspaceIntegrationOutput;
use App\Entity\Basket\Basket;
use App\Entity\Core\File;
use App\Entity\Integration\AbstractIntegrationData;
use App\Entity\Integration\IntegrationBasketData;
use App\Entity\Integration\IntegrationFileData;
use App\Entity\Integration\WorkspaceIntegration;
use App\Integration\FileActionsIntegrationInterface;
use App\Integration\IntegrationDataManager;
use App\Integration\IntegrationManager;
use App\Repository\Integration\IntegrationTokenRepository;
use App\Security\Voter\AbstractVoter;
use Doctrine\ORM\EntityManagerInterface;
use GuzzleHttp\Psr7\Query;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class WorkspaceIntegrationOutputTransformer implements OutputTransformerInterface
{
    use SecurityAwareTrait;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly IntegrationManager $integrationManager,
        private readonly IntegrationDataManager $integrationDataManager,
        private readonly IntegrationTokenRepository $integrationTokenRepository,
    ) {
    }

    public function supports(string $outputClass, object $data): bool
    {
        return WorkspaceIntegrationOutput::class === $outputClass && $data instanceof WorkspaceIntegration;
    }

    /**
     * @param WorkspaceIntegration $data
     */
    public function transform(object $data, string $outputClass, array &$context = []): object
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

        $object = null;
        $objectId = $filters['objectId'] ?? null;
        if (null !== $objectId) {
            $type = $filters['type'] ?? throw new BadRequestHttpException('Missing integration type to fetch data');
            $class = [
                'basket' => Basket::class,
                'file' => File::class,
            ][$type] ?? throw new \InvalidArgumentException(sprintf('Invalid type "%s"', $type));

            $object = $this->em->getRepository($class)->find($objectId);
            if (null === $object) {
                throw new \InvalidArgumentException(sprintf('%s "%s" not found', $class, $objectId));
            }
            $this->denyAccessUnlessGranted(AbstractVoter::READ, $object);

            $dataClass = [
                'basket' => IntegrationBasketData::class,
                'file' => IntegrationFileData::class,
            ][$type] ?? throw new \InvalidArgumentException(sprintf('Invalid type "%s"', $type));

            /** @var AbstractIntegrationData[] $subData */
            $subData = $this->em->getRepository($dataClass)
                ->findBy([
                    'integration' => $data->getId(),
                    'object' => $object->getId(),
                ]);

            $output->setData($subData);
        }

        $config = $this->integrationManager->getIntegrationConfiguration($data);
        $integration = $config->getIntegration();
        $output->setConfig($integration->resolveClientConfiguration($data, $config));

        if ($object instanceof File && $integration instanceof FileActionsIntegrationInterface) {
            $output->setSupported($integration->supportsFileActions($object, $config));
        }

        $tokens = $this->integrationTokenRepository->getValidUserTokens($data->getId(), $this->getStrictUser()->getId());
        $output->setTokens($tokens);

        return $output;
    }
}
