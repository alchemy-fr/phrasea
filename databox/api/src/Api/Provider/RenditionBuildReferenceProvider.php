<?php

declare(strict_types=1);

namespace App\Api\Provider;

use Alchemy\RenditionFactory\BuildConfigDocumentation;
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
        #[AutowireLocator(TransformerModuleInterface::TAG, defaultIndexMethod: 'getName')]
        private ServiceLocator $transformers,
        private BuildConfigDocumentation $buildConfigDocumentation,
        private ConfigurationReferenceDumper $referenceDumper,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object
    {
        $object = new RenditionBuildReference();
        $object->reference = $this->referenceDumper->dumpTree($this->buildConfigDocumentation->getTreeBuilder());

        foreach (array_keys($this->transformers->getProvidedServices()) as $name) {
            /** @var TransformerModuleInterface $transformer */
            $transformer = $this->transformers->get($name);
            $documentation = $transformer->getDocumentation();

            $object->references[] = [
                'name' => $name,
                'description' => trim($documentation->getHeader()) ?: null,
                'reference' => $this->referenceDumper->dumpDocumentation($documentation),
            ];
        }

        return $object;
    }
}
