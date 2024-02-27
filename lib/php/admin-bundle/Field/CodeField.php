<?php

namespace Alchemy\AdminBundle\Field;

use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use EasyCorp\Bundle\EasyAdminBundle\Field\FieldTrait;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\Form\Extension\Core\Type\TextType;

final class CodeField implements FieldInterface
{
    use FieldTrait;

    /**
     * @param string|false|null $label
     */
    public static function new(string $propertyName, $label = null): TextField
    {
        return TextField::new($propertyName, $label)
            ->setTemplatePath('@AlchemyAdmin/fields/code.html.twig')
            ->setFormType(TextType::class);
    }
}
