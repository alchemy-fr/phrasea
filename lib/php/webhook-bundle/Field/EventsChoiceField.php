<?php

declare(strict_types=1);

namespace Alchemy\WebhookBundle\Field;

use Alchemy\WebhookBundle\Form\EventsChoiceType;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use EasyCorp\Bundle\EasyAdminBundle\Field\FieldTrait;

final class EventsChoiceField implements FieldInterface
{
    use FieldTrait;

    public static function new(string $propertyName, $label = null): self
    {
        return (new self())
            ->setProperty($propertyName)
            ->setLabel($label)
            ->setFormType(EventsChoiceType::class)
            ;
    }
}
