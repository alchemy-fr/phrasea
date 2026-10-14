<?php

declare(strict_types=1);

namespace App\Api\Mapper\Input;

use App\Api\Model\Input\WorkspaceIntegrationInput;
use App\Api\Processor\WithOwnerIdProcessorTrait;
use App\Entity\Integration\WorkspaceIntegration;
use App\Integration\IntegrationInterface;
use App\Integration\IntegrationRegistry;
use App\Model\IntegrationType;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

#[AsTaggedItem(index: WorkspaceIntegrationInput::class)]
class WorkspaceIntegrationInputMapper extends AbstractInputMapper implements InputMapperInterface
{
    use WithOwnerIdProcessorTrait;

    public function __construct(
        private readonly IntegrationRegistry $integrationRegistry,
    ) {
    }

    /**
     * @param WorkspaceIntegrationInput $data
     */
    public function map(object $data, ?object $target, array $context = []): ?object
    {
        $isNew = null === $target;
        /** @var WorkspaceIntegration $object */
        $object = $target ?? new WorkspaceIntegration();
        if (null !== $data->name) {
            $object->setName($data->name);
        }

        $integrationTypeName = IntegrationType::denormalizeId($data->integration ?? '');

        if ($isNew) {
            if (null !== $data->workspace) {
                $object->setWorkspace($data->workspace);
            }
            $object->setIntegration($integrationTypeName);
        }

        $integration = $this->integrationRegistry->getIntegration($object->getIntegration());

        if (null !== $data->configYaml) {
            try {
                $object->setConfig(Yaml::parse($data->configYaml) ?? []);
            } catch (ParseException $e) {
                throw new BadRequestHttpException(sprintf('Invalid YAML configuration: %s', $e->getMessage()), $e);
            }
        } elseif (null !== $data->config) {
            $object->setConfig($data->config);
        }

        if ($isNew) {
            if ($integration instanceof IntegrationInterface) {
                $object->setConfig($integration->generateConfigurationDefaults($object->getConfig()));
            }
        }

        if (null !== $data->enabled) {
            $object->setEnabled($data->enabled);
        }

        if (null !== $data->public) {
            $object->setPublic($data->public);
        }

        if (null !== $data->needs) {
            $needs = $object->getNeeds();
            $needs->clear();
            foreach ($data->needs as $need) {
                $needs->add($need);
            }
        }
        if (null !== $data->if) {
            $object->setIf($data->if ?: null);
        }

        $object = $this->processOwnerId($object);

        $this->validator->validate($object, $context); // Validate before normalization

        if ($integration instanceof IntegrationInterface) {
            $object->setConfig($integration->normalizeConfiguration($object->getConfig(), $object->getWorkspace()));
        }

        return $object;
    }
}
