<?php

declare(strict_types=1);

namespace App\Form\Type;

use App\Integration\IntegrationRegistry;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class IntegrationChoiceType extends AbstractType
{
    private IntegrationRegistry $integrationRegistry;

    public function __construct(IntegrationRegistry $integrationRegistry)
    {
        $this->integrationRegistry = $integrationRegistry;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $choices = [];
        foreach ($this->integrationRegistry->getIntegrations() as $type) {
            $choices[$type::getTitle()] = $type::getName();
        }

        $resolver->setDefaults([
            'choices' => $choices,
            'placeholder' => 'Choose...',
        ]);
    }

    public function getParent()
    {
        return ChoiceType::class;
    }
}
