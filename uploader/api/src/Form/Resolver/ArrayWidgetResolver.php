<?php

declare(strict_types=1);

namespace App\Form\Resolver;

use Symfony\Component\Form\Extension\Core\Type\ChoiceType;

class ArrayWidgetResolver implements WidgetResolverInterface
{
    public function getFormType(array $config): string
    {
        return ChoiceType::class;
    }

    public function getFormOptions(array $config): array
    {
        $choices = [];
        if (isset($config['items'])) {
            $items = $config['items'];

            if (isset($items['enum_titles'])) {
                for ($i = 0; $i < count($items['enum_titles']); $i++) {
                    $choices[$items['enum_titles'][$i]] = $items['enum'][$i];
                }
            } else {
                for ($i = 0; $i < count($items['enum']); $i++) {
                    $choices[$items['enum'][$i]] = $items['enum'][$i];
                }
            }
        }

        return [
            'multiple' => true,
            'choices' => $choices,
        ];
    }

    public function supports(array $config): bool
    {
        return in_array($config['type'], [
                'array',
            ], true);
    }
}
