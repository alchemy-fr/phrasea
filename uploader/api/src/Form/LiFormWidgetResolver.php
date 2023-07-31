<?php

declare(strict_types=1);

namespace App\Form;

use App\Form\Resolver\WidgetResolverInterface;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;
use Symfony\Component\Validator\Constraints\NotBlank;

class LiFormWidgetResolver
{
    /**
     * @var WidgetResolverInterface[]
     */
    private iterable $resolvers;

    public function __construct(
        #[TaggedIterator(WidgetResolverInterface::TAG)]
        iterable $resolvers
    )
    {
        $this->resolvers = $resolvers;
    }

    public function getFormType(array $config): string
    {
        foreach ($this->getSupportedResolvers($config) as $resolver) {
            return $resolver->getFormType($config);
        }

        throw new \InvalidArgumentException(sprintf('Unsupported field config %s', json_encode($config, JSON_THROW_ON_ERROR)));
    }

    public function getFieldOptions(array $fieldConfig): array
    {
        $fieldOptions = [
            'required' => $fieldConfig['required'] ?? false,
        ];

        if (isset($fieldConfig['title'])) {
            $fieldOptions['label'] = $fieldConfig['title'];
        }

        if ($fieldOptions['required']) {
            $fieldOptions['constraints'][] = new NotBlank();
        }

        foreach ($this->getSupportedResolvers($fieldConfig) as $resolver) {
            $fieldOptions = array_merge($fieldOptions, $resolver->getFormOptions($fieldConfig));
        }

        return $fieldOptions;
    }

    /**
     * @return WidgetResolverInterface[]
     */
    private function getSupportedResolvers(array $fieldConfig): iterable
    {
        foreach ($this->resolvers as $resolver) {
            $fieldConfig = $this->normalizeConfig($fieldConfig);
            if ($resolver->supports($fieldConfig)) {
                yield $resolver;
            }
        }
    }

    private function normalizeConfig(array $config): array
    {
        $config['type'] ??= 'string';
        $config['widget'] ??= 'text';
        $config['format'] ??= null;

        return $config;
    }
}
