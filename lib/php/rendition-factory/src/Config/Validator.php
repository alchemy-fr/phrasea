<?php

namespace Alchemy\RenditionFactory\Config;

use Alchemy\RenditionFactory\DTO\BuildConfig\BuildConfig;
use Alchemy\RenditionFactory\DTO\FamilyEnum;
use Alchemy\RenditionFactory\Transformer\TransformerModuleInterface;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\Attribute\TaggedLocator;
use Symfony\Component\DependencyInjection\ServiceLocator;

class Validator
{
    public function __construct(
        #[TaggedLocator(TransformerModuleInterface::TAG, defaultIndexMethod: 'getName')]
        private readonly ServiceLocator $transformers,
    ) {

    }

    public function getTransformers(): ServiceLocator
    {
        return $this->transformers;
    }

    public function validate(BuildConfig $config): void
    {
        foreach (FamilyEnum::cases() as $family) {
            $familyConfig = $config->getFamily($family);
            if (null === $familyConfig) {
                continue;
            }
            foreach ($familyConfig->getTransformations() as $transformation) {
                $transformerName = $transformation->getModule();

                /** @var TransformerModuleInterface $transformer */
                $transformer = $this->transformers->get($transformerName);

                try {
                    $this->checkTransformerConfiguration($transformer, $transformation->toArray());
                } catch (\Throwable $e) {
                    $msg = sprintf("Error in module \"%s\"\n%s", $transformerName, $e->getMessage());
                    throw new InvalidConfigurationException($msg);
                }
            }
        }
    }

    private function checkTransformerConfiguration(TransformerModuleInterface $transformer, array $options): void
    {
        $documentation = $transformer->getDocumentation();
        $treeBuilder = $documentation->getTreeBuilder();

        $processor = new Processor();
        $processor->process($treeBuilder->buildTree(), ['root' => $options]);
    }
}
