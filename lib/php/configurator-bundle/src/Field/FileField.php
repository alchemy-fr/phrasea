<?php

namespace Alchemy\ConfiguratorBundle\Field;

use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use EasyCorp\Bundle\EasyAdminBundle\Field\FieldTrait;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Contracts\Translation\TranslatableInterface;

final class FileField implements FieldInterface
{
    use FieldTrait;

    /**
     * @param TranslatableInterface|string|false|null $label
     */
    public static function new(string $propertyName, $label = null): self
    {
        return (new self())
            ->setProperty($propertyName)
            ->setLabel($label)
            ->setFormType(FileType::class)
            ->setCssClass('field-file')
            ->addJsFiles('bundles/alchemyconfigurator/js/file-field.js')
        ;
    }
}
