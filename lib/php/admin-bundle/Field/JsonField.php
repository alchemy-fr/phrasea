<?php

namespace Alchemy\AdminBundle\Field;

use Alchemy\AdminBundle\Form\JsonType;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use EasyCorp\Bundle\EasyAdminBundle\Field\FieldTrait;

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
            ->setTemplatePath('@AlchemyAdmin/list/json.html.twig')
            ->setFormType(JsonType::class);
    }
}
