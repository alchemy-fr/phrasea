<?php

declare(strict_types=1);

namespace App\Form;

use App\Form\Resolver\WidgetResolverInterface;
use InvalidArgumentException;
use Symfony\Component\Validator\Constraints\NotBlank;

class LiFormWidgetResolver
{
    /**
     * @var WidgetResolverInterface[]
     */
    private $resolvers = [];

    public function addResolver(WidgetResolverInterface $resolver)
    {
        $this->resolvers[] = $resolver;
    }

    public function getFormType(array $config): string
    {
        foreach ($this->getSupportedResolvers($config) as $resolver) {
            return $resolver->getFormType($config);
        }

        throw new InvalidArgumentException(sprintf('Unsupported field config %s', json_encode($config)));
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
            if ($resolver->supports($fieldConfig)) {
                yield $resolver;
            }
        }
    }
}
