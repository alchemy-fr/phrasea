<?php

declare(strict_types=1);

namespace App\Form;

use App\ConfigurationManager;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class EnvVarNameChoiceType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $choices = [];
        $config = array_filter(ConfigurationManager::CONFIG, fn (array $c): bool => $c['overridableInAdmin'] ?? true);
        foreach ($config as $c) {
            $key = $c['name'];
            $choices[$key] = $key;
        }

        $resolver->setDefaults([
            'choices' => $choices,
        ]);
    }

    public function getParent(): ?string
    {
        return ChoiceType::class;
    }
}
