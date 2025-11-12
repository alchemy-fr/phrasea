<?php

namespace Alchemy\ConfiguratorBundle\Form\Type;

use Alchemy\ConfiguratorBundle\Schema\SchemaProperty;
use Alchemy\ConfiguratorBundle\Service\ConfigurationReference;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ConfigurationKeyType extends AbstractType
{
    public function __construct(
        private ConfigurationReference $configurationReference,
    ) {
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $choices = [];
        $visitor = function (SchemaProperty $prop, array &$choices, ?string $parentPath = null) use (&$visitor): void {
            $path = $parentPath ? $parentPath.'.'.$prop->name : $prop->name;
            if (empty($prop->children)) {
                $choices[$path] = $path;

                return;
            }

            foreach ($prop->children as $child) {
                $visitor($child, $choices, $path);
            }
        };

        foreach ($this->configurationReference->getSchemas() as $schema) {
            $subChoices = [];

            foreach ($schema->getSchema() as $prop) {
                $visitor($prop, $subChoices, $schema->getRootKey());
            }

            $choices[$schema->getTitle()] = $subChoices;
        }

        dump($choices);

        $resolver->setDefaults([
            'choices' => $choices,
            'placeholder' => 'Select a configuration key',
            'required' => true,
        ]);
    }

    public function getParent()
    {
        return ChoiceType::class;
    }
}
