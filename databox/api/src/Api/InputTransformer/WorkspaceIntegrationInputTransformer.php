<?php

declare(strict_types=1);

namespace App\Api\InputTransformer;

use App\Api\Model\Input\WorkspaceIntegrationInput;
use App\Api\Processor\WithOwnerIdProcessorTrait;
use App\Entity\Integration\WorkspaceIntegration;
use App\Model\IntegrationType;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Yaml\Yaml;

class WorkspaceIntegrationInputTransformer extends AbstractInputTransformer
{
    use WithOwnerIdProcessorTrait;

    /**
     * @param WorkspaceIntegrationInput $data
     */
    public function transform(object $data, string $resourceClass, array $context = []): object|iterable
    {
        $this->validator->validate($data, $context);

        $isNew = !isset($context[AbstractNormalizer::OBJECT_TO_POPULATE]);
        /** @var WorkspaceIntegration $object */
        $object = $context[AbstractNormalizer::OBJECT_TO_POPULATE] ?? new WorkspaceIntegration();
        if (null !== $data->title) {
            $object->setTitle($data->title);
        }

        if ($isNew) {
            if (null !== $data->workspace) {
                $object->setWorkspace($data->workspace);
            }
            $object->setIntegration(IntegrationType::denormalizeId($data->integration));
        }

        if (null !== $data->configYaml) {
            $object->setConfig(Yaml::parse($data->configYaml) ?? []);
        } elseif (null !== $data->config) {
            $object->setConfig($data->config);
        }
        $object->setEnabled($data->enabled);

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

        return $this->processOwnerId($object);
    }

    public function supports(string $resourceClass, object $data): bool
    {
        return WorkspaceIntegration::class === $resourceClass && $data instanceof WorkspaceIntegrationInput;
    }
}
