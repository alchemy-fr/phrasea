<?php

declare(strict_types=1);

namespace App\Api\Provider;

use Alchemy\RenditionFactory\Transformer\TransformerModuleInterface;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Documentation\ConfigurationReferenceDumper;
use App\Model\RenditionBuildModule;
use Symfony\Component\DependencyInjection\Attribute\AutowireLocator;
use Symfony\Component\DependencyInjection\ServiceLocator;

final readonly class RenditionBuildModuleProvider implements ProviderInterface
{
    public function __construct(
        #[AutowireLocator(TransformerModuleInterface::TAG, defaultIndexMethod: 'getName')]
        private ServiceLocator $transformers,
        private ConfigurationReferenceDumper $referenceDumper,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        if ($operation instanceof GetCollection) {
            return array_map(
                fn (string $name): RenditionBuildModule => $this->createModule($name),
                array_keys($this->transformers->getProvidedServices())
            );
        }

        $name = $uriVariables['id'];
        if (!$this->transformers->has($name)) {
            return null;
        }

        return $this->createModule($name);
    }

    private function createModule(string $name): RenditionBuildModule
    {
        /** @var TransformerModuleInterface $transformer */
        $transformer = $this->transformers->get($name);

        $documentation = $transformer->getDocumentation();

        $module = new RenditionBuildModule();
        $module->id = $name;
        $module->name = $name;
        $module->description = trim($documentation->getHeader()) ?: null;
        $module->reference = $this->referenceDumper->dumpDocumentation($documentation);

        return $module;
    }
}
