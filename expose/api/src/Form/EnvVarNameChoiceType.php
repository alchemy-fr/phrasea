<?php

declare(strict_types=1);

namespace App\Form;

use App\ConfigurationManager;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class EnvVarNameChoiceType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver)
    {
        $choices = [];
        $config = array_filter(ConfigurationManager::CONFIG, function (array $c): bool {
            return $c['overridableInAdmin'] ?? true;
        });
        foreach ($config as $c) {
            $key = $c['name'];
            $choices[$key] = $key;
        }

        $resolver->setDefaults([
            'choices' => $choices,
        ]);
    }

    public function getParent()
    {
        return ChoiceType::class;
    }
}
