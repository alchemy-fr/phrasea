<?php

namespace Alchemy\AdminBundle\Field;

use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use EasyCorp\Bundle\EasyAdminBundle\Field\FieldTrait;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;

final class JsonField implements FieldInterface
{
    use FieldTrait;

    /**
     * @param string|false|null $label
     */
    public static function new(string $propertyName, $label = null): self
    {
        return (new self())
            ->setProperty($propertyName)
            ->setLabel($label)

            // this template is used in 'index' and 'detail' pages
            ->setTemplatePath('@AlchemyAdmin/list/json.html.twig')

            // this is used in 'edit' and 'new' pages to edit the field contents
            // you can use your own form types too
            ->setFormType(TextareaType::class);
    }
}
