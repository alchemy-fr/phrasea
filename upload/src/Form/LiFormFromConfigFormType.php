<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class LiFormFromConfigFormType extends AbstractType
{
    /**
     * @var LiFormWidgetResolver
     */
    private $widgetResolver;

    public function __construct(LiFormWidgetResolver $widgetRegistry)
    {
        $this->widgetResolver = $widgetRegistry;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $schema = $options['schema'];

        if (isset($schema['required'])) {
            foreach ($schema['required'] as $requiredField) {
                $schema['properties'][$requiredField]['required'] = true;
            }
        }

        foreach ($schema['properties'] as $name => $fieldConfig) {
            $builder->add($name, $this->widgetResolver->getFormType($fieldConfig), $this->widgetResolver->getFieldOptions($fieldConfig));
        }
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setRequired('schema');
        $resolver->setAllowedTypes('schema', ['array']);
    }
}
