<?php

declare(strict_types=1);

namespace Alchemy\AclBundle\Form;

use Alchemy\AclBundle\Mapping\ObjectMapping;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ObjectTypeFormType extends AbstractType
{
    private ObjectMapping $objectMapping;

    public function __construct(ObjectMapping $objectMapping)
    {
        $this->objectMapping = $objectMapping;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $choices = [];
        foreach ($this->objectMapping->getObjectTypes() as $name) {
            $choices[$name] = $name;
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
