<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class LiFormFromSchemaFormType extends AbstractType
{
    public function __construct(private readonly LiFormWidgetResolver $widgetResolver)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $schema = $options['schema'] ?? [];

        if (isset($schema['required'])) {
            foreach ($schema['required'] as $requiredField) {
                $schema['properties'][$requiredField]['required'] = true;
            }
        }

        foreach ($schema['properties'] ?? [] as $name => $fieldConfig) {
            $builder->add($name, $this->widgetResolver->getFormType($fieldConfig), $this->widgetResolver->getFieldOptions($fieldConfig));
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setRequired('schema');
        $resolver->setAllowedTypes('schema', ['array']);
    }
}
