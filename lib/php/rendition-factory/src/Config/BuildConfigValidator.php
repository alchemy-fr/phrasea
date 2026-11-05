<?php

namespace Alchemy\RenditionFactory\Config;

use Alchemy\RenditionFactory\DTO\BuildConfig\BuildConfig;
use Alchemy\RenditionFactory\DTO\BuildConfig\Transformation;
use Alchemy\RenditionFactory\DTO\FamilyEnum;
use Alchemy\RenditionFactory\Transformer\GeneratorModuleInterface;
use Alchemy\RenditionFactory\Transformer\TransformerModuleInterface;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\Attribute\AutowireLocator;
use Symfony\Component\DependencyInjection\ServiceLocator;

readonly class BuildConfigValidator
{
    public function __construct(
        #[AutowireLocator(TransformerModuleInterface::TAG)]
        private ServiceLocator $transformers,
        #[AutowireLocator(GeneratorModuleInterface::TAG)]
        private ServiceLocator $generators,
    ) {
    }

    public function getTransformers(): ServiceLocator
    {
        return $this->transformers;
    }

    public function getGenerators(): ServiceLocator
    {
        return $this->generators;
    }

    public function validate(BuildConfig $config): void
    {
        foreach (FamilyEnum::cases() as $family) {
            $familyConfig = $config->getFamily($family);
            if (null === $familyConfig) {
                continue;
            }
            foreach ($familyConfig->getTransformations() as $transformation) {
                $this->validateTransformation($this->transformers, $transformation);
            }
        }

        if (null !== $generateConfig = $config->getGenerate()) {
            $enabledTransformations = $generateConfig->getEnabledTransformations();
            if (empty($enabledTransformations)) {
                throw new InvalidConfigurationException(sprintf('"%s" needs at least one enabled module', BuildConfig::GENERATE));
            }

            $generatorName = $enabledTransformations[0]->getModule();
            if (!$this->generators->has($generatorName)) {
                throw new InvalidConfigurationException(sprintf('The first module of "%s" must be a generator (%s), "%s" is not', BuildConfig::GENERATE, implode(', ', array_keys($this->generators->getProvidedServices())), $generatorName));
            }

            foreach ($generateConfig->getTransformations() as $transformation) {
                $this->validateTransformation(
                    $transformation === $enabledTransformations[0] ? $this->generators : $this->transformers,
                    $transformation,
                );
            }
        }
    }

    private function validateTransformation(ServiceLocator $modules, Transformation $transformation): void
    {
        $moduleName = $transformation->getModule();

        /** @var TransformerModuleInterface|GeneratorModuleInterface $module */
        $module = $modules->get($moduleName);

        try {
            $this->checkModuleConfiguration($module, $transformation->toArray());
        } catch (\Throwable $e) {
            $msg = sprintf("Error in module \"%s\"\n%s", $moduleName, $e->getMessage());
            throw new InvalidConfigurationException($msg);
        }
    }

    private function checkModuleConfiguration(TransformerModuleInterface|GeneratorModuleInterface $module, array $options): void
    {
        $documentation = $module->getDocumentation();
        $treeBuilder = $documentation->getTreeBuilder();

        $processor = new Processor();
        $processor->process($treeBuilder->buildTree(), ['root' => $options]);
    }
}
