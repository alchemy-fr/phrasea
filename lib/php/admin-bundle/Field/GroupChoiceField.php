<?php

namespace Alchemy\AdminBundle\Field;

use Alchemy\AdminBundle\Form\GroupChoiceType;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use EasyCorp\Bundle\EasyAdminBundle\Field\FieldTrait;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;

final class GroupChoiceField implements FieldInterface
{
    use FieldTrait;

    /**s
     * @param string|false|null $label
     */
    public static function new(string $propertyName, $label = null): self
    {
       return (new self())
            ->setFormTypeOptions([
                'multiple' => true,
                'expanded' => true
            ])
            ->setProperty($propertyName)
            ->setLabel($label)
            ->setFormType(GroupChoiceType::class)
            ->setHelp('If no group is selected, the target will be allowed to any user.')
            ;
    }
}
