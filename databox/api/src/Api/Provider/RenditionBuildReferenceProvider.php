<?php

declare(strict_types=1);

namespace App\Api\Provider;

use Alchemy\RenditionFactory\BuildConfigDocumentation;
use Alchemy\RenditionFactory\Transformer\GeneratorModuleInterface;
use Alchemy\RenditionFactory\Transformer\TransformerModuleInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Documentation\ConfigurationReferenceDumper;
use App\Model\RenditionBuildReference;
use Symfony\Component\DependencyInjection\Attribute\AutowireLocator;
use Symfony\Component\DependencyInjection\ServiceLocator;

final readonly class RenditionBuildReferenceProvider implements ProviderInterface
{
    public function __construct(
        #[AutowireLocator(TransformerModuleInterface::TAG)]
        private ServiceLocator $transformers,
        #[AutowireLocator(GeneratorModuleInterface::TAG)]
        private ServiceLocator $generators,
        private BuildConfigDocumentation $buildConfigDocumentation,
        private ConfigurationReferenceDumper $referenceDumper,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object
    {
        $object = new RenditionBuildReference();
        $object->reference = $this->referenceDumper->dumpTree($this->buildConfigDocumentation->getTreeBuilder());

        foreach ([$this->transformers, $this->generators] as $modules) {
            foreach (array_keys($modules->getProvidedServices()) as $name) {
                /** @var TransformerModuleInterface|GeneratorModuleInterface $module */
                $module = $modules->get($name);
                $documentation = $module->getDocumentation();

                $object->references[] = [
                    'name' => $name,
                    'description' => trim($documentation->getHeader()) ?: null,
                    'reference' => $this->referenceDumper->dumpDocumentation($documentation),
                ];
            }
        }

        return $object;
    }
}
